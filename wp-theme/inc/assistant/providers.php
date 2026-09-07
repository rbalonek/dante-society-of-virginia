<?php
/**
 * Dante Assistant — AI provider adapter.
 *
 * A thin interface so providers/models can be swapped later. Slice 1 ships one
 * implementation (Anthropic). To add another provider, implement the interface
 * and register it in dante_assistant_provider().
 *
 * Messages use a provider-neutral, content-block shape (which happens to mirror
 * Anthropic's):
 *   [ 'role' => 'user'|'assistant', 'content' => [ block, block, ... ] ]
 * where a block is one of:
 *   [ 'type'=>'text', 'text'=>'...' ]
 *   [ 'type'=>'tool_use', 'id'=>'...', 'name'=>'...', 'input'=>[...] ]
 *   [ 'type'=>'tool_result', 'tool_use_id'=>'...', 'content'=>'...' ]
 *
 * @package Dante_Society
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

interface Dante_AI_Provider {
    /**
     * @param string  $system   System prompt.
     * @param array   $messages Provider-neutral messages (see file header).
     * @param array[] $tools    Tool schemas from dante_assistant_tools().
     * @return array|WP_Error {
     *     @type string $stop              'tool' if the model wants to call tools, else 'end'.
     *     @type string $text              Assistant text (may be empty on a tool turn).
     *     @type array  $tool_calls        [ ['id','name','input'], ... ].
     *     @type array  $assistant_content Raw assistant content blocks, to append to history.
     * }
     */
    public function chat( $system, array $messages, array $tools );
}

/**
 * Resolve the Anthropic API key.
 *
 * Priority: a server-side secret (the DANTE_ANTHROPIC_KEY constant, defined in
 * a secrets file outside the web root and required from wp-config.php, or the
 * matching environment variable) beats the value in the options table. This
 * lets the key live off the dashboard entirely — invisible to WordPress admins
 * — while the DB option remains a fallback for sites that haven't migrated.
 *
 * @return string
 */
function dante_assistant_api_key() {
    if ( defined( 'DANTE_ANTHROPIC_KEY' ) && DANTE_ANTHROPIC_KEY ) {
        return trim( (string) DANTE_ANTHROPIC_KEY );
    }
    $env = getenv( 'DANTE_ANTHROPIC_KEY' );
    if ( $env ) {
        return trim( $env );
    }
    $settings = get_option( 'dante_assistant_settings', array() );
    return isset( $settings['anthropic_key'] ) ? trim( $settings['anthropic_key'] ) : '';
}

/**
 * True when the key comes from a server-side secret (not the DB option), so the
 * settings UI can show a read-only "managed on the server" state.
 *
 * @return bool
 */
function dante_assistant_key_is_managed() {
    return ( defined( 'DANTE_ANTHROPIC_KEY' ) && DANTE_ANTHROPIC_KEY ) || (bool) getenv( 'DANTE_ANTHROPIC_KEY' );
}

/**
 * Resolve the active provider from settings.
 *
 * @return Dante_AI_Provider|WP_Error
 */
function dante_assistant_provider() {
    $settings = get_option( 'dante_assistant_settings', array() );
    $which    = isset( $settings['provider'] ) ? $settings['provider'] : 'anthropic';

    switch ( $which ) {
        case 'anthropic':
        default:
            $key = dante_assistant_api_key();
            if ( '' === $key ) {
                return new WP_Error( 'no_key', 'The assistant is not set up yet. An administrator needs to add an API key under Settings → Dante Assistant.' );
            }
            $model = isset( $settings['model'] ) && $settings['model'] ? $settings['model'] : 'claude-sonnet-4-6';
            return new Dante_AI_Anthropic( $key, $model );
    }
}

/**
 * Anthropic Messages API implementation.
 */
class Dante_AI_Anthropic implements Dante_AI_Provider {

    const ENDPOINT = 'https://api.anthropic.com/v1/messages';
    const VERSION  = '2023-06-01';

    private $key;
    private $model;
    private $max_tokens;
    private $cache_system;

    /**
     * @param string $key          API key.
     * @param string $model        Model id.
     * @param int    $max_tokens   Output allowance. The chat default is small; the
     *                             newsletter editor needs more, because one edit
     *                             carries two verbatim snippets of the document.
     * @param bool   $cache_system Cache the system prompt. Worth it when the system
     *                             prompt is large and stable across the tool
     *                             round-trips of a single turn (the newsletter
     *                             editor puts the whole email document in it).
     */
    public function __construct( $key, $model, $max_tokens = 1024, $cache_system = false ) {
        $this->key          = $key;
        $this->model        = $model;
        $this->max_tokens   = (int) $max_tokens;
        $this->cache_system = (bool) $cache_system;
    }

    public function chat( $system, array $messages, array $tools ) {
        // A cached system prompt has to be sent as content blocks, not a string.
        $system_param = $this->cache_system
            ? array(
                array(
                    'type'          => 'text',
                    'text'          => $system,
                    'cache_control' => array( 'type' => 'ephemeral' ),
                ),
            )
            : $system;

        $body = array(
            'model'      => $this->model,
            'max_tokens' => $this->max_tokens,
            'system'     => $system_param,
            'messages'   => $messages,   // already in Anthropic content-block shape
            'tools'      => $tools,
        );

        $response = wp_remote_post( self::ENDPOINT, array(
            'timeout' => 60,
            'headers' => array(
                'content-type'      => 'application/json',
                'x-api-key'         => $this->key,
                'anthropic-version' => self::VERSION,
            ),
            'body'    => wp_json_encode( $body ),
        ) );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code( $response );
        $data = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( 200 !== (int) $code ) {
            $msg = isset( $data['error']['message'] ) ? $data['error']['message'] : 'The AI service returned an error.';
            return new WP_Error( 'api_error', $msg, array( 'status' => $code ) );
        }

        $content    = isset( $data['content'] ) && is_array( $data['content'] ) ? $data['content'] : array();
        $text       = '';
        $tool_calls = array();

        foreach ( $content as $idx => $block ) {
            if ( ! isset( $block['type'] ) ) {
                continue;
            }
            if ( 'text' === $block['type'] ) {
                $text .= $block['text'];
            } elseif ( 'tool_use' === $block['type'] ) {
                $input = isset( $block['input'] ) ? $block['input'] : array();

                // A tool called with no arguments arrives as `{}`, which
                // json_decode turns into an empty PHP array(). Left alone, that
                // re-encodes as a JSON array [] when this turn is sent back on
                // the next round-trip, and the API rejects it with
                // "tool_use.input: Input should be an object". Force empty
                // inputs back to an object so the conversation stays valid.
                if ( is_array( $input ) && empty( $input ) ) {
                    $content[ $idx ]['input'] = new stdClass();
                }

                $tool_calls[] = array(
                    'id'    => $block['id'],
                    'name'  => $block['name'],
                    'input' => is_array( $input ) ? $input : (array) $input,
                );
            }
        }

        $stop = ( isset( $data['stop_reason'] ) && 'tool_use' === $data['stop_reason'] ) ? 'tool' : 'end';

        return array(
            'stop'              => $stop,
            'text'              => trim( $text ),
            'tool_calls'        => $tool_calls,
            'assistant_content' => $content,
        );
    }
}

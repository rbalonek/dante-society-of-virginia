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
            $key = isset( $settings['anthropic_key'] ) ? trim( $settings['anthropic_key'] ) : '';
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

    public function __construct( $key, $model ) {
        $this->key   = $key;
        $this->model = $model;
    }

    public function chat( $system, array $messages, array $tools ) {
        $body = array(
            'model'      => $this->model,
            'max_tokens' => 1024,
            'system'     => $system,
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

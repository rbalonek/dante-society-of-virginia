<?php
/**
 * Dante Assistant — admin-only settings (API key + model picker).
 *
 * This is the only technical surface. Board members never see it; only an
 * administrator (manage_options) does. Settings live in the DB per-environment,
 * exactly like the WP Mail SMTP keys — so Local and live can differ.
 *
 * @package Dante_Society
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Register the settings + option.
 */
function dante_assistant_register_settings() {
    register_setting( 'dante_assistant', 'dante_assistant_settings', array(
        'type'              => 'array',
        'sanitize_callback' => 'dante_assistant_sanitize_settings',
        'default'           => array(
            'provider'      => 'anthropic',
            'model'         => 'claude-sonnet-4-6',
            'anthropic_key' => '',
        ),
    ) );
}
add_action( 'admin_init', 'dante_assistant_register_settings' );

/**
 * Sanitize + preserve the key if the field is submitted blank (masked).
 */
function dante_assistant_sanitize_settings( $input ) {
    $existing = get_option( 'dante_assistant_settings', array() );
    $out      = array();

    $out['provider'] = 'anthropic'; // only option in Slice 1.

    $allowed_models = array( 'claude-sonnet-4-6', 'claude-opus-4-8', 'claude-haiku-4-5-20251001' );
    $model          = isset( $input['model'] ) ? sanitize_text_field( $input['model'] ) : 'claude-sonnet-4-6';
    $out['model']   = in_array( $model, $allowed_models, true ) ? $model : 'claude-sonnet-4-6';

    // Only overwrite the key when a new one is actually entered.
    $submitted = isset( $input['anthropic_key'] ) ? trim( $input['anthropic_key'] ) : '';
    if ( '' !== $submitted && false === strpos( $submitted, '••' ) ) {
        $out['anthropic_key'] = sanitize_text_field( $submitted );
    } else {
        $out['anthropic_key'] = isset( $existing['anthropic_key'] ) ? $existing['anthropic_key'] : '';
    }

    return $out;
}

/**
 * Register the Settings → Dante Assistant page.
 */
function dante_assistant_settings_menu() {
    add_options_page(
        __( 'Dante Assistant', 'dante-society' ),
        __( 'Dante Assistant', 'dante-society' ),
        'manage_options',
        'dante-assistant',
        'dante_assistant_settings_page'
    );
}
add_action( 'admin_menu', 'dante_assistant_settings_menu' );

/**
 * Render the settings page.
 */
function dante_assistant_settings_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }
    $settings  = get_option( 'dante_assistant_settings', array() );
    $has_key   = ! empty( $settings['anthropic_key'] );
    $model     = isset( $settings['model'] ) ? $settings['model'] : 'claude-sonnet-4-6';
    $key_field = $has_key ? '••••••••••••••••' : '';
    ?>
    <div class="wrap">
        <h1><?php esc_html_e( 'Dante Assistant', 'dante-society' ); ?></h1>
        <p><?php esc_html_e( 'Connect the AI service that powers the assistant on the Dashboard. Board members never see this page.', 'dante-society' ); ?></p>

        <form method="post" action="options.php">
            <?php settings_fields( 'dante_assistant' ); ?>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><label for="dante_assistant_key"><?php esc_html_e( 'Anthropic API key', 'dante-society' ); ?></label></th>
                    <td>
                        <input type="password" id="dante_assistant_key" class="regular-text"
                               name="dante_assistant_settings[anthropic_key]"
                               value="<?php echo esc_attr( $key_field ); ?>"
                               autocomplete="new-password" />
                        <p class="description">
                            <?php echo $has_key
                                ? esc_html__( 'A key is saved. Leave the dots as-is to keep it, or paste a new key to replace it.', 'dante-society' )
                                : esc_html__( 'Paste your Anthropic API key (starts with sk-ant-).', 'dante-society' ); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="dante_assistant_model"><?php esc_html_e( 'Model', 'dante-society' ); ?></label></th>
                    <td>
                        <select id="dante_assistant_model" name="dante_assistant_settings[model]">
                            <option value="claude-sonnet-4-6" <?php selected( $model, 'claude-sonnet-4-6' ); ?>>Claude Sonnet 4.6 — balanced (recommended)</option>
                            <option value="claude-haiku-4-5-20251001" <?php selected( $model, 'claude-haiku-4-5-20251001' ); ?>>Claude Haiku 4.5 — fastest / cheapest</option>
                            <option value="claude-opus-4-8" <?php selected( $model, 'claude-opus-4-8' ); ?>>Claude Opus 4.8 — most capable</option>
                        </select>
                        <p class="description"><?php esc_html_e( 'Sonnet is the best default for this site. Switch anytime.', 'dante-society' ); ?></p>
                    </td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}

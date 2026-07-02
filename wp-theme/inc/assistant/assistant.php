<?php
/**
 * Dante Assistant — bootstrap.
 *
 * A friendly, chat-based way for board members to update the website. Slice 1
 * covers events: "Add our October wine tasting to the events" creates a draft
 * they review and publish. Everything is draft-and-approve, with one-click undo
 * of the last few edit sessions.
 *
 * Files:
 *   changelog.php  — version control (change sets + undo)
 *   tools.php      — the actions the assistant can take
 *   providers.php  — AI provider adapter (Anthropic first)
 *   rest.php       — REST endpoints + the agent loop
 *   settings.php   — admin-only API key + model picker
 *
 * @package Dante_Society
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'DANTE_ASSISTANT_VERSION', '0.1.0' );

require_once __DIR__ . '/changelog.php';
require_once __DIR__ . '/providers.php';
require_once __DIR__ . '/tools.php';
require_once __DIR__ . '/tools-newsletter.php';
require_once __DIR__ . '/tools-pages.php';
require_once __DIR__ . '/rest.php';
require_once __DIR__ . '/settings.php';

/**
 * Who may use the assistant. Editors/admins (anyone who can edit posts).
 */
function dante_assistant_can() {
    return current_user_can( 'edit_posts' );
}

/**
 * Register the Dashboard widget — the primary, inviting entry point.
 */
function dante_assistant_dashboard_widget() {
    if ( ! dante_assistant_can() ) {
        return;
    }
    wp_add_dashboard_widget(
        'dante_assistant_widget',
        '🖋 ' . __( 'Dante Assistant', 'dante-society' ),
        'dante_assistant_widget_html',
        null,
        null,
        'normal',
        'high' // top of the dashboard.
    );
}
add_action( 'wp_dashboard_setup', 'dante_assistant_dashboard_widget' );

/**
 * The widget markup (chat + review + history mount points; JS fills them).
 */
function dante_assistant_widget_html() {
    $user = wp_get_current_user();
    $name = $user && $user->first_name ? $user->first_name : __( 'there', 'dante-society' );
    ?>
    <div class="dante-assistant" data-greeting="<?php echo esc_attr( sprintf( __( 'Hi %s! What would you like to do?', 'dante-society' ), $name ) ); ?>">
        <div class="dante-assistant__log" aria-live="polite"></div>

        <div class="dante-assistant__newsletter"></div>

        <div class="dante-assistant__review" hidden></div>

        <div class="dante-assistant__attach">
            <input type="file" class="dante-assistant__file" accept="image/*" hidden />
            <button type="button" class="button dante-assistant__photo">📷 <?php esc_html_e( 'Add a photo', 'dante-society' ); ?></button>
            <span class="dante-assistant__thumb" hidden></span>
        </div>

        <form class="dante-assistant__form">
            <label class="screen-reader-text" for="dante-assistant-input"><?php esc_html_e( 'Ask the assistant', 'dante-society' ); ?></label>
            <textarea id="dante-assistant-input" class="dante-assistant__input" rows="2"
                placeholder="<?php esc_attr_e( 'Type here… e.g. "Add our October wine tasting to the events"', 'dante-society' ); ?>"></textarea>
            <button type="submit" class="button button-primary dante-assistant__send"><?php esc_html_e( 'Send', 'dante-society' ); ?></button>
        </form>

        <div class="dante-assistant__chips">
            <button type="button" class="dante-assistant__chip">➕ <?php esc_html_e( 'Add an event', 'dante-society' ); ?></button>
            <button type="button" class="dante-assistant__chip">✉️ <?php esc_html_e( 'Send a newsletter', 'dante-society' ); ?></button>
            <button type="button" class="dante-assistant__chip">✏️ <?php esc_html_e( 'Edit a page', 'dante-society' ); ?></button>
            <button type="button" class="dante-assistant__chip">📅 <?php esc_html_e( "What's coming up?", 'dante-society' ); ?></button>
        </div>

        <div class="dante-assistant__history"></div>
    </div>
    <?php
}

/**
 * Enqueue the chat UI on the Dashboard only (where the widget lives).
 */
function dante_assistant_admin_assets( $hook ) {
    if ( 'index.php' !== $hook || ! dante_assistant_can() ) {
        return;
    }

    $base = get_template_directory_uri();
    $dir  = get_template_directory();

    wp_enqueue_style(
        'dante-assistant',
        $base . '/css/assistant.css',
        array(),
        file_exists( $dir . '/css/assistant.css' ) ? filemtime( $dir . '/css/assistant.css' ) : DANTE_ASSISTANT_VERSION
    );

    wp_enqueue_script(
        'dante-assistant',
        $base . '/js/assistant.js',
        array( 'wp-api-fetch' ),
        file_exists( $dir . '/js/assistant.js' ) ? filemtime( $dir . '/js/assistant.js' ) : DANTE_ASSISTANT_VERSION,
        true
    );

    $configured = ! empty( get_option( 'dante_assistant_settings', array() )['anthropic_key'] );

    wp_localize_script( 'dante-assistant', 'danteAssistant', array(
        'root'       => esc_url_raw( rest_url( 'dante/v1/assistant' ) ),
        'nonce'      => wp_create_nonce( 'wp_rest' ),
        'configured' => $configured,
        'settingsUrl'=> admin_url( 'options-general.php?page=dante-assistant' ),
        'canManage'  => current_user_can( 'manage_options' ),
    ) );
}
add_action( 'admin_enqueue_scripts', 'dante_assistant_admin_assets' );

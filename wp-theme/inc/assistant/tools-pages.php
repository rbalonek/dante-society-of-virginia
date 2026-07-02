<?php
/**
 * Dante Assistant — page text editing tools.
 *
 * Editing page content is the delicate one: pages are Gutenberg block markup, so
 * a blind string replace can corrupt the page. Instead we parse the page into
 * blocks, let the model target ONE block by index, and rebuild only that block's
 * text — preserving heading level, list type, and every other block untouched.
 *
 * Only text blocks (paragraph, heading, list) are editable this way; anything
 * else returns a friendly "use the page editor" message. Edits apply to the live
 * page immediately and are logged for one-click undo (see changelog.php).
 *
 * @package Dante_Society
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Tool: list the site's pages so the model can find the right one.
 */
function dante_tool_list_pages( $args ) {
    $front = (int) get_option( 'page_on_front' );
    $pages = get_pages( array( 'sort_column' => 'menu_order,post_title' ) );
    $out   = array();

    foreach ( $pages as $p ) {
        $out[] = array(
            'id'       => $p->ID,
            'title'    => get_the_title( $p ),
            'slug'     => $p->post_name,
            'is_front' => ( $p->ID === $front ),
        );
    }

    return array( 'ok' => true, 'pages' => $out );
}

/**
 * Tool: read a page's editable sections (index + type + current text).
 */
function dante_tool_read_page( $args ) {
    $id   = isset( $args['page_id'] ) ? (int) $args['page_id'] : 0;
    $post = $id ? get_post( $id ) : null;
    if ( ! $post || 'page' !== $post->post_type ) {
        return array( 'error' => 'That page was not found. Use list_pages first.' );
    }

    $blocks   = parse_blocks( $post->post_content );
    $sections = array();
    $index    = 0;

    foreach ( $blocks as $block ) {
        if ( empty( $block['blockName'] ) ) {
            continue; // whitespace between blocks.
        }
        $text = dante_pages_block_text( $block );
        $sections[] = array(
            'index'    => $index,
            'type'     => str_replace( 'core/', '', $block['blockName'] ),
            'editable' => dante_pages_is_editable( $block['blockName'] ),
            'text'     => $text,
        );
        $index++;
    }

    return array(
        'ok'       => true,
        'page_id'  => $id,
        'title'    => get_the_title( $id ),
        'sections' => $sections,
    );
}

/**
 * Tool: replace the wording of one block, preserving everything else.
 */
function dante_tool_edit_page_block( $args ) {
    $id   = isset( $args['page_id'] ) ? (int) $args['page_id'] : 0;
    $post = $id ? get_post( $id ) : null;
    if ( ! $post || 'page' !== $post->post_type ) {
        return array( 'error' => 'That page was not found. Use list_pages first.' );
    }
    if ( ! current_user_can( 'edit_post', $id ) ) {
        return array( 'error' => 'You do not have permission to edit that page.' );
    }

    $target   = isset( $args['block_index'] ) ? (int) $args['block_index'] : -1;
    $new_text = isset( $args['new_text'] ) ? (string) $args['new_text'] : '';
    if ( $target < 0 ) {
        return array( 'error' => 'A section number (block_index) is required — read the page first.' );
    }
    if ( '' === trim( $new_text ) ) {
        return array( 'error' => 'The new wording is empty.' );
    }

    $blocks = parse_blocks( $post->post_content );

    // Map the visible section index back to its real position in the array.
    $map = array();
    $i   = 0;
    foreach ( $blocks as $pos => $block ) {
        if ( empty( $block['blockName'] ) ) {
            continue;
        }
        $map[ $i ] = $pos;
        $i++;
    }
    if ( ! isset( $map[ $target ] ) ) {
        return array( 'error' => 'That section number was not found. Read the page again.' );
    }

    $real    = $map[ $target ];
    $rebuilt = dante_pages_rebuild_block( $blocks[ $real ], $new_text );
    if ( is_wp_error( $rebuilt ) ) {
        return array( 'error' => $rebuilt->get_error_message() );
    }
    $blocks[ $real ] = $rebuilt;

    $before      = array( 'post_content' => $post->post_content );
    $new_content = serialize_blocks( $blocks );

    // wp_update_post runs wp_unslash internally, so slash the block markup first.
    wp_update_post( array( 'ID' => $id, 'post_content' => wp_slash( $new_content ) ) );

    dante_changeset_log_applied( array(
        'type'    => 'update',
        'post_id' => $id,
        'label'   => sprintf( 'Edited text on the "%s" page', get_the_title( $id ) ),
        'before'  => $before,
    ) );

    return array(
        'ok'      => true,
        'page_id' => $id,
        'summary' => sprintf( 'Updated the wording on the "%s" page.', get_the_title( $id ) ),
    );
}

/* -------------------------------------------------------------------------
 * Helpers
 * ---------------------------------------------------------------------- */

/**
 * Which block types we can safely edit as plain text.
 */
function dante_pages_is_editable( $block_name ) {
    return in_array( $block_name, array( 'core/paragraph', 'core/heading', 'core/list' ), true );
}

/**
 * Readable text for a block (for read_page).
 */
function dante_pages_block_text( $block ) {
    $html = isset( $block['innerHTML'] ) ? $block['innerHTML'] : '';

    // Lists made of inner list-item blocks have empty innerHTML — gather items.
    if ( '' === trim( wp_strip_all_tags( $html ) ) && ! empty( $block['innerBlocks'] ) ) {
        $parts = array();
        foreach ( $block['innerBlocks'] as $child ) {
            $parts[] = wp_strip_all_tags( isset( $child['innerHTML'] ) ? $child['innerHTML'] : '' );
        }
        $html = implode( "\n", $parts );
    }

    $text = html_entity_decode( wp_strip_all_tags( $html ), ENT_QUOTES, 'UTF-8' );
    return trim( preg_replace( '/\s+/', ' ', $text ) );
}

/**
 * Rebuild a single block with new wording, preserving its structure.
 *
 * @return array|WP_Error The rebuilt block, or an error for unsupported types.
 */
function dante_pages_rebuild_block( $block, $new_text ) {
    $type = $block['blockName'];
    $html = isset( $block['innerHTML'] ) ? $block['innerHTML'] : '';

    if ( 'core/paragraph' === $type ) {
        $new = dante_pages_swap_inner( $html, $new_text, 'p' );
    } elseif ( 'core/heading' === $type ) {
        $level = isset( $block['attrs']['level'] ) ? (int) $block['attrs']['level'] : 2;
        $new   = dante_pages_swap_inner( $html, $new_text, 'h' . $level );
    } elseif ( 'core/list' === $type ) {
        $items = array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n/', $new_text ) ) );
        $lis   = '';
        foreach ( $items as $item ) {
            $lis .= '<li>' . esc_html( $item ) . '</li>';
        }
        $tag = ( false !== strpos( $html, '<ol' ) ) ? 'ol' : 'ul';
        $new = '<' . $tag . '>' . $lis . '</' . $tag . '>';
        $block['innerBlocks'] = array(); // replace any inner list-item blocks.
    } else {
        return new WP_Error(
            'unsupported',
            'I can only change wording in paragraphs, headings, and lists. For that part of the page, please use the page editor.'
        );
    }

    $block['innerHTML']    = $new;
    $block['innerContent'] = array( $new );
    return $block;
}

/**
 * Swap the inner text of a single-element block, preserving the opening tag
 * (and thus its attributes, e.g. text alignment). Falls back to a default tag.
 */
function dante_pages_swap_inner( $html, $new_text, $default_tag ) {
    $inner = esc_html( $new_text );
    if ( preg_match( '/^(\s*<([a-zA-Z0-9]+)[^>]*>)(.*)(<\/\2>\s*)$/s', $html, $m ) ) {
        return $m[1] . $inner . $m[4];
    }
    return '<' . $default_tag . '>' . $inner . '</' . $default_tag . '>';
}

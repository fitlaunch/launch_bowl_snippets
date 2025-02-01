<?php
/**
 * Plugin Name: Launch Login Message
 * Description: Displays a "Logged in as" message in the header.
 */

 add_shortcode( 'custom_header_message', 'add_user_login_message' ); // Register the shortcode

function add_user_login_message() {
    if ( is_user_logged_in() ) {
        $current_user = wp_get_current_user();
        // Using a span instead of a div
        $message = '<span class="user-login-message" style="margin-left: 20px; color: #ffffff;">Logged in as ' . esc_html( $current_user->display_name ) . '</span>';
    } else {
        // Using a span instead of a div
        $message = '<span class="user-login-message" style="margin-left: 20px; color: #ffffff;">Not logged in</span>';
    }
    return $message;
}

add_shortcode( 'custom_header_message', 'add_user_login_message' );
add_filter( 'neve_header_right_content', 'add_user_login_message' );

//maybe stop pop up error
add_filter( 'sgo_js_minify_exclude', 'exclude_custom_scripts' );
function exclude_custom_scripts( $exclude_list ) {
    $exclude_list[] = 'zip-ai-sidebar'; // Add the script handle you want to exclude
    return $exclude_list;
}

<?php
/*
Plugin Name: Launch Enqueue Scripts
Description: A plugin to enqueue custom scripts and styles.
Version: 1.0
Author: Launch
*/

// Enqueue custom admin scripts
function enqueue_custom_admin_scripts() {
    wp_enqueue_script('tournament-placings-script', plugin_dir_url(__FILE__) . 'js/tournament-placings.js', array('jquery'), null, true);
    wp_localize_script('tournament-placings-script', 'ajax_object', array('ajax_url' => admin_url('admin-ajax.php')));
}
add_action('admin_enqueue_scripts', 'enqueue_custom_admin_scripts');

function enqueue_jquery_and_select2() {
    wp_enqueue_script( 'jquery' );
    wp_enqueue_script( 'select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', array('jquery'), '4.1.0-rc.0', true );
    wp_enqueue_style( 'select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css', array(), '4.1.0-rc.0' );

    // Enqueue custom form handler script
    wp_enqueue_script( 'custom-form-handler', plugin_dir_url( __FILE__ ) . '../js/custom-form-handler.js', array('jquery'), null, true );
    wp_localize_script( 'custom-form-handler', 'ajax_object', array( 'ajax_url' => admin_url( 'admin-ajax.php' ), 'user_id' => get_current_user_id() ) );
}
add_action( 'wp_enqueue_scripts', 'enqueue_jquery_and_select2' );

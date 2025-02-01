<?php
/**
 * Plugin Name: Launch MP Custom Header
 * Description: Customizes the header of MemberPress pages.
 */

add_action( 'wp_head', 'add_custom_header_to_memberpress_pages' );
add_action( 'login_enqueue_scripts', 'add_custom_header_to_memberpress_pages' );

function add_custom_header_to_memberpress_pages() {
  if ( is_page( 'account' ) || is_page( 'login' ) ) { // Only show the header on account and login pages
    ?>


    <div class="account-page-links" style="text-align: left; margin-left: 30px; margin-bottom: 20px; margin-top: 20px; color: #06429e; ">
    <a href="<?php echo esc_url( home_url() ); ?>">
        <img src="http://launchfantasy.com/wp-content/uploads/2025/01/cropped-Logo-GemGen-1-1024x573.jpeg" alt="Launch Fantasy Logo" style="max-width: 250px; height: auto;"> 
      </a>
      <a href="<?php echo esc_url( home_url() ); ?>" style="color: #5A5A5A; margin: 0 10px;">Home</a> | 
      <a href="<?php echo esc_url( site_url('/sport/') ); ?>" style="color: #5A5A5A; margin: 0 10px;">Leagues</a> |

      <a href="<?php echo esc_url( site_url('/team/') ); ?>" style="color: #5A5A5A; margin: 0 10px;">Team</a> 
      
    </div>
    <?php
  }
}
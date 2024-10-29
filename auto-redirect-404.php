<?php

/*
Plugin Name: Auto Redirect 404 in 301 for Trashed Posts
Description: This plugin allows you to automatically produce 301 redirects to the home page or another URL for deleted posts and taxonomies to avoid 404 errors.
Version: 1.0
Author: GeekPress, Groupe361
Author URI: http://www.geekpress.fr/

	Copyright 2013 Jonathan Buttigieg

	This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
	along with this program.  If not, see <http://www.gnu.org/licenses/>.

*/

class AutoRedirect404 {


    /**
     * settings
     *
     * (default value: array())
     *
     * since 1.0
     * @var array
     * @access private
     */
    private $settings = array();


    /**
     * urls
     *
     * (default value: array())
     *
     * since 1.0
     * @var array
     * @access private
     */
    private $urls = array();


    /**
     * __construct function.
     *
     * since 1.0
     * @access public
     * @return void
     */
    function __construct() {
        add_action( 'plugins_loaded', array( $this, 'load' ) );
    }


    /**
     * load function.
     *
     * since 1.0
     * @access public
     * @return void
     */
    function load() {

        // Add Translations
        load_plugin_textdomain( 'auto-redirect-404' , false, dirname(plugin_basename( __FILE__ )) . '/languages/');

        // Add submenu in settings panel
		add_action('admin_menu', array( $this, 'add_submenu' ) );

		// Add input to submit box
		add_action( 'post_submitbox_misc_actions', array( $this, 'add_input_redirection_404_in_post_submitbox' ) );

		// Settings API
		add_action('admin_init', array( $this, 'register_setting' ) );

        // Get options values
        if( !get_option( 'ar404_settings' ) )
		  add_option( 'ar404_settings', $this->settings );

		if( !get_option( 'ar404_urls' ) )
		  add_option( 'ar404_urls', $this->urls );

        $this->settings = get_option( 'ar404_settings' );
        $this->urls = get_option( 'ar404_urls' );


        // All redirection actions
        add_action( 'delete_post',              array( $this, 'save_301_old_posts_urls'         )        );
        add_action( 'transition_post_status',   array( $this, 'save_302_old_posts_urls'         ), 10, 3 );
        add_action( 'save_post',                array( $this, 'save_redirect_post_submitbox'    ), 10, 2 );
        add_action( 'delete_term_taxonomy',     array( $this, 'save_301_old_terms_urls'         )        );
        add_action( 'wp',                       array( $this, 'redirect_old_urls'               )        );

    }


    /**
     * save_302_old_posts_urls function.
     *
     * since 1.0
     * @access public
     * @param mixed $new_status
     * @param mixed $old_status
     * @param mixed $post
     * @return void
     */
    function save_302_old_posts_urls( $new_status, $old_status, $post ) {

        // Get redirect url for this post
        $redirect_to = get_post_meta( $post->ID, 'post_redirect_404', true );

        // Get the permalink structure
        $permalink_structure = get_sample_permalink( $post->ID );

        // Get permalink
        $permalink = get_option( 'permalink_structure' )
                         ? trim( str_replace( home_url(), '', str_replace( '%postname%', $permalink_structure[1], $permalink_structure[0] ) ), '/' )
                         : $permalink_structure[0];


        // If the new status of the post is publish or private, it's removed from the option
        if( $new_status == 'publish' || $new_status == 'private' ) {

            foreach( $this->urls as $key => $tab ) {

                if( $tab['url'] == $permalink  ) {

                    unset( $this->urls[$key] );
                    update_option( 'ar404_urls', $this->urls );

                    break;

                }

            }

        }

        // If the old status of the is publish or private, it's added to the option
        else if ( ( $old_status == 'publish' || $old_status == 'private' )
                  && ( !wp_is_post_revision( $post->ID ) && !in_array_r( $permalink, $this->urls ) )
                ) {

                array_push( $this->urls, array( 'status' => 302, 'redirect_to' => $redirect_to, 'type' => $post->post_type, 'url' => $permalink ) );
                update_option( 'ar404_urls', $this->urls );

        }
        else {

           foreach( $this->urls as $key => $tab ) {

                if( $tab['url'] == $permalink ) {

                    $this->urls[$key]['redirect_to'] = $redirect_to;
                    update_option( 'ar404_urls', $this->urls );

                    break;

                }

            }

        }

    }


    /**
     * save_301_old_posts_urls function.
     *
     * since 1.0
     * @access public
     * @param mixed $post_id
     * @return void
     */
    function save_301_old_posts_urls( $post_id ) {

        // Get redirect url for this post
        $redirect_to = get_post_meta( $post_id, 'post_redirect_404', true );

        // Get permalink
        $permalink = trim( str_replace( home_url(), '', get_permalink( $post_id ) ), '/' );

        if( !wp_is_post_revision( $post_id ) && !in_array_r( $permalink, $this->urls ) ) {

                array_push( $this->urls, array( 'status' => 301, 'redirect_to' => $redirect_to, 'type' => get_post_type( $post_id ), 'url' => $permalink ) );
                update_option( 'ar404_urls', $this->urls );

        }
        else {

            foreach( $this->urls as $key => $tab ) {

                if( $tab['url'] == $permalink ) {

                    $this->urls[$key]['status'] = 301;
                    update_option( 'ar404_urls', $this->urls );

                    break;

                }

            }

        }

    }


    /**
     * save_old_posts_by_settings function.
     *
     * since 1.0
     * @access public
     * @param mixed $input
     * @return void
     */
    function save_old_posts_by_settings( $input ) {

        if( isset( $_POST['ar404_urls'] ) && is_array( $_POST['ar404_urls'] ) ) {

            foreach( array_filter( $_POST['ar404_urls'] ) as $key => $url ) {
                $this->urls[$key]['redirect_to'] = $url;
                update_option( 'ar404_urls', $this->urls );
            }
        }

        return $input;

    }


    /**
     * save_301_old_terms_urls function.
     *
     * since 1.0
     * @access public
     * @param mixed $term_id
     * @return void
     */
    function save_301_old_terms_urls( $term_id ) {

        $taxonomy   = $_REQUEST['taxonomy'] ? $_REQUEST['taxonomy'] : 'post_tag';
        $permalink  = trim( str_replace( home_url(), '', get_term_link( get_term( $term_id, $taxonomy ) ) ), '/' );

        if( !in_array_r( $permalink, $this->urls ) ) {

            array_push( $this->urls, array( 'status' => 301, 'redirect_to' => '', 'type' => $taxonomy, 'url' => $permalink ) );
            update_option( 'ar404_urls', $this->urls );

        }

    }


    /**
     * save_redirect_post_submitbox function.
     *
     * since 1.0
     * @access public
     * @return void
     */
    function save_redirect_post_submitbox( $post_id, $post ) {

        if ( !current_user_can( 'edit_post', $post_id )      // On verifie que l'utilisateur a les droits
 	         || ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) // En cas de sauvegarde auto
        )
 	      return $post_id;


 	    !empty( $_POST['post_redirect_404'] ) ? update_post_meta( $post_id, 'post_redirect_404', $_POST['post_redirect_404'] )
 	                                          : delete_post_meta( $post_id, 'post_redirect_404' );

    }


    /**
     * redirect_old_urls function.
     *
     * since 0.1
     * @access public
     * @return void
     */
    function redirect_old_urls() {

        $redirect_to = home_url( '/' );

        $permalink = parse_url( set_url_scheme( 'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] ) );
        $permalink = $permalink['scheme'] . '://' . $permalink['host'] . $permalink['path'];
        $permalink = trim( str_replace( home_url(), '', $permalink ), '/' );


        foreach( $this->urls as $key => $tab ) {

            if( $tab['url'] == $permalink  ) {

                if( !empty( $tab['redirect_to'] ) )
                    $redirect_to = $tab['redirect_to'];

                else if( !empty( $this->settings[$tab['type']] ) )
                    $redirect_to = $this->settings[$tab['type']];


                wp_redirect( $redirect_to , $tab['status'] );
                break;

            }

        }

    }


    /**
     * add_submenu function.
     *
     * since 0.1
     * @access public
     * @return void
     */
    function add_submenu() {
        add_options_page( 'Auto Redirect 404', 'Auto Redirect 404', 'manage_options', 'ar404', array( $this, 'display_page' ) );
    }


    /**
     * register_setting function.
     *
     * since 1.0
     * @access public
     * @return void
     */
    function register_setting()  {
		register_setting( 'auto_redirect_404', 'ar404_settings', array( $this, 'save_old_posts_by_settings' ) );
	}


    /**
     * display_page function.
     *
     * since 0.1
     * @access public
     * @return void
     */
    function display_page() { ?>

        <style>
        ::-webkit-input-placeholder { color:#ccc; }
        ::-moz-placeholder { color:#ccc; } /* firefox 19+ */
        :-ms-input-placeholder { color:#ccc; } /* ie */
        input:-moz-placeholder { color:#ccc; }
        </style>

		<div class="wrap">
			<?php screen_icon(); ?>
				<h2>Auto Redirect 404 in 301 for Trashed Posts</h2>

				<div class="updated">
				    <p><?php _e( 'By default, the old posts and taxonomies are redirected to the home page of your site.', 'auto-redirect-404' ); ?>
				    <br/><?php _e( 'The following parameters allow you to change the forwarding address based on Post Type or Taxonomy.', 'auto-redirect-404' ); ?></p>
				</div>

				<form method="post" action="options.php">

					<?php settings_fields( 'auto_redirect_404' ); // Add fields ?>

					<h3>Post Types</h3>
					<table class="form-table">

						<?php

    				    // Display all public post types

                        $post_types = array_merge(
                            get_post_types( array( 'public' => true, '_builtin' => true ), 'objects' ),
                            get_post_types( array( 'public' => true ), 'objects' )
                        );

						foreach ( $post_types as $post_type ) { ?>

						  <tr valign="top">
    							<th scope="row">
    								<label for="<?php echo $post_type->name; ?>"><?php echo $post_type->labels->singular_name; ?></label>
    							</th>
    							<td>
    								<input type="url" name="ar404_settings[<?php echo $post_type->name; ?>]" id="<?php echo $post_type->name; ?>" value="<?php echo array_key_exists( $post_type->name, $this->settings ) ? esc_url( $this->settings[$post_type->name] ) : ''; ?>" placeholder="<?php echo home_url( '/' ); ?>" size="30" />
    							</td>
    						</tr>

						<?php
						}
						?>

					</table>

					<h3>Taxonomies</h3>
					<table class="form-table">

					<?php

    				// Display all public taxonomies

                    $taxonomies = get_taxonomies( array( 'public' => true ), 'objects' );
                    unset( $taxonomies['post_format'] );

                    if ( $taxonomies ) {

                        foreach ( $taxonomies  as $taxonomy ) { ?>

                            <tr valign="top">
    							<th scope="row">
    								<label for="<?php echo $taxonomy->name; ?>"><?php echo $taxonomy->labels->singular_name; ?></label>
    							</th>
    							<td>
    								<input type="url" name="ar404_settings[<?php echo $taxonomy->name; ?>]" id="<?php echo $taxonomy->name; ?>" value="<?php echo array_key_exists( $taxonomy->name, $this->settings ) ? esc_url( $this->settings[$taxonomy->name] ) : ''; ?>" placeholder="<?php echo home_url( '/' ); ?>" size="30" />
    							</td>
    						</tr>

                        <?php
                        }

                    }
                    ?>
					</table>

    				<?php

    				// Display all old urls with a 301 status

    				if( count( $this->urls ) >= 1 ) { ?>

    				    <h3><?php _e( 'Olds URLs', 'auto-redirect-404'); ?></h3>
    				    <table class="form-table">

    				    <?php
    				    foreach ( $this->urls  as $key => $tab ) {

        				    // Get the value of the placeholder
        				    $placeholder = home_url( '/' );

        				    if( !empty( $tab['redirect_to'] ) ) $placeholder = $tab['redirect_to'];
                            else if( !empty( $this->settings[$tab['type']] ) ) $placeholder = $this->settings[$tab['type']];


        				    // Display it only if the post have a status = 301
        				    if( $tab['status'] == 301 ) {  ?>

            				    <tr valign="top">
        							<th scope="row">
        								<label for="url_<?php echo $key; ?>"><?php echo home_url( $tab['url'] ); ?></label>
        							</th>
        							<td>
        								<input type="url" name="ar404_urls[<?php echo $key; ?>]" id="url_<?php echo $key; ?>" value="<?php echo !empty( $tab['redirect_to'] ) ? esc_url( $tab['redirect_to'] ) : ''; ?>" placeholder="<?php echo esc_url( $placeholder ) ?>" size="30" />
        							</td>
        						</tr>

            				<?php
        				    }

                        }
    				    ?>
    				    </table>
        			<?php
    				}
    				
    				// Add submit button
					submit_button(); 	
					?>
				</form>
		</div>
	<?php
	}


	/**
     * add_input_redirection_404_in_post_submitbox function.
     *
     * since 1.0
     * @access public
     * @return void
     */
    function add_input_redirection_404_in_post_submitbox() {

        global $post, $typenow; ?>

        <div class="misc-pub-section">
        	<span id="redirect-404-span" style="display: inline;">
        		<label for="post_redirect_404">Auto Redirect 404 :</label> 
        		<input type="url" name="post_redirect_404" id="post_redirect_404" value="<?php echo esc_url( get_post_meta( $post->ID, 'post_redirect_404', true ) ); ?>" style="width: 100%" placeholder="<?php echo !empty( $this->settings[$typenow] ) ? esc_attr( $this->settings[$typenow] ) : home_url( '/' ); ?>" />
        		<br>
        	</span>
        </div>

    <?php
    }

}

// Start this plugin once all other plugins are fully loaded
global $AutoRedirect404; $AutoRedirect404 = new AutoRedirect404();


if( !function_exists( 'in_array_r' ) ) {


    /**
     * in_array_r function.
     *
     * since 1.0
     * @access public
     * @param mixed $needle
     * @param mixed $haystack
     * @param bool $strict (default: false)
     * @return void
     */
    function in_array_r($needle, $haystack, $strict = false) {
        foreach ($haystack as $item) {
            if (($strict ? $item === $needle : $item == $needle) || (is_array($item) && in_array_r($needle, $item, $strict))) {
                return true;
            }
        }

        return false;
    }

}
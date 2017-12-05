<?php
/**
 * Plugin Name:       WP Smart Update
 * Plugin URI:        http://wpsmartplugin.com/wpsu
 * Description:       Test Plugin
 * Version:           0.1
 * Author:            Rajan V
 * Author URI:        https://www.wpsmartplugin.com/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       wpsu
 */

class WPSU
{
    private $plugin_name;
    private $version;
    public function __construct()
    {
        $this->plugin_name = 'WP Smart Update';
        $this->version     = '1.0';

        add_action( 'admin_menu', array( $this, 'admin_menu' ), 20 );

        add_filter( 'plugin_action_links', array( $this, 'plugin_action_links' ), 20, 4 );
    }
    
    
    public function plugin_action_links( $actions, $plugin_file, $plugin_data, $context )
    {

        // Filter for other devs.
            $plugin_data = apply_filters( 'wpsu_plugin_data', $plugin_data );

            // If plugin is missing package data do not output wpsu option.
            if ( ! isset( $plugin_data['package'] ) || strpos( $plugin_data['package'], 'https://downloads.wordpress.org' ) === false ) {
                return $actions;
            }

            // Multisite check.
            if ( is_multisite() && ( ! is_network_admin() && ! is_main_site() ) ) {
                return $actions;
            }

            // Must have version.
            if ( ! isset( $plugin_data['Version'] ) ) {
                return $actions;
            }

            // Base wpsu URL
            $wpsu_url = 'index.php?page=wp-wpsu&type=plugin&plugin_file=' . $plugin_file;

            $wpsu_url = add_query_arg( apply_filters( 'wpsu_plugin_query_args', array(
                'current_version' => urlencode( $plugin_data['Version'] ),
                'wpsu_name'   => urlencode( $plugin_data['Name'] ),
                'plugin_slug'     => urlencode( $plugin_data['slug'] ),
                '_wpnonce'        => wp_create_nonce( 'wpsu_wpsu_nonce' ),
            ) ), $wpsu_url );

            // Final Output
            $actions['wpsu'] = apply_filters( 'wpsu_plugin_markup', '<a href="' . esc_url( $wpsu_url ) . '">' . __( 'Backup before Update', 'wp-wpsu' ) . '</a>' );

            return apply_filters( 'wpsu_plugin_action_links', $actions );
        
    }

    public function admin_menu() {


        // Only show menu item when necessary (user is interacting with plugin, ie rolling back something)
        if ( isset( $_GET['page'] ) && $_GET['page'] == 'wp-wpsu' ) {

            // Add it in a native WP way, like WP updates do... (a dashboard page)
            add_dashboard_page( __( 'WP Smart backup', 'wpsu' ), __( 'WP Smart backup', 'wpsu' ), 'update_plugins', 'wp-wpsu', array( $this, 'html' ) );

        }

    }


    public function html() {

        // Permissions check
        if ( ! current_user_can( 'update_plugins' ) ) {
            wp_die( __( 'You do not have sufficient permissions to perform rollbacks for this site.', 'wp-rollback' ) );
        }

        // Get the necessary class
        include_once( ABSPATH . 'wp-admin/includes/class-wp-upgrader.php' );


        $args = wp_parse_args( $_GET, $defaults );

        file_put_contents(dirname(__FILE__)."/__debugger1.php", var_export($args,1)."\n<br><br>\n",FILE_APPEND );


    }

    
}

$wpsu = new WPSU();
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

class HZip
{
  /**
   * Add files and sub-directories in a folder to zip file.
   * @param string $folder
   * @param ZipArchive $zipFile
   * @param int $exclusiveLength Number of text to be exclusived from the file path.
   */
  private static function folderToZip($folder, &$zipFile, $exclusiveLength) {
    $handle = opendir($folder);
    while (false !== $f = readdir($handle)) {
      if ($f != '.' && $f != '..') {
        $filePath = "$folder/$f";
        // Remove prefix from file path before add to zip.
        $localPath = substr($filePath, $exclusiveLength);
        if (is_file($filePath)) {
          $zipFile->addFile($filePath, $localPath);
        } elseif (is_dir($filePath)) {
          // Add sub-directory.
          $zipFile->addEmptyDir($localPath);
          self::folderToZip($filePath, $zipFile, $exclusiveLength);
        }
      }
    }
    closedir($handle);
  }

  /**
   * Zip a folder (include itself).
   * Usage:
   *   HZip::zipDir('/path/to/sourceDir', '/path/to/out.zip');
   *
   * @param string $sourcePath Path of directory to be zip.
   * @param string $outZipPath Path of output zip file.
   */
  public static function zipDir($sourcePath, $outZipPath)
  {
    $pathInfo = pathInfo($sourcePath);
    $parentPath = $pathInfo['dirname'];
    $dirName = $pathInfo['basename'];

    $z = new ZipArchive();
    $z->open($outZipPath, ZIPARCHIVE::CREATE);
    $z->addEmptyDir($dirName);
    self::folderToZip($sourcePath, $z, strlen("$parentPath/"));
    $z->close();
  }
} 

class WPSU
{
    private $plugin_name;
    private $version;
    public function __construct()
    {
        $this->plugin_name = 'WP Smart Update';
        $this->version     = '1.0';

        add_action( 'init', array( $this, 'wpsu_init') );
        add_action( 'admin_menu', array( $this, 'admin_menu' ), 20 );
        add_filter( 'plugin_action_links', array( $this, 'plugin_action_links' ), 20, 4 );
    }

    public function wpsu_init() {
        $args = array(
            'public' => true,
            'label'  => 'WPSU_Backup'
        );
        register_post_type( 'wpsu_backup', $args );
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

            $wpsu_backup_url = add_query_arg( apply_filters( 'wpsu_plugin_query_args', array(
                'mode'  => 'backup',
                'wpsu_name'   => urlencode( $plugin_data['Name'] ),
                'plugin_slug'     => urlencode( $plugin_data['slug'] ),
                '_wpnonce'        => wp_create_nonce( 'wpsu_wpsu_nonce' ),
            ) ), $wpsu_url );

            $wpsu_restore_url = add_query_arg( apply_filters( 'wpsu_plugin_query_args', array(
                'mode'  => 'restore',
                'wpsu_name'   => urlencode( $plugin_data['Name'] ),
                'plugin_slug'     => urlencode( $plugin_data['slug'] ),
                '_wpnonce'        => wp_create_nonce( 'wpsu_wpsu_nonce' ),
            ) ), $wpsu_url );

            // Final Output
            $actions['wpsu'] = apply_filters( 'wpsu_plugin_markup', '<a href="' . esc_url( $wpsu_backup_url ) . '">' . __( 'Backup Now', 'wp-wpsu' ) . '</a>' );

            $actions['wpsu_restore'] = apply_filters( 'wpsu_plugin_markup', '<a href="' . esc_url( $wpsu_restore_url ) . '">' . __( 'Restore', 'wp-wpsu' ) . '</a>' );

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
            wp_die( __( 'You do not have sufficient permissions to perform rollbacks for this site.', 'wp-wpsu' ) );
        }

        // Get the necessary class
        include_once( ABSPATH . 'wp-admin/includes/class-wp-upgrader.php' );

        $args = wp_parse_args( $_GET, $defaults );

        if( $args['type'] == 'plugin' && $args['mode'] == 'backup' ){
            $this->wpsu_backup_plugin( $args['plugin_file'], $args['plugin_slug'] );
            echo "Success";
        }
        elseif( $args['type'] == 'plugin' && $args['mode'] == 'restore' ){
            
            $args = array(
                'post_type'  => 'wpsu_backup',
                'meta_query' => array(
                    array(
                        'key'     => 'type',
                        'value'   => $args['type']
                    ),
                    array(
                        'key'     => 'slug',
                        'value'   => $args['plugin_slug']
                    )
                ),
            );

            $wpsu_backup_posts = get_posts( $args );

            foreach ( $wpsu_backup_posts as $post ) { setup_postdata( $post ); 
                //file_put_contents(dirname(__FILE__)."/__debugger1.php", var_export($post,1)."\n<br><br>\n",FILE_APPEND );
                echo '<li>'. $post->post_date. '</li>';
            }

        }

    }

    public function wpsu_backup_plugin($plugin_file, $plugin_slug){

        $plugin_dir = plugin_dir_path($plugin_file);
        $upload_dir = wp_upload_dir();

        $source = ABSPATH . 'wp-content/plugins/' . $plugin_dir;
        $destination = $upload_dir['path'] . '/' . $plugin_slug . '-' . time() . '.zip';

        $this->wpsu_save_backup_data($plugin_slug, $destination, 'plugin');
        
        HZip::zipDir($source, $destination); 
    }

    public function wpsu_save_backup_data($slug, $destination, $type){

        $wpsu_backup_post = array(
            'post_title'    => $slug ,
            'post_status'   => 'publish',
            'post_type' => 'wpsu_backup',
        );

        $wpsu_backup_post_id = wp_insert_post( $wpsu_backup_post );

        if ( ! add_post_meta( $wpsu_backup_post_id, 'source_file', $destination, true ) ) { 
            update_post_meta( $wpsu_backup_post_id, 'source_file', $destination );
        }
        if ( ! add_post_meta( $wpsu_backup_post_id, 'type', $type, true ) ) { 
            update_post_meta( $wpsu_backup_post_id, 'type', $type );
        }
        if ( ! add_post_meta( $wpsu_backup_post_id, 'slug', $slug, true ) ) { 
            update_post_meta( $wpsu_backup_post_id, 'slug', $slug );
        }

    }
    
}

$wpsu = new WPSU();
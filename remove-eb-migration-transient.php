<?php
/**
 * Plugin Name: Eventbrite Tickets Extension: Clean Up Migration Data
 * Description: Older versions of Eventbrite Tickets would sometimes add a transient with no expiration date. This extension removes that transient if it exists.
 * Version: 1.0.0
 * Author: Modern Tribe, Inc.
 * Author URI: http://m.tri.be/1971
 * License: GPLv2 or later
 */

defined( 'WPINC' ) or die;

class Tribe__Extension__Remove_Eventbrite_Migration_Transient {

    /**
     * The name of the migration transient that we may be deleting.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public static $transient_key = '_tribe_eventbrite_is_migrating';

    /**
     * The name of the option where we store a simple boolean if the old transient was deleted.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public static $deleted_key = '_tribe_deleted_old_migration_transient';

    /**
     * The path to this extension's main plugin file.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public $extension_path = '';

    /**
     * Extension version.
     *
     * @var string
     */
    const VERSION = '1.0.0';

    /**
     * Required plugins as defined by their Main classes.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public $plugins_required = array(
        'Tribe__Events__Main'                      => '4.7',
        'Tribe__Events__Tickets__Eventbrite__Main' => '4.5'
    );

    /**
     * Ensures extension hooks run well after main plugin hooks.
     *
     * @since 1.0.0
     */
    public function __construct() {

        $this->extension_path = plugin_basename( __FILE__ );

        add_action( 'plugins_loaded', array( $this, 'init' ), 100 );
    }

    /**
     * Simple check for whether the extension's okay to run.
     *
     * @since 1.0.0
     *
     * @return boolean
     */
    public function extension_should_run() {
        return function_exists( 'tribe_register_plugin' ) && tribe_register_plugin( __FILE__, __CLASS__, self::VERSION, $this->plugins_required );
    }

    /**
     * Extension hooks.
     *
     * @since 1.0.0
     */
    public function init() {

        if ( ! $this->extension_should_run() ) {
            return false;
        }

        add_action( 'init', array( $this, 'maybe_delete_old_transient' ) );
        add_action( 'after_plugin_row_' . $this->extension_path, array( $this, 'maybe_add_deactivation_notice' ), 10, 3 );
        add_action( 'admin_head', array( $this, 'admin_head_css' ) );
    }

    /**
     * If the migration transient exists already, *and* doesn't have an expiration.
     *
     * @since 1.0.0
     *
     * @return boolean
     */
    public function old_transient_exists() {
        $transient         = get_transient( self::$transient_key );
        $transient_timeout = (bool) get_option( '_transient_timeout_' . self::$transient_key );

        return ! empty( $transient ) && false === $transient_timeout;
    }

    /**
     * Look for the old transient and check if it's got no expiration date; delete it if so.
     *
     * @since 1.0.0
     *
     * @return bool true if successful, false otherwise
     */
    public function maybe_delete_old_transient() {

        if ( ! $this->old_transient_exists() ) {
            add_option( self::$deleted_key, 'false' );
            return false;
        }

        $deleted = delete_transient( self::$transient_key );

        if ( $deleted ) {
            add_option( self::$deleted_key, 'true' );
        }

        return $deleted;
    }

    /**
     * A deactivation notice that shows under the extension's listing in the Plugins list table.
     *
     * @since 1.0.0
     */
    public function maybe_add_deactivation_notice( $plugin_file, $plugin_data, $status ) {

        if ( false === get_option( self::$deleted_key ) ) {
            return false;
        }

        $message  = esc_html__( 'No Eventbrite Tickets cleanup task was necessary.', 'tribe-extension' );
        $dashicon = 'dashicons-flag';

        if ( 'true' === get_option( self::$deleted_key ) ) {
            $message  = esc_html__( 'Success! Eventbrite\'s migration data was cleaned up.', 'tribe-extension' );
            $dashicon = 'dashicons-yes';
        }

        printf(
            '<tr class="active tribe--plugin-row-notice"><th class="check-column" scope="row"><span class="dashicons %1$s"></span></th><td><strong>%2$s</strong></td><td>%3$s</td></tr>',
            $dashicon,
            $message,
            esc_html__( 'You can now deactivate this extension, then delete it.', 'tribe-extension' )
        );
    }
    
    /**
     * Some styles for the plugin row notice.
     *
     * @since 1.0.0
     */
    public function admin_head_css() {
        ?><style>
        tr[data-plugin="remove-eb-migration-transient.php"] td,
        tr[data-plugin="remove-eb-migration-transient.php"] th {
            box-shadow: none !important;
        }

        tr.tribe--plugin-row-notice .check-column {
            text-align: center;
        }

        tr.tribe--plugin-row-notice .dashicons {
            margin-left: 5px;
            line-height: 1.25;
        }

        /* Convoluted selectors, but adds a mouseover effect that helps the two separate table rows appear as one unit. */
        tr.active[data-plugin="remove-eb-migration-transient.php"]:hover td,
        tr.active[data-plugin="remove-eb-migration-transient.php"]:hover th,
        tr.active[data-plugin="remove-eb-migration-transient.php"]:hover + tr td,
        tr.active[data-plugin="remove-eb-migration-transient.php"]:hover + tr th {
            background: #fdfff2 !important;
        }
        </style><?php
    }

    /**
     * Remove deletion flag from options upon deactivation.
     *
     * @since 1.0.0
     *
     * @return bool true if successful, false otherwise
     */
    public static function cleanup_on_deactivation() {
        return delete_option( self::$deleted_key );
    }

}

register_deactivation_hook( __FILE__, array( 'Tribe__Extension__Remove_Eventbrite_Migration_Transient', 'cleanup_on_deactivation' ) );

new Tribe__Extension__Remove_Eventbrite_Migration_Transient();

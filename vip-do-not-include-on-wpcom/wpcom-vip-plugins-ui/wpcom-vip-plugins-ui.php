<?php

/**
 * Plugin Name: WordPress.com VIP Plugins
 * Plugin URI:  http://vip.wordpress.com/
 * Description: Provides an interface to manage the activation and deactivation of the pre-approved WordPress.com VIP plugins.
 * Author:      Alex Mills (Automattic)
 * Author URI:  http://automattic.com/
 *
 * This class structure was stolen from bbPress, so credit to JJJ & co.
 */

if ( ! function_exists( 'wpcom_vip_load_plugin' ) )
	require_once( WP_CONTENT_DIR . '/themes/vip/plugins/vip-init.php' );

class WPcom_VIP_Plugins_UI {

	/**
	 * @var string Option name containing the list of active plugins.
	 */
	const OPTION_ACTIVE_PLUGINS = 'wpcom_vip_active_plugins';

	/**
	 * @var string This plugin's menu slug.
	 */
	const MENU_SLUG = 'wpcom-vip-plugins';

	/**
	 * @var string VIP menu's slug.
	 */
	const VIP_MENU_SLUG = 'vip-dashboard';

	/**
	 * @var string Required capability to access this plugin's features.
	 */
	const CAPABILITY = 'activate_plugins';

	/**
	 * @var string Action: Plugin activation.
	 */
	const ACTION_PLUGIN_ACTIVATE = 'wpcom-vip-plugins_activate';

	/**
	 * @var string Action: Plugin deactivation.
	 */
	const ACTION_PLUGIN_DEACTIVATE = 'wpcom-vip-plugins_deactivate';

	/**
	 * @var string Path to the extra plugins folder.
	 */
	public $plugin_folder;

	/**
	 * @var string The $hook_suffix value for the menu page.
	 */
	public $hook_suffix;

	/**
	 * @var array List of plugins that should be hidden.
	 */
	public $hidden_plugins = array();

	/**
	 * @var array List of Featured Partner Program plugins.
	 */
	public $fpp_plugins = array();

	/** Singleton *************************************************************/

	/**
	 * @var WPcom_VIP_Plugins_UI Stores the instance of this class.
	 */
	private static $instance;

	/**
	 * Main WPcom_VIP_Plugins_UI Instance
	 *
	 * Insures that only one instance of WPcom_VIP_Plugins_UI exists in memory at any one
	 * time. Also prevents needing to define globals all over the place.
	 *
	 * @staticvar array $instance
	 * @uses WPcom_VIP_Plugins_UI::setup_globals() Setup the globals needed
	 * @uses WPcom_VIP_Plugins_UI::setup_actions() Setup the hooks and actions
	 * @uses WPcom_VIP_Plugins_UI::include_active_plugins() Load enabled plugins
	 * @see WPcom_VIP_Plugins_UI()
	 * @return The one true WPcom_VIP_Plugins_UI
	 */
	public static function instance() {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new WPcom_VIP_Plugins_UI;
			self::$instance->setup_globals();
			self::$instance->setup_actions();
			self::$instance->include_active_plugins();
		}
		return self::$instance;
	}

	/** Magic Methods *********************************************************/

	/**
	 * A dummy constructor to prevent WPcom_VIP_Plugins_UI from being loaded more than once.
	 *
	 * @see WPcom_VIP_Plugins_UI::instance()
	 * @see WPcom_VIP_Plugins_UI();
	 */
	private function __construct() { /* Do nothing here */ }

	/**
	 * A dummy magic method to prevent WPcom_VIP_Plugins_UI from being cloned
	 */
	public function __clone() { wp_die( __( 'Cheatin’ uh?' ) ); }

	/**
	 * A dummy magic method to prevent WPcom_VIP_Plugins_UI from being unserialized
	 */
	public function __wakeup() { wp_die( __( 'Cheatin’ uh?' ) ); }

	/** Private Methods *******************************************************/

	/**
	 * Set up the class variables.
	 *
	 * @access private
	 */
	private function setup_globals() {
		$this->plugin_folder = WP_CONTENT_DIR . '/themes/vip/plugins';

		$this->hidden_plugins = array(
			'vip-do-not-include-on-wpcom', // Local dev helper
			'internacional', // Not ready yet (ever?)

			// Commercial non-FPP plugins. Available but not promoted.
			'disqus',
			'kapost-byline',
			'outbrain',
			'share-this-classic-wpcom',
			'share-this-wpcom',
			'storify',
		);

		$this->fpp_plugins = array(
			'chartbeat'    => array(
				'name'        => 'Chartbeat',
				'description' => 'Real-time data for big-time publishers',
			),
			'engagewidget' => array(
				'name'        => 'ContextLogic',
				'description' => "Dramatically increase your site's traffic and engagement",
			),
			'daylife'      => array(
				'name'        => 'Daylife',
				'description' => 'Daylife. Simply Amazing Cloud Publishing.',
			),
			'livefyre'     => array(
				'name'        => 'Livefyre',
				'description' => 'Replace comments with live conversations connected to the social web.',
			),
			'mediapass'    => array(
				'name'        => 'MediaPass Subscriptions',
				'description' => 'Monetize your content with recurring subscriptions made easy.',
			),
			'wibiya'       => array(
				'name'        => 'Mobilize with Wibiya',
				'description' => 'Instantly transform your blog into a stunning, mobile site!',
			),
			'ooyala'       => array(
				'name'        => 'Ooyala',
				'description' => 'Powering personalized video across all screens',
			),
			'socialflow'   => array(
				'name'        => 'SocialFlow',
				'description' => 'Get more readers and traffic from Twitter & Facebook with SocialFlow Optimized Publisher&trade;.',
			),
			'uppsite'      => array(
				'name'        => 'UppSite',
				'description' => 'Create fully automated mobile applications in 2 minutes for free.',
			),
		);
	}

	/**
	 * Set up the plugin's various hooks.
	 *
	 * @access private
	 * @uses add_action() To add various actions
	 */
	private function setup_actions() {
		add_option( self::OPTION_ACTIVE_PLUGINS, array() );

		add_action( 'admin_menu', array( $this, 'action_admin_menu_add_menu_item' ) );

		add_action( 'admin_post_' . self::ACTION_PLUGIN_ACTIVATE, array( $this, 'action_admin_post_plugin_activate' ) );
		add_action( 'admin_post_' . self::ACTION_PLUGIN_DEACTIVATE, array( $this, 'action_admin_post_plugin_deactivate' ) );
	}

	/**
	 * Includes any active plugin files that are enabled via the UI/option.
	 *
	 * @access private
	 */
	private function include_active_plugins() {
		foreach ( $this->get_active_plugins_option() as $plugin ) {
			wpcom_vip_load_plugin( $plugin );
		}
	}

	/** Public Hook Callback Methods ******************************************/

	/**
	 * Adds the new menu item and registers a few more hook callbacks relating to the menu page.
	 */
	public function action_admin_menu_add_menu_item() {
		if ( defined( 'WPCOM_IS_VIP_ENV' ) && WPCOM_IS_VIP_ENV ) {
			$this->hook_suffix = add_submenu_page( self::VIP_MENU_SLUG, __( 'WordPress.com VIP Plugins & Services', 'wpcom-vip-plugins-ui' ), __( 'Plugins & Services', 'wpcom-vip-plugins-ui' ), self::CAPABILITY, self::MENU_SLUG, array( $this, 'display_menu_page' ) );
		} else {
			$this->hook_suffix = add_plugins_page( __( 'WordPress.com VIP Plugins', 'wpcom-vip-plugins-ui' ), __( 'WP.com VIP Plugins', 'wpcom-vip-plugins-ui' ), self::CAPABILITY, self::MENU_SLUG, array( $this, 'display_menu_page' ) );
		}

		add_action( 'admin_print_styles-' . $this->hook_suffix, array( $this, 'menu_page_css' ) );

		// This is required because WPcom_VIP_Plugins_UI_List_Table() is defined inside of a function
		add_filter( 'manage_' . $this->hook_suffix . '_columns', array( $this, 'community_plugins_menu_columns' ) );
	}

	/**
	 * Handles the plugin activation links and activates the requested plugin.
	 */
	public function action_admin_post_plugin_activate() {
		if ( empty( $_GET['plugin'] ) )
			wp_die( sprintf( __( 'Missing % parameter', 'wpcom-vip-plugins-ui' ), '<code>plugin</code>' ) );

		if ( ! current_user_can( self::CAPABILITY ) )
			wp_die( __( 'You do not have sufficient permissions to activate plugins for this site.' ) );

		check_admin_referer( 'activate-' . $_GET['plugin'] );

		if ( ! $this->activate_plugin( $_GET['plugin'] ) )
			wp_die( __( "Failed to activate plugin. Maybe it's already activated?", 'wpcom-vip-plugins-ui' ) );

		wp_safe_redirect( $this->get_menu_url( array( 'activated' => '1' ) ) );
		exit();
	}

	/**
	 * Handles the plugin deactivation links and deactivates the requested plugin.
	 */
	public function action_admin_post_plugin_deactivate() {
		if ( empty( $_GET['plugin'] ) )
			wp_die( sprintf( __( 'Missing % parameter', 'wpcom-vip-plugins-ui' ), '<code>plugin</code>' ) );

		if ( ! current_user_can( self::CAPABILITY ) )
			wp_die( __( 'You do not have sufficient permissions to deactivate plugins for this site.' ) );

		check_admin_referer( 'deactivate-' . $_GET['plugin'] );

		if ( ! $this->deactivate_plugin( $_GET['plugin'] ) )
			wp_die( __( "Failed to deactivate plugin. Maybe it was already deactivated?", 'wpcom-vip-plugins-ui' ) );

		wp_safe_redirect( $this->get_menu_url( array( 'deactivated' => '1' ) ) );
		exit();
	}

	/**
	 * Outputs the contents of the menu page.
	 */
	public function display_menu_page() {
		require_once( dirname( __FILE__ ) . '/class-wpcom-vip-plugins-list-tables.php' );

		// Internal view stats for tracking page usage
		if ( function_exists( 'wpcom_vip_dashboard_bump_stats' ) )
			wpcom_vip_dashboard_bump_stats( 'plugins' );

		$fpp_table = new WPCOM_VIP_Featured_Plugins_List_Table();
		$fpp_table->prepare_items();

		$wp_list_table = new WPcom_VIP_Plugins_UI_List_Table();
		$wp_list_table->prepare_items();

		if ( ! empty( $_GET['activated'] ) )
			add_settings_error( 'wpcom-vip-plugins-ui', 'wpcom-vip-plugins-activated', __( 'Plugin activated.', 'wpcom-vip-plugins-ui' ), 'updated' );
		elseif( ! empty( $_GET['deactivated'] ) )
			add_settings_error( 'wpcom-vip-plugins-ui', 'wpcom-vip-plugins-activated', __( 'Plugin deactivated.', 'wpcom-vip-plugins-ui' ), 'updated' );

?>
<div class="wrap">
	<?php screen_icon( 'plugins' ); ?>
	<h2><?php esc_html_e( 'WordPress.com VIP Plugins & Services', 'wpcom-vip-plugins-ui' ); ?></h2>

	<?php settings_errors( 'wpcom-vip-plugins-ui' ); ?>

	<?php $fpp_table->display(); ?>

	<?php $wp_list_table->display(); ?>
</div>
<?php
	}

	/**
	 * Filters the columns of the Community Plugins table.
	 *
	 * @param array $columns An array of existing columns.
	 * @return array Modified list of columns.
	 */
	public function community_plugins_menu_columns( $columns ) {
		$columns['name']        = 'Community Plugins';
		$columns['description'] = '';

		return $columns;
	}

	/**
	 * Outputs some CSS used on the menu page.
	 */
	public function menu_page_css() {
?>
<style type="text/css">
	table.featuredplugins td {
		padding: 5px 10px 10px 10px;
	}
	table.featuredplugins td.left {
		border-right: 1px solid #dfdfdf;
	}
	table.featuredplugins h3 {
		margin: 0.5em 0;
	}
	table.featuredplugins td img {
		-webkit-border-radius: 3px;
		-moz-border-radius: 3px;
		border-radius: 3px;
		display: block;
		float: left;
		margin-right: 16px;
		margin-bottom: 20px;
		margin-top: 6px;
	}
	table.featuredplugins td div.description {
		float: left;
		max-width: 80%;
	}
	div.tablenav {
		height: 10px;
	}

	.row-actions-visible .deactivate-manually span {
		cursor: help;
		color: gray;
	}
</style>
<?php
	}

	/** Helper Functions ******************************************************/

	/**
	 * Gets the list of VIP plugins that have been activated via the UI.
	 *
	 * @return array List of active VIP plugin slugs.
	 */
	public function get_active_plugins_option() {
		return (array) get_option( self::OPTION_ACTIVE_PLUGINS, array() );
	}

	/**
	 * Generates the URL to activate a VIP plugin.
	 *
	 * @param string $plugin The slug of the VIP plugin to activate.
	 * @return string Activation URL.
	 */
	public function get_plugin_activation_link( $plugin ) {
		return wp_nonce_url( admin_url( 'admin-post.php?action=' . self::ACTION_PLUGIN_ACTIVATE . '&plugin=' . urlencode( $plugin ) ), 'activate-' . $plugin );
	}

	/**
	 * Generates the URL to deactivate a VIP plugin.
	 *
	 * @param string $plugin The slug of the VIP plugin to deactivate.
	 * @return string Deactivation URL.
	 */
	public function get_plugin_deactivation_link( $plugin ) {
		return wp_nonce_url( admin_url( 'admin-post.php?action=' . self::ACTION_PLUGIN_DEACTIVATE . '&plugin=' . urlencode( $plugin ) ), 'deactivate-' . $plugin );
	}

	/**
	 * Determines if a given plugin slug is already activated or not.
	 *
	 * @param string $plugin The slug of the VIP plugin to check.
	 * @return string|false "option" if the activated via UI, "manual" if activated via code, and false if not activated.
	 */
	public function is_plugin_active( $plugin ) {
		if ( in_array( $plugin, $this->get_active_plugins_option() ) )
			return 'option';
		elseif ( in_array( 'plugins/' . $plugin, wpcom_vip_get_loaded_plugins() ) )
			return 'manual';
		else
			return false;
	}

	/**
	 * Filters an array of action links to add an activation or deactivation link.
	 *
	 * @param array $actions Existing actions.
	 * @param string $plugin Plugin slug to generate the link for.
	 * @return array List of actions, including the new one.
	 */
	public function add_activate_or_deactive_action_link( $actions, $plugin ) {
		$is_active = WPcom_VIP_Plugins_UI()->is_plugin_active( $plugin );

		if ( $is_active ) {
			if ( 'option' == $is_active ) {
				$actions['deactivate'] = '<a href="' . esc_url( WPcom_VIP_Plugins_UI()->get_plugin_deactivation_link( $plugin ) ) . '" title="' . esc_attr__( 'Deactivate this plugin' ) . '">' . __( 'Deactivate' ) . '</a>';
			} elseif ( 'manual' == $is_active ) {
				$actions['deactivate-manually'] = '<span title="To deactivate this particular plugin, edit your theme\'s functions.php file">' . __( "Enabled via your theme's code" ) . '</span>';
			}
		} else {
			$actions['activate'] = '<a href="' . esc_url( WPcom_VIP_Plugins_UI()->get_plugin_activation_link( $plugin ) ) . '" title="' . esc_attr__( 'Activate this plugin' ) . '" class="edit">' . __( 'Activate' ) . '</a>';
		}

		return $actions;
	}

	/**
	 * Validates a plugin slug.
	 *
	 * @param string $plugin The slug of the VIP plugin to validate.
	 * @return boolean True if valid, false if not.
	 */
	public function validate_plugin( $plugin ) {
		return ( 0 === validate_file( $plugin ) && file_exists( $this->plugin_folder . '/' . $plugin . '/' . $plugin . '.php' ) );
	}

	/**
	 * Activates a plugin.
	 *
	 * @param string $plugin The slug of the VIP plugin to activate.
	 * @return boolean True if the plugin was activated, false if an error was encountered.
	 */
	public function activate_plugin( $plugin ) {
		global $wpdb;

		if ( ! $this->validate_plugin( $plugin ) ) {
			return false;
		}

		$plugins = $this->get_active_plugins_option();

		// Don't add it twice
		if ( in_array( $plugin, $plugins ) ) {
			return false;
		}

		// Log this to the database table used to track plugin usage across all sites
		if ( defined( 'WPCOM_IS_VIP_ENV' ) && WPCOM_IS_VIP_ENV ) {
			// Double check that there isn't an existing row for this blog ID + slug combination
			$existing = $wpdb->get_row( $wpdb->prepare( "SELECT plugin_enabled_id FROM vip_plugins_enabled WHERE blog_id = %d AND plugin_slug = %s LIMIT 1", $wpdb->blogid, $plugin ) );

			if ( ! $existing ) {
				$wpdb->insert(
					'vip_plugins_enabled',
					array(
						'blog_id'     => $wpdb->blogid,
						'plugin_slug' => $plugin,
					),
					array(
						'%d',
						'%s',
					)
				);
			}

			// Log this action to the audit trail
			if ( function_exists( 'audit_log' ) ) {
				audit_log( 'vip_plugin_activate', null, $plugin );
			}
		}

		$plugins[] = $plugin;

		return update_option( self::OPTION_ACTIVE_PLUGINS, $plugins );
	}

	/**
	 * Deactivates a plugin.
	 *
	 * @param string $plugin The slug of the VIP plugin to deactivate.
	 * @return boolean True if the plugin was deactivated, false if an error was encountered.
	 */
	public function deactivate_plugin( $plugin ) {
		global $wpdb;

		if ( ! $this->validate_plugin( $plugin ) ) {
			return false;
		}

		// Log this to the database table used to track plugin usage across all sites
		if ( defined( 'WPCOM_IS_VIP_ENV' ) && WPCOM_IS_VIP_ENV ) {
			$wpdb->query( $wpdb->prepare( "DELETE FROM vip_plugins_enabled WHERE blog_id = %d AND plugin_slug = %s", $wpdb->blogid, $plugin ) );

			// Log this action to the audit trail
			if ( function_exists( 'audit_log' ) ) {
				audit_log( 'vip_plugin_deactivate', null, $plugin );
			}
		}

		$plugins = $this->get_active_plugins_option();

		if ( ! in_array( $plugin, $plugins ) ) {
			return false;
		}

		// Remove from array and re-index (just to stay clean)
		$plugins = array_values( array_diff( $plugins, array( $plugin ) ) );

		return update_option( self::OPTION_ACTIVE_PLUGINS, $plugins );
	}

	/**
	 * Generates a link to the plugin's menu page.
	 *
	 * @param array $extra_query_args Optional. Extra arguments to pass to add_query_arg().
	 * @return string URL to the plugin's menu page.
	 */
	public function get_menu_url( $extra_query_args = array() ) {
		$menu_url = ( defined( 'WPCOM_IS_VIP_ENV' ) && WPCOM_IS_VIP_ENV ) ? 'admin.php' : 'plugins.php';

		$menu_url = add_query_arg(
			array_merge(
				array( 'page' => self::MENU_SLUG ),
				$extra_query_args
			),
			$menu_url
		);

		$menu_url = admin_url( $menu_url );

		return $menu_url;
	}
}

/**
 * The main function responsible for returning the one true WPcom_VIP_Plugins_UI instance
 * to functions everywhere.
 *
 * Use this function like you would a global variable, except without needing
 * to declare the global.
 *
 * Example: <?php $WPcom_VIP_Plugins_UI = WPcom_VIP_Plugins_UI(); ?>
 *
 * @return The one true WPcom_VIP_Plugins_UI Instance
 */
function WPcom_VIP_Plugins_UI() {
	return WPcom_VIP_Plugins_UI::instance();
}

// Start up the class immediately
WPcom_VIP_Plugins_UI();

?>
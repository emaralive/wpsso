<?php
/**
 * License: GPLv3
 * License URI: https://www.gnu.org/licenses/gpl.txt
 * Copyright 2012-2020 Jean-Sebastien Morisset (https://wpsso.com/)
 */

if ( ! defined( 'ABSPATH' ) ) {

	die( 'These aren\'t the droids you\'re looking for.' );
}

if ( ! defined( 'WPSSO_PLUGINDIR' ) ) {

	die( 'Do. Or do not. There is no try.' );
}

if ( ! class_exists( 'WpssoAdmin' ) ) {

	class WpssoAdmin {

		protected $p;
		protected $head;
		protected $filters;
		protected $menu_id;
		protected $menu_name;
		protected $menu_lib;
		protected $menu_ext;	// Lowercase acronyn for plugin or add-on.
		protected $pagehook;
		protected $pageref_url;
		protected $pageref_title;

		protected static $pkg_cache = array();

		public static $readme = array();

		public $form    = null;
		public $lang    = array();
		public $submenu = array();

		/**
		 * Instantiated by Wpsso->set_objects() when is_admin() is true.
		 */
		public function __construct( &$plugin ) {

			$this->p =& $plugin;

			if ( $this->p->debug->enabled ) {

				$this->p->debug->mark();
			}

			$doing_ajax = SucomUtilWP::doing_ajax();

			require_once WPSSO_PLUGINDIR . 'lib/admin-head.php';

			$this->head = new WpssoAdminHead( $plugin );

			require_once WPSSO_PLUGINDIR . 'lib/admin-filters.php';

			$this->filters = new WpssoAdminFilters( $plugin );

			/**
			 * The WpssoScript add_iframe_inline_script() method includes jQuery in the thickbox iframe to add the
			 * iframe_parent arguments when the Install or Update button is clicked.
			 *
			 * These class properties are used by both the WpssoAdmin plugin_complete_actions() and
			 * plugin_complete_redirect() methods to direct the user back to the thickbox iframe parent (aka the plugin
			 * licenses settings page) after plugin installation / activation / update.
			 */
			if ( ! empty( $_GET[ 'wpsso_pageref_title' ] ) ) {

				$this->pageref_title = esc_html( urldecode( $_GET[ 'wpsso_pageref_title' ] ) );
			}

			if ( ! empty( $_GET[ 'wpsso_pageref_url' ] ) ) {

				$this->pageref_url = esc_url_raw( urldecode( $_GET[ 'wpsso_pageref_url' ] ) );
			}

			add_action( 'activated_plugin', array( $this, 'reset_admin_check_options' ), 10 );
			add_action( 'after_switch_theme', array( $this, 'reset_admin_check_options' ), 10 );
			add_action( 'upgrader_process_complete', array( $this, 'reset_admin_check_options' ), 10 );
			add_action( 'update_option_home', array( $this, 'site_address_changed' ), PHP_INT_MAX, 3 );
			add_action( 'sucom_update_option_home', array( $this, 'site_address_changed' ), PHP_INT_MAX, 3 );

			/**
			 * This filter re-sorts (if necessary) the active plugins array to load WPSSO Core before its add-ons.
			 */
			add_filter( 'pre_update_option_active_plugins', array( $this, 'pre_update_active_plugins' ), 10, 3 );

			/**
			 * Define and disable the "Expect: 100-continue" header.
			 */
			add_filter( 'http_request_args', array( $this, 'add_expect_header' ), 1000, 2 );

			add_filter( 'http_request_host_is_external', array( $this, 'allow_safe_hosts' ), 1000, 3 );

			/**
			 * Provide plugin data / information from the readme.txt for additional add-ons. Don't hook the
			 * 'plugins_api_result' filter if the update manager is active as it provides more complete plugin data
			 * than what's available from the readme.txt.
			 */
			if ( empty( $this->p->avail[ 'p_ext' ][ 'um' ] ) ) {	// Since WPSSO UM v1.6.0.

				add_filter( 'plugins_api_result', array( $this, 'external_plugin_data' ), 1000, 3 );	// Since WP v2.7.
			}

			/**
			 * Add plugin / add-on settings links to the WordPress Plugins page. Hook this filter even when doing ajax
			 * since the WordPress plugin search results are created using an ajax call.
			 *
			 * The 5th argument is $menu_lib = 'submenu', so always limit the method arguments to 4.
			 */
			add_filter( 'plugin_action_links', array( $this, 'add_plugin_action_links' ), 1000, 4 );

			if ( is_multisite() ) {

					/**
					 * The 5th argument is $menu_lib = 'sitesubmenu', so always limit the method arguments to 4.
					 */
					add_filter( 'network_admin_plugin_action_links', array( $this, 'add_site_plugin_action_links' ), 1000, 4 );
			}

			if ( ! $doing_ajax ) {

				/**
				 * The admin_menu action is run before admin_init.
				 */
				add_action( 'admin_menu', array( $this, 'load_menu_objects' ), -1000 );
				add_action( 'admin_menu', array( $this, 'add_admin_menus' ), WPSSO_ADD_MENU_PRIORITY );
				add_action( 'admin_menu', array( $this, 'add_admin_submenus' ), WPSSO_ADD_SUBMENU_PRIORITY );

				add_action( 'admin_init', array( $this, 'add_plugins_page_upgrade_notice' ) );
				add_action( 'admin_init', array( $this, 'check_tmpl_head_attributes' ), 20 );
				add_action( 'admin_init', array( $this, 'check_wp_config_constants' ), 10 );
				add_action( 'admin_init', array( $this, 'register_setting' ) );

				if ( is_multisite() ) {

					add_action( 'network_admin_menu', array( $this, 'load_network_menu_objects' ), -1000 );
					add_action( 'network_admin_menu', array( $this, 'add_network_admin_menus' ), WPSSO_ADD_MENU_PRIORITY );
					add_action( 'network_admin_edit_' . WPSSO_SITE_OPTIONS_NAME, array( $this, 'save_site_options' ) );
				}

				add_filter( 'install_plugin_complete_actions', array( $this, 'plugin_complete_actions' ), 1000, 1 );
				add_filter( 'update_plugin_complete_actions', array( $this, 'plugin_complete_actions' ), 1000, 1 );

				add_filter( 'wp_redirect', array( $this, 'plugin_complete_redirect' ), 1000, 1 );
				add_filter( 'wp_redirect', array( $this, 'profile_updated_redirect' ), -100, 2 );

				/**
				 * get_notice_system() can return true, false, or an array of notice types to include in the menu.
				 */
				if ( $this->p->notice->get_notice_system() ) {

					add_action( 'admin_bar_menu', array( $this, 'add_admin_tb_notices_menu_item' ), WPSSO_TB_NOTICE_MENU_ORDER );
				}
			}
		}

		public function load_network_menu_objects() {

			if ( $this->p->debug->enabled ) {

				$this->p->debug->mark();
			}

			$this->load_menu_objects( array( 'submenu', 'sitesubmenu' ) );
		}

		public function load_menu_objects( $menu_libs = array() ) {

			if ( $this->p->debug->enabled ) {

				$this->p->debug->mark();
			}

			$this->get_pkg_info();	// Returns an array from cache.

			if ( empty( $menu_libs ) ) {

				$menu_libs = array( 'dashboard', 'plugins', 'profile', 'settings', 'submenu', 'tools', 'users' );
			}

			foreach ( $menu_libs as $menu_lib ) {

				foreach ( $this->p->cf[ 'plugin' ] as $ext => $info ) {

					if ( ! isset( $info[ 'lib' ][ $menu_lib ] ) ) {	// Not all add-ons have submenus.

						continue;
					}

					foreach ( $info[ 'lib' ][ $menu_lib ] as $menu_id => $menu_name ) {

						$classname = apply_filters( $ext . '_load_lib', false, $menu_lib . '/' . $menu_id );

						if ( is_string( $classname ) && class_exists( $classname ) ) {

							if ( $this->p->debug->enabled ) {

								$this->p->debug->log( 'loading classname ' . $classname . ' for menu id ' . $menu_id );
							}

							if ( ! empty( $info[ 'text_domain' ] ) ) {

								$menu_name = _x( $menu_name, 'lib file description', $info[ 'text_domain' ] );
							}

							$this->submenu[ $menu_id ] = new $classname( $this->p, $menu_id, $menu_name, $menu_lib, $ext );

						} elseif ( $this->p->debug->enabled ) {

							$this->p->debug->log( 'classname not found for menu lib ' . $menu_lib . '/' . $menu_id );
						}
					}
				}
			}
		}

		public function get_menu_dashicon_html( $menu_id, $css_class = '' ) {

			$dashicon = $this->get_menu_dashicon( $menu_id );

			return '<div class="' . trim( $css_class . ' dashicons-before dashicons-' . $dashicon ) . '"></div>';
		}

		public function get_menu_dashicon( $menu_id ) {

			$dashicon = 'admin-generic';

			if ( ! empty( $this->p->cf[ 'menu' ][ 'dashicons' ][ $menu_id ] ) ) {

				$dashicon = $this->p->cf[ 'menu' ][ 'dashicons' ][ $menu_id ];

			} elseif ( ! empty( $this->p->cf[ 'menu' ][ 'dashicons' ][ '*' ] ) ) {

				$dashicon = $this->p->cf[ 'menu' ][ 'dashicons' ][ '*' ];
			}

			return $dashicon;
		}

		/**
		 * Deprecated since 2020/11/25.
		 */
		public function plugin_pkg_info() {

			return $this->get_pkg_info();
		}

		public function get_pkg_info() {

			if ( ! empty( self::$pkg_cache ) ) {	// Only execute once.

				return self::$pkg_cache;
			}

			$pkg_info = array();	// Init a new pkg info array.

			foreach ( $this->p->cf[ 'plugin' ] as $ext => $info ) {

				$ext_pdir    = $this->p->check->pp( $ext, $li = false );
				$ext_auth_id = $this->p->check->get_ext_auth_id( $ext );
				$ext_pp      = $ext_auth_id && $this->p->check->pp( $ext, $li = true, WPSSO_UNDEF ) === WPSSO_UNDEF ? true : false;
				$ext_stat    = ( $ext_pp ? 'L' : ( $ext_pdir ? 'U' : 'S' ) ) . ( $ext_auth_id ? '*' : '' );

				$info_name_transl = _x( $info[ 'name' ], 'plugin name', 'wpsso' );
				$dist_pro_transl  = _x( $this->p->cf[ 'dist' ][ 'pro' ], 'distribution name', 'wpsso' );
				$dist_std_transl  = _x( $this->p->cf[ 'dist' ][ 'std' ], 'distribution name', 'wpsso' );

				$pkg_info[ $ext ][ 'pdir' ]       = $ext_pdir;
				$pkg_info[ $ext ][ 'pp' ]         = $ext_pp;
				$pkg_info[ $ext ][ 'dist' ]       = $ext_pp ? $dist_pro_transl : $dist_std_transl;
				$pkg_info[ $ext ][ 'short' ]      = $info[ 'short' ];
				$pkg_info[ $ext ][ 'short_dist' ] = $info[ 'short' ] . ' ' . $pkg_info[ $ext ][ 'dist' ];
				$pkg_info[ $ext ][ 'short_pro' ]  = $info[ 'short' ] . ' ' . $dist_pro_transl;
				$pkg_info[ $ext ][ 'short_std' ]  = $info[ 'short' ] . ' ' . $dist_std_transl;
				$pkg_info[ $ext ][ 'gen' ]        = $info[ 'short' ] . ( isset( $info[ 'version' ] ) ? ' ' . $info[ 'version' ] . '/' . $ext_stat : '' );
				$pkg_info[ $ext ][ 'name' ]       = $info_name_transl;
				$pkg_info[ $ext ][ 'name_dist' ]  = SucomUtil::get_dist_name( $info_name_transl, $pkg_info[ $ext ][ 'dist' ] );
				$pkg_info[ $ext ][ 'name_pro' ]   = SucomUtil::get_dist_name( $info_name_transl, $dist_pro_transl );
				$pkg_info[ $ext ][ 'name_std' ]   = SucomUtil::get_dist_name( $info_name_transl, $dist_std_transl );
			}

			return self::$pkg_cache = $pkg_info;
		}

		public function add_network_admin_menus() {

			if ( $this->p->debug->enabled ) {

				$this->p->debug->mark();
			}

			$this->add_admin_menus( 'sitesubmenu' );
		}

		/**
		 * Add a new main menu and its sub-menu items.
		 *
		 * $menu_lib = 'dashboard', 'plugins', 'profile', 'settings', 'submenu', 'sitesubmenu', 'tools', or 'users'
		 */
		public function add_admin_menus( $menu_lib = '' ) {

			if ( $this->p->debug->enabled ) {

				$this->p->debug->mark();
			}

			if ( empty( $menu_lib ) ) {	// Just in case.

				$menu_lib = 'submenu';
			}

			$libs = $this->p->cf[ '*' ][ 'lib' ][ $menu_lib ];

			$this->menu_id   = key( $libs );
			$this->menu_name = $libs[ $this->menu_id ];
			$this->menu_lib  = $menu_lib;
			$this->menu_ext  = $this->p->id;	// Lowercase acronyn for plugin or add-on.

			if ( isset( $this->submenu[ $this->menu_id ] ) ) {

				$menu_slug = 'wpsso-' . $this->menu_id;

				$this->submenu[ $this->menu_id ]->add_menu_page( $menu_slug );
			}

			$sorted_menu   = array();
			$unsorted_menu = array();

			$top_first_id = false;
			$top_last_id  = false;
			$ext_first_id = false;
			$ext_last_id  = false;

			foreach ( $this->p->cf[ 'plugin' ] as $ext => $info ) {

				if ( ! isset( $info[ 'lib' ][ $menu_lib ] ) ) {	// Not all add-ons have submenus.

					continue;
				}

				foreach ( $info[ 'lib' ][ $menu_lib ] as $menu_id => $menu_name ) {

					if ( ! empty( $info[ 'text_domain' ] ) ) {

						$menu_name = _x( $menu_name, 'lib file description', $info[ 'text_domain' ] );
					}

					$ksort_key = $menu_name . '-' . $menu_id;

					$parent_slug = 'wpsso-' . $this->menu_id;

					if ( 'wpsso' === $ext ) {

						$unsorted_menu[] = array( $parent_slug, $menu_id, $menu_name, $menu_lib, $ext );

						if ( false === $top_first_id ) {

							$top_first_id = $menu_id;
						}

						$top_last_id = $menu_id;

					} else {

						$sorted_menu[ $ksort_key ] = array( $parent_slug, $menu_id, $menu_name, $menu_lib, $ext );

						if ( false === $ext_first_id ) {

							$ext_first_id = $menu_id;
						}

						$ext_last_id = $menu_id;
					}
				}
			}

			ksort( $sorted_menu,  SORT_FLAG_CASE | SORT_NATURAL );

			foreach ( array_merge( $unsorted_menu, $sorted_menu ) as $key => $arg ) {

				if ( $arg[ 1 ] === $top_first_id ) {

					$css_class = 'top-first-submenu-page';

				} elseif ( $arg[ 1 ] === $top_last_id ) {

					$css_class = 'top-last-submenu-page';	// Underlined with add-ons.

					if ( empty( $ext_first_id ) ) {
						$css_class .= ' no-add-ons';
					} else {
						$css_class .= ' with-add-ons';
					}

				} elseif ( $arg[ 1 ] === $ext_first_id ) {

					$css_class = 'ext-first-submenu-page';

				} elseif ( $arg[ 1 ] === $ext_last_id ) {

					$css_class = 'ext-last-submenu-page';

				} else {

					$css_class = '';
				}

				if ( isset( $this->submenu[ $arg[ 1 ] ] ) ) {

					$this->submenu[ $arg[ 1 ] ]->add_submenu_page( $arg[ 0 ], '', '', '', '', $css_class );

				} else {

					$this->add_submenu_page( $arg[ 0 ], $arg[ 1 ], $arg[ 2 ], $arg[ 3 ], $arg[ 4 ], $css_class );
				}
			}
		}

		/**
		 * Add sub-menu items to existing menus (dashboard, plugin, profile, and setting).
		 */
		public function add_admin_submenus() {

			foreach ( array( 'dashboard', 'plugins', 'profile', 'settings', 'tools', 'users' ) as $menu_lib ) {

				/**
				 * Match WordPress behavior (users page for admins, profile page for everyone else).
				 */
				if ( 'profile' === $menu_lib && current_user_can( 'list_users' ) ) {

					$parent_slug = $this->p->cf[ 'wp' ][ 'admin' ][ 'users' ][ 'page' ];

				} else {

					$parent_slug = $this->p->cf[ 'wp' ][ 'admin' ][ $menu_lib ][ 'page' ];
				}

				$sorted_menu = array();

				foreach ( $this->p->cf[ 'plugin' ] as $ext => $info ) {

					if ( ! isset( $info[ 'lib' ][ $menu_lib ] ) ) {	// Not all add-ons have submenus.

						continue;
					}

					foreach ( $info[ 'lib' ][ $menu_lib ] as $menu_id => $menu_name ) {

						if ( ! empty( $info[ 'text_domain' ] ) ) {

							$menu_name = _x( $menu_name, 'lib file description', $info[ 'text_domain' ] );
						}

						$ksort_key = $menu_name . '-' . $menu_id;

						$sorted_menu[ $ksort_key ] = array( $parent_slug, $menu_id, $menu_name, $menu_lib, $ext );
					}
				}

				ksort( $sorted_menu, SORT_FLAG_CASE | SORT_NATURAL );

				foreach ( $sorted_menu as $key => $arg ) {

					if ( isset( $this->submenu[ $arg[ 1 ] ] ) ) {

						$this->submenu[ $arg[ 1 ] ]->add_submenu_page( $arg[ 0 ] );

					} else {

						$this->add_submenu_page( $arg[ 0 ], $arg[ 1 ], $arg[ 2 ], $arg[ 3 ], $arg[ 4 ] );
					}
				}
			}
		}

		/**
		 * Called by show_setting_page() and extended by the sitesubmenu classes to load site options instead.
		 *
		 * $menu_ext is the lowercase acronyn for the plugin or add-on.
		 */
		protected function set_form_object( $menu_ext ) {	// $menu_ext required for text_domain.

			if ( $this->p->debug->enabled ) {

				$this->p->debug->mark();

				$this->p->debug->log( 'setting form object for ' . $menu_ext );
			}

			$def_opts = $this->p->opt->get_defaults();

			$this->form = new SucomForm( $this->p, WPSSO_OPTIONS_NAME, $this->p->options, $def_opts, $menu_ext );
		}

		/**
		 * $menu_ext is the lowercase acronyn for the plugin or add-on.
		 */
		public function &get_form_object( $menu_ext ) {	// $menu_ext required for text_domain.

			if ( $this->p->debug->enabled ) {

				$this->p->debug->mark();
			}

			if ( ! isset( $this->form ) ) {

				if ( $this->p->debug->enabled ) {

					$this->p->debug->log( 'form object not defined' );
				}

				$this->set_form_object( $menu_ext );

			} elseif ( $this->form->get_menu_ext() !== $menu_ext ) {

				if ( $this->p->debug->enabled ) {

					$this->p->debug->log( 'form object text domain does not match' );
				}

				$this->set_form_object( $menu_ext );
			}

			return $this->form;
		}

		public function register_setting() {

			register_setting( 'wpsso_setting', WPSSO_OPTIONS_NAME, $args = array(
				'sanitize_callback' => array( $this, 'registered_setting_sanitation' ),
			) );
		}

		public function add_plugins_page_upgrade_notice() {

			foreach ( $this->p->cf[ 'plugin' ] as $ext => $info ) {

				if ( ! empty( $info[ 'base' ] ) ) {

					add_action( 'in_plugin_update_message-' . $info[ 'base' ], array( $this, 'show_upgrade_notice' ), 10, 2 );
				}
			}
		}

		public function show_upgrade_notice( $data, $response ) {

			if ( isset( $data[ 'upgrade_notice' ] ) ) {	// Just in case.

				echo '<span style="display:table;border-collapse:collapse;margin-left:26px;">';
				echo '<span style="display:table-cell;">' . strip_tags( $data[ 'upgrade_notice' ] ) . '</span>';
				echo '</span>';
			}
		}

		protected function add_menu_page( $menu_slug ) {

			global $wp_version;

			$pkg_info    = $this->get_pkg_info();	// Returns an array from cache.
			$page_title  = $pkg_info[ 'wpsso' ][ 'short_dist' ] . ' &mdash; ' . $this->menu_name;
			$menu_title  = _x( $this->p->cf[ 'menu' ][ 'title' ], 'menu title', 'wpsso' );
			$cf_wp_admin = $this->p->cf[ 'wp' ][ 'admin' ];
			$capability  = isset( $cf_wp_admin[ $this->menu_lib ][ 'cap' ] ) ? $cf_wp_admin[ $this->menu_lib ][ 'cap' ] : 'manage_options';
			$icon_url    = 'none';	// Icon provided by WpssoStyle::add_admin_page_style().
			$function    = array( $this, 'show_setting_page' );
			$position    = WPSSO_MENU_ORDER;

			$this->pagehook = add_menu_page( $page_title, $menu_title, $capability, $menu_slug, $function, $icon_url, $position );

			add_action( 'load-' . $this->pagehook, array( $this, 'load_setting_page' ) );
		}

		protected function add_submenu_page( $parent_slug, $menu_id = '', $menu_name = '', $menu_lib = '', $menu_ext = '', $css_class = '' ) {

			if ( empty( $menu_id ) ) {

				$menu_id = $this->menu_id;
			}

			if ( empty( $menu_name ) ) {

				$menu_name = $this->menu_name;
			}

			if ( empty( $menu_lib ) ) {

				$menu_lib = $this->menu_lib;
			}

			if ( empty( $menu_ext ) ) {

				$menu_ext = $this->menu_ext;	// Lowercase acronyn for plugin or add-on.

				if ( empty( $menu_ext ) ) {

					$menu_ext = $this->p->id;
				}
			}

			global $wp_version;

			/**
			 * WordPress version 3.8 is required for dashicons.
			 */
			if ( ( $menu_lib === 'submenu' || $menu_lib === 'sitesubmenu' ) && version_compare( $wp_version, '3.8', '>=' ) ) {

				$css_class = trim( 'wpsso-menu-item wpsso-' . $menu_id . ' ' . $css_class );

				$dashicon_html = $this->get_menu_dashicon_html( $menu_id, $css_class );

				$menu_title = $dashicon_html . '<div class="' . $css_class . ' menu-item-label">' . $menu_name . '</div>';

			} else {

				$menu_title = $menu_name;
			}

			$pkg_info    = $this->get_pkg_info();	// Returns an array from cache.
			$page_title  = $pkg_info[ $menu_ext ][ 'short_dist' ] . ' &mdash; ' . $menu_name;
			$cf_wp_admin = $this->p->cf[ 'wp' ][ 'admin' ];
			$capability  = isset( $cf_wp_admin[ $menu_lib ][ 'cap' ] ) ? $cf_wp_admin[ $menu_lib ][ 'cap' ] : 'manage_options';
			$menu_slug   = 'wpsso-' . $menu_id;
			$function    = array( $this, 'show_setting_page' );
			$position    = null;

			if ( isset( $cf_wp_admin[ $menu_lib ][ 'sub' ][ $menu_id ] ) ) {

				$cf_menu_id = $cf_wp_admin[ $menu_lib ][ 'sub' ][ $menu_id ];
				$capability = isset( $cf_menu_id[ 'cap' ] ) ? $cf_menu_id[ 'cap' ] : $capability;
				$position   = isset( $cf_menu_id[ 'pos' ] ) ? $cf_menu_id[ 'pos' ] : $position;
			}

			$this->pagehook = add_submenu_page( $parent_slug, $page_title, $menu_title, $capability, $menu_slug, $function, $position );

			add_action( 'load-' . $this->pagehook, array( $this, 'load_setting_page' ) );
		}

		/**
		 * Add plugin links for the WordPress network plugins page.
		 */
		public function add_site_plugin_action_links( $action_links, $plugin_base, $plugin_data, $context, $menu_lib = 'sitesubmenu' ) {

			return $this->add_plugin_action_links( $action_links, $plugin_base, $plugin_data, $context, $menu_lib );
		}

		/**
		 * Add plugin links for the WordPress plugins page.
		 *
		 * $plugin_data is an array of plugin data. See get_plugin_data().
		 * $context can be 'all', 'active', 'inactive', 'recently_activated', 'upgrade', 'mustuse', 'dropins', or 'search'.
		 */
		public function add_plugin_action_links( $action_links, $plugin_base, $plugin_data, $context, $menu_lib = 'submenu'  ) {

			if ( ! isset( $this->p->cf[ '*' ][ 'base' ][ $plugin_base ] ) ) {

				return $action_links;
			}

			$ext               = $this->p->cf[ '*' ][ 'base' ][ $plugin_base ];
			$info              = $this->p->cf[ 'plugin' ][ $ext ];
			$settings_page     = empty( $info[ 'lib' ][ $menu_lib ] ) ? '' : key( $info[ 'lib' ][ $menu_lib ] );
			$settings_page_url = $this->p->util->get_admin_url( $settings_page );

			switch ( $ext ) {

				case 'wpsso':

					$addons_page     = 'sitesubmenu' === $menu_lib ? 'site-addons' : 'addons';
					$addons_page_url = $this->p->util->get_admin_url( $addons_page );

					if ( ! empty( $settings_page ) ) {

						$action_links[] = '<a href="' . $settings_page_url . '">' . _x( 'Plugin Settings', 'plugin action link', 'wpsso' ) . '</a>';
					}

					$action_links[] = '<a href="' . $addons_page_url . '">' . _x( 'Complementary Add-ons', 'plugin action link', 'wpsso' ) . '</a>';

					break;

				default:

					if ( ! empty( $settings_page ) ) {

						$action_links[] = '<a href="' . $settings_page_url . '">' . _x( 'Add-on Settings', 'plugin action link', 'wpsso' ) . '</a>';
					}

					break;
			}

			return $action_links;
		}

		/**
		 * Plugin links for the addons and licenses settings page.
		 */
		public function get_ext_action_links( $ext, $info, &$tabindex = false ) {

			$pkg_info = $this->get_pkg_info();	// Returns an array from cache.

			$action_links = array();

			if ( ! empty( $info[ 'base' ] ) ) {

				$install_url = is_multisite() ?
					network_admin_url( 'plugin-install.php', null ) :
					get_admin_url( $blog_id = null, 'plugin-install.php' );

				$details_url = add_query_arg( array(
					'plugin'    => $info[ 'slug' ],
					'tab'       => 'plugin-information',
					'TB_iframe' => 'true',
					'width'     => $this->p->cf[ 'wp' ][ 'tb_iframe' ][ 'width' ],
					'height'    => $this->p->cf[ 'wp' ][ 'tb_iframe' ][ 'height' ],
				), $install_url );

				if ( SucomPlugin::is_plugin_installed( $info[ 'base' ], $use_cache = true ) ) {

					if ( SucomPlugin::have_plugin_update( $info[ 'base' ] ) ) {

						$action_links[] = '<a href="' . $details_url . '" class="thickbox" tabindex="' . ++$tabindex . '">' .
							'<font color="red">' . ( 'wpsso' === $ext ? _x( 'Plugin Details and Update',
								'plugin action link', 'wpsso' ) : _x( 'Add-on Details and Update',
									'plugin action link', 'wpsso' ) ) . '</font></a>';
					} else {

						$action_links[] = '<a href="' . $details_url . '" class="thickbox" tabindex="' . ++$tabindex . '">' .
							( 'wpsso' === $ext ? _x( 'Plugin Details', 'plugin action link', 'wpsso' ) :
								_x( 'Add-on Details', 'plugin action link', 'wpsso' ) ) . '</a>';
					}

				} else {

					$action_links[] = '<a href="' . $details_url . '" class="thickbox" tabindex="' . ++$tabindex . '">' .
						( 'wpsso' === $ext ? _x( 'Plugin Details and Install', 'plugin action link', 'wpsso' ) :
							_x( 'Add-on Details and Install', 'plugin action link', 'wpsso' ) ) . '</a>';
				}

			} elseif ( ! empty( $info[ 'url' ][ 'home' ] ) ) {

				$action_links[] = '<a href="' . $info[ 'url' ][ 'home' ] . '" tabindex="' . ++$tabindex . '">' .
					_x( 'About Page', 'plugin action link', 'wpsso' ) . '</a>';
			}

			if ( ! empty( $info[ 'url' ][ 'docs' ] ) ) {

				$action_links[] = '<a href="' . $info[ 'url' ][ 'docs' ] . '"' .
					( false !== $tabindex ? ' tabindex="' . ++$tabindex . '"' : '' ) . '>' .
						_x( 'Documentation', 'plugin action link', 'wpsso' ) . '</a>';
			}

			if ( ! empty( $info[ 'url' ][ 'support' ] ) && $pkg_info[ $ext ][ 'pp' ] ) {

				$action_links[] = '<a href="' . $info[ 'url' ][ 'support' ] . '"' .
					( false !== $tabindex ? ' tabindex="' . ++$tabindex . '"' : '' ) . '>' .
						sprintf( _x( '%s Support', 'plugin action link', 'wpsso' ),
							_x( $this->p->cf[ 'dist' ][ 'pro' ], 'distribution name', 'wpsso' ) ) . '</a>';

			} elseif ( ! empty( $info[ 'url' ][ 'forum' ] ) ) {

				$action_links[] = '<a href="' . $info[ 'url' ][ 'forum' ] . '"' .
					( false !== $tabindex ? ' tabindex="' . ++$tabindex . '"' : '' ) . '>' .
						_x( 'Community Forum', 'plugin action link', 'wpsso' ) . '</a>';
			}

			if ( ! empty( $info[ 'url' ][ 'purchase' ] ) ) {

				$action_links[] = $this->p->msgs->get( 'pro-purchase-link', array(
					'ext'      => $ext,
					'url'      => $info[ 'url' ][ 'purchase' ],
					'tabindex' => false !== $tabindex ? ++$tabindex : false,
				) );
			}

			return $action_links;
		}

		/**
		 * Define and disable the "Expect: 100-continue" header.
		 *
		 * $req should be an array, so make sure other filters aren't giving us a string or boolean.
		 */
		public function add_expect_header( $req, $url ) {

			if ( ! is_array( $req ) ) {

				$req = array();
			}

			if ( ! isset( $req[ 'headers' ] ) || ! is_array( $req[ 'headers' ] ) ) {

				$req[ 'headers' ] = array();
			}

			$req[ 'headers' ][ 'Expect' ] = '';

			return $req;
		}

		public function allow_safe_hosts( $is_allowed, $ip, $url ) {

			if ( $is_allowed ) {	// Already allowed.

				return $is_allowed;
			}

			if ( isset( $this->p->cf[ 'extend' ] ) ) {

				foreach ( $this->p->cf[ 'extend' ] as $host ) {

					if ( 0 === strpos( $url, $host ) ) {

						return true;
					}
				}
			}

			return $is_allowed;
		}

		/**
		 * Provide plugin data / information from the readme.txt for additional add-ons.
		 */
		public function external_plugin_data( $result, $action = null, $args = null ) {

			if ( $this->p->debug->enabled ) {

				$this->p->debug->mark();
			}

			if ( $action !== 'plugin_information' ) {				// This filter only provides plugin data.

				return $result;

			} elseif ( empty( $args->slug ) ) {					// Make sure we have a slug in the request.

				return $result;

			} elseif ( empty( $this->p->cf[ '*' ][ 'slug' ][ $args->slug ] ) ) {	// Make sure the plugin slug is one of ours.

				return $result;

			} elseif ( isset( $result->slug ) && $result->slug === $args->slug ) {	// If the object from WordPress looks complete, return it as-is.

				return $result;
			}

			$ext = $this->p->cf[ '*' ][ 'slug' ][ $args->slug ];			// Get the add-on acronym to read its config.

			if ( empty( $this->p->cf[ 'plugin' ][ $ext ] ) ) {			// Make sure we have a config for that acronym.

				return $result;
			}

			$plugin_data = $this->get_plugin_data( $ext, $read_cache = true );	// Get plugin data from the plugin readme.

			if ( empty( $plugin_data ) ) {						// Make sure we have some data to return.

				return $result;
			}

			$plugin_data->external = true;						// Let WordPress known that this is not a wordpress.org plugin.

			return $plugin_data;
		}

		/**
		 * Get the plugin readme and convert array elements to a plugin data object.
		 */
		public function get_plugin_data( $ext, $read_cache = true ) {

			$data = new StdClass;

			$info = $this->p->cf[ 'plugin' ][ $ext ];

			$readme = $this->get_readme_info( $ext, $read_cache );

			if ( empty( $readme ) ) {	// Make sure we got something back.

				return array();
			}

			foreach ( array(

				/**
				 * Readme array => Plugin object.
				 */
				'plugin_name'       => 'name',
				'plugin_slug'       => 'slug',
				'base'              => 'plugin',
				'stable_tag'        => 'version',
				'tested_up_to'      => 'tested',
				'requires_at_least' => 'requires',
				'home'              => 'homepage',
				'latest'            => 'download_link',
				'author'            => 'author',
				'upgrade_notice'    => 'upgrade_notice',
				'last_updated'      => 'last_updated',
				'sections'          => 'sections',
				'remaining_content' => 'other_notes',	// Added to sections.
				'banners'           => 'banners',
				'icons'             => 'icons',
			) as $readme_key => $prop_name ) {

				switch ( $readme_key ) {

					case 'base':	// From plugin config.

						if ( ! empty( $info[ $readme_key ] ) ) {

							$data->$prop_name = $info[ $readme_key ];
						}

						break;

					case 'home':	// From plugin config.

						if ( ! empty( $info[ 'url' ][ 'purchase' ] ) ) {	// Check for purchase url first.

							$data->$prop_name = $info[ 'url' ][ 'purchase' ];

						} elseif ( ! empty( $info[ 'url' ][ $readme_key ] ) ) {

							$data->$prop_name = $info[ 'url' ][ $readme_key ];
						}

						break;

					case 'latest':	// From plugin config.

						if ( ! empty( $info[ 'url' ][ $readme_key ] ) ) {

							$data->$prop_name = $info[ 'url' ][ $readme_key ];
						}

						break;

					case 'banners':	// From plugin config.
					case 'icons':	// From plugin config.

						if ( ! empty( $info[ 'assets' ][ $readme_key ] ) ) {

							$data->$prop_name = $info[ 'assets' ][ $readme_key ];	// Array with 1x / 2x images.
						}

						break;

					case 'remaining_content':

						if ( ! empty( $readme[ $readme_key ] ) ) {

							$data->sections[ $prop_name ] = $readme[ $readme_key ];
						}

						break;

					default:

						if ( ! empty( $readme[ $readme_key ] ) ) {

							$data->$prop_name = $readme[ $readme_key ];
						}

						break;
				}
			}

			return $data;
		}

		/**
		 * This method receives only a partial options array, so re-create a full one.
		 *
		 * WordPress handles the actual saving of the options to the database table.
		 */
		public function registered_setting_sanitation( $opts ) {

			/**
			 * Just in case - make sure we do not return or save empty settings.
			 */
			if ( ! is_array( $opts ) ) {

				$opts = $this->p->options;
			}

			/**
			 * Clear any old notices for the current user before sanitation checks.
			 */
			$this->p->notice->clear();

			$def_opts = $this->p->opt->get_defaults();

			$opts = SucomUtil::restore_checkboxes( $opts );
			$opts = array_merge( $this->p->options, $opts );
			$opts = $this->p->opt->sanitize( $opts, $def_opts, $network = false );
			$opts = apply_filters( 'wpsso_save_setting_options', $opts, $network = false, $upgrading = false );

			/**
			 * Update the current options with any changes.
			 */
			$this->p->options = $opts;

			/**
			 * Create a clear cache URL from the current page URL.
			 */
			$clear_cache_url = SucomUtil::get_request_value( '_wp_http_referer', 'POST', $this->p->util->get_admin_url() );
			$clear_cache_url = add_query_arg( 'wpsso-action', 'clear_cache', $clear_cache_url );
			$clear_cache_url = wp_nonce_url( $clear_cache_url, WpssoAdmin::get_nonce_action(), WPSSO_NONCE_NAME );

			$clear_cache_link = '<a href="' . $clear_cache_url . '">' . _x( 'Clear All Caches', 'submit button', 'wpsso' ) . '</a>';

			$this->p->notice->upd( '<strong>' . __( 'Plugin settings have been saved.', 'wpsso' ) . '</strong> ' .
				sprintf( __( 'Note that some caches may take several days to expire and reflect these changes (or %s now).',
					'wpsso' ), $clear_cache_link ) );

			if ( empty( $opts[ 'plugin_filter_content' ] ) ) {

				$notice_key = 'notice-content-filters-disabled';

				if ( $notice_msg = $this->p->msgs->get( $notice_key ) ) {

					$this->p->notice->inf( $notice_msg, null, $notice_key, $dismiss_time = true );
				}
			}

			if ( empty( $opts[ 'plugin_check_img_dims' ] ) ) {

				$notice_key = 'notice-check-img-dims-disabled';

				if ( $notice_msg = $this->p->msgs->get( $notice_key ) ) {

					$this->p->notice->inf( $notice_msg, null, $notice_key, $dismiss_time = true );
				}
			}

			return $opts;
		}

		public function save_site_options() {

			$default_page     = key( $this->p->cf[ '*' ][ 'lib' ][ 'sitesubmenu' ] );
			$default_page_url = $this->p->util->get_admin_url( $default_page );
			$setting_page     = SucomUtil::get_request_value( 'page', 'POST', $default_page );
			$setting_page_url = $this->p->util->get_admin_url( $setting_page );

			if ( empty( $_POST[ WPSSO_NONCE_NAME ] ) ) {	// WPSSO_NONCE_NAME is an md5() string.

				if ( $this->p->debug->enabled ) {

					$this->p->debug->log( 'nonce token validation post field missing' );
				}

				wp_redirect( $default_page_url );	// Do not trust the 'page' request value.

				exit;

			} elseif ( ! wp_verify_nonce( $_POST[ WPSSO_NONCE_NAME ], WpssoAdmin::get_nonce_action() ) ) {

				$this->p->notice->err( __( 'Nonce token validation failed for network options (update ignored).', 'wpsso' ) );

				wp_redirect( $default_page_url );	// Do not trust the 'page' request value.

				exit;

			} elseif ( ! current_user_can( 'manage_network_options' ) ) {

				$this->p->notice->err( __( 'Insufficient privileges to modify network options.', 'wpsso' ) );

				wp_redirect( $setting_page_url );

				exit;
			}

			/**
			 * Clear any old notices for the current user before sanitation checks.
			 */
			$this->p->notice->clear();

			$def_opts = $this->p->opt->get_site_defaults();

			$opts = SucomUtil::restore_checkboxes( $_POST[ WPSSO_SITE_OPTIONS_NAME ] );
			$opts = array_merge( $this->p->site_options, $opts );
			$opts = $this->p->opt->sanitize( $opts, $def_opts, $network = true );
			$opts = apply_filters( 'wpsso_save_setting_options', $opts, $network = true, $upgrading = false );

			update_site_option( WPSSO_SITE_OPTIONS_NAME, $opts );

			$this->p->notice->upd( '<strong>' . __( 'Network plugin settings have been saved.', 'wpsso' ) . '</strong>' );

			$setting_page_url = add_query_arg( 'settings-updated', 'true', $setting_page_url );

			wp_redirect( $setting_page_url );

			exit;	// Stop after redirect.
		}

		public function load_setting_page() {

			$user_id      = get_current_user_id();
			$action_query = 'wpsso-action';
			$action_value = SucomUtil::get_request_value( $action_query ) ;		// POST or GET with sanitize_text_field().
			$action_value = SucomUtil::sanitize_hookname( $action_value );
			$nonce_value  = SucomUtil::get_request_value( WPSSO_NONCE_NAME ) ;	// POST or GET with sanitize_text_field().

			$_SERVER[ 'REQUEST_URI' ] = remove_query_arg( array( $action_query, WPSSO_NONCE_NAME ) );

			wp_enqueue_script( 'postbox' );

			if ( ! empty( $action_value ) ) {

				if ( empty( $nonce_value ) ) {	// WPSSO_NONCE_NAME is an md5() string.

					if ( $this->p->debug->enabled ) {

						$this->p->debug->log( 'nonce token validation field missing' );
					}

				} elseif ( ! wp_verify_nonce( $nonce_value, WpssoAdmin::get_nonce_action() ) ) {

					$notice_msg = sprintf( __( 'Nonce token validation failed for %1$s action "%2$s".', 'wpsso' ), 'admin', $action_value );

					$this->p->notice->err( $notice_msg, $user_id );

				} else {

					switch ( $action_value ) {

						case 'clear_cache':

							$this->p->util->cache->schedule_clear( $user_id, $clear_other = true );

							$notice_msg = __( 'A background task will begin shortly to clear all caches.', 'wpsso' );

							$this->p->notice->upd( $notice_msg, $user_id );

							break;

						case 'clear_cache_and_short_urls':

							$this->p->util->cache->schedule_clear( $user_id, $clear_other = true, $clear_short = true );

							$notice_msg = __( 'A background task will begin shortly to clear all caches and short URLs.', 'wpsso' );

							$this->p->notice->upd( $notice_msg, $user_id );

							break;

						case 'clear_cache_dir':

							$cleared_count = $this->p->util->cache->clear_cache_dir();

							$notice_msg = sprintf( __( '%s cache files have been deleted.', 'wpsso' ), $cleared_count );

							$this->p->notice->upd( $notice_msg, $user_id );

							break;

						case 'clear_db_transients':

							$cleared_count = $this->p->util->cache->clear_db_transients( $clear_short = true, $transient_prefix = '' );

							$notice_msg = sprintf( __( '%s database transients have been deleted.', 'wpsso' ), $cleared_count );

							$this->p->notice->upd( $notice_msg, $user_id );

							break;

						case 'refresh_cache':

							$this->p->util->cache->schedule_refresh( $user_id, $read_cache = false );

							$notice_msg = __( 'A background task will begin shortly to refresh the post, term and user transient cache objects.',
								'wpsso' );

							$this->p->notice->upd( $notice_msg, $user_id );

							break;

						case 'add_persons':

							$this->p->user->schedule_add_person_role();

							$notice_msg = sprintf( __( 'A background task will begin shortly to add the %s role to content creators.',
								'wpsso' ), _x( 'Person', 'user role', 'wpsso' ) );

							$this->p->notice->upd( $notice_msg, $user_id );

							break;

						case 'remove_persons':

							$this->p->user->schedule_remove_person_role();

							$notice_msg = sprintf( __( 'A background task will begin shortly to remove the %s role from all users.',
								'wpsso' ), _x( 'Person', 'user role', 'wpsso' ) );

							$this->p->notice->upd( $notice_msg, $user_id );

							break;

						case 'reset_user_metabox_layout':

							WpssoUser::delete_metabox_prefs( $user_id );

							$user_obj = get_userdata( $user_id );

							$user_name = $user_obj->display_name;

							$notice_msg = sprintf( __( 'Metabox layout preferences for user ID #%d "%s" have been reset.', 'wpsso' ),
								$user_id, $user_name );

							$this->p->notice->upd( $notice_msg, $user_id );

							break;

						case 'reset_user_dismissed_notices':

							$this->p->notice->reset_dismissed( $user_id );

							$user_obj = get_userdata( $user_id );

							$user_name = $user_obj->display_name;

							$notice_msg = sprintf( __( 'Dismissed notices for user ID #%d "%s" have been reset.', 'wpsso' ),
								$user_id, $user_name );

							$this->p->notice->upd( $notice_msg, $user_id );

							break;

						case 'change_show_options':

							$_SERVER[ 'REQUEST_URI' ] = remove_query_arg( array( 'show-opts' ) );

							$show_opts_key = isset( $_GET[ 'show-opts' ] ) ? SucomUtil::sanitize_key( $_GET[ 'show-opts' ] ) : null;

							if ( isset( $this->p->cf[ 'form' ][ 'show_options' ][ $show_opts_key ] ) ) {

								$show_name_transl  = _x( $this->p->cf[ 'form' ][ 'show_options' ][ $show_opts_key ], 'option value', 'wpsso' );

								WpssoUser::save_pref( array( 'show_opts' => $show_opts_key ) );

								$notice_msg = sprintf( __( 'Option preference saved &mdash; viewing "%s" by default.', 'wpsso' ),
									$show_name_transl );

								$this->p->notice->upd( $notice_msg, $user_id );
							}

							break;

						case 'modify_tmpl_head_attributes':

							$this->modify_tmpl_head_attributes();

							break;

						case 'reload_default_image_sizes':

							$opts     =& $this->p->options;	// Update the existing options array.
							$def_opts = $this->p->opt->get_defaults();
							$img_opts = SucomUtil::preg_grep_keys( '/_img_(width|height|crop|crop_x|crop_y)$/', $def_opts );
							$opts     = array_merge( $this->p->options, $img_opts );

							$this->p->opt->save_options( WPSSO_OPTIONS_NAME, $opts, $network = false );

							$this->p->notice->upd( __( 'Image size settings have been reloaded with their default values and saved.',
								'wpsso' ) );

							break;

						case 'export_plugin_settings_json':

							$this->export_plugin_settings_json();

							break;

						case 'import_plugin_settings_json':

							$this->import_plugin_settings_json();

							break;

						default:

							do_action( 'wpsso_load_setting_page_' . $action_value,
								$this->pagehook, $this->menu_id, $this->menu_name, $this->menu_lib );

							break;
					}
				}
			}

			$menu_ext = $this->menu_ext;	// Lowercase acronyn for plugin or add-on.

			if ( empty( $menu_ext ) ) {

				$menu_ext = $this->p->id;
			}

			$this->get_form_object( $menu_ext );

			$this->add_plugin_hooks();	// Add settings page filter and action hooks.

			$this->add_meta_boxes();	// Add last to move any duplicate side metaboxes.

			$this->add_footer_hooks();	// Include add-on name and version in settings page footer.
		}

		/**
		 * Add settings page filter and action hooks.
		 *
		 * This method is extended by each submenu page.
		 */
		protected function add_plugin_hooks() {
		}

		/**
		 * This method is extended by each submenu page.
		 */
		protected function add_meta_boxes() {
		}

		/**
		 * Include add-on name and version in settings page footer.
		 */
		protected function add_footer_hooks() {

			add_filter( 'admin_footer_text', array( $this, 'admin_footer_ext' ) );

			add_filter( 'update_footer', array( $this, 'admin_footer_host' ) );
		}

		/**
		 * This method is extended by each submenu page.
		 */
		protected function get_table_rows( $metabox_id, $tab_key ) {
		}

		/**
		 * Called from the add_meta_boxes() method in specific settings pages (essential, general, etc.).
		 */
		protected function maybe_show_language_notice() {

			$current_locale = SucomUtil::get_locale( 'current' );
			$default_locale = SucomUtil::get_locale( 'default' );

			if ( $current_locale && $default_locale && $current_locale !== $default_locale ) {

				$notice_msg = sprintf( __( 'Please note that your current language is different from the default site language (%s).', 'wpsso' ), $default_locale ) . ' ';

				$notice_msg .= sprintf( __( 'Localized option values (%s) are used for webpages and content in that language only (not for the default language, or any other language).', 'wpsso' ), $current_locale );

				$notice_key = $this->menu_id . '-language-notice-current-' . $current_locale . '-default-' . $default_locale;

				$this->p->notice->inf( $notice_msg, null, $notice_key, $dismiss_time = true );
			}
		}

		public function show_setting_page() {

			if ( ! $this->is_settings() ) {	// Default check is for $this->menu_id.

				settings_errors( WPSSO_OPTIONS_NAME );
			}

			$pkg_info        = $this->get_pkg_info();	// Returns an array from cache.
			$side_info_boxes = $this->get_side_info_boxes();
			$dashicon_html   = $this->get_menu_dashicon_html( $this->menu_id );

			/**
			 * Settings page wrapper.
			 */
			echo '<div id="' . $this->pagehook . '" class="wrap">' . "\n";

			/**
			 * Settings page header.
			 */
			echo '<div id="wpsso-setting-page-header">' . "\n";
			echo '<h1>';
			echo $dashicon_html . ' ';
			echo $this->menu_name;
			echo ' <span class="qualifier">(';
			echo $pkg_info[ $this->menu_ext ][ 'short_dist' ];
			echo ')</span></h1>' . "\n";
			echo '</div><!-- #wpsso-setting-page-header -->' . "\n";

			/**
			 * Settings page content.
			 */
			echo '<div id="wpsso-setting-page-content" class="' . ( empty( $side_info_boxes ) ? 'no' : 'has' ) . '-side-info-column">' . "\n";

			/**
			 * Metaboxes.
			 */
			echo '<div id="poststuff" class="metabox-holder no-right-sidebar">' . "\n";
			echo '<div id="post-body" class="no-sidebar">' . "\n";
			echo '<div id="post-body-content" class="no-sidebar-content">' . "\n";

			$this->show_post_body_setting_form();

			echo '</div><!-- #post-body-content -->' . "\n";
			echo '</div><!-- #post-body -->' . "\n";
			echo '</div><!-- #poststuff -->' . "\n";

			/**
			 * Information boxes.
			 */
			if ( ! empty( $side_info_boxes ) ) {

				echo '<div id="side-info-column">' . "\n";

				foreach ( $side_info_boxes as $info_box ) {

					echo '<table class="sucom-settings wpsso side-info-box">' . "\n";
					echo '<tr><td>' . "\n";
					echo $info_box;
					echo '</td></tr>' . "\n";
					echo '</table><!-- .side-info-box -->' . "\n";
				}

				echo '</div><!-- #side-info-column -->' . "\n";
			}

			echo '</div><!-- #wpsso-setting-page-content -->' . "\n";
			echo '</div><!-- #' . $this->pagehook .' -->' . "\n";

			?>
			<script type="text/javascript">

				jQuery( document ).ready(

					function( $ ) {

						/* Close postboxes that should be closed. */
						$( '.if-js-closed' ).removeClass( 'if-js-closed' ).addClass( 'closed' );

						/* Postboxes setup. */
						postboxes.add_postbox_toggles( '<?php echo $this->pagehook; ?>' );
					}
				);

			</script>
			<?php
		}

		public function profile_updated_redirect( $url, $status ) {

			if ( false !== strpos( $url, 'updated=' ) && strpos( $url, 'wp_http_referer=' ) ) {

				/**
				 * Match WordPress behavior (users page for admins, profile page for everyone else).
				 */
				$menu_lib      = current_user_can( 'list_users' ) ? 'users' : 'profile';
				$parent_slug   = $this->p->cf[ 'wp' ][ 'admin' ][ $menu_lib ][ 'page' ];
				$referer_match = '/' . $parent_slug . '?page=wpsso-';

				parse_str( parse_url( $url, PHP_URL_QUERY ), $parts );

				if ( strpos( $parts[ 'wp_http_referer' ], $referer_match ) ) {

					// translators: Please ignore - translation uses a different text domain.
					$this->p->notice->upd( __( 'Profile updated.' ) );

					$url = add_query_arg( 'updated', true, $parts[ 'wp_http_referer' ] );
				}
			}

			return $url;
		}

		protected function show_post_body_setting_form() {

			$menu_hookname = SucomUtil::sanitize_hookname( $this->menu_id );

			$form_css_id = 'wpsso_setting_form_' . $menu_hookname;

			switch ( $this->menu_lib ) {

				case 'profile':

					$user_id = get_current_user_id();

					$user_obj = get_user_to_edit( $user_id );

					$admin_color = get_user_option( 'admin_color', $user_id );	// Note that $user_id is the second argument.

					if ( empty( $admin_color ) ) {

						$admin_color = 'fresh';
					}

					/**
					 * Match WordPress behavior (users page for admins, profile page for everyone else).
					 */
					$referer_admin_url = current_user_can( 'list_users' ) ?
						$this->p->util->get_admin_url( $this->menu_id, null, 'users' ) :
						$this->p->util->get_admin_url( $this->menu_id, null, $this->menu_lib );

					/**
					 * Call sucomDisableUnchanged() on submit to include disabled options and exclude unchanged
					 * options from the $_POST.
					 */
					echo '<form name="wpsso" id="' . $form_css_id . '"' .
						' action="user-edit.php" method="post"' .
						' onSubmit="sucomDisableUnchanged( \'#' . $form_css_id . '\' );">' . "\n";
					echo '<input type="hidden" name="wp_http_referer" value="' . $referer_admin_url . '" />' . "\n";
					echo '<input type="hidden" name="action" value="update" />' . "\n";
					echo '<input type="hidden" name="user_id" value="' . $user_id . '" />' . "\n";
					echo '<input type="hidden" name="nickname" value="' . $user_obj->nickname . '" />' . "\n";
					echo '<input type="hidden" name="email" value="' . $user_obj->user_email . '" />' . "\n";
					echo '<input type="hidden" name="admin_color" value="' . $admin_color . '" />' . "\n";
					echo '<input type="hidden" name="rich_editing" value="' . $user_obj->rich_editing . '" />' . "\n";
					echo '<input type="hidden" name="comment_shortcuts" value="' . $user_obj->comment_shortcuts . '" />' . "\n";
					echo '<input type="hidden" name="admin_bar_front" value="' . _get_admin_bar_pref( 'front', $user_id ) . '" />' . "\n";

					wp_nonce_field( 'update-user_' . $user_id );

					break;

				case 'dashboard':
				case 'plugins':
				case 'settings':
				case 'submenu':
				case 'tools':

					/**
					 * Call sucomDisableUnchanged() on submit to include disabled options and exclude unchanged
					 * options from the $_POST.
					 */
					echo '<form name="wpsso" id="' . $form_css_id . '"' .
						' action="options.php" method="post"' .
						' onSubmit="sucomDisableUnchanged( \'#' . $form_css_id . '\' );">' . "\n";

					settings_fields( 'wpsso_setting' );

					break;

				case 'sitesubmenu':

					/**
					 * Call sucomDisableUnchanged() on submit to include disabled options and exclude unchanged
					 * options from the $_POST.
					 */
					echo '<form name="wpsso" id="' . $form_css_id . '"' .
						' action="edit.php?action=' . WPSSO_SITE_OPTIONS_NAME . '" method="post"' .
						' onSubmit="sucomDisableUnchanged( \'#' . $form_css_id . '\' );">' . "\n";

					echo '<input type="hidden" name="page" value="' . $this->menu_id . '" />' . "\n";

					break;

				default:

					return;
			}

			echo "\n" . '<!-- wpsso nonce fields -->' . "\n";

			wp_nonce_field( WpssoAdmin::get_nonce_action(), WPSSO_NONCE_NAME );

			echo "\n";

			wp_nonce_field( 'closedpostboxes', 'closedpostboxesnonce', false );

			echo "\n";

			wp_nonce_field( 'meta-box-order', 'meta-box-order-nonce', false );

			echo "\n";

			do_meta_boxes( $this->pagehook, $context = 'normal', $object = null );

			$action_name = 'wpsso_form_content_metaboxes_' . $menu_hookname;

			do_action( $action_name, $this->pagehook );

			if ( $this->menu_lib === 'profile' ) {

				$submit_label_transl = _x( 'Save All Profile Settings', 'submit button', 'wpsso' );

			} else {

				$submit_label_transl = _x( 'Save All Plugin Settings', 'submit button', 'wpsso' );
			}

			echo $this->get_form_buttons( $submit_label_transl );

			echo '</form>', "\n";
		}

		protected function get_side_info_boxes() {

			static $local_cache = null;

			if ( null !== $local_cache ) {

				return $local_cache;
			}

			$pkg_info    = $this->get_pkg_info();	// Returns an array from cache.
			$local_cache = array();

			foreach ( $this->p->cf[ 'plugin' ] as $ext => $info ) {

				if ( empty( $info[ 'version' ] ) ) {	// Filter out add-ons that are not installed.

					continue;

				} elseif ( empty( $info[ 'url' ][ 'purchase' ] ) ) {

					continue;

				} elseif ( $pkg_info[ $ext ][ 'pp' ] ) {

					continue;
				}

				$info_box = '<div class="side-info-header">' . "\n";
				$info_box .= '<h2>' . __( 'Upgrade to Premium', 'wpsso' ) . '</h2>' . "\n";
				$info_box .= '</div><!-- .side-info-header -->' . "\n";

				$info_box .= '<div class="side-info-icon">' . "\n";
				$info_box .= $this->get_ext_img_icon( $ext ) . "\n";
				$info_box .= '</div><!-- .side-info-icon -->' . "\n";

				$info_box .= '<div class="side-info-content has-buttons">' . "\n";
				$info_box .= $this->p->msgs->get( 'column-purchase-' . $ext, $info ) . "\n";
				$info_box .= '</div><!-- .side-info-content -->' . "\n";

				$info_box .= '<div class="side-info-buttons">' . "\n";
				$info_box .= $this->form->get_button( sprintf( _x( 'Get %s', 'submit button', 'wpsso' ), $pkg_info[ $ext ][ 'short_pro' ] ),
					'button-secondary', 'column-purchase', $info[ 'url' ][ 'purchase' ], true ) . "\n";
				$info_box .= '</div><!-- .side-info-buttons -->' . "\n";

				$local_cache[] = $info_box;
			}

			return $local_cache;
		}

		protected function get_form_buttons( $submit_label_transl = '' ) {

			if ( empty( $submit_label_transl ) ) {	// Just in case.

				$submit_label_transl = _x( 'Save All Plugin Settings', 'submit button', 'wpsso' );
			}

			$change_show_next_key     = SucomUtil::next_key( WpssoUser::show_opts(), $this->p->cf[ 'form' ][ 'show_options' ] );
			$change_show_name_transl  = _x( $this->p->cf[ 'form' ][ 'show_options' ][ $change_show_next_key ], 'option value', 'wpsso' );
			$change_show_label_transl = sprintf( _x( 'Change to "%s" View', 'submit button', 'wpsso' ), $change_show_name_transl );

			/**
			 * A default two dimentional array of button rows for settings pages.
			 *
			 * The 'submit' button will be assigned a class of 'button-primary', while all other 1st row buttons will
			 * be 'button-secondary button-highlight'. The 2nd+ row buttons will be assigned a class of
			 * 'button-secondary'.
			 */
			$form_button_rows = array(
				array(
					'submit' => $submit_label_transl,
					'change_show_options&show-opts=' . $change_show_next_key => $change_show_label_transl,
				),
			);

			$form_button_rows = apply_filters( 'wpsso_form_button_rows', $form_button_rows,
				$this->menu_id, $this->menu_name, $this->menu_lib, $this->menu_ext );

			$row_num = 0;

			$buttons_html = '';

			foreach ( $form_button_rows as $key => $buttons_row ) {

				if ( $row_num >= 2 ) {

					$css_class = 'button-secondary';			// Third+ row.

				} elseif ( $row_num >= 1 ) {

					$css_class = 'button-secondary button-alt';		// Second row.

				} else {

					$css_class = 'button-secondary button-highlight';	// First row.
				}

				$buttons_html .= '<div class="submit-buttons">';

				foreach ( $buttons_row as $action_value => $mixed ) {

					if ( empty( $action_value ) || empty( $mixed ) ) {	// Just in case.

						continue;
					}

					if ( is_string( $mixed ) ) {

						if ( $action_value === 'submit' ) {

							$buttons_html .= $this->form->get_submit( $mixed, 'button-primary' );

						} else {

							$action_url = $this->p->util->get_admin_url( '?wpsso-action=' . $action_value );

							$button_url = wp_nonce_url( $action_url, WpssoAdmin::get_nonce_action(), WPSSO_NONCE_NAME );

							$buttons_html .= $this->form->get_button( $mixed, $css_class, '', $button_url );
						}

					} elseif ( is_array( $mixed ) ) {

						if ( ! empty( $mixed[ 'html' ] ) ) {

							$buttons_html .= $mixed[ 'html' ];
						}
					}
				}

				$buttons_html .= '</div>';

				$row_num++;
			}

			return $buttons_html;
		}

		public function show_metabox_table( $obj, $mb ) {

			if ( isset( $mb[ 'args' ][ 'page_id' ] ) && isset( $mb[ 'args' ][ 'metabox_id' ] )  ) {

				$page_id     = $mb[ 'args' ][ 'page_id' ];
				$metabox_id  = $mb[ 'args' ][ 'metabox_id' ];
				$filter_name = 'wpsso_' . $page_id . '_' . $metabox_id . '_rows';

				$table_rows = array_merge(
					$this->get_table_rows( $page_id, $metabox_id ),
					(array) apply_filters( $filter_name, array(), $this->form, $network = false )
				);

				$this->p->util->metabox->do_table( $table_rows, 'metabox-' . $page_id . '-' . $metabox_id );

			} else {

				$table_rows = array(
					'<td><p class="status-msg">' .
					__( 'Missing page ID or metabox ID to create the metabox table.', 'wpsso' ) .
					'</p></td>'
				);

				$this->p->util->metabox->do_table( $table_rows );
			}
		}

		public function show_metabox_cache_status() {

			$table_cols         = 4;
			$db_transient_keys  = SucomUtilWP::get_db_transient_keys();
			$all_transients_pre = 'wpsso_';
			$have_filtered_exp  = false;

			echo '<table class="sucom-settings wpsso column-metabox cache-status">';

			echo '<tr><td colspan="' . $table_cols . '"><h4>';
			echo sprintf( __( '%s Database Transients', 'wpsso' ), $this->p->cf[ 'plugin' ][ 'wpsso' ][ 'short' ] );
			echo '</h4></td></tr>';

			echo '<tr>';
			echo '<th class="cache-label"></th>';
			echo '<th class="cache-count">' . __( 'Count', 'wpsso' ) . '</th>';
			echo '<th class="cache-size">' . __( 'MB', 'wpsso' ) . '</th>';
			echo '<th class="cache-expiration">' . __( 'Hours', 'wpsso' ) . '</th>';
			echo '</tr>';

			/**
			 * The $this->p->cf is filtered and may have been modified by add-ons, so make sure the "All Transients"
			 * count is last.
			 */
			if ( isset( $this->p->cf[ 'wp' ][ 'transient' ][ $all_transients_pre ] ) ) {

				SucomUtil::move_to_end( $this->p->cf[ 'wp' ][ 'transient' ], $all_transients_pre );
			}

			foreach ( $this->p->cf[ 'wp' ][ 'transient' ] as $cache_md5_pre => $cache_info ) {

				if ( empty( $cache_info ) ) {

					continue;

				} elseif ( empty( $cache_info[ 'label' ] ) ) {	// Skip cache info without labels.

					continue;
				}

				$cache_text_dom     = empty( $cache_info[ 'text_domain' ] ) ? $this->p->id : $cache_info[ 'text_domain' ];
				$cache_label_transl = _x( $cache_info[ 'label' ], 'option label', $cache_text_dom );
				$cache_count        = count( preg_grep( '/^' . $cache_md5_pre . '/', $db_transient_keys ) );
				$cache_size         = SucomUtilWP::get_db_transient_size_mb( $decimals = 1, $dec_point = '.', $thousands_sep = '', $cache_md5_pre );
				$cache_opt_key      = isset( $cache_info[ 'opt_key' ] ) ? $cache_info[ 'opt_key' ] : false;
				$cache_exp_secs     = $cache_opt_key && isset( $this->p->options[ $cache_opt_key ] ) ? $this->p->options[ $cache_opt_key ] : 0;
				$cache_exp_suffix   = '';

				if ( ! empty( $cache_info[ 'filter' ] ) ) {

					$filter_name     = $cache_info[ 'filter' ];
					$filter_exp_secs = (int) apply_filters( $filter_name, $cache_exp_secs );

					if ( $cache_exp_secs !== $filter_exp_secs ) {

						$cache_exp_secs    = $filter_exp_secs;
						$cache_exp_suffix  = ' *';	// Show that value was changed by a filter.
						$have_filtered_exp = true;
					}
				}

				if ( is_numeric( $cache_exp_secs ) && $cache_exp_secs > 0 ) {

					$cache_exp_hours = number_format( $cache_exp_secs / 60 / 60, $decimals = 1, $dec_point = '.', $thousands_sep = '' );

				} else {

					$cache_exp_hours = $cache_exp_secs;
				}

				echo '<tr>';
				echo '<th class="cache-label">' . $cache_label_transl . ':</th>';
				echo '<td class="cache-count">' . $cache_count . '</td>';
				echo '<td class="cache-size">' . $cache_size . '</td>';

				if ( $cache_md5_pre !== $all_transients_pre ) {

					echo '<td class="cache-expiration">' . $cache_exp_hours . $cache_exp_suffix . '</td>';
				}

				echo '</tr>' . "\n";
			}

			do_action( 'wpsso_column_metabox_cache_status_table_rows', $table_cols, $this->form, $db_transient_keys );

			if ( $have_filtered_exp ) {

				echo '<tr><td></td></tr>' . "\n";
				echo '<tr><td colspan="' . $table_cols . '"><p class="status-msg smaller left">* ' .
					__( 'Cache expiration modified by filter.', 'wpsso' ) . '</small></p></td></tr>' . "\n";
			}

			echo '</table>';
		}

		public function show_metabox_version_info() {

			$table_cols = 2;

			$label_width = '30%';

			echo '<table class="sucom-settings wpsso column-metabox version-info" style="table-layout:fixed;">';

			/**
			 * Required for chrome to display a fixed table layout.
			 */
			echo '<colgroup>';
			echo '<col style="width:' . $label_width . ';"/>';
			echo '<col/>';
			echo '</colgroup>';

			foreach ( $this->p->cf[ 'plugin' ] as $ext => $info ) {

				if ( empty( $info[ 'version' ] ) ) {	// Filter out add-ons that are not installed.

					continue;
				}

				$plugin_version = isset( $info[ 'version' ] ) ? $info[ 'version' ] : ''; // Static value from config.
				$stable_version = __( 'Not Available', 'wpsso' ); // Default value.
				$latest_version = __( 'Not Available', 'wpsso' ); // Default value.
				$latest_notice  = '';
				$changelog_url  = isset( $info[ 'url' ][ 'changelog' ] ) ? $info[ 'url' ][ 'changelog' ] : '';
				$readme_info    = $this->get_readme_info( $ext, $read_cache = true );
				$td_style_attr  = '';

				if ( ! empty( $readme_info[ 'stable_tag' ] ) ) {

					$stable_version = $readme_info[ 'stable_tag' ];
					$is_newer_avail = version_compare( $plugin_version, $stable_version, '<' );

					if ( is_array( $readme_info[ 'upgrade_notice' ] ) ) {

						/**
						 * Hooked by the update manager to apply the version filter.
						 */
						$upgrade_notice = apply_filters( 'wpsso_readme_upgrade_notices', $readme_info[ 'upgrade_notice' ], $ext );

						if ( ! empty( $upgrade_notice ) ) {

							reset( $upgrade_notice );

							$latest_version = key( $upgrade_notice );
							$latest_notice  = $upgrade_notice[ $latest_version ];
						}
					}

					/**
					 * Hooked by the update manager to check installed version against the latest version, if a
					 * non-stable filter is selected for that plugin / add-on.
					 */
					if ( apply_filters( 'wpsso_newer_version_available', $is_newer_avail, $ext, $plugin_version, $stable_version, $latest_version ) ) {

						$td_style_attr = 'style="background-color:#f00;"';	// Red background.

					} elseif ( preg_match( '/[a-z]/', $plugin_version ) ) {	// Current but not stable (alpha chars in version).

						$td_style_attr = 'style="background-color:#ff0;"';	// Yellow background.

					} else {

						$td_style_attr = 'style="background-color:#0f0;"';	// Green background.
					}
				}

				echo '<tr><td colspan="' . $table_cols . '"><h4>' . $info[ 'name' ] . '</h4></td></tr>';

				echo '<tr><th class="version-label">' . _x( 'Installed', 'option label', 'wpsso' ) . ':</th>
					<td class="version-number" ' . $td_style_attr . '>' . $plugin_version . '</td></tr>';

				/**
				 * Only show the stable version if the latest version is different (ie. latest is a non-stable version).
				 */
				if ( $stable_version !== $latest_version ) {

					echo '<tr><th class="version-label">' . _x( 'Stable', 'option label', 'wpsso' ) . ':</th>
						<td class="version-number">' . $stable_version . '</td></tr>';
				}

				echo '<tr><th class="version-label">' . _x( 'Latest', 'option label', 'wpsso' ) . ':</th>
					<td class="version-number">' . $latest_version . '</td></tr>';

				/**
				 * Only show the latest version notice message if there's a newer / non-matching version.
				 */
				if ( $plugin_version !== $stable_version || $plugin_version !== $latest_version ) {

					echo '<tr><td colspan="' . $table_cols . '" class="latest-notice">';

					if ( ! empty( $latest_notice ) ) {

						echo '<p><em><strong>Version ' . $latest_version . '</strong> ' . $latest_notice . '</em></p>';
					}

					echo '<p><a href="' . $changelog_url . '">' . sprintf( __( 'View %s changelog...', 'wpsso'),
						$info[ 'short' ] ) . '</a></p>';

					echo '</td></tr>';
				}
			}

			do_action( 'wpsso_column_metabox_version_info_table_rows', $table_cols, $this->form );

			echo '</table>';
		}

		public function show_metabox_status_pro() {

			$pkg_info = $this->get_pkg_info();	// Returns an array from cache.

			echo '<table class="sucom-settings wpsso column-metabox feature-status">';

			$ext_num = 0;

			foreach ( $this->p->cf[ 'plugin' ] as $ext => $info ) {

				$features = array();

				if ( isset( $info[ 'lib' ][ 'pro' ] ) ) {

					foreach ( $info[ 'lib' ][ 'pro' ] as $sub => $libs ) {

						if ( 'admin' === $sub ) {	// Skip status for admin menus and tabs.

							continue;
						}

						foreach ( $libs as $id => $label ) {

							$td_class   = $pkg_info[ $ext ][ 'pp' ] ? '' : 'blank';
							$classname  = SucomUtil::sanitize_classname( $ext . 'pro' . $sub . $id, $allow_underscore = false );
							$status_off = empty( $this->p->avail[ $sub ][ $id ] ) ? 'off' : 'rec';
							$status_on  = $pkg_info[ $ext ][ 'pp' ] ? 'on' : $status_off;

							$features[ $label ] = array(
								'sub'          => $sub,
								'lib'          => $id,
								'td_class'     => $td_class,
								'label_transl' => _x( $label, 'lib file description', $info[ 'text_domain' ] ),
								'status'       => class_exists( $classname ) ? $status_on : $status_off,
							);
						}
					}
				}

				$features = apply_filters( $ext . '_status_pro_features', $features, $ext, $info, $pkg_info[ $ext ] );

				if ( ! empty( $features ) ) {

					$ext_num++;

					echo '<tr><td colspan="3">';
					echo '<h4' . ( $ext_num > 1 ? ' style="margin-top:10px;"' : '' ) . '>';
					echo _x( $info[ 'name' ], 'plugin name', 'wpsso' );
					echo '</h4></td></tr>';

					$this->show_features_status( $ext, $info, $features );
				}
			}

			echo '</table>';
		}

		public function show_metabox_status_std() {

			$pkg_info = $this->get_pkg_info();	// Returns an array from cache.

			echo '<table class="sucom-settings wpsso column-metabox feature-status">';

			$ext_num = 0;

			foreach ( $this->p->cf[ 'plugin' ] as $ext => $info ) {

				$features = apply_filters( $ext . '_status_std_features', array(), $ext, $info, $pkg_info[ $ext ] );

				if ( ! empty( $features ) ) {

					$ext_num++;

					echo '<tr><td colspan="3">';
					echo '<h4' . ( $ext_num > 1 ? ' style="margin-top:10px;"' : '' ) . '>';
					echo _x( $info[ 'name' ], 'plugin name', 'wpsso' );
					echo '</h4></td></tr>';

					$this->show_features_status( $ext, $info, $features );
				}
			}

			echo '</table>';
		}

		public function show_metabox_help_support() {

			$pkg_info = $this->get_pkg_info();	// Returns an array from cache.

			echo '<table class="sucom-settings wpsso column-metabox"><tr><td>';

			foreach ( $this->p->cf[ 'plugin' ] as $ext => $info ) {

				if ( empty( $info[ 'version' ] ) ) {	// Filter out add-ons that are not installed.

					continue;
				}

				$action_links = array();

				if ( ! empty( $info[ 'url' ][ 'faqs' ] ) ) {

					$action_links[] = sprintf( __( '<a href="%s">Frequently Asked Questions</a>', 'wpsso' ), $info[ 'url' ][ 'faqs' ] );
				}

				if ( ! empty( $info[ 'url' ][ 'notes' ] ) ) {

					$action_links[] = sprintf( __( '<a href="%s">Notes and Documentation</a>', 'wpsso' ), $info[ 'url' ][ 'notes' ] );
				}

				if ( ! empty( $info[ 'url' ][ 'support' ] ) && $pkg_info[ $ext ][ 'pp' ] ) {

					$action_links[] = sprintf( __( '<a href="%s">Priority Support Ticket</a>', 'wpsso' ), $info[ 'url' ][ 'support' ] );

				} elseif ( ! empty( $info[ 'url' ][ 'forum' ] ) ) {

					$action_links[] = sprintf( __( '<a href="%s">Community Support Forum</a>', 'wpsso' ), $info[ 'url' ][ 'forum' ] );
				}

				if ( ! empty( $action_links ) ) {

					echo '<h4>' . $info[ 'name' ] . '</h4>' . "\n";

					echo '<ul><li>' . implode( $glue = '</li><li>', $action_links ) . '</li></ul>' . "\n";
				}
			}

			echo '</td></tr></table>';
		}

		public function show_metabox_rate_review() {

			echo '<table class="sucom-settings wpsso column-metabox"><tr><td>';

			echo $this->p->msgs->get( 'column-rate-review' );

			echo '<h4>' . __( 'Active plugins', 'option label', 'wpsso' ) . ':</h4>' . "\n";

			$action_links = array();

			foreach ( $this->p->cf[ 'plugin' ] as $ext => $info ) {

				if ( empty( $info[ 'version' ] ) ) {	// Filter out add-ons that are not installed.

					continue;
				}

				if ( ! empty( $info[ 'url' ][ 'review' ] ) ) {

					$action_links[] = '<a href="' . $info[ 'url' ][ 'review' ] . '">' . $info[ 'name' ] . '</a>';
				}
			}

			if ( ! empty( $action_links ) ) {

				echo '<ul><li>' . implode( $glue = '</li><li>', $action_links ) . '</li></ul>' . "\n";
			}

			echo '</td></tr></table>';
		}

		/**
		 * Always call as WpssoAdmin::get_nonce_action() to have a reliable __METHOD__ value.
		 */
		public static function get_nonce_action() {

			$salt = __FILE__ . __LINE__ . __METHOD__;

			return md5( $salt );
		}

		private function is_settings( $menu_id = false ) {

			return $this->is_lib( 'settings', $menu_id );
		}

		private function is_lib( $lib_name, $menu_id = false ) {

			if ( false === $menu_id ) {

				$menu_id = $this->menu_id;
			}

			return isset( $this->p->cf[ '*' ][ 'lib' ][ $lib_name ][ $menu_id ] ) ? true : false;
		}

		private function show_features_status( &$ext = '', &$info = array(), &$features = array() ) {

			$status_titles = array(
				'disabled'    => __( 'Feature is disabled.', 'wpsso' ),
				'off'         => __( 'Feature is not active.', 'wpsso' ),
				'on'          => __( 'Feature is active.', 'wpsso' ),
				'rec'         => __( 'Feature is recommended but not active.', 'wpsso' ),
				'recommended' => __( 'Feature is recommended but not active.', 'wpsso' ),
			);

			$apis_tab_url = $this->p->util->get_admin_url( 'advanced#sucom-tabset_plugin-tab_apikeys' );

			foreach ( $features as $label => $arr ) {

				if ( ! isset( $arr[ 'label_url' ] ) ) {

					/**
					 * By default, all API related features should have their options located under the
					 * Advanced Settings > Service APIs tab.
					 */
					if ( preg_match( '/ API$/', $label ) ) {

						$arr[ 'label_url' ] = $apis_tab_url;

						$features[ $label ] = $arr;
					}
				}

				if ( ! empty( $arr[ 'label_transl' ] ) ) {

					$label_transl = $arr[ 'label_transl' ];

					unset( $features[ $label ], $arr[ 'label_transl' ] );

					$features[ $label_transl ] = $arr;
				}
			}

			uksort( $features, array( 'self', 'sort_plugin_features' ) );

			foreach ( $features as $label_transl => $arr ) {

				if ( isset( $arr[ 'status' ] ) ) {	// Use provided status before class or constant check.

					$status_key = $arr[ 'status' ];

				} elseif ( isset( $arr[ 'classname' ] ) ) {

					$status_key = class_exists( $arr[ 'classname' ] ) ? 'on' : 'off';

				} elseif ( isset( $arr[ 'constant' ] ) ) {

					$status_key = SucomUtil::get_const( $arr[ 'constant' ] ) ? 'on' : 'off';

				} else {

					$status_key = '';
				}

				if ( ! empty( $status_key ) ) {

					$dashicon_title = '';
					$dashicon_name  = preg_match( '/^\(([a-z\-]+)\) (.*)/', $label_transl, $match ) ? $match[ 1 ] : 'admin-generic';
					$label_transl   = empty( $match[ 2 ] ) ? $label_transl : $match[ 2 ];
					$label_url      = empty( $arr[ 'label_url' ] ) ? '' : $arr[ 'label_url' ];
					$td_class       = empty( $arr[ 'td_class' ] ) ? '' : ' ' . $arr[ 'td_class' ];
					$td_class_is    = ' ' . SucomUtil::sanitize_key( 'module-is-' . $status_key );

					switch ( $dashicon_name ) {

						case 'api':
						case 'update':

							$dashicon_title = __( 'Service API module', 'wpsso' );
							$dashicon_name  = 'update';

							break;

						case 'code':

							$dashicon_title = __( 'HTML tag and markup module', 'wpsso' );
							$dashicon_name  = 'media-code';

							break;

						case 'feature':

							$dashicon_title = __( 'Additional functionality module', 'wpsso' );
							$dashicon_name  = 'pressthis';

							break;

						case 'plugin':

							$dashicon_title = __( 'Plugin integration module', 'wpsso' );
							$dashicon_name  = 'admin-plugins';

							break;

						case 'plus':

							$dashicon_title = __( 'Markup property module', 'wpsso' );
							$dashicon_name  = 'welcome-add-page';

							break;

						case 'sharing':

							$dashicon_title = __( 'Sharing functionality module', 'wpsso' );
							$dashicon_name  = 'share';

							break;
					}

					echo '<tr>';

					echo '<td class="module-icon' . $td_class_is . '">';
					echo '<span class="dashicons-before dashicons-' . $dashicon_name . '" title="' . $dashicon_title . '"></span>';
					echo '</td>';

					echo '<td class="' . trim( 'module-label ' . $td_class . $td_class_is ) . '">';
					echo $label_url ? '<a href="' . $label_url . '">' : '';
					echo $label_transl;
					echo $label_url ? '</a>' : '';
					echo '</td>';

					echo '<td class="module-status' . $td_class_is .'">';
					echo '<div class="status-light" title="';
					echo isset( $status_titles[ $status_key ] ) ? $status_titles[ $status_key ] : '';
					echo '"></div>';
					echo '</td>';

					echo '</tr>' . "\n";
				}
			}
		}

		private static function sort_plugin_features( $feature_a, $feature_b ) {

			return strnatcasecmp( self::feature_priority( $feature_a ), self::feature_priority( $feature_b ) );
		}

		private static function feature_priority( $feature ) {

			if ( strpos( $feature, '(feature)' ) === 0 ) {

				return '(10) ' . $feature;

			} else {

				return $feature;
			}
		}

		public function addons_metabox_content( $network = false ) {

			$ext_sorted = WpssoConfig::get_ext_sorted();

			unset( $ext_sorted[ $this->p->id ] );

			$tabindex  = 0;
			$ext_num   = 0;
			$ext_total = count( $ext_sorted );
			$charset   = get_bloginfo( 'charset' );

			echo '<table class="sucom-settings wpsso addons-metabox" style="padding-bottom:10px">' . "\n";

			foreach ( $ext_sorted as $ext => $info ) {

				$ext_num++;

				$ext_links = $this->get_ext_action_links( $ext, $info, $tabindex );

				$table_rows = array();

				/**
				 * Plugin name, description and links.
				 */
				$plugin_name_html = '<h4>' . htmlentities( _x( $info[ 'name' ], 'plugin name', 'wpsso' ),
					ENT_QUOTES, $charset, $double_encode = false ) . '</h4>';

				$plugin_desc_html = '<p>' . htmlentities( _x( $info[ 'desc' ], 'plugin description', 'wpsso' ),
					ENT_QUOTES, $charset, $double_encode = false ) . '</p>';

				$table_rows[ 'plugin_name' ] = '<td class="ext-info-plugin-name" id="ext-info-plugin-name-' . $ext . '">' .
					$plugin_name_html . $plugin_desc_html . ( empty( $ext_links ) ? '' : '<div class="row-actions visible">' .
						implode( $glue = ' | ', $ext_links ) . '</div>' ) . '</td>';

				/**
				 * Plugin separator.
				 */
				if ( $ext_num < $ext_total ) {

					$table_rows[ 'dotted_line' ] = '<td class="ext-info-plugin-separator"></td>';

				} else {

					$table_rows[] = '<td></td>';
				}

				/**
				 * Show the plugin icon and table rows.
				 */
				foreach ( $table_rows as $key => $row ) {

					echo '<tr>';

					if ( $key === 'plugin_name' ) {

						$span_rows = count( $table_rows );

						echo '<td class="ext-info-plugin-icon" id="ext-info-plugin-icon-' . $ext . '"' .
							' width="168" rowspan="' . $span_rows . '" valign="top" align="left">' . "\n";
						echo '<a class="ext-anchor" id="' . $ext . '"></a>' . "\n";	// Add an anchor for the add-on.
						echo $this->get_ext_img_icon( $ext );
						echo '</td>';
					}

					echo $row . '</tr>' . "\n";
				}
			}

			echo '</table>' . "\n";
		}

		public function licenses_metabox_content( $network = false ) {

			$ext_sorted = WpssoConfig::get_ext_sorted();

			foreach ( $ext_sorted as $ext => $info ) {

				if ( empty( $info[ 'update_auth' ] ) ) {	// Only show plugins with Premium versions.

					unset( $ext_sorted[ $ext ] );
				}
			}

			$tabindex  = 0;
			$ext_num   = 0;
			$ext_total = count( $ext_sorted );
			$charset   = get_bloginfo( 'charset' );

			echo '<table class="sucom-settings wpsso licenses-metabox" style="padding-bottom:10px">' . "\n";
			echo '<tr><td colspan="3">' . $this->p->msgs->get( 'info-plugin-tid' . ( $network ? '-network' : '' ) ) . '</td></tr>' . "\n";

			foreach ( $ext_sorted as $ext => $info ) {

				$ext_num++;

				$ext_links = $this->get_ext_action_links( $ext, $info, $tabindex );

				$table_rows = array();

				/**
				 * Plugin name, description and links
				 */
				$plugin_name_html = '<h4>' . htmlentities( _x( $info[ 'name' ], 'plugin name', 'wpsso' ),
					ENT_QUOTES, $charset, $double_encode = false ) . '</h4>';

				$table_rows[ 'plugin_name' ] = '<td colspan="2" class="ext-info-plugin-name" id="ext-info-plugin-name-' . $ext . '">' .
					$plugin_name_html . ( empty( $ext_links ) ? '' : '<div class="row-actions visible">' .
						implode( $glue = ' | ', $ext_links ) . '</div>' ) . '</td>';

				/**
				 * Plugin authentication ID and license information.
				 */
				$table_rows[ 'plugin_tid' ] = $this->form->get_th_html( sprintf( _x( '%s Authentication ID',
					'option label', 'wpsso' ), $info[ 'short' ] ), 'medium nowrap' );

				$table_rows[ 'plugin_tid' ] .= '<td width="100%">' .
					$this->form->get_input( 'plugin_' . $ext . '_tid', 'tid mono', '', 0,
						'', false, ++$tabindex ) . '</td>';

				if ( $network ) {

					$table_rows[ 'site_use' ] = self::get_option_site_use( 'plugin_' . $ext . '_tid', $this->form, $network, $is_enabled = true );

				} elseif ( ! empty( $this->p->options[ 'plugin_' . $ext . '_tid' ] ) && class_exists( 'SucomUpdate' ) ) {

					$show_update_opts = array(
						'exp_date' => _x( 'Support and Updates Expire', 'option label', 'wpsso' ),
						'qty_used' => _x( 'License Information', 'option label', 'wpsso' ),
					);

					foreach ( $show_update_opts as $key => $label ) {

						$val = SucomUpdate::get_option( $ext, $key );

						if ( empty( $val ) ) {	// Add an empty row for empty values.

							$val = _x( 'Not available', 'option value', 'wpsso' );

						} elseif ( 'exp_date' === $key ) {

							if ( '0000-00-00 00:00:00' === $val ) {

								$val = _x( 'Never', 'option value', 'wpsso' );
							}

						} elseif ( $key === 'qty_used' ) {

							/**
							 * The default 'qty_used' value is a 'n/n' string.
							 */
							$val = sprintf( __( '%s site addresses registered', 'wpsso' ), $val );

							/**
							 * Use a better '# of #' string translation if possible.
							 */
							if ( version_compare( WpssoUmConfig::get_version(), '1.10.1', '>=' ) ) {

								$qty_reg   = SucomUpdate::get_option( $ext, 'qty_reg' );
								$qty_total = SucomUpdate::get_option( $ext, 'qty_total' );

								if ( null !== $qty_reg && null !== $qty_total ) {

									$val = sprintf( __( '%d of %d site addresses registered', 'wpsso' ),
										$qty_reg, $qty_total );
								}
							}

							/**
							 * Add a license information link (thickbox). 
							 */
							if ( ! empty( $info[ 'url' ][ 'info' ] ) ) {

								/**
								 * get_user_locale() is available since WP v4.7.0, so make sure it exists before calling it. :)
								 */
								$locale = is_admin() && function_exists( 'get_user_locale' ) ? get_user_locale() : get_locale();

								$info_url = add_query_arg( array(
									'tid'       => $this->p->options[ 'plugin_' . $ext . '_tid' ],
									'locale'    => $locale,
									'TB_iframe' => 'true',
									'width'     => $this->p->cf[ 'wp' ][ 'tb_iframe' ][ 'width' ],
									'height'    => $this->p->cf[ 'wp' ][ 'tb_iframe' ][ 'height' ],
								), $info[ 'url' ][ 'purchase' ] . 'info/' );

								$val = '<a href="' . $info_url . '" class="thickbox">' . $val . '</a>';
							}
						}

						$table_rows[ $key ] = '<th class="medium nowrap">' . $label . '</th><td width="100%">' . $val . '</td>';
					}

				} else {

					$table_rows[] = '<th class="medium nowrap">&nbsp;</th><td width="100%">&nbsp;</td>';
				}

				/**
				 * Plugin separator.
				 */
				if ( $ext_num < $ext_total ) {

					$table_rows[ 'dotted_line' ] = '<td colspan="2" class="ext-info-plugin-separator"></td>';

				} else {

					$table_rows[] = '<td></td>';
				}

				/**
				 * Show the plugin icon and table rows.
				 */
				foreach ( $table_rows as $key => $row ) {

					echo '<tr>';

					if ( $key === 'plugin_name' ) {

						$span_rows = count( $table_rows );

						echo '<td class="ext-info-plugin-icon" id="ext-info-plugin-icon-' . $ext . '"' .
							' width="168" rowspan="' . $span_rows . '" valign="top" align="left">' . "\n";
						echo $this->get_ext_img_icon( $ext );
						echo '</td>';
					}

					echo $row . '</tr>';
				}
			}

			echo '</table>' . "\n";
		}

		public function add_admin_tb_notices_menu_item( $wp_admin_bar ) {

			if ( ! current_user_can( 'edit_posts' ) ) {

				return;
			}

			$menu_icon = '<span class="ab-icon" id="wpsso-toolbar-notices-icon"></span>';

			$menu_count = '<span class="ab-label" id="wpsso-toolbar-notices-count">0</span>';

			$no_notices_text = sprintf( __( 'Fetching %s notifications...', 'wpsso' ), $this->p->cf[ 'menu' ][ 'title' ] );

			$wp_admin_bar->add_node( array(
				'id'     => 'wpsso-toolbar-notices',
				'title'  => $menu_icon . $menu_count,
				'parent' => false,
				'href'   => false,
				'group'  => false,
				'meta'   => array(),
			) );

			$wp_admin_bar->add_node( array(
				'id'     => 'wpsso-toolbar-notices-container',
				'title'  => $no_notices_text,
				'parent' => 'wpsso-toolbar-notices',
				'href'   => false,
				'group'  => false,
				'meta'   => array(),
			) );
		}

		public function admin_footer_ext( $footer_html ) {

			$pkg_info = $this->get_pkg_info();	// Returns an array from cache.

			$footer_html = '<div class="admin-footer-ext">';

			if ( isset( $pkg_info[ $this->menu_ext ][ 'name_dist' ] ) ) {

				$footer_html .= $pkg_info[ $this->menu_ext ][ 'name_dist' ] . '<br/>';
			}

			if ( isset( $pkg_info[ $this->menu_ext ][ 'gen' ] ) ) {

				$footer_html .= $pkg_info[ $this->menu_ext ][ 'gen' ] . '<br/>';
			}

			$footer_html .= '</div>';

			return $footer_html;
		}

		public function admin_footer_host( $footer_html ) {

			global $wp_version;

			$footer_html = '<div class="admin-footer-host">';

			$home_url = strtolower( SucomUtilWP::raw_get_home_url() );

			$host_name = preg_replace( '/^[^:]*:\/\//', '', $home_url );

			$footer_html .= $host_name . '<br/>';

			$footer_html .= 'WordPress ' . $wp_version . '<br/>';

			$footer_html .= 'PHP ' . phpversion() . '<br/>';

			$footer_html .= '</div>';

			return $footer_html;
		}

		/**
		 * Called by the 'activated_plugin', 'after_switch_theme', and 'upgrader_process_complete' actions, along with the
		 * WpssoRegister->deactivate_plugin() method.
		 */
		public static function reset_admin_check_options() {

			delete_option( WPSSO_POST_CHECK_COUNT_NAME );

			delete_option( WPSSO_TMPL_HEAD_CHECK_NAME );

			delete_option( WPSSO_WP_CONFIG_CHECK_NAME );
		}

		/**
		 * Since WPSSO Core v8.5.1.
		 *
		 * Called when the WordPress Settings > Site Address URL or the WP_HOME constant value is changed.
		 */
		public function site_address_changed( $old_value, $new_value, $option = 'home' ) {

			static $do_once = null;

			if ( true === $do_once ) {

				return;	// Stop here.
			}

			$do_once = true;

			if ( $this->p->debug->enabled ) {

				$this->p->debug->mark();
			}

			if ( $old_value === $new_value ) {	// Nothing to do.

				return;	// Stop here.
			}

			$user_id = get_current_user_id();

			if ( ! $user_id ) {	// Nobody there.

				return;	// Stop here.
			}

			$notice_msg = sprintf( __( 'The Site Address URL value has been changed from %1$s to %2$s.', 'wpsso' ), $old_value, $new_value );

			$notice_key = __FUNCTION__ . '_' . $old_value . '_' . $new_value;

			$this->p->notice->upd( $notice_msg, $user_id, $notice_key );
		}

		/**
		 * WordPress sorts the active plugins array before updating the 'active_plugins' option. The default PHP sort order
		 * loads WPSSO add-ons before the WPSSO Core plugin. This filter re-sorts (if necessary) the active plugins array
		 * to load WPSSO Core before its add-ons. This allows WPSSO Core to load the latest WpssoAddon and SucomAddon
		 * classes (for example) before any (possibly older) add-on does.
		 *
		 * When activating a plugin, the activate_plugin() function executes the following:
		 *
		 *	$current   = get_option( 'active_plugins', array() );	// Get current plugins array.
		 *	$current[] = $plugin;					// Add the new plugin.
		 *	sort( $current );					// Sort the plugin array.
		 *	update_option( 'active_plugins', $current );		// Save the plugin array.
		 *
		 * See the activate_plugin() function in wordpress/wp-admin/includes/plugin.php for additional context.
		 */
		public function pre_update_active_plugins( $current, $old_value, $option = 'active_plugins' ) {

			usort( $current, array( 'self', 'sort_active_plugins' ) );

			return $current;
		}

		/**
		 * Sort the WPSSO Core plugin slug before the WPSSO add-on slugs.
		 */
		private static function sort_active_plugins( $a, $b ) {

			$plugin_prefix = 'wpsso/';

			$addon_prefix = 'wpsso-';

			if ( 0 === strpos( $a, $plugin_prefix ) ) {		// WPSSO Core plugin.

				if ( 0 === strpos( $b, $addon_prefix ) ) {	// WPSSO add-on.

					return -1;				// Sort WPSSO Core before the add-on.
				}

			} elseif ( 0 === strpos( $a, $addon_prefix ) ) {	// WPSSO add-on.

				if ( 0 === strpos( $b, $plugin_prefix ) ) {	// WPSSO Core plugin.

					return 1;				// Sort the add-on after WPSSO Core.
				}
			}

			return strcmp( $a, $b );				// Fallback to default sort() used by WordPress.
		}

		public function check_tmpl_head_attributes() {

			if ( $this->p->debug->enabled ) {

				$this->p->debug->mark();
			}

			$filter_name = SucomUtil::get_const( 'WPSSO_HEAD_ATTR_FILTER_NAME', 'head_attributes' );

			/**
			 * Skip if not using the default filter name.
			 */
			if ( 'head_attributes' !== $filter_name ) {

				return;
			}

			/**
			 * Skip if previous check is already successful.
			 */
			if ( $passed = get_option( WPSSO_TMPL_HEAD_CHECK_NAME, $default = false ) ) {

				return;
			}

			/**
			 * Skip if we will be modifying the header templates.
			 */
			$action_query = 'wpsso-action';
			$action_value = SucomUtil::get_request_value( $action_query ) ;		// POST or GET with sanitize_text_field().

			if ( 'modify_tmpl_head_attributes' === $action_value ) {

				return;
			}

			/**
			 * Skip if already dismissed.
			 */
			$notice_key = 'notice-header-tmpl-no-head-attr-' . SucomUtilWP::get_theme_slug_version();

			if ( $this->p->notice->is_admin_pre_notices( $notice_key ) ) {

				/**
				 * Get parent and child theme template file paths.
				 */
				$header_files = SucomUtilWP::get_theme_header_file_paths();

				foreach ( $header_files as $tmpl_base => $tmpl_file ) {

					$stripped_php = SucomUtil::get_stripped_php( $tmpl_file );

					if ( empty( $stripped_php ) ) {	// Empty string or false.

						continue;

					} elseif ( false !== strpos( $stripped_php, '<head>' ) ) {

						$notice_msg = $this->p->msgs->get( 'notice-header-tmpl-no-head-attr' );

						$this->p->notice->warn( $notice_msg, null, $notice_key, $dismiss_time = true );

						return;	// Stop here.
					}
				}

				/**
				 * Mark all template head checks as complete.
				 */
				update_option( WPSSO_TMPL_HEAD_CHECK_NAME, $passed = true, $autoload = false );
			}
		}

		public function check_wp_config_constants() {

			if ( $this->p->debug->enabled ) {

				$this->p->debug->mark();
			}

			/**
			 * Skip if previous check is already successful.
			 */
			if ( $passed = get_option( WPSSO_WP_CONFIG_CHECK_NAME, $default = false ) ) {

				return;
			}

			if ( $file_path = SucomUtilWP::get_wp_config_file_path() ) {

				$stripped_php = SucomUtil::get_stripped_php( $file_path );

				if ( preg_match( '/define\( *[\'"]WP_HOME[\'"][^\)]*\$/', $stripped_php ) ) {

					$notice_key = 'notice-wp-config-php-variable-home';

					$notice_msg = $this->p->msgs->get( $notice_key );

					$this->p->notice->err( $notice_msg, null, $notice_key );

					return;	// Stop here.
				}
			}

			/**
			 * Since WPSSO Core v8.5.1.
			 */
			$is_public = get_option( 'blog_public' );

			if ( $is_public ) {

				$home_url = SucomUtilWP::raw_get_home_url();

				if ( preg_match( '/^([a-z]+):\/\/([0-9\.]+)(:[0-9]+)?$/', $home_url ) ) {

					$general_settings_url = get_admin_url( $blog_id = null, 'options-general.php' );

					$reading_settings_url = get_admin_url( $blog_id = null, 'options-reading.php' );

					$notice_msg = sprintf( __( 'The WordPress <a href="%1$s">Search Engine Visibility</a> option is set to allow search engines and social sites to access this site, but your <a href="%2$s">Site Address URL</a> value is an IP address (%3$s).', 'wpsso' ), $reading_settings_url, $general_settings_url, $home_url ) . ' ';

					$notice_msg .= __( 'Please update your Search Engine Visibility option to discourage search engines from indexing this site, or use a fully qualified domain name as your Site Address URL.', 'wpsso' );

					$notice_key = 'notice-wp-config-home-url-ip-address';

					$this->p->notice->warn( $notice_msg, null, $notice_key );

					return;	// Stop here.
				}
			}

			/**
			 * Mark all WordPress config checks as complete.
			 */
			update_option( WPSSO_WP_CONFIG_CHECK_NAME, $passed = true, $autoload = false );
		}

		/**
		 * Called by WpssoAdmin->load_setting_page().
		 */
		private function modify_tmpl_head_attributes() {

			$modified = false;

			$header_files = SucomUtilWP::get_theme_header_file_paths();

			$head_action_php = '<head <?php do_action( \'add_head_attributes\' ); ?' . '>>';	// Breakup the closing php string for vim.

			if ( empty( $header_files ) ) {

				$this->p->notice->err( __( 'No header templates found in the parent or child theme directories.', 'wpsso' ) );

				return;	// Exit early.
			}

			foreach ( $header_files as $tmpl_base => $tmpl_file ) {

				$tmpl_base = basename( $tmpl_file );

				$backup_file = $tmpl_file . '~backup-' . date( 'Ymd-His' );

				$backup_base = basename( $backup_file );

				$stripped_php = SucomUtil::get_stripped_php( $tmpl_file );

				/**
				 * Double check in case of reloads etc.
				 */
				if ( empty( $stripped_php ) || strpos( $stripped_php, '<head>' ) === false ) {

					$this->p->notice->err( sprintf( __( 'No <code>%1$s</code> HTML tag found in the <code>%2$s</code> template.',
						'wpsso' ), '&lt;head&gt;', $tmpl_file ) );

					continue;
				}

				/**
				 * Make a backup of the original.
				 */
				if ( ! copy( $tmpl_file, $backup_file ) ) {

					$this->p->notice->err( sprintf( __( 'Error copying <code>%1$s</code> to <code>%2$s</code>.',
						'wpsso' ), $tmpl_file, $backup_base ) );

					continue;
				}

				$tmpl_contents = file_get_contents( $tmpl_file );

				$tmpl_contents = str_replace( '<head>', $head_action_php, $tmpl_contents );

				if ( ! $tmpl_fh = @fopen( $tmpl_file, 'wb' ) ) {

					$this->p->notice->err( sprintf( __( 'Failed to open template file <code>%s</code> for writing.',
						'wpsso' ), $tmpl_file ) );

					continue;
				}

				if ( fwrite( $tmpl_fh, $tmpl_contents ) ) {

					$notice_msg = sprintf( __( 'The <code>%s</code> template has been successfully modified and saved.',
						'wpsso' ), $tmpl_file ) . ' ';

					$notice_msg .= sprintf( __( 'A backup copy of the original template has been saved as <code>%s</code> in the same folder.',
						'wpsso' ), $backup_base );

					$this->p->notice->upd( $notice_msg );

					$modified = true;

				} else {

					$notice_msg = sprintf( __( 'Failed to write the <code>%s</code> template.',
						'wpsso' ), $tmpl_file ) . ' ';

					$notice_msg .= sprintf( __( 'You may need to restore the original template saved as <code>%s</code> in the same folder.',
						'wpsso' ), $backup_base );

					$this->p->notice->err( $notice_msg );
				}

				fclose( $tmpl_fh );
			}

			if ( $modified ) {

				$admin_roles = $this->p->cf[ 'wp' ][ 'roles' ][ 'admin' ];

				$user_ids = SucomUtilWP::get_roles_user_ids( $admin_roles );

				$notice_key = 'notice-header-tmpl-no-head-attr-' . SucomUtilWP::get_theme_slug_version();

				$this->p->notice->clear_key( $notice_key, $user_ids );	// Just in case.
			}
		}

		public function add_og_types_table_rows( array &$table_rows, $form ) {

			$pkg_info = $this->get_pkg_info();	// Returns an array from cache.
			$td_attr  = $pkg_info[ 'wpsso' ][ 'pp' ] ? '' : ' class="blank"';
			$get_func = $pkg_info[ 'wpsso' ][ 'pp' ] ? 'get_select' : 'get_no_select';
			$og_types = $this->p->og->get_og_types_select();

			$table_rows[] = $pkg_info[ 'wpsso' ][ 'pp' ] ? '' : '<td colspan="2">' . $this->p->msgs->pro_feature( 'wpsso' ) . '</td>';

			/**
			 * Open Graph Type.
			 */
			foreach ( array( 
				'home_page'    => _x( 'Open Graph Type for Page Homepage', 'option label', 'wpsso' ),
				'home_posts'   => _x( 'Open Graph Type for Posts Homepage', 'option label', 'wpsso' ),
				'user_page'    => _x( 'Open Graph Type for User Profile', 'option label', 'wpsso' ),
				'search_page'  => _x( 'Open Graph Type for Search Results', 'option label', 'wpsso' ),
				'archive_page' => _x( 'Open Graph Type for Other Archive', 'option label', 'wpsso' ),
			) as $type_name => $th_label ) {

				$opt_key = 'og_type_for_' . $type_name;	// Hard-coded value - no sanitation required.

				$table_rows[ $opt_key ] = $form->get_tr_hide( 'basic', $opt_key ) .
					$form->get_th_html( $th_label, '', $opt_key ) . 
					'<td' . $td_attr . '>' . $form->$get_func( $opt_key, $og_types, $css_class = 'og_type' ) . '</td>';
			}

			/**
			 * Open Graph Type by Post Type.
			 */
			$type_select = '';
			$type_keys   = array();
			$post_types  = SucomUtilWP::get_post_types( 'objects' );

			foreach ( $post_types as $obj ) {

				$type_keys[] = $opt_key = SucomUtil::sanitize_hookname( 'og_type_for_' . $obj->name );

				$obj_label = SucomUtilWP::get_object_label( $obj );

				$type_select .= '<p>' . $form->$get_func( $opt_key, $og_types, $css_class = 'og_type' ) . ' ' .
					sprintf( _x( 'for %s', 'option comment', 'wpsso' ), $obj_label ) . '</p>' . "\n";
			}

			$type_keys[] = $opt_key = 'og_type_for_post_archive';	// Hard-coded value - no sanitation required.

			$type_select .= '<p>' . $form->$get_func( $opt_key, $og_types, $css_class = 'og_type' ) . ' ' .
				sprintf( _x( 'for %s', 'option comment', 'wpsso' ), _x( 'Post Type Archive Page', 'option comment', 'wpsso' ) ) . '</p>' . "\n";

			$tr_key   = 'og_type_for_ptn';
			$th_label = _x( 'Open Graph Type by Post Type', 'option label', 'wpsso' );

			$table_rows[ $tr_key ] = '' .
				$form->get_th_html( $th_label, $css_class = '', $css_id = $tr_key ) .
				'<td' . $td_attr . '>' . $type_select . '</td>';

			unset( $type_select, $type_keys, $post_types, $tr_key, $th_label );	// Just in case.

			/**
			 * Open Graph Type by Taxonomy.
			 */
			$type_select = '';
			$type_keys   = array();
			$taxonomies  = SucomUtilWP::get_taxonomies( 'objects' );

			foreach ( $taxonomies as $obj ) {

				$type_keys[] = $opt_key = SucomUtil::sanitize_hookname( 'og_type_for_tax_' . $obj->name );

				$obj_label = SucomUtilWP::get_object_label( $obj );

				$type_select .= '<p>' . $form->$get_func( $opt_key, $og_types, $css_class = 'og_type' ) . ' ' .
					sprintf( _x( 'for %s', 'option comment', 'wpsso' ), $obj_label ) . '</p>' . "\n";
			}

			$tr_key   = 'og_type_for_ttn';
			$th_label = _x( 'Open Graph Type by Taxonomy', 'option label', 'wpsso' );

			$table_rows[ $tr_key ] = $form->get_tr_hide( 'basic', $type_keys ) .
				$form->get_th_html( $th_label, '', $tr_key ) . '<td' . $td_attr . '>' . $type_select . '</td>';

			unset( $type_select, $type_keys, $taxonomies, $tr_key, $th_label );	// Just in case.
		}

		/**
		 * Deprecated on 2020/07/01.
		 */
		public function add_schema_knowledge_graph_table_rows( array &$table_rows, $form ) {
		}

		public function add_schema_item_props_table_rows( array &$table_rows, $form ) {

			if ( $this->p->debug->enabled ) {

				$this->p->debug->mark();
			}

			$max_media_items = $this->p->cf[ 'form' ][ 'max_media_items' ];

			$json_req_msg = $this->p->msgs->maybe_ext_required( 'wpssojson' );

			$this->add_schema_publisher_type_table_rows( $table_rows, $form );

			$table_rows[ 'seo_author_name' ] = $form->get_tr_hide( 'basic', 'seo_author_name' ) . 
				$form->get_th_html( _x( 'Author / Person Name Format', 'option label', 'wpsso' ), $css_class = '', $css_id = 'seo_author_name' ) . 
				'<td>' . $form->get_select( 'seo_author_name', $this->p->cf[ 'form' ][ 'user_name_fields' ] ) . '</td>';

			$table_rows[ 'schema_img_max' ] = $form->get_tr_hide( 'basic', 'schema_img_max' ) . 
				$form->get_th_html( _x( 'Maximum Images to Include', 'option label', 'wpsso' ), $css_class = '', $css_id = 'schema_img_max' ) . 
				'<td>' .
				$form->get_select( 'schema_img_max', range( 0, $max_media_items ), $css_class = 'short', $css_id = '', $is_assoc = true ) .
				$this->p->msgs->maybe_preview_images_first() .
				'</td>';

			$table_rows[ 'schema_1_1_img_size' ] = $form->get_tr_hide_img_dim( 'basic', 'schema_1_1_img' ) .
				$form->get_th_html( _x( 'Schema 1:1 (Google) Image Size', 'option label', 'wpsso' ), '', 'schema_1_1_img_size' ) . 
				'<td>' . $form->get_input_image_dimensions( 'schema_1_1_img' ) . $json_req_msg . '</td>';

			$table_rows[ 'schema_4_3_img_size' ] = $form->get_tr_hide_img_dim( 'basic', 'schema_4_3_img' ) .
				$form->get_th_html( _x( 'Schema 4:3 (Google) Image Size', 'option label', 'wpsso' ), '', 'schema_4_3_img_size' ) . 
				'<td>' . $form->get_input_image_dimensions( 'schema_4_3_img' ) . $json_req_msg . '</td>';

			$table_rows[ 'schema_16_9_img_size' ] = $form->get_tr_hide_img_dim( 'basic', 'schema_16_9_img' ) .
				$form->get_th_html( _x( 'Schema 16:9 (Google) Image Size', 'option label', 'wpsso' ), '', 'schema_16_9_img_size' ) . 
				'<td>' . $form->get_input_image_dimensions( 'schema_16_9_img' ) . $json_req_msg . '</td>';

			$table_rows[ 'thumb_img_size' ] = $form->get_tr_hide_img_dim( 'basic', 'thumb_img' ) .
				$form->get_th_html( _x( 'Schema Thumbnail Image Size', 'option label', 'wpsso' ), '', 'thumb_img_size' ).
				'<td>' . $form->get_input_image_dimensions( 'thumb_img' ) . '</td>';

			$table_rows[ 'schema_desc_max_len' ] = $form->get_tr_hide( 'basic', 'schema_desc_max_len' ) . 
				$form->get_th_html( _x( 'Schema Description Max. Length', 'option label', 'wpsso' ), '', 'schema_desc_max_len' ) . 
				'<td>' . $form->get_input( 'schema_desc_max_len', 'chars' ) . ' ' . _x( 'characters or less', 'option comment', 'wpsso' ) . '</td>';
		}

		public function add_schema_publisher_type_table_rows( array &$table_rows, $form ) {

			if ( $this->p->debug->enabled ) {

				$this->p->debug->mark();
			}

			$owner_roles = $this->p->cf[ 'wp' ][ 'roles' ][ 'owner' ];
			$site_owners = SucomUtilWP::get_roles_user_select( $owner_roles );

			$table_rows[ 'site_pub_schema_type' ] = '' . 
				$this->form->get_th_html( _x( 'WebSite Publisher Type', 'option label', 'wpsso' ), $css_class = '', $css_id = 'site_pub_schema_type' ) . 
				'<td>' . $this->form->get_select( 'site_pub_schema_type', $this->p->cf[ 'form' ][ 'publisher_types' ], $css_class = '', $css_id = '',
					$is_assoc = true, $is_disabled = false, $selected = false, $event_names = array( 'on_change_unhide_rows' ) ) . '</td>';

			$table_rows[ 'site_pub_person_id' ] = $form->get_tr_on_change( 'site_pub_schema_type', 'person' ) . 
				$this->form->get_th_html( _x( 'WebSite Publisher (Person)', 'option label', 'wpsso' ), '', 'site_pub_person_id' ) . 
				'<td>' . $this->form->get_select( 'site_pub_person_id', $site_owners, $css_class = '', $css_id = '', $is_assoc = true ) . '</td>';

			$table_rows[ 'site_org_logo_url' ] = $form->get_tr_on_change( 'site_pub_schema_type', 'organization' ) .
				$form->get_th_html_locale( '<a href="https://developers.google.com/structured-data/customize/logos">' .
				_x( 'Organization Logo URL', 'option label', 'wpsso' ) . '</a>', $css_class = '', $css_id = 'site_org_logo_url' ) . 
				'<td>' . $form->get_input_locale( 'site_org_logo_url', $css_class = 'wide is_required' ) . '</td>';

			$table_rows[ 'site_org_banner_url' ] = $form->get_tr_on_change( 'site_pub_schema_type', 'organization' ) .
				$form->get_th_html_locale( '<a href="https://developers.google.com/search/docs/data-types/article#logo-guidelines">' .
				_x( 'Organization Banner URL', 'option label', 'wpsso' ) . '</a>', $css_class = '', $css_id = 'site_org_banner_url' ) . 
				'<td>' . $form->get_input_locale( 'site_org_banner_url', $css_class = 'wide is_required' ) . '</td>';
		}

		public function add_schema_item_types_table_rows( array &$table_rows, $form ) {

			$pkg_info        = $this->get_pkg_info();	// Returns an array from cache.
			$td_attr         = $pkg_info[ 'wpsso' ][ 'pp' ] ? '' : ' class="blank"';
			$get_func        = $pkg_info[ 'wpsso' ][ 'pp' ] ? 'get_select' : 'get_no_select';
			$schema_exp_secs = $this->p->util->get_cache_exp_secs( 'wpsso_t_' );	// Default is month in seconds.
			$schema_types    = $this->p->schema->get_schema_types_select( $context = 'settings' );

			$table_rows[] = $pkg_info[ 'wpsso' ][ 'pp' ] ? '' : '<td colspan="2">' . $this->p->msgs->pro_feature( 'wpsso' ) . '</td>';

			/**
			 * Schema Type.
			 */
			foreach ( array( 
				'home_page'    => _x( 'Schema Type for Page Homepage', 'option label', 'wpsso' ),
				'home_posts'   => _x( 'Schema Type for Posts Homepage', 'option label', 'wpsso' ),
				'user_page'    => _x( 'Schema Type for User Profile', 'option label', 'wpsso' ),
				'search_page'  => _x( 'Schema Type for Search Results', 'option label', 'wpsso' ),
				'archive_page' => _x( 'Schema Type for Other Archive', 'option label', 'wpsso' ),
			) as $type_name => $th_label ) {

				$opt_key = 'schema_type_for_' . $type_name;	// Hard-coded value - no sanitation required.

				$table_rows[ $opt_key ] = $form->get_tr_hide( 'basic', $opt_key ) . 
					$form->get_th_html( $th_label, '', $opt_key ) . 
					'<td' . $td_attr . '>' . $form->$get_func( $opt_key, $schema_types, $css_class = 'schema_type', $css_id = '',
						$is_assoc = true, $is_disabled = false, $selected = false, $event_names = array( 'on_focus_load_json' ),
							$event_args = array(
								'json_var'  => 'schema_types',
								'exp_secs'  => $schema_exp_secs,	// Create and read from a javascript URL.
								'is_transl' => true,			// No label translation required.
								'is_sorted' => true,			// No label sorting required.
							)
						) .
					'</td>';
			}

			/**
			 * Schema Type by Post Type.
			 */
			$type_select = '';
			$type_keys   = array();
			$post_types  = SucomUtilWP::get_post_types( 'objects' );

			foreach ( $post_types as $obj ) {

				$type_keys[] = $opt_key = SucomUtil::sanitize_hookname( 'schema_type_for_' . $obj->name );

				$obj_label = SucomUtilWP::get_object_label( $obj );

				$type_select .= '<p>' . $form->$get_func( $opt_key, $schema_types, $css_class = 'schema_type', $css_id = '',
					$is_assoc = true, $is_disabled = false, $selected = false, $event_names = array( 'on_focus_load_json' ),
						$event_args = array(
							'json_var'  => 'schema_types',
							'exp_secs'  => $schema_exp_secs,	// Create and read from a javascript URL.
							'is_transl' => true,			// No label translation required.
							'is_sorted' => true,			// No label sorting required.
						)
					) . ' ' . sprintf( _x( 'for %s', 'option comment', 'wpsso' ), $obj_label ) . '</p>' . "\n";
			}

			$type_keys[] = $opt_key = 'schema_type_for_post_archive';	// Hard-coded value - no sanitation required.

			$type_select .= '<p>' . $form->$get_func( $opt_key, $schema_types, $css_class = 'schema_type', $css_id = '',
				$is_assoc = true, $is_disabled = false, $selected = false, $event_names = array( 'on_focus_load_json' ),
					$event_args = array(
						'json_var'  => 'schema_types',
						'exp_secs'  => $schema_exp_secs,	// Create and read from a javascript URL.
						'is_transl' => true,			// No label translation required.
						'is_sorted' => true,			// No label sorting required.
					)
				) . ' ' .
				sprintf( _x( 'for %s', 'option comment', 'wpsso' ), _x( 'Post Type Archive Page', 'option comment', 'wpsso' ) ) .
				'</p>' . "\n";

			$tr_key   = 'schema_type_for_ptn';
			$th_label = _x( 'Schema Type by Post Type', 'option label', 'wpsso' );

			$table_rows[ $tr_key ] = $form->get_th_html( $th_label, '', $tr_key ) . '<td' . $td_attr . '>' . $type_select . '</td>';

			unset( $type_select, $type_keys, $post_types, $tr_key, $th_label );	// Just in case.

			/**
			 * Schema Type by Taxonomy.
			 */
			$type_select = '';
			$type_keys   = array();
			$taxonomies  = SucomUtilWP::get_taxonomies( 'objects' );

			foreach ( $taxonomies as $obj ) {

				$type_keys[] = $opt_key = SucomUtil::sanitize_hookname( 'schema_type_for_tax_' . $obj->name );

				$obj_label = SucomUtilWP::get_object_label( $obj );

				$type_select .= '<p>' . $form->$get_func( $opt_key, $schema_types, $css_class = 'schema_type', $css_id = '',
					$is_assoc = true, $is_disabled = false, $selected = false, $event_names = array( 'on_focus_load_json' ),
						$event_args = array(
							'json_var'  => 'schema_types',
							'exp_secs'  => $schema_exp_secs,	// Create and read from a javascript URL.
							'is_transl' => true,			// No label translation required.
							'is_sorted' => true,			// No label sorting required.
						)
					) . ' ' . sprintf( _x( 'for %s', 'option comment', 'wpsso' ), $obj_label ) . '</p>' . "\n";
			}

			$tr_key   = 'schema_type_for_ttn';
			$th_label = _x( 'Schema Type by Taxonomy', 'option label', 'wpsso' );

			$table_rows[ $tr_key ] = $form->get_tr_hide( 'basic', $type_keys ) .
				$form->get_th_html( $th_label, '', $tr_key ) . '<td' . $td_attr . '>' . $type_select . '</td>';

			unset( $type_select, $type_keys, $taxonomies, $tr_key, $th_label );	// Just in case.
		}

		public function add_advanced_plugin_settings_table_rows( array &$table_rows, $form, $network = false ) {

			$table_rows[ 'plugin_clean_on_uninstall' ] = '' .
				$form->get_th_html( _x( 'Remove Settings on Uninstall', 'option label', 'wpsso' ), '', 'plugin_clean_on_uninstall' ) . 
				'<td>' . $form->get_checkbox( 'plugin_clean_on_uninstall' ) . ' ' .
				_x( 'including any custom meta for posts, terms and users', 'option comment', 'wpsso' ) . '</td>' .
				self::get_option_site_use( 'plugin_clean_on_uninstall', $form, $network, $is_enabled = true );

			$table_rows[ 'plugin_debug' ] = '' .
				$form->get_th_html( _x( 'Add Hidden Debug Messages', 'option label', 'wpsso' ), '', 'plugin_debug' ) . 
				'<td>' . ( ! $network && SucomUtil::get_const( 'WPSSO_HTML_DEBUG' ) ? $form->get_no_checkbox( 'plugin_debug' ) .
					' <em>' . _x( 'WPSSO_HTML_DEBUG constant is true', 'option comment', 'wpsso' ) . '</em>' :
						$form->get_checkbox( 'plugin_debug' ) ) . '</td>' .
				self::get_option_site_use( 'plugin_debug', $form, $network, $is_enabled = true );
		}

		/**
		 * Deprecated since 2020/04/28.
		 */
		public function add_advanced_product_attr_table_rows( array &$table_rows, $form ) {

			$this->add_advanced_product_attrs_table_rows( $table_rows, $form );
		}

		public function add_advanced_product_attrs_table_rows( array &$table_rows, $form ) {

			$pkg_info = $this->get_pkg_info();	// Returns an array from cache.
			$td_attr  = $pkg_info[ 'wpsso' ][ 'pp' ] ? '' : ' class="blank"';
			$get_func = $pkg_info[ 'wpsso' ][ 'pp' ] ? 'get_input' : 'get_no_input';

			$table_rows[] = $pkg_info[ 'wpsso' ][ 'pp' ] ? '' : '<td colspan="2">' . $this->p->msgs->pro_feature( 'wpsso' ) . '</td>';

			$table_rows[] = '<td colspan="2">' . $this->p->msgs->get( 'info-product-attrs' ) . '</td>';

			foreach ( $this->p->cf[ 'form' ][ 'attr_labels' ] as $opt_key => $opt_label ) {

				$cmt_transl = self::get_option_unit_comment( $opt_key );

				$table_rows[ $opt_key ] = '' .
					$form->get_th_html( _x( $opt_label, 'option label', 'wpsso' ), '', $opt_key ) . 
					'<td' . $td_attr . '>' . $form->$get_func( $opt_key ) . $cmt_transl . '</td>';
			}
		}

		public function add_advanced_custom_fields_table_rows( array &$table_rows, $form ) {

			$pkg_info = $this->get_pkg_info();	// Returns an array from cache.
			$td_attr  = $pkg_info[ 'wpsso' ][ 'pp' ] ? '' : ' class="blank"';
			$get_func = $pkg_info[ 'wpsso' ][ 'pp' ] ? 'get_input' : 'get_no_input';

			$table_rows[] = $pkg_info[ 'wpsso' ][ 'pp' ] ? '' : '<td colspan="2">' . $this->p->msgs->pro_feature( 'wpsso' ) . '</td>';

			$table_rows[] = '<td colspan="2">' . $this->p->msgs->get( 'info-custom-fields' ) . '</td>';

			/**
			 * Example config:
			 *
			 * 	$cf_md_index = array(
			 *		'plugin_cf_addl_type_urls'           => 'schema_addl_type_url',
			 *		'plugin_cf_howto_steps'              => 'schema_howto_step',
			 *		'plugin_cf_howto_supplies'           => 'schema_howto_supply',
			 *		'plugin_cf_howto_tools'              => 'schema_howto_tool',
			 *		'plugin_cf_img_url'                  => 'og_img_url',
			 *		'plugin_cf_product_avail'            => 'product_avail',
			 *		'plugin_cf_product_brand'            => 'product_brand',
			 *		'plugin_cf_product_color'            => 'product_color',
			 *		'plugin_cf_product_condition'        => 'product_condition',
			 *		'plugin_cf_product_currency'         => 'product_currency',
			 *		'plugin_cf_product_material'         => 'product_material',
			 *		'plugin_cf_product_mfr_part_no'      => 'product_mfr_part_no',		// Product MPN.
			 *		'plugin_cf_product_price'            => 'product_price',
			 *		'plugin_cf_product_retailer_part_no' => 'product_retailer_part_no',	// Product SKU.
			 *		'plugin_cf_product_size'             => 'product_size',
			 *		'plugin_cf_product_target_gender'    => 'product_target_gender',
			 *		'plugin_cf_recipe_ingredients'       => 'schema_recipe_ingredient',
			 *		'plugin_cf_recipe_instructions'      => 'schema_recipe_instruction',
			 *		'plugin_cf_sameas_urls'              => 'schema_sameas_url',
			 *		'plugin_cf_vid_embed'                => 'og_vid_embed',
			 *		'plugin_cf_vid_url'                  => 'og_vid_url',
			 * 	);
			 *
			 * Hooked by the WpssoProRecipeWpRecipeMaker and WpssoProRecipeWpUltimateRecipe classes
			 * to clear the 'plugin_cf_recipe_ingredients' and 'plugin_cf_recipe_instructions' values.
			 */
			$cf_md_index = (array) apply_filters( 'wpsso_cf_md_index', $this->p->cf[ 'opt' ][ 'cf_md_index' ] );

			$opt_labels = array();

			foreach ( $cf_md_index as $opt_key => $md_key ) {

				/**
				 * Make sure we have a label for the custom field option.
				 */
				if ( ! empty( $this->p->cf[ 'form' ][ 'cf_labels' ][ $opt_key ] ) ) {

					$opt_labels[ $opt_key ] = $this->p->cf[ 'form' ][ 'cf_labels' ][ $opt_key ];
				}
			}

			asort( $opt_labels );

			foreach ( $opt_labels as $opt_key => $opt_label ) {

				/**
				 * If we don't have a meta data key, then clear the custom field name (just in case) and disable
				 * the option.
				 */
				if ( empty( $cf_md_index[ $opt_key ] ) ) {

					$form->options[ $opt_key ] = '';

					$always_disabled = true;

				} else {
					$always_disabled = false;
				}

				$cmt_transl = self::get_option_unit_comment( $opt_key );

				$table_rows[ $opt_key ] = '' .
					$form->get_th_html( _x( $opt_label, 'option label', 'wpsso' ), '', $opt_key ) . 
					'<td' . $td_attr . '>' . $form->$get_func( $opt_key, $css_class = '', $css_id = '',
						$max_len = 0, $holder = '', $always_disabled ) . $cmt_transl . '</td>';
			}
		}

		public static function get_option_unit_comment( $opt_key ) {

			$cmt_transl = '';

			if ( preg_match( '/^.*_([^_]+)_value$/', $opt_key, $unit_match ) ) {

				if ( $unit_text = WpssoSchema::get_data_unit_text( $unit_match[ 1 ] ) ) {

					$cmt_transl = ' ' . sprintf( _x( 'in %s', 'option comment', 'wpsso' ), $unit_text );
				}
			}

			return $cmt_transl;
		}

		public static function get_option_site_use( $name, $form, $network = false, $is_enabled = false ) {

			$html = '';

			if ( $network ) {

				$pkg_info   = $this->get_pkg_info();	// Returns an array from cache.
				$is_enabled = $is_enabled || $pkg_info[ 'wpsso' ][ 'pp' ] ? true : false;

				$html .= $form->get_th_html( _x( 'Site Use', 'option label (very short)', 'wpsso' ), 'site-use' );

				$html .= $is_enabled ? '<td class="site-use">' : '<td class="blank site-use">';

				$html .= $is_enabled ? $form->get_select( $name . ':use', WpssoConfig::$cf[ 'form' ][ 'site_option_use' ], $css_class = 'site-use' ) :
					$form->get_no_select( $name . ':use', WpssoConfig::$cf[ 'form' ][ 'site_option_use' ], $css_class = 'site-use' );

				$html .= '</td>';
			}

			return $html;
		}

		public function get_readme_info( $ext, $read_cache = true ) {

			if ( $this->p->debug->enabled ) {

				$this->p->debug->log_args( array( 
					'ext'        => $ext,
					'read_cache' => $read_cache,
				) );
			}

			$rel_file  = 'readme.txt';
			$file_path = WpssoConfig::get_ext_file_path( $ext, $rel_file );
			$file_key  = SucomUtil::sanitize_hookname( $rel_file );	// Changes readme.txt to readme_txt (note underscore).
			$file_url  = isset( $this->p->cf[ 'plugin' ][ $ext ][ 'url' ][ $file_key ] ) ? 
				$this->p->cf[ 'plugin' ][ $ext ][ 'url' ][ $file_key ] : false;

			$cache_md5_pre = 'wpsso_';
			$cache_salt    = __METHOD__ . '(ext:' . $ext . ')';
			$cache_id      = $cache_md5_pre . md5( $cache_salt );

			/**
			 * Set and filter the cache expiration value only once.
			 */
			static $cache_exp_secs = null;

			if ( null === $cache_exp_secs ) {

				$cache_exp_filter = 'wpsso_cache_expire_' . $file_key;	// Example: 'wpsso_cache_expire_readme_txt'.
				$cache_exp_secs   = (int) apply_filters( $cache_exp_filter, DAY_IN_SECONDS );
			}

			$readme_info     = false;
			$readme_content  = false;
			$readme_from_url = false;

			if ( $cache_exp_secs > 0 ) {

				if ( $read_cache ) {

					$readme_info = get_transient( $cache_id );

					if ( is_array( $readme_info ) ) {

						return $readme_info;	// Stop here.
					}
				}

				if ( $file_url && strpos( $file_url, '://' ) ) {

					/**
					 * Clear the cache first if reading the cache is disabled.
					 */
					if ( ! $read_cache ) {

						$this->p->cache->clear( $file_url );
					}

					$readme_from_url = true;

					$readme_content = $this->p->cache->get( $file_url, 'raw', 'file', $cache_exp_secs );
				}
			} else {
				delete_transient( $cache_id );	// Just in case.
			}

			if ( empty( $readme_content ) ) {

				if ( $file_path && file_exists( $file_path ) && $fh = @fopen( $file_path, 'rb' ) ) {

					$readme_from_url = false;

					$readme_content = fread( $fh, filesize( $file_path ) );

					fclose( $fh );
				}
			}

			if ( empty( $readme_content ) ) {

				$readme_info = array();	// Save an empty array.

			} else {

				require_once WPSSO_PLUGINDIR . 'lib/ext/parse-readme.php';

				$parser =& SuextParseReadme::get_instance();

				$readme_info = $parser->parse_readme_contents( $readme_content );

				/**
				 * Remove possibly inaccurate information from the local readme file.
				 */
				if ( ! $readme_from_url && is_array( $readme_info ) ) {

					foreach ( array( 'stable_tag', 'upgrade_notice' ) as $key ) {

						unset ( $readme_info[ $key ] );
					}
				}
			}

			/**
			 * Save the parsed readme to the transient cache.
			 */
			if ( $cache_exp_secs > 0 ) {

				set_transient( $cache_id, $readme_info, $cache_exp_secs );

				if ( $this->p->debug->enabled ) {

					$this->p->debug->log( 'readme_info saved to transient cache for ' . $cache_exp_secs . ' seconds' );
				}
			}

			return is_array( $readme_info ) ? $readme_info : array();	// Just in case.
		}

		/**
		 * Deprecated on 2020/09/14.
		 */
		public function get_config_url_content( $ext, $rel_file, $cache_exp_secs = null ) {

			return $this->get_ext_file_content( $ext, $rel_file, $cache_exp_secs );
		}

		/**
		 * Called from WpssoSubmenuSetup->show_metabox_setup_guide() and WpssoJsonSubmenuSchemaShortcode->show_metabox_schema_shortcode().
		 */
		public function get_ext_file_content( $ext, $rel_file, $cache_exp_secs = null ) {

			if ( $this->p->debug->enabled ) {

				$this->p->debug->log_args( array( 
					'ext'            => $ext,
					'rel_file'       => $rel_file,
					'cache_exp_secs' => $cache_exp_secs,
				) );
			}

			$rel_file = SucomUtil::sanitize_file_path( $rel_file );

			$file_path = WpssoConfig::get_ext_file_path( $ext, $rel_file );

			$file_key = SucomUtil::sanitize_hookname( basename( $rel_file ) );	// Changes html/setup.html to setup_html (note underscore).

			$file_url = isset( $this->p->cf[ 'plugin' ][ $ext ][ 'url' ][ $file_key ] ) ? 
				$this->p->cf[ 'plugin' ][ $ext ][ 'url' ][ $file_key ] : false;

			$text_domain = isset( $this->p->cf[ 'plugin' ][ $ext ][ 'text_domain' ] ) ?
				$this->p->cf[ 'plugin' ][ $ext ][ 'text_domain' ] : false;

			if ( null === $cache_exp_secs ) {

				$cache_exp_secs = WEEK_IN_SECONDS;
			}

			$cache_exp_filter = 'wpsso_cache_expire_' . $file_key;	// Example: 'wpsso_cache_expire_setup_html'.
			$cache_exp_secs   = (int) apply_filters( $cache_exp_filter, $cache_exp_secs );
			$cache_content    = false;

			if ( $cache_exp_secs > 0 ) {

				if ( $file_url && strpos( $file_url, '://' ) ) {

					$cache_content = $this->p->cache->get( $file_url, 'raw', 'file', $cache_exp_secs );
				}
			}

			if ( empty( $cache_content ) ) {	// No content from the file URL cache.

				if ( $file_path && file_exists( $file_path ) && $fh = @fopen( $file_path, 'rb' ) ) {

					$cache_content = fread( $fh, filesize( $file_path ) );

					fclose( $fh );
				}
			}

			if ( $text_domain ) {

				/**
				 * Translate HTML headers, paragraphs, and list items.
				 */
				$cache_content = SucomUtil::get_html_transl( $cache_content, $text_domain );
			}

			return $cache_content;
		}

		public function plugin_complete_actions( $actions ) {

			if ( ! empty( $this->pageref_url ) && ! empty( $this->pageref_title ) ) {

				foreach ( $actions as $action => &$html ) {

					switch ( $action ) {

						case 'plugins_page':

							$html = '<a href="' . urlencode( $this->pageref_url ) . '" target="_parent">' .
								sprintf( __( 'Return to %s', 'wpsso' ), $this->pageref_title ) . '</a>';

							break;

						default:

							if ( preg_match( '/^(.*href=["\'])([^"\']+)(["\'].*)$/', $html, $matches ) ) {

								$url = add_query_arg( array(
									'wpsso_pageref_url'   => urlencode( $this->pageref_url ),
									'wpsso_pageref_title' => urlencode( $this->pageref_title ),
								), $matches[ 2 ] );

								$html = $matches[ 1 ] . $url . $matches[ 3 ];
							}

							break;
					}
				}
			}

			return $actions;
		}

		public function plugin_complete_redirect( $url ) {

			if ( strpos( $url, '?activate=true' ) ) {

				if ( ! empty( $this->pageref_url ) ) {

					// translators: Please ignore - translation uses a different text domain.
					$this->p->notice->upd( __( 'Plugin <strong>activated</strong>.' ) );

					$url = $this->pageref_url;
				}
			}

			return $url;
		}

		public function get_check_for_updates_link( $get_notice = true ) {

			$check_url  = '';
			$notice_msg = '';

			if ( class_exists( 'WpssoUm' ) ) {

				$pkg_info  = $this->get_pkg_info();	// Returns an array from cache.
				$check_url = $this->p->util->get_admin_url( 'um-general?wpsso-action=check_for_updates' );
				$check_url = wp_nonce_url( $check_url, WpssoAdmin::get_nonce_action(), WPSSO_NONCE_NAME );

				// translators: %1$s is the URL, %2$s is the short plugin name.
				$notice_transl = __( 'You may <a href="%1$s">refresh the update information for %2$s and its add-ons</a> to check if newer versions are available.', 'wpsso' );

				$notice_msg = sprintf( $notice_transl, $check_url, $pkg_info[ 'wpsso' ][ 'short_dist' ] );

			} elseif ( empty( $_GET[ 'force-check' ] ) ) {

				$check_url = self_admin_url( 'update-core.php?force-check=1' );

				// translators: %1$s is the URL.
				$notice_transl = __( 'You may <a href="%1$s">refresh the update information for WordPress (plugins, themes and translations)</a> to check if newer versions are available.', 'wpsso' );

				$notice_msg = sprintf( $notice_transl, $check_url );
			}

			return $get_notice ? $notice_msg : $check_url;
		}

		/**
		 * Returns a 128x128px image by default.
		 */
		public function get_ext_img_icon( $ext, $px = 128 ) {

			/**
			 * The default image is a transparent 1px gif.
			 */
			$img_src = 'src="data:image/gif;base64,R0lGODlhAQABAIAAAP///wAAACH5BAEAAAAALAAAAAABAAEAAAICRAEAOw=="';

			if ( ! empty( $this->p->cf[ 'plugin' ][ $ext ][ 'assets' ][ 'icons' ] ) ) {

				/**
				 * Icon image array keys are '1x' and '2x'.
				 */
				$icons = $this->p->cf[ 'plugin' ][ $ext ][ 'assets' ][ 'icons' ];

				if ( ! empty( $icons[ '1x' ] ) ) {

					$img_src = 'src="' . $icons[ '1x' ] . '"';	// 128px.
				}

				if ( ! empty( $icons[ '2x' ] ) ) {

					$img_src .= ' srcset="' . $icons[ '2x' ] . ' 256w"';	// 256px.
				}
			}

			return '<img ' . $img_src . ' width="' . $px . '" height="' . $px . '" style="width:' . $px . 'px; height:' . $px . 'px;"/>';
		}

		/**
		 * Called from the network settings pages.
		 *
		 * Add a class to set a minimum width for the network postboxes.
		 */
		public function add_class_postbox_network( $classes ) {

			$classes[] = 'postbox-network';

			return $classes;
		}

		public function add_class_postbox_menu_id( $classes ) {

			global $wp_current_filter;

			$filter_name = end( $wp_current_filter );

			$postbox_name = preg_replace( '/^.*-(' . $this->menu_id . '_.*)$/u', '$1', $filter_name );

			$classes[] = 'postbox-' . $this->menu_id;
			$classes[] = 'postbox-' . $postbox_name;

			return $classes;
		}

		public function export_plugin_settings_json() {

			$date_slug    = date( 'YmdHiT' );
			$home_slug    = SucomUtil::sanitize_hookname( preg_replace( '/^.*\/\//', '', get_home_url() ) );
			$mime_type_gz = 'application/x-gzip';
			$file_name_gz = WpssoConfig::get_version( $add_slug = true ) . '-' . $home_slug . '-' . $date_slug . '.json.gz';
			$opts_encoded = SucomUtil::json_encode_array( $this->p->options );
			$gzdata       = gzencode( $opts_encoded, 9, FORCE_GZIP );
			$filesize     = strlen( $gzdata );
			$disposition  = 'attachment';
			$chunksize    = 1024 * 32;	// 32kb per fread().

			session_write_close();

			ignore_user_abort();

			set_time_limit( 0 );

			ini_set( 'zlib.output_compression', 0 );

			ini_set( 'implicit_flush', 1 );

			if ( function_exists( 'apache_setenv' ) ) {

				apache_setenv( 'no-gzip', 1 );
			}

			ob_end_flush();

			/**
			 * Remove all dots, except last one, for MSIE clients.
			 */
			if ( strstr( $_SERVER[ 'HTTP_USER_AGENT' ], 'MSIE' ) ) {

				$file_name_gz = preg_replace( '/\./', '%2e', $file_name_gz, substr_count( $file_name_gz, '.' ) - 1 );
			}

			if ( isset( $_SERVER[ 'HTTPS' ] ) ) {

				header( 'Pragma: ' );
				header( 'Cache-Control: ' );
				header( 'Expires: Mon, 26 Jul 1997 05:00:00 GMT' );
				header( 'Last-Modified: ' . gmdate( 'D, d M Y H:i:s' ) . ' GMT' );
				header( 'Cache-Control: no-store, no-cache, must-revalidate' );
				header( 'Cache-Control: post-check=0, pre-check=0', false );

			} elseif ( $disposition == 'attachment' ) {

				header( 'Cache-control: private' );

			} else {

				header( 'Cache-Control: no-cache, must-revalidate' );
				header( 'Pragma: no-cache' );
			}

			header( 'Content-Type: application/' . $mime_type_gz );
			header( 'Content-Disposition: ' . $disposition . '; filename="' . $file_name_gz . '"' );
			header( 'Content-Transfer-Encoding: binary' );
			header( 'Content-Length: ' . $filesize );

			echo $gzdata;

			flush();

			sleep( 1 );

			exit();
		}

		public function import_plugin_settings_json() {

			$mime_type_gz  = 'application/x-gzip';
			$dot_file_ext  = '.json.gz';
			$max_file_size = 100000;	// 100K.

			if ( ! isset( $_FILES[ 'file' ][ 'error' ] ) ) {

				if ( $this->p->debug->enabled ) {
					$this->p->debug->log( 'incomplete post method upload' );
				}

				return false;

			} elseif ( $_FILES[ 'file' ][ 'error' ] === UPLOAD_ERR_NO_FILE ) {

				$this->p->notice->err( sprintf( __( 'Please select a %1$s settings file to import.',
					'wpsso' ), $dot_file_ext ) );

				return false;

			} elseif ( $_FILES[ 'file' ][ 'type' ] !== 'application/x-gzip' ) {

				$this->p->notice->err( sprintf( __( 'The %1$s settings file to import must be an "%2$s" mime type.',
					'wpsso' ), $dot_file_ext, $mime_type_gz ) );

				return false;

			} elseif ( $_FILES[ 'file' ][ 'size' ] > $max_file_size ) {	// Just in case.

				$this->p->notice->err( sprintf( __( 'The %1$s settings file is larger than the maximum of %2$d bytes allowed.',
					'wpsso' ), $dot_file_ext, $max_file_size ) );

				return false;
			}

			$gzdata = file_get_contents( $_FILES[ 'file' ][ 'tmp_name' ] );

			@unlink( $_FILES[ 'file' ][ 'tmp_name' ] );

			$opts_encoded = gzdecode( $gzdata );

			if ( empty( $opts_encoded ) ) {	// false or empty array.

				$this->p->notice->err( sprintf( __( 'The %1$s settings file is appears to be empty or corrupted.',
					'wpsso' ), $dot_file_ext ) );

				return false;
			}

			$opts = json_decode( $opts_encoded, $assoc = true );

			if ( empty( $opts ) || ! is_array( $opts ) ) {	// false or empty array.

				$this->p->notice->err( sprintf( __( 'The %1$s settings file could not be decoded into a settings array.',
					'wpsso' ), $dot_file_ext ) );

				return false;
			}

			$this->p->options = $this->p->opt->check_options( WPSSO_OPTIONS_NAME, $opts );

			$this->p->opt->save_options( WPSSO_OPTIONS_NAME, $opts, $network = false );

			$this->p->notice->upd( __( 'Import of plugin and add-on settings is complete.', 'wpsso' ) );

			return true;
		}
	}
}

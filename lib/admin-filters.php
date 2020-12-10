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

if ( ! class_exists( 'WpssoAdminFilters' ) ) {

	/**
	 * Since WPSSO Core v8.5.1.
	 */
	class WpssoAdminFilters {

		private $p;	// Wpsso class object.

		/**
		 * Instantiated by WpssoAdmin->__construct().
		 */
		public function __construct( &$plugin ) {

			$this->p =& $plugin;

			if ( $this->p->debug->enabled ) {

				$this->p->debug->mark();
			}

			$doing_ajax = SucomUtilWP::doing_ajax();

			if ( ! $doing_ajax ) {

				$this->p->util->add_plugin_filters( $this, array( 
					'status_pro_features' => 3,
					'status_std_features' => 3,
				), $prio = -10000 );
			}
		}

		/**
		 * Filter for 'wpsso_status_pro_features'.
		 */
		public function filter_status_pro_features( $features, $ext, $info ) {

			$pkg_info      = $this->p->admin->get_pkg_info();	// Returns an array from cache.
			$td_class      = $pkg_info[ $ext ][ 'pp' ] ? '' : 'blank';
			$status_on     = $pkg_info[ $ext ][ 'pp' ] ? 'on' : 'recommended';
			$apis_tab_url  = $this->p->util->get_admin_url( 'advanced#sucom-tabset_plugin-tab_apikeys' );
			$integ_tab_url = $this->p->util->get_admin_url( 'advanced#sucom-tabset_plugin-tab_integration' );

			$features[ '(feature) Enforce Image Dimension Checks' ][ 'label_url' ] = $integ_tab_url;

			$features[ '(feature) Enforce Image Dimension Checks' ][ 'status' ] = $this->p->options[ 'plugin_check_img_dims' ] ? $status_on : 'recommended';

			$features[ '(feature) Import Yoast SEO Social Meta' ][ 'label_url' ] = $integ_tab_url;

			$features[ '(feature) URL Shortening Service' ][ 'label_url' ] = $apis_tab_url;

			$features[ '(feature) Upscale Media Library Images' ][ 'label_url' ] = $integ_tab_url;

			$features[ '(feature) Use WordPress Title Filters' ] = array(
				'td_class'     => $td_class,
				'label_transl' => _x( '(feature) Use WordPress Title Filters', 'lib file description', 'wpsso' ),
				'label_url'    => $integ_tab_url,
				'status'       => $this->p->options[ 'plugin_filter_title' ] ? $status_on : 'off',
			);

			$features[ '(feature) Use WordPress Content Filters' ] = array(
				'td_class'     => $td_class,
				'label_transl' => _x( '(feature) Use WordPress Content Filters', 'lib file description', 'wpsso' ),
				'label_url'    => $integ_tab_url,
				'status'       => $this->p->options[ 'plugin_filter_content' ] ? $status_on : 'recommended',
			);

			$features[ '(feature) Use WordPress Excerpt Filters' ] = array(
				'td_class'     => $td_class,
				'label_transl' => _x( '(feature) Use WordPress Excerpt Filters', 'lib file description', 'wpsso' ),
				'label_url'    => $integ_tab_url,
				'status'       => $this->p->options[ 'plugin_filter_excerpt' ] ? $status_on : 'off',
			);

			foreach ( $this->p->cf[ 'form' ][ 'shorteners' ] as $svc_id => $name ) {

				if ( 'none' === $svc_id ) {

					continue;
				}

				$name_transl  = _x( $name, 'option value', 'wpsso' );
				$label_transl = sprintf( _x( '(api) %s Shortener API', 'lib file description', 'wpsso' ), $name_transl );
				$svc_status   = 'off';	// Off unless selected or configured.

				if ( isset( $this->p->m[ 'util' ][ 'shorten' ] ) ) {	// URL shortening service is enabled.

					if ( $svc_id === $this->p->options[ 'plugin_shortener' ] ) {	// Shortener API service ID is selected.

						$svc_status = 'recommended';	// Recommended if selected.

						if ( $this->p->m[ 'util' ][ 'shorten' ]->get_svc_instance( $svc_id ) ) {	// False or object.

							$svc_status = 'on';	// On if configured.
						}
					}
				}

				$features[ '(api) ' . $name . ' Shortener API' ] = array(
					'td_class'     => $td_class,
					'label_transl' => $label_transl,
					'label_url'    => $apis_tab_url,
					'status'       => $svc_status,
				);
			}

			return $features;
		}

		/**
		 * Filter for 'wpsso_status_std_features'.
		 */
		public function filter_status_std_features( $features, $ext, $info ) {

			if ( $this->p->debug->enabled ) {

				$this->p->debug->mark();
			}

			if ( $this->p->avail[ 'p' ][ 'schema' ] ) {

				$org_status    = 'organization' === $this->p->options[ 'site_pub_schema_type' ] ? 'on' : 'off';
				$person_status = 'person' === $this->p->options[ 'site_pub_schema_type' ] ? 'on' : 'off';
				$knowl_status  = 'on';

			} else {

				$org_status    = 'organization' === $this->p->options[ 'site_pub_schema_type' ] ? 'disabled' : 'off';
				$person_status = 'person' === $this->p->options[ 'site_pub_schema_type' ] ? 'disabled' : 'off';
				$knowl_status  = 'disabled';
			}

			$features[ '(code) Facebook / Open Graph Meta Tags' ] = array(
				'label_transl' => _x( '(code) Facebook / Open Graph Meta Tags', 'lib file description', 'wpsso' ),
				'status'       => class_exists( 'wpssoopengraph' ) ? 'on' : 'recommended',
			);

			$features[ '(code) Knowledge Graph Organization Markup' ] = array(
				'label_transl' => _x( '(code) Knowledge Graph Organization Markup', 'lib file description', 'wpsso' ),
				'status'       => $org_status,
			);

			$features[ '(code) Knowledge Graph Person Markup' ] = array(
				'label_transl' => _x( '(code) Knowledge Graph Person Markup', 'lib file description', 'wpsso' ),
				'status'       => $person_status,
			);

			$features[ '(code) Knowledge Graph WebSite Markup' ] = array(
				'label_transl' => _x( '(code) Knowledge Graph WebSite Markup', 'lib file description', 'wpsso' ),
				'status'       => $knowl_status,
			);

			$features[ '(code) Link Relation URL Tags' ] = array(
				'label_transl' => _x( '(code) Link Relation URL Tags', 'lib file description', 'wpsso' ),
				'status'       => class_exists( 'WpssoLinkRel' ) ? 'on' : 'recommended',
			);

			/**
			 * get_oembed_response_data() is available since WP v4.4.
			 */
			$features[ '(code) oEmbed Response Enhancements' ] = array(
				'label_transl' => _x( '(code) oEmbed Response Enhancements', 'lib file description', 'wpsso' ),
				'status'       => class_exists( 'WpssoOembed' ) && function_exists( 'get_oembed_response_data' ) ? 'on' : 'recommended',
			);

			$features[ '(code) Pinterest / SEO Meta Name Tags' ] = array(
				'label_transl' => _x( '(code) Pinterest / SEO Meta Name Tags', 'lib file description', 'wpsso' ),
				'status'       => class_exists( 'WpssoMetaName' ) ? 'on' : 'recommended',
			);

			$features[ '(code) Post, Term, and User Robots Meta' ] = array(
				'label_transl' => _x( '(code) Post, Term, and User Robots Meta', 'lib file description', 'wpsso' ),
				'status'       => empty( $this->p->options[ 'add_meta_name_robots' ] ) ? 'off' : 'on',
			);

			$features[ '(code) Twitter Card Meta Tags' ] = array(
				'label_transl' => _x( '(code) Twitter Card Meta Tags', 'lib file description', 'wpsso' ),
				'status'       => class_exists( 'WpssoTwitterCard' ) ? 'on' : 'recommended',
			);

			$features[ '(code) WP Sitemaps Enhancements' ] = array(
				'label_transl' => _x( '(code) WP Sitemaps Enhancements', 'lib file description', 'wpsso' ),
				'status'       => SucomUtilWP::sitemaps_enabled() ? 'on' : 'off',
			);

			return $features;
		}
	}
}

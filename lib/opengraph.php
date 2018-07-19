<?php
/**
 * License: GPLv3
 * License URI: https://www.gnu.org/licenses/gpl.txt
 * Copyright 2012-2018 Jean-Sebastien Morisset (https://wpsso.com/)
 */

if ( ! defined( 'ABSPATH' ) ) {
	die( 'These aren\'t the droids you\'re looking for...' );
}

if ( ! class_exists( 'WpssoOpenGraph' ) ) {

	class WpssoOpenGraph {

		protected $p;

		public function __construct( &$plugin ) {
			$this->p =& $plugin;

			if ( $this->p->debug->enabled ) {
				$this->p->debug->mark();
			}

			$this->p->util->add_plugin_filters( $this, array(
				'plugin_image_sizes' => 1,
			) );

			/**
			 * Hook the first available filter name (example: 'language_attributes').
			 */
			foreach ( array( 'plugin_html_attr_filter', 'plugin_head_attr_filter' ) as $opt_prefix ) {

				if ( ! empty( $this->p->options[$opt_prefix . '_name'] ) && $this->p->options[$opt_prefix . '_name'] !== 'none' ) {

					$wp_filter_name = $this->p->options[$opt_prefix . '_name'];

					add_filter( $wp_filter_name, array( $this, 'add_ogpns_attributes' ),
						 ( isset( $this->p->options[$opt_prefix . '_prio'] ) ?
						 	(int) $this->p->options[$opt_prefix . '_prio'] : 100 ), 1 );

					if ( $this->p->debug->enabled ) {
						$this->p->debug->log( 'added add_ogpns_attributes filter for ' . $wp_filter_name );
					}

					break;	// Stop here.

				} elseif ( $this->p->debug->enabled ) {
					$this->p->debug->log( 'skipping add_ogpns_attributes for ' . $opt_prefix . ' - filter name is empty or disabled' );
				}
			}
		}

		public function filter_plugin_image_sizes( $sizes ) {

			$sizes['og_img'] = array( 		// options prefix
				'name'  => 'opengraph',		// wpsso-opengraph
				'label' => _x( 'Facebook / Open Graph', 'image size label', 'wpsso' ),
			);

			return $sizes;
		}

		public function add_ogpns_attributes( $html_attr ) {

			if ( $this->p->debug->enabled ) {
				$this->p->debug->log_args( array (
					'html_attr' => $html_attr,
				) );
			}

			$use_post = apply_filters( $this->p->lca . '_use_post', false );	// Used by woocommerce with is_shop().

			if ( $this->p->debug->enabled ) {
				$this->p->debug->log( 'required call to get_page_mod()' );
			}

			$mod = $this->p->util->get_page_mod( $use_post );

			$og_type_id   = $this->get_mod_og_type( $mod );

			$og_ns = array(
				'og' => 'http://ogp.me/ns#',
				'fb' => 'http://ogp.me/ns/fb#',
			);

			/**
			 * Check that the og_type is known and add it's namespace value.
			 *
			 * Example: article, place, product, website, etc.
			 */
			if ( ! empty( $this->p->cf['head']['og_type_ns'][$og_type_id] ) ) {
				$og_ns[$og_type_id] = $this->p->cf['head']['og_type_ns'][$og_type_id];
			}

			$og_ns = apply_filters( $this->p->lca . '_og_ns', $og_ns, $mod );

			if ( SucomUtil::is_amp() ) {

				/**
				 * Nothing to do.
				 */

			} else {

				$html_attr = ' ' . $html_attr;	// Prepare the string for testing.

				/**
				 * Find and extract an existing prefix attribute value (if any).
				 */
				if ( strpos( $html_attr, ' prefix=' ) &&
					preg_match( '/^(.*) prefix=["\']([^"\']*)["\'](.*)$/', $html_attr, $match ) ) {

					$html_attr    = $match[1] . $match[3];	// Remove the prefix.
					$prefix_value = ' ' . $match[2];

				} else {
					$prefix_value = '';
				}
	
				foreach ( $og_ns as $name => $url ) {
					if ( strpos( $prefix_value, ' ' . $name . ': ' . $url ) === false ) {
						$prefix_value .= ' ' . $name . ': ' . $url;
					}
				}
	
				$html_attr .= ' prefix="' . trim( $prefix_value ) . '"';
			}

			return trim( $html_attr );
		}

		/**
		 * Returns the open graph type.
		 *
		 * Example: article, product, place, etc.
		 */
		public function get_mod_og_type( array $mod, $ret_type_ns = false, $use_mod_opts = true ) {

			if ( $this->p->debug->enabled ) {
				$this->p->debug->mark();
			}

			$default_key = apply_filters( $this->p->lca . '_og_type_for_default', 'website' );
			$og_type_ns  = $this->p->cf['head']['og_type_ns'];
			$og_type_id  = null;

			/**
			 * Get custom open graph type from post, term, or user meta.
			 */
			if ( $use_mod_opts ) {

				if ( ! empty( $mod['obj'] ) ) {	// Just in case.

					$og_type_id = $mod['obj']->get_options( $mod['id'], 'og_type' );	// Returns null if an index key is not found.

					if ( empty( $og_type_id ) ) {	// Must be a non-empty string.
						if ( $this->p->debug->enabled ) {
							$this->p->debug->log( 'custom type id from meta is empty' );
						}
					} elseif ( $og_type_id === 'none' ) {
						if ( $this->p->debug->enabled ) {
							$this->p->debug->log( 'custom type id is disabled with value none' );
						}
					} elseif ( empty( $og_type_ns[$og_type_id] ) ) {
						if ( $this->p->debug->enabled ) {
							$this->p->debug->log( 'custom type id "' . $og_type_id . '" not in og types' );
						}
						$og_type_id = null;
					} elseif ( $this->p->debug->enabled ) {
						$this->p->debug->log( 'custom type id "' . $og_type_id . '" from ' . $mod['name'] . ' meta' );
					}

				} elseif ( $this->p->debug->enabled ) {
					$this->p->debug->log( 'skipping custom type_id - mod object is empty' );
				}

			} elseif ( $this->p->debug->enabled ) {
				$this->p->debug->log( 'skipping custom type_id - use_mod_opts is false' );
			}

			if ( empty( $og_type_id ) ) {
				$is_custom = false;
			} else {
				$is_custom = true;
			}

			if ( empty( $og_type_id ) ) {	// If no custom of type, then use the default settings.

				if ( $this->p->debug->enabled ) {
					$this->p->debug->log( 'using plugin settings to determine og type' );
				}

				/**
				 * An index or static home page is 'website' by default.
				 */
				if ( $mod['is_home'] ) {
	
					$og_type_id = $default_key;
	
					if ( $mod['is_home_page'] ) {
	
						$og_type_id = apply_filters( $this->p->lca . '_og_type_for_home_page', $og_type_id, $mod );
	
						if ( $this->p->debug->enabled ) {
							$this->p->debug->log( 'using og type id "' . $og_type_id . '" for home page' );
						}
	
					} else {
	
						$og_type_id = apply_filters( $this->p->lca . '_og_type_for_home_index', $og_type_id, $mod );
	
						if ( $this->p->debug->enabled ) {
							$this->p->debug->log( 'using og type id "' . $og_type_id . '" for home index' );
						}
					}
	
				/**
				 * Singular posts / pages are articles by default.
				 */
				} elseif ( $mod['is_post'] ) {
	
					$og_type_id = empty( $this->p->options['og_post_type'] ) ?
						'article' : $this->p->options['og_post_type'];
	
					if ( ! empty( $mod['post_type'] ) ) {
	
						if ( empty( $mod['id'] ) && is_post_type_archive() ) {
	
							$og_type_id = $default_key;
	
							if ( $this->p->debug->enabled ) {
								$this->p->debug->log( 'using og type id "' . $og_type_id . '" for post type archive page' );
							}

						} elseif ( isset( $og_type_ns[ $mod[ 'post_type' ] ] ) ) {
	
							$og_type_id = $mod['post_type'];

							if ( $this->p->debug->enabled ) {
								$this->p->debug->log( 'using og type id "' . $og_type_id . '" from post type name' );
							}

						} else {
							if ( $this->p->debug->enabled ) {
								$this->p->debug->log( 'post type name is not a valid og type id' );
							}
						}
					}
	
				/**
				 * Default for everything else is 'website'.
				 */
				} else {

					$og_type_id = $default_key;

					if ( $this->p->debug->enabled ) {
						$this->p->debug->log( 'using default og type id "' . $default_key . '"' );
					}
				}
			}

			if ( $this->p->debug->enabled ) {
				$this->p->debug->log( 'og type id before filter is "' . $og_type_id . '"' );
			}

			$og_type_id = apply_filters( $this->p->lca . '_og_type', $og_type_id, $mod, $is_custom );

			if ( $this->p->debug->enabled ) {
				$this->p->debug->log( 'og type id after filter is "' . $og_type_id . '"' );
			}

			return $og_type_id;
		}

		public function get_og_types_select( $add_none = true ) {

			if ( $this->p->debug->enabled ) {
				$this->p->debug->mark();
			}

			$og_type_ids = $this->p->cf['head']['og_type_ns_compat'];

			$select = array();

			foreach ( $og_type_ids as $og_type_id => $type_ns ) {

				$type_ns = preg_replace( '/(^.*\/\/|#$)/', '', $type_ns );

				$select[ $og_type_id ] = $og_type_id . ' | ' . $type_ns;
			}

			if ( defined( 'SORT_STRING' ) ) {	// Just in case.
				asort( $select, SORT_STRING );
			} else {
				asort( $select );
			}

			if ( $add_none ) {
				return array_merge( array( 'none' => '[None]' ), $select );
			} else {
				return $select;
			}
		}

		public function get_array( array $mod, array $mt_og, $crawler_name = false ) {

			if ( $this->p->debug->enabled ) {
				$this->p->debug->mark();
			}

			if ( false === $crawler_name ) {
				$crawler_name = SucomUtil::get_crawler_name();
			}

			$pdir        = $this->p->avail['*']['p_dir'];
			$aop         = $this->p->check->aop( $this->p->lca, true, $pdir );
			$max_nums    = $this->p->util->get_max_nums( $mod );
			$post_id     = $mod['is_post'] ? $mod['id'] : false;
			$check_dupes = true;
			$prev_count  = 0;
			$mt_og       = apply_filters( $this->p->lca . '_og_seed', $mt_og, $mod );

			if ( ! empty( $mt_og ) ) {
				if ( $this->p->debug->enabled ) {
					$this->p->debug->log( $this->p->lca . '_og_seed filter returned:' );
					$this->p->debug->log( $mt_og );
				}
			}

			if ( ! isset( $mt_og['fb:admins'] ) ) {
				if ( ! empty( $this->p->options['fb_admins'] ) ) {
					foreach ( explode( ',', $this->p->options['fb_admins'] ) as $fb_admin ) {
						$mt_og['fb:admins'][] = trim( $fb_admin );
					}
				}
			}

			if ( ! isset( $mt_og['fb:app_id'] ) ) {
				$mt_og['fb:app_id'] = $this->p->options['fb_app_id'];
			}

			if ( ! isset( $mt_og['og:type'] ) ) {
				$mt_og['og:type'] = $this->get_mod_og_type( $mod );
			} elseif ( $this->p->debug->enabled ) {
				$this->p->debug->log( 'og:type already defined = ' . $mt_og['og:type'] );
			}

			if ( ! isset( $mt_og['og:url'] ) ) {
				$mt_og['og:url'] = $this->p->util->get_sharing_url( $mod );
			} elseif ( $this->p->debug->enabled ) {
				$this->p->debug->log( 'og:url already defined = ' . $mt_og['og:url'] );
			}

			if ( ! isset( $mt_og['og:locale'] ) ) {
				$mt_og['og:locale'] = $this->get_fb_locale( $this->p->options, $mod );	// localized
			} elseif ( $this->p->debug->enabled ) {
				$this->p->debug->log( 'og:locale already defined = ' . $mt_og['og:locale'] );
			}

			if ( ! isset( $mt_og['og:site_name'] ) ) {
				if ( $this->p->debug->enabled ) {
					$this->p->debug->log( 'getting site name for og:site_name meta tag' );
				}
				$mt_og['og:site_name'] = SucomUtil::get_site_name( $this->p->options, $mod );	// localized
			} elseif ( $this->p->debug->enabled ) {
				$this->p->debug->log( 'og:site_name already defined = ' . $mt_og['og:site_name'] );
			}

			if ( ! isset( $mt_og['og:title'] ) ) {
				if ( $this->p->debug->enabled ) {
					$this->p->debug->log( 'getting title for og:title meta tag' );
				}
				$max_len = $this->p->options['og_title_len'];
				$mt_og['og:title'] = $this->p->page->get_title( $max_len, '...', $mod );
				if ( $this->p->debug->enabled ) {
					$this->p->debug->log( 'og:title value = ' . $mt_og['og:title'] );
				}
			} elseif ( $this->p->debug->enabled ) {
				$this->p->debug->log( 'og:title already defined = ' . $mt_og['og:title'] );
			}

			if ( ! isset( $mt_og['og:description'] ) ) {
				if ( $this->p->debug->enabled ) {
					$this->p->debug->log( 'getting description for og:description meta tag' );
				}
				$max_len = $this->p->options['og_desc_len'];
				$add_htags = $this->p->options['og_desc_hashtags'];
				$mt_og['og:description'] = $this->p->page->get_description( $max_len, '...', $mod, true, $add_htags );
				if ( $this->p->debug->enabled ) {
					$this->p->debug->log( 'og:description value = ' . $mt_og['og:description'] );
				}
			} elseif ( $this->p->debug->enabled ) {
				$this->p->debug->log( 'og:description already defined = ' . $mt_og['og:description'] );
			}

			if ( ! isset( $mt_og['og:updated_time'] ) ) {
				if ( $mod['is_post'] && $post_id ) {
					$mt_og['og:updated_time'] = trim( get_post_modified_time( 'c', true, $post_id ) );	// $gmt is true.
				}
			}

			/**
			 * Get all videos.
			 *
			 * Call before getting all images to find / use preview images.
			 */
			if ( ! isset( $mt_og['og:video'] ) && $aop ) {

				if ( empty( $max_nums['og_vid_max'] ) ) {
					if ( $this->p->debug->enabled ) {
						$this->p->debug->log( 'videos disabled: maximum videos = 0' );
					}
				} else {
					if ( $this->p->debug->enabled ) {
						$this->p->debug->log( 'getting videos for og:video meta tag' );
					}

					$mt_og['og:video'] = $this->get_all_videos( $max_nums['og_vid_max'], $mod, $check_dupes, 'og' );

					if ( $this->p->debug->enabled ) {
						$this->p->debug->log( 'checking for video preview images' );
					}

					if ( ! empty( $mt_og['og:video'] ) && is_array( $mt_og['og:video'] ) ) {

						foreach ( $mt_og['og:video'] as $num => $og_single_video ) {

							$image_url = SucomUtil::get_mt_media_url( $og_single_video );

							/**
							 * Check preview images for duplicates since the same videos may be available in
							 * different formats (application/x-shockwave-flash and text/html for example).
							 */
							if ( $image_url && $this->p->util->is_uniq_url( $image_url, 'preview' ) ) {
								$mt_og['og:video'][$num]['og:video:has_image'] = true;
								$prev_count++;
							} else {
								$mt_og['og:video'][$num]['og:video:has_image'] = false;
							}
						}

						if ( $prev_count > 0 ) {

							$max_nums['og_img_max'] -= $prev_count;

							if ( $this->p->debug->enabled ) {
								$this->p->debug->log( $prev_count . ' video preview images found ' . 
									'(og_img_max adjusted to ' . $max_nums['og_img_max'] . ')' );
							}

						} elseif ( $this->p->debug->enabled ) {
							$this->p->debug->log( 'no video preview images found' );
						}
					}
				}
			}

			/**
			 * Get all images.
			 */
			if ( ! isset( $mt_og['og:image'] ) ) {

				if ( empty( $max_nums['og_img_max'] ) ) {

					if ( $this->p->debug->enabled ) {
						$this->p->debug->log( 'images disabled: maximum images = 0' );
					}

				} else {

					$img_sizes = array( 'og' => $this->p->lca . '-opengraph' );

					foreach ( $img_sizes as $md_pre => $size_name ) {

						if ( $this->p->debug->enabled ) {
							$this->p->debug->log( 'getting images for ' . $md_pre . ' (' . $size_name . ')' );
						}

						/**
						 * The size_name is used as a context for duplicate checks.
						 */
						$mt_og[$md_pre . ':image'] = $this->get_all_images( $max_nums['og_img_max'], $size_name, $mod, $check_dupes, $md_pre );

						/**
						 * If there's no image, and no video preview, then add the default image for singular (aka post) webpages.
						 */
						if ( empty( $mt_og[$md_pre . ':image'] ) && ! $prev_count && $mod['is_post'] ) {

							if ( $this->p->debug->enabled ) {
								$this->p->debug->log( 'getting default image for ' . $md_pre . ' (' . $size_name . ')' );
							}

							$mt_og[$md_pre . ':image'] = $this->p->media->get_default_images( $max_nums['og_img_max'], $size_name, $check_dupes );
						}
					}
				}
			}

			/**
			 * Pre-define some basic open graph meta tags for this og:type. If the meta tag
			 * has an associated meta option name, then read it's value from the meta options.
			 */
			if ( $this->p->debug->enabled ) {
				$this->p->debug->log( 'checking og_type_mt array for known meta tags and md options' );
			}

			$og_type_id = $mt_og['og:type'];

			if ( isset( $this->p->cf['head']['og_type_mt'][$og_type_id] ) ) {	// Check if og:type is in config.

				$og_type_mt_md = $this->p->cf['head']['og_type_mt'][$og_type_id];

				/**
				 * Optimize and call get_options() only once. Returns an empty string if no meta found.
				 */
				$md_opts = empty( $mod['obj'] ) ? array() : (array) $mod['obj']->get_options( $mod['id'] );

				foreach ( $og_type_mt_md as $mt_name => $md_idx ) {

					/**
					 * Use a custom value if one is available - ignore empty strings and 'none'.
					 */
					if ( $md_idx && isset( $md_opts[$md_idx] ) && $md_opts[$md_idx] !== '' ) {

						if ( $md_opts[$md_idx] === 'none' ) {

							if ( $this->p->debug->enabled ) {
								$this->p->debug->log( $md_idx . ' option is "none" - unsetting ' . $mt_name . ' meta tag' );
							}

							unset( $mt_og[$mt_name] );

						} else {

							if ( $this->p->debug->enabled ) {
								$this->p->debug->log( $og_type_id . ' meta tag ' . $mt_name . ' from option = ' . $md_opts[$md_idx] );
							}

							$mt_og[$mt_name] = $md_opts[$md_idx];
						}

					} elseif ( isset( $mt_og[$mt_name] ) ) {	// if the meta tag has not already been set

						if ( $this->p->debug->enabled ) {
							$this->p->debug->log( $og_type_id . ' meta tag ' . $mt_name . ' value kept = ' . $mt_og[$mt_name] );
						}

					} else {

						if ( $this->p->debug->enabled ) {
							$this->p->debug->log( $og_type_id . ' meta tag ' . $mt_name . ' defined as null' );
						}

						$mt_og[$mt_name] = null;	// use null so isset() returns false
					}
				}

				if ( isset( $mt_og['product:price:amount'] ) ) {
					if ( is_numeric( $mt_og['product:price:amount'] ) ) {	// allow for price of 0
						if ( empty( $mt_og['product:price:currency'] ) ) {
							$mt_og['product:price:currency'] = $this->p->options['plugin_def_currency'];
						}
					} else {
						if ( $this->p->debug->enabled ) {
							$this->p->debug->log( 'product price amount must be numeric' );
						}
						unset( $mt_og['product:price:amount'] );
						unset( $mt_og['product:price:currency'] );
					}
				}
			}

			/**
			 * If the module is a post object, define the author, publishing date, etc.
			 * These values may still be used by other filters, and if the og:type is
			 * not an article, the meta tags will be sanitized at the end of
			 * WpssoHead::get_head_array().
			 */
			if ( $mod['is_post'] && $post_id ) {

				if ( ! isset( $mt_og['article:author'] ) ) {

					if ( $mod['is_post'] && isset( $this->p->m['util']['user'] ) ) {

						if ( $this->p->debug->enabled ) {
							$this->p->debug->log( 'getting names / urls for article:author meta tags' );
						}

						$user_mod =& $this->p->m['util']['user'];

						if ( $mod['post_author'] ) {
							$mt_og['article:author']      = $user_mod->get_og_profile_urls( $mod['post_author'], $crawler_name );
							$mt_og['article:author:name'] = $user_mod->get_author_meta( $mod['post_author'],
								$this->p->options['fb_author_name'] );
						} else {
							$mt_og['article:author'] = array();
						}

						if ( ! empty( $mod['post_coauthors'] ) ) {
							$mt_og['article:author'] = array_merge( $mt_og['article:author'],
								$user_mod->get_og_profile_urls( $mod['post_coauthors'], $crawler_name ) );
						}
					}
				}

				if ( ! isset( $mt_og['article:publisher'] ) ) {
					$mt_og['article:publisher'] = SucomUtil::get_key_value( 'fb_publisher_url', $this->p->options, $mod );
				}

				if ( ! isset( $mt_og['article:tag'] ) ) {
					$mt_og['article:tag'] = $this->p->page->get_tags( $post_id );
				}

				if ( ! isset( $mt_og['article:section'] ) ) {
					$mt_og['article:section'] = $this->p->page->get_article_section( $post_id );
				}

				if ( ! isset( $mt_og['article:published_time'] ) ) {
					if ( $mod['post_status'] === 'publish' ) {	// Must be published to have publish time.
						$mt_og['article:published_time'] = trim( get_post_time( 'c', true, $post_id ) );	// $gmt is true.
					}
				}

				if ( ! isset( $mt_og['article:modified_time'] ) ) {
					$mt_og['article:modified_time'] = trim( get_post_modified_time( 'c', true, $post_id ) );	// $gmt is true.
				}
			}

			return (array) apply_filters( $this->p->lca . '_og', $mt_og, $mod );
		}

		/**
		 * Unset mis-matched og_type meta tags using the 'og_type_mt' array as a reference.
		 * For example, remove all 'article' meta tags if the og_type is 'website'. Removing
		 * only known meta tags (using the 'og_type_mt' array as a reference) protects
		 * internal meta tags that may be used later by WpssoHead::extract_head_info().
		 * For example, the schema:type:id and p:image meta tags.
		 *
		 * The 'og_content_map' array is also checked for Schema values that need to be
		 * swapped for simpler Open Graph meta tag values.
		 *
		 * Called by WpssoHead::get_head_array() before merging all meta tag arrays.
		 */
		public function sanitize_array( array $mod, array $mt_og ) {

			if ( $this->p->debug->enabled ) {
				$this->p->debug->mark();
			}

			if ( empty( $mt_og['og:type'] ) ) {
				if ( $this->p->debug->enabled ) {
					$this->p->debug->log( 'og:type is empty and required for sanitation' );
				}
				return $mt_og;
			}

			foreach ( $this->p->cf['head']['og_type_mt'] as $og_type_id => $og_type_mt_md ) {

				foreach ( $og_type_mt_md as $mt_name => $md_idx ) {

					if ( isset( $mt_og[$mt_name] ) ) {

						if (  $og_type_id !== $mt_og['og:type'] ) {	// Mis-matched meta tag for this og:type

							if ( $this->p->debug->enabled ) {
								$this->p->debug->log( 'removing extra meta tag ' . $mt_name );
							}

							unset( $mt_og[$mt_name] );

						} elseif ( isset( $this->p->cf['head']['og_content_map'][$mt_name][$mt_og[$mt_name]] ) ) {

							if ( $this->p->debug->enabled ) {
								$this->p->debug->log( 'mapping content value for ' . $mt_name );
							}

							$mt_og[$mt_name] = $this->p->cf['head']['og_content_map'][$mt_name][$mt_og[$mt_name]];
						}
					}
				}
			}

			return $mt_og;
		}

		public function get_all_videos( $num = 0, array $mod, $check_dupes = true, $md_pre = 'og', $force_prev = false ) {

			if ( $this->p->debug->enabled ) {
				$this->p->debug->log_args( array(
					'num'         => $num,
					'mod'         => $mod,
					'check_dupes' => $check_dupes,
					'md_pre'      => $md_pre,
					'force_prev'  => $force_prev,
				) );
			}

			$og_ret   = array();
			$pdir     = $this->p->avail['*']['p_dir'];
			$aop      = $this->p->check->aop( $this->p->lca, true, $pdir );
			$use_prev = $this->p->options['og_vid_prev_img'];		// default option value is true/false
			$num_diff = SucomUtil::count_diff( $og_ret, $num );

			$this->p->util->clear_uniq_urls( array( 'video', 'content_video', 'video_info' ) );

			/**
			 * Get video information and preview enable/disable option from the post/term/user meta.
			 */
			if ( $aop && ! empty( $mod['obj'] ) ) {

				/**
				 * Note that get_options() returns null if an index key is not found.
				 */
				if ( ( $mod_prev = $mod['obj']->get_options( $mod['id'], 'og_vid_prev_img' ) ) !== null ) {

					$use_prev = $mod_prev;	// use true/false/1/0 value from the custom option

					if ( $this->p->debug->enabled ) {
						$this->p->debug->log( 'setting use_prev to '.( empty( $use_prev ) ? 'false' : 'true' ).' from meta data' );
					}
				}

				$og_ret = array_merge( $og_ret, $mod['obj']->get_og_videos( $num_diff, $mod['id'], $check_dupes, $md_pre ) );
			}

			$num_diff = SucomUtil::count_diff( $og_ret, $num );

			/**
			 * Optionally get more videos from the post content.
			 */
			if ( $mod['is_post'] && ! $this->p->util->is_maxed( $og_ret, $num ) ) {
				$og_ret = array_merge( $og_ret, $this->p->media->get_content_videos( $num_diff, $mod, $check_dupes ) );
			}

			$this->p->util->slice_max( $og_ret, $num );

			/**
			 * Optionally remove the image meta tags (aka video preview).
			 */
			if ( empty( $use_prev ) && empty( $force_prev ) ) {

				if ( $this->p->debug->enabled ) {
					$this->p->debug->log( 'use_prev and force_prev are false - removing video preview images' );
				}

				foreach ( $og_ret as $num => $og_single_video ) {
					foreach ( SucomUtil::preg_grep_keys( '/^og:image(:.*)?$/', $og_single_video ) as $k => $v ) {
						unset ( $og_ret[$num][$k] );
					}
					$og_ret[$num]['og:video:has_image'] = false;
				}
			}

			/**
			 * Get custom video information from post/term/user meta data for FIRST video.
			 *
			 * If $md_pre is 'none' (special index keyword), then don't load any custom video information.
			 * The og:video:title and og:video:description meta tags are not standard and their values will
			 * only appear in Schema markup.
			 */
			if ( $aop && ! empty( $mod['obj'] ) && $md_pre !== 'none' ) {

				foreach ( $og_ret as $num => $og_single_video ) {

					foreach ( array(
						'og_vid_width'  => 'og:video:width',
						'og_vid_height' => 'og:video:height',
						'og_vid_title'  => 'og:video:title',
						'og_vid_desc'   => 'og:video:description',
					) as $idx => $mt_name ) {
	
						/**
						 * Note that get_options() returns null if an index key is not found.
						 */
						$value = $mod['obj']->get_options( $mod['id'], $idx );
	
						if ( ! empty( $value ) ) {	// must be a non-empty string
							$og_ret[$num][$mt_name] = $value;
						}
					}

					break;	// Only do the first video.
				}
			}

			if ( ! empty( $this->p->options['og_vid_html_type'] ) ) {

				$og_extend = array();

				foreach ( $og_ret as $num => $og_single_video ) {

					if ( ! empty( $og_single_video['og:video:embed_url'] ) ) {

						/**
						 * Start with a fresh copy of all og meta tags.
						 */
						$og_single_embed = SucomUtil::get_mt_video_seed( 'og', $og_single_video, false );

						/**
						 * Exclude the facebook applink meta tags.
						 */
						$og_single_embed = SucomUtil::preg_grep_keys( '/^og:/', $og_single_embed );

						if ( strpos( $og_single_video['og:video:embed_url'], 'https:' ) !== false ) {

							if ( ! empty( $this->p->options['add_meta_property_og:video:secure_url'] ) ) {
								$og_single_embed['og:video:secure_url'] = $og_single_video['og:video:embed_url'];
							} else {
								$og_single_embed['og:video:secure_url'] = '';	// Just in case.
							}
						}

						$og_single_embed['og:video:url'] = $og_single_video['og:video:embed_url'];

						$og_single_embed['og:video:type'] = 'text/html';

						/**
						 * Embedded videos may not have width / height information defined.
						 */
						foreach ( array( 'og:video:width', 'og:video:height' ) as $mt_name ) {
							if ( isset( $og_single_embed[$mt_name] ) && $og_single_embed[$mt_name] === '' ) {
								unset( $og_single_embed[$mt_name] );
							}
						}

						/**
						 * Add application/x-shockwave-flash video first and the text/html video second.
						 */
						if ( SucomUtil::get_mt_media_url( $og_single_video, 'og:video', array( ':secure_url', ':url', '' ) ) ) {
							$og_extend[] = $og_single_video;
						}

						$og_extend[] = $og_single_embed;

					} else {
						$og_extend[] = $og_single_video;
					}
				}

				return $og_extend;

			} else {
				return $og_ret;
			}
		}

		public function get_all_images( $num = 0, $size_name = 'thumbnail', array $mod, $check_dupes = true, $md_pre = 'og' ) {

			if ( $this->p->debug->enabled ) {
				$this->p->debug->log_args( array(
					'num'         => $num,
					'size_name'   => $size_name,
					'mod'         => $mod,
					'check_dupes' => $check_dupes,
					'md_pre'      => $md_pre,
				) );
			}

			$og_ret      = array();
			$num_diff    = SucomUtil::count_diff( $og_ret, $num );
			$force_regen = $this->p->util->is_force_regen( $mod, $md_pre );	// false by default

			$this->p->util->clear_uniq_urls( $size_name );			// clear cache for $size_name context

			if ( $mod['is_post'] ) {

				if ( $mod['post_type'] === 'attachment' && wp_attachment_is_image( $mod['id'] ) ) {

					$og_single_image = $this->p->media->get_attachment_image( $num_diff, $size_name, $mod['id'], $check_dupes );

					if ( empty( $og_single_image ) ) {

						if ( $this->p->debug->enabled ) {
							$this->p->debug->log( 'exiting early: no attachment image - returning default image' );
						}

						return array_merge( $og_ret, $this->p->media->get_default_images( $num_diff,
							$size_name, $check_dupes, $force_regen ) );

					} else {
						return array_merge( $og_ret, $og_single_image );
					}
				}

				/**
				 * Check for custom meta, featured, or attached image(s).
				 * Allow for empty post id in order to execute featured / attached image filters for modules.
				 */
				if ( ! $this->p->util->is_maxed( $og_ret, $num ) ) {
					$og_ret = array_merge( $og_ret, $this->p->media->get_post_images( $num_diff,
						$size_name, $mod['id'], $check_dupes, $md_pre ) );
				}

				/**
				 * Check for NGG query variables and shortcodes.
				 */
				if ( ! empty( $this->p->m['media']['ngg'] ) && ! $this->p->util->is_maxed( $og_ret, $num ) ) {

					if ( $this->p->debug->enabled ) {
						$this->p->debug->log( 'checking for NGG query variables and shortcodes' );
					}

					$num_diff = SucomUtil::count_diff( $og_ret, $num );

					$ngg_obj =& $this->p->m['media']['ngg'];

					$query_images = $ngg_obj->get_query_og_images( $num_diff, $size_name, $mod['id'], $check_dupes );

					if ( count( $query_images ) > 0 ) {

						if ( $this->p->debug->enabled ) {
							$this->p->debug->log( 'skipping NGG shortcode check - '.count( $query_images ).' query image(s) returned' );
						}

						$og_ret = array_merge( $og_ret, $query_images );

					} elseif ( ! $this->p->util->is_maxed( $og_ret, $num ) ) {

						$num_diff = SucomUtil::count_diff( $og_ret, $num );

						$shortcode_images = $ngg_obj->get_shortcode_og_images( $num_diff, $size_name, $mod['id'], $check_dupes );

						if ( ! empty( $shortcode_images ) ) {
							$og_ret = array_merge( $og_ret, $shortcode_images );
						}
					}

				}

				/**
				 * If we haven't reached the limit of images yet, keep going and check the content text.
				 */
				if ( ! $this->p->util->is_maxed( $og_ret, $num ) ) {

					if ( $this->p->debug->enabled ) {
						$this->p->debug->log( 'checking the content text for images' );
					}

					$num_diff       = SucomUtil::count_diff( $og_ret, $num );
					$content_images = $this->p->media->get_content_images( $num_diff, $size_name, $mod, $check_dupes, $force_regen );

					if ( ! empty( $content_images ) ) {
						$og_ret = array_merge( $og_ret, $content_images );
					}
				}

			} else {

				/**
				 * get_og_images() also provides filter hooks for additional image ids and urls.
				 */
				if ( ! empty( $mod['obj'] ) ) {	// Term or user.

					$og_images = $mod['obj']->get_og_images( $num_diff, $size_name, $mod['id'], $check_dupes, $force_regen, $md_pre );

					if ( ! empty( $og_images ) ) {
						$og_ret = array_merge( $og_ret, $og_images );
					}
				}

				if ( empty( $og_ret ) ) {
					$og_ret = array_merge( $og_ret, $this->p->media->get_default_images( $num_diff,
						$size_name, $check_dupes, $force_regen ) );

				}
			}

			$this->p->util->slice_max( $og_ret, $num );

			return $og_ret;
		}

		/**
		 * The returned array can include a varying number of elements, depending on the $request value.
		 */
		public function get_media_info( $size_name, array $request, array $mod, $md_pre = 'og', $mt_pre = 'og' ) {

			if ( $this->p->debug->enabled ) {
				$this->p->debug->mark();
			}

			$ret       = array();
			$pdir      = $this->p->avail['*']['p_dir'];
			$aop       = $this->p->check->aop( $this->p->lca, true, $pdir );
			$og_images = null;
			$og_videos = null;

			foreach ( $request as $key ) {

				switch ( $key ) {

					case 'pid':
					case ( preg_match( '/^(image|img)/', $key ) ? true : false ):

						if ( null === $og_images ) {	// get images only once
							$og_images = $this->get_all_images( 1, $size_name, $mod, false, $md_pre );
						}

						break;

					case ( preg_match( '/^(vid|prev)/', $key ) ? true : false ):

						if ( null === $og_videos && $aop ) {	// get videos only once
							$og_videos = $this->get_all_videos( 1, $mod, false, $md_pre );	// $check_dupes = false
						}

						break;
				}
			}

			foreach ( $request as $key ) {

				switch ( $key ) {

					case 'pid':

						if ( ! isset( $get_mt_name ) ) {
							$get_mt_name = $mt_pre.':image:id';
						}

						// no break - fall through

					case 'image':
					case 'img_url':

						if ( ! isset( $get_mt_name ) ) {
							$get_mt_name = $mt_pre . ':image';
						}

						// no break - fall through

						if ( $og_videos !== null ) {
							$ret[$key] = $this->get_media_value( $og_videos, $get_mt_name );
						}

						if ( empty( $ret[$key] ) ) {
							$ret[$key] = $this->get_media_value( $og_images, $get_mt_name );
						}

						/**
						 * If there's no image, and no video preview image, then add
						 * the default image for singular (aka post) webpages.
						 */
						if ( empty( $ret[$key] ) && $mod['is_post'] ) {
							$og_images = $this->p->media->get_default_images( 1, $size_name, false );	// $check_dupes is false.
							$ret[$key] = $this->get_media_value( $og_images, $get_mt_name );
						}

						break;

					case 'img_alt':

						$ret[$key] = $this->get_media_value( $og_images, $mt_pre.':image:alt' );

						break;

					case 'video':
					case 'vid_url':

						$ret[$key] = $this->get_media_value( $og_videos, $mt_pre.':video' );

						break;

					case 'vid_type':

						$ret[$key] = $this->get_media_value( $og_videos, $mt_pre.':video:type' );

						break;

					case 'vid_title':

						$ret[$key] = $this->get_media_value( $og_videos, $mt_pre.':video:title' );

						break;

					case 'vid_desc':

						$ret[$key] = $this->get_media_value( $og_videos, $mt_pre.':video:description' );

						break;

					case 'vid_width':

						$ret[$key] = $this->get_media_value( $og_videos, $mt_pre.':video:width' );

						break;

					case 'vid_height':

						$ret[$key] = $this->get_media_value( $og_videos, $mt_pre.':video:height' );

						break;

					case 'prev_url':
					case 'preview':

						$ret[$key] = $this->get_media_value( $og_videos, $mt_pre.':video:thumbnail_url' );

						break;

					default:

						$ret[$key] = '';

						break;
				}

				unset( $get_mt_name );
			}

			if ( $this->p->debug->enabled ) {
				$this->p->debug->log( $ret );
			}

			return $ret;
		}

		public function get_media_value( $mt_og, $mt_media_pre ) {

			if ( empty( $mt_og ) || ! is_array( $mt_og ) ) {
				return '';
			}

			$og_media = reset( $mt_og );	// only search the first media array

			switch ( $mt_media_pre ) {

				/**
				 * If we're asking for an image or video url, then search all three values sequentially.
				 */
				case ( preg_match( '/:(image|video)(:secure_url|:url)?$/', $mt_media_pre ) ? true : false ):

					$mt_search = array(
						$mt_media_pre . ':secure_url',	// og:image:secure_url
						$mt_media_pre . ':url',		// og:image:url
						$mt_media_pre,		// og:image
					);

					break;

				/**
				 * Otherwise, only search for that specific meta tag name.
				 */
				default:

					$mt_search = array( $mt_media_pre );

					break;
			}

			foreach ( $mt_search as $key ) {

				if ( ! isset( $og_media[$key] ) ) {

					continue;

				} elseif ( $og_media[$key] === '' || $og_media[$key] === null ) {	// Allow for 0.

					if ( $this->p->debug->enabled ) {
						$this->p->debug->log( $og_media[$key].' value is empty (skipped)' );
					}

				} elseif ( $og_media[$key] === WPSSO_UNDEF_INT || $og_media[$key] === (string) WPSSO_UNDEF_INT ) {

					if ( $this->p->debug->enabled ) {
						$this->p->debug->log( $og_media[$key].' value is '.WPSSO_UNDEF_INT.' (skipped)' );
					}

				} else {
					return $og_media[$key];
				}
			}

			return '';
		}

		/**
		 * Returns an optional and customized locale value for the og:locale meta tag.
		 *
		 * $mixed = 'default' | 'current' | post ID | $mod array
		 */
		public function get_fb_locale( array $opts, $mixed = 'current' ) {

			// check for customized locale
			if ( ! empty( $opts ) ) {
				$fb_locale_key = SucomUtil::get_key_locale( 'fb_locale', $opts, $mixed );
				if ( ! empty( $opts[$fb_locale_key] ) ) {
					if ( $this->p->debug->enabled ) {
						$this->p->debug->log( 'returning '.$fb_locale_key.' value from options: '.$opts[$fb_locale_key] );
					}
					return $opts[$fb_locale_key];
				}
			}

			$locale = SucomUtil::get_locale( $mixed );
			$def_locale = SucomUtil::get_locale( 'default' );
			$fb_pub_lang = SucomUtil::get_pub_lang( 'facebook' );

			// exceptions
			switch ( $locale ) {
				case 'de_DE_formal':
					$locale = 'de_DE';
					break;
			}

			if ( ! empty( $fb_pub_lang[$locale] ) ) {
				if ( $this->p->debug->enabled ) {
					$this->p->debug->log( 'returning valid facebook locale: '.$locale );
				}
				return $locale;
			} elseif ( ! empty( $fb_pub_lang[$def_locale] ) ) {
				if ( $this->p->debug->enabled ) {
					$this->p->debug->log( 'returning default locale: '.$def_locale );
				}
				return $def_locale;
			} else {
				if ( $this->p->debug->enabled ) {
					$this->p->debug->log( 'returning fallback locale: en_US' );
				}
				return 'en_US';
			}
		}

	}
}

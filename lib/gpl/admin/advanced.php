<?php
/*
 * License: GPLv3
 * License URI: http://www.gnu.org/licenses/gpl.txt
 * Copyright 2012-2015 - Jean-Sebastien Morisset - http://surniaulula.com/
 */

if ( ! defined( 'ABSPATH' ) ) 
	die( 'These aren\'t the droids you\'re looking for...' );

if ( ! class_exists( 'WpssoGplAdminAdvanced' ) ) {

	class WpssoGplAdminAdvanced {

		public function __construct( &$plugin ) {
			$this->p =& $plugin;
			$this->p->util->add_plugin_filters( $this, array( 
				'plugin_settings_rows' => 3,
				'plugin_content_rows' => 2,
				'plugin_social_rows' => 2,
				'plugin_integration_rows' => 2,
				'plugin_cache_rows' => 3,
				'plugin_apikeys_rows' => 2,
				'cm_custom_rows' => 2,
				'cm_builtin_rows' => 2,
				'taglist_tags_rows' => 4,
			), 20 );
		}

		public function filter_plugin_settings_rows( $rows, $form, $network = false ) {

			$rows[] = '<td colspan="'.( $network ? 4 : 2 ).'" align="center">'.
				$this->p->msgs->get( 'pro-feature-msg', array( 'lca' => 'wpsso' ) ).'</td>';

			$rows[] = $this->p->util->get_th( _x( 'Options to Show by Default',
				'option label', 'wpsso' ), null, 'plugin_show_opts' ).
			'<td class="blank">'.$this->p->cf['form']['show_options'][$this->p->options['plugin_show_opts']].'</td>'.
			$this->p->admin->get_site_use( $form, $network, 'plugin_object_cache_exp' );

			$rows[] = '<tr class="hide_in_basic">'.
			$this->p->util->get_th( _x( 'Report Cache Purge Count',
				'option label', 'wpsso' ), null, 'plugin_cache_info' ).
			'<td class="blank">'.$form->get_no_checkbox( 'plugin_cache_info' ).'</td>'.
			$this->p->admin->get_site_use( $form, $network, 'plugin_cache_info' );

			$rows[] = '<tr class="hide_in_basic">'.
			$this->p->util->get_th( _x( 'Use WP Locale for Language',
				'option label', 'wpsso' ), null, 'plugin_filter_lang' ).
			'<td class="blank">'.$form->get_no_checkbox( 'plugin_filter_lang' ).'</td>'.
			$this->p->admin->get_site_use( $form, $network, 'plugin_filter_lang' );

			$rows[] = '<tr class="hide_in_basic">'.
			$this->p->util->get_th( _x( 'Auto-Resize Media Images',
				'option label', 'wpsso' ), null, 'plugin_auto_img_resize' ).
			'<td class="blank">'.$form->get_no_checkbox( 'plugin_auto_img_resize' ).'</td>'.
			$this->p->admin->get_site_use( $form, $network, 'plugin_auto_img_resize' );

			$rows[] = '<tr class="hide_in_basic">'.
			$this->p->util->get_th( _x( 'Check Image Dimensions',
				'option label', 'wpsso' ), null, 'plugin_ignore_small_img' ).
			'<td class="blank">'.$form->get_no_checkbox( 'plugin_ignore_small_img' ).'</td>'.
			$this->p->admin->get_site_use( $form, $network, 'plugin_ignore_small_img' );

			if ( ! empty( $this->p->cf['*']['lib']['shortcode'] ) ) {
				$rows[] = '<tr class="hide_in_basic">'.
				$this->p->util->get_th( _x( 'Enable Plugin Shortcode(s)',
					'option label', 'wpsso' ), null, 'plugin_shortcodes' ).
				'<td class="blank">'.$form->get_no_checkbox( 'plugin_shortcodes' ).'</td>'.
				$this->p->admin->get_site_use( $form, $network, 'plugin_shortcodes' );
			}

			if ( ! empty( $this->p->cf['*']['lib']['widget'] ) ) {
				$rows[] = '<tr class="hide_in_basic">'.
				$this->p->util->get_th( _x( 'Enable Plugin Widget(s)',
					'option label', 'wpsso' ), null, 'plugin_widgets' ).
				'<td class="blank">'.$form->get_no_checkbox( 'plugin_widgets' ).'</td>'.
				$this->p->admin->get_site_use( $form, $network, 'plugin_widgets' );
			}

			$rows[] = '<tr class="hide_in_basic">'.
			$this->p->util->get_th( _x( 'Enable WP Excerpt for Pages',
				'option label', 'wpsso' ), null, 'plugin_page_excerpt' ).
			'<td class="blank">'.$form->get_no_checkbox( 'plugin_page_excerpt' ).'</td>'.
			$this->p->admin->get_site_use( $form, $network, 'plugin_page_excerpt' );

			$rows[] = '<tr class="hide_in_basic">'.
			$this->p->util->get_th( _x( 'Enable WP Tags for Pages',
				'option label', 'wpsso' ), null, 'plugin_page_tags' ).
			'<td class="blank">'.$form->get_no_checkbox( 'plugin_page_tags' ).'</td>'.
			$this->p->admin->get_site_use( $form, $network, 'plugin_page_tags' );

			return $rows;
		}

		public function filter_plugin_content_rows( $rows, $form ) {

			$rows[] = '<td colspan="2" align="center">'.
				$this->p->msgs->get( 'pro-feature-msg' ).'</td>';

			$rows[] = $this->p->util->get_th( _x( 'Use Filtered (SEO) Title',
				'option label', 'wpsso' ), 'highlight', 'plugin_filter_title' ).
			'<td class="blank">'.$form->get_no_checkbox( 'plugin_filter_title' ).'</td>';
			
			$rows[] = $this->p->util->get_th( _x( 'Apply WordPress Content Filters',
				'option label', 'wpsso' ), 'highlight', 'plugin_filter_content' ).
			'<td class="blank">'.$form->get_no_checkbox( 'plugin_filter_content' ).'</td>';

			$rows[] = '<tr class="hide_in_basic">'.
			$this->p->util->get_th( _x( 'Apply WordPress Excerpt Filters',
				'option label', 'wpsso' ), null, 'plugin_filter_excerpt' ).
			'<td class="blank">'.$form->get_no_checkbox( 'plugin_filter_excerpt' ).'</td>';

			$rows[] = '<tr class="hide_in_basic">'.
			$this->p->util->get_th( _x( 'Content Starts at 1st Paragraph',
				'option label', 'wpsso' ), null, 'plugin_p_strip' ).
			'<td class="blank">'.$form->get_no_checkbox( 'plugin_p_strip' ).'</td>';

			$rows[] = '<tr class="hide_in_basic">'.
			$this->p->util->get_th( _x( 'Use Image Alt if No Content',
				'option label', 'wpsso' ), null, 'plugin_use_img_alt' ).
			'<td class="blank">'.$form->get_no_checkbox( 'plugin_use_img_alt' ).'</td>';

			$rows[] = '<tr class="hide_in_basic">'.
			$this->p->util->get_th( _x( 'Image Alt Text Prefix',
				'option label', 'wpsso' ), null, 'plugin_img_alt_prefix' ).
			'<td class="blank">'.$form->options['plugin_img_alt_prefix'].'</td>';

			$rows[] = '<tr class="hide_in_basic">'.
			$this->p->util->get_th( _x( 'WP Caption Paragraph Prefix',
				'option label', 'wpsso' ), null, 'plugin_p_cap_prefix' ).
			'<td class="blank">'.$form->options['plugin_p_cap_prefix'].'</td>';

			$rows[] = $this->p->util->get_th( _x( 'Check for Embedded Media',
				'option label', 'wpsso' ), null, 'plugin_embedded_media' ).
			'<td class="blank">'.
			'<p>'.$form->get_no_checkbox( 'plugin_slideshare_api' ).' Slideshare Presentations</p>'.
			'<p>'.$form->get_no_checkbox( 'plugin_vimeo_api' ).' Vimeo Videos</p>'.
			'<p>'.$form->get_no_checkbox( 'plugin_wistia_api' ).' Wistia Videos</p>'.
			'<p>'.$form->get_no_checkbox( 'plugin_youtube_api' ).' YouTube Videos and Playlists</p>'.
			'</td>';

			return $rows;
		}

		public function filter_plugin_social_rows( $rows, $form ) {

			$rows[] = '<td colspan="2" align="center">'.
				$this->p->msgs->get( 'pro-feature-msg' ).'</td>';

			$checkboxes = '';
			foreach ( $this->p->util->get_post_types( 'backend' ) as $post_type )
				$checkboxes .= '<p>'.$form->get_no_checkbox( 'plugin_add_to_'.$post_type->name ).' '.
					$post_type->label.' '.( empty( $post_type->description ) ? '' : '('.$post_type->description.')' ).'</p>';
			$checkboxes .= '<p>'.$form->get_no_checkbox( 'plugin_add_to_taxonomy' ).' Taxonomy (Categories and Tags)</p>';
			$checkboxes .= '<p>'.$form->get_no_checkbox( 'plugin_add_to_user' ).' User Profile</p>';

			$rows[] = $this->p->util->get_th( _x( 'Show Social Settings Metabox on',
				'option label', 'wpsso' ), null, 'plugin_add_to' ).
			'<td class="blank">'.$checkboxes.'</td>';
			
			$rows[] = '<tr class="hide_in_basic">'.
			$this->p->util->get_th( _x( 'Image URL Custom Field',
				'option label', 'wpsso' ), null, 'plugin_cf_img_url' ).
			'<td class="blank">'.$form->get_hidden( 'plugin_cf_img_url' ).
				$this->p->options['plugin_cf_img_url'].'</td>';

			$rows[] = '<tr class="hide_in_basic">'.
			$this->p->util->get_th( _x( 'Video URL Custom Field',
				'option label', 'wpsso' ), null, 'plugin_cf_vid_url' ).
			'<td class="blank">'.$form->get_hidden( 'plugin_cf_vid_url' ).
				$this->p->options['plugin_cf_vid_url'].'</td>';

			$rows[] = '<tr class="hide_in_basic">'.
			$this->p->util->get_th( _x( 'Video Embed HTML Custom Field',
				'option label', 'wpsso' ), null, 'plugin_cf_vid_embed' ).
			'<td class="blank">'.$form->get_hidden( 'plugin_cf_vid_embed' ).
				$this->p->options['plugin_cf_vid_embed'].'</td>';
			
			return $rows;
		}

		public function filter_plugin_integration_rows( $rows, $form ) {

			$rows[] = '<td colspan="3" align="center">'.
				$this->p->msgs->get( 'pro-feature-msg' ).'</td>';

			$rows[] = $this->p->util->get_th( _x( 'Check for Duplicate Meta Tags',
				'option label', 'wpsso' ), 'highlight', 'plugin_check_head' ).
			'<td class="blank">'.$form->get_no_checkbox( 'plugin_check_head' ).'</td>';

			$rows[] = '<tr class="hide_in_basic">'.
			$this->p->util->get_th( _x( 'Header &lt;html&gt; Attribute Filter',
				'option label', 'wpsso' ), null, 'plugin_html_attr_filter' ).
			'<td class="blank">Name:&nbsp;'.$this->p->options['plugin_html_attr_filter_name'].'</td><td class="blank">'.
				'Priority:&nbsp;'.$this->p->options['plugin_html_attr_filter_prio'].'</td>';
			
			$rows[] = '<tr class="hide_in_basic">'.
			$this->p->util->get_th( _x( 'Header &lt;head&gt; Attribute Filter',
				'option label', 'wpsso' ), 'highlight', 'plugin_head_attr_filter' ).
			'<td class="blank">Name:&nbsp;'.$this->p->options['plugin_head_attr_filter_name'].'</td><td class="blank">'.
				'Priority:&nbsp;'.$this->p->options['plugin_head_attr_filter_prio'].'</td>';
			
			return $rows;
		}

		public function filter_plugin_cache_rows( $rows, $form, $network = false ) {

			$rows[] = '<td colspan="'.( $network ? 4 : 2 ).'" align="center">'.
				$this->p->msgs->get( 'pro-feature-msg', array( 'lca' => 'wpsso' ) ).'</td>';

			$rows['plugin_object_cache_exp'] = $this->p->util->get_th( _x( 'Object Cache Expiry',
				'option label', 'wpsso' ), 'highlight', 'plugin_object_cache_exp' ).
			'<td nowrap class="blank">'.$this->p->options['plugin_object_cache_exp'].' seconds</td>'.
			$this->p->admin->get_site_use( $form, $network, 'plugin_object_cache_exp' );

			$rows['plugin_verify_certs'] = '<tr class="hide_in_basic">'.
			$this->p->util->get_th( _x( 'Verify SSL Certificates',
				'option label', 'wpsso' ), null, 'plugin_verify_certs' ).
			'<td class="blank">'.$form->get_no_checkbox( 'plugin_verify_certs' ).'</td>'.
			$this->p->admin->get_site_use( $form, $network, 'plugin_verify_certs' );

			return $rows;
		}

		public function filter_plugin_apikeys_rows( $rows, $form, $network = false ) {

			$rows[] = '<td colspan="'.( $network ? 4 : 2 ).'" align="center">'.
				$this->p->msgs->get( 'pro-feature-msg', array( 'lca' => 'wpsso' ) ).'</td>';

			$rows['plugin_shortener'] = $this->p->util->get_th( _x( 'Preferred URL Shortening Service',
				'option label', 'wpsso' ), null, 'plugin_shortener' ).
			'<td class="blank">'.$form->get_hidden( 'plugin_shortener' ).
			$this->p->cf['form']['shorteners'][$this->p->options['plugin_shortener']].'</td>';

			$rows['plugin_min_shorten'] = '<tr class="hide_in_basic">'.
			$this->p->util->get_th( _x( 'Minimum URL Length to Shorten',
				'option label', 'wpsso' ), null, 'plugin_min_shorten' ). 
			'<td nowrap class="blank">'.$this->p->options['plugin_min_shorten'].' '.
				_x( 'characters', 'option comment', 'wpsso' ).'</td>';

			$rows['plugin_bitly_login'] = $this->p->util->get_th( _x( 'Bit.ly Username',
				'option label', 'wpsso' ), null, 'plugin_bitly_login' ).
			'<td class="blank mono">'.$this->p->options['plugin_bitly_login'].'</td>';

			$rows['plugin_bitly_api_key'] = $this->p->util->get_th( _x( 'Bit.ly API Key',
				'option label', 'wpsso' ), null, 'plugin_bitly_api_key' ).
			'<td class="blank mono">'.$this->p->options['plugin_bitly_api_key'].'</td>';

			$rows['plugin_google_api_key'] = $this->p->util->get_th( _x( 'Google Project App BrowserKey',
				'option label', 'wpsso' ), null, 'plugin_google_api_key' ).
			'<td class="blank mono">'.$this->p->options['plugin_google_api_key'].'</td>';

			$rows['plugin_google_shorten'] = $this->p->util->get_th( _x( 'Google URL Shortener API is ON',
				'option label', 'wpsso' ), null, 'plugin_google_shorten' ).
			'<td class="blank">'._x( $this->p->cf['form']['yes_no'][$this->p->options['plugin_google_shorten']],
				'form option value', 'wpsso' ).'</td>';

			return $rows;
		}

		public function filter_cm_custom_rows( $rows, $form ) {

			$rows[] = '<td colspan="4" align="center">'.
				$this->p->msgs->get( 'pro-feature-msg' ).'</td>';

			$rows[] = '<td></td>'.
			$this->p->util->get_th( _x( 'Show',
				'column title', 'wpsso' ), 'left checkbox' ).
			$this->p->util->get_th( _x( 'Contact Field Name',
				'column title', 'wpsso' ), 'left medium', 'custom-cm-field-name' ).
			$this->p->util->get_th( _x( 'Profile Contact Label',
				'column title', 'wpsso' ), 'left wide' );

			$sorted_opt_pre = $this->p->cf['opt']['pre'];
			ksort( $sorted_opt_pre );

			foreach ( $sorted_opt_pre as $id => $pre ) {

				$cm_enabled = 'plugin_cm_'.$pre.'_enabled';
				$cm_name = 'plugin_cm_'.$pre.'_name';
				$cm_label = 'plugin_cm_'.$pre.'_label';

				// check for the lib website classname for a nice 'display name'
				$name = empty( $this->p->cf['*']['lib']['website'][$id] ) ? 
					ucfirst( $id ) : $this->p->cf['*']['lib']['website'][$id];
				$name = $name == 'GooglePlus' ? 'Google+' : $name;

				// not all social websites have a contact method field
				if ( isset( $this->p->options[$cm_enabled] ) ) {
					$rows[] = $this->p->util->get_th( $name, 'medium' ).
					'<td class="blank checkbox">'.$form->get_no_checkbox( $cm_enabled ).'</td>'.
					'<td class="blank">'.$form->get_no_input( $cm_name, 'medium' ).'</td>'.
					'<td class="blank">'.$form->get_no_input( $cm_label ).'</td>';
				}
			}

			return $rows;
		}

		public function filter_cm_builtin_rows( $rows, $form ) {

			$rows[] = '<td colspan="4" align="center">'.
				$this->p->msgs->get( 'pro-feature-msg' ).'</td>';

			$rows[] = '<td></td>'.
			$this->p->util->get_th( _x( 'Show',
				'column title', 'wpsso' ), 'left checkbox' ).
			$this->p->util->get_th( _x( 'Contact Field Name',
				'column title', 'wpsso' ), 'left medium', 'custom-cm-field-name' ).
			$this->p->util->get_th( _x( 'Profile Contact Label',
				'column title', 'wpsso' ), 'left wide' );

			$sorted_wp_cm = $this->p->cf['wp']['cm'];
			ksort( $sorted_wp_cm );

			foreach ( $sorted_wp_cm as $id => $name ) {

				$cm_enabled = 'wp_cm_'.$id.'_enabled';
				$cm_name = 'wp_cm_'.$id.'_name';
				$cm_label = 'wp_cm_'.$id.'_label';

				if ( array_key_exists( $cm_enabled, $this->p->options ) ) {
					$rows[] = $this->p->util->get_th( $name, 'medium' ).
					'<td class="blank checkbox">'.
						$form->get_hidden( $cm_enabled ).
						$form->get_no_checkbox( $cm_enabled ).'</td>'.
					'<td>'.$form->get_no_input( $cm_name, 'medium' ).'</td>'.
					'<td class="blank">'.$form->get_no_input( $cm_label ).'</td>';
				}
			}

			return $rows;
		}

		public function filter_taglist_tags_rows( $rows, $form, $network = false, $tag = '[^_]+' ) {
			$og_cols = 2;
			$cells = array();
			foreach ( $this->p->opt->get_defaults() as $opt => $val ) {
				if ( strpos( $opt, 'add_' ) === 0 &&
					preg_match( '/^add_('.$tag.')_([^_]+)_(.+)$/', $opt, $match ) ) {
					switch ( $opt ) {
						case 'add_meta_name_generator':
							continue 2;
						case 'add_meta_name_canonical':
						case 'add_meta_name_description':
							$highlight = ' highlight';
							break;
						default:
							$highlight = '';
							break;
					}
					$cells[] = '<!-- '.( implode( ' ', $match ) ).' -->'.	// required for sorting
						'<td class="checkbox blank">'.$form->get_checkbox( $opt, null, null, true ).'</td>'.
						'<td class="xshort'.$highlight.'">'.$match[1].'</td>'.
						'<td class="taglist'.$highlight.'">'.$match[2].'</td>'.
						'<th class="taglist'.$highlight.'">'.$match[3].'</th>';
				}
			}
			sort( $cells );
			$col_rows = array();
			$per_col = ceil( count( $cells ) / $og_cols );
			foreach ( $cells as $num => $cell ) {
				if ( empty( $col_rows[ $num % $per_col ] ) )
					$col_rows[ $num % $per_col ] = '<tr class="hide_in_basic">';	// initialize the array
				$col_rows[ $num % $per_col ] .= $cell;					// create the html for each row
			}
			return array_merge( $rows, $col_rows );
		}
	}
}

?>

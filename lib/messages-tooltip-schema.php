<?php
/**
 * License: GPLv3
 * License URI: https://www.gnu.org/licenses/gpl.txt
 * Copyright 2012-2022 Jean-Sebastien Morisset (https://wpsso.com/)
 */

if ( ! defined( 'ABSPATH' ) ) {

	die( 'These aren\'t the droids you\'re looking for.' );
}

if ( ! defined( 'WPSSO_PLUGINDIR' ) ) {

	die( 'Do. Or do not. There is no try.' );
}

if ( ! class_exists( 'WpssoMessagesTooltipSchema' ) ) {

	/**
	 * Instantiated by WpssoMessagesTooltip->get() only when needed.
	 */
	class WpssoMessagesTooltipSchema extends WpssoMessages {

		public function get( $msg_key = false, $info = array() ) {

			$text = '';

			switch ( $msg_key ) {

				case 'tooltip-schema_1x1_img_size':	// Schema 1:1 (Google) Image Size.
				case 'tooltip-schema_4x3_img_size':	// Schema 4:3 (Google) Image Size.
				case 'tooltip-schema_16x9_img_size':	// Schema 16:9 (Google) Image Size.

					if ( preg_match( '/^tooltip-(schema_([0-9]+)x([0-9]+))_img_size$/', $msg_key, $matches ) ) {

						$opt_pre       = $matches[ 1 ];
						$ratio_msg     = $matches[ 2 ] . ':' . $matches[ 3 ];
						$def_img_dims  = $this->get_def_img_dims( $opt_pre );
						$min_img_width = $this->p->cf[ 'head' ][ 'limit_min' ][ $opt_pre . '_img_width' ];

						$text = sprintf( __( 'The %1$s dimensions used for Schema markup images (default dimensions are %2$s).', 'wpsso' ), $ratio_msg, $def_img_dims ) . ' ';

						$text .= sprintf( __( 'The minimum image width required by Google is %dpx.', 'wpsso' ), $min_img_width ). ' ';
					}

					break;

				case 'tooltip-schema_thumb_img_size':	// Schema Thumbnail Image Size.

					$def_img_dims = $this->get_def_img_dims( 'thumb' );

					$text = sprintf( __( 'The dimensions used for the Schema "%1$s" property and "%2$s" HTML tag (default dimensions are %3$s).', 'wpsso' ), 'thumbnailUrl', 'meta name thumbnail', $def_img_dims );

					break;

				case 'tooltip-schema_aggr_offers':		// Aggregate Offers by Currency.

					$text = __( 'Aggregate (ie. group) product offers by currency.', 'wpsso' ) . ' ';

					$text .= sprintf( __( 'Note that to be eligible for <a href="%s">price drop appearance in Google search results</a>, product offers cannot be aggregated.', 'wpsso' ), 'https://developers.google.com/search/docs/data-types/product#price-drop' );

		 			break;

				case 'tooltip-schema_add_text_prop':		// Add Text / Article Body Properties.

					$text = __( 'Add a "text" or "articleBody" property to Schema CreativeWork markup with the complete textual content of the post / page.', 'wpsso' );

				 	break;

				/**
				 * SSO > Advanced Settings > Document Types > Schema tab.
				 */
				case 'tooltip-schema_type_for_home_page':	// Type for Page Homepage.

					$def_type = $this->p->schema->get_default_schema_type_name_for( 'home_page' );

					// translators: %s is the markup standard name (ie. Open Graph or Schema).
					$text = sprintf( __( 'Select a default %s type for a static front page.', 'wpsso' ), 'Schema' ) . ' ';

					// translators: %1$s is the markup standard name (ie. Open Graph or Schema) and %2$s is the type name.
					$text .= sprintf( __( 'The default %1$s type is "%2$s".', 'wpsso' ), 'Schema', $def_type  );

					break;

				case 'tooltip-schema_type_for_home_posts':	// Type for Posts Homepage.

					$def_type = $this->p->schema->get_default_schema_type_name_for( 'home_posts' );

					// translators: %s is the markup standard name (ie. Open Graph or Schema).
					$text = sprintf( __( 'Select a default %s type for a blog (non-static) front page.', 'wpsso' ), 'Schema' ) . ' ';

					// translators: %1$s is the markup standard name (ie. Open Graph or Schema) and %2$s is the type name.
					$text .= sprintf( __( 'The default %1$s type is "%2$s".', 'wpsso' ), 'Schema', $def_type  );

					break;

				case 'tooltip-schema_type_for_user_page':	// Type for User / Author.

					$def_type = $this->p->schema->get_default_schema_type_name_for( 'user_page' );

					// translators: %s is the markup standard name (ie. Open Graph or Schema).
					$text = sprintf( __( 'Select a default %s type for user / author profile pages.', 'wpsso' ), 'Schema' ) . ' ';

					// translators: %1$s is the markup standard name (ie. Open Graph or Schema) and %2$s is the type name.
					$text .= sprintf( __( 'The default %1$s type is "%2$s".', 'wpsso' ), 'Schema', $def_type  );

					break;

				case 'tooltip-schema_type_for_search_page':	// Type for Search Results.

					$def_type = $this->p->schema->get_default_schema_type_name_for( 'search_page' );

					// translators: %s is the markup standard name (ie. Open Graph or Schema).
					$text = sprintf( __( 'Select a default %s type for search results pages.', 'wpsso' ), 'Schema' ) . ' ';

					// translators: %1$s is the markup standard name (ie. Open Graph or Schema) and %2$s is the type name.
					$text .= sprintf( __( 'The default %1$s type is "%2$s".', 'wpsso' ), 'Schema', $def_type  );

					break;

				case 'tooltip-schema_type_for_archive_page':	// Type for Archive Page.

					$def_type = $this->p->schema->get_default_schema_type_name_for( 'archive_page' );

					// translators: %s is the markup standard name (ie. Open Graph or Schema).
					$text = sprintf( __( 'Select a default %s type for other archive pages (date-based archive pages, for example).', 'wpsso' ), 'Schema' ) . ' ';

					// translators: %1$s is the markup standard name (ie. Open Graph or Schema) and %2$s is the type name.
					$text .= sprintf( __( 'The default %1$s type is "%2$s".', 'wpsso' ), 'Schema', $def_type  );

					break;

				case 'tooltip-schema_type_for_pt':	// Type by Post Type.

					// translators: %s is the markup standard name (ie. Open Graph or Schema).
					$text = sprintf( __( 'Select a default %s type for each post type.', 'wpsso' ), 'Schema' ) . ' ';

					break;

				case 'tooltip-schema_type_for_pta':	// Type by Post Type Archive.

					$def_type = $this->p->schema->get_default_schema_type_name_for( 'archive_page' );

					// translators: %s is the markup standard name (ie. Open Graph or Schema).
					$text = sprintf( __( 'Select a default %s type for each post type archive.', 'wpsso' ), 'Schema' ) . ' ';

					// translators: %1$s is the markup standard name (ie. Open Graph or Schema) and %2$s is the type name.
					$text .= sprintf( __( 'The default %1$s type is "%2$s".', 'wpsso' ), 'Schema', $def_type  );

					break;

				case 'tooltip-schema_type_for_tax':	// Type by Taxonomy.

					$def_type = $this->p->schema->get_default_schema_type_name_for( 'archive_page' );

					// translators: %s is the markup standard name (ie. Open Graph or Schema).
					$text = sprintf( __( 'Select a default %s type for each taxonomy.', 'wpsso' ), 'Schema' ) . ' ';

					// translators: %1$s is the markup standard name (ie. Open Graph or Schema) and %2$s is the type name.
					$text .= sprintf( __( 'The default %1$s type is "%2$s".', 'wpsso' ), 'Schema', $def_type  );

					break;

				/**
				 * SSO > Advanced Settings > Schema Properties > Book tab.
				 */
				case 'tooltip-schema_def_book_format':		// Default Book Format.

					$text = __( 'Select a default format type for the Schema Book type.', 'wpsso' );

				 	break;

				/**
				 * SSO > Advanced Settings > Schema Properties > Creative Work tab.
				 */
				case 'tooltip-schema_def_family_friendly':	// Default Family Friendly.

					$text = __( 'Select a default family friendly value for the Schema CreativeWork type and/or its sub-types (Article, BlogPosting, WebPage, etc).', 'wpsso' );

				 	break;

				case 'tooltip-schema_def_pub_org_id':		// Default Publisher Org.

					$text = __( 'Select a default publisher organization for the Schema CreativeWork type and/or its sub-types (Article, BlogPosting, WebPage, etc).', 'wpsso' );

				 	break;

				case 'tooltip-schema_def_pub_person_id':	// Default Publisher Person.

					$text = __( 'Select a default publisher person for the Schema CreativeWork type and/or its sub-types (Article, BlogPosting, WebPage, etc).', 'wpsso' );

				 	break;

				case 'tooltip-schema_def_prov_org_id':		// Default Service Prov. Org.
				case 'tooltip-schema_def_prov_person_id':	// Default Service Prov. Person.

					$text = __( 'Select a default service provider, service operator or service performer (example: "Netflix").', 'wpsso' );

				 	break;

				/**
				 * SSO > Advanced Settings > Schema Properties > Event tab.
				 */
				case 'tooltip-schema_def_event_attendance':	// Event Attendance.

					$text = __( 'Select a default attendance for the Schema Event type.', 'wpsso' );

				 	break;

				case 'tooltip-schema_def_event_location_id':	// Default Physical Venue.

					$text = __( 'Select a default venue for the Schema Event type.', 'wpsso' );

				 	break;

				case 'tooltip-schema_def_event_organizer_org_id':	// Default Organizer Org.

					$text = __( 'Select a default organizer (organization) for the Schema Event type.', 'wpsso' );

				 	break;

				case 'tooltip-schema_def_event_organizer_person_id':	// Default Organizer Person.

					$text = __( 'Select a default organizer (person) for the Schema Event type.', 'wpsso' );

				 	break;

				case 'tooltip-schema_def_event_performer_org_id':	// Default Performer Org.

					$text = __( 'Select a default performer (organization) for the Schema Event type.', 'wpsso' );

				 	break;

				case 'tooltip-schema_def_event_performer_person_id':	// Default Performer Person.

					$text = __( 'Select a default performer (person) for the Schema Event type.', 'wpsso' );

				 	break;

				/**
				 * SSO > Advanced Settings > Schema Properties > Job Posting tab.
				 */
				case 'tooltip-schema_def_job_hiring_org_id':	// Default Job Hiring Org.

					$text = __( 'Select a default organization for the Schema JobPosting hiring organization.', 'wpsso' );

				 	break;

				case 'tooltip-schema_def_job_location_id':	// Default Job Location.

					$text = __( 'Select a default location for the Schema JobPosting job location.', 'wpsso' );

				 	break;

				case 'tooltip-schema_def_job_location_type':	// Default Job Location Type.

					$text = sprintf( __( 'Select a default optional Google approved location type (see <a href="%s">Google\'s Job Posting guidelines</a> for more information).', 'wpsso' ), 'https://developers.google.com/search/docs/data-types/job-postings' );

				 	break;

				/**
				 * SSO > Advanced Settings > Schema Properties > Review tab.
				 */
				case 'tooltip-schema_def_review_item_type':	// Default Subject Webpage Type.

					$text = __( 'Select a default Schema type for the Schema Review subject URL.', 'wpsso' ) . ' ';

					$text .= sprintf( __( 'Please note that although the Schema standard allows the subject of a review to be any Schema type, <a href="%1$s">Google allows reviews for only a few specific Schema types (and their sub-types)</a>.', 'wpsso' ), 'https://developers.google.com/search/docs/data-types/review-snippet' );

				 	break;

				default:

					$text = apply_filters( 'wpsso_messages_tooltip_schema', $text, $msg_key, $info );

					break;

			}	// End of 'tooltip-schema' switch.

			return $text;
		}
	}
}

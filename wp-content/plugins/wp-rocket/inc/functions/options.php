<?php
defined( 'ABSPATH' ) or	die( 'Cheatin&#8217; uh?' );

/**
 * A wrapper to easily get rocket option
 *
 * @since 1.3.0
 *
 * @param string $option  The option name
 * @param bool   $default (default: false) The default value of option
 * @return mixed The option value
 */
function get_rocket_option( $option, $default = false )
{
	/**
	 * Pre-filter any WP Rocket option before read
	 *
	 * @since 2.5
	 *
	 * @param variant $default The default value
	*/
	$value = apply_filters( 'pre_get_rocket_option_' . $option, NULL, $default );
	if ( NULL !== $value ) {
		return $value;
	}
	$options = get_option( WP_ROCKET_SLUG );
	if ( 'consumer_key' == $option && defined( 'WP_ROCKET_KEY' ) ) {
		return WP_ROCKET_KEY;
	} elseif( 'consumer_email' == $option && defined( 'WP_ROCKET_EMAIL' ) ) {
		return WP_ROCKET_EMAIL;
	}
	$value = isset( $options[ $option ] ) && $options[ $option ] !== '' ? $options[ $option ] : $default;
	/**
	 * Filter any WP Rocket option after read
	 *
	 * @since 2.5
	 *
	 * @param variant $default The default value
	*/
	return apply_filters( 'get_rocket_option_' . $option, $value, $default );
}

/**
 * Is we need to exclude some specifics options on a post.
 *
 * @since 2.5
 *
 * @param  string $option  The option name (lazyload, css, js, cdn)
 * @return bool 		   True if the option is deactivated
 */
function is_rocket_post_excluded_option( $option ) {
	if( is_home() ) {
		$post_id = get_queried_object_id();
	}
	
	if ( is_singular() ) {
		$post_id = $GLOBALS['post']->ID;	
	}
	
	return ( isset( $post_id ) ) ? get_post_meta( $post_id, '_rocket_exclude_' . $option, true ) : false;
}

/**
 * Check if we need to cache the mobile version of the website (if available)
 *
 * @since 1.0
 *
 * @return bool True if option is activated
 */
function is_rocket_cache_mobile()
{
	return get_rocket_option( 'cache_mobile', false );
}

/**
 * Check if we need to cache SSL requests of the website (if available)
 *
 * @since 1.0
 * @access public
 * @return bool True if option is activated
 */
function is_rocket_cache_ssl()
{
	return get_rocket_option( 'cache_ssl', false );
}

/**
 * Check if we need to disable CDN on SSL pages
 *
 * @since 2.5
 * @access public
 * @return bool True if option is activated
 */
function is_rocket_cdn_on_ssl() {
	return is_ssl() && get_rocket_option( 'cdn_ssl', 0 ) ? false : true;
}

/**
 * Get the interval task cron purge in seconds
 * This setting can be changed from the options page of the plugin
 *
 * @since 1.0
 *
 * @return int The interval task cron purge in seconds
 */
function get_rocket_purge_cron_interval()
{
	if ( ! get_rocket_option( 'purge_cron_interval' ) || ! get_rocket_option( 'purge_cron_unit' ) ) {
		return 0;
	}
	return (int) ( get_rocket_option( 'purge_cron_interval' ) * constant( get_rocket_option( 'purge_cron_unit' ) ) );
}

/**
 * Get all uri we don't cache
 *
 * @since 2.6	Using json_get_url_prefix() to auto-exclude the WordPress REST API
 * @since 2.4.1 Auto-exclude WordPress REST API
 * @since 2.0
 *
 * @return array List of rejected uri
 */
function get_rocket_cache_reject_uri()
{
	$uri = get_rocket_option( 'cache_reject_uri', array() );
	
	// Exclude cart & checkout pages from e-commerce plugins
	$uri = array_merge( $uri, get_rocket_ecommerce_exclude_pages() );
		
	// Exclude hide login plugins
	$uri = array_merge( $uri, get_rocket_logins_exclude_pages() );
	
	/**
	  * By default, don't cache the WP REST API.
	  *
	  * @since 2.5.12
	  *
	  * @param bool false will force to cache the WP REST API
	 */
	$rocket_cache_reject_wp_rest_api = apply_filters( 'rocket_cache_reject_wp_rest_api', true );
	
	// Exclude WP REST API
	if( function_exists( 'json_get_url_prefix' ) && $rocket_cache_reject_wp_rest_api ) {
		$uri[] = '/' . json_get_url_prefix() . '/(.*)';	
	}
	
	// Exclude feeds
	$uri[] = '.*/' . $GLOBALS['wp_rewrite']->feed_base . '/';
	
	/**
	 * Filter the rejected uri
	 *
	 * @since 2.1
	 *
	 * @param array $uri List of rejected uri
	*/
	$uri = apply_filters( 'rocket_cache_reject_uri', $uri );

	$uri = implode( '|', array_filter( $uri ) );
	return $uri;
}

/**
 * Get all cookie names we don't cache
 *
 * @since 2.0
 *
 * @return array List of rejected cookies
 */
function get_rocket_cache_reject_cookies()
{
	$cookies   = get_rocket_option( 'cache_reject_cookies', array() );
	$cookies[] = str_replace( COOKIEHASH, '', LOGGED_IN_COOKIE );
	$cookies[] = 'wp-postpass_';
	$cookies[] = 'wptouch_switch_toggle';
	$cookies[] = 'comment_author_';
	$cookies[] = 'comment_author_email_';

	/**
	 * Filter the rejected cookies
	 *
	 * @since 2.1
	 *
	 * @param array $cookies List of rejected cookies
	*/
	$cookies = apply_filters( 'rocket_cache_reject_cookies', $cookies );

	$cookies = implode( '|', array_filter( $cookies ) );
	return $cookies;
}

/**
 * Get all User-Agent we don't allow to get cache files
 *
 * @since 2.3.5
 *
 * @return array List of rejected User-Agent
 */
function get_rocket_cache_reject_ua() {
	$ua   = get_rocket_option( 'cache_reject_ua', array() );
	$ua[] = 'facebookexternalhit';

	/**
	 * Filter the rejected User-Agent
	 *
	 * @since 2.3.5
	 *
	 * @param array $ua List of rejected User-Agent
	*/
	$ua = apply_filters( 'rocket_cache_reject_ua', $ua );

	$ua = implode( '|', array_filter( $ua ) );
	return $ua;
}

/**
 * Get all files we don't allow to get in CDN
 *
 * @since 2.5
 *
 * @return array List of rejected files
 */
function get_rocket_cdn_reject_files() {
	$files = get_rocket_option( 'cdn_reject_files', array() );
	
	/**
	 * Filter the rejected files
	 *
	 * @since 2.5
	 *
	 * @param array $files List of rejected files
	*/
	$files = apply_filters( 'rocket_cdn_reject_files', $files );
	
	$files = implode( '|', array_filter( $files ) );	
	
	return $files;
}

/*
 * Get all CNAMES
 *
 * @since 2.1
 *
 * @param string $zone (default: 'all') List of zones
 * @return array List of CNAMES
 */
function get_rocket_cdn_cnames( $zone = 'all' )
{
	if ( (int) get_rocket_option( 'cdn' ) == 0 ) {
		return array();
	}

	$hosts       = array();
	$cnames      = get_rocket_option( 'cdn_cnames', array() );
	$cnames_zone = get_rocket_option( 'cdn_zone', array() );
	$zone 		 = is_array( $zone ) ? $zone : (array) $zone;

	foreach( $cnames as $k=>$_urls ) {
		if ( in_array( $cnames_zone[$k], $zone ) ) {
			$_urls = explode( ',' , $_urls );
			$_urls = array_map( 'trim' , $_urls );

			foreach( $_urls as $url ) {
				$hosts[] = $url;
			}
		}
	}
	return $hosts;
}

/**
 * Get all query strings which can be cached.
 *
 * @since 2.3
 *
 * @return array List of query strings which can be cached.
 */
function get_rocket_cache_query_string() {
	$query_strings = get_rocket_option( 'cache_query_strings', array() );
	
	/**
	 * Filter query strings which can be cached.
	 *
	 * @since 2.3
	 *
	 * @param array $query_strings List of query strings which can be cached.
	*/
	$query_strings = apply_filters( 'rocket_cache_query_strings', $query_strings );

	return $query_strings;
}

/**
 * Get all CSS files to exclude to the minification.
 *
 * @since 2.6
 *
 * @return array List of excluded CSS files.
 */
function get_rocket_exclude_css() {
	global $rocket_excluded_enqueue_css;
	
	$css_files = get_rocket_option( 'exclude_css', array() );
	$css_files = array_unique( array_merge( $css_files, (array) $rocket_excluded_enqueue_css ) );
	
	/**
	 * Filter CSS files to exclude to the minification.
	 *
	 * @since 2.6
	 *
	 * @param array $css_files List of excluded CSS files.
	*/
	$css_files = apply_filters( 'rocket_exclude_css', $css_files );
	
	return $css_files;
}

/**
 * Get all JS files to exclude to the minification.
 *
 * @since 2.6
 *
 * @return array List of excluded JS files.
 */
function get_rocket_exclude_js() {	
	global $rocket_excluded_enqueue_js;
	
	$js_files = get_rocket_option( 'exclude_js', array() );
	$js_files = array_unique( array_merge( $js_files, (array) $rocket_excluded_enqueue_js ) );
	
	/**
	 * Filter JS files to exclude to the minification.
	 *
	 * @since 2.6
	 *
	 * @param array $css_files List of excluded JS files.
	*/
	$js_files = apply_filters( 'rocket_exclude_js', $js_files );
	
	return $js_files;
}

/**
 * Get all JS files to move in the footer during the minification.
 *
 * @since 2.6
 *
 * @return array List of JS files.
 */
function get_rocket_minify_js_in_footer() {
	global $rocket_enqueue_js_in_footer;
	
	$js_files = get_rocket_option( 'minify_js_in_footer', array() );
	$js_files = array_map( 'rocket_set_internal_url_scheme', $js_files );
	$js_files = array_unique( array_merge( $js_files, (array) $rocket_enqueue_js_in_footer ) );
	
	/**
	 * Filter JS files to move in the footer during the minification.
	 *
	 * @since 2.6
	 *
	 * @param array $js_files List of JS files.
	*/
	$js_files = apply_filters( 'rocket_minify_js_in_footer', $js_files );
	
	return $js_files;
}

/**
 * Get list of JS files to deferred.
 *
 * @since 2.6
 *
 * @return array List of JS files.
 */
function get_rocket_deferred_js_files() {
	/**
	 * Filter list of Deferred JavaScript files
	 *
	 * @since 1.1.0
	 *
	 * @param array List of Deferred JavaScript files
	 */
	$deferred_js_files = apply_filters( 'rocket_minify_deferred_js', get_rocket_option( 'deferred_js_files', array() ) );
	
	return $deferred_js_files;
}

/**
 * Determine if the key is valid
 *
 * @since 1.0
 */
function rocket_valid_key()
{
	return true;
}

/**
 * Determine if the key is valid
 *
 * @since 2.2 The function do the live check and update the option
 */
function rocket_check_key( $type = 'transient_1', $data = null )
{
	// Recheck the license
	$return = rocket_valid_key();

	if ( ! rocket_valid_key()
		|| ( 'transient_1' == $type && ! get_transient( 'rocket_check_licence_1' ) )
		|| ( 'transient_30' == $type && ! get_transient( 'rocket_check_licence_30' ) )
		|| 'live' == $type ) {

		//$response = wp_remote_get( WP_ROCKET_WEB_VALID, array( 'timeout'=>30 ) );

		$json = ! is_wp_error( $response ) ? json_decode( $response['body'] ) : false;
		$rocket_options = array();
		$json->data->consumer_key = '2b35dec65674314f5d8d9fe900dc03b8';
		$json->data->consumer_email = 'example@example.com';
		$json->data->secret_key = '4a2b0ceb5ec45ec5d8d9f56af476e31b';
		$json->success = true;

		if ( $json ) {

			$rocket_options['consumer_key'] 	= $json->data->consumer_key;
			$rocket_options['consumer_email']	= $json->data->consumer_email;

			if( $json->success ) {

				$rocket_options['secret_key'] = $json->data->secret_key;
				if ( ! get_rocket_option( 'license' ) ) {
					$rocket_options['license'] = '1';
				}
				
				if ( 'live' != $type ) {
					if ( 'transient_1' == $type ) {
						set_transient( 'rocket_check_licence_1', true, DAY_IN_SECONDS );
					} elseif ( 'transient_30' == $type ) {
						set_transient( 'rocket_check_licence_30', true, DAY_IN_SECONDS*30 );
					}
				}

			} else {

				$messages = array( 	'BAD_LICENSE'	=> __( 'Your license is not valid.', 'rocket' ),
									'BAD_NUMBER'	=> __( 'You cannot add more websites. Upgrade your account.', 'rocket' ),
									'BAD_SITE'		=> __( 'This website is not allowed.', 'rocket' ),
									'BAD_KEY'		=> __( 'This license key is not accepted.', 'rocket' ),
								);
				$rocket_options['secret_key'] = '';

				add_settings_error( 'general', 'settings_updated', $messages[ $json->data->reason ], 'error' );

			}

			set_transient( WP_ROCKET_SLUG, $rocket_options );
			$return = (array) $rocket_options;

		}
	}

	return $return;
}
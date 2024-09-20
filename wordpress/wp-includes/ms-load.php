<?php
/**
 * These functions are needed to load Multisite.
 *
 * @since 3.0.0
 *
 * @package WordPress
 * @subpackage Multisite
 */

/**
 * Whether a subdomain configuration is enabled.
 *
 * @since 3.0.0
 *
 * @return bool True if subdomain configuration is enabled, false otherwise.
 */
function is_subdomain_install() {
	if ( defined( 'SUBDOMAIN_INSTALL' ) ) {
		return SUBDOMAIN_INSTALL;
	}

	return ( defined( 'VHOST' ) && 'yes' === VHOST );
}

/**
 * Returns array of network plugin files to be included in global scope.
 *
 * The default directory is wp-content/plugins. To change the default directory
 * manually, define `WP_PLUGIN_DIR` and `WP_PLUGIN_URL` in `wp-config.php`.
 *
 * @access private
 * @since 3.1.0
 *
 * @return string[] Array of absolute paths to files to include.
 */
function wp_get_active_network_plugins() {
	$active_plugins = (array) get_site_option( 'active_sitewide_plugins', array() );
	if ( empty( $active_plugins ) ) {
		return array();
	}

	$plugins        = array();
	$active_plugins = array_keys( $active_plugins );
	sort( $active_plugins );

	foreach ( $active_plugins as $plugin ) {
		if ( ! validate_file( $plugin )                     // $plugin must validate as file.
			&& str_ends_with( $plugin, '.php' )             // $plugin must end with '.php'.
			&& file_exists( WP_PLUGIN_DIR . '/' . $plugin ) // $plugin must exist.
			) {
			$plugins[] = WP_PLUGIN_DIR . '/' . $plugin;
		}
	}

	return $plugins;
}

/**
 * Checks status of current blog.
 *
 * Checks if the blog is deleted, inactive, archived, or spammed.
 *
 * Dies with a default message if the blog does not pass the check.
 *
 * To change the default message when a blog does not pass the check,
 * use the wp-content/blog-deleted.php, blog-inactive.php and
 * blog-suspended.php drop-ins.
 *
 * @since 3.0.0
 *
 * @return true|string Returns true on success, or drop-in file to include.
 */
function ms_site_check() {

	/**
	 * Filters checking the status of the current blog.
	 *
	 * @since 3.0.0
	 *
	 * @param bool|null $check Whether to skip the blog status check. Default null.
	 */
	$check = apply_filters( 'ms_site_check', null );
	if ( null !== $check ) {
		return true;
	}

	// Allow super admins to see blocked sites.
	if ( is_super_admin() ) {
		return true;
	}

	$blog = get_site();

	if ( '1' === $blog->deleted ) {
		if ( file_exists( WP_CONTENT_DIR . '/blog-deleted.php' ) ) {
			return WP_CONTENT_DIR . '/blog-deleted.php';
		} else {
			wp_die( __( 'This site is no longer available.' ), '', array( 'response' => 410 ) );
		}
	}

	if ( '2' === $blog->deleted ) {
		if ( file_exists( WP_CONTENT_DIR . '/blog-inactive.php' ) ) {
			return WP_CONTENT_DIR . '/blog-inactive.php';
		} else {
			$admin_email = str_replace( '@', ' AT ', get_site_option( 'admin_email', 'support@' . get_network()->domain ) );
			wp_die(
				sprintf(
					/* translators: %s: Admin email link. */
					__( 'This site has not been activated yet. If you are having problems activating your site, please contact %s.' ),
					sprintf( '<a href="mailto:%1$s">%1$s</a>', $admin_email )
				)
			);
		}
	}

	if ( '1' === $blog->archived || '1' === $blog->spam ) {
		if ( file_exists( WP_CONTENT_DIR . '/blog-suspended.php' ) ) {
			return WP_CONTENT_DIR . '/blog-suspended.php';
		} else {
			wp_die( __( 'This site has been archived or suspended.' ), '', array( 'response' => 410 ) );
		}
	}

	return true;
}

/**
 * Retrieves the closest matching network for a domain and path.
 *
 * @since 3.9.0
 *
 * @internal In 4.4.0, converted to a wrapper for WP_Network::get_by_path()
 *
 * @param string   $domain   Domain to check.
 * @param string   $path     Path to check.
 * @param int|null $segments Path segments to use. Defaults to null, or the full path.
 * @return WP_Network|false Network object if successful. False when no network is found.
 */
function get_network_by_path( $domain, $path, $segments = null ) {
	return WP_Network::get_by_path( $domain, $path, $segments );
}

/**
 * Retrieves the closest matching site object by its domain and path.
 *
 * This will not necessarily return an exact match for a domain and path. Instead, it
 * breaks the domain and path into pieces that are then used to match the closest
 * possibility from a query.
 *
 * The intent of this method is to match a site object during bootstrap for a
 * requested site address
 *
 * @since 3.9.0
 * @since 4.7.0 Updated to always return a `WP_Site` object.
 *
 * @param string   $domain   Domain to check.
 * @param string   $path     Path to check.
 * @param int|null $segments Path segments to use. Defaults to null, or the full path.
 * @return WP_Site|false Site object if successful. False when no site is found.
 */
function get_site_by_path( $domain, $path, $segments = null ) {
	$path_segments = array_filter( explode( '/', trim( $path, '/' ) ) );

	/**
	 * Filters the number of path segments to consider when searching for a site.
	 *
	 * @since 3.9.0
	 *
	 * @param int|null $segments The number of path segments to consider. WordPress by default looks at
	 *                           one path segment following the network path. The function default of
	 *                           null only makes sense when you know the requested path should match a site.
	 * @param string   $domain   The requested domain.
	 * @param string   $path     The requested path, in full.
	 */
	$segments = apply_filters( 'site_by_path_segments_count', $segments, $domain, $path );

	if ( null !== $segments && count( $path_segments ) > $segments ) {
		$path_segments = array_slice( $path_segments, 0, $segments );
	}

	$paths = array();

	while ( count( $path_segments ) ) {
		$paths[] = '/' . implode( '/', $path_segments ) . '/';
		array_pop( $path_segments );
	}

	$paths[] = '/';

	/**
	 * Determines a site by its domain and path.
	 *
	 * This allows one to short-circuit the default logic, perhaps by
	 * replacing it with a routine that is more optimal for your setup.
	 *
	 * Return null to avoid the short-circuit. Return false if no site
	 * can be found at the requested domain and path. Otherwise, return
	 * a site object.
	 *
	 * @since 3.9.0
	 *
	 * @param null|false|WP_Site $site     Site value to return by path. Default null
	 *                                     to continue retrieving the site.
	 * @param string             $domain   The requested domain.
	 * @param string             $path     The requested path, in full.
	 * @param int|null           $segments The suggested number of paths to consult.
	 *                                     Default null, meaning the entire path was to be consulted.
	 * @param string[]           $paths    The paths to search for, based on $path and $segments.
	 */
	$pre = apply_filters( 'pre_get_site_by_path', null, $domain, $path, $segments, $paths );
	if ( null !== $pre ) {
		if ( false !== $pre && ! $pre instanceof WP_Site ) {
			$pre = new WP_Site( $pre );
		}
		return $pre;
	}

	/*
	 * @todo
	 * Caching, etc. Consider alternative optimization routes,
	 * perhaps as an opt-in for plugins, rather than using the pre_* filter.
	 * For example: The segments filter can expand or ignore paths.
	 * If persistent caching is enabled, we could query the DB for a path <> '/'
	 * then cache whether we can just always ignore paths.
	 */

	/*
	 * Either www or non-www is supported, not both. If a www domain is requested,
	 * query for both to provide the proper redirect.
	 */
	$domains = array( $domain );
	if ( str_starts_with( $domain, 'www.' ) ) {
		$domains[] = substr( $domain, 4 );
	}

	$args = array(
		'number'                 => 1,
		'update_site_meta_cache' => false,
	);

	if ( count( $domains ) > 1 ) {
		$args['domain__in']               = $domains;
		$args['orderby']['domain_length'] = 'DESC';
	} else {
		$args['domain'] = array_shift( $domains );
	}

	if ( count( $paths ) > 1 ) {
		$args['path__in']               = $paths;
		$args['orderby']['path_length'] = 'DESC';
	} else {
		$args['path'] = array_shift( $paths );
	}

	$result = get_sites( $args );
	$site   = array_shift( $result );

	if ( $site ) {
		return $site;
	}

	return false;
}

/**
 * Identifies the network and site of a requested domain and path and populates the
 * corresponding network and site global objects as part of the multisite bootstrap process.
 *
 * Prior to 4.6.0, this was a procedural block in `ms-settings.php`. It was wrapped into
 * a function to facilitate unit tests. It should not be used outside of core.
 *
 * Usually, it's easier to query the site first, which then declares its network.
 * In limited situations, we either can or must find the network first.
 *
 * If a network and site are found, a `true` response will be returned so that the
 * request can continue.
 *
 * If neither a network or site is found, `false` or a URL string will be returned
 * so that either an error can be shown or a redirect can occur.
 *
 * @since 4.6.0
 * @access private
 *
 * @global WP_Network $current_site The current network.
 * @global WP_Site    $current_blog The current site.
 *
 * @param string $domain    The requested domain.
 * @param string $path      The requested path.
 * @param bool   $subdomain Optional. Whether a subdomain (true) or subdirectory (false) configuration.
 *                          Default false.
 * @return bool|string True if bootstrap successfully populated `$current_blog` and `$current_site`.
 *                     False if bootstrap could not be properly completed.
 *                     Redirect URL if parts exist, but the request as a whole can not be fulfilled.
 */
function ms_load_current_site_and_network( $domain, $path, $subdomain = false ) {
	global $current_site, $current_blog;

	// If the network is defined in wp-config.php, we can simply use that.
	if ( defined( 'DOMAIN_CURRENT_SITE' ) && defined( 'PATH_CURRENT_SITE' ) ) {
		$current_site         = new stdClass();
		$current_site->id     = defined( 'SITE_ID_CURRENT_SITE' ) ? SITE_ID_CURRENT_SITE : 1;
		$current_site->domain = DOMAIN_CURRENT_SITE;
		$current_site->path   = PATH_CURRENT_SITE;
		if ( defined( 'BLOG_ID_CURRENT_SITE' ) ) {
			$current_site->blog_id = BLOG_ID_CURRENT_SITE;
		} elseif ( defined( 'BLOGID_CURRENT_SITE' ) ) { // Deprecated.
			$current_site->blog_id = BLOGID_CURRENT_SITE;
		}

		if ( 0 === strcasecmp( $current_site->domain, $domain ) && 0 === strcasecmp( $current_site->path, $path ) ) {
			$current_blog = get_site_by_path( $domain, $path );
		} elseif ( '/' !== $current_site->path && 0 === strcasecmp( $current_site->domain, $domain ) && 0 === stripos( $path, $current_site->path ) ) {
			/*
			 * If the current network has a path and also matches the domain and path of the request,
			 * we need to look for a site using the first path segment following the network's path.
			 */
			$current_blog = get_site_by_path( $domain, $path, 1 + count( explode( '/', trim( $current_site->path, '/' ) ) ) );
		} else {
			// Otherwise, use the first path segment (as usual).
			$current_blog = get_site_by_path( $domain, $path, 1 );
		}
	} elseif ( ! $subdomain ) {
		/*
		 * A "subdomain" installation can be re-interpreted to mean "can support any domain".
		 * If we're not dealing with one of these installations, then the important part is determining
		 * the network first, because we need the network's path to identify any sites.
		 */
		$current_site = wp_cache_get( 'current_network', 'site-options' );
		if ( ! $current_site ) {
			// Are there even two networks installed?
			$networks = get_networks( array( 'number' => 2 ) );
			if ( count( $networks ) === 1 ) {
				$current_site = array_shift( $networks );
				wp_cache_add( 'current_network', $current_site, 'site-options' );
			} elseif ( empty( $networks ) ) {
				// A network not found hook should fire here.
				return false;
			}
		}

		if ( empty( $current_site ) ) {
			$current_site = WP_Network::get_by_path( $domain, $path, 1 );
		}

		if ( empty( $current_site ) ) {
			/**
			 * Fires when a network cannot be found based on the requested domain and path.
			 *
			 * At the time of this action, the only recourse is to redirect somewhere
			 * and exit. If you want to declare a particular network, do so earlier.
			 *
			 * @since 4.4.0
			 *
			 * @param string $domain       The domain used to search for a networ€µ     µ      µ     Î¶     ¶µ     Ìµ     Üµ     ğµ      ¶     ¶     2¶     F¶     X¶     b¶     n¶     ˆ¶      ¶NtxF¼¶     C`  ÿÿ                             ¸p€_ƒÈÿÿ¸p€_ƒÈÿÿ                                                                                                                 H                                     €!ÛVƒÈÿÿ                                                                                                                                         NtxF        C`                                 (r€_ƒÈÿÿ(r€_ƒÈÿÿ                                                                                                                 H                                     €!ÛVƒÈÿÿ                                                                                                                                         NtxF0K€   C`                                 ˜s€_ƒÈÿÿ˜s€_ƒÈÿÿ        `t€_ƒÈÿÿÀk€_ƒÈÿÿ                                                                                        H                                     €!ÛVƒÈÿÿ                       u€_ƒÈÿÿ°s€_ƒÈÿÿ                                                                                                 ñNtxFÏ	€   C`                                 u€_ƒÈÿÿu€_ƒÈÿÿ        Ğu€_ƒÈÿÿ`t€_ƒÈÿÿ                                                                                        H                                     €!ÛVƒÈÿÿ                      ğV€_ƒÈÿÿ u€_ƒÈÿÿ                                                                                                 QNtxFl€   C`                                 xv€_ƒÈÿÿxv€_ƒÈÿÿ        @w€_ƒÈÿÿ W€_ƒÈÿÿ                                                                                        H                                     €!ÛVƒÈÿÿ                       x€_ƒÈÿÿv€_ƒÈÿÿ                                                                                                  NtxF        C`                                 èw€_ƒÈÿÿèw€_ƒÈÿÿ        °x€_ƒÈÿÿ@w€_ƒÈÿÿ                                                                                        H                                     €!ÛVƒÈÿÿ                       R€_ƒÈÿÿ x€_ƒÈÿÿ                                                                                                  NtxF@q
€   C`                                 Xy€_ƒÈÿÿXy€_ƒÈÿÿ                                                                                                                 H                                     €!ÛVƒÈÿÿ                                                                                                                                         NtxF¶È¯G(örìC`  ÿÿ                             Èz€_ƒÈÿÿÈz€_ƒÈÿÿ        {€_ƒÈÿÿÙ,_ƒÈÿÿ                                                                                        H                                     €!ÛVƒÈÿÿ                      ğ›€_ƒÈÿÿàz€_ƒÈÿÿ                                                                                                  NtxF8u
€   C`                                 8|€_ƒÈÿÿ8|€_ƒÈÿÿ         }€_ƒÈÿÿĞ^€_ƒÈÿÿ                                                                                        H                             °d3]ƒÈÿÿ€!ÛVƒÈÿÿ                      t]ƒÈÿÿP|€_ƒÈÿÿ                                                                                                  NtxF w
€   C`                                 ¨}€_ƒÈÿÿ¨}€_ƒÈÿÿ                                                                                                                 H                                     €!ÛVƒÈÿÿ                                                                                                                                       dy in progress  connection refused      connection reset        cross device link       destination address required    device or resource busy directory not empty     executable format error file exists     file too large  filename too long       function not supported  host unreachable        identifier removed      illegal byte sequence   inappropriate io control oNtxFon      C`                                 ˆ€€_ƒÈÿÿˆ€€_ƒÈÿÿ        P€_ƒÈÿÿĞŒ€_ƒÈÿÿ                                                                                        H                                     €!ÛVƒÈÿÿ                      0–€_ƒÈÿÿ €€_ƒÈÿÿ                                                                                                 eNtxFr addresC`                                 ø€_ƒÈÿÿø€_ƒÈÿÿ        À‚€_ƒÈÿÿ ‘€_ƒÈÿÿ                                                                                        H                                     €!ÛVƒÈÿÿ                      €ƒ€_ƒÈÿÿ‚€_ƒÈÿÿ                                                                                                 rNtxF not supC`                                 hƒ€_ƒÈÿÿhƒ€_ƒÈÿÿ        0„€_ƒÈÿÿÀ‚€_ƒÈÿÿ                                                                                        H                                     €!ÛVƒÈÿÿ                      P ,_ƒÈÿÿ€ƒ€_ƒÈÿÿ                                                                                                 xNtxF†våA(örìC`                                 Ø„€_ƒÈÿÿØ„€_ƒÈÿÿ         …€_ƒÈÿÿ€ˆ€_ƒÈÿÿ                                                                                        H                                     €!ÛVƒÈÿÿ                      `€_ƒÈÿÿğ„€_ƒÈÿÿ                                                                                                 yNtxFˆ2 €   C`                                 H†€_ƒÈÿÿH†€_ƒÈÿÿ                                                                                                                 H                                     €!ÛVƒÈÿÿ                                       Ğ­^ƒÈÿÿ                                                                                         ¢NtxFlÄ €   C`                                 ¸‡€_ƒÈÿÿ¸‡€_ƒÈÿÿ        €ˆ€_ƒÈÿÿÀ™€_ƒÈÿÿ                                                                                        H                                     €!ÛVƒÈÿÿ                      ğ„€_ƒÈÿÿĞ‡€_ƒÈÿÿ                                                                                                 ÛNtxF¬Ü€   C`                                 (‰€_ƒÈÿÿ(‰€_ƒÈÿÿ        ğ‰€_ƒÈÿÿĞ£€_ƒÈÿÿ                                                                                        H                                     €!ÛVƒÈÿÿ                      °Š€_ƒÈÿÿ@‰€_ƒÈÿÿ                                                                                                  NtxF        C`                                 ˜Š€_ƒÈÿÿ˜Š€_ƒÈÿÿ        `‹€_ƒÈÿÿğ‰€_ƒÈÿÿ                                                                                        H                                     €!ÛVƒÈÿÿ                      à‘€_ƒÈÿÿ°Š€_ƒÈÿÿ                                                                                                 vNtxFPz€   C`                                 Œ€_ƒÈÿÿŒ€_ƒÈÿÿ        ĞŒ€_ƒÈÿÿÀT€_ƒÈÿÿ                                                                                        H                                     €!ÛVƒÈÿÿ                       €€_ƒÈÿÿ Œ€_ƒÈÿÿ                                                                                                 ÿNtxFÿÿÿÿÿÿÿÿC`                                 x€_ƒÈÿÿx€_ƒÈÿÿ                                  
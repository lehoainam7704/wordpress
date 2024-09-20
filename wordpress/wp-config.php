<?php
/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the installation.
 * You don't have to use the website, you can copy this file to "wp-config.php"
 * and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * Database settings
 * * Secret keys
 * * Database table prefix
 * * ABSPATH
 *
 * @link https://developer.wordpress.org/advanced-administration/wordpress/wp-config/
 *
 * @package WordPress
 */

// ** Database settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define( 'DB_NAME', 'wordpress' );

/** Database username */
define( 'DB_USER', 'root' );

/** Database password */
define( 'DB_PASSWORD', '' );

/** Database hostname */
define( 'DB_HOST', 'localhost' );

/** Database charset to use in creating database tables. */
define( 'DB_CHARSET', 'utf8mb4' );

/** The database collate type. Don't change this if in doubt. */
define( 'DB_COLLATE', '' );

/**#@+
 * Authentication unique keys and salts.
 *
 * Change these to different unique phrases! You can generate these using
 * the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}.
 *
 * You can change these at any point in time to invalidate all existing cookies.
 * This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define( 'AUTH_KEY',         'C0YNl%G. .W9j(`>@/jM9lwul;gnUFY4#Q 7~OL8IEEeG%AHUZ1skD#l@TMJh}qh' );
define( 'SECURE_AUTH_KEY',  '/-sVEsPk:1N9TwbW]Z`07FFy]T?qGe$KH=CL&Jez8?)WdTdRY+(/ X0-/UW|bv6%' );
define( 'LOGGED_IN_KEY',    'LDq2)_hXlB(XpBPpwo:t[0gjo><D0FpT-V!wN<[,Yl7& CK`_k`HLzm2u1^M&%+T' );
define( 'NONCE_KEY',        '8rm5d-&!Svq?5Ke^%Gu2=-GqZfa[B:EE(?T2DdYv_0e:,zD0:BrtWo^6weMR*<Z^' );
define( 'AUTH_SALT',        '&Rpe;x~TEIi@2X?q heH:E/;[T}ln^`Ju[9@X~&.5 )l|s,6`*eSLE gQuEe$azw' );
define( 'SECURE_AUTH_SALT', '~:4x) 99?YJ7oL7B~{{wWD:%/OR.gZ-W9eDUx{]C!xh V9[}Jf:xMv}M#m2)>|lz' );
define( 'LOGGED_IN_SALT',   'S!</h31GRMBl*+fS:lA^6hcsi1{$OJ,#LA7gZ+MRiXmzZ+1>Z:o5+W)oH)+.)>[4' );
define( 'NONCE_SALT',       'Mu$dJeDH^ogM_tIOBQa/-v)#0Xr>d%l-elYTe#_1u+AM651V0,{kVK9QAKoyP:6]' );

/**#@-*/

/**
 * WordPress database table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 */
$table_prefix = 'wp_wordpress';

/**
 * For developers: WordPress debugging mode.
 *
 * Change this to true to enable the display of notices during development.
 * It is strongly recommended that plugin and theme developers use WP_DEBUG
 * in their development environments.
 *
 * For information on other constants that can be used for debugging,
 * visit the documentation.
 *
 * @link https://developer.wordpress.org/advanced-administration/debug/debug-wordpress/
 */
define( 'WP_DEBUG', false );

/* Add any custom values between this line and the "stop editing" line. */



/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';

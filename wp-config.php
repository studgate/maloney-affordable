<?php
/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the installation.
 * You don't have to use the web site, you can copy this file to "wp-config.php"
 * and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * Database settings
 * * Secret keys
 * * Database table prefix
 * * Localized language
 * * ABSPATH
 *
 * @link https://wordpress.org/support/article/editing-wp-config-php/
 *
 * @package WordPress
 */

// ** Database settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define( 'DB_NAME', 'local' );

/** Database username */
define( 'DB_USER', 'root' );

/** Database password */
define( 'DB_PASSWORD', 'root' );

/** Database hostname */
define( 'DB_HOST', 'localhost' );

/** Database charset to use in creating database tables. */
define( 'DB_CHARSET', 'utf8' );

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
define( 'AUTH_KEY',          '=y3RF=lYWqX#c*uS2&O,fQ--}F{bt=E}Kzp6Na}odJS>@4d+z]KY6{Fti~r-tFk2' );
define( 'SECURE_AUTH_KEY',   '(Bi].Kvc3z?FjhVVc_~?[tp`No)(a=t?k/a5lc10CFK$^GwXd<K]P0nK*)C1)1&s' );
define( 'LOGGED_IN_KEY',     '0yh`]Zf}5[z)/n#+:c<KB2+aPyb#(}l8j*lErWseVey>n&-9|Xub7mgzXv)8+ALH' );
define( 'NONCE_KEY',         '9y:5h8O|ZzD[FzGIbS,A:6x&wa1>YM!+pJk-]@$_mXq%ti`8Tf!Fta.L0D>ReNat' );
define( 'AUTH_SALT',         '2VFr%w3@iN6 =j>Ezp+Va8!XOX7m51S9)TALz3!kX#?$ef4rtnTJhFMGSprGv5cz' );
define( 'SECURE_AUTH_SALT',  '[i}Z?3)R)e~HjQ!Ri@Bomvf?Bxki*h39t.EW=u^JBz%H!`+tX;c4*}nrR$P;e*T?' );
define( 'LOGGED_IN_SALT',    'EN|x.~sMc+L QdQVwh@CgUS,s3`1R2$.Vo$u hk5I8ad*~I4jl:^*XZ7l$Rrd7:_' );
define( 'NONCE_SALT',        '{SpLH<Q0_H,}+| ^)P8hx,HJcm}6t3+-QP11@P-x#t)47U(JN<USF:,bl/I24,/ ' );
define( 'WP_CACHE_KEY_SALT', '3 wOG2*B6N^`x%G}B1rqz*s[M=&$>Y)x##qz3M,Br{iblhsbK,Aw> O>?TLNt&6&' );


/**#@-*/

/**
 * WordPress database table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 */
$table_prefix = 'wp_';


/* Add any custom values between this line and the "stop editing" line. */



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
 * @link https://wordpress.org/support/article/debugging-in-wordpress/
 */
if ( ! defined( 'WP_DEBUG' ) ) {
	define( 'WP_DEBUG', false );
}

define( 'WP_ENVIRONMENT_TYPE', 'local' );
/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';

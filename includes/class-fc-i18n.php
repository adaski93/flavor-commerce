<?php
/**
 * Flavor Commerce — Custom i18n System
 *
 * English keys as identifiers, PL and EN as equal translation files.
 * Separate files for frontend and admin contexts.
 *
 * @package Flavor Commerce
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class FC_i18n {

    /** @var array Loaded translations: [ 'frontend' => [...], 'admin' => [...] ] */
    private static $strings = array();

    /** @var string Current context: 'frontend' or 'admin' */
    private static $context = 'frontend';

    /** @var string Resolved language code */
    private static $lang = '';

    /** @var bool Whether init() has been called */
    private static $initialised = false;

    /**
     * Initialise the i18n system. Safe to call multiple times.
     */
    public static function init() {
        if ( self::$initialised ) {
            return;
        }
        self::$initialised = true;

        // Determine context (AJAX requests go through admin-ajax.php
        // so is_admin() returns true, but they serve frontend content)
        if ( wp_doing_ajax() ) {
            self::$context = 'frontend';
        } else {
            self::$context = is_admin() ? 'admin' : 'frontend';
        }

        // Resolve language
        $option_key = ( self::$context === 'admin' ) ? 'fc_admin_lang' : 'fc_frontend_lang';
        self::$lang = get_option( $option_key, 'pl' );

        // Load the file for this context + language
        self::load( self::$context, self::$lang );
    }

    /**
     * Force-reload translations (e.g. after changing the language option at runtime).
     */
    public static function reload() {
        self::$strings      = array();
        self::$initialised  = false;
        self::init();
    }

    /**
     * Load a translation file if not already loaded.
     *
     * @param string $context 'frontend' or 'admin'
     * @param string $lang    Language code, e.g. 'pl', 'en'
     */
    private static function load( $context, $lang ) {
        if ( isset( self::$strings[ $context ] ) ) {
            return;
        }

        $file = FC_PLUGIN_DIR . "languages/{$lang}/{$context}.php";

        if ( file_exists( $file ) ) {
            $data = include $file;
            if ( is_array( $data ) ) {
                self::$strings[ $context ] = $data;
                return;
            }
        }

        // Fallback: try default language (pl)
        if ( $lang !== 'pl' ) {
            $fallback = FC_PLUGIN_DIR . "languages/pl/{$context}.php";
            if ( file_exists( $fallback ) ) {
                $data = include $fallback;
                if ( is_array( $data ) ) {
                    self::$strings[ $context ] = $data;
                    return;
                }
            }
        }

        self::$strings[ $context ] = array();
    }

    /**
     * Get a translated string.
     *
     * @param string      $key     English key identifier
     * @param string|null $context Force context ('frontend' or 'admin'). Null = auto.
     * @return string Translated string, or key name as fallback.
     */
    public static function get( $key, $context = null ) {
        if ( ! self::$initialised ) {
            self::init();
        }

        $ctx = $context ?: self::$context;

        // Make sure the requested context is loaded
        if ( ! isset( self::$strings[ $ctx ] ) ) {
            self::load( $ctx, self::$lang );
        }

        if ( isset( self::$strings[ $ctx ][ $key ] ) ) {
            return self::$strings[ $ctx ][ $key ];
        }

        // Cross-context fallback: if key not found in current context, try the other
        $other = ( $ctx === 'frontend' ) ? 'admin' : 'frontend';
        if ( ! isset( self::$strings[ $other ] ) ) {
            self::load( $other, self::$lang );
        }
        if ( isset( self::$strings[ $other ][ $key ] ) ) {
            return self::$strings[ $other ][ $key ];
        }

        // Ultimate fallback: the key itself (useful for debugging)
        return $key;
    }

    /**
     * Echo a translated string.
     *
     * @param string      $key
     * @param string|null $context
     */
    public static function out( $key, $context = null ) {
        echo self::get( $key, $context );
    }

    /**
     * Get available languages by scanning the languages/ directory.
     *
     * @return array [ 'pl' => 'Polski', 'en' => 'English', ... ]
     */
    public static function get_available_languages() {
        $dir  = FC_PLUGIN_DIR . 'languages/';
        $langs = array();

        if ( ! is_dir( $dir ) ) {
            return $langs;
        }

        foreach ( scandir( $dir ) as $entry ) {
            if ( $entry === '.' || $entry === '..' ) continue;
            if ( is_dir( $dir . $entry ) ) {
                // Try to read a meta file, otherwise use folder name
                $meta_file = $dir . $entry . '/meta.php';
                if ( file_exists( $meta_file ) ) {
                    $meta = include $meta_file;
                    $langs[ $entry ] = isset( $meta['name'] ) ? $meta['name'] : strtoupper( $entry );
                } else {
                    $langs[ $entry ] = strtoupper( $entry );
                }
            }
        }

        return $langs;
    }
}

/* ──────────────────────────────────────────────
 * Global helper functions
 * ────────────────────────────────────────────── */

/**
 * Return a translated string (frontend context by default).
 *
 * @param string      $key
 * @param string|null $context
 * @return string
 */
function fc__( $key, $context = null ) {
    return FC_i18n::get( $key, $context );
}

/**
 * Echo a translated string (frontend context by default).
 *
 * @param string      $key
 * @param string|null $context
 */
function fc_e( $key, $context = null ) {
    FC_i18n::out( $key, $context );
}

/**
 * Plural-aware translated string: returns sprintf($count === 1 ? singular : plural, $count).
 *
 * @param string $singular_key Translation key for singular form
 * @param string $plural_key   Translation key for plural form
 * @param int    $count        The number
 * @return string
 */
function fc_n( $singular_key, $plural_key, $count ) {
    $key = ( (int) $count === 1 ) ? $singular_key : $plural_key;
    return sprintf( fc__( $key ), $count );
}

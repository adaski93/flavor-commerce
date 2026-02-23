<?php
/**
 * GitHub-based auto-updater for Flavor Commerce plugin.
 *
 * Checks GitHub Releases API for new versions and integrates
 * with the WordPress plugin update system so updates appear
 * in Dashboard → Updates just like any wp.org plugin.
 *
 * @package Flavor_Commerce
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class FC_Updater {

    /** @var string  GitHub owner/repo, e.g. "adaski93/flavor-commerce" */
    private $repo;

    /** @var string  Current plugin version (from plugin header). */
    private $version;

    /** @var string  Plugin basename, e.g. "flavor-commerce/flavor-commerce.php" */
    private $basename;

    /** @var string  Plugin slug, e.g. "flavor-commerce" */
    private $slug;

    /** @var object|null  Cached release data for this request. */
    private $release = null;

    /**
     * @param string $plugin_file  __FILE__ of the main plugin file.
     * @param string $github_repo  "owner/repo" on GitHub.
     */
    public function __construct( $plugin_file, $github_repo ) {
        $this->repo     = $github_repo;
        $this->basename = plugin_basename( $plugin_file );
        $this->slug     = dirname( $this->basename );

        // Read version from plugin header.
        if ( ! function_exists( 'get_plugin_data' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        $data = get_plugin_data( $plugin_file, false, false );
        $this->version = $data['Version'];

        // Hook into WordPress update system.
        add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'check_update' ) );
        add_filter( 'plugins_api',                            array( $this, 'plugin_info' ), 10, 3 );
        add_filter( 'upgrader_post_install',                  array( $this, 'post_install' ), 10, 3 );
    }

    /**
     * Fetch the latest release from GitHub API (cached per request).
     *
     * @return object|false  Release object or false on failure.
     */
    private function get_latest_release() {
        if ( $this->release !== null ) {
            return $this->release;
        }

        $url = 'https://api.github.com/repos/' . $this->repo . '/releases/latest';

        $response = wp_remote_get( $url, array(
            'timeout' => 10,
            'headers' => array(
                'Accept'     => 'application/vnd.github.v3+json',
                'User-Agent' => 'WordPress/' . get_bloginfo( 'version' ) . '; ' . home_url(),
            ),
        ) );

        if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
            $this->release = false;
            return false;
        }

        $body = json_decode( wp_remote_retrieve_body( $response ) );

        if ( empty( $body->tag_name ) ) {
            $this->release = false;
            return false;
        }

        $this->release = $body;
        return $this->release;
    }

    /**
     * Extract a clean version number from the tag name.
     * Accepts "v1.2.3", "1.2.3", "release-1.2.3" etc.
     *
     * @param string $tag  Git tag name.
     * @return string      Semver string like "1.2.3".
     */
    private function tag_to_version( $tag ) {
        return ltrim( preg_replace( '/^[^0-9]*/', '', $tag ), 'v' );
    }

    /**
     * Find the .zip download URL from the release assets or fallback to zipball.
     *
     * @param object $release  GitHub release object.
     * @return string          ZIP download URL.
     */
    private function get_zip_url( $release ) {
        // Prefer an uploaded .zip asset (e.g. "flavor-commerce.zip").
        if ( ! empty( $release->assets ) ) {
            foreach ( $release->assets as $asset ) {
                if ( substr( $asset->name, -4 ) === '.zip' ) {
                    return $asset->browser_download_url;
                }
            }
        }
        // Fallback: GitHub auto-generated source zip.
        return $release->zipball_url;
    }

    /**
     * Tell WordPress a new version is available (if it is).
     *
     * @param object $transient  The update_plugins transient.
     * @return object
     */
    public function check_update( $transient ) {
        if ( empty( $transient->checked ) ) {
            return $transient;
        }

        $release = $this->get_latest_release();
        if ( ! $release ) {
            return $transient;
        }

        $remote_version = $this->tag_to_version( $release->tag_name );

        if ( version_compare( $remote_version, $this->version, '>' ) ) {
            $transient->response[ $this->basename ] = (object) array(
                'slug'        => $this->slug,
                'plugin'      => $this->basename,
                'new_version' => $remote_version,
                'url'         => $release->html_url,
                'package'     => $this->get_zip_url( $release ),
                'icons'       => array(),
                'banners'     => array(),
                'tested'      => '',
                'requires'    => '5.0',
                'requires_php'=> '7.4',
            );
        }

        return $transient;
    }

    /**
     * Provide plugin details for the "View details" popup.
     *
     * @param false|object|array $result
     * @param string             $action
     * @param object             $args
     * @return false|object
     */
    public function plugin_info( $result, $action, $args ) {
        if ( $action !== 'plugin_information' || ! isset( $args->slug ) || $args->slug !== $this->slug ) {
            return $result;
        }

        $release = $this->get_latest_release();
        if ( ! $release ) {
            return $result;
        }

        $remote_version = $this->tag_to_version( $release->tag_name );

        return (object) array(
            'name'            => 'Flavor Commerce',
            'slug'            => $this->slug,
            'version'         => $remote_version,
            'author'          => '<a href="https://flavor-theme.dev">Developer</a>',
            'homepage'        => 'https://flavor-theme.dev',
            'requires'        => '5.0',
            'tested'          => '',
            'requires_php'    => '7.4',
            'download_link'   => $this->get_zip_url( $release ),
            'sections'        => array(
                'description'  => 'Prosta, ale kompletna wtyczka eCommerce dla WordPress.',
                'changelog'    => nl2br( esc_html( $release->body ?? '' ) ),
            ),
        );
    }

    /**
     * After upgrade, make sure the directory name matches the plugin slug.
     * Handles both GitHub source zips (owner-repo-hash) and uploaded asset zips
     * that may contain a nested folder (e.g. flavor-commerce/flavor-commerce/).
     *
     * @param bool  $response
     * @param array $hook_extra
     * @param array $result
     * @return array
     */
    public function post_install( $response, $hook_extra, $result ) {
        if ( ! isset( $hook_extra['plugin'] ) || $hook_extra['plugin'] !== $this->basename ) {
            return $result;
        }

        global $wp_filesystem;

        $dest = untrailingslashit( $result['destination'] );

        // If the extracted folder contains a single sub-folder with our slug inside,
        // we need to "flatten" it — move contents up one level.
        $nested = $dest . '/' . $this->slug;
        if ( $wp_filesystem->is_dir( $nested ) && $wp_filesystem->exists( $nested . '/' . basename( $this->basename ) ) ) {
            // Move nested contents to a temp dir, remove outer, rename temp.
            $tmp = WP_PLUGIN_DIR . '/' . $this->slug . '-tmp-' . time();
            $wp_filesystem->move( $nested, $tmp );
            $wp_filesystem->delete( $dest, true );
            $wp_filesystem->move( $tmp, WP_PLUGIN_DIR . '/' . $this->slug );
            $result['destination'] = WP_PLUGIN_DIR . '/' . $this->slug;
        } else {
            // Standard case: just rename the extracted folder to our slug.
            $proper_dir = WP_PLUGIN_DIR . '/' . $this->slug;
            if ( $dest !== $proper_dir ) {
                $wp_filesystem->move( $dest, $proper_dir );
                $result['destination'] = $proper_dir;
            }
        }

        // Re-activate if it was active.
        if ( is_plugin_active( $this->basename ) ) {
            activate_plugin( $this->basename );
        }

        return $result;
    }
}

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

    /** @var string  Transient name for caching GitHub release data. */
    private $transient_key = 'fc_github_release';

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
        add_filter( 'site_transient_update_plugins',         array( $this, 'check_update' ) );
        add_filter( 'plugins_api',                            array( $this, 'plugin_info' ), 10, 3 );
        add_filter( 'upgrader_post_install',                  array( $this, 'post_install' ), 10, 3 );

        // Link "Sprawdź aktualizacje" w wierszu wtyczki.
        add_filter( 'plugin_action_links_' . $this->basename, array( $this, 'action_links' ) );

        // Obsługa ręcznego sprawdzenia aktualizacji.
        add_action( 'admin_init', array( $this, 'handle_force_check' ) );
    }

    /**
     * Fetch the latest release from GitHub API (cached in transient for 6 hours).
     *
     * @param bool $force  Skip cache and fetch fresh data.
     * @return object|false  Release object or false on failure.
     */
    private function get_latest_release( $force = false ) {
        if ( ! $force && $this->release !== null ) {
            return $this->release;
        }

        // Check transient cache first (6h).
        if ( ! $force ) {
            $cached = get_transient( $this->transient_key );
            if ( $cached !== false ) {
                $this->release = $cached;
                return $this->release;
            }
        }

        $url = 'https://api.github.com/repos/' . $this->repo . '/releases/latest';

        $response = wp_remote_get( $url, array(
            'timeout' => 15,
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

        // Cache for 6 hours.
        set_transient( $this->transient_key, $body, 6 * HOUR_IN_SECONDS );

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
        // Nie blokuj gdy $transient->checked jest pusty — wiele serwerów tego nie wypełnia.
        if ( ! is_object( $transient ) ) {
            $transient = new stdClass();
        }

        $release = $this->get_latest_release();
        if ( ! $release ) {
            return $transient;
        }

        $remote_version = $this->tag_to_version( $release->tag_name );

        if ( version_compare( $remote_version, $this->version, '>' ) ) {
            if ( ! isset( $transient->response ) || ! is_array( $transient->response ) ) {
                $transient->response = array();
            }
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
     * Add "Check for updates" link in plugin action links.
     */
    public function action_links( $links ) {
        $check_url = wp_nonce_url(
            add_query_arg( 'fc_force_update_check', '1', admin_url( 'plugins.php' ) ),
            'fc_force_update_check'
        );
        $links[] = '<a href="' . esc_url( $check_url ) . '">' . esc_html__( 'Sprawdź aktualizacje', 'flavor-commerce' ) . '</a>';
        return $links;
    }

    /**
     * Handle manual update check — clears cache and forces re-check.
     */
    public function handle_force_check() {
        if ( empty( $_GET['fc_force_update_check'] ) || ! current_user_can( 'update_plugins' ) ) {
            return;
        }
        if ( ! wp_verify_nonce( $_GET['_wpnonce'] ?? '', 'fc_force_update_check' ) ) {
            return;
        }

        // Clear our cached release data.
        delete_transient( $this->transient_key );
        $this->release = null;

        // Force WordPress to re-check plugin updates.
        delete_site_transient( 'update_plugins' );

        // Fetch fresh data from GitHub.
        $release = $this->get_latest_release( true );

        if ( $release ) {
            $remote_version = $this->tag_to_version( $release->tag_name );
            if ( version_compare( $remote_version, $this->version, '>' ) ) {
                // Inject update into transient immediately.
                $transient = get_site_transient( 'update_plugins' );
                if ( ! is_object( $transient ) ) {
                    $transient = new stdClass();
                }
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
                set_site_transient( 'update_plugins', $transient );

                wp_redirect( admin_url( 'plugins.php?fc_update_found=1' ) );
                exit;
            }
        }

        wp_redirect( admin_url( 'plugins.php?fc_update_found=0' ) );
        exit;
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

        // Clear update cache after successful install.
        delete_transient( $this->transient_key );

        // Re-activate if it was active.
        if ( is_plugin_active( $this->basename ) ) {
            activate_plugin( $this->basename );
        }

        return $result;
    }
}

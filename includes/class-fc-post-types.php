<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Rejestracja Custom Post Types: Produkty i Zamówienia
 */
class FC_Post_Types {

    public static function init() {
        add_action( 'init', array( __CLASS__, 'register' ) );

        // Niestandardowe statusy (fc_published, fc_preorder) muszą być liczone w term.count
        add_filter( 'get_terms_args', array( __CLASS__, 'fix_hide_empty_for_fc_taxonomies' ), 10, 2 );

        // Przelicz term counts po zapisie/usunięciu produktu
        add_action( 'save_post_fc_product', array( __CLASS__, 'recount_terms' ), 20 );
        add_action( 'delete_post', array( __CLASS__, 'recount_terms_on_delete' ), 20 );

        // Jednorazowa migracja term counts
        add_action( 'admin_init', array( __CLASS__, 'maybe_recount_all_terms' ) );
    }

    public static function register() {
        // Produkty
        register_post_type( 'fc_product', array(
            'labels' => array(
                'name'               => fc__( 'order_products' ),
                'singular_name'      => fc__( 'order_product' ),
                'add_new'            => fc__( 'pt_add_product' ),
                'add_new_item'       => fc__( 'pt_add_new_product' ),
                'edit_item'          => fc__( 'pt_edit_product' ),
                'new_item'           => fc__( 'pt_new_product' ),
                'view_item'          => fc__( 'pt_view_product' ),
                'search_items'       => fc__( 'pt_search_products' ),
                'not_found'          => fc__( 'pt_no_products_found' ),
                'not_found_in_trash' => fc__( 'pt_no_products_found_in_trash' ),
                'all_items'          => fc__( 'pt_all_products' ),
                'menu_name'          => fc__( 'order_products' ),
            ),
            'public'             => true,
            'has_archive'        => true,
            'rewrite'            => array( 'slug' => 'produkt' ),
            'supports'           => array( 'title', 'editor', 'thumbnail', 'excerpt' ),
            'menu_icon'          => 'dashicons-cart',
            'menu_position'      => 26,
            'show_in_rest'       => true,
        ) );

        // Kategorie produktów
        register_taxonomy( 'fc_product_cat', 'fc_product', array(
            'labels' => array(
                'name'              => fc__( 'pt_categories' ),
                'singular_name'     => fc__( 'pt_category' ),
                'search_items'      => fc__( 'pt_search_categories' ),
                'all_items'         => fc__( 'pt_all_categories' ),
                'parent_item'       => fc__( 'pt_parent_category' ),
                'parent_item_colon' => fc__( 'pt_parent_category_2' ),
                'edit_item'         => fc__( 'pt_edit_category' ),
                'update_item'       => fc__( 'pt_update_category' ),
                'add_new_item'      => fc__( 'pt_add_new_category' ),
                'new_item_name'     => fc__( 'pt_new_category_name' ),
                'menu_name'         => fc__( 'pt_categories' ),
            ),
            'hierarchical'      => true,
            'show_ui'           => true,
            'show_admin_column' => true,
            'rewrite'           => array( 'slug' => 'kategoria-produktu' ),
            'show_in_rest'      => true,
        ) );

        // Marki produktów
        register_taxonomy( 'fc_product_brand', 'fc_product', array(
            'labels' => array(
                'name'              => fc__( 'pt_brands' ),
                'singular_name'     => fc__( 'pt_brand' ),
                'search_items'      => fc__( 'pt_search_brands' ),
                'all_items'         => fc__( 'pt_all_brands' ),
                'edit_item'         => fc__( 'pt_edit_brand' ),
                'update_item'       => fc__( 'pt_update_brand' ),
                'add_new_item'      => fc__( 'pt_add_new_brand' ),
                'new_item_name'     => fc__( 'pt_new_brand_name' ),
                'menu_name'         => fc__( 'pt_brands' ),
            ),
            'hierarchical'      => false,
            'show_ui'           => true,
            'show_admin_column' => true,
            'rewrite'           => array( 'slug' => 'marka' ),
            'show_in_rest'      => true,
        ) );

        // Klasy wysyłkowe
        register_taxonomy( 'fc_shipping_class', 'fc_product', array(
            'labels' => array(
                'name'          => fc__( 'pt_shipping_classes' ),
                'singular_name' => fc__( 'pt_shipping_class' ),
                'search_items'  => fc__( 'pt_search_shipping_classes' ),
                'all_items'     => fc__( 'pt_all_shipping_classes' ),
                'edit_item'     => fc__( 'pt_edit_shipping_class' ),
                'update_item'   => fc__( 'pt_update_shipping_class' ),
                'add_new_item'  => fc__( 'pt_add_new_shipping_class' ),
                'new_item_name' => fc__( 'pt_new_shipping_class' ),
                'menu_name'     => fc__( 'pt_shipping_classes' ),
            ),
            'hierarchical'      => false,
            'show_ui'           => true,
            'show_in_menu'      => false,
            'show_admin_column' => false,
            'rewrite'           => false,
            'show_in_rest'      => true,
        ) );

        // Status: Opublikowany (widoczny w sklepie)
        register_post_status( 'fc_published', array(
            'label'                     => fc__( 'pt_published' ),
            'public'                    => true,
            'exclude_from_search'       => false,
            'show_in_admin_all_list'    => true,
            'show_in_admin_status_list' => true,
            'label_count'               => _n_noop(
                fc__( 'pt_published_count' ),
                fc__( 'pt_published_count_plural' ),
                'flavor-commerce'
            ),
        ) );

        // Status: Szkic
        register_post_status( 'fc_draft', array(
            'label'                     => fc__( 'pt_draft' ),
            'public'                    => false,
            'exclude_from_search'       => true,
            'show_in_admin_all_list'    => true,
            'show_in_admin_status_list' => true,
            'label_count'               => _n_noop(
                fc__( 'pt_draft_2' ),
                fc__( 'pt_drafts' ),
                'flavor-commerce'
            ),
        ) );

        // Status: Ukryty (niewidoczny w sklepie, możliwość zaplanowanej publikacji)
        register_post_status( 'fc_hidden', array(
            'label'                     => fc__( 'pt_hidden_2' ),
            'public'                    => false,
            'exclude_from_search'       => true,
            'show_in_admin_all_list'    => true,
            'show_in_admin_status_list' => true,
            'label_count'               => _n_noop(
                fc__( 'pt_hidden_3' ),
                fc__( 'pt_hidden' ),
                'flavor-commerce'
            ),
        ) );

        // Status: Preorder (przedsprzedaż — z opcjonalną datą publikacji i datą wysyłki)
        register_post_status( 'fc_preorder', array(
            'label'                     => fc__( 'pt_preorder' ),
            'public'                    => true,
            'exclude_from_search'       => false,
            'show_in_admin_all_list'    => true,
            'show_in_admin_status_list' => true,
            'label_count'               => _n_noop(
                fc__( 'pt_preorder_2' ),
                fc__( 'pt_preorders' ),
                'flavor-commerce'
            ),
        ) );

        // Status: Prywatny (produkty użytkowników, np. z kalkulatora 3D)
        register_post_status( 'fc_private', array(
            'label'                     => fc__( 'pt_private_2' ),
            'public'                    => false,
            'exclude_from_search'       => true,
            'show_in_admin_all_list'    => true,
            'show_in_admin_status_list' => true,
            'label_count'               => _n_noop(
                fc__( 'pt_private_3' ),
                fc__( 'pt_private' ),
                'flavor-commerce'
            ),
        ) );

        // Status: W koszu
        register_post_status( 'fc_trash', array(
            'label'                     => fc__( 'pt_in_trash' ),
            'public'                    => false,
            'exclude_from_search'       => true,
            'show_in_admin_all_list'    => false,
            'show_in_admin_status_list' => true,
            'internal'                  => true,
            'label_count'               => _n_noop(
                fc__( 'pt_in_trash_2' ),
                fc__( 'pt_in_trash_2' ),
                'flavor-commerce'
            ),
        ) );

        // Zamówienia
        register_post_type( 'fc_order', array(
            'labels' => array(
                'name'               => fc__( 'pt_orders' ),
                'singular_name'      => fc__( 'pt_order' ),
                'add_new'            => fc__( 'pt_add_order' ),
                'add_new_item'       => fc__( 'pt_add_new_order' ),
                'edit_item'          => fc__( 'pt_edit_order' ),
                'new_item'           => fc__( 'pt_new_order' ),
                'view_item'          => fc__( 'pt_view_order' ),
                'search_items'       => fc__( 'pt_search_orders' ),
                'not_found'          => fc__( 'pt_no_orders_found' ),
                'not_found_in_trash' => fc__( 'pt_no_orders_found_in_trash' ),
                'all_items'          => fc__( 'pt_all_orders' ),
                'menu_name'          => fc__( 'pt_orders' ),
            ),
            'public'             => false,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'capability_type'    => 'post',
            'supports'           => array( 'title' ),
            'menu_icon'          => 'dashicons-list-view',
            'menu_position'      => 27,
        ) );
    }

    /**
     * WordPress domyślnie liczy term.count tylko dla statusu 'publish'.
     * Nasze produkty mają statusy fc_published / fc_preorder,
     * więc get_terms z hide_empty=true zwraca pustą tablicę.
     *
     * Rozwiązanie: zamiast modyfikować core query, przeliczamy term.count
     * poprawnie i utrzymujemy je aktualne.
     * Dodatkowo: wymuszamy hide_empty=false i filtrujemy ręcznie w render_sidebar_blocks
     * (nie — to zbyt inwazyjne). Zamiast tego: hook na _update_post_term_count.
     */

    /**
     * Filtruj argumenty get_terms dla taksonomii fc_product_*.
     * Poprawne term counts sprawiają że hide_empty działa poprawnie.
     */
    public static function fix_hide_empty_for_fc_taxonomies( $args, $taxonomies ) {
        return $args;
    }

    /**
     * Przelicz term counts dla taksonomii przypisanych do zapisywanego produktu.
     */
    public static function recount_terms( $post_id ) {
        if ( get_post_type( $post_id ) !== 'fc_product' ) return;
        // Defer recount to shutdown to batch multiple saves
        if ( ! has_action( 'shutdown', array( __CLASS__, 'do_recount_terms' ) ) ) {
            add_action( 'shutdown', array( __CLASS__, 'do_recount_terms' ) );
        }
    }

    /**
     * Przelicz term counts po usunięciu produktu.
     */
    public static function recount_terms_on_delete( $post_id ) {
        if ( get_post_type( $post_id ) !== 'fc_product' ) return;
        self::do_recount_terms();
    }

    /**
     * Jednorazowe przeliczenie — uruchamia się raz po aktualizacji.
     */
    public static function maybe_recount_all_terms() {
        $done = get_option( 'fc_term_counts_fixed', false );
        if ( $done ) return;
        self::do_recount_terms();
        update_option( 'fc_term_counts_fixed', true );
    }

    /**
     * Przelicz term.count dla taksonomii fc_product_cat, fc_product_brand
     * uwzględniając niestandardowe statusy fc_published i fc_preorder.
     */
    public static function do_recount_terms() {
        global $wpdb;

        $taxonomies = array( 'fc_product_cat', 'fc_product_brand', 'fc_shipping_class' );
        $statuses   = array( 'fc_published', 'fc_preorder' );
        $statuses_in = "'" . implode( "','", $statuses ) . "'";

        foreach ( $taxonomies as $taxonomy ) {
            // Pobierz wszystkie termy tej taksonomii
            $terms = $wpdb->get_results( $wpdb->prepare(
                "SELECT t.term_id, tt.term_taxonomy_id
                 FROM {$wpdb->terms} t
                 INNER JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
                 WHERE tt.taxonomy = %s",
                $taxonomy
            ) );

            if ( empty( $terms ) ) continue;

            foreach ( $terms as $term ) {
                // Policz posty z naszymi statusami
                $count = (int) $wpdb->get_var( $wpdb->prepare(
                    "SELECT COUNT(DISTINCT tr.object_id)
                     FROM {$wpdb->term_relationships} tr
                     INNER JOIN {$wpdb->posts} p ON tr.object_id = p.ID
                     WHERE tr.term_taxonomy_id = %d
                       AND p.post_type = 'fc_product'
                       AND p.post_status IN ({$statuses_in})",
                    $term->term_taxonomy_id
                ) );

                // Zaktualizuj count w term_taxonomy
                $wpdb->update(
                    $wpdb->term_taxonomy,
                    array( 'count' => $count ),
                    array( 'term_taxonomy_id' => $term->term_taxonomy_id )
                );
            }
        }

        // Wyczyść cache termów dla wszystkich taksonomii
        foreach ( $taxonomies as $taxonomy ) {
            $tax_terms = get_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => false, 'fields' => 'ids' ) );
            if ( ! is_wp_error( $tax_terms ) && ! empty( $tax_terms ) ) {
                clean_term_cache( $tax_terms, $taxonomy );
            }
        }
    }
}

<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * System recenzji produktów — tylko dla zalogowanych kupujących
 */
class FC_Reviews {

    public static function init() {
        // Dodaj obsługę komentarzy dla fc_product
        add_action( 'init', array( __CLASS__, 'enable_comments' ) );

        // Przetwarzanie formularza recenzji
        add_action( 'init', array( __CLASS__, 'process_review' ) );

        // Zapisz ocenę jako meta komentarza
        add_action( 'comment_post', array( __CLASS__, 'save_rating' ), 10, 1 );
    }

    /**
     * Włącz komentarze dla fc_product
     */
    public static function enable_comments() {
        add_post_type_support( 'fc_product', 'comments' );
    }

    /**
     * Sprawdź czy użytkownik kupił produkt
     */
    public static function user_has_purchased( $product_id, $user_id = 0 ) {
        if ( ! $user_id ) $user_id = get_current_user_id();
        if ( ! $user_id ) return false;

        global $wpdb;

        // Use SQL with LIKE to search serialized _fc_order_items for the product ID
        // This avoids loading ALL orders into memory
        $product_id_int = intval( $product_id );

        $found = $wpdb->get_var( $wpdb->prepare(
            "SELECT p.ID
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} mc ON p.ID = mc.post_id AND mc.meta_key = '_fc_customer_id' AND mc.meta_value = %s
             INNER JOIN {$wpdb->postmeta} ms ON p.ID = ms.post_id AND ms.meta_key = '_fc_order_status' AND ms.meta_value IN ('processing','shipped','completed')
             INNER JOIN {$wpdb->postmeta} mi ON p.ID = mi.post_id AND mi.meta_key = '_fc_order_items'
             WHERE p.post_type = 'fc_order' AND p.post_status = 'publish'
               AND mi.meta_value LIKE %s
             LIMIT 1",
            $user_id,
            '%\"product_id\";' . ( is_int( $product_id_int ) ? 'i:' . $product_id_int : 's:%\"' . $product_id_int . '\"%' ) . '%'
        ) );

        // If SQL LIKE found a candidate, verify with deserialization (LIKE can have false positives)
        if ( $found ) {
            return true;
        }

        // Fallback: limited query for edge cases (serialization format varies)
        $orders = get_posts( array(
            'post_type'      => 'fc_order',
            'posts_per_page' => 50,
            'meta_query'     => array(
                array(
                    'key'   => '_fc_customer_id',
                    'value' => $user_id,
                ),
                array(
                    'key'     => '_fc_order_status',
                    'value'   => array( 'processing', 'shipped', 'completed' ),
                    'compare' => 'IN',
                ),
            ),
            'fields'  => 'ids',
            'orderby' => 'ID',
            'order'   => 'DESC',
        ) );

        foreach ( $orders as $order_id ) {
            $items = get_post_meta( $order_id, '_fc_order_items', true );
            if ( is_array( $items ) ) {
                foreach ( $items as $item ) {
                    if ( intval( $item['product_id'] ) === $product_id_int ) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * Sprawdź czy użytkownik już wystawił recenzję
     */
    public static function user_has_reviewed( $product_id, $user_id = 0 ) {
        if ( ! $user_id ) $user_id = get_current_user_id();
        if ( ! $user_id ) return false;

        $existing = get_comments( array(
            'post_id' => $product_id,
            'user_id' => $user_id,
            'type'    => 'fc_review',
            'count'   => true,
        ) );

        return $existing > 0;
    }

    /**
     * Pobierz średnią ocenę produktu
     */
    public static function get_average_rating( $product_id ) {
        $reviews = get_comments( array(
            'post_id' => $product_id,
            'type'    => 'fc_review',
            'status'  => 'approve',
        ) );

        if ( empty( $reviews ) ) return 0;

        $total = 0;
        foreach ( $reviews as $review ) {
            $total += floatval( get_comment_meta( $review->comment_ID, '_fc_rating', true ) );
        }

        return round( $total / count( $reviews ), 1 );
    }

    /**
     * Pobierz liczbę recenzji
     */
    public static function get_review_count( $product_id ) {
        return (int) get_comments( array(
            'post_id' => $product_id,
            'type'    => 'fc_review',
            'status'  => 'approve',
            'count'   => true,
        ) );
    }

    /**
     * Renderuj gwiazdki HTML
     */
    public static function render_stars( $rating, $echo = true ) {
        $rating = floatval( $rating );
        $html   = '<div class="fc-stars" title="' . esc_attr( $rating ) . '/5">';
        for ( $i = 1; $i <= 5; $i++ ) {
            if ( $rating >= $i ) {
                $html .= '<span class="fc-star fc-star-full">★</span>';
            } elseif ( $rating >= $i - 0.5 ) {
                $html .= '<span class="fc-star fc-star-half">★</span>';
            } else {
                $html .= '<span class="fc-star fc-star-empty">☆</span>';
            }
        }
        $html .= '</div>';

        if ( $echo ) echo $html;
        return $html;
    }

    /**
     * Renderuj sekcję recenzji na stronie produktu
     */
    public static function render_reviews_section( $product_id ) {
        $reviews = get_comments( array(
            'post_id' => $product_id,
            'type'    => 'fc_review',
            'status'  => 'approve',
            'orderby' => 'comment_date',
            'order'   => 'DESC',
        ) );

        $avg    = self::get_average_rating( $product_id );
        $count  = count( $reviews );
        $can_review = false;
        $review_message = '';

        if ( ! is_user_logged_in() ) {
            $review_message = fc__( 'login_to_review' );
        } elseif ( self::user_has_reviewed( $product_id ) ) {
            $review_message = fc__( 'already_reviewed' );
        } elseif ( ! self::user_has_purchased( $product_id ) ) {
            $review_message = fc__( 'only_buyers_can_review' );
        } else {
            $can_review = true;
        }

        $success = isset( $_GET['review_added'] ) && $_GET['review_added'] === '1';
        ?>
        <div class="fc-reviews-section" id="fc-reviews">
            <h2><?php fc_e( 'customer_reviews' ); ?></h2>

            <?php if ( $count > 0 ) : ?>
                <div class="fc-reviews-summary">
                    <div class="fc-reviews-avg">
                        <span class="fc-reviews-avg-number"><?php echo number_format( $avg, 1, ',', '' ); ?></span>
                        <?php self::render_stars( $avg ); ?>
                        <span class="fc-reviews-count">(<?php echo intval( $count ); ?>)</span>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ( $success ) : ?>
                <div class="fc-account-notice fc-notice-success"><?php fc_e( 'thank_you_for_review' ); ?></div>
            <?php endif; ?>

            <?php
            $review_error = isset( $_GET['review_error'] ) ? sanitize_text_field( $_GET['review_error'] ) : '';
            $error_messages = array(
                'no_rating'        => fc__( 'select_rating' ),
                'no_content'       => fc__( 'enter_review_content' ),
                'not_purchased'    => fc__( 'only_buyers_can_review' ),
                'already_reviewed' => fc__( 'already_reviewed' ),
            );
            if ( $review_error && isset( $error_messages[ $review_error ] ) ) : ?>
                <div class="fc-account-notice fc-notice-error"><?php echo esc_html( $error_messages[ $review_error ] ); ?></div>
            <?php endif; ?>

            <?php if ( $can_review ) : ?>
                <div class="fc-review-form-wrap">
                    <h3><?php fc_e( 'write_review' ); ?></h3>
                    <form method="post" class="fc-review-form">
                        <?php wp_nonce_field( 'fc_add_review', 'fc_review_nonce' ); ?>
                        <input type="hidden" name="fc_review_product" value="<?php echo esc_attr( $product_id ); ?>">

                        <div class="fc-field">
                            <label><?php fc_e( 'your_rating' ); ?> <span class="fc-required">*</span></label>
                            <div class="fc-star-rating-input" data-rating="0">
                                <input type="hidden" name="fc_rating" value="" required>
                                <div class="fc-rating-stars">
                                    <?php for ( $i = 1; $i <= 5; $i++ ) : ?>
                                        <span class="fc-input-star" data-value="<?php echo $i; ?>">★</span>
                                    <?php endfor; ?>
                                </div>
                                <span class="fc-rating-text"></span>
                            </div>
                        </div>

                        <div class="fc-field">
                            <label for="fc_review_content"><?php fc_e( 'review_content' ); ?> <span class="fc-required">*</span></label>
                            <textarea name="fc_review_content" id="fc_review_content" rows="4" required></textarea>
                        </div>

                        <button type="submit" name="fc_submit_review" class="fc-btn"><?php fc_e( 'add_review' ); ?></button>
                    </form>
                </div>
            <?php elseif ( $review_message ) : ?>
                <p class="fc-review-notice"><?php echo esc_html( $review_message ); ?></p>
            <?php endif; ?>

            <?php if ( ! empty( $reviews ) ) : ?>
                <div class="fc-reviews-list">
                    <?php foreach ( $reviews as $review ) :
                        $rating = floatval( get_comment_meta( $review->comment_ID, '_fc_rating', true ) );
                        $author = $review->comment_author;
                        $date   = date_i18n( 'j M Y', strtotime( $review->comment_date ) );
                    ?>
                        <div class="fc-review-item">
                            <div class="fc-review-header">
                                <div class="fc-review-author-info">
                                    <?php echo get_avatar( $review->comment_author_email, 40 ); ?>
                                    <div>
                                        <strong class="fc-review-author"><?php echo esc_html( $author ); ?></strong>
                                        <span class="fc-review-date"><?php echo esc_html( $date ); ?></span>
                                    </div>
                                </div>
                                <?php self::render_stars( $rating ); ?>
                            </div>
                            <div class="fc-review-content">
                                <?php echo wpautop( esc_html( $review->comment_content ) ); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php elseif ( ! $success ) : ?>
                <p class="fc-reviews-empty"><?php fc_e( 'no_reviews_yet' ); ?></p>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Przetwarzanie formularza recenzji
     */
    public static function process_review() {
        if ( ! isset( $_POST['fc_submit_review'] ) ) return;
        if ( ! wp_verify_nonce( $_POST['fc_review_nonce'] ?? '', 'fc_add_review' ) ) return;
        if ( ! is_user_logged_in() ) return;

        $product_id = absint( $_POST['fc_review_product'] ?? 0 );
        $rating     = floatval( $_POST['fc_rating'] ?? 0 );
        $content    = sanitize_textarea_field( $_POST['fc_review_content'] ?? '' );
        $user       = wp_get_current_user();

        // Walidacja: 0.5 - 5.0, tylko kroki co 0.5
        $valid_ratings = array( 0.5, 1.0, 1.5, 2.0, 2.5, 3.0, 3.5, 4.0, 4.5, 5.0 );

        $error = '';
        if ( ! $product_id ) {
            return;
        } elseif ( ! in_array( $rating, $valid_ratings, false ) ) {
            $error = 'no_rating';
        } elseif ( empty( $content ) ) {
            $error = 'no_content';
        } elseif ( ! self::user_has_purchased( $product_id, $user->ID ) ) {
            $error = 'not_purchased';
        } elseif ( self::user_has_reviewed( $product_id, $user->ID ) ) {
            $error = 'already_reviewed';
        }

        if ( $error ) {
            wp_safe_redirect( add_query_arg( 'review_error', $error, get_permalink( $product_id ) . '#fc-reviews' ) );
            exit;
        }

        $comment_id = wp_insert_comment( array(
            'comment_post_ID'  => $product_id,
            'comment_author'   => $user->display_name,
            'comment_author_email' => $user->user_email,
            'comment_content'  => $content,
            'comment_type'     => 'fc_review',
            'user_id'          => $user->ID,
            'comment_approved' => get_option( 'fc_review_auto_approve', '0' ) === '1' ? 1 : 0,
        ) );

        if ( $comment_id ) {
            update_comment_meta( $comment_id, '_fc_rating', number_format( $rating, 1, '.', '' ) );
        }

        wp_safe_redirect( add_query_arg( 'review_added', '1', get_permalink( $product_id ) . '#fc-reviews' ) );
        exit;
    }

    /**
     * Zapisz ocenę (fallback)
     */
    public static function save_rating( $comment_id ) {
        $comment = get_comment( $comment_id );
        if ( ! $comment || $comment->comment_type !== 'fc_review' ) return;

        if ( isset( $_POST['fc_rating'] ) ) {
            $rating = floatval( $_POST['fc_rating'] );
            if ( $rating >= 0.5 && $rating <= 5.0 ) {
                update_comment_meta( $comment_id, '_fc_rating', number_format( $rating, 1, '.', '' ) );
            }
        }
    }
}

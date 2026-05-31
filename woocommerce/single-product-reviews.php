<?php
/**
 * Single Product Reviews — DeviceHub override
 *
 * Renders the review list (with custom empty state) and the review submission form.
 *
 * @package DeviceHub
 */

defined('ABSPATH') || exit;

global $product;

if (!comments_open()) {
    return;
}

$review_count = $product->get_review_count();
?>

<div id="reviews" class="devhub-reviews">

    <?php if ($review_count > 0): ?>
        <ol class="devhub-reviews__list commentlist">
            <?php wp_list_comments(apply_filters('woocommerce_product_review_list_args', ['callback' => 'woocommerce_comments'])); ?>
        </ol>
        <?php if (get_comment_pages_count() > 1 && get_option('page_comments')): ?>
            <nav class="devhub-reviews__pagination woocommerce-pagination">
                <?php paginate_comments_links(apply_filters('woocommerce_comment_pagination_args', [
                    'prev_text' => '&larr;',
                    'next_text' => '&rarr;',
                    'type'      => 'list',
                ])); ?>
            </nav>
        <?php endif; ?>
    <?php else: ?>
        <div class="devhub-reviews__empty">
            <svg class="devhub-reviews__empty-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64" fill="none" aria-hidden="true">
                <rect x="4" y="6" width="56" height="40" rx="8" stroke="#d1d5db" stroke-width="3.5" fill="none"/>
                <line x1="16" y1="20" x2="48" y2="20" stroke="#d1d5db" stroke-width="3.5" stroke-linecap="round"/>
                <line x1="16" y1="30" x2="48" y2="30" stroke="#d1d5db" stroke-width="3.5" stroke-linecap="round"/>
                <line x1="16" y1="40" x2="36" y2="40" stroke="#d1d5db" stroke-width="3.5" stroke-linecap="round"/>
                <path d="M20 46 L14 58 L28 52" stroke="#d1d5db" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round" fill="none"/>
            </svg>
            <h3 class="devhub-reviews__empty-title"><?php esc_html_e('No Reviews Yet', 'devicehub-theme'); ?></h3>
            <p class="devhub-reviews__empty-sub"><?php esc_html_e('Be the first to review this product!', 'devicehub-theme'); ?></p>
        </div>
    <?php endif; ?>

    <?php
    $can_review = get_option('woocommerce_review_rating_verification_required') === 'no'
        || wc_customer_bought_product('', get_current_user_id(), $product->get_id());

    if ($can_review):
        $commenter = wp_get_current_commenter();
        $name_email_required = (bool) get_option('require_name_email', 1);

        $fields = [];
        foreach (['author' => [__('Name', 'woocommerce'), 'text', $commenter['comment_author'], 'name'],
                  'email'  => [__('Email', 'woocommerce'), 'email', $commenter['comment_author_email'], 'email']] as $key => [$label, $type, $value, $autocomplete]) {
            $req = $name_email_required ? ' <span class="required">*</span>' : '';
            $req_attr = $name_email_required ? ' required' : '';
            $fields[$key] = '<p class="comment-form-' . esc_attr($key) . '">'
                . '<label for="' . esc_attr($key) . '">' . esc_html($label) . $req . '</label>'
                . '<input id="' . esc_attr($key) . '" name="' . esc_attr($key) . '" type="' . esc_attr($type) . '" autocomplete="' . esc_attr($autocomplete) . '" value="' . esc_attr($value) . '" size="30"' . $req_attr . '>'
                . '</p>';
        }

        $comment_field = '';
        if (wc_review_ratings_enabled()) {
            $req = wc_review_ratings_required() ? ' <span class="required">*</span>' : '';
            $comment_field .= '<div class="comment-form-rating">'
                . '<label for="rating">' . esc_html__('Your rating', 'woocommerce') . $req . '</label>'
                . '<select name="rating" id="rating" required>'
                . '<option value="">' . esc_html__('Rate&hellip;', 'woocommerce') . '</option>'
                . '<option value="5">' . esc_html__('Perfect', 'woocommerce') . '</option>'
                . '<option value="4">' . esc_html__('Good', 'woocommerce') . '</option>'
                . '<option value="3">' . esc_html__('Average', 'woocommerce') . '</option>'
                . '<option value="2">' . esc_html__('Not that bad', 'woocommerce') . '</option>'
                . '<option value="1">' . esc_html__('Very poor', 'woocommerce') . '</option>'
                . '</select></div>';
        }
        $comment_field .= '<p class="comment-form-comment">'
            . '<label for="comment">' . esc_html__('Your review', 'woocommerce') . ' <span class="required">*</span></label>'
            . '<textarea id="comment" name="comment" cols="45" rows="6" required></textarea>'
            . '</p>';

        $account_page_url = wc_get_page_permalink('myaccount');
        $must_log_in = $account_page_url
            ? '<p class="must-log-in">' . sprintf(
                esc_html__('You must be %1$slogged in%2$s to post a review.', 'woocommerce'),
                '<a href="' . esc_url($account_page_url) . '">',
                '</a>'
              ) . '</p>'
            : '';

        $form_args = apply_filters('woocommerce_product_review_comment_form_args', [
            'title_reply'         => $review_count > 0
                ? esc_html__('Add a review', 'woocommerce')
                : sprintf(esc_html__('Be the first to review &ldquo;%s&rdquo;', 'woocommerce'), get_the_title()),
            'title_reply_before'  => '<span id="reply-title" class="comment-reply-title" role="heading" aria-level="3">',
            'title_reply_after'   => '</span>',
            'comment_notes_after' => '',
            'label_submit'        => esc_html__('Submit', 'woocommerce'),
            'logged_in_as'        => '',
            'comment_field'       => $comment_field,
            'fields'              => $fields,
            'must_log_in'         => $must_log_in,
        ]);

        comment_form($form_args);
    else: ?>
        <p class="woocommerce-verification-required">
            <?php esc_html_e('Only logged in customers who have purchased this product may leave a review.', 'woocommerce'); ?>
        </p>
    <?php endif; ?>

</div>

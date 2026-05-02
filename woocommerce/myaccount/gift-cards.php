<?php
defined('ABSPATH') || exit;

/**
 * Gift Cards — DeviceHub
 * Replace this later with real plugin data
 */

// TEMP: simulate no gift cards
$gift_cards = [];
$has_cards = !empty($gift_cards);
?>

<?php if ($has_cards): ?>

    <!-- Future: render gift cards list -->
    <p>Your gift cards will appear here.</p>

<?php else: ?>

    <div class="devhub-empty-state">

        <!-- Icon -->
        <div class="devhub-empty-state__icon-wrap">
            <div class="devhub-empty-state__layer devhub-empty-state__layer--1"></div>
            <div class="devhub-empty-state__layer devhub-empty-state__layer--2"></div>

            <div class="devhub-empty-state__card">
                <!-- Gift Icon -->
                <svg width="72" height="72" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2">
                    <rect x="2" y="7" width="20" height="14" rx="2" />
                    <path d="M16 7V5a2 2 0 0 0-4 0v2" />
                    <path d="M8 7V5a2 2 0 0 1 4 0v2" />
                    <line x1="12" y1="7" x2="12" y2="21" />
                    <line x1="2" y1="12" x2="22" y2="12" />
                </svg>
            </div>

            <div class="devhub-empty-state__badge">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="11" cy="11" r="8" />
                    <line x1="21" y1="21" x2="16.65" y2="16.65" />
                </svg>
            </div>
        </div>

        <!-- Text -->
        <div class="devhub-empty-state__text">
            <h4>
                <?php esc_html_e('No gift cards available yet.', 'devicehub-theme'); ?>
            </h4>
            <p>
                <?php esc_html_e('You haven’t received or purchased any gift cards yet.', 'devicehub-theme'); ?>
            </p>
        </div>

        <!-- Action -->
        <div class="devhub-empty-state__actions">
            <a href="<?php echo esc_url(wc_get_page_permalink('shop')); ?>"
                class="devhub-empty-state__btn devhub-empty-state__btn--primary">
                Browse Products
            </a>
        </div>

    </div>

<?php endif; ?>
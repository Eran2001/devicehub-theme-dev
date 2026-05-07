</div>
</div>
<footer id="wf_footer" class="wf_footer wf_footer--one clearfix">
    <?php
    $img_uri = get_template_directory_uri() . '/assets/images/';
    $footer_settings = function_exists('devhub_get_footer_settings') ? devhub_get_footer_settings() : [];
    $contact_title = $footer_settings['contact_title'] ?? 'Contact';
    $link_sections = function_exists('devhub_get_footer_link_sections') ? devhub_get_footer_link_sections() : [];
    $address = trim((string) ($footer_settings['address'] ?? ''));
    $phone = trim((string) ($footer_settings['phone'] ?? ''));
    $email = trim((string) ($footer_settings['email'] ?? ''));
    $copyright_text = trim((string) ($footer_settings['copyright_text'] ?? ''));
    $phone_href = function_exists('devhub_get_footer_phone_href') ? devhub_get_footer_phone_href($phone) : '';
    $google_play_url = trim((string) ($footer_settings['google_play_url'] ?? '#'));
    $app_store_url = trim((string) ($footer_settings['app_store_url'] ?? '#'));
    $email_href = sanitize_email($email);
    $social_links = [
        [
            'label' => 'Facebook',
            'url' => trim((string) ($footer_settings['facebook_url'] ?? '#')),
            'icon' => 'FaceBook.svg',
        ],
        [
            'label' => 'Twitter',
            'url' => trim((string) ($footer_settings['twitter_url'] ?? '#')),
            'icon' => 'Twitter.svg',
        ],
        [
            'label' => 'LinkedIn',
            'url' => trim((string) ($footer_settings['linkedin_url'] ?? '#')),
            'icon' => 'LinkedIn.svg',
        ],
        [
            'label' => 'Instagram',
            'url' => trim((string) ($footer_settings['instagram_url'] ?? '#')),
            'icon' => 'Instagram.svg',
        ],
    ];
    if ($google_play_url === '') {
        $google_play_url = '#';
    }
    if ($app_store_url === '') {
        $app_store_url = '#';
    }
    ?>
    <div class="dh-footer__inner wf-container">

        <!-- Col 1: Logo + App badges -->
        <div class="dh-footer__brand">
            <a href="<?php echo esc_url(home_url('/')); ?>" class="dh-footer__logo">
                <img src="<?php echo esc_url($img_uri . 'HUTCHMainLogo.svg'); ?>" alt="HUTCH">
            </a>
            <div class="dh-footer__badges">
                <a href="<?php echo esc_url($google_play_url); ?>" class="dh-footer__badge" target="_blank" rel="noopener noreferrer">
                    <img src="<?php echo esc_url($img_uri . 'GooglePlay.svg'); ?>" alt="Get it on Google Play">
                </a>
                <a href="<?php echo esc_url($app_store_url); ?>" class="dh-footer__badge" target="_blank" rel="noopener noreferrer">
                    <img src="<?php echo esc_url($img_uri . 'AppStore.svg'); ?>" alt="Download on the App Store">
                </a>
            </div>
        </div>

        <!-- Col 2: Middle link sections -->
        <?php if (!empty($link_sections)): ?>
            <div class="dh-footer__middle">
                <?php foreach ($link_sections as $section): ?>
                    <div class="dh-footer__col dh-footer__col--links">
                        <h4 class="dh-footer__heading"><?php echo esc_html($section['title']); ?></h4>
                        <ul class="dh-footer__links">
                            <?php foreach ($section['links'] as $link): ?>
                                <li>
                                    <a href="<?php echo esc_url($link['url'] !== '' ? $link['url'] : '#'); ?>"><?php echo esc_html($link['label']); ?></a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Col 3: Contact + social -->
        <div class="dh-footer__col dh-footer__col--contact">
            <h4 class="dh-footer__heading"><?php echo esc_html($contact_title); ?></h4>
            <ul class="dh-footer__contact">
                <?php if ($address !== ''): ?>
                    <li>
                        <i class="fas fa-map-marker-alt" aria-hidden="true"></i>
                        <span><?php echo esc_html($address); ?></span>
                    </li>
                <?php endif; ?>
                <?php if ($phone !== ''): ?>
                    <li>
                        <i class="fas fa-phone" aria-hidden="true"></i>
                        <?php if ($phone_href !== ''): ?>
                            <a href="tel:<?php echo esc_attr($phone_href); ?>"><?php echo esc_html($phone); ?></a>
                        <?php else: ?>
                            <span><?php echo esc_html($phone); ?></span>
                        <?php endif; ?>
                    </li>
                <?php endif; ?>
                <?php if ($email_href !== ''): ?>
                    <li>
                        <i class="fas fa-envelope" aria-hidden="true"></i>
                        <a href="mailto:<?php echo esc_attr(antispambot($email_href)); ?>"><?php echo esc_html(antispambot($email_href)); ?></a>
                    </li>
                <?php endif; ?>
            </ul>
            <ul class="dh-footer__social">
                <?php foreach ($social_links as $social_link): ?>
                    <li>
                        <a href="<?php echo esc_url($social_link['url'] !== '' ? $social_link['url'] : '#'); ?>" target="_blank" rel="noopener noreferrer" aria-label="<?php echo esc_attr($social_link['label']); ?>">
                            <img src="<?php echo esc_url($img_uri . $social_link['icon']); ?>" alt="<?php echo esc_attr($social_link['label']); ?>">
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>

    </div>
    <div class="dh-footer__copyright wf-container">
        <?php if ($copyright_text !== ''): ?>
            <span class="dh-footer__copyright-text">
                <?php echo wp_kses_post($copyright_text); ?>
            </span>
            <span class="dh-footer__credit-separator" aria-hidden="true">|</span>
        <?php endif; ?>
        <span class="dh-footer__credit">
            <?php esc_html_e('Designed with', 'devicehub-theme'); ?>
            <span class="dh-footer__credit-heart" aria-hidden="true">❤</span>
            <?php esc_html_e('by FinTech Hive (Pvt) Ltd.', 'devicehub-theme'); ?>
        </span>
    </div>
</footer>
<?php
do_action('shopire_top_scroller');
do_action('shopire_footer_mobile_menu');
?>
<?php wp_footer(); ?>
</body>

</html>

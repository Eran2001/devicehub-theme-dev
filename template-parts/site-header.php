<?php
$shopire_hs_hdr_sticky = get_theme_mod('shopire_hs_hdr_sticky', '1');
$shopire_hs_hdr_bcat = get_theme_mod('shopire_hs_hdr_bcat', '1');
?>
<header id="wf_header" class="wf_header header--one">
    <div class="wf_header-inner">
        <div class="wf_header-topbar wf-d-lg-block wf-d-none">
            <?php do_action('shopire_site_header'); ?>
        </div>
        <div class="wf_header-navwrapper">
            <div class="wf_header-navwrapperinner">

                <!-- Desktop Navbar -->
                <div class="wf_navbar wf-d-none wf-d-lg-block">
                    <div
                        class="wf_navbar-wrapper <?php if ($shopire_hs_hdr_sticky == '1')
                            esc_attr_e('is--sticky', 'shopire'); ?>">
                        <div class="wf-container">
                            <div class="wf-row align-items-center">

                                <!-- Logo -->
                                <div class="wf-col-2">
                                    <div class="site--logo">
                                        <?php do_action('shopire_site_logo'); ?>
                                    </div>
                                </div>

                                <!-- Search -->
                                <div class="wf-col-7">
                                    <?php do_action('shopire_hdr_product_search'); ?>
                                </div>

                                <!-- Right icons -->
                                <!-- Right icons -->
                                <div class="wf-col-3">
                                    <div class="wf_navbar-right">
                                        <ul class="wf_navbar-list-right">
                                            <?php do_action('shopire_woo_cart'); ?>
                                            <?php do_action('shopire_hdr_account'); ?>
                                            <?php do_action('shopire_hdr_side_docker'); ?>
                                        </ul>
                                    </div>
                                </div>

                            </div>
                        </div>
                    </div>
                </div>
                <!-- End Desktop Navbar -->

                <?php if (function_exists('devhub_render_secondary_nav') && function_exists('devhub_show_secondary_nav') && devhub_show_secondary_nav()) {
                    devhub_render_secondary_nav();
                } ?>

                <!-- Mobile Menu -->
                <div
                    class="wf_mobilenav wf-d-lg-none <?php if ($shopire_hs_hdr_sticky == '1')
                        esc_attr_e('is--sticky', 'shopire'); ?>">
                    <div class="wf-container">
                        <div class="wf-row">
                            <div class="wf-col-12">
                                <div class="wf_mobilenav-menu">
                                    <div class="wf_mobilenav-mainmenu">
                                        <button type="button" class="hamburger wf_mobilenav-mainmenu-toggle">
                                            <span></span>
                                            <span></span>
                                            <span></span>
                                        </button>
                                        <nav class="wf_mobilenav-mainmenu-content">
                                            <div class="wf_header-closemenu off--layer"></div>
                                            <div class="wf_mobilenav-mainmenu-inner">
                                                <div class="dh-mobile-drawer__header">
                                                    <div class="dh-mobile-drawer__logo site--logo">
                                                        <?php do_action('shopire_site_logo'); ?>
                                                    </div>
                                                    <button type="button" class="wf_header-closemenu site--close"
                                                        aria-label="<?php esc_attr_e('Close menu', 'devicehub-theme'); ?>"></button>
                                                </div>
                                                <div class="wf_mobilenav-mainmenu-wrap">
                                                    <?php
                                                    $shopire_hdr_bcat_ttl = get_theme_mod('shopire_hdr_bcat_ttl', 'Browse Categories');
                                                    $shopire_hdr_mobile_nav_ttl = get_theme_mod('shopire_hdr_mobile_nav_ttl', 'Main Menu');
                                                    if (!empty($shopire_hdr_mobile_nav_ttl) && $shopire_hs_hdr_bcat == '1' && !empty($shopire_hdr_bcat_ttl) && class_exists('woocommerce')):
                                                        ?>
                                                        <h5 class="title">
                                                            <?php echo wp_kses_post($shopire_hdr_mobile_nav_ttl); ?></h5>
                                                    <?php endif; ?>
                                                    <?php do_action('shopire_site_header_navigation'); ?>
                                                    <?php if ($shopire_hs_hdr_bcat == '1' && !empty($shopire_hdr_bcat_ttl) && class_exists('woocommerce')): ?>
                                                        <h5 class="title"><?php echo wp_kses_post($shopire_hdr_bcat_ttl); ?>
                                                        </h5>
                                                        <?php do_action('shopire_header_bcat_base'); ?>
                                                    <?php endif; ?>
                                                    <?php
                                                    if (function_exists('devhub_render_mobile_secondary_nav_sections') && function_exists('devhub_show_secondary_nav') && devhub_show_secondary_nav()) {
                                                        devhub_render_mobile_secondary_nav_sections();
                                                    }
                                                    ?>
                                                </div>
                                            </div>
                                        </nav>
                                    </div>
                                    <div class="wf_mobilenav-logo">
                                        <div class="site--logo">
                                            <?php do_action('shopire_site_logo'); ?>
                                        </div>
                                    </div>
                                    <div class="wf_mobilenav-toggles">
                                        <div class="wf_mobilenav-right">
                                            <ul class="wf_navbar-list-right">
                                                <?php do_action('shopire_hdr_account'); ?>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- End Mobile Menu -->

            </div>
        </div>
    </div>
</header>

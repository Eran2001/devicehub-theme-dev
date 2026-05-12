<?php
/**
 * DeviceHub - Footer Settings
 *
 * Admin-managed footer content page for non-technical editors.
 *
 * @package DeviceHub
 */

defined('ABSPATH') || exit;

const DEVHUB_FOOTER_SETTINGS_CAPABILITY = 'manage_devhub_footer_settings';

add_action('admin_menu', 'devhub_register_footer_settings_page');
add_action('admin_init', 'devhub_register_footer_settings');
add_action('init', 'devhub_ensure_footer_settings_capability');

function devhub_get_footer_settings_capability(): string
{
    return DEVHUB_FOOTER_SETTINGS_CAPABILITY;
}

function devhub_ensure_footer_settings_capability(): void
{
    foreach (['administrator', 'shop_manager'] as $role_name) {
        $role = get_role($role_name);

        if (!$role instanceof WP_Role) {
            continue;
        }

        if (!$role->has_cap(devhub_get_footer_settings_capability())) {
            $role->add_cap(devhub_get_footer_settings_capability());
        }
    }
}

function devhub_footer_settings_defaults(): array
{
    return [
        'contact_title' => __('Contact', 'devicehub-theme'),
        'address' => '234 Galle Rd, Colombo, Sri Lanka',
        'phone' => '0788 777 111',
        'email' => 'cs@hutchison.lk',
        'google_play_url' => '#',
        'app_store_url' => '#',
        'copyright_text' => '',
        'facebook_url' => '#',
        'twitter_url' => '#',
        'linkedin_url' => '#',
        'instagram_url' => '#',
        'background_color' => '#ff6600',
        'link_sections' => [
            [
                'title' => __('Company', 'devicehub-theme'),
                'links' => [
                    [
                        'label' => __('About us', 'devicehub-theme'),
                        'url' => '#',
                    ],
                    [
                        'label' => __('Delivery', 'devicehub-theme'),
                        'url' => '#',
                    ],
                    [
                        'label' => __('Legal Notice', 'devicehub-theme'),
                        'url' => '#',
                    ],
                    [
                        'label' => __('Terms & conditions', 'devicehub-theme'),
                        'url' => '#',
                    ],
                    [
                        'label' => __('Secure payment', 'devicehub-theme'),
                        'url' => '#',
                    ],
                    [
                        'label' => __('Contact us', 'devicehub-theme'),
                        'url' => '#',
                    ],
                ],
            ],
            [
                'title' => '',
                'links' => [
                    ['label' => '', 'url' => ''],
                    ['label' => '', 'url' => ''],
                    ['label' => '', 'url' => ''],
                    ['label' => '', 'url' => ''],
                    ['label' => '', 'url' => ''],
                    ['label' => '', 'url' => ''],
                ],
            ],
            [
                'title' => '',
                'links' => [
                    ['label' => '', 'url' => ''],
                    ['label' => '', 'url' => ''],
                    ['label' => '', 'url' => ''],
                    ['label' => '', 'url' => ''],
                    ['label' => '', 'url' => ''],
                    ['label' => '', 'url' => ''],
                ],
            ],
        ],
    ];
}

function devhub_get_footer_settings(): array
{
    $defaults = devhub_footer_settings_defaults();
    $saved = get_option('devhub_footer_settings', []);

    if (!is_array($saved)) {
        return $defaults;
    }

    $settings = array_merge($defaults, $saved);
    $settings['link_sections'] = $defaults['link_sections'];

    $saved_sections = [];
    if (isset($saved['link_sections']) && is_array($saved['link_sections'])) {
        $saved_sections = $saved['link_sections'];
    } elseif (isset($saved['company_title']) || isset($saved['company_links'])) {
        $saved_sections[0] = [
            'title' => (string) ($saved['company_title'] ?? $defaults['link_sections'][0]['title']),
            'links' => isset($saved['company_links']) && is_array($saved['company_links']) ? $saved['company_links'] : $defaults['link_sections'][0]['links'],
        ];
    }

    foreach ($defaults['link_sections'] as $section_index => $default_section) {
        $section_input = isset($saved_sections[$section_index]) && is_array($saved_sections[$section_index])
            ? $saved_sections[$section_index]
            : [];

        $settings['link_sections'][$section_index]['title'] = (string) ($section_input['title'] ?? $default_section['title']);

        $saved_links = isset($section_input['links']) && is_array($section_input['links'])
            ? $section_input['links']
            : [];

        foreach ($default_section['links'] as $link_index => $default_link) {
            $link_input = isset($saved_links[$link_index]) && is_array($saved_links[$link_index])
                ? $saved_links[$link_index]
                : [];

            $settings['link_sections'][$section_index]['links'][$link_index] = [
                'label' => (string) ($link_input['label'] ?? $default_link['label']),
                'url' => (string) ($link_input['url'] ?? $default_link['url']),
            ];
        }
    }

    return $settings;
}

function devhub_get_footer_link_sections(): array
{
    $settings = devhub_get_footer_settings();
    $sections = [];

    foreach ($settings['link_sections'] as $section) {
        $title = trim((string) ($section['title'] ?? ''));
        $links = [];

        foreach (($section['links'] ?? []) as $link) {
            $label = trim((string) ($link['label'] ?? ''));
            $url = trim((string) ($link['url'] ?? ''));

            if ($label === '') {
                continue;
            }

            $links[] = [
                'label' => $label,
                'url' => $url,
            ];
        }

        if ($title === '' || empty($links)) {
            continue;
        }

        $sections[] = [
            'title' => $title,
            'links' => $links,
        ];
    }

    return $sections;
}

function devhub_get_footer_phone_href(string $phone): string
{
    $phone_href = preg_replace('/(?!^\+)[^0-9]/', '', trim($phone));

    return is_string($phone_href) ? $phone_href : '';
}

function devhub_sanitize_footer_link_target($value): string
{
    $value = trim((string) $value);

    if ($value === '') {
        return '';
    }

    if ($value === '#') {
        return '#';
    }

    if ($value[0] === '#') {
        return sanitize_text_field($value);
    }

    if ($value[0] === '/') {
        return '/' . ltrim(sanitize_text_field($value), '/');
    }

    if (preg_match('/^(mailto:|tel:)/i', $value) === 1) {
        return sanitize_text_field($value);
    }

    return esc_url_raw($value);
}

function devhub_register_footer_settings_page(): void
{
    add_menu_page(
        __('Footer Settings', 'devicehub-theme'),
        __('Footer Settings', 'devicehub-theme'),
        devhub_get_footer_settings_capability(),
        'devhub-footer-settings',
        'devhub_render_footer_settings_page',
        'dashicons-editor-kitchensink',
        60
    );
}

function devhub_register_footer_settings(): void
{
    register_setting(
        'devhub_footer_settings_group',
        'devhub_footer_settings',
        [
            'type' => 'array',
            'sanitize_callback' => 'devhub_sanitize_footer_settings',
            'default' => devhub_footer_settings_defaults(),
        ]
    );
}

function devhub_sanitize_footer_settings($input): array
{
    $defaults = devhub_footer_settings_defaults();
    $sanitized = $defaults;
    $input = is_array($input) ? $input : [];

    $sanitized['contact_title'] = sanitize_text_field($input['contact_title'] ?? $defaults['contact_title']);
    $sanitized['address'] = sanitize_textarea_field($input['address'] ?? $defaults['address']);
    $sanitized['phone'] = sanitize_text_field($input['phone'] ?? $defaults['phone']);
    $sanitized['email'] = sanitize_email($input['email'] ?? $defaults['email']);
    $sanitized['google_play_url'] = devhub_sanitize_footer_link_target($input['google_play_url'] ?? $defaults['google_play_url']);
    $sanitized['app_store_url'] = devhub_sanitize_footer_link_target($input['app_store_url'] ?? $defaults['app_store_url']);
    $sanitized['copyright_text'] = wp_kses_post($input['copyright_text'] ?? $defaults['copyright_text']);
    $sanitized['facebook_url'] = devhub_sanitize_footer_link_target($input['facebook_url'] ?? $defaults['facebook_url']);
    $sanitized['twitter_url'] = devhub_sanitize_footer_link_target($input['twitter_url'] ?? $defaults['twitter_url']);
    $sanitized['linkedin_url'] = devhub_sanitize_footer_link_target($input['linkedin_url'] ?? $defaults['linkedin_url']);
    $sanitized['instagram_url'] = devhub_sanitize_footer_link_target($input['instagram_url'] ?? $defaults['instagram_url']);

    $background_color = sanitize_hex_color($input['background_color'] ?? $defaults['background_color']);
    $sanitized['background_color'] = $background_color ?: $defaults['background_color'];

    $link_sections = $input['link_sections'] ?? [];
    foreach ($defaults['link_sections'] as $section_index => $section_defaults) {
        $section_input = isset($link_sections[$section_index]) && is_array($link_sections[$section_index])
            ? $link_sections[$section_index]
            : [];

        $sanitized['link_sections'][$section_index] = [
            'title' => sanitize_text_field($section_input['title'] ?? $section_defaults['title']),
            'links' => [],
        ];

        $section_links = $section_input['links'] ?? [];
        foreach ($section_defaults['links'] as $link_index => $link_defaults) {
            $link_input = isset($section_links[$link_index]) && is_array($section_links[$link_index])
                ? $section_links[$link_index]
                : [];

            $sanitized['link_sections'][$section_index]['links'][$link_index] = [
                'label' => sanitize_text_field($link_input['label'] ?? $link_defaults['label']),
                'url' => devhub_sanitize_footer_link_target($link_input['url'] ?? $link_defaults['url']),
            ];
        }
    }

    add_settings_error(
        'devhub_footer_settings',
        'devhub_footer_settings_saved',
        __('Footer settings saved.', 'devicehub-theme'),
        'updated'
    );

    return $sanitized;
}

function devhub_render_footer_settings_page(): void
{
    if (!current_user_can(devhub_get_footer_settings_capability())) {
        return;
    }

    $settings = devhub_get_footer_settings();
    ?>
    <div class="wrap">
        <style>
            .devhub-footer-sections-grid {
                display: grid;
                grid-template-columns: repeat(3, minmax(0, 1fr));
                gap: 16px;
                margin-top: 16px;
            }

            .devhub-footer-section-card {
                background: #fff;
                border: 1px solid #dcdcde;
                border-radius: 8px;
                padding: 16px;
                box-shadow: 0 1px 2px rgba(0, 0, 0, 0.04);
            }

            .devhub-footer-section-card h3 {
                margin: 0 0 12px;
                font-size: 14px;
            }

            .devhub-footer-section-card .regular-text {
                width: 100%;
            }

            .devhub-footer-links-table {
                width: 100%;
                border-collapse: collapse;
                margin-top: 12px;
            }

            .devhub-footer-links-table th,
            .devhub-footer-links-table td {
                padding: 8px;
                border: 1px solid #dcdcde;
                vertical-align: top;
            }

            .devhub-footer-links-table th {
                background: #f6f7f7;
                text-align: left;
            }

            .devhub-footer-links-table td:first-child {
                width: 56px;
                white-space: nowrap;
                font-weight: 600;
            }

            .devhub-footer-links-table input {
                width: 100%;
            }

            @media (max-width: 1400px) {
                .devhub-footer-sections-grid {
                    grid-template-columns: 1fr;
                }
            }
        </style>
        <h1><?php esc_html_e('Footer Settings', 'devicehub-theme'); ?></h1>
        <p><?php esc_html_e('Manage footer content here. The logo and icon graphics stay controlled by the theme.', 'devicehub-theme'); ?></p>

        <?php settings_errors('devhub_footer_settings'); ?>

        <form action="options.php" method="post">
            <?php settings_fields('devhub_footer_settings_group'); ?>

            <h2><?php esc_html_e('Main Settings', 'devicehub-theme'); ?></h2>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><label for="devhub-footer-background-color"><?php esc_html_e('Footer Background Color', 'devicehub-theme'); ?></label></th>
                    <td>
                        <input id="devhub-footer-background-color" type="color" name="devhub_footer_settings[background_color]" value="<?php echo esc_attr($settings['background_color']); ?>">
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="devhub-footer-google-play-url"><?php esc_html_e('Google Play URL', 'devicehub-theme'); ?></label></th>
                    <td>
                        <input id="devhub-footer-google-play-url" class="regular-text" type="text" name="devhub_footer_settings[google_play_url]" value="<?php echo esc_attr($settings['google_play_url']); ?>" placeholder="https://example.com/app or /app">
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="devhub-footer-app-store-url"><?php esc_html_e('App Store URL', 'devicehub-theme'); ?></label></th>
                    <td>
                        <input id="devhub-footer-app-store-url" class="regular-text" type="text" name="devhub_footer_settings[app_store_url]" value="<?php echo esc_attr($settings['app_store_url']); ?>" placeholder="https://example.com/app or /app">
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="devhub-footer-copyright-text"><?php esc_html_e('Copyright Text', 'devicehub-theme'); ?></label></th>
                    <td>
                        <textarea id="devhub-footer-copyright-text" class="large-text" rows="2" name="devhub_footer_settings[copyright_text]" placeholder="<?php esc_attr_e('Copyright © 2026 Hutch. All rights reserved.', 'devicehub-theme'); ?>"><?php echo esc_textarea($settings['copyright_text']); ?></textarea>
                        <p class="description"><?php esc_html_e('Shown centered at the bottom of the footer. Leave empty to hide this section.', 'devicehub-theme'); ?></p>
                    </td>
                </tr>
            </table>

            <h2><?php esc_html_e('Middle Link Sections', 'devicehub-theme'); ?></h2>
            <p><?php esc_html_e('You can manage up to 3 sections here. Each section can have up to 6 links.', 'devicehub-theme'); ?></p>
            <div class="devhub-footer-sections-grid">
                <?php foreach ($settings['link_sections'] as $section_index => $section): ?>
                    <?php $section_number = $section_index + 1; ?>
                    <div class="devhub-footer-section-card">
                        <h3><?php echo esc_html(sprintf(__('Section %d', 'devicehub-theme'), $section_number)); ?></h3>
                        <p>
                            <label for="devhub-footer-section-title-<?php echo esc_attr((string) $section_index); ?>"><?php esc_html_e('Title', 'devicehub-theme'); ?></label><br>
                            <input id="devhub-footer-section-title-<?php echo esc_attr((string) $section_index); ?>" class="regular-text" type="text" name="devhub_footer_settings[link_sections][<?php echo esc_attr((string) $section_index); ?>][title]" value="<?php echo esc_attr($section['title']); ?>">
                        </p>

                        <table class="devhub-footer-links-table" role="presentation">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e('#', 'devicehub-theme'); ?></th>
                                    <th><?php esc_html_e('Label', 'devicehub-theme'); ?></th>
                                    <th><?php esc_html_e('URL', 'devicehub-theme'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($section['links'] as $link_index => $link): ?>
                                    <?php $link_number = $link_index + 1; ?>
                                    <tr>
                                        <td><?php echo esc_html((string) $link_number); ?></td>
                                        <td>
                                            <input id="devhub-footer-section-<?php echo esc_attr((string) $section_index); ?>-link-label-<?php echo esc_attr((string) $link_index); ?>" type="text" name="devhub_footer_settings[link_sections][<?php echo esc_attr((string) $section_index); ?>][links][<?php echo esc_attr((string) $link_index); ?>][label]" value="<?php echo esc_attr($link['label']); ?>">
                                        </td>
                                        <td>
                                            <input id="devhub-footer-section-<?php echo esc_attr((string) $section_index); ?>-link-url-<?php echo esc_attr((string) $link_index); ?>" type="text" name="devhub_footer_settings[link_sections][<?php echo esc_attr((string) $section_index); ?>][links][<?php echo esc_attr((string) $link_index); ?>][url]" value="<?php echo esc_attr($link['url']); ?>" placeholder="https://example.com or /page">
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endforeach; ?>
            </div>

            <h2><?php esc_html_e('Contact Column', 'devicehub-theme'); ?></h2>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><label for="devhub-footer-contact-title"><?php esc_html_e('Heading', 'devicehub-theme'); ?></label></th>
                    <td>
                        <input id="devhub-footer-contact-title" class="regular-text" type="text" name="devhub_footer_settings[contact_title]" value="<?php echo esc_attr($settings['contact_title']); ?>">
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="devhub-footer-address"><?php esc_html_e('Address', 'devicehub-theme'); ?></label></th>
                    <td>
                        <textarea id="devhub-footer-address" class="large-text" rows="3" name="devhub_footer_settings[address]"><?php echo esc_textarea($settings['address']); ?></textarea>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="devhub-footer-phone"><?php esc_html_e('Phone', 'devicehub-theme'); ?></label></th>
                    <td>
                        <input id="devhub-footer-phone" class="regular-text" type="text" name="devhub_footer_settings[phone]" value="<?php echo esc_attr($settings['phone']); ?>">
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="devhub-footer-email"><?php esc_html_e('Email', 'devicehub-theme'); ?></label></th>
                    <td>
                        <input id="devhub-footer-email" class="regular-text" type="email" name="devhub_footer_settings[email]" value="<?php echo esc_attr($settings['email']); ?>">
                    </td>
                </tr>
            </table>

            <h2><?php esc_html_e('Social Links', 'devicehub-theme'); ?></h2>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><label for="devhub-footer-facebook-url"><?php esc_html_e('Facebook URL', 'devicehub-theme'); ?></label></th>
                    <td>
                        <input id="devhub-footer-facebook-url" class="regular-text" type="text" name="devhub_footer_settings[facebook_url]" value="<?php echo esc_attr($settings['facebook_url']); ?>" placeholder="https://facebook.com/your-page">
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="devhub-footer-twitter-url"><?php esc_html_e('Twitter URL', 'devicehub-theme'); ?></label></th>
                    <td>
                        <input id="devhub-footer-twitter-url" class="regular-text" type="text" name="devhub_footer_settings[twitter_url]" value="<?php echo esc_attr($settings['twitter_url']); ?>" placeholder="https://x.com/your-page">
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="devhub-footer-linkedin-url"><?php esc_html_e('LinkedIn URL', 'devicehub-theme'); ?></label></th>
                    <td>
                        <input id="devhub-footer-linkedin-url" class="regular-text" type="text" name="devhub_footer_settings[linkedin_url]" value="<?php echo esc_attr($settings['linkedin_url']); ?>" placeholder="https://linkedin.com/company/your-page">
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="devhub-footer-instagram-url"><?php esc_html_e('Instagram URL', 'devicehub-theme'); ?></label></th>
                    <td>
                        <input id="devhub-footer-instagram-url" class="regular-text" type="text" name="devhub_footer_settings[instagram_url]" value="<?php echo esc_attr($settings['instagram_url']); ?>" placeholder="https://instagram.com/your-page">
                    </td>
                </tr>
            </table>

            <?php submit_button(__('Save Footer Settings', 'devicehub-theme')); ?>
        </form>
    </div>
    <?php
}

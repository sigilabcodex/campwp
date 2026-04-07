<?php

declare(strict_types=1);

namespace CampWP\Admin\Menu;

use CampWP\Admin\Settings\DefaultsSettings;
use CampWP\Domain\ContentModel\PostTypeRegistrar;
use CampWP\Infrastructure\Media\WordPressMediaLibraryProvider;

final class AdminMenu
{
    private DefaultsSettings $defaultsSettings;

    public function __construct(?DefaultsSettings $defaultsSettings = null)
    {
        $this->defaultsSettings = $defaultsSettings ?? new DefaultsSettings();
    }

    public function register(): void
    {
        add_action('admin_menu', [$this, 'registerMenu']);
    }

    public function registerMenu(): void
    {
        add_menu_page(
            __('CAMPWP', 'campwp'),
            __('CAMPWP', 'campwp'),
            'edit_posts',
            'campwp',
            [$this, 'renderOverviewPage'],
            'dashicons-album',
            20
        );

        add_submenu_page('campwp', __('Overview', 'campwp'), __('Overview', 'campwp'), 'edit_posts', 'campwp', [$this, 'renderOverviewPage']);
        add_submenu_page('campwp', __('Add New', 'campwp'), __('Add New', 'campwp'), 'edit_posts', 'campwp-add-new', [$this, 'renderAddNewPage']);
        add_submenu_page('campwp', __('Releases', 'campwp'), __('Releases', 'campwp'), 'edit_posts', 'edit.php?post_type=' . PostTypeRegistrar::ALBUM_POST_TYPE);
        add_submenu_page('campwp', __('Tracks', 'campwp'), __('Tracks', 'campwp'), 'edit_posts', 'edit.php?post_type=' . PostTypeRegistrar::TRACK_POST_TYPE);
        add_submenu_page('campwp', __('Merch', 'campwp'), __('Merch', 'campwp'), 'edit_posts', 'campwp-merch', [$this, 'renderMerchPlaceholder']);
        add_submenu_page('campwp', __('Settings', 'campwp'), __('Settings', 'campwp'), 'manage_options', 'campwp-settings', [$this, 'renderSettingsPage']);
    }

    public function renderOverviewPage(): void
    {
        $albumCount = wp_count_posts(PostTypeRegistrar::ALBUM_POST_TYPE);
        $trackCount = wp_count_posts(PostTypeRegistrar::TRACK_POST_TYPE);
        $wooDetected = class_exists('WooCommerce');
        $mediaAvailable = (new WordPressMediaLibraryProvider())->isAvailable();

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('CAMPWP Overview', 'campwp') . '</h1>';
        echo '<p>' . esc_html__('Diagnostic summary of your music publishing setup.', 'campwp') . '</p>';
        echo '<table class="widefat striped" style="max-width:760px">';
        echo '<tbody>';
        echo '<tr><th scope="row">' . esc_html__('Plugin Version', 'campwp') . '</th><td>' . esc_html(defined('CAMPWP_VERSION') ? CAMPWP_VERSION : 'unknown') . '</td></tr>';
        echo '<tr><th scope="row">' . esc_html__('Releases', 'campwp') . '</th><td>' . esc_html((string) ($albumCount->publish ?? 0)) . ' ' . esc_html__('published', 'campwp') . ' / ' . esc_html((string) ($albumCount->draft ?? 0)) . ' ' . esc_html__('draft', 'campwp') . '</td></tr>';
        echo '<tr><th scope="row">' . esc_html__('Tracks', 'campwp') . '</th><td>' . esc_html((string) ($trackCount->publish ?? 0)) . ' ' . esc_html__('published', 'campwp') . ' / ' . esc_html((string) ($trackCount->draft ?? 0)) . ' ' . esc_html__('draft', 'campwp') . '</td></tr>';
        echo '<tr><th scope="row">' . esc_html__('WooCommerce', 'campwp') . '</th><td>' . esc_html($wooDetected ? __('Detected', 'campwp') : __('Not detected', 'campwp')) . '</td></tr>';
        echo '<tr><th scope="row">' . esc_html__('Media Handling', 'campwp') . '</th><td>' . esc_html($mediaAvailable ? __('Available', 'campwp') : __('Unavailable', 'campwp')) . '</td></tr>';
        echo '</tbody>';
        echo '</table>';
        echo '</div>';
    }

    public function renderAddNewPage(): void
    {
        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Add New Content', 'campwp') . '</h1>';
        echo '<p>' . esc_html__('Start publishing from a single place.', 'campwp') . '</p>';
        echo '<div style="display:flex;gap:16px;flex-wrap:wrap;max-width:900px">';
        $this->renderActionCard(__('New Release', 'campwp'), admin_url('post-new.php?post_type=' . PostTypeRegistrar::ALBUM_POST_TYPE), __('Create a new album or release.', 'campwp'));
        $this->renderActionCard(__('New Track', 'campwp'), admin_url('post-new.php?post_type=' . PostTypeRegistrar::TRACK_POST_TYPE), __('Create a standalone track or add one for a release.', 'campwp'));
        $this->renderActionCard(__('New Merch', 'campwp'), admin_url('admin.php?page=campwp-merch'), __('Placeholder for upcoming merch workflow.', 'campwp'));
        echo '</div>';
        echo '</div>';
    }

    public function renderMerchPlaceholder(): void
    {
        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Merch', 'campwp') . '</h1>';
        echo '<p>' . esc_html__('Merch publishing is not implemented yet. This section reserves workflow space for the next pass.', 'campwp') . '</p>';
        echo '</div>';
    }

    public function renderSettingsPage(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to manage CAMPWP settings.', 'campwp'));
        }

        $defaults = $this->defaultsSettings->getDefaults();

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('CAMPWP Settings', 'campwp') . '</h1>';
        echo '<form method="post" action="options.php">';
        settings_fields('campwp_settings');

        echo '<table class="form-table" role="presentation"><tbody>';
        echo '<tr><th scope="row"><label for="campwp-default-artist">' . esc_html__('Default Artist Display Name', 'campwp') . '</label></th><td><input type="text" class="regular-text" id="campwp-default-artist" name="' . esc_attr(DefaultsSettings::OPTION_KEY) . '[artist_display_name]" value="' . esc_attr((string) $defaults['artist_display_name']) . '" /></td></tr>';
        echo '<tr><th scope="row"><label for="campwp-default-label">' . esc_html__('Default Label Name', 'campwp') . '</label></th><td><input type="text" class="regular-text" id="campwp-default-label" name="' . esc_attr(DefaultsSettings::OPTION_KEY) . '[label_name]" value="' . esc_attr((string) $defaults['label_name']) . '" /></td></tr>';
        echo '<tr><th scope="row"><label for="campwp-default-download-mode">' . esc_html__('Default Download Mode', 'campwp') . '</label></th><td><select id="campwp-default-download-mode" name="' . esc_attr(DefaultsSettings::OPTION_KEY) . '[download_mode]">';

        $modeOptions = [
            'public' => __('Public', 'campwp'),
            'restricted' => __('Restricted (logged-in only)', 'campwp'),
            'purchase' => __('Purchase required', 'campwp'),
        ];

        foreach ($modeOptions as $mode => $label) {
            echo '<option value="' . esc_attr($mode) . '"' . selected((string) $defaults['download_mode'], $mode, false) . '>' . esc_html($label) . '</option>';
        }

        echo '</select></td></tr>';
        echo '<tr><th scope="row"><label for="campwp-default-credits">' . esc_html__('Default Credits Template', 'campwp') . '</label></th><td><textarea class="large-text" rows="5" id="campwp-default-credits" name="' . esc_attr(DefaultsSettings::OPTION_KEY) . '[credits_template]">' . esc_textarea((string) $defaults['credits_template']) . '</textarea><p class="description">' . esc_html__('Used to prefill credits fields when creating new releases or tracks.', 'campwp') . '</p></td></tr>';
        echo '</tbody></table>';

        submit_button(__('Save Settings', 'campwp'));
        echo '</form>';
        echo '</div>';
    }

    private function renderActionCard(string $title, string $url, string $description): void
    {
        echo '<div style="background:#fff;border:1px solid #ccd0d4;padding:24px;min-width:220px;flex:1">';
        echo '<h2 style="margin-top:0">' . esc_html($title) . '</h2>';
        echo '<p>' . esc_html($description) . '</p>';
        echo '<p><a class="button button-primary button-hero" href="' . esc_url($url) . '">' . esc_html($title) . '</a></p>';
        echo '</div>';
    }
}

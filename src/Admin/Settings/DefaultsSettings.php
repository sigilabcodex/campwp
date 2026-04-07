<?php

declare(strict_types=1);

namespace CampWP\Admin\Settings;

use CampWP\Domain\Commerce\EntitlementService;

final class DefaultsSettings
{
    public const OPTION_KEY = 'campwp_defaults';

    /**
     * @return array<string, string>
     */
    public function getDefaults(): array
    {
        $stored = get_option(self::OPTION_KEY, []);

        if (! is_array($stored)) {
            $stored = [];
        }

        return wp_parse_args($stored, [
            'artist_display_name' => '',
            'label_name' => '',
            'download_mode' => EntitlementService::MODE_PUBLIC,
            'credits_template' => '',
        ]);
    }

    public function register(): void
    {
        add_action('admin_init', [$this, 'registerSettings']);
    }

    public function registerSettings(): void
    {
        register_setting(
            'campwp_settings',
            self::OPTION_KEY,
            [
                'type' => 'array',
                'sanitize_callback' => [$this, 'sanitizeSettings'],
                'default' => [],
                'show_in_rest' => false,
            ]
        );
    }

    /**
     * @param mixed $value
     * @return array<string, string>
     */
    public function sanitizeSettings($value): array
    {
        if (! is_array($value)) {
            $value = [];
        }

        $downloadMode = sanitize_key((string) ($value['download_mode'] ?? EntitlementService::MODE_PUBLIC));
        $allowedModes = [
            EntitlementService::MODE_PUBLIC,
            EntitlementService::MODE_RESTRICTED,
            EntitlementService::MODE_PURCHASE,
        ];

        if (! in_array($downloadMode, $allowedModes, true)) {
            $downloadMode = EntitlementService::MODE_PUBLIC;
        }

        return [
            'artist_display_name' => sanitize_text_field((string) ($value['artist_display_name'] ?? '')),
            'label_name' => sanitize_text_field((string) ($value['label_name'] ?? '')),
            'download_mode' => $downloadMode,
            'credits_template' => sanitize_textarea_field((string) ($value['credits_template'] ?? '')),
        ];
    }
}

<?php

declare(strict_types=1);

namespace CampWP\Domain;

final class DomainService
{
    private const TRACK_META_ALBUM_ID = '_campwp_album_id';
    private const TRACK_META_ORDER = '_campwp_track_order';

    public function register(): void
    {
        add_action('init', [$this, 'registerTrackRelationshipMeta']);
    }

    public function registerTrackRelationshipMeta(): void
    {
        $trackPostType = $this->getTrackPostType();

        register_post_meta(
            $trackPostType,
            self::TRACK_META_ALBUM_ID,
            [
                'type' => 'integer',
                'single' => true,
                'default' => 0,
                'show_in_rest' => true,
                'sanitize_callback' => 'absint',
                'auth_callback' => static fn (): bool => current_user_can('edit_posts'),
            ]
        );

        register_post_meta(
            $trackPostType,
            self::TRACK_META_ORDER,
            [
                'type' => 'integer',
                'single' => true,
                'default' => 0,
                'show_in_rest' => true,
                'sanitize_callback' => static fn ($value): int => max(0, absint($value)),
                'auth_callback' => static fn (): bool => current_user_can('edit_posts'),
            ]
        );
    }

    private function getTrackPostType(): string
    {
        $postType = apply_filters('campwp_track_post_type', 'campwp_track');

        if (! is_string($postType) || $postType === '') {
            return 'campwp_track';
        }

        return sanitize_key($postType);
    }
}

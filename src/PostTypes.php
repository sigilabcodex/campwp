<?php

namespace CampWP;

class PostTypes
{
    public const ALBUM = 'campwp_album';
    public const TRACK = 'campwp_track';

    public function register(): void
    {
        add_action('init', [$this, 'register_post_types']);
    }

    public function register_post_types(): void
    {
        register_post_type(self::ALBUM, [
            'labels' => [
                'name' => __('Albums', 'campwp'),
                'singular_name' => __('Album', 'campwp'),
            ],
            'public' => true,
            'show_in_rest' => true,
            'menu_icon' => 'dashicons-album',
            'supports' => ['title', 'editor', 'excerpt', 'thumbnail'],
            'has_archive' => true,
            'rewrite' => ['slug' => 'albums'],
        ]);

        register_post_type(self::TRACK, [
            'labels' => [
                'name' => __('Tracks', 'campwp'),
                'singular_name' => __('Track', 'campwp'),
            ],
            'public' => true,
            'show_in_rest' => true,
            'menu_icon' => 'dashicons-format-audio',
            'supports' => ['title', 'editor', 'excerpt'],
            'has_archive' => true,
            'rewrite' => ['slug' => 'tracks'],
        ]);

        register_post_meta(self::ALBUM, '_campwp_release_date', [
            'type' => 'string',
            'single' => true,
            'show_in_rest' => true,
            'sanitize_callback' => 'sanitize_text_field',
            'auth_callback' => [$this, 'can_edit_post'],
        ]);

        register_post_meta(self::ALBUM, '_campwp_catalog_number', [
            'type' => 'string',
            'single' => true,
            'show_in_rest' => true,
            'sanitize_callback' => 'sanitize_text_field',
            'auth_callback' => [$this, 'can_edit_post'],
        ]);

        register_post_meta(self::TRACK, '_campwp_album_id', [
            'type' => 'integer',
            'single' => true,
            'show_in_rest' => true,
            'sanitize_callback' => 'absint',
            'auth_callback' => [$this, 'can_edit_post'],
        ]);

        register_post_meta(self::TRACK, '_campwp_track_number', [
            'type' => 'integer',
            'single' => true,
            'show_in_rest' => true,
            'sanitize_callback' => 'absint',
            'auth_callback' => [$this, 'can_edit_post'],
        ]);
    }

    public function can_edit_post(): bool
    {
        return current_user_can('edit_posts');
    }
}

<?php

declare(strict_types=1);

namespace CampWP\Infrastructure\ContentModel;

final class PostTypeRegistrar
{
    public const ALBUM_POST_TYPE = 'campwp_album';
    public const TRACK_POST_TYPE = 'campwp_track';

    public function register(): void
    {
        add_action('init', [$this, 'registerPostTypes']);
    }

    public function registerPostTypes(): void
    {
        register_post_type(self::ALBUM_POST_TYPE, $this->getAlbumPostTypeArgs());
        register_post_type(self::TRACK_POST_TYPE, $this->getTrackPostTypeArgs());

        $this->registerTrackMeta();
    }

    public function registerTrackMeta(): void
    {
        register_post_meta(
            self::TRACK_POST_TYPE,
            '_campwp_album_id',
            [
                'single' => true,
                'type' => 'integer',
                'default' => 0,
                'show_in_rest' => true,
                'sanitize_callback' => 'absint',
                'auth_callback' => static function (): bool {
                    return current_user_can('edit_posts');
                },
            ]
        );

        register_post_meta(
            self::TRACK_POST_TYPE,
            '_campwp_track_order',
            [
                'single' => true,
                'type' => 'integer',
                'default' => 0,
                'show_in_rest' => true,
                'sanitize_callback' => static function ($value): int {
                    return max(0, absint($value));
                },
                'auth_callback' => static function (): bool {
                    return current_user_can('edit_posts');
                },
            ]
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function getAlbumPostTypeArgs(): array
    {
        return [
            'labels' => [
                'name' => __('Albums', 'campwp'),
                'singular_name' => __('Album', 'campwp'),
                'menu_name' => __('Albums', 'campwp'),
                'add_new' => __('Add New', 'campwp'),
                'add_new_item' => __('Add New Album', 'campwp'),
                'edit_item' => __('Edit Album', 'campwp'),
                'new_item' => __('New Album', 'campwp'),
                'view_item' => __('View Album', 'campwp'),
                'search_items' => __('Search Albums', 'campwp'),
                'not_found' => __('No albums found', 'campwp'),
                'not_found_in_trash' => __('No albums found in Trash', 'campwp'),
                'all_items' => __('All Albums', 'campwp'),
                'archives' => __('Album Archives', 'campwp'),
            ],
            'description' => __('Albums released in CAMPWP.', 'campwp'),
            'public' => true,
            'publicly_queryable' => true,
            'show_ui' => true,
            'show_in_menu' => true,
            'show_in_admin_bar' => true,
            'show_in_nav_menus' => true,
            'exclude_from_search' => false,
            'has_archive' => 'albums',
            'rewrite' => [
                'slug' => 'album',
                'with_front' => false,
                'feeds' => true,
                'pages' => true,
            ],
            'menu_position' => 20,
            'menu_icon' => 'dashicons-album',
            'show_in_rest' => true,
            'rest_base' => 'albums',
            'supports' => ['title', 'editor', 'thumbnail', 'excerpt', 'custom-fields'],
            'capability_type' => 'post',
            'map_meta_cap' => true,
            'hierarchical' => false,
            'can_export' => true,
            'delete_with_user' => false,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getTrackPostTypeArgs(): array
    {
        return [
            'labels' => [
                'name' => __('Tracks', 'campwp'),
                'singular_name' => __('Track', 'campwp'),
                'menu_name' => __('Tracks', 'campwp'),
                'add_new' => __('Add New', 'campwp'),
                'add_new_item' => __('Add New Track', 'campwp'),
                'edit_item' => __('Edit Track', 'campwp'),
                'new_item' => __('New Track', 'campwp'),
                'view_item' => __('View Track', 'campwp'),
                'search_items' => __('Search Tracks', 'campwp'),
                'not_found' => __('No tracks found', 'campwp'),
                'not_found_in_trash' => __('No tracks found in Trash', 'campwp'),
                'all_items' => __('All Tracks', 'campwp'),
                'archives' => __('Track Archives', 'campwp'),
            ],
            'description' => __('Tracks released in CAMPWP.', 'campwp'),
            'public' => true,
            'publicly_queryable' => true,
            'show_ui' => true,
            'show_in_menu' => true,
            'show_in_admin_bar' => true,
            'show_in_nav_menus' => true,
            'exclude_from_search' => false,
            'has_archive' => false,
            'rewrite' => [
                'slug' => 'track',
                'with_front' => false,
                'feeds' => false,
                'pages' => false,
            ],
            'menu_position' => 21,
            'menu_icon' => 'dashicons-format-audio',
            'show_in_rest' => true,
            'rest_base' => 'tracks',
            'supports' => ['title', 'editor', 'thumbnail', 'excerpt', 'custom-fields'],
            'capability_type' => 'post',
            'map_meta_cap' => true,
            'hierarchical' => false,
            'can_export' => true,
            'delete_with_user' => false,
        ];
    }
}

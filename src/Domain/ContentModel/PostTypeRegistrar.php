<?php

declare(strict_types=1);

namespace CampWP\Domain\ContentModel;

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
    }

    /**
     * @return array<string, mixed>
     */
    private function getAlbumPostTypeArgs(): array
    {
        return [
            'labels' => [
                'name' => __('Albums', 'campwp'),
                'singular_name' => __('Album', 'campwp'),
                'menu_name' => __('Albums', 'campwp'),
                'name_admin_bar' => __('Album', 'campwp'),
                'add_new' => __('Add New', 'campwp'),
                'add_new_item' => __('Add New Album', 'campwp'),
                'edit_item' => __('Edit Album', 'campwp'),
                'new_item' => __('New Album', 'campwp'),
                'view_item' => __('View Album', 'campwp'),
                'view_items' => __('View Albums', 'campwp'),
                'search_items' => __('Search Albums', 'campwp'),
                'not_found' => __('No albums found.', 'campwp'),
                'not_found_in_trash' => __('No albums found in Trash.', 'campwp'),
                'all_items' => __('All Albums', 'campwp'),
                'archives' => __('Album Archives', 'campwp'),
                'attributes' => __('Album Attributes', 'campwp'),
                'featured_image' => __('Album Cover Image', 'campwp'),
                'set_featured_image' => __('Set album cover image', 'campwp'),
                'remove_featured_image' => __('Remove album cover image', 'campwp'),
                'use_featured_image' => __('Use as album cover image', 'campwp'),
                'insert_into_item' => __('Insert into album', 'campwp'),
                'uploaded_to_this_item' => __('Uploaded to this album', 'campwp'),
                'filter_items_list' => __('Filter albums list', 'campwp'),
                'items_list_navigation' => __('Albums list navigation', 'campwp'),
                'items_list' => __('Albums list', 'campwp'),
                'item_published' => __('Album published.', 'campwp'),
                'item_updated' => __('Album updated.', 'campwp'),
            ],
            'description' => __('Albums managed by CAMPWP.', 'campwp'),
            'public' => true,
            'publicly_queryable' => true,
            'show_ui' => true,
            'show_in_menu' => true,
            'show_in_admin_bar' => true,
            'show_in_nav_menus' => true,
            'exclude_from_search' => false,
            'has_archive' => true,
            'rewrite' => [
                'slug' => 'album',
                'with_front' => false,
            ],
            'query_var' => true,
            'can_export' => true,
            'delete_with_user' => false,
            'hierarchical' => false,
            'menu_position' => 20,
            'menu_icon' => 'dashicons-album',
            'supports' => ['title', 'editor', 'excerpt', 'thumbnail', 'custom-fields'],
            'show_in_rest' => true,
            'rest_base' => 'albums',
            'rest_controller_class' => 'WP_REST_Posts_Controller',
            'capability_type' => 'post',
            'map_meta_cap' => true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function getTrackPostTypeArgs(): array
    {
        return [
            'labels' => [
                'name' => __('Tracks', 'campwp'),
                'singular_name' => __('Track', 'campwp'),
                'menu_name' => __('Tracks', 'campwp'),
                'name_admin_bar' => __('Track', 'campwp'),
                'add_new' => __('Add New', 'campwp'),
                'add_new_item' => __('Add New Track', 'campwp'),
                'edit_item' => __('Edit Track', 'campwp'),
                'new_item' => __('New Track', 'campwp'),
                'view_item' => __('View Track', 'campwp'),
                'view_items' => __('View Tracks', 'campwp'),
                'search_items' => __('Search Tracks', 'campwp'),
                'not_found' => __('No tracks found.', 'campwp'),
                'not_found_in_trash' => __('No tracks found in Trash.', 'campwp'),
                'all_items' => __('All Tracks', 'campwp'),
                'archives' => __('Track Archives', 'campwp'),
                'attributes' => __('Track Attributes', 'campwp'),
                'featured_image' => __('Track Artwork', 'campwp'),
                'set_featured_image' => __('Set track artwork', 'campwp'),
                'remove_featured_image' => __('Remove track artwork', 'campwp'),
                'use_featured_image' => __('Use as track artwork', 'campwp'),
                'insert_into_item' => __('Insert into track', 'campwp'),
                'uploaded_to_this_item' => __('Uploaded to this track', 'campwp'),
                'filter_items_list' => __('Filter tracks list', 'campwp'),
                'items_list_navigation' => __('Tracks list navigation', 'campwp'),
                'items_list' => __('Tracks list', 'campwp'),
                'item_published' => __('Track published.', 'campwp'),
                'item_updated' => __('Track updated.', 'campwp'),
            ],
            'description' => __('Tracks managed by CAMPWP.', 'campwp'),
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
            ],
            'query_var' => true,
            'can_export' => true,
            'delete_with_user' => false,
            'hierarchical' => false,
            'menu_position' => 21,
            'menu_icon' => 'dashicons-format-audio',
            'supports' => ['title', 'editor', 'excerpt', 'thumbnail', 'custom-fields'],
            'show_in_rest' => true,
            'rest_base' => 'tracks',
            'rest_controller_class' => 'WP_REST_Posts_Controller',
            'capability_type' => 'post',
            'map_meta_cap' => true,
        ];
    }
}

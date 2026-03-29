<?php

declare(strict_types=1);

namespace CampWP\Admin;

use CampWP\Admin\Metadata\CoreMetadataMetaBox;
use CampWP\Domain\ContentModel\AlbumTrackRelationshipService;
use CampWP\Domain\Metadata\MetadataKeys;

final class AdminService
{
    private const NONCE_ACTION = 'campwp_save_album_tracks';
    private const NONCE_NAME = 'campwp_album_tracks_nonce';

    private AlbumTrackRelationshipService $albumTrackRelationships;

    public function __construct(?AlbumTrackRelationshipService $albumTrackRelationships = null)
    {
        $this->albumTrackRelationships = $albumTrackRelationships ?? new AlbumTrackRelationshipService();
    }

    public function register(): void
    {
        (new CoreMetadataMetaBox())->register();

        add_action('add_meta_boxes', [$this, 'registerAlbumTracksMetaBox']);

        foreach ($this->getAlbumPostTypes() as $albumPostType) {
            add_action('save_post_' . $albumPostType, [$this, 'saveAlbumTracksMetaBox'], 10, 2);
        }
    }

    public function registerAlbumTracksMetaBox(): void
    {
        foreach ($this->getAlbumPostTypes() as $albumPostType) {
            add_meta_box(
                'campwp-album-tracks',
                __('Album Tracks', 'campwp'),
                [$this, 'renderAlbumTracksMetaBox'],
                $albumPostType,
                'normal',
                'default'
            );
        }
    }

    /**
     * @param \WP_Post $post
     */
    public function renderAlbumTracksMetaBox($post): void
    {
        wp_nonce_field(self::NONCE_ACTION, self::NONCE_NAME);

        $selectedTracks = $this->albumTrackRelationships->getTracksForAlbum((int) $post->ID);
        $selectedById = [];

        foreach ($selectedTracks as $selectedTrack) {
            $selectedById[(int) $selectedTrack->ID] = (int) get_post_meta((int) $selectedTrack->ID, MetadataKeys::TRACK_ORDER, true);
        }

        $trackPosts = $this->albumTrackRelationships->getAssignableTracks();

        if ($trackPosts === []) {
            echo '<p>' . esc_html__('No tracks found. Create tracks first, then return to assign them to this album.', 'campwp') . '</p>';
            return;
        }

        echo '<p>' . esc_html__('Select tracks and set their order for this album. Tracks can only belong to one album in this initial model.', 'campwp') . '</p>';
        echo '<table class="widefat striped">';
        echo '<thead><tr>';
        echo '<th scope="col">' . esc_html__('Assign', 'campwp') . '</th>';
        echo '<th scope="col">' . esc_html__('Order', 'campwp') . '</th>';
        echo '<th scope="col">' . esc_html__('Track', 'campwp') . '</th>';
        echo '</tr></thead><tbody>';

        foreach ($trackPosts as $trackPost) {
            $trackId = (int) $trackPost->ID;
            $assignedAlbumId = (int) get_post_meta($trackId, MetadataKeys::TRACK_ALBUM_ID, true);
            $isSelected = isset($selectedById[$trackId]);
            $orderValue = $selectedById[$trackId] ?? 0;

            echo '<tr>';
            echo '<td><label>';
            echo '<input type="checkbox" name="campwp_album_tracks[selected][]" value="' . esc_attr((string) $trackId) . '"' . checked($isSelected, true, false) . ' />';
            echo '<span class="screen-reader-text">' . esc_html(sprintf(__('Assign track %s', 'campwp'), get_the_title($trackId))) . '</span>';
            echo '</label></td>';

            echo '<td>';
            echo '<input type="number" min="1" step="1" class="small-text" name="campwp_album_tracks[order][' . esc_attr((string) $trackId) . ']" value="' . esc_attr((string) max($orderValue, 1)) . '" />';
            echo '</td>';

            echo '<td>';
            echo '<strong>' . esc_html(get_the_title($trackId)) . '</strong>';

            if ($assignedAlbumId !== 0 && $assignedAlbumId !== (int) $post->ID) {
                /* translators: %d is an album post ID. */
                echo '<br /><em>' . esc_html(sprintf(__('Currently assigned to album #%d. Saving here will move it.', 'campwp'), $assignedAlbumId)) . '</em>';
            }

            echo '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
    }

    /**
     * @param \WP_Post $post
     */
    public function saveAlbumTracksMetaBox(int $postId, $post): void
    {
        if (! isset($_POST[self::NONCE_NAME]) || ! wp_verify_nonce(sanitize_text_field(wp_unslash((string) $_POST[self::NONCE_NAME])), self::NONCE_ACTION)) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (wp_is_post_revision($postId) || wp_is_post_autosave($postId)) {
            return;
        }

        if (! in_array($post->post_type, $this->getAlbumPostTypes(), true)) {
            return;
        }

        if (! current_user_can('edit_post', $postId)) {
            return;
        }

        $rawTracks = $_POST['campwp_album_tracks'] ?? [];

        if (! is_array($rawTracks)) {
            $rawTracks = [];
        }

        $selectedIds = [];
        if (isset($rawTracks['selected']) && is_array($rawTracks['selected'])) {
            $selectedIds = array_values(array_unique(array_filter(array_map('absint', wp_unslash($rawTracks['selected'])))));
        }

        $rawOrders = [];
        if (isset($rawTracks['order']) && is_array($rawTracks['order'])) {
            $rawOrders = wp_unslash($rawTracks['order']);
        }

        $this->albumTrackRelationships->saveAlbumTrackAssignments($postId, $selectedIds, $rawOrders);
    }

    /**
     * @return list<string>
     */
    private function getAlbumPostTypes(): array
    {
        $postTypes = apply_filters('campwp_album_post_types', ['campwp_album']);

        if (! is_array($postTypes)) {
            return ['campwp_album'];
        }

        return array_values(array_unique(array_filter(array_map('sanitize_key', $postTypes))));
    }

}

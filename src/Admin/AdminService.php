<?php

declare(strict_types=1);

namespace CampWP\Admin;

final class AdminService
{
    private const TRACK_META_ALBUM_ID = '_campwp_album_id';
    private const TRACK_META_ORDER = '_campwp_track_order';
    private const NONCE_ACTION = 'campwp_save_album_tracks';
    private const NONCE_NAME = 'campwp_album_tracks_nonce';

    public function register(): void
    {
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

        $selectedTracks = $this->getSelectedTracks((int) $post->ID);
        $selectedById = [];

        foreach ($selectedTracks as $selectedTrack) {
            $selectedById[(int) $selectedTrack->ID] = (int) get_post_meta((int) $selectedTrack->ID, self::TRACK_META_ORDER, true);
        }

        $trackPosts = get_posts([
            'post_type' => $this->getTrackPostType(),
            'posts_per_page' => -1,
            'post_status' => ['publish', 'draft', 'pending', 'future', 'private'],
            'orderby' => 'title',
            'order' => 'ASC',
            'suppress_filters' => false,
        ]);

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
            $assignedAlbumId = (int) get_post_meta($trackId, self::TRACK_META_ALBUM_ID, true);
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

        $validSelectedIds = [];
        foreach ($selectedIds as $trackId) {
            if (get_post_type($trackId) !== $this->getTrackPostType()) {
                continue;
            }

            if (! current_user_can('edit_post', $trackId)) {
                continue;
            }

            $validSelectedIds[] = $trackId;
        }

        usort(
            $validSelectedIds,
            static function (int $left, int $right) use ($rawOrders): int {
                $leftOrder = isset($rawOrders[$left]) ? max(1, (int) $rawOrders[$left]) : PHP_INT_MAX;
                $rightOrder = isset($rawOrders[$right]) ? max(1, (int) $rawOrders[$right]) : PHP_INT_MAX;

                if ($leftOrder === $rightOrder) {
                    return $left <=> $right;
                }

                return $leftOrder <=> $rightOrder;
            }
        );

        $currentTrackIds = wp_list_pluck($this->getSelectedTracks($postId), 'ID');
        $currentTrackIds = array_map('absint', $currentTrackIds);

        foreach ($currentTrackIds as $currentTrackId) {
            if (in_array($currentTrackId, $validSelectedIds, true)) {
                continue;
            }

            if (! current_user_can('edit_post', $currentTrackId)) {
                continue;
            }

            delete_post_meta($currentTrackId, self::TRACK_META_ALBUM_ID);
            delete_post_meta($currentTrackId, self::TRACK_META_ORDER);
        }

        $orderPosition = 1;
        foreach ($validSelectedIds as $trackId) {
            update_post_meta($trackId, self::TRACK_META_ALBUM_ID, $postId);
            update_post_meta($trackId, self::TRACK_META_ORDER, $orderPosition);
            $orderPosition++;
        }
    }

    /**
     * @return list<\WP_Post>
     */
    private function getSelectedTracks(int $albumId): array
    {
        $tracks = get_posts([
            'post_type' => $this->getTrackPostType(),
            'posts_per_page' => -1,
            'post_status' => ['publish', 'draft', 'pending', 'future', 'private'],
            'meta_query' => [
                [
                    'key' => self::TRACK_META_ALBUM_ID,
                    'value' => $albumId,
                    'compare' => '=',
                    'type' => 'NUMERIC',
                ],
            ],
            'meta_key' => self::TRACK_META_ORDER,
            'orderby' => ['meta_value_num' => 'ASC', 'ID' => 'ASC'],
            'suppress_filters' => false,
        ]);

        return is_array($tracks) ? $tracks : [];
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

    private function getTrackPostType(): string
    {
        $postType = apply_filters('campwp_track_post_type', 'campwp_track');

        if (! is_string($postType) || $postType === '') {
            return 'campwp_track';
        }

        return sanitize_key($postType);
    }
}

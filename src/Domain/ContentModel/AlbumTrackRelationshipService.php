<?php

declare(strict_types=1);

namespace CampWP\Domain\ContentModel;

use CampWP\Domain\Metadata\MetadataKeys;

final class AlbumTrackRelationshipService
{
    /**
     * @return list<\WP_Post>
     */
    public function getTracksForAlbum(int $albumId): array
    {
        $tracks = get_posts([
            'post_type' => $this->getTrackPostType(),
            'posts_per_page' => -1,
            'post_status' => ['publish', 'draft', 'pending', 'future', 'private'],
            'meta_query' => [
                [
                    'key' => MetadataKeys::TRACK_ALBUM_ID,
                    'value' => $albumId,
                    'compare' => '=',
                    'type' => 'NUMERIC',
                ],
            ],
            'meta_key' => MetadataKeys::TRACK_ORDER,
            'orderby' => ['meta_value_num' => 'ASC', 'ID' => 'ASC'],
            'suppress_filters' => false,
        ]);

        return is_array($tracks) ? $tracks : [];
    }

    /**
     * @return list<\WP_Post>
     */
    public function getAssignableTracks(): array
    {
        $tracks = get_posts([
            'post_type' => $this->getTrackPostType(),
            'posts_per_page' => -1,
            'post_status' => ['publish', 'draft', 'pending', 'future', 'private'],
            'orderby' => 'title',
            'order' => 'ASC',
            'suppress_filters' => false,
        ]);

        return is_array($tracks) ? $tracks : [];
    }

    /**
     * @param array<int|string, mixed> $rawOrders
     * @param list<int> $selectedTrackIds
     */
    public function saveAlbumTrackAssignments(int $albumId, array $selectedTrackIds, array $rawOrders): void
    {
        $validSelectedIds = $this->validateTrackIds($selectedTrackIds);

        usort(
            $validSelectedIds,
            static function (int $leftTrackId, int $rightTrackId) use ($rawOrders): int {
                $leftOrder = isset($rawOrders[$leftTrackId]) ? max(1, absint($rawOrders[$leftTrackId])) : PHP_INT_MAX;
                $rightOrder = isset($rawOrders[$rightTrackId]) ? max(1, absint($rawOrders[$rightTrackId])) : PHP_INT_MAX;

                if ($leftOrder === $rightOrder) {
                    return $leftTrackId <=> $rightTrackId;
                }

                return $leftOrder <=> $rightOrder;
            }
        );

        $existingTrackIds = wp_list_pluck($this->getTracksForAlbum($albumId), 'ID');
        $existingTrackIds = array_map('absint', $existingTrackIds);

        foreach ($existingTrackIds as $existingTrackId) {
            if (in_array($existingTrackId, $validSelectedIds, true)) {
                continue;
            }

            if (! current_user_can('edit_post', $existingTrackId)) {
                continue;
            }

            delete_post_meta($existingTrackId, MetadataKeys::TRACK_ALBUM_ID);
            delete_post_meta($existingTrackId, MetadataKeys::TRACK_ORDER);
        }

        $orderPosition = 1;
        foreach ($validSelectedIds as $trackId) {
            update_post_meta($trackId, MetadataKeys::TRACK_ALBUM_ID, $albumId);
            update_post_meta($trackId, MetadataKeys::TRACK_ORDER, $orderPosition);
            $orderPosition++;
        }
    }

    /**
     * @param list<int> $selectedTrackIds
     * @return list<int>
     */
    private function validateTrackIds(array $selectedTrackIds): array
    {
        $validIds = [];

        foreach ($selectedTrackIds as $trackId) {
            if (get_post_type($trackId) !== $this->getTrackPostType()) {
                continue;
            }

            if (! current_user_can('edit_post', $trackId)) {
                continue;
            }

            $validIds[] = $trackId;
        }

        return $validIds;
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

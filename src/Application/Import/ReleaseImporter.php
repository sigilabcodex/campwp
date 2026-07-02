<?php

declare(strict_types=1);

namespace CampWP\Application\Import;

use CampWP\Domain\Import\ReleaseManifest;
use CampWP\Domain\Import\TrackManifest;
use CampWP\Domain\Metadata\MetadataKeys;

final class ReleaseImporter
{
    private ManifestReader $reader;
    private ManifestNormalizer $normalizer;

    public function __construct(?ManifestReader $reader = null, ?ManifestNormalizer $normalizer = null)
    {
        $this->reader = $reader ?? new ManifestReader();
        $this->normalizer = $normalizer ?? new ManifestNormalizer();
    }

    public function importJsonString(string $json, bool $dryRun = false): ImportResult
    {
        try {
            return $this->importArray($this->reader->readJsonString($json), $dryRun);
        } catch (\InvalidArgumentException $exception) {
            return ImportResult::failed([$exception->getMessage()], [], $dryRun);
        }
    }

    /**
     * @param array<string, mixed> $manifest
     */
    public function importArray(array $manifest, bool $dryRun = false): ImportResult
    {
        try {
            $manifest = $this->reader->readArray($manifest);
        } catch (\InvalidArgumentException $exception) {
            return ImportResult::failed([$exception->getMessage()], [], $dryRun);
        }

        $normalized = $this->normalizer->normalize($manifest);
        if (! $normalized['manifest'] instanceof ReleaseManifest) {
            return ImportResult::failed($normalized['errors'], $normalized['warnings'], $dryRun);
        }

        return $this->importNormalized($normalized['manifest'], $dryRun, $normalized['warnings']);
    }

    public function importLocalFile(string $path, bool $dryRun = false): ImportResult
    {
        try {
            return $this->importArray($this->reader->readLocalFile($path), $dryRun);
        } catch (\InvalidArgumentException $exception) {
            return ImportResult::failed([$exception->getMessage()], [], $dryRun);
        }
    }

    /**
     * @param list<string> $warnings
     */
    private function importNormalized(ReleaseManifest $manifest, bool $dryRun, array $warnings): ImportResult
    {
        $errors = [];
        $created = 0;
        $updated = 0;
        $unchanged = 0;
        $trackResults = [];

        $albumId = $this->findAlbum($manifest);
        if ($albumId === 0) {
            $legacyAlbumId = $this->findLegacyAlbumWithoutCatalogIdentity($manifest);
            if ($legacyAlbumId > 0) {
                $warnings[] = 'Existing imported album without catalog identity was not matched automatically.';
            }
        }

        $albumAction = $albumId > 0 ? $this->postAction($albumId, $this->albumPostData($manifest), $manifest->albumMeta) : 'created';

        if ($dryRun) {
            $plannedCreated = $albumAction === 'created' ? 1 : 0;
            $plannedUpdated = $albumAction === 'updated' ? 1 : 0;
            $plannedUnchanged = $albumAction === 'unchanged' ? 1 : 0;
            $plannedAlbumId = $albumId;

            foreach ($manifest->tracks as $track) {
                $existingTrackId = $plannedAlbumId > 0 ? $this->findTrack($plannedAlbumId, $track->externalTrackId) : 0;
                $trackAction = $existingTrackId > 0
                    ? $this->postAction($existingTrackId, $this->trackPostData($track, $plannedAlbumId, $manifest->postStatus), $track->meta + [MetadataKeys::TRACK_ALBUM_ID => $plannedAlbumId, MetadataKeys::TRACK_ORDER => $track->index])
                    : 'created';

                if ($trackAction === 'created') {
                    $plannedCreated++;
                } elseif ($trackAction === 'updated') {
                    $plannedUpdated++;
                } else {
                    $plannedUnchanged++;
                }

                $trackResults[] = new TrackImportResult($existingTrackId, $track->externalTrackId, $track->index, $trackAction);
            }

            return new ImportResult($plannedAlbumId, $albumAction, $trackResults, $plannedCreated, $plannedUpdated, $plannedUnchanged, $warnings, [], $manifest->getIdentityKey(), true, 'dry_run');
        }

        if ($albumId <= 0) {
            $inserted = wp_insert_post($this->albumPostData($manifest), true);
            if (is_wp_error($inserted)) {
                return ImportResult::failed([$inserted->get_error_message()], $warnings, false, $manifest->getIdentityKey());
            }
            $albumId = (int) $inserted;
            $created++;
        } elseif ($albumAction === 'updated') {
            $updatedPost = wp_update_post($this->albumPostData($manifest) + ['ID' => $albumId], true);
            if (is_wp_error($updatedPost)) {
                return ImportResult::failed([$updatedPost->get_error_message()], $warnings, false, $manifest->getIdentityKey());
            }
            $updated++;
        } else {
            $unchanged++;
        }

        $metaErrors = $this->writeMeta($albumId, $manifest->albumMeta);
        if ($metaErrors !== []) {
            return $this->partialResult($albumId, $albumAction, $trackResults, $created, $updated, $unchanged, $warnings, $metaErrors, $manifest);
        }

        foreach ($manifest->tracks as $track) {
            $existingTrackId = $this->findTrack($albumId, $track->externalTrackId);
            $trackPostData = $this->trackPostData($track, $albumId, $manifest->postStatus);
            $trackMeta = $track->meta + [MetadataKeys::TRACK_ALBUM_ID => $albumId, MetadataKeys::TRACK_ORDER => $track->index];
            $action = $existingTrackId > 0 ? $this->postAction($existingTrackId, $trackPostData, $trackMeta) : 'created';

            $trackId = $existingTrackId;
            if ($existingTrackId <= 0) {
                $inserted = wp_insert_post($trackPostData, true);
                if (is_wp_error($inserted)) {
                    $errors[] = sprintf('Track %s failed: %s', $track->externalTrackId, $inserted->get_error_message());
                    $trackResults[] = new TrackImportResult(0, $track->externalTrackId, $track->index, 'failed');
                    return $this->partialResult($albumId, $albumAction, $trackResults, $created, $updated, $unchanged, $warnings, $errors, $manifest);
                }
                $trackId = (int) $inserted;
                $created++;
                $trackMeta[MetadataKeys::TRACK_ALBUM_ID] = $albumId;
            } elseif ($action === 'updated') {
                $updatedPost = wp_update_post($trackPostData + ['ID' => $existingTrackId], true);
                if (is_wp_error($updatedPost)) {
                    $errors[] = sprintf('Track %s failed: %s', $track->externalTrackId, $updatedPost->get_error_message());
                    $trackResults[] = new TrackImportResult($existingTrackId, $track->externalTrackId, $track->index, 'failed');
                    return $this->partialResult($albumId, $albumAction, $trackResults, $created, $updated, $unchanged, $warnings, $errors, $manifest);
                }
                $updated++;
            } else {
                $unchanged++;
            }

            $metaErrors = $this->writeMeta($trackId, $trackMeta);
            if ($metaErrors !== []) {
                $errors = array_map(static fn (string $error): string => sprintf('Track %s failed: %s', $track->externalTrackId, $error), $metaErrors);
                $trackResults[] = new TrackImportResult($trackId, $track->externalTrackId, $track->index, 'failed');
                return $this->partialResult($albumId, $albumAction, $trackResults, $created, $updated, $unchanged, $warnings, $errors, $manifest);
            }

            $trackResults[] = new TrackImportResult($trackId, $track->externalTrackId, $track->index, $action);
        }

        $status = $created === 0 && $updated === 0 ? 'unchanged' : 'success';

        return new ImportResult(
            $albumId,
            $albumAction,
            $trackResults,
            $created,
            $updated,
            $unchanged,
            $warnings,
            [],
            $manifest->getIdentityKey(),
            false,
            $status
        );
    }

    private function findAlbum(ReleaseManifest $manifest): int
    {
        $posts = get_posts([
            'post_type' => 'campwp_album',
            'post_status' => ['publish', 'draft', 'pending', 'future', 'private'],
            'posts_per_page' => -1,
            'fields' => 'ids',
            'meta_query' => [
                ['key' => MetadataKeys::ALBUM_CATALOG_IDENTITY, 'value' => $manifest->catalogIdentity, 'compare' => '='],
                ['key' => MetadataKeys::ALBUM_SOURCE_PROVIDER, 'value' => $manifest->provider, 'compare' => '='],
                ['key' => MetadataKeys::ALBUM_EXTERNAL_RELEASE_ID, 'value' => $manifest->externalReleaseId, 'compare' => '='],
            ],
            'suppress_filters' => false,
        ]);

        return is_array($posts) && isset($posts[0]) ? (int) $posts[0] : 0;
    }

    private function findTrack(int $albumId, string $externalTrackId): int
    {
        $posts = get_posts([
            'post_type' => 'campwp_track',
            'post_status' => ['publish', 'draft', 'pending', 'future', 'private'],
            'posts_per_page' => 1,
            'fields' => 'ids',
            'meta_query' => [
                ['key' => MetadataKeys::TRACK_ALBUM_ID, 'value' => $albumId, 'compare' => '=', 'type' => 'NUMERIC'],
                ['key' => MetadataKeys::TRACK_EXTERNAL_TRACK_ID, 'value' => $externalTrackId, 'compare' => '='],
            ],
            'suppress_filters' => false,
        ]);

        return is_array($posts) && isset($posts[0]) ? (int) $posts[0] : 0;
    }

    /** @return array<string, mixed> */
    private function albumPostData(ReleaseManifest $manifest): array
    {
        return [
            'post_type' => 'campwp_album',
            'post_status' => $manifest->postStatus,
            'post_title' => $manifest->title,
            'post_content' => $manifest->content,
        ];
    }

    /** @return array<string, mixed> */
    private function trackPostData(TrackManifest $track, int $albumId, string $postStatus): array
    {
        return [
            'post_type' => 'campwp_track',
            'post_status' => $postStatus,
            'post_title' => $track->title,
            'post_parent' => $albumId,
            'menu_order' => $track->index,
        ];
    }

    /** @param array<string, mixed> $postData @param array<string, mixed> $meta */
    private function postAction(int $postId, array $postData, array $meta): string
    {
        $post = get_post($postId);
        if (! $post instanceof \WP_Post) {
            return 'created';
        }

        foreach ($postData as $key => $value) {
            if (property_exists($post, $key) && (string) $post->{$key} !== (string) $value) {
                return 'updated';
            }
        }

        foreach ($meta as $key => $value) {
            if ((string) get_post_meta($postId, (string) $key, true) !== (string) $value) {
                return 'updated';
            }
        }

        return 'unchanged';
    }

    private function findLegacyAlbumWithoutCatalogIdentity(ReleaseManifest $manifest): int
    {
        $posts = get_posts([
            'post_type' => 'campwp_album',
            'post_status' => ['publish', 'draft', 'pending', 'future', 'private'],
            'posts_per_page' => 1,
            'fields' => 'ids',
            'meta_query' => [
                ['key' => MetadataKeys::ALBUM_SOURCE_PROVIDER, 'value' => $manifest->provider, 'compare' => '='],
                ['key' => MetadataKeys::ALBUM_EXTERNAL_RELEASE_ID, 'value' => $manifest->externalReleaseId, 'compare' => '='],
                ['key' => MetadataKeys::ALBUM_CATALOG_IDENTITY, 'compare' => 'NOT EXISTS'],
            ],
            'suppress_filters' => false,
        ]);

        return is_array($posts) && isset($posts[0]) ? (int) $posts[0] : 0;
    }

    /** @param list<TrackImportResult> $trackResults @param list<string> $warnings @param list<string> $errors */
    private function partialResult(int $albumId, string $albumAction, array $trackResults, int $created, int $updated, int $unchanged, array $warnings, array $errors, ReleaseManifest $manifest): ImportResult
    {
        return new ImportResult($albumId, $albumAction, $trackResults, $created, $updated, $unchanged, $warnings, $errors, $manifest->getIdentityKey(), false, 'partial');
    }

    /**
     * @param array<string, mixed> $meta
     * @return list<string>
     */
    private function writeMeta(int $postId, array $meta): array
    {
        $errors = [];

        foreach ($meta as $key => $value) {
            $metaKey = (string) $key;
            $existing = get_post_meta($postId, $metaKey, true);
            if ((string) $existing === (string) $value) {
                continue;
            }

            update_post_meta($postId, $metaKey, $value);
            $persisted = get_post_meta($postId, $metaKey, true);
            if ((string) $persisted !== (string) $value) {
                $errors[] = sprintf('Meta %s failed to persist.', $metaKey);
            }
        }

        return $errors;
    }
}

<?php

declare(strict_types=1);

namespace CampWP\Application\Import;

use CampWP\Application\Import\Media\CoverSideloaderInterface;
use CampWP\Application\Import\Media\WordPressCoverSideloader;
use CampWP\Domain\Import\CoverManifest;
use CampWP\Domain\Import\ReleaseManifest;
use CampWP\Domain\Import\TrackManifest;
use CampWP\Domain\Metadata\MetadataKeys;

final class ReleaseImporter
{
    private ManifestReader $reader;
    private ManifestNormalizer $normalizer;
    private CoverSideloaderInterface $coverSideloader;

    public function __construct(?ManifestReader $reader = null, ?ManifestNormalizer $normalizer = null, ?CoverSideloaderInterface $coverSideloader = null)
    {
        $this->reader = $reader ?? new ManifestReader();
        $this->normalizer = $normalizer ?? new ManifestNormalizer();
        $this->coverSideloader = $coverSideloader ?? new WordPressCoverSideloader();
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
        $coverResult = new CoverImportResult('skipped', 0, '', '', 'No cover provided.');

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

            $coverResult = $this->coverAction($manifest->cover, $plannedAlbumId);

            return new ImportResult($plannedAlbumId, $albumAction, $trackResults, $plannedCreated, $plannedUpdated, $plannedUnchanged, $warnings, [], $manifest->getIdentityKey(), true, 'dry_run', $coverResult);
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
            return $this->partialResult($albumId, $albumAction, $trackResults, $created, $updated, $unchanged, $warnings, $metaErrors, $manifest, $coverResult);
        }

        [$coverResult, $coverWarnings] = $this->syncCover($manifest->cover, $albumId);
        $warnings = array_merge($warnings, $coverWarnings);

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
                    return $this->partialResult($albumId, $albumAction, $trackResults, $created, $updated, $unchanged, $warnings, $errors, $manifest, $coverResult);
                }
                $trackId = (int) $inserted;
                $created++;
                $trackMeta[MetadataKeys::TRACK_ALBUM_ID] = $albumId;
            } elseif ($action === 'updated') {
                $updatedPost = wp_update_post($trackPostData + ['ID' => $existingTrackId], true);
                if (is_wp_error($updatedPost)) {
                    $errors[] = sprintf('Track %s failed: %s', $track->externalTrackId, $updatedPost->get_error_message());
                    $trackResults[] = new TrackImportResult($existingTrackId, $track->externalTrackId, $track->index, 'failed');
                    return $this->partialResult($albumId, $albumAction, $trackResults, $created, $updated, $unchanged, $warnings, $errors, $manifest, $coverResult);
                }
                $updated++;
            } else {
                $unchanged++;
            }

            $metaErrors = $this->writeMeta($trackId, $trackMeta);
            if ($metaErrors !== []) {
                $errors = array_map(static fn (string $error): string => sprintf('Track %s failed: %s', $track->externalTrackId, $error), $metaErrors);
                $trackResults[] = new TrackImportResult($trackId, $track->externalTrackId, $track->index, 'failed');
                return $this->partialResult($albumId, $albumAction, $trackResults, $created, $updated, $unchanged, $warnings, $errors, $manifest, $coverResult);
            }

            $trackResults[] = new TrackImportResult($trackId, $track->externalTrackId, $track->index, $action);
        }

        $status = $created === 0 && $updated === 0 && in_array($coverResult->action, ['unchanged', 'skipped'], true) ? 'unchanged' : 'success';

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
            $status,
            $coverResult
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

    private function findCoverAttachment(CoverManifest $cover, int $albumId): int
    {
        $posts = get_posts([
            'post_type' => 'attachment',
            'post_status' => ['inherit', 'private', 'publish', 'draft'],
            'posts_per_page' => 1,
            'fields' => 'ids',
            'meta_query' => [
                ['key' => MetadataKeys::ALBUM_COVER_SOURCE, 'value' => $cover->source, 'compare' => '='],
                ['key' => MetadataKeys::ALBUM_COVER_EXTERNAL_ID, 'value' => $cover->externalId, 'compare' => '='],
            ],
            'suppress_filters' => false,
        ]);

        if (is_array($posts) && isset($posts[0])) {
            return (int) $posts[0];
        }

        $thumbnailId = (int) get_post_thumbnail_id($albumId);
        if ($thumbnailId > 0 && get_post_meta($thumbnailId, MetadataKeys::ALBUM_COVER_EXTERNAL_ID, true) !== '') {
            return $thumbnailId;
        }

        return 0;
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
    private function partialResult(int $albumId, string $albumAction, array $trackResults, int $created, int $updated, int $unchanged, array $warnings, array $errors, ReleaseManifest $manifest, ?CoverImportResult $coverResult = null): ImportResult
    {
        return new ImportResult($albumId, $albumAction, $trackResults, $created, $updated, $unchanged, $warnings, $errors, $manifest->getIdentityKey(), false, 'partial', $coverResult);
    }

    private function coverAction(?CoverManifest $cover, int $albumId): CoverImportResult
    {
        if (! $cover instanceof CoverManifest) {
            return new CoverImportResult('skipped', 0, '', '', 'No cover provided.');
        }

        if ($albumId <= 0) {
            return new CoverImportResult('created', 0, $cover->externalId, $cover->url);
        }

        $attachmentId = $this->findCoverAttachment($cover, $albumId);
        if ($attachmentId <= 0) {
            return new CoverImportResult('created', 0, $cover->externalId, $cover->url);
        }

        $storedUrl = (string) get_post_meta($attachmentId, MetadataKeys::ALBUM_COVER_SOURCE_URL, true);
        $storedHash = (string) get_post_meta($attachmentId, MetadataKeys::ALBUM_COVER_PAYLOAD_HASH, true);
        $thumbnailId = (int) get_post_thumbnail_id($albumId);
        $hashMatches = $cover->payloadHash === '' || $storedHash === $cover->payloadHash;
        if ($storedUrl === $cover->url && $hashMatches && $thumbnailId === $attachmentId) {
            return new CoverImportResult('unchanged', $attachmentId, $cover->externalId, $cover->url);
        }

        return new CoverImportResult('updated', $attachmentId, $cover->externalId, $cover->url);
    }

    /** @return array{CoverImportResult,list<string>} */
    private function syncCover(?CoverManifest $cover, int $albumId): array
    {
        $action = $this->coverAction($cover, $albumId);
        if (! $cover instanceof CoverManifest) {
            return [$action, []];
        }

        if ($action->action === 'unchanged') {
            return [$action, []];
        }

        $sideload = $this->coverSideloader->sideload($cover, $albumId, $action->attachmentId);
        if (! $sideload->success || $sideload->attachmentId <= 0) {
            $message = 'Cover sideload skipped: ' . ($sideload->message !== '' ? $sideload->message : 'unknown failure.');
            return [new CoverImportResult('skipped', 0, $cover->externalId, $cover->url, $message), [$message]];
        }

        $payloadHash = $cover->payloadHash !== '' ? $cover->payloadHash : $sideload->payloadHash;
        $meta = $this->coverMeta($cover, $payloadHash);
        $attachmentErrors = $this->writeMeta($sideload->attachmentId, $meta);
        $albumErrors = $this->writeMeta($albumId, $meta);
        $this->setAlbumFeaturedImage($albumId, $sideload->attachmentId);

        $warnings = array_map(static fn (string $error): string => 'Cover metadata warning: ' . $error, array_merge($attachmentErrors, $albumErrors));
        return [new CoverImportResult($action->action, $sideload->attachmentId, $cover->externalId, $cover->url), $warnings];
    }

    /** @return array<string, mixed> */
    private function coverMeta(CoverManifest $cover, string $payloadHash): array
    {
        $meta = [
            MetadataKeys::ALBUM_COVER_SOURCE => $cover->source,
            MetadataKeys::ALBUM_COVER_EXTERNAL_ID => $cover->externalId,
            MetadataKeys::ALBUM_COVER_SOURCE_URL => $cover->url,
            MetadataKeys::ALBUM_COVER_FILENAME => $cover->filename,
            MetadataKeys::ALBUM_COVER_MIME_TYPE => $cover->mimeType,
            MetadataKeys::ALBUM_COVER_STRATEGY => $cover->strategy,
        ];

        if ($payloadHash !== '') {
            $meta[MetadataKeys::ALBUM_COVER_PAYLOAD_HASH] = $payloadHash;
        }

        return $meta;
    }

    private function setAlbumFeaturedImage(int $albumId, int $attachmentId): void
    {
        if (function_exists('set_post_thumbnail')) {
            set_post_thumbnail($albumId, $attachmentId);
            return;
        }

        update_post_meta($albumId, '_thumbnail_id', $attachmentId);
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

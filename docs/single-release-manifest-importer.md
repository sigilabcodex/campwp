# Single Release Manifest Importer

CAMPWP includes a service-layer importer for one local normalized release manifest. It is intentionally limited to local JSON input, deterministic WordPress post/meta writes, and optional cover sideloading from an explicit manifest URL. It does not fetch Internet Archive metadata, discover remote files, or run bulk imports.

## Invocation

Use `CampWP\Application\Import\ReleaseImporter` from application code or tests:

```php
use CampWP\Application\Import\ReleaseImporter;

$importer = new ReleaseImporter();
$result = $importer->importLocalFile('/path/to/release.json', dryRun: true);

if (! $result->isSuccess()) {
    // Inspect $result->errors.
}
```

The importer also accepts a JSON string with `importJsonString()` or a decoded associative array with `importArray()`.

File-path imports require a readable local `.json` file. URL wrappers, stream wrappers, directories, recursive searches, and source-file mutation are rejected. Readable local JSON symlinks are currently accepted because PHP `is_file()` follows symlinks.

## Accepted Schema

Supported schema versions:

- `campwp-release-manifest-v1`
- `campwp-mdk-import-example-v0` for the audited MDK example fixture

The normalized v1 shape is:

```json
{
  "schema_version": "campwp-release-manifest-v1",
  "album": {
    "catalog_key": "test_catalog",
    "external_release_id": "TEST001",
    "source_provider": "direct",
    "title": "Test Remote Album",
    "description": "Release notes or body content.",
    "post_status": "draft",
    "artist": "Artist Name",
    "release_date": "2026-01-02",
    "credits": "Produced by Example",
    "bandcamp_url": "https://artist.bandcamp.com/album/example",
    "project_url": "https://example.com/project",
    "license": {
      "name": "Creative Commons Attribution 3.0 Unported",
      "code": "cc-by-3-0",
      "url": "https://creativecommons.org/licenses/by/3.0/"
    },
    "cover": {
      "source": "direct",
      "external_id": "TEST001:cover",
      "url": "https://cdn.example.com/cover.jpg",
      "filename": "cover.jpg",
      "mime_type": "image/jpeg",
      "strategy": "sideload_featured_image",
      "payload_hash": "sha256:..."
    },
    "provenance": {
      "payload_hash": "sha256:..."
    },
    "sync_state": {
      "sync_status": "synced",
      "last_synced_at": "2026-01-02T03:04:05Z"
    }
  },
  "tracks": [
    {
      "external_track_id": "TEST001-01",
      "index": 1,
      "track_number": 1,
      "title": "One",
      "source_type": "internet_archive",
      "source_provider": "direct",
      "playback_url": "https://cdn.example.com/one.mp3",
      "original_url": "https://cdn.example.com/one.flac",
      "download_url": "https://cdn.example.com/one.flac"
    }
  ]
}
```

The MDK audit example keeps its top-level fields (`release_id`, `archive_identifier`, `internet_archive_url`, `archive_metadata`, `sync_state`, and `tracks`) and is normalized into the same internal model.

## Identity Rules

Album identity is provider-neutral and does not use title or slug matching.

- Catalog identity: `catalog_key` when supplied, otherwise the source provider for legacy/audit manifests.
- Album upsert key: `_campwp_catalog_identity` plus `_campwp_source_provider` plus `_campwp_external_release_id`.
- Source release identity reported in results: `catalog_identity:external_release_id`.
- Existing imported albums that lack `_campwp_catalog_identity` are not matched automatically; the importer reports a warning and creates a new safely identified album instead of guessing.

Track identity is scoped to the album.

- Canonical normalized manifests must provide `external_track_id` for every track.
- Track upsert key: `_campwp_external_track_id` plus `_campwp_album_id`.
- The audited legacy MDK fixture may omit `external_track_id`; in that compatibility path only, CAMPWP derives a stable hashed ID from `original_filename`, or from the basename of a validated original/download/playback media URL when a filename is absent.
- Track index, track number, array position, and title are never used as fallback identity. If no explicit ID and no stable file identity exists, the track is rejected.

## Validation

Validation completes before any write. Invalid manifests return structured errors and do not partially import.

The importer validates:

- Supported schema version.
- Album object and tracks array shape.
- Required release identity, title, provider for remote media, track title, and positive track index.
- Unique track external IDs and unique track indexes.
- Known source types and providers.
- Strict remote URLs using `MetadataSanitizer` without network access.
- Optional cover sideload manifests when `cover.strategy` is `sideload_featured_image`: source, external ID, URL, filename, MIME type, supported image type, and optional checksum.
- Internet Archive identifiers, checksums, ISO-8601 timestamps, sync statuses, derivative statuses, formats, and non-negative sizes.
- Scalar values are not coerced from arrays or objects.
- Reasonable string and track-count limits.

## Authoritative And Preserved Fields

Provided manifest fields are authoritative for the matching post and are written on create or update.

Album fields written when present:

- Post title, post content, and post status.
- `_campwp_source_provider`
- `_campwp_catalog_identity`
- `_campwp_external_release_id`
- `_campwp_external_item_url`
- `_campwp_internet_archive_identifier`
- `_campwp_internet_archive_metadata_url`
- `_campwp_bandcamp_url`
- `_campwp_project_url`
- `_campwp_license_name`
- `_campwp_license_code`
- `_campwp_license_url`
- `_campwp_remote_cover_url`
- Cover sideload provenance when a cover is successfully imported: `_campwp_cover_source`, `_campwp_cover_external_id`, `_campwp_cover_source_url`, `_campwp_cover_filename`, `_campwp_cover_mime_type`, `_campwp_cover_strategy`, and `_campwp_cover_payload_hash`.
- `_campwp_source_payload_hash`
- `_campwp_last_synced_at`
- `_campwp_sync_status`
- Existing album artist, release date, catalog number, and credits metadata when supplied.

Track fields written when present:

- Post title, post parent, menu order.
- `_campwp_album_id`
- `_campwp_track_order`
- `_campwp_external_track_id`
- `_campwp_external_track_index`
- `_campwp_track_number`
- `_campwp_track_audio_source_type`
- `_campwp_track_source_provider`
- `_campwp_audio_original_url`
- `_campwp_audio_playback_url`
- `_campwp_audio_download_url`
- Remote format, size, checksum, and derivative status fields when supplied.
- Duration and credits when supplied.

Absent optional fields are preserved. The importer does not delete manually entered metadata merely because a field is absent from a later manifest. Featured images are never cleared. A featured image is assigned or updated only when the manifest explicitly requests `cover.strategy: "sideload_featured_image"`.

## Dry Run

Dry-run mode validates, normalizes, locates existing records, and reports planned create/update/unchanged actions for the album, tracks, and cover. It performs no post inserts, post updates, meta writes, HTTP requests, media downloads, attachment changes, featured-image changes, or cleanup.


## Cover Sideloading

A cover is optional. Missing `cover`, `cover: null`, or a cover object without `strategy` remains a no-op; a simple `cover.url` is still stored as `_campwp_remote_cover_url` for remote rendering fallback.

When `cover.strategy` is `sideload_featured_image`, the manifest must already contain the resolved remote cover URL. WordPress does not query Internet Archive or any provider to discover artwork. Required fields are `source`, `external_id`, `url`, `filename`, `mime_type`, and `strategy`. Supported MIME types are `image/jpeg`, `image/png`, `image/gif`, and `image/webp`. `payload_hash` is optional and may be `sha256:`, `sha1:`, or `md5:`.

Apply mode downloads the image through WordPress HTTP APIs, validates HTTP success, MIME, and image type, creates or updates a Media Library attachment, generates attachment metadata when WordPress supports it, assigns the attachment as the album featured image, and stores provenance on both the attachment and album. Re-imports reuse the existing managed attachment by cover external identity; if the same identity has a changed URL or hash, the importer updates that attachment instead of creating a duplicate where practical.

Cover download, MIME, and media API failures are non-fatal. The album and tracks continue importing, the result includes a warning, and the cover result is reported as `skipped`. Malformed cover objects are validation errors and prevent writes.

## Creation And Update Behavior

First import creates one `campwp_album` and one `campwp_track` per manifest track. New posts default to `draft` when status is absent. Supported explicit statuses are `draft`, `pending`, and `private`; `publish` and arbitrary statuses are validation errors. The importer does not publish automatically.

A repeated identical import updates no posts and reports unchanged records. Title changes update the same album or track IDs. Track reordering updates `_campwp_track_order` and `menu_order` on existing track IDs.

Missing tracks in a later manifest are not deleted, trashed, or detached in this PR. Existing unrelated albums and tracks are not touched.

## Result Shape

`ImportResult` contains:

- `status`: `success`, `unchanged`, `partial`, `failed`, or `dry_run`
- `albumPostId`
- `albumAction`: `created`, `updated`, `unchanged`, or `failed`
- Track results with post ID, external track ID, index, and action
- Cover result with action (`created`, `updated`, `unchanged`, or `skipped`), attachment ID, external ID, URL, and optional message
- Created, updated, and unchanged counts
- Warnings and errors
- Source release identity
- Dry-run flag

If a post or metadata write fails after earlier writes succeeded, the result uses `status: partial`, preserves the album ID, preserves successful prior track results, preserves completed create/update/unchanged counts, appends the failing track result when applicable, and includes structured errors. It never reports zero writes after writes have occurred.

Metadata persistence is verified by comparing the existing value before writing and reading the value back after `update_post_meta()`. An unchanged value is not treated as a failure even when WordPress would return `false`; a changed value that does not persist is reported as an import error.

## Current Limitations

- No Internet Archive HTTP client or remote artwork discovery.
- No remote metadata refresh or verification.
- No bulk import runner.
- No transaction wrapper; safety comes from validate-before-write, deterministic ordering, structured failures, and no destructive cleanup.
- No media sideloading beyond the explicit single-release cover strategy.

# Remote Media Vertical Slice

This document describes the public rendering flow for releases whose cover and track audio are stored remotely. It covers runtime rendering only. CAMPWP still does not include an Internet Archive HTTP client, JSON importer, WP-CLI importer, bulk import tooling, remote verification, or media sideloading.

## Cover Resolution

Album cover resolution is handled by `CampWP\Domain\Media\AlbumCoverResolver`.

Resolution priority:

1. WordPress featured image attachment from `get_post_thumbnail_id()`.
2. Strictly validated `_campwp_remote_cover_url`.
3. Existing no-cover fallback, which renders no cover image.

A remote cover never overrides a featured image. Remote cover URLs are validated with `MetadataSanitizer::sanitizeRemoteUrl()` using `_campwp_source_provider`. If the provider is empty and the album has Internet Archive identity metadata, the effective provider is `internet_archive`. The resolver does not fetch, probe, resize, cache, or sideload the image.

The public album view renders remote covers with an escaped HTTPS `src` and alt text derived from the album title.

## Playback Resolution

Track playback still flows through `TrackAudioResolver::getTrackPlaybackFile()`.

For `internet_archive` source type tracks, playback priority is:

1. `_campwp_audio_playback_url`, typically a remote MP3.
2. Legacy `_campwp_track_audio_external_url` fallback.
3. `_campwp_audio_original_url` only when it is browser-playable by conservative extension/MIME inference.
4. Existing attachment fallback.

The album player and track rows use the resolved `TrackAudioFile` URL directly. Rendering performs no network checks and does not expose local filenames.

## Download Resolution

Track download CTAs still respect `EntitlementService` download settings.

For `internet_archive` and `external_url` tracks, the public album view links directly to the resolved remote download URL when one is available. For an IA-style track this is expected to be `_campwp_audio_download_url`, typically a remote FLAC. CAMPWP does not proxy or stream remote downloads through WordPress.

Attachment-backed downloads continue using the existing routed `/campwp-download/track/{id}/` URL.

## Manual Setup Recipe

Create an album and leave the featured image empty when testing remote cover fallback.

Required album metadata:

- `_campwp_source_provider`: `internet_archive` or empty when IA identity metadata is present.
- `_campwp_internet_archive_identifier`: example `example-item` when using IA fallback provider behavior.
- `_campwp_remote_cover_url`: example `https://archive.org/download/example-item/cover.jpg`.

Required track metadata for each remote track:

- `_campwp_album_id`: album post ID.
- `_campwp_track_order`: ordered track number.
- `_campwp_track_audio_source_type`: `internet_archive`.
- `_campwp_track_source_provider`: `internet_archive`.
- `_campwp_audio_playback_url`: remote MP3 URL.
- `_campwp_audio_download_url`: remote FLAC URL.
- `_campwp_audio_original_url`: remote FLAC URL, when the original and download are the same file.
- `_campwp_track_download_enabled`: `1`, unless the album-level download settings should apply.

Automated tests use fake/example URLs only. Do not hard-code production MDK or Archive.org item URLs into runtime code.

## Fallback Behavior

- Featured image attachment wins over remote cover URL.
- Invalid or unsafe remote cover URLs render no cover image.
- Invalid or unsafe remote playback URLs do not produce a player source.
- Invalid or unsafe remote download URLs do not produce a download action.
- Existing attachment-backed tracks and legacy `external_url` tracks continue to render through their existing paths.
- Empty remote metadata should not produce PHP warnings or broken image/audio elements.

## Current Limitations

- No remote metadata fetching exists.
- No Internet Archive API client exists.
- No importer, sync job, WP-CLI command, or bulk tooling exists.
- No media is downloaded, sideloaded, resized, transcoded, cached, or verified by this slice.

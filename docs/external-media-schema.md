# External Media Schema

CAMPWP supports attachment-based audio and legacy generic external audio URLs. This schema extends that model with provider-neutral external media and provenance fields for future importers and integrations.

This document describes schema and resolver behavior. CAMPWP does not include an Internet Archive HTTP client, bulk import tooling, or remote catalog verification. The single-release importer may sideload a cover only from an explicit manifest URL.

## Source Types

Track audio source type is stored in `_campwp_track_audio_source_type`.

Allowed values:

- `attachment`: existing Media Library attachment behavior.
- `external_url`: existing generic external URL behavior using `_campwp_track_audio_external_url`.
- `internet_archive`: provider-backed remote media behavior with separate playback/download/original URLs.

Existing `attachment` and `external_url` tracks do not require migration.

## Providers

Provider fields identify where external metadata or media came from. They do not fetch anything by themselves.

Allowed stored provider values:

- empty string: unset provider
- `direct`
- `internet_archive`
- `bandcamp`
- `backblaze_b2`
- `s3`
- `other`

Album provider is stored in `_campwp_source_provider`. Track provider is stored in `_campwp_track_source_provider`. Empty input and invalid provider input sanitize to an empty string. Explicit `direct` remains distinct from unset.

For tracks with source type `internet_archive`, the audio resolver uses `internet_archive` as the effective provider only when `_campwp_track_source_provider` is empty. If a valid provider is explicitly stored, the resolver validates remote URLs against that provider and does not silently reinterpret it as Internet Archive.

## Album Fields

- `_campwp_source_provider`
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
- `_campwp_cover_source`
- `_campwp_cover_external_id`
- `_campwp_cover_source_url`
- `_campwp_cover_filename`
- `_campwp_cover_mime_type`
- `_campwp_cover_strategy`
- `_campwp_cover_payload_hash`
- `_campwp_source_payload_hash`
- `_campwp_last_synced_at`
- `_campwp_sync_status`
- `_campwp_sync_message`

Remote cover URL is used as a public rendering fallback when no featured image attachment exists. Frontend featured-image attachment rendering remains the first priority. Cover sideload provenance fields identify Media Library attachments managed by the single-release importer and are also mirrored on the album after a successful cover sync.

## Track Fields

- `_campwp_external_track_id`
- `_campwp_external_track_index`
- `_campwp_track_source_provider`
- `_campwp_audio_original_url`
- `_campwp_audio_playback_url`
- `_campwp_audio_download_url`
- `_campwp_audio_original_format`
- `_campwp_audio_playback_format`
- `_campwp_audio_original_size`
- `_campwp_audio_playback_size`
- `_campwp_audio_original_checksum`
- `_campwp_audio_playback_checksum`
- `_campwp_remote_derivative_status`
- `_campwp_track_source_payload_hash`
- `_campwp_track_last_synced_at`
- `_campwp_track_sync_status`
- `_campwp_track_sync_message`

Track provenance keys are prefixed with `_campwp_track_` where needed to avoid accidental key collisions with album provenance fields.

## Playback And Download Resolution

Attachment source behavior is unchanged:

- Playback: streaming attachment, MP3 attachment, OGG attachment, then source attachment.
- Download: source attachment, MP3 attachment, OGG attachment, streaming attachment, then audio attachment.

Generic external URL behavior is unchanged:

- Playback: `_campwp_track_audio_external_url`.
- Download: `_campwp_track_audio_external_url`.
- Legacy reads remain permissive and do not retroactively reject stored HTTP URLs.

`internet_archive` source behavior:

- Strict remote URL validation uses `_campwp_track_source_provider` when set.
- Empty `_campwp_track_source_provider` is treated as effective provider `internet_archive` for backward compatibility with IA-mode tracks.
- Explicit valid providers such as `direct`, `bandcamp`, `backblaze_b2`, or `s3` are honored.


Playback priority:

1. `_campwp_audio_playback_url`
2. `_campwp_track_audio_external_url` legacy fallback
3. `_campwp_audio_original_url` only when browser-playable by extension/MIME inference
4. Existing attachment fallback

Download priority:

1. `_campwp_audio_download_url`
2. `_campwp_audio_original_url`
3. `_campwp_track_audio_external_url` legacy fallback
4. Existing attachment fallback

No network checks are performed. Unsafe strict remote URLs resolve to empty values and are not returned.

## URL Security Rules

Provider-backed URL fields use strict offline validation:

- HTTPS only.
- Reject `file://`, `ftp://`, `data:`, and `javascript:` schemes.
- Reject local filesystem paths.
- Reject URLs with embedded credentials.
- Reject `localhost`, loopback IPs, RFC1918 private IPs, and link-local IPs.
- Reject suspicious numeric or alternate-address host forms such as decimal-only hosts, hexadecimal hosts, and dotted octal/hex-style components.
- Reject URLs with host suffix confusion such as `archive.org.example.com` for Internet Archive fields.
- Do not perform DNS resolution or HTTP requests.

Known allowed hosts include:

- `archive.org`
- `www.archive.org`
- `ia800*.us.archive.org` for direct IA file hosts when needed
- `bandcamp.com`
- `www.bandcamp.com`
- any proper subdomain ending in `.bandcamp.com`, such as `artist.bandcamp.com`
- `mdkband.com`
- `www.mdkband.com`

The validator is centralized in `MetadataSanitizer` so provider host policies can be extended later. Bandcamp matching is exact or suffix-based only; hosts such as `bandcamp.com.example.org`, `fakebandcamp.com`, and `bandcamp.example.com` are rejected.

## Source-Switch Cleanup

Admin saves and release-builder inline saves use the same deterministic cleanup policy:

- Switching to `attachment` clears `_campwp_track_audio_external_url`, provider-backed remote audio URLs, remote format/size/checksum/derivative fields, and `_campwp_track_source_provider`. Stable external track identity and provenance/sync fields are preserved for future resync workflows.
- Switching to `external_url` clears attachment-driven audio IDs, provider-backed remote audio URLs, remote technical metadata, and `_campwp_track_source_provider`. Stable external track identity and provenance/sync fields are preserved.
- Switching to `internet_archive` clears attachment-driven audio IDs and preserves remote URL and technical metadata. `_campwp_track_source_provider` is set to `internet_archive` only when currently empty; an explicitly stored valid provider is not overwritten unless a future UI explicitly changes it.

## Identifier And Hash Rules

Internet Archive identifiers:

- Trim whitespace.
- Reject URLs and path separators.
- Permit conservative IA-compatible characters: letters, numbers, `.`, `_`, and `-`.
- Limit length to 100 characters.

Checksums and source payload hashes support:

- `sha256:<64 hex characters>`
- `sha1:<40 hex characters>`
- `md5:<32 hex characters>`

CAMPWP does not calculate checksums in this PR.

## Sync And Derivative States

Sync status values:

- `never_synced`
- `pending`
- `synced`
- `stale`
- `remote_missing`
- `failed`
- `conflict`

Derivative status values:

- `unknown`
- `pending`
- `available`
- `missing`
- `failed`

These fields are for future import/sync workflows and do not currently trigger remote checks.

## Compatibility

- Existing metadata keys remain valid.
- Empty new fields have harmless defaults.
- Existing posts require no migration.
- Uninstall behavior is unchanged.
- No remote media is downloaded or sideloaded by this schema.

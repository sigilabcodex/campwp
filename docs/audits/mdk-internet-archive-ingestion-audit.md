# MDK Internet Archive Ingestion Audit

Date: 2026-07-01

Branch: `audit/mdk-internet-archive-ingestion`

Scope: architecture and capability audit only. No importer was implemented, no runtime plugin code was changed, no WordPress site was modified, no media was uploaded, and no Internet Archive item was created or modified.

The separate MDK catalog repository was not available in this workspace. This audit uses the schema examples supplied in the task and the current CAMPWP repository. Direct inspection of `/home/diegom/mdk-catalog-tools` is still needed before finalizing importer validation rules, file-selection heuristics, and exact edge-case handling for all 148 releases.

## Executive Conclusion

### Works Now

- CAMPWP registers `campwp_album` and `campwp_track` post types.
- CAMPWP can create/update album and track records manually through WordPress admin.
- CAMPWP stores ordered album-track relationships using `_campwp_album_id` and `_campwp_track_order`.
- CAMPWP supports track audio source type `external_url` and can render that URL into the frontend browser player.
- CAMPWP can store common release and track fields: title, release date, catalog number, release notes, artist display, credits, label, duration, track number, and track artwork attachment ID.
- CAMPWP can avoid duplicating audio storage if a track is manually configured as `external_url`.

### Partially Works

- IA MP3 playback works only if a direct IA MP3 URL is manually stored in `_campwp_track_audio_external_url`.
- IA-hosted track downloads work only as the same manually stored external URL. There is no separate remote playback URL and remote original download URL.
- Ordered track rendering exists, but idempotent import keyed by `release_id`, `archive_identifier`, or remote track keys does not.
- Cover artwork can be represented as a WordPress featured image or attachment-backed track artwork, but remote cover URLs are not first-class fields.
- Download entitlement UI exists, but external URL downloads bypass the local `/campwp-download/...` controller in the view-data layer.

### Missing

- JSON importer.
- WP-CLI import/sync/verify commands.
- Internet Archive provider/client.
- Archive.org metadata API parsing.
- Remote media verification.
- External release and track identifiers.
- Provenance and synchronization state.
- Source payload hash/change detection.
- Bulk import tooling for 148 releases.
- Provider-neutral media source records.
- Separate remote MP3 playback and remote FLAC download fields.
- Remote cover field and optional cover-only sideload workflow.
- Bandcamp/project/license/tags first-class fields.
- Tests.

### Unsafe Or Ambiguous

- External audio URLs are sanitized as HTTP(S), but not allowlisted by host, scheme policy beyond HTTP(S), max length, extension, or media type.
- There is no SSRF-sensitive IA metadata fetching yet; when added it must be allowlisted and constrained.
- External downloads currently use direct remote URLs in CTAs, not the entitlement-checked download controller.
- Descriptions/content rendering relies on normal post content and outputs `$content` unescaped after WordPress filters, which is typical but makes importer capability and KSES policy important.
- Remote source type semantics are ambiguous: `external_url` means both playback and download.

## Current Architecture

### Bootstrap And Registration

- `campwp.php` defines plugin constants, loads Composer autoload, registers activation/deactivation hooks, and starts `CampWP\Core\Application`.
- `src/Core/Application.php` registers services: `AdminService`, `DomainService`, `InfrastructureService`, `IntegrationService`, `FrontendService`, and `ImplementationReport`.
- `src/Domain/DomainService.php` registers content types and metadata schema.
- `src/Frontend/FrontendService.php` registers download routing, content filtering, and frontend assets.

### Content Model

- `src/Domain/ContentModel/PostTypeRegistrar.php` registers:
  - `campwp_album`, supports `title` and `thumbnail`, public, archive enabled, REST disabled.
  - `campwp_track`, supports `title` and `thumbnail`, public, no archive, REST disabled.
- `src/Domain/Metadata/MetadataKeys.php` defines current album, track, audio, and download meta keys.
- `src/Domain/Metadata/MetadataSchemaRegistrar.php` registers post meta with sanitizers and `edit_posts` auth callbacks.
- `src/Domain/ContentModel/AlbumTrackRelationshipService.php` stores album-track assignment on tracks with `_campwp_album_id` and order with `_campwp_track_order`.
- `src/Domain/ContentModel/ReleaseBuilderService.php` creates tracks from Media Library audio attachments and saves inline track fields.

### Audio And Downloads

- `src/Domain/Audio/TrackAudioResolver.php` supports `attachment` and `external_url`.
- Attachment playback preference is streaming attachment, then MP3, then OGG, then source attachment.
- Attachment download preference is source attachment, then MP3, OGG, streaming, then audio attachment.
- External URL playback and download both resolve to `_campwp_track_audio_external_url`.
- `src/Domain/Commerce/DownloadResolver.php` resolves only WordPress `MediaAsset` attachment downloads.
- `src/Frontend/Data/AlbumViewDataProvider.php` and `TrackViewDataProvider.php` special-case external URL tracks and use the external URL directly for CTA downloads.
- `src/Frontend/Download/DownloadController.php` redirects entitlement-approved attachment downloads to attachment URLs.

### Rendering

- `src/Frontend/Rendering/AlbumPageRenderer.php` renders album cover, player, ordered tracklist, notes, credits, bonus assets, and CTAs.
- `src/Frontend/Rendering/AlbumPlayerRenderer.php` renders an HTML `<audio>` element and uses source URL/type from `TrackAudioFile`.
- `assets/js/campwp-album-player.js` drives playlist selection and playback.

### Integrations, REST, WP-CLI, Import/Export

- `src/Integrations/IntegrationService.php` is a placeholder.
- WooCommerce integration exists only for entitlement/product mapping.
- There are no custom REST endpoints.
- Post types set `show_in_rest` to `false`, even though meta registration uses `show_in_rest`.
- No `WP_CLI` usage or command class exists.
- No JSON import/export code exists.

### Tests And Docs

- No test directory or PHPUnit/PHPStan/PHPCS/Psalm config was found.
- Existing documentation includes `README.md` and `docs/track-metadata-v2-proposal.md`.
- `uninstall.php` is intentionally minimal and does not remove data.

## Capability Matrix

| # | Capability | Current support | Evidence | Limitation | Recommended change |
|---|---|---|---|---|---|
| 1 | Import one release from structured JSON | No | No importer classes, no CLI, no REST import endpoint | Manual admin only | Add importer service accepting normalized publication JSON |
| 2 | Create/update `campwp_album` | Partial | Post type exists in `PostTypeRegistrar` | No idempotent external key lookup | Add external release identity meta and importer upsert |
| 3 | Create/update ordered `campwp_track` posts | Partial | `_campwp_album_id`, `_campwp_track_order` and relationship service | Creation currently attachment/admin-driven | Add track upsert by stable external track key |
| 4 | Idempotent import by `release_id` or `archive_identifier` | No | No external ID meta keys | Duplicate posts likely | Add unique release key lookup and conflict policy |
| 5 | Store IA identifiers and item URLs | No | `MetadataKeys` has no archive/provider fields | Could only store in content/free text | Add provider-neutral external source meta |
| 6 | Store original FLAC URLs without sideloading | No first-class support | Only one external audio URL exists | Same URL used for playback and download | Add remote original/download URL fields |
| 7 | Store derived MP3 URLs without sideloading | Partial | `_campwp_track_audio_external_url` | No IA provider/type/status | Add playback URL plus provider metadata |
| 8 | Use external MP3 as browser-playable source | Yes manually | `TrackAudioResolver::getTrackPlaybackFile()` resolves `external_url` | No validation beyond URL sanitization | Add allowlisted remote source validation |
| 9 | Offer original FLAC as download | Partial/No | External CTA can link URL directly | Cannot differ from playback URL; entitlement bypass | Add remote download resolver and FLAC URL field |
| 10 | Reference external cover artwork | No first-class support | Cover uses post thumbnail attachment | Remote cover URL not rendered | Add `_campwp_remote_cover_url` fallback |
| 11 | Optionally sideload only cover | No workflow | No `download_url` or sideload use found | Manual featured image only | Add optional cover sideload path with limits |
| 12 | Preserve Bandcamp/project URLs | No first-class support | No Bandcamp/project meta | Could only place in content | Add external link meta/repeater |
| 13 | Preserve CC BY 3.0 metadata | No first-class support | No license meta | Could only place in notes/content | Add license name/code/URL meta |
| 14 | Map release and track credits | Partial | Album credits override and track credits exist | No structured contributor roles | Store text now; plan structured credits later |
| 15 | Map release date, tags, descriptions, notes, track order | Partial | Release date/notes/order exist; post content can hold description | No tags taxonomy registered for these CPTs | Add taxonomy strategy and importer mapping |
| 16 | Render album page with playlist and downloads | Partial | Album renderer/player/download CTAs exist | Remote FLAC/IA links missing | Extend view data with remote source/download/link rows |
| 17 | Refresh remote IA metadata later | No | No remote client or sync state | No change detection | Add IA sync service and scheduled/manual sync |
| 18 | Detect remote changes without duplicate posts | No | No source hash/external keys | Reimports would duplicate/overwrite blindly | Add payload hash and idempotent upsert |
| 19 | Handle missing/incomplete IA derivatives | No | No IA parser | Manual missing URL means no audio | Add derivative status and fallback policy |
| 20 | Bulk-import 148 releases safely | No | No CLI/importer/logging | Manual admin not safe enough | Add dry-run, batching, resume log, rollback plan |
| 21 | Run imports through WP-CLI | No | No `WP_CLI` usage | None | Add `wp campwp ...` commands |
| 22 | Record provenance/sync state | No | No provenance meta | Audit trail absent | Add source payload hash, synced time, status |
| 23 | Avoid 58+ GiB duplicated media storage | Partial | External URL audio avoids audio sideload | No importer enforcing remote strategy | Make remote audio default for MDK |
| 24 | Avoid local `/home/diegom/Music` paths | Partial | Current runtime uses attachment file paths only for attachment metadata/classification | Import payload must reject local paths | Add schema validation forbidding local paths |

## Current Data Model

### Album Fields

- Post: `post_title`; featured image as cover.
- Meta:
  - `_campwp_album_subtitle`
  - `_campwp_album_release_date`
  - `_campwp_album_catalog_number`
  - `_campwp_album_artist_display`
  - `_campwp_album_credits_override`
  - `_campwp_album_label_name`
  - `_campwp_album_release_notes`
  - `_campwp_album_release_type`
  - `_campwp_album_bonus_items`
  - download fields: `_campwp_album_download_enabled`, `_campwp_album_download_mode`, `_campwp_album_product_id`

### Track Fields

- Post: `post_title`; featured image as fallback track artwork.
- Meta:
  - relationship/order: `_campwp_album_id`, `_campwp_track_order`
  - descriptive fields: `_campwp_track_number`, `_campwp_track_subtitle`, `_campwp_track_duration`, `_campwp_track_artist_display`, `_campwp_track_credits`, `_campwp_track_lyrics`, `_campwp_track_isrc`
  - artwork: `_campwp_track_artwork_id`
  - audio: `_campwp_track_audio_source_type`, `_campwp_track_audio_external_url`, `_campwp_track_audio_attachment_id`, `_campwp_track_audio_source_attachment_id`, `_campwp_track_audio_mp3_attachment_id`, `_campwp_track_audio_ogg_attachment_id`, `_campwp_track_audio_streaming_attachment_id`, `_campwp_track_audio_source_classification`
  - downloads: `_campwp_track_download_enabled`, `_campwp_track_download_mode`, `_campwp_track_product_id`

### Attachment Assumptions

- Cover rendering uses WordPress featured image attachment HTML.
- Track artwork uses attachment ID or post thumbnail fallback.
- Album bonus assets are JSON objects of type `wp_attachment` with `reference_id`.
- The media provider resolves only WordPress attachment IDs to `MediaAsset`.
- Attachment audio metadata extraction uses `get_attached_file()`.

### Inheritance Rules

- Empty track artist and credits inherit album defaults.
- Empty album artist/credits/label can inherit site defaults.
- Track order is stored as numeric meta and sorted by order, then ID.

### Source And Download Handling

- Source types are limited to `attachment` and `external_url`.
- Attachment mode supports separate source/playback attachment IDs.
- External URL mode has one URL for both playback and download.
- Attachment downloads go through entitlement checks and a redirect controller.
- External URL downloads are direct CTA links when the view data detects `external_url`.

## Internet Archive Compatibility

### Metadata API

CAMPWP has no IA API client. A future provider should fetch `https://archive.org/metadata/{identifier}` using constrained `wp_remote_get()` calls with:

- Host allowlist: `archive.org`, `archive.org/download`, and expected IA metadata hosts only.
- Short timeout and bounded retries.
- Response size limits.
- JSON parsing with schema validation.
- No use of arbitrary URLs from untrusted JSON without validation.

### Original FLAC URL

IA original file URLs can generally be represented as:

`https://archive.org/download/{identifier}/{encoded-filename.flac}`

CAMPWP currently has no remote original URL field. Do not store this in `_campwp_track_audio_external_url` if the desired browser playback source is MP3.

### Derived MP3 URL

IA derived MP3 URLs can generally be represented as:

`https://archive.org/download/{identifier}/{encoded-filename.mp3}`

CAMPWP can manually use this as `_campwp_track_audio_external_url`, and the album player will render it.

### Cover URL

IA cover files can be represented as direct download URLs or IA item file URLs. CAMPWP currently needs either a sideloaded WordPress attachment or a future remote cover URL field.

### Item URL And Identifier

No current fields exist for `mdk148-the-trade-wind-desert-soundscape` or `https://archive.org/details/mdk148-the-trade-wind-desert-soundscape`.

### Checksum And Size Metadata

No current fields exist for IA file size, md5/sha1, original/derivative format, or verification timestamp.

### Derivative Availability State

No current fields exist for derivative availability, missing MP3 fallback, failed verification, or retry scheduling.

## Storage Strategies

### A. WordPress-Hosted Media

- Storage: highest; duplicates FLAC/MP3/cover into `uploads`.
- Bandwidth: WordPress/CDN bears audio bandwidth.
- Reliability: under site control after upload.
- CORS/range requests: controlled by host.
- Browser playback: easiest for Media Library files.
- Media Library integration: full.
- Backup implications: very large backups; undesirable for 58+ GiB.
- Migration implications: heavy migration and restore cost.
- Fit for MDK: not recommended for audio.

### B. Fully Remote IA Media

- Storage: minimal.
- Bandwidth: IA bears media bandwidth.
- Reliability: depends on IA availability and derivative generation.
- CORS/range requests: must be verified against direct IA URLs; browser playback generally depends on direct file URLs supporting range requests.
- Browser playback: works if direct MP3 URL is stable and CORS/range behavior is acceptable.
- Media Library integration: minimal unless represented as external source records.
- Backup implications: small.
- Migration implications: portable if remote URLs and identifiers are stable.
- Fit for MDK: best default for audio, metadata, and non-cover assets.

### C. Hybrid: Local Cover + Remote Audio

- Storage: small; only cover thumbnails/variants stored in WordPress.
- Bandwidth: WordPress handles images, IA handles audio.
- Reliability: cover remains available even if IA image URL changes.
- CORS/range requests: audio still must be verified.
- Browser playback: remote MP3.
- Media Library integration: good for covers, limited for audio.
- Backup implications: modest.
- Migration implications: manageable.
- Fit for MDK: recommended publishing strategy.

### D. Hybrid: IA MP3 Playback + IA FLAC Download

- Storage: minimal for audio.
- Bandwidth: IA handles playback/download.
- Reliability: depends on IA original and derivative availability.
- CORS/range requests: MP3 must be verified for player use; FLAC download can be direct.
- Browser playback: best with MP3 derivative.
- Media Library integration: needs external source UI.
- Backup implications: small.
- Migration implications: stable if identifier/filename are stored.
- Fit for MDK: recommended audio strategy.

## Security Analysis

- SSRF risk: currently low because there is no remote fetch path for IA metadata. Future `wp_remote_get()` must be host-allowlisted and must not fetch arbitrary user-supplied URLs.
- Remote URL allowlisting: missing. Current external audio accepts any HTTP(S) URL after `esc_url_raw()`.
- Metadata sanitization: current text/textarea/date/integer fields are sanitized. Future JSON import must validate all fields before `wp_insert_post()` and `update_post_meta()`.
- HTML in descriptions: importer should either store descriptions as sanitized post content via `wp_kses_post()` or plain text. Notes/credits currently render as escaped text.
- Capability checks: admin metaboxes check nonces and capabilities. Future CLI should require execution by trusted operators; future REST import would need `manage_options` or a dedicated capability.
- Nonce checks: current admin save paths use nonces. CLI has no nonce concept.
- Import permissions: missing. Add capability checks for web/REST imports and document WP-CLI trust boundary.
- Download proxy abuse: current controller redirects only resolved attachments. Future remote downloads should avoid becoming an arbitrary open redirect/proxy by validating stored remote URLs and provider.
- Open redirects: `wp_safe_redirect()` protects attachment redirects; direct external CTA links bypass controller. Future remote download URLs must be validated and escaped.
- Remote file size limits: missing. Required for cover sideload and verification HEAD/GET calls.
- Timeout/retry behavior: missing because no remote client exists. Add bounded retries, exponential backoff, and logging.

## Import Architecture Recommendation

Use a provider-neutral model with IA as one provider, while preserving current CAMPWP conventions.

Recommended source types:

- `attachment`: current Media Library path.
- `external_url`: generic direct URL.
- `internet_archive`: structured provider-backed remote source.

Recommended provider values:

- `internet_archive`
- `bandcamp`
- `direct`

Recommended compatible meta additions:

Album:

- `_campwp_source_provider`
- `_campwp_external_release_id`
- `_campwp_external_item_url`
- `_campwp_internet_archive_identifier`
- `_campwp_internet_archive_metadata_url`
- `_campwp_bandcamp_url`
- `_campwp_project_url`
- `_campwp_license_name`
- `_campwp_license_url`
- `_campwp_remote_cover_url`
- `_campwp_source_payload_hash`
- `_campwp_last_synced_at`
- `_campwp_sync_status`
- `_campwp_sync_message`

Track:

- `_campwp_external_track_id`
- `_campwp_external_track_index`
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
- `_campwp_source_payload_hash`
- `_campwp_last_synced_at`

Keep `_campwp_track_audio_source_type` for compatibility, but extend allowed values to include `internet_archive`. Keep `_campwp_track_audio_external_url` as a legacy generic playback URL adapter rather than the complete model.

## Idempotency Design

- Stable release key: prefer `release_id`; also store and index `archive_identifier`. If both exist and point to different posts, fail the import.
- Stable track key: `release_id + track filename` or `release_id + track number + normalized title` from the MDK record. Prefer an explicit MDK track ID if available in the source repository.
- Create behavior: create draft or configured status album, then tracks, then assign track order.
- Update behavior: update managed fields when source hash changes; leave local-only fields untouched.
- Skip behavior: skip unchanged source hashes in apply mode; report in dry-run.
- Deleted remote tracks: do not delete posts automatically. Mark sync status as `remote_missing` or unassign only with an explicit destructive flag in a later feature.
- Changed track order: update `_campwp_track_order` and `_campwp_track_number`.
- Changed titles: update only if field is managed and not locally modified.
- Changed covers: update remote cover URL; optional sideload should create a new attachment only if hash/URL changed.
- Conflict policy: store source hash and last managed field values. If WordPress content changed since last sync, report conflict and preserve local edits unless `--force-source` is specified.

## WP-CLI Design Proposal

Do not implement yet. Proposed commands:

```bash
wp campwp import-mdk /path/to/MDK148.json --dry-run
wp campwp import-mdk /path/to/publication-catalog.json --all --dry-run
wp campwp import-mdk /path/to/publication-catalog.json --all --apply --batch-size=10
wp campwp sync-internet-archive MDK148 --dry-run
wp campwp verify-external-media MDK148
```

Command requirements:

- Default to `--dry-run`.
- Require explicit `--apply` for writes.
- Print post counts, planned meta changes, skipped records, warnings, and failures.
- Support `--resume-from`, `--limit`, `--batch-size`, and `--cover-strategy=remote|sideload|skip`.
- Write a local import log or WP option/transient record with batch status.

## Recommended Rendering Design

- Use IA derived MP3 URL for browser playback.
- Use IA original FLAC URL for download.
- Render IA item link on album pages.
- Render Bandcamp and project links as external release links.
- Render license label and URL near release metadata.
- Render track duration from MDK/IA metadata, not only local attachment extraction.
- If MP3 derivative is absent, show a non-playable row with FLAC download and status, or optionally use IA embedded player as a fallback.
- If FLAC is absent, allow MP3 playback but mark original download unavailable.
- Avoid proxying public IA downloads unless entitlement rules require a controlled redirect.

## Bulk Import Plan For 148 Releases

1. Dry-run the complete catalog and save a report.
2. Pilot one release, preferably MDK148, with `--dry-run`, then `--apply` to a non-production WordPress environment.
3. Use batch size 5-10 releases for early imports.
4. Make imports resumable using release key and import log.
5. Log create/update/skip/conflict/error counts.
6. Avoid destructive rollback initially. Rollback should be a separate audited command that can trash only posts created by a specific import run.
7. Track post creation counts: expect 1 album plus N tracks per release.
8. Default attachment strategy: no audio sideload; optional cover sideload only.
9. Rate-limit IA metadata requests and verification.
10. Verify direct MP3/FLAC URLs with HEAD when possible, fallback bounded GET range if needed.

## Exact Gap List

### P0 Required Before Any MDK Import

- Add external release ID and IA identifier meta.
- Add remote playback/download/original URL model.
- Add importer normalization and validation layer.
- Add idempotent album and track lookup.
- Add dry-run planning output.
- Add URL allowlisting and local-path rejection.
- Add basic tests for schema, URL validation, and idempotency.

### P1 Required Before Bulk Import

- Add WP-CLI dry-run/apply commands.
- Add IA metadata provider/client.
- Add source payload hash and sync status.
- Add remote derivative availability handling.
- Add batch logging and resumability.
- Add conflict policy for local edits.
- Add tests for batch interruption/resume and failed remote requests.

### P2 Desirable After First Publication

- Remote cover rendering plus optional cover sideload.
- License, Bandcamp, project, and IA item rendering.
- Admin UI for external source metadata.
- Verification dashboard/report.
- IA embedded player fallback.

### P3 Future Enhancements

- Structured credits/contributor roles.
- Dedicated external source custom table.
- Scheduled sync jobs.
- Rich search/filter by source provider/license/tags.
- Download analytics for remote media.

## Tests To Recommend

- Metadata schema compatibility.
- Existing attachment-based releases continue working.
- External URL validation and host allowlisting.
- IA metadata parsing.
- MP3 derivative selection.
- FLAC original selection.
- Idempotent import create/update/skip.
- Track reordering.
- Local-edit conflict policy.
- Failed remote requests.
- Missing MP3.
- Missing FLAC.
- Missing cover.
- Batch interruption.
- Resume.
- Rollback planning.
- REST permissions if REST import is ever added.
- WP-CLI dry-run/apply behavior.
- Frontend escaping for imported text/URLs.
- Download routing and entitlement behavior.

## Verification Performed

- Repository file listing and source search for the requested terms.
- PHP syntax check across plugin PHP files: passed.
- Node syntax check for `assets/js/campwp-album-player.js`: passed.
- Composer inspection: no test/static-analysis scripts are defined.
- Test/config scan: no tests or PHPUnit/PHPStan/PHPCS/Psalm config found.


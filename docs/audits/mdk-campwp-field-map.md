# MDK To CAMPWP Field Map

This map is based on the MDK publication schema described in the task and the current CAMPWP code. The external-source fields listed as proposed targets do not exist yet and should be added before implementation.

## Album Post Fields

| Source JSON path | Target | Transformation | Required | Conflict behavior | Example value |
|---|---|---|---|---|---|
| `$.title` | `campwp_album.post_title` | Plain text sanitize | Yes | Update if source-managed and unchanged locally | `The trade wind desert soundscape` |
| `$.description` | `campwp_album.post_content` | Sanitize as allowed post HTML or plain text policy | Optional | Preserve local edits unless forced | `Eight desert soundscape tracks...` |
| `$.publication_status` | `campwp_album.post_status` | Map `published` to `publish`, otherwise `draft` | Optional | Do not unpublish without explicit flag | `published` |

## Album Meta

| Source JSON path | Target | Transformation | Required | Conflict behavior | Example value |
|---|---|---|---|---|---|
| `$.release_id` | Proposed `_campwp_external_release_id` | Text key, uppercase preserved | Yes | Immutable; fail if points to another post | `MDK148` |
| `$.release_number` | `_campwp_album_catalog_number` | Prefix if needed or store numeric as string | Optional | Source-managed update | `148` |
| `$.title_original` | Proposed `_campwp_album_title_original` | Text | Optional | Source-managed update | `The trade wind desert soundscape` |
| `$.title_normalized` | Proposed `_campwp_album_title_normalized` | Text/key | Optional | Source-managed update | `the-trade-wind-desert-soundscape` |
| `$.artist` | `_campwp_album_artist_display` | Text | Optional | Preserve local edit conflict | `MDK` |
| `$.release_date` | `_campwp_album_release_date` | Validate `YYYY-MM-DD` | Optional | Source-managed update | `null` when not supplied |
| `$.credits` | `_campwp_album_credits_override` | Textarea | Optional | Preserve local edit conflict | `Produced By K` |
| `$.license.name` or `$.license` | Proposed `_campwp_license_name` | Normalize license label | Optional | Source-managed update | `CC BY 3.0` |
| `$.license.url` | Proposed `_campwp_license_url` | URL allowlist/validate | Optional | Source-managed update | `https://creativecommons.org/licenses/by/3.0/` |
| `$.tags[]` | WordPress taxonomy, proposed `post_tag` or `campwp_genre` | Sanitize terms | Optional | Add source-managed terms; do not remove local-only unless forced | `soundscape` |
| `$.bandcamp_url` | Proposed `_campwp_bandcamp_url` | URL validate | Optional | Source-managed update | `null` when not supplied |
| `$.project_url` | Proposed `_campwp_project_url` | URL validate | Optional | Source-managed update | `https://mdkband.com/...` |
| `$.archive_identifier` | Proposed `_campwp_internet_archive_identifier` | IA identifier regex | Yes for IA media | Immutable; conflict fails | `mdk148-the-trade-wind-desert-soundscape` |
| `$.internet_archive_url` | Proposed `_campwp_external_item_url` | URL validate, IA item URL | Optional | Source-managed update | `https://archive.org/details/mdk148-the-trade-wind-desert-soundscape` |
| `$.archive_metadata.metadata_url` | Proposed `_campwp_internet_archive_metadata_url` | Build/validate metadata API URL | Optional | Source-managed update | `https://archive.org/metadata/mdk148-the-trade-wind-desert-soundscape` |
| `$.archive_state` | Proposed `_campwp_sync_status` | Normalize enum | Optional | Source-managed update | `verified` |
| `$.validation` | Proposed `_campwp_source_validation_json` | Store compact JSON | Optional | Source-managed update | `{"valid":true}` |
| `$.source_urls` | Proposed `_campwp_source_urls_json` | Store compact JSON after URL validation | Optional | Source-managed update | `["https://archive.org/..."]` |
| `$.cover.url` or IA cover file | Proposed `_campwp_remote_cover_url` | URL validate | Optional | Source-managed update; sideload only if selected | `null` when not supplied |
| Whole payload | Proposed `_campwp_source_payload_hash` | Stable JSON canonical hash | Yes | Determines update/skip | `null` until computed from canonical payload |
| Import timestamp | Proposed `_campwp_last_synced_at` | UTC ISO 8601 | Yes | Always update on apply | `null` before import execution |

## Track Post Fields

| Source JSON path | Target | Transformation | Required | Conflict behavior | Example value |
|---|---|---|---|---|---|
| `$.tracks[n].title` | `campwp_track.post_title` | Plain text sanitize | Yes | Preserve local edit conflict | `Fennec Fox` |
| `$.tracks[n].publication_status` | `campwp_track.post_status` | Map to album policy | Optional | Do not unpublish without explicit flag | `published` |

## Track Meta

| Source JSON path | Target | Transformation | Required | Conflict behavior | Example value |
|---|---|---|---|---|---|
| Parent album post ID | `_campwp_album_id` | Set after album upsert | Yes | Source-managed update | `123` |
| `$.tracks[n].position` | `_campwp_track_order` | Positive integer | Yes | Source-managed update | `1` |
| `$.tracks[n].track_number` | `_campwp_track_number` | Positive integer | Optional | Source-managed update | `1` |
| `$.tracks[n].subtitle` | `_campwp_track_subtitle` | Text | Optional | Preserve local edit conflict | `Desert dawn mix` |
| `$.tracks[n].duration` | `_campwp_track_duration` | Normalize to `M:SS` or ISO duration policy | Optional | Source-managed update | `08:11` |
| `$.tracks[n].artist` | `_campwp_track_artist_display` | Text; omit if same as album | Optional | Preserve local edit conflict | `MDK` |
| `$.tracks[n].credits` | `_campwp_track_credits` | Textarea | Optional | Preserve local edit conflict | `null` when not supplied |
| `$.tracks[n].isrc` | `_campwp_track_isrc` | Existing ISRC sanitizer | Optional | Source-managed update | `CAABC2400001` |
| `$.tracks[n].external_track_id` or derived key | Proposed `_campwp_external_track_id` | Stable text key | Yes | Immutable; conflict fails | `null` until canonical payload provides or importer derives a documented key |
| IA item identifier | Proposed `_campwp_source_provider` and `_campwp_internet_archive_identifier` | Store provider and inherited item ID | Yes | Source-managed update | `internet_archive` |
| Playback mode | `_campwp_track_audio_source_type` | Proposed new value `internet_archive`; legacy adapter may use `external_url` | Yes | Source-managed update | `internet_archive` |
| `$.tracks[n].derived_mp3_url` | Proposed `_campwp_audio_playback_url` | URL validate, IA host allowlist | Optional but needed for playback | Source-managed update | `https://archive.org/download/.../01%20-%20Fennec%20Fox.mp3` |
| `$.tracks[n].original_flac_url` | Proposed `_campwp_audio_original_url` | URL validate, IA host allowlist | Optional but needed for FLAC download | Source-managed update | `https://archive.org/download/.../01%20-%20Fennec%20Fox.flac` |
| `$.tracks[n].download_url` | Proposed `_campwp_audio_download_url` | Usually original FLAC URL | Optional | Source-managed update | `https://archive.org/download/.../01%20-%20Fennec%20Fox.flac` |
| IA file `size` | Proposed `_campwp_audio_original_size` / `_campwp_audio_playback_size` | Integer bytes | Optional | Source-managed update | `39843821` |
| IA file checksum | Proposed `_campwp_audio_original_checksum` / `_campwp_audio_playback_checksum` | Store algorithm/value JSON or text | Optional | Source-managed update | `md5:...` |
| IA derivative status | Proposed `_campwp_remote_derivative_status` | Enum `available`, `missing`, `pending`, `failed` | Optional | Source-managed update | `available` |
| Track payload | Proposed `_campwp_source_payload_hash` | Stable JSON canonical hash | Yes | Skip unchanged | `null` until computed from canonical payload |
| Import timestamp | Proposed `_campwp_last_synced_at` | UTC ISO 8601 | Yes | Always update on apply | `null` before import execution |

## WordPress Taxonomies

| Source JSON path | Target | Transformation | Required | Conflict behavior | Example value |
|---|---|---|---|---|---|
| `$.tags[]` | Proposed taxonomy choice: `post_tag` enabled for `campwp_album`, or custom `campwp_release_tag` | Lowercase/display term sanitize | Optional | Add source-managed terms; preserve local-only | `ambient` |
| `$.license.code` | Optional `campwp_license` taxonomy | Normalize license code | Optional | Source-managed update | `cc-by-3.0` |

## Media Attachments

| Source JSON path | Target | Transformation | Required | Conflict behavior | Example value |
|---|---|---|---|---|---|
| `$.cover.url` | Album featured image attachment | Only if `cover_strategy=sideload`; use `download_url()` and `media_handle_sideload()` with host/size/type limits | Optional | Create/update only when cover hash changed | `null` when not supplied |
| `$.tracks[n].artwork_url` | `_campwp_track_artwork_id` | Only if sideloading track artwork is explicitly enabled | Optional | Create/update only when changed | `01-fennec-fox.png` |
| Audio URLs | No attachment | Do not sideload MDK audio by default | Required strategy | Never create audio attachments in MDK default import | `01 - Fennec Fox.flac` |

## External Source Records

If meta-only storage becomes unwieldy, add a provider-neutral custom table or custom post type later. For the first importer, meta fields are compatible with current CAMPWP conventions and lower risk.

Recommended logical record:

| Field | Source | Notes |
|---|---|---|
| `provider` | Static/import | `internet_archive` |
| `external_release_id` | `$.release_id` | Stable MDK key |
| `archive_identifier` | `$.archive_identifier` | Stable IA key |
| `item_url` | `$.internet_archive_url` | Public item |
| `metadata_url` | Derived/source | Metadata API |
| `payload_hash` | Whole payload | Idempotency |
| `last_synced_at` | Import runtime | Sync audit |
| `sync_status` | Import/sync result | `ok`, `warning`, `error`, `conflict` |


# CAMPWP Track Metadata Model Research & v2 Proposal

## Status and scope

This document is a **research + proposal artifact only**. It does not implement a metadata rewrite.

Goals:

- audit the current CAMPWP track metadata model as implemented in code
- identify model/UI gaps for real music publishing workflows
- propose a practical v2 metadata structure for future phased implementation
- preserve current data and workflows via backward-compatible rollout

---

## 1) Current-state audit (repo-aware)

### 1.1 Where metadata lives today

Track metadata is currently distributed across:

- Track core metabox (`CoreMetadataMetaBox`) for standalone track editing.
- Album “Release Builder” (`AdminService` + `ReleaseBuilderService`) for compact inline per-track edits.
- Shared meta key constants (`MetadataKeys`) and sanitizer methods.
- Autofill/inheritance services used when creating tracks from uploaded audio.

Key evidence in repo:

- Track fields rendered in track core metabox: track number, subtitle, duration, artist override, credits, lyrics, ISRC, artwork ID, audio attachment ID, download overrides.
- Release Builder focused editor currently exposes a subset: title, track #, subtitle, read-only duration, artist override, credits, and an advanced audio attachment ID field.
- `ReleaseBuilderService::saveInlineTrackFields()` persists only that subset for inline edits.
- Track/album defaults and inheritance currently focus on artist display + credits (plus release-level label/date defaults for other contexts).
- Audio metadata extraction maps conservative fields from `wp_read_audio_metadata()` and filename fallback.

### 1.2 Current field inventory

#### Track-level metadata keys in active use

- `_campwp_track_number`
- `_campwp_track_subtitle`
- `_campwp_track_duration`
- `_campwp_track_artist_display`
- `_campwp_track_credits`
- `_campwp_track_lyrics`
- `_campwp_track_isrc`
- `_campwp_track_artwork_id`
- `_campwp_track_audio_attachment_id`
- `_campwp_track_audio_source_attachment_id`
- `_campwp_track_audio_source_classification`
- `_campwp_track_audio_mp3_attachment_id`
- `_campwp_track_audio_ogg_attachment_id`
- `_campwp_track_audio_streaming_attachment_id`
- `_campwp_track_order`
- `_campwp_album_id`

#### Album-level metadata affecting tracks

- `_campwp_album_artist_display`
- `_campwp_album_credits_override`
- `_campwp_album_release_date`
- `_campwp_album_label_name`

### 1.3 Current autofill/extraction behavior

When adding audio in Release Builder:

- extract: title, artist, album, track number, release year/date, comments/composer hints, duration
- fallback parse of filenames (e.g., `01 - Artist - Title`, `01 Title`)
- apply defaults/inheritance for artist + credits if missing
- optionally backfill album artist/date/credits from extracted metadata when album fields are empty

This is practical and intentionally conservative, but model scope is still narrow compared to full music metadata expectations.

---

## 2) Problems and gaps

### 2.1 Field semantics are mixed

Current model mixes different kinds of data without clear boundaries:

- **editorial display fields** (subtitle, artist display)
- **music identity fields** (title, track number, ISRC)
- **technical linkage fields** (audio attachment IDs, classification)
- **commerce/policy fields** (download overrides, product IDs)

This makes evolution harder and risks exposing technical internals in author-facing editors.

### 2.2 Credits are too free-form for many workflows

`credits` is currently a textarea. Good for quick use, but insufficient for:

- standardized contributor roles (producer, writer, composer, mixer, mastering)
- multiple contributors per role
- future machine-readable export/import

### 2.3 Missing common music metadata fields

For practical publishing (and rough parity with common ID3/Vorbis/FLAC expectations), key missing or under-modeled areas include:

- featured artists and artist splits
- composer/lyricist/producer role-specific credits
- explicit year/date split semantics (recording vs release date)
- genre/subgenre
- BPM, key (optional but common for some catalogs)
- language, explicit/advisory flag
- UPC/EAN at release level (future), label code, publisher text
- copyright / phonographic copyright text

### 2.4 UI mismatch between simple and advanced needs

- Release Builder intentionally keeps editing compact (good), but currently exposes an advanced technical field (`audio_attachment_id`) in the same surface as musical fields.
- Standalone track metabox has more fields, but grouping is still mostly flat and can feel noisy over time as fields expand.

### 2.5 Backward-compatibility risk if rewritten naively

Many services/readers rely directly on current flat keys. A hard rewrite could break:

- frontend rendering data providers
- release builder save payload format
- existing installs and track data already published

---

## 3) Proposed v2 metadata model (music-oriented superset)

Recommendation: keep existing flat meta keys as the canonical storage during transition, but introduce a **logical v2 schema** grouped by domain. This enables better UI and future API/export while preserving compatibility.

### 3.1 Core fields (always visible; simple workflow)

These should support “publish a track quickly” and map to existing fields where possible:

- `title` (post title)
- `version_title` (maps from current subtitle; label remains user-friendly like “Version / Mix”)
- `track_number`
- `artist_display` (primary display artist)
- `duration` (usually read-only from audio, editable override allowed)
- `audio_source` (selected file, but picker UI not raw IDs)
- `artwork` (optional override)

### 3.2 Optional musical fields (collapsed by default)

High-value fields for most artists/labels, but not mandatory:

- `featured_artists` (list)
- `genre_primary`
- `genre_secondary` (optional)
- `lyrics`
- `isrc`
- `language`
- `explicit_content` (boolean/enum)
- `release_year_hint` (if needed for per-track variance)

### 3.3 Advanced/pro fields (hidden under Advanced section)

For professional catalogs/metadata hygiene:

- `credits_structured` (array/object of role -> people)
  - performer
  - composer
  - lyricist
  - producer
  - mixing_engineer
  - mastering_engineer
  - additional roles (extensible)
- `credits_text` (human-readable liner note block; maintain current `_campwp_track_credits` compatibility)
- `publisher`
- `copyright_c` (© text)
- `copyright_p` (℗ text)
- `bpm`
- `musical_key`
- `catalog_reference` (if track-specific)
- `recording_date` (optional)

### 3.4 Internal/implementation fields (not in primary editor)

Technical fields should be maintained but moved out of core author flow:

- attachment IDs and derived transcode IDs
- source classification (`lossless`, `lossy`, `unknown`)
- album linkage/order internals
- download entitlement/product IDs
- migration state/version flags

These should live in dedicated “Technical”/debug surfaces or only in code-level APIs.

### 3.5 Suggested canonical schema shape (logical)

For future code design (not immediate storage rewrite):

- `identity`: title, version, artist, featured artists
- `sequencing`: track number, disc number (future)
- `descriptive`: genre, language, explicit flag, lyrics
- `credits`: structured roles + display text
- `rights`: isrc, copyright, publisher, label reference
- `media`: audio/artwork references
- `technical`: format/source/transcodes/duration provenance

This maps cleanly to ID3/Vorbis/FLAC concepts while remaining WordPress-admin practical.

---

## 4) Proposed future editor grouping

Use progressive disclosure to keep simple flows simple.

### Group A — Main Info (default open)

- Title
- Version / Mix
- Track #
- Artist
- Duration

### Group B — Credits & Contributors (collapsed)

- Featured artists
- Credits text
- Structured contributors (role-based repeater)

### Group C — Media & Files (collapsed)

- Audio source picker
- Artwork picker
- Optional file diagnostics (format/classification badge)

### Group D — Publishing Metadata (collapsed)

- ISRC
- Genre(s)
- Language
- Explicit flag
- Lyrics

### Group E — Advanced / Technical (collapsed; warning label)

- Attachment IDs and transcode fields
- internal linkage/debug values
- download/commerce override internals

UI principle: never require Group B–E for a valid publish flow.

---

## 5) Compatibility and migration strategy

### 5.1 Storage compatibility first

- Keep existing meta keys intact.
- Introduce a schema adapter layer (read/write mapper) that can:
  - read old flat keys into logical v2 shape
  - write v2 updates back to existing keys during transition

### 5.2 Dual-read / single-write phases

- Phase 1: read old keys only (today)
- Phase 2: read old + new logical fields, write old keys as source of truth
- Phase 3: optionally write both old and new keys (with migration flag)
- Phase 4: long-tail deprecation only after proven stability

### 5.3 Non-breaking payload evolution

- Preserve existing Release Builder payload (`campwp_release_builder[tracks][id][field]`).
- Add new fields as optional keys; ignore unknown keys safely.
- Avoid renaming or removing current keys until adapter + migration tooling exists.

### 5.4 Migration tooling recommendations

- background or admin-triggered “metadata normalization” routine
- idempotent migration with version flag per track
- dry-run report mode before applying batch updates
- no destructive deletes in early phases

### 5.5 Frontend/API stability

- Keep current frontend providers consuming existing keys until adapters are in place.
- Introduce new presenter fields incrementally with fallback to legacy values.

---

## 6) Phased implementation roadmap (small PR-sized steps)

### Phase 0 — this PR (research only)

- Add this design doc.
- No behavioral changes.

### Phase 1 — schema adapter + tests

- Add `TrackMetadataSchema`/adapter service (logical v2 <-> legacy keys).
- Unit tests for mapping edge cases.

### Phase 2 — internal service adoption

- Update autofill/inheritance services to output through adapter shape.
- Keep persisted keys unchanged.

### Phase 3 — UI regrouping (no field explosion)

- Reorganize existing track fields into grouped sections.
- Move technical attachment ID inputs into Advanced group.
- Keep field set mostly unchanged to reduce risk.

### Phase 4 — add high-value optional fields

- Introduce featured artists, genre, language, explicit flag.
- Add sanitize + persistence through adapter.
- Update frontend display selectively.

### Phase 5 — structured credits

- Add optional role-based contributor model.
- Keep `credits` textarea as compatibility + display field.
- Provide formatter from structured credits -> credits text fallback.

### Phase 6 — migration and long-tail cleanup

- ship admin migration/report tool
- begin optional dual-write strategy
- evaluate deprecations only after adoption data

---

## 7) Mapping notes against common audio metadata conventions

This proposal aligns CAMPWP with common conventions while staying pragmatic:

- **ID3-style expectations**: title, artist, album, track number, year/date, genre, lyrics, ISRC, composer/writer roles.
- **Vorbis/FLAC comment-style expectations**: flexible key/value metadata, often including artist variants, copyright, label, and custom tags.
- **Platform expectations**: simple artist/title flows for small creators + deeper rights/credits metadata for labels/power users.

CAMPWP should therefore support a **superset model with progressive UI disclosure**, not force all tags into the default editing surface.

---

## 8) Recommendation summary

- Keep current architecture (custom post types + meta + classic WP admin forms).
- Introduce a logical v2 schema adapter before changing UI/storage deeply.
- Separate musical/editorial fields from technical implementation fields.
- Use grouped, collapsible editor sections to avoid overload.
- Roll out in incremental PRs with strict backward compatibility.


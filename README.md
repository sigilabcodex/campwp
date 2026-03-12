# CAMPWP

Catalog of Albums & Music Publishing for WordPress.

## Phase 1 foundation included

- WordPress plugin bootstrap with service-oriented class registration.
- Custom post types for albums and tracks (`campwp_album`, `campwp_track`).
- Album/track relationship through canonical post meta (`_campwp_album_id`).
- Secure admin meta boxes for core metadata.
- Local storage namespacing in uploads for album/track media.
- Audio metadata extraction at upload (`_campwp_audio_metadata`).
- Frontend shortcode rendering foundations.
- WooCommerce-compatible linkage foundation for album to product mapping.

## Shortcodes

- `[campwp_album id="123"]`
- `[campwp_track id="456"]`

## Notes

This plugin intentionally focuses on foundational architecture and avoids social, recommendation, or DRM concerns in this phase.

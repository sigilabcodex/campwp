<?php

namespace CampWP\Media;

class MetadataExtractor
{
    public function register(): void
    {
        add_action('add_attachment', [$this, 'extract_on_upload']);
    }

    public function extract_on_upload(int $attachment_id): void
    {
        $mime = get_post_mime_type($attachment_id);
        if (! is_string($mime) || strpos($mime, 'audio/') !== 0) {
            return;
        }

        $path = get_attached_file($attachment_id);
        if (! is_string($path) || ! file_exists($path)) {
            return;
        }

        $meta = wp_read_audio_metadata($path);
        if (! is_array($meta) || $meta === []) {
            return;
        }

        update_post_meta($attachment_id, '_campwp_audio_metadata', $meta);
    }
}

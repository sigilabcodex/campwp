<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

$GLOBALS['campwp_test_meta'] = [];
$GLOBALS['campwp_registered_meta'] = [];
$GLOBALS['campwp_deleted_meta'] = [];

if (! function_exists('sanitize_key')) {
    function sanitize_key($key): string
    {
        return strtolower(preg_replace('/[^a-zA-Z0-9_\-]/', '', (string) $key) ?? '');
    }
}

if (! function_exists('sanitize_text_field')) {
    function sanitize_text_field($value): string
    {
        return trim(strip_tags((string) $value));
    }
}

if (! function_exists('sanitize_textarea_field')) {
    function sanitize_textarea_field($value): string
    {
        return trim(strip_tags((string) $value));
    }
}

if (! function_exists('absint')) {
    function absint($value): int
    {
        return abs((int) $value);
    }
}

if (! function_exists('esc_url_raw')) {
    function esc_url_raw($url, $protocols = null): string
    {
        $url = trim((string) $url);
        $scheme = parse_url($url, PHP_URL_SCHEME);
        if ($scheme === null) {
            return '';
        }
        if (is_array($protocols) && ! in_array(strtolower($scheme), $protocols, true)) {
            return '';
        }
        return filter_var($url, FILTER_VALIDATE_URL) ? $url : '';
    }
}

if (! function_exists('wp_parse_url')) {
    function wp_parse_url($url, $component = -1)
    {
        return $component === -1 ? parse_url((string) $url) : parse_url((string) $url, $component);
    }
}

if (! function_exists('wp_check_filetype')) {
    function wp_check_filetype($filename): array
    {
        $extension = strtolower(pathinfo((string) $filename, PATHINFO_EXTENSION));
        $types = [
            'mp3' => 'audio/mpeg',
            'ogg' => 'audio/ogg',
            'wav' => 'audio/wav',
            'flac' => 'audio/flac',
        ];

        return ['ext' => $extension, 'type' => $types[$extension] ?? ''];
    }
}

if (! function_exists('wp_json_encode')) {
    function wp_json_encode($value): string
    {
        return json_encode($value, JSON_UNESCAPED_SLASHES) ?: '';
    }
}

if (! function_exists('wp_unslash')) {
    function wp_unslash($value)
    {
        return $value;
    }
}

if (! function_exists('current_user_can')) {
    function current_user_can($capability): bool
    {
        return true;
    }
}

if (! function_exists('apply_filters')) {
    function apply_filters($hook, $value)
    {
        return $value;
    }
}

if (! function_exists('add_action')) {
    function add_action($hook, $callback, $priority = 10, $acceptedArgs = 1): void
    {
    }
}

if (! function_exists('register_post_meta')) {
    function register_post_meta($postType, $metaKey, $args): bool
    {
        $GLOBALS['campwp_registered_meta'][$postType][$metaKey] = $args;
        return true;
    }
}

if (! function_exists('get_post_meta')) {
    function get_post_meta($postId, $metaKey = '', $single = false)
    {
        $value = $GLOBALS['campwp_test_meta'][(int) $postId][$metaKey] ?? '';
        return $single ? $value : [$value];
    }
}

if (! function_exists('delete_post_meta')) {
    function delete_post_meta($postId, $metaKey): bool
    {
        $GLOBALS['campwp_deleted_meta'][] = [(int) $postId, (string) $metaKey];
        unset($GLOBALS['campwp_test_meta'][(int) $postId][$metaKey]);
        return true;
    }
}

if (! function_exists('update_post_meta')) {
    function update_post_meta($postId, $metaKey, $value): bool
    {
        $GLOBALS['campwp_test_meta'][(int) $postId][$metaKey] = $value;
        return true;
    }
}

if (! function_exists('get_post_type')) {
    function get_post_type($postId): string
    {
        return $GLOBALS['campwp_test_post_types'][(int) $postId] ?? 'campwp_track';
    }
}

if (! function_exists('wp_update_post')) {
    function wp_update_post($postarr, $wpError = false): int
    {
        $GLOBALS['campwp_updated_posts'][] = $postarr;
        return (int) ($postarr['ID'] ?? 0);
    }
}

if (! function_exists('get_post_mime_type')) {
    function get_post_mime_type($postId): string
    {
        return $GLOBALS['campwp_test_attachment_mimes'][(int) $postId] ?? 'audio/flac';
    }
}

if (! function_exists('get_attached_file')) {
    function get_attached_file($attachmentId): string
    {
        return $GLOBALS['campwp_test_attachment_files'][(int) $attachmentId] ?? 'track.flac';
    }
}

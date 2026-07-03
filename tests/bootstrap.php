<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

$GLOBALS['campwp_test_meta'] = [];
$GLOBALS['campwp_registered_meta'] = [];
$GLOBALS['campwp_deleted_meta'] = [];


if (! class_exists('CampWpCliExitException')) {
    class CampWpCliExitException extends \RuntimeException
    {
        public int $exitCode;

        public function __construct(int $exitCode)
        {
            parent::__construct('WP_CLI halted with exit code ' . $exitCode, $exitCode);
            $this->exitCode = $exitCode;
        }
    }
}

if (! class_exists('WP_CLI')) {
    class WP_CLI
    {
        public static array $commands = [];
        public static array $addCommandCalls = [];
        public static array $lines = [];
        public static array $warnings = [];
        public static array $errors = [];

        public static function add_command($name, $callable): void
        {
            self::$addCommandCalls[] = (string) $name;
            self::$commands[(string) $name] = $callable;
        }

        public static function line($message = ''): void
        {
            self::$lines[] = (string) $message;
        }

        public static function success($message): void
        {
            self::$lines[] = 'Success: ' . (string) $message;
        }

        public static function warning($message): void
        {
            self::$warnings[] = (string) $message;
        }

        public static function error($message, $exit = true): void
        {
            self::$errors[] = (string) $message;
            if ($exit) {
                throw new \CampWpCliExitException(1);
            }
        }

        public static function halt($exitCode): void
        {
            throw new \CampWpCliExitException((int) $exitCode);
        }

        public static function reset(): void
        {
            self::$commands = [];
            self::$addCommandCalls = [];
            self::$lines = [];
            self::$warnings = [];
            self::$errors = [];
        }
    }
}

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
        $failures = $GLOBALS['campwp_test_meta_write_failures'] ?? [];
        $failureKey = (int) $postId . ':' . (string) $metaKey;
        if (isset($failures[$failureKey]) || in_array((string) $metaKey, $failures, true)) {
            return false;
        }

        $GLOBALS['campwp_test_meta'][(int) $postId][$metaKey] = $value;
        return ! (bool) ($GLOBALS['campwp_test_update_post_meta_return_false'] ?? false);
    }
}

if (! function_exists('get_post_type')) {
    function get_post_type($postId): string
    {
        return $GLOBALS['campwp_test_post_types'][(int) $postId] ?? 'campwp_track';
    }
}

if (! function_exists('wp_update_post')) {
    function wp_update_post($postarr, $wpError = false)
    {
        $postId = (int) ($postarr['ID'] ?? 0);
        if (in_array((string) ($postarr['post_title'] ?? ''), $GLOBALS['campwp_test_wp_update_post_fail_titles'] ?? [], true)) {
            return $wpError ? new \WP_Error('update_failed', 'Simulated post update failure.') : 0;
        }
        if ($postId <= 0 || ! isset($GLOBALS['campwp_test_posts'][$postId])) {
            return $wpError ? new \WP_Error('missing_post', 'Post not found.') : 0;
        }

        $post = $GLOBALS['campwp_test_posts'][$postId];
        if ($post instanceof \WP_Post) {
            foreach (['post_title', 'post_type', 'post_status'] as $field) {
                if (array_key_exists($field, $postarr)) {
                    $post->{$field} = (string) $postarr[$field];
                }
            }

            foreach (['post_content', 'post_excerpt'] as $field) {
                if (array_key_exists($field, $postarr)) {
                    $post->{$field} = (string) $postarr[$field];
                }
            }

            foreach (['post_parent', 'menu_order'] as $field) {
                if (array_key_exists($field, $postarr)) {
                    $post->{$field} = (int) $postarr[$field];
                }
            }

            $GLOBALS['campwp_test_posts'][$postId] = $post;
            $GLOBALS['campwp_test_post_types'][$postId] = $post->post_type;
        }

        $GLOBALS['campwp_updated_posts'][] = $postarr;
        return $postId;
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


$GLOBALS['campwp_test_posts'] = $GLOBALS['campwp_test_posts'] ?? [];
$GLOBALS['campwp_test_next_post_id'] = $GLOBALS['campwp_test_next_post_id'] ?? 1000;
$GLOBALS['campwp_test_thumbnail_ids'] = $GLOBALS['campwp_test_thumbnail_ids'] ?? [];
$GLOBALS['campwp_test_attachment_urls'] = $GLOBALS['campwp_test_attachment_urls'] ?? [];
$GLOBALS['campwp_test_options'] = $GLOBALS['campwp_test_options'] ?? [];

if (! class_exists('WP_Post')) {
    class WP_Post
    {
        public int $ID;
        public string $post_title;
        public string $post_type;
        public string $post_status;
        public string $post_content;
        public string $post_excerpt;
        public int $post_parent;
        public int $menu_order;

        /**
         * @param array<string, mixed> $args
         */
        public function __construct(array $args = [])
        {
            $this->ID = (int) ($args['ID'] ?? 0);
            $this->post_title = (string) ($args['post_title'] ?? '');
            $this->post_type = (string) ($args['post_type'] ?? 'post');
            $this->post_status = (string) ($args['post_status'] ?? 'publish');
            $this->post_content = (string) ($args['post_content'] ?? '');
            $this->post_excerpt = (string) ($args['post_excerpt'] ?? '');
            $this->post_parent = (int) ($args['post_parent'] ?? 0);
            $this->menu_order = (int) ($args['menu_order'] ?? 0);
        }
    }
}

if (! function_exists('__')) {
    function __($text, $domain = 'default'): string
    {
        return (string) $text;
    }
}

if (! function_exists('_n')) {
    function _n($single, $plural, $number, $domain = 'default'): string
    {
        return (int) $number === 1 ? (string) $single : (string) $plural;
    }
}

if (! function_exists('esc_html')) {
    function esc_html($text): string
    {
        return htmlspecialchars((string) $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (! function_exists('esc_attr')) {
    function esc_attr($text): string
    {
        return htmlspecialchars((string) $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (! function_exists('esc_url')) {
    function esc_url($url): string
    {
        return htmlspecialchars((string) $url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (! function_exists('esc_html__')) {
    function esc_html__($text, $domain = 'default'): string
    {
        return esc_html($text);
    }
}

if (! function_exists('esc_attr__')) {
    function esc_attr__($text, $domain = 'default'): string
    {
        return esc_attr($text);
    }
}

if (! function_exists('esc_html_e')) {
    function esc_html_e($text, $domain = 'default'): void
    {
        echo esc_html($text);
    }
}

if (! function_exists('esc_attr_e')) {
    function esc_attr_e($text, $domain = 'default'): void
    {
        echo esc_attr($text);
    }
}

if (! function_exists('wp_kses_post')) {
    function wp_kses_post($html): string
    {
        return (string) $html;
    }
}

if (! function_exists('wp_kses')) {
    function wp_kses($html, $allowedHtml = []): string
    {
        return (string) $html;
    }
}

if (! function_exists('get_post')) {
    function get_post($postId)
    {
        return $GLOBALS['campwp_test_posts'][(int) $postId] ?? null;
    }
}

if (! class_exists('WP_Error')) {
    class WP_Error
    {
        private string $message;

        public function __construct(string $code = '', string $message = '')
        {
            $this->message = $message !== '' ? $message : $code;
        }

        public function get_error_message(): string
        {
            return $this->message;
        }
    }
}

if (! function_exists('is_wp_error')) {
    function is_wp_error($thing): bool
    {
        return $thing instanceof \WP_Error;
    }
}

if (! function_exists('wp_insert_post')) {
    function wp_insert_post($postarr, $wpError = false)
    {
        $title = trim((string) ($postarr['post_title'] ?? ''));
        if (in_array($title, $GLOBALS['campwp_test_wp_insert_post_fail_titles'] ?? [], true)) {
            return $wpError ? new \WP_Error('insert_failed', 'Simulated post insert failure.') : 0;
        }
        if ($title === '') {
            return $wpError ? new \WP_Error('empty_title', 'Post title is required.') : 0;
        }

        $postId = (int) ($GLOBALS['campwp_test_next_post_id'] ?? 1000);
        $GLOBALS['campwp_test_next_post_id'] = $postId + 1;

        $post = new \WP_Post([
            'ID' => $postId,
            'post_title' => $title,
            'post_type' => (string) ($postarr['post_type'] ?? 'post'),
            'post_status' => (string) ($postarr['post_status'] ?? 'draft'),
        ]);
        $post->post_content = (string) ($postarr['post_content'] ?? '');
        $post->post_excerpt = (string) ($postarr['post_excerpt'] ?? '');
        $post->post_parent = (int) ($postarr['post_parent'] ?? 0);
        $post->menu_order = (int) ($postarr['menu_order'] ?? 0);

        $GLOBALS['campwp_test_posts'][$postId] = $post;
        $GLOBALS['campwp_test_post_types'][$postId] = $post->post_type;
        $GLOBALS['campwp_inserted_posts'][] = $postarr + ['ID' => $postId];

        return $postId;
    }
}

if (! function_exists('get_posts')) {
    function get_posts($args = []): array
    {
        $postType = is_array($args) ? (string) ($args['post_type'] ?? '') : '';
        $fields = is_array($args) ? (string) ($args['fields'] ?? '') : '';
        $posts = array_values(array_filter(
            $GLOBALS['campwp_test_posts'],
            static fn ($post): bool => $post instanceof \WP_Post && ($postType === '' || $post->post_type === $postType)
        ));

        if (is_array($args) && isset($args['post_status'])) {
            $statuses = array_map('strval', (array) $args['post_status']);
            $posts = array_values(array_filter(
                $posts,
                static fn ($post): bool => in_array((string) $post->post_status, $statuses, true)
            ));
        }

        if (is_array($args) && isset($args['meta_query']) && is_array($args['meta_query'])) {
            foreach ($args['meta_query'] as $query) {
                if (! is_array($query) || ! isset($query['key'])) {
                    continue;
                }

                $metaKey = (string) $query['key'];
                $compare = strtoupper((string) ($query['compare'] ?? '='));
                $hasValue = array_key_exists('value', $query);
                $metaValue = $hasValue ? (string) $query['value'] : '';

                $posts = array_values(array_filter(
                    $posts,
                    static function ($post) use ($metaKey, $compare, $hasValue, $metaValue): bool {
                        $existing = get_post_meta((int) $post->ID, $metaKey, true);
                        $exists = $existing !== '' && $existing !== null && $existing !== [];

                        if ($compare === 'NOT EXISTS') {
                            return ! $exists;
                        }

                        if (! $hasValue) {
                            return $exists;
                        }

                        return (string) $existing === $metaValue;
                    }
                ));
            }
        }

        usort(
            $posts,
            static function ($left, $right): int {
                $leftOrder = (int) get_post_meta((int) $left->ID, '_campwp_track_order', true);
                $rightOrder = (int) get_post_meta((int) $right->ID, '_campwp_track_order', true);

                return [$leftOrder, (int) $left->ID] <=> [$rightOrder, (int) $right->ID];
            }
        );

        $limit = is_array($args) ? (int) ($args['posts_per_page'] ?? 0) : 0;
        if ($limit > 0) {
            $posts = array_slice($posts, 0, $limit);
        }

        if ($fields === 'ids') {
            return array_map(static fn ($post): int => (int) $post->ID, $posts);
        }

        return $posts;
    }
}

if (! function_exists('get_post_status')) {
    function get_post_status($postId): string
    {
        $post = get_post((int) $postId);

        return $post instanceof \WP_Post ? $post->post_status : 'publish';
    }
}

if (! function_exists('get_the_title')) {
    function get_the_title($post = 0): string
    {
        if ($post instanceof \WP_Post) {
            return $post->post_title;
        }

        $resolved = get_post((int) $post);

        return $resolved instanceof \WP_Post ? $resolved->post_title : '';
    }
}

if (! function_exists('get_permalink')) {
    function get_permalink($post = 0): string
    {
        $postId = $post instanceof \WP_Post ? $post->ID : (int) $post;

        return 'https://example.test/?p=' . $postId;
    }
}

if (! function_exists('home_url')) {
    function home_url($path = ''): string
    {
        return 'https://example.test' . (string) $path;
    }
}

if (! function_exists('add_query_arg')) {
    function add_query_arg($key, $value = null, $url = null): string
    {
        if (is_array($key)) {
            $args = $key;
            $base = (string) $value;
        } else {
            $args = [(string) $key => (string) $value];
            $base = (string) $url;
        }

        return $base . (str_contains($base, '?') ? '&' : '?') . http_build_query($args);
    }
}

if (! function_exists('get_option')) {
    function get_option($option, $default = false)
    {
        return $GLOBALS['campwp_test_options'][(string) $option] ?? $default;
    }
}

if (! function_exists('is_user_logged_in')) {
    function is_user_logged_in(): bool
    {
        return (bool) ($GLOBALS['campwp_test_is_user_logged_in'] ?? false);
    }
}

if (! function_exists('get_current_user_id')) {
    function get_current_user_id(): int
    {
        return (int) ($GLOBALS['campwp_test_current_user_id'] ?? 0);
    }
}

if (! class_exists('WP_User')) {
    class WP_User
    {
        public string $user_email;

        /**
         * @param array<string, mixed> $args
         */
        public function __construct(array $args = [])
        {
            $this->user_email = (string) ($args['user_email'] ?? 'user@example.test');
        }
    }
}

if (! function_exists('get_user_by')) {
    function get_user_by($field, $value)
    {
        if ((string) $field !== 'id' || (int) $value <= 0) {
            return false;
        }

        return new \WP_User(['user_email' => 'buyer@example.test']);
    }
}

if (! function_exists('wc_customer_bought_product')) {
    function wc_customer_bought_product($customerEmail, $userId, $productId): bool
    {
        return (bool) ($GLOBALS['campwp_test_customer_bought_product'][(int) $productId] ?? false);
    }
}

if (! function_exists('wp_login_url')) {
    function wp_login_url($redirect = ''): string
    {
        return 'https://example.test/wp-login.php';
    }
}

if (! function_exists('get_post_thumbnail_id')) {
    function get_post_thumbnail_id($post = null): int
    {
        $postId = $post instanceof \WP_Post ? $post->ID : (int) $post;

        return (int) ($GLOBALS['campwp_test_thumbnail_ids'][$postId] ?? 0);
    }
}

if (! function_exists('wp_get_attachment_image')) {
    function wp_get_attachment_image($attachmentId, $size = 'thumbnail'): string
    {
        return '<img src="https://media.example.test/attachment-' . (int) $attachmentId . '.jpg" alt="Attachment ' . (int) $attachmentId . '" data-size="' . esc_attr((string) $size) . '" />';
    }
}

if (! function_exists('get_the_post_thumbnail')) {
    function get_the_post_thumbnail($post = null, $size = 'thumbnail'): string
    {
        $postId = $post instanceof \WP_Post ? $post->ID : (int) $post;
        $attachmentId = (int) ($GLOBALS['campwp_test_thumbnail_ids'][$postId] ?? 0);

        return $attachmentId > 0 ? wp_get_attachment_image($attachmentId, $size) : '';
    }
}

if (! function_exists('wp_get_attachment_url')) {
    function wp_get_attachment_url($attachmentId): string
    {
        return $GLOBALS['campwp_test_attachment_urls'][(int) $attachmentId] ?? 'https://media.example.test/audio-' . (int) $attachmentId . '.flac';
    }
}

if (! function_exists('wp_attachment_is')) {
    function wp_attachment_is($type, $post = null): bool
    {
        return $type === 'audio' && str_starts_with((string) get_post_mime_type((int) $post), 'audio/');
    }
}

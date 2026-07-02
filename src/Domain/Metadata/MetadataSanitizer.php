<?php

declare(strict_types=1);

namespace CampWP\Domain\Metadata;

final class MetadataSanitizer
{
    /**
     * @var list<string>
     */
    private const ALBUM_RELEASE_TYPES = ['single', 'ep', 'album', 'compilation', 'other'];

    /** @var list<string> */
    private const PROVIDERS = ['direct', 'internet_archive', 'bandcamp', 'backblaze_b2', 's3', 'other'];

    /** @var list<string> */
    private const SYNC_STATUSES = ['never_synced', 'pending', 'synced', 'stale', 'remote_missing', 'failed', 'conflict'];

    /** @var list<string> */
    private const DERIVATIVE_STATUSES = ['unknown', 'pending', 'available', 'missing', 'failed'];

    private const BONUS_ITEM_TYPE_ATTACHMENT = 'wp_attachment';
    private const TRACK_AUDIO_SOURCE_TYPE_ATTACHMENT = 'attachment';
    private const TRACK_AUDIO_SOURCE_TYPE_EXTERNAL_URL = 'external_url';
    private const TRACK_AUDIO_SOURCE_TYPE_INTERNET_ARCHIVE = 'internet_archive';

    public function sanitizeText(string $value): string
    {
        return sanitize_text_field($value);
    }

    public function sanitizeTextarea(string $value): string
    {
        return sanitize_textarea_field($value);
    }

    public function sanitizeReleaseDate(string $value): string
    {
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        $dateTime = \DateTimeImmutable::createFromFormat('Y-m-d', $value);
        $errors = \DateTimeImmutable::getLastErrors();

        if ($dateTime === false) {
            return '';
        }

        if (($errors['warning_count'] ?? 0) > 0 || ($errors['error_count'] ?? 0) > 0) {
            return '';
        }

        return $dateTime->format('Y-m-d');
    }

    public function sanitizePositiveInteger(string $value): int
    {
        return max(0, absint($value));
    }

    public function sanitizeDuration(string $value): string
    {
        return sanitize_text_field($value);
    }

    public function sanitizeIsrc(string $value): string
    {
        $normalized = strtoupper(sanitize_text_field($value));
        return preg_replace('/[^A-Z0-9\-]/', '', $normalized) ?? '';
    }

    public function sanitizeAttachmentId(string $value): int
    {
        return max(0, absint($value));
    }

    public function sanitizeTrackAudioSourceType(string $value): string
    {
        $normalized = sanitize_key($value);
        $allowed = [
            self::TRACK_AUDIO_SOURCE_TYPE_ATTACHMENT,
            self::TRACK_AUDIO_SOURCE_TYPE_EXTERNAL_URL,
            self::TRACK_AUDIO_SOURCE_TYPE_INTERNET_ARCHIVE,
        ];

        if (! in_array($normalized, $allowed, true)) {
            return self::TRACK_AUDIO_SOURCE_TYPE_ATTACHMENT;
        }

        return $normalized;
    }

    public function sanitizeProvider(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        $provider = sanitize_key(str_replace([" ", "-"], "_", $value));

        return in_array($provider, self::PROVIDERS, true) ? $provider : '';
    }

    public function sanitizeExternalId(string $value): string
    {
        $value = trim(sanitize_text_field($value));

        if ($value === '' || strlen($value) > 191) {
            return '';
        }

        return preg_match('/^[A-Za-z0-9][A-Za-z0-9._:\-]*$/', $value) === 1 ? $value : '';
    }

    public function sanitizeInternetArchiveIdentifier(string $value): string
    {
        $value = trim($value);

        if ($value === '' || strlen($value) > 100) {
            return '';
        }

        if (str_contains($value, '/') || str_contains($value, '\\') || preg_match('/^[a-z][a-z0-9+.-]*:/i', $value) === 1) {
            return '';
        }

        return preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]*$/', $value) === 1 ? $value : '';
    }

    public function sanitizeTrackAudioExternalUrl(string $value): string
    {
        $url = trim($value);
        if ($url === '') {
            return '';
        }

        $validated = esc_url_raw($url, ['http', 'https']);

        return is_string($validated) ? $validated : '';
    }

    public function sanitizeRemoteUrl(string $value, string $provider = 'direct'): string
    {
        $url = trim($value);
        if ($url === '' || $this->looksLikeLocalPath($url)) {
            return '';
        }

        $parts = wp_parse_url($url);
        if (! is_array($parts)) {
            return '';
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));

        if ($scheme !== 'https' || $host === '' || isset($parts['user']) || isset($parts['pass'])) {
            return '';
        }

        $sanitizedProvider = $this->sanitizeProvider($provider);
        if (trim($provider) !== '' && $sanitizedProvider === '') {
            return '';
        }

        $effectiveProvider = $sanitizedProvider !== '' ? $sanitizedProvider : 'direct';
        if ($this->isDisallowedHost($host) || ! $this->isAllowedProviderHost($host, $effectiveProvider)) {
            return '';
        }

        $validated = esc_url_raw($url, ['https']);

        return is_string($validated) ? $validated : '';
    }

    public function sanitizeLicenseUrl(string $value): string
    {
        $url = trim($value);
        if ($url === '' || $this->looksLikeLocalPath($url)) {
            return '';
        }

        $parts = wp_parse_url($url);
        if (! is_array($parts)) {
            return '';
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));

        if ($scheme !== 'https' || $host === '' || isset($parts['user']) || isset($parts['pass']) || $this->isDisallowedHost($host)) {
            return '';
        }

        $validated = esc_url_raw($url, ['https']);

        return is_string($validated) ? $validated : '';
    }

    public function sanitizeChecksum(string $value): string
    {
        $value = strtolower(trim($value));

        if (preg_match('/^sha256:[a-f0-9]{64}$/', $value) === 1) {
            return $value;
        }

        if (preg_match('/^sha1:[a-f0-9]{40}$/', $value) === 1) {
            return $value;
        }

        if (preg_match('/^md5:[a-f0-9]{32}$/', $value) === 1) {
            return $value;
        }

        return '';
    }

    public function sanitizeIso8601Timestamp(string $value): string
    {
        $value = trim($value);
        if ($value === '' || preg_match('/^\d{4}-\d{2}-\d{2}T/', $value) !== 1) {
            return '';
        }

        try {
            $date = new \DateTimeImmutable($value);
        } catch (\Exception $exception) {
            return '';
        }

        return $date->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z');
    }

    public function sanitizeSyncStatus(string $value): string
    {
        $status = sanitize_key($value);

        return in_array($status, self::SYNC_STATUSES, true) ? $status : 'never_synced';
    }

    public function sanitizeDerivativeStatus(string $value): string
    {
        $status = sanitize_key($value);

        return in_array($status, self::DERIVATIVE_STATUSES, true) ? $status : 'unknown';
    }

    public function sanitizeFormatName(string $value): string
    {
        if (str_contains($value, "/") || str_contains($value, "\\")) {
            return "";
        }

        $format = sanitize_key($value);

        if ($format === '' || strlen($format) > 32) {
            return '';
        }

        return preg_match('/^[a-z0-9][a-z0-9_-]*$/', $format) === 1 ? $format : '';
    }

    public function sanitizePositiveFileSize(string $value): int
    {
        $size = absint($value);

        return $size > 0 ? $size : 0;
    }

    public function sanitizeSourcePayloadHash(string $value): string
    {
        return $this->sanitizeChecksum($value);
    }

    public function sanitizeReleaseType(string $value): string
    {
        $normalized = sanitize_key($value);

        if (! in_array($normalized, self::ALBUM_RELEASE_TYPES, true)) {
            return 'album';
        }

        return $normalized;
    }

    /**
     * @param mixed $value
     */
    public function sanitizeBonusItems($value): string
    {
        $decoded = $this->decodeBonusItems($value);

        if (! is_array($decoded)) {
            return '[]';
        }

        $normalizedItems = [];

        foreach ($decoded as $item) {
            if (! is_array($item)) {
                continue;
            }

            $type = isset($item['type']) ? sanitize_key((string) $item['type']) : '';
            $referenceId = isset($item['reference_id']) ? absint($item['reference_id']) : 0;
            $label = isset($item['label']) ? sanitize_text_field((string) $item['label']) : '';

            if ($type !== self::BONUS_ITEM_TYPE_ATTACHMENT || $referenceId <= 0) {
                continue;
            }

            $itemKey = $type . ':' . $referenceId;
            $normalizedItems[$itemKey] = [
                'type' => self::BONUS_ITEM_TYPE_ATTACHMENT,
                'reference_id' => $referenceId,
                'label' => $label,
            ];
        }

        return (string) wp_json_encode(array_values($normalizedItems));
    }

    private function looksLikeLocalPath(string $value): bool
    {
        if (str_starts_with($value, '/')) {
            return true;
        }

        return strlen($value) >= 3
            && ctype_alpha($value[0])
            && $value[1] === ':'
            && in_array($value[2], ['\\', '/'], true);
    }

    private function isDisallowedHost(string $host): bool
    {
        $host = trim($host, '[]');

        if (in_array($host, ['localhost', 'localhost.localdomain'], true)) {
            return true;
        }

        if ($this->isSuspiciousNumericHost($host)) {
            return true;
        }

        if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return ! filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
        }

        if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            return ! filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
        }

        return false;
    }

    private function isSuspiciousNumericHost(string $host): bool
    {
        $host = strtolower(trim($host, '.'));
        if ($host === '') {
            return false;
        }

        if (preg_match('/^(?:\d+|0x[0-9a-f]+)$/i', $host) === 1) {
            return true;
        }

        $parts = explode('.', $host);
        if (count($parts) < 2) {
            return false;
        }

        $allNumericLike = true;
        foreach ($parts as $part) {
            if ($part === '') {
                return false;
            }

            if (preg_match('/^(?:\d+|0x[0-9a-f]+)$/i', $part) !== 1) {
                $allNumericLike = false;
                continue;
            }

            if (preg_match('/^(?:0x[0-9a-f]+|0\d+)$/i', $part) === 1) {
                return true;
            }
        }

        return $allNumericLike;
    }

    private function isAllowedProviderHost(string $host, string $provider): bool
    {
        if ($provider === 'internet_archive') {
            return in_array($host, ['archive.org', 'www.archive.org'], true)
                || preg_match('/^ia800\d*\.us\.archive\.org$/', $host) === 1;
        }

        if ($provider === 'bandcamp') {
            return $host === 'bandcamp.com' || $host === 'www.bandcamp.com' || str_ends_with($host, '.bandcamp.com');
        }

        if ($host === 'mdkband.com' || $host === 'www.mdkband.com') {
            return true;
        }

        return in_array($provider, ['direct', 'backblaze_b2', 's3', 'other'], true);
    }

    /**
     * @param mixed $value
     * @return mixed
     */
    private function decodeBonusItems($value)
    {
        if (is_array($value)) {
            return $value;
        }

        if (! is_string($value) || trim($value) === '') {
            return [];
        }

        $decoded = json_decode(wp_unslash($value), true);

        return is_array($decoded) ? $decoded : [];
    }
}

<?php

declare(strict_types=1);

namespace CampWP\Domain\Metadata;

final class MetadataSanitizer
{
    /**
     * @var list<string>
     */
    private const ALBUM_RELEASE_TYPES = ['single', 'ep', 'album', 'compilation', 'other'];

    private const BONUS_ITEM_TYPE_ATTACHMENT = 'wp_attachment';

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

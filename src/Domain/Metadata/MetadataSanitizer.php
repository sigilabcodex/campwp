<?php

declare(strict_types=1);

namespace CampWP\Domain\Metadata;

final class MetadataSanitizer
{
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
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        if (preg_match('/^\d{1,2}:\d{2}(?::\d{2})?$/', $value) !== 1) {
            return '';
        }

        return $value;
    }

    public function sanitizeIsrc(string $value): string
    {
        $normalized = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $value) ?? '');

        if ($normalized === '') {
            return '';
        }

        if (preg_match('/^[A-Z]{2}[A-Z0-9]{3}\d{7}$/', $normalized) !== 1) {
            return '';
        }

        return $normalized;
    }
}

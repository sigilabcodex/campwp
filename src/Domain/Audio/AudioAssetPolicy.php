<?php

declare(strict_types=1);

namespace CampWP\Domain\Audio;

final class AudioAssetPolicy
{
    /**
     * Default ingest policy: lossy sources are allowed, but always treated as secondary to lossless masters.
     * Sites can disable lossy source uploads by filtering `campwp_audio_policy_allow_lossy_sources` to false.
     */
    private const DEFAULT_ALLOW_LOSSY_SOURCES = true;

    public function allowsLossySources(): bool
    {
        return (bool) apply_filters('campwp_audio_policy_allow_lossy_sources', self::DEFAULT_ALLOW_LOSSY_SOURCES);
    }

    public function shouldAllowSourceClassification(string $classification): bool
    {
        if ($classification === AudioFormatClassification::LOSSLESS) {
            return true;
        }

        if ($classification === AudioFormatClassification::LOSSY) {
            return $this->allowsLossySources();
        }

        return false;
    }

    public function describeClassification(string $classification): string
    {
        if ($classification === AudioFormatClassification::LOSSLESS) {
            return __('Preferred source (lossless master).', 'campwp');
        }

        if ($classification === AudioFormatClassification::LOSSY) {
            return __('Lossy-only source. Lossless is preferred, and no true hi-fi lossless derivative can be offered from this file.', 'campwp');
        }

        return __('Unsupported or unknown source format.', 'campwp');
    }
}

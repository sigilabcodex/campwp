<?php

declare(strict_types=1);

namespace CampWP\Domain\Audio;

final class AudioAssetPolicy
{
    public function allowsLossySources(): bool
    {
        return (bool) apply_filters('campwp_audio_policy_allow_lossy_sources', true);
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
            return __('Lossy-only source. High-fidelity lossless derivatives cannot be generated from this upload.', 'campwp');
        }

        return __('Unsupported or unknown source format.', 'campwp');
    }
}

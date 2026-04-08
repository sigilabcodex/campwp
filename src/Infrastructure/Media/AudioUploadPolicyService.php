<?php

declare(strict_types=1);

namespace CampWP\Infrastructure\Media;

use CampWP\Domain\Audio\AudioAssetPolicy;
use CampWP\Domain\Audio\AudioFormatClassifier;
use CampWP\Domain\Audio\AudioFormatClassification;

final class AudioUploadPolicyService
{
    private AudioFormatClassifier $classifier;
    private AudioAssetPolicy $policy;

    public function __construct(?AudioFormatClassifier $classifier = null, ?AudioAssetPolicy $policy = null)
    {
        $this->classifier = $classifier ?? new AudioFormatClassifier();
        $this->policy = $policy ?? new AudioAssetPolicy();
    }

    public function register(): void
    {
        add_filter('upload_mimes', [$this, 'registerAudioMimes']);
        add_filter('wp_check_filetype_and_ext', [$this, 'normalizeAudioFiletype'], 10, 4);
        add_filter('wp_handle_upload_prefilter', [$this, 'enforceAudioPolicy']);
    }

    /**
     * @param array<string,string> $mimes
     * @return array<string,string>
     */
    public function registerAudioMimes(array $mimes): array
    {
        return array_merge($mimes, $this->classifier->supportedMimes());
    }

    /**
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    public function normalizeAudioFiletype(array $data, string $file, string $filename, array $mimes): array
    {
        if (($data['ext'] ?? false) !== false && ($data['type'] ?? false) !== false) {
            return $data;
        }

        $type = wp_check_filetype($filename, array_merge($mimes, $this->classifier->supportedMimes()));
        $ext = strtolower((string) ($type['ext'] ?? ''));

        if (! in_array($ext, ['wav', 'flac', 'aif', 'aiff', 'mp3', 'ogg'], true)) {
            return $data;
        }

        return [
            'ext' => $ext,
            'type' => (string) ($type['type'] ?? ''),
            'proper_filename' => $data['proper_filename'] ?? false,
        ];
    }

    /**
     * @param array<string,mixed> $file
     * @return array<string,mixed>
     */
    public function enforceAudioPolicy(array $file): array
    {
        $name = (string) ($file['name'] ?? '');
        $tmp = (string) ($file['tmp_name'] ?? '');

        if ($name === '' || $tmp === '') {
            return $file;
        }

        $type = wp_check_filetype_and_ext($tmp, $name, $this->classifier->supportedMimes());
        $classification = $this->classifier->classify((string) ($type['type'] ?? ''), (string) ($type['ext'] ?? ''));

        if ($classification['classification'] === AudioFormatClassification::LOSSY && ! $this->policy->allowsLossySources()) {
            $file['error'] = __('This site currently allows only lossless source audio uploads (WAV, FLAC, AIFF).', 'campwp');
        }

        return $file;
    }
}

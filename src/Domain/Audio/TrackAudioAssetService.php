<?php

declare(strict_types=1);

namespace CampWP\Domain\Audio;

use CampWP\Domain\Metadata\MetadataKeys;

final class TrackAudioAssetService
{
    public function getForTrack(int $trackId): TrackAudioAsset
    {
        return new TrackAudioAsset(
            max(0, (int) get_post_meta($trackId, MetadataKeys::TRACK_AUDIO_SOURCE_ATTACHMENT_ID, true)),
            max(0, (int) get_post_meta($trackId, MetadataKeys::TRACK_AUDIO_MP3_ATTACHMENT_ID, true)),
            max(0, (int) get_post_meta($trackId, MetadataKeys::TRACK_AUDIO_OGG_ATTACHMENT_ID, true)),
            max(0, (int) get_post_meta($trackId, MetadataKeys::TRACK_AUDIO_STREAMING_ATTACHMENT_ID, true)),
            (string) get_post_meta($trackId, MetadataKeys::TRACK_AUDIO_SOURCE_CLASSIFICATION, true)
        );
    }
}

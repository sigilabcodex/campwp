<?php

declare(strict_types=1);

namespace CampWP\Frontend\Rendering;

use CampWP\Domain\Audio\TrackAudioFile;

final class AlbumPlayerRenderer
{
    /**
     * @param list<array<string, mixed>> $tracks
     */
    public function render(array $tracks): void
    {
        $firstPlayableTrack = $this->getFirstPlayableTrack($tracks);
        $initialSource = '';
        $initialMime = '';
        $initialTitle = '';

        if (is_array($firstPlayableTrack)) {
            $audio = $firstPlayableTrack['audio'] ?? null;
            if ($audio instanceof TrackAudioFile) {
                $initialSource = $audio->getUrl();
                $initialMime = $audio->getMimeType();
                $initialTitle = (string) ($firstPlayableTrack['title'] ?? '');
            }
        }
        ?>
        <section class="campwp-album-player" data-campwp-album-player>
            <h2><?php esc_html_e('Player', 'campwp'); ?></h2>
            <div class="campwp-album-player-surface">
                <div class="campwp-album-player-controls">
                    <button type="button" class="campwp-player-button" data-campwp-action="prev" aria-label="<?php esc_attr_e('Previous track', 'campwp'); ?>">‹‹</button>
                    <button type="button" class="campwp-player-button" data-campwp-action="toggle" aria-label="<?php esc_attr_e('Play', 'campwp'); ?>" data-campwp-toggle-state="paused">▶</button>
                    <button type="button" class="campwp-player-button" data-campwp-action="next" aria-label="<?php esc_attr_e('Next track', 'campwp'); ?>">››</button>
                </div>
                <p class="campwp-album-player-track">
                    <strong><?php esc_html_e('Now playing', 'campwp'); ?>:</strong>
                    <span data-campwp-current-track><?php echo esc_html($initialTitle !== '' ? $initialTitle : __('No track selected', 'campwp')); ?></span>
                </p>
                <div class="campwp-album-player-timeline">
                    <span data-campwp-current-time>0:00</span>
                    <input type="range" min="0" max="100" step="0.1" value="0" data-campwp-seek />
                    <span data-campwp-duration>0:00</span>
                </div>
                <audio preload="metadata" data-campwp-audio>
                    <?php if ($initialSource !== '') : ?>
                        <source src="<?php echo esc_url($initialSource); ?>" type="<?php echo esc_attr($initialMime); ?>" />
                    <?php endif; ?>
                </audio>
            </div>
        </section>
        <?php
    }

    /**
     * @param list<array<string, mixed>> $tracks
     * @return array<string, mixed>|null
     */
    private function getFirstPlayableTrack(array $tracks): ?array
    {
        foreach ($tracks as $track) {
            if (($track['audio'] ?? null) instanceof TrackAudioFile) {
                return $track;
            }
        }

        return null;
    }
}

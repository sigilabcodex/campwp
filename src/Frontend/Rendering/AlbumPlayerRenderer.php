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
                    <button type="button" class="campwp-player-button" data-campwp-action="prev" aria-label="<?php esc_attr_e('Previous track', 'campwp'); ?>">
                        <?php $this->renderIcon('previous'); ?>
                    </button>
                    <button type="button" class="campwp-player-button" data-campwp-action="toggle" aria-label="<?php esc_attr_e('Play', 'campwp'); ?>" data-campwp-toggle-state="paused">
                        <span class="campwp-player-icon" data-campwp-icon aria-hidden="true"><?php $this->renderIcon('play'); ?></span>
                    </button>
                    <button type="button" class="campwp-player-button" data-campwp-action="next" aria-label="<?php esc_attr_e('Next track', 'campwp'); ?>">
                        <?php $this->renderIcon('next'); ?>
                    </button>
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

    private function renderIcon(string $name): void
    {
        $icons = [
            'previous' => '<svg viewBox="0 0 24 24" width="16" height="16" focusable="false" aria-hidden="true"><rect x="4" y="4" width="2" height="16" fill="currentColor"></rect><polygon points="20,4 8,12 20,20" fill="currentColor"></polygon></svg>',
            'play' => '<svg viewBox="0 0 24 24" width="16" height="16" focusable="false" aria-hidden="true"><polygon points="6,4 20,12 6,20" fill="currentColor"></polygon></svg>',
            'pause' => '<svg viewBox="0 0 24 24" width="16" height="16" focusable="false" aria-hidden="true"><rect x="6" y="4" width="4" height="16" fill="currentColor"></rect><rect x="14" y="4" width="4" height="16" fill="currentColor"></rect></svg>',
            'next' => '<svg viewBox="0 0 24 24" width="16" height="16" focusable="false" aria-hidden="true"><rect x="18" y="4" width="2" height="16" fill="currentColor"></rect><polygon points="4,4 16,12 4,20" fill="currentColor"></polygon></svg>',
        ];

        echo wp_kses(
            $icons[$name] ?? '',
            [
                'svg' => [
                    'viewBox' => true,
                    'focusable' => true,
                    'aria-hidden' => true,
                ],
                'rect' => [
                    'x' => true,
                    'y' => true,
                    'width' => true,
                    'height' => true,
                    'fill' => true,
                ],
                'polygon' => [
                    'points' => true,
                    'fill' => true,
                ],
            ]
        );
    }
}

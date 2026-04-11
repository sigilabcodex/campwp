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
                        <span class="campwp-player-icon campwp-player-icon-play" data-campwp-icon="play" aria-hidden="true"><?php $this->renderIcon('play'); ?></span>
                        <span class="campwp-player-icon campwp-player-icon-pause" data-campwp-icon="pause" aria-hidden="true"><?php $this->renderIcon('pause'); ?></span>
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
            'previous' => '<svg viewBox="0 0 24 24" focusable="false" aria-hidden="true"><path fill="currentColor" d="M7 5a1 1 0 0 1 1 1v12a1 1 0 1 1-2 0V6a1 1 0 0 1 1-1Zm11.5 1.8v10.4a1 1 0 0 1-1.52.86l-7.8-5.2a1 1 0 0 1 0-1.66l7.8-5.2a1 1 0 0 1 1.52.8Z"/></svg>',
            'play' => '<svg viewBox="0 0 24 24" focusable="false" aria-hidden="true"><path fill="currentColor" d="M8 5.8a1 1 0 0 1 1.5-.86l9 6.2a1 1 0 0 1 0 1.72l-9 6.2A1 1 0 0 1 8 18.2V5.8Z"/></svg>',
            'pause' => '<svg viewBox="0 0 24 24" focusable="false" aria-hidden="true"><path fill="currentColor" d="M8 5a1 1 0 0 1 1 1v12a1 1 0 1 1-2 0V6a1 1 0 0 1 1-1Zm8 0a1 1 0 0 1 1 1v12a1 1 0 1 1-2 0V6a1 1 0 0 1 1-1Z"/></svg>',
            'next' => '<svg viewBox="0 0 24 24" focusable="false" aria-hidden="true"><path fill="currentColor" d="M17 5a1 1 0 0 1 1 1v12a1 1 0 1 1-2 0V6a1 1 0 0 1 1-1ZM5.5 6.8v10.4a1 1 0 0 0 1.52.86l7.8-5.2a1 1 0 0 0 0-1.66l-7.8-5.2a1 1 0 0 0-1.52.8Z"/></svg>',
        ];

        echo wp_kses(
            $icons[$name] ?? '',
            [
                'svg' => [
                    'viewBox' => true,
                    'focusable' => true,
                    'aria-hidden' => true,
                ],
                'path' => [
                    'fill' => true,
                    'd' => true,
                ],
            ]
        );
    }
}

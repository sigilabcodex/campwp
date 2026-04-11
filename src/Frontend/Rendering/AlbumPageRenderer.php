<?php

declare(strict_types=1);

namespace CampWP\Frontend\Rendering;

use CampWP\Domain\Audio\TrackAudioFile;
use CampWP\Frontend\Data\AlbumViewDataProvider;

final class AlbumPageRenderer
{
    private AlbumViewDataProvider $dataProvider;
    private AlbumPlayerRenderer $albumPlayerRenderer;

    public function __construct(AlbumViewDataProvider $dataProvider, ?AlbumPlayerRenderer $albumPlayerRenderer = null)
    {
        $this->dataProvider = $dataProvider;
        $this->albumPlayerRenderer = $albumPlayerRenderer ?? new AlbumPlayerRenderer();
    }

    public function render(\WP_Post $album, string $content): string
    {
        $data = $this->dataProvider->getAlbumViewData($album);
        $heroTitle = $this->buildArtistTitleHeading((string) $data['artist_display'], (string) $data['title']);

        ob_start();
        ?>
        <article class="campwp campwp-album" data-campwp-release-id="<?php echo esc_attr((string) $data['id']); ?>">
            <header class="campwp-release-header">
                <div class="campwp-release-hero">
                    <?php if ($data['cover_html'] !== '') : ?>
                        <figure class="campwp-release-cover">
                            <button type="button" class="campwp-release-cover-button" data-campwp-cover-open aria-label="<?php esc_attr_e('Open release cover', 'campwp'); ?>">
                                <?php echo wp_kses_post((string) $data['cover_html']); ?>
                            </button>
                        </figure>
                    <?php endif; ?>

                    <div class="campwp-release-summary">
                        <h1 class="campwp-release-title"><?php echo esc_html($heroTitle); ?></h1>
                        <?php if ($data['subtitle'] !== '') : ?>
                            <p class="campwp-release-subtitle"><?php echo esc_html((string) $data['subtitle']); ?></p>
                        <?php endif; ?>

                        <?php $releaseIdentity = $this->buildReleaseIdentityLine((string) $data['release_type_label'], (string) $data['release_date'], (string) $data['label_name']); ?>
                        <?php if ($releaseIdentity !== '') : ?>
                            <p class="campwp-release-meta-line"><?php echo esc_html($releaseIdentity); ?></p>
                        <?php endif; ?>

                        <?php $this->renderCta((array) $data['cta'], __('Album download', 'campwp'), 'release'); ?>
                    </div>
                </div>
            </header>

            <?php if ($data['tracks'] !== []) : ?>
                <?php $this->albumPlayerRenderer->render((array) $data['tracks']); ?>
            <?php endif; ?>

            <section class="campwp-track-list">
                <h2><?php esc_html_e('Tracklist', 'campwp'); ?></h2>
                <div class="campwp-track-list-surface">
                    <?php if ($data['tracks'] !== []) : ?>
                        <p class="campwp-track-list-summary">
                            <?php
                            echo esc_html(
                                sprintf(
                                    _n('%d track', '%d tracks', count($data['tracks']), 'campwp'),
                                    count($data['tracks'])
                                )
                            );
                            ?>
                        </p>
                    <?php endif; ?>

                    <?php if ($data['tracks'] === []) : ?>
                        <p><?php esc_html_e('No published tracks are assigned to this release yet.', 'campwp'); ?></p>
                        <?php if ((int) ($data['unpublished_track_count'] ?? 0) > 0) : ?>
                            <p><?php echo esc_html(sprintf(_n('%d assigned track is still unpublished.', '%d assigned tracks are still unpublished.', (int) $data['unpublished_track_count'], 'campwp'), (int) $data['unpublished_track_count'])); ?></p>
                        <?php endif; ?>
                    <?php else : ?>
                        <ol class="campwp-track-list-items">
                            <?php foreach ($data['tracks'] as $track) : ?>
                                <?php $audio = $track['audio'] instanceof TrackAudioFile ? $track['audio'] : null; ?>
                                <li
                                    class="campwp-track-row"
                                    data-campwp-track-id="<?php echo esc_attr((string) $track['id']); ?>"
                                    data-campwp-title="<?php echo esc_attr((string) $track['title']); ?>"
                                    data-campwp-duration="<?php echo esc_attr((string) ($track['duration'] ?? '')); ?>"
                                    data-campwp-audio-src="<?php echo esc_attr($audio ? $audio->getUrl() : ''); ?>"
                                    data-campwp-audio-type="<?php echo esc_attr($audio ? $audio->getMimeType() : ''); ?>"
                                >
                                    <div class="campwp-track-primary">
                                        <button type="button" class="campwp-track-play-toggle" data-campwp-action="track-select">
                                            <?php if ($track['artwork_html'] !== '') : ?>
                                                <div class="campwp-track-artwork"><?php echo wp_kses_post((string) $track['artwork_html']); ?></div>
                                            <?php endif; ?>

                                            <div class="campwp-track-info">
                                                <?php
                                                $trackTitle = trim((string) $track['title']);
                                                if ($trackTitle === '') {
                                                    $trackTitle = __('Untitled track', 'campwp');
                                                }
                                                $trackIdentity = $this->buildArtistTitleHeading((string) $track['artist_display'], $trackTitle);
                                                ?>
                                                <div class="campwp-track-heading">
                                                    <span class="campwp-track-link"><?php echo esc_html(sprintf('%d. %s', (int) $track['number'], $trackIdentity)); ?></span>
                                                    <span class="campwp-track-play-hint"><?php esc_html_e('(click to play)', 'campwp'); ?></span>
                                                </div>

                                                <?php if ($track['subtitle'] !== '') : ?>
                                                    <p class="campwp-track-subtitle"><?php echo esc_html((string) $track['subtitle']); ?></p>
                                                <?php endif; ?>

                                                <p class="campwp-track-metadata">
                                                    <?php if ($track['duration'] !== '') : ?>
                                                        <span><strong><?php esc_html_e('Duration', 'campwp'); ?>:</strong> <?php echo esc_html((string) $track['duration']); ?></span>
                                                    <?php endif; ?>
                                                </p>
                                            </div>
                                        </button>

                                        <?php if ($track['permalink'] !== '') : ?>
                                            <p class="campwp-track-permalink">
                                                <a href="<?php echo esc_url((string) $track['permalink']); ?>"><?php esc_html_e('Open track page', 'campwp'); ?></a>
                                            </p>
                                        <?php endif; ?>
                                        <?php if (! $audio) : ?>
                                            <p class="campwp-empty-state"><?php esc_html_e('No audio attached for this track yet.', 'campwp'); ?></p>
                                        <?php endif; ?>
                                    </div>

                                    <div class="campwp-track-secondary">
                                        <?php $this->renderCta((array) $track['cta'], __('Track download', 'campwp'), 'track'); ?>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ol>
                    <?php endif; ?>
                </div>
            </section>

            <?php if ($content !== '') : ?>
                <section class="campwp-release-description">
                    <h2><?php esc_html_e('About', 'campwp'); ?></h2>
                    <?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                </section>
            <?php endif; ?>

            <?php if ($data['release_notes'] !== '' || $data['credits'] !== '') : ?>
                <section class="campwp-release-notes">
                    <h2><?php esc_html_e('Notes', 'campwp'); ?></h2>
                    <?php if ($data['release_notes'] !== '') : ?>
                        <p><?php echo nl2br(esc_html((string) $data['release_notes'])); ?></p>
                    <?php endif; ?>
                    <?php if ($data['credits'] !== '') : ?>
                        <p><?php echo nl2br(esc_html((string) $data['credits'])); ?></p>
                    <?php endif; ?>
                </section>
            <?php endif; ?>

            <section class="campwp-bonus-assets">
                <h2><?php esc_html_e('Bonus media', 'campwp'); ?></h2>
                <?php if ($data['bonus_assets'] === []) : ?>
                    <p class="campwp-empty-state"><?php esc_html_e('No bonus attachments are available for this release.', 'campwp'); ?></p>
                <?php else : ?>
                    <ul class="campwp-bonus-list">
                        <?php foreach ($data['bonus_assets'] as $asset) : ?>
                            <?php
                            $assetLabel = (string) ($asset['label'] ?? '');
                            $fallbackLabel = (string) ($asset['fallback_label'] ?? '');
                            $displayLabel = $assetLabel !== '' ? $assetLabel : $fallbackLabel;
                            ?>
                            <li class="campwp-bonus-row">
                                <p class="campwp-bonus-title"><?php echo esc_html($displayLabel !== '' ? $displayLabel : __('Bonus file', 'campwp')); ?></p>
                                <?php $this->renderCta((array) $asset['cta'], __('Bonus download', 'campwp'), 'bonus'); ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </section>

            <?php if ($data['cover_html'] !== '') : ?>
                <div class="campwp-cover-lightbox" data-campwp-cover-lightbox hidden>
                    <button type="button" class="campwp-cover-lightbox-backdrop" data-campwp-cover-close aria-label="<?php esc_attr_e('Close cover preview', 'campwp'); ?>"></button>
                    <div class="campwp-cover-lightbox-dialog" role="dialog" aria-modal="true" aria-label="<?php esc_attr_e('Release cover preview', 'campwp'); ?>">
                        <button type="button" class="campwp-cover-lightbox-close" data-campwp-cover-close aria-label="<?php esc_attr_e('Close', 'campwp'); ?>">×</button>
                        <figure class="campwp-cover-lightbox-image">
                            <?php echo wp_kses_post((string) $data['cover_html']); ?>
                        </figure>
                    </div>
                </div>
            <?php endif; ?>
        </article>
        <?php

        return (string) ob_get_clean();
    }

    /**
     * @param array<string, mixed> $cta
     */
    private function renderCta(array $cta, string $title, string $context = 'generic'): void
    {
        $title = $this->normalizeCtaTitle($title, $context);
        $state = (string) ($cta['state'] ?? '');
        $label = $this->normalizeCtaLabel((string) ($cta['label'] ?? ''), $state, $context);
        $message = $this->normalizeCtaMessage((string) ($cta['message'] ?? ''), $state, $context);
        $actionLabel = $this->normalizeCtaActionLabel((string) ($cta['action_label'] ?? ''), $context);
        $actionUrl = (string) ($cta['action_url'] ?? '');
        ?>
        <section class="campwp-cta" data-campwp-cta-state="<?php echo esc_attr($state); ?>">
            <?php if ($title !== '') : ?>
                <h3 class="campwp-cta-title"><?php echo esc_html($title); ?></h3>
            <?php endif; ?>
            <?php if ($label !== '') : ?>
                <p class="campwp-cta-label"><?php echo esc_html($label); ?></p>
            <?php endif; ?>
            <?php if ($message !== '') : ?>
                <p class="campwp-cta-message"><?php echo esc_html($message); ?></p>
            <?php endif; ?>
            <?php if ($actionLabel !== '' && $actionUrl !== '') : ?>
                <p><a class="campwp-cta-button" href="<?php echo esc_url($actionUrl); ?>"><?php echo esc_html($actionLabel); ?></a></p>
            <?php endif; ?>
        </section>
        <?php
    }

    private function normalizeCtaTitle(string $title, string $context): string
    {
        if ($context === 'track') {
            return '';
        }

        return $title;
    }

    private function normalizeCtaLabel(string $label, string $state, string $context): string
    {
        if ($context === 'release' && $state === 'unavailable') {
            return __('Full album download not available yet', 'campwp');
        }

        if ($context === 'track') {
            if ($state === 'downloadable' && str_contains(strtolower($label), 'free')) {
                return __('Free download', 'campwp');
            }

            if ($state === 'downloadable' && $label === '') {
                return __('Download available', 'campwp');
            }
        }

        return $label;
    }

    private function buildArtistTitleHeading(string $artist, string $title): string
    {
        $artist = trim($artist);
        $title = trim($title);

        if ($artist === '') {
            return $title;
        }

        if ($title === '') {
            return $artist;
        }

        return sprintf('%s — %s', $artist, $title);
    }

    private function buildReleaseIdentityLine(string $releaseTypeLabel, string $releaseDate, string $labelName): string
    {
        $parts = [];

        $type = trim($releaseTypeLabel);
        if ($type !== '') {
            $parts[] = $type;
        }

        $year = $this->extractReleaseYear($releaseDate);
        if ($year !== '') {
            $parts[] = $year;
        }

        $label = trim($labelName);
        if ($label !== '') {
            $parts[] = $label;
        }

        return implode(' · ', $parts);
    }

    private function extractReleaseYear(string $releaseDate): string
    {
        if (preg_match('/\b(\d{4})\b/', $releaseDate, $matches) === 1) {
            return $matches[1];
        }

        $timestamp = strtotime($releaseDate);
        if ($timestamp === false) {
            return '';
        }

        return gmdate('Y', $timestamp);
    }

    private function normalizeCtaMessage(string $message, string $state, string $context): string
    {
        if ($context === 'release' && $state === 'unavailable') {
            return '';
        }

        if ($context === 'track' && str_contains(strtolower($message), 'download available')) {
            return '';
        }

        return $message;
    }

    private function normalizeCtaActionLabel(string $actionLabel, string $context): string
    {
        if ($context === 'track' && $actionLabel !== '') {
            return __('Download', 'campwp');
        }

        return $actionLabel;
    }
}

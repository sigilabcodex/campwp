<?php

declare(strict_types=1);

namespace CampWP\Frontend\Rendering;

use CampWP\Domain\Audio\TrackAudioFile;
use CampWP\Frontend\Data\AlbumViewDataProvider;

final class AlbumPageRenderer
{
    private AlbumViewDataProvider $dataProvider;

    public function __construct(AlbumViewDataProvider $dataProvider)
    {
        $this->dataProvider = $dataProvider;
    }

    public function render(\WP_Post $album, string $content): string
    {
        $data = $this->dataProvider->getAlbumViewData($album);

        ob_start();
        ?>
        <article class="campwp campwp-album" data-campwp-release-id="<?php echo esc_attr((string) $data['id']); ?>">
            <header class="campwp-release-header">
                <div class="campwp-release-hero">
                    <?php if ($data['cover_html'] !== '') : ?>
                        <figure class="campwp-release-cover"><?php echo wp_kses_post((string) $data['cover_html']); ?></figure>
                    <?php endif; ?>

                    <div class="campwp-release-summary">
                        <h1 class="campwp-release-title"><?php echo esc_html((string) $data['title']); ?></h1>
                        <?php if ($data['subtitle'] !== '') : ?>
                            <p class="campwp-release-subtitle"><?php echo esc_html((string) $data['subtitle']); ?></p>
                        <?php endif; ?>

                        <ul class="campwp-release-meta">
                            <?php if ($data['artist_display'] !== '') : ?>
                                <li><strong><?php esc_html_e('Artist', 'campwp'); ?>:</strong> <?php echo esc_html((string) $data['artist_display']); ?></li>
                            <?php endif; ?>
                            <li><strong><?php esc_html_e('Type', 'campwp'); ?>:</strong> <?php echo esc_html((string) $data['release_type_label']); ?></li>
                            <?php if ($data['release_date'] !== '') : ?>
                                <li><strong><?php esc_html_e('Release date', 'campwp'); ?>:</strong> <?php echo esc_html((string) $data['release_date']); ?></li>
                            <?php endif; ?>
                            <?php if ($data['label_name'] !== '') : ?>
                                <li><strong><?php esc_html_e('Label', 'campwp'); ?>:</strong> <?php echo esc_html((string) $data['label_name']); ?></li>
                            <?php endif; ?>
                        </ul>

                        <?php $this->renderCta((array) $data['cta'], __('Release download', 'campwp')); ?>
                    </div>
                </div>
            </header>

            <?php if ($content !== '') : ?>
                <section class="campwp-release-description">
                    <h2><?php esc_html_e('About this release', 'campwp'); ?></h2>
                    <?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                </section>
            <?php endif; ?>

            <?php if ($data['release_notes'] !== '' || $data['credits'] !== '') : ?>
                <section class="campwp-release-notes">
                    <h2><?php esc_html_e('Notes & credits', 'campwp'); ?></h2>
                    <?php if ($data['release_notes'] !== '') : ?>
                        <p><?php echo nl2br(esc_html((string) $data['release_notes'])); ?></p>
                    <?php endif; ?>
                    <?php if ($data['credits'] !== '') : ?>
                        <p><?php echo nl2br(esc_html((string) $data['credits'])); ?></p>
                    <?php endif; ?>
                </section>
            <?php endif; ?>

            <section class="campwp-track-list">
                <h2><?php esc_html_e('Track list', 'campwp'); ?></h2>

                <?php if ($data['tracks'] === []) : ?>
                    <p><?php esc_html_e('No published tracks are assigned to this release yet.', 'campwp'); ?></p>
                    <?php if ((int) ($data['unpublished_track_count'] ?? 0) > 0) : ?>
                        <p><?php echo esc_html(sprintf(_n('%d assigned track is still unpublished.', '%d assigned tracks are still unpublished.', (int) $data['unpublished_track_count'], 'campwp'), (int) $data['unpublished_track_count'])); ?></p>
                    <?php endif; ?>
                <?php else : ?>
                    <ol class="campwp-track-list-items">
                        <?php foreach ($data['tracks'] as $track) : ?>
                            <li class="campwp-track-row" data-campwp-track-id="<?php echo esc_attr((string) $track['id']); ?>">
                                <div class="campwp-track-primary">
                                    <?php if ($track['artwork_html'] !== '') : ?>
                                        <div class="campwp-track-artwork"><?php echo wp_kses_post((string) $track['artwork_html']); ?></div>
                                    <?php endif; ?>

                                    <div class="campwp-track-info">
                                        <div class="campwp-track-heading">
                                            <span class="campwp-track-number"><?php echo esc_html((string) $track['number']); ?>.</span>
                                            <a class="campwp-track-link" href="<?php echo esc_url((string) $track['permalink']); ?>"><?php echo esc_html((string) $track['title']); ?></a>
                                        </div>

                                        <?php if ($track['subtitle'] !== '') : ?>
                                            <p class="campwp-track-subtitle"><?php echo esc_html((string) $track['subtitle']); ?></p>
                                        <?php endif; ?>

                                        <p class="campwp-track-metadata">
                                            <?php if ($track['artist_display'] !== '') : ?>
                                                <span><strong><?php esc_html_e('Artist', 'campwp'); ?>:</strong> <?php echo esc_html((string) $track['artist_display']); ?></span>
                                            <?php endif; ?>
                                            <?php if ($track['duration'] !== '') : ?>
                                                <span><strong><?php esc_html_e('Duration', 'campwp'); ?>:</strong> <?php echo esc_html((string) $track['duration']); ?></span>
                                            <?php endif; ?>
                                        </p>
                                    </div>
                                </div>

                                <div class="campwp-track-secondary">
                                    <?php if ($track['audio'] instanceof TrackAudioFile) : ?>
                                        <audio controls preload="none">
                                            <source src="<?php echo esc_url($track['audio']->getUrl()); ?>" type="<?php echo esc_attr($track['audio']->getMimeType()); ?>" />
                                        </audio>
                                    <?php else : ?>
                                        <p class="campwp-empty-state"><?php esc_html_e('No audio attached for this track yet.', 'campwp'); ?></p>
                                    <?php endif; ?>

                                    <?php $this->renderCta((array) $track['cta'], __('Track download', 'campwp')); ?>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                <?php endif; ?>
            </section>

            <section class="campwp-bonus-assets">
                <h2><?php esc_html_e('Bonus attachments', 'campwp'); ?></h2>
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
                                <?php $this->renderCta((array) $asset['cta'], __('Bonus download', 'campwp')); ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </section>
        </article>
        <?php

        return (string) ob_get_clean();
    }

    /**
     * @param array<string, mixed> $cta
     */
    private function renderCta(array $cta, string $title): void
    {
        $state = (string) ($cta['state'] ?? '');
        $label = (string) ($cta['label'] ?? '');
        $message = (string) ($cta['message'] ?? '');
        $actionLabel = (string) ($cta['action_label'] ?? '');
        $actionUrl = (string) ($cta['action_url'] ?? '');
        ?>
        <section class="campwp-cta" data-campwp-cta-state="<?php echo esc_attr($state); ?>">
            <h3 class="campwp-cta-title"><?php echo esc_html($title); ?></h3>
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
}

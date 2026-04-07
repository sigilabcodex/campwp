<?php

declare(strict_types=1);

namespace CampWP\Frontend\Rendering;

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
                <h1 class="campwp-release-title"><?php echo esc_html((string) $data['title']); ?></h1>
                <?php if ($data['subtitle'] !== '') : ?>
                    <p class="campwp-release-subtitle"><?php echo esc_html((string) $data['subtitle']); ?></p>
                <?php endif; ?>

                <ul class="campwp-release-meta">
                    <li><strong><?php esc_html_e('Type', 'campwp'); ?>:</strong> <?php echo esc_html((string) $data['release_type_label']); ?></li>
                    <?php if ($data['artist_display'] !== '') : ?>
                        <li><strong><?php esc_html_e('Artist', 'campwp'); ?>:</strong> <?php echo esc_html((string) $data['artist_display']); ?></li>
                    <?php endif; ?>
                    <?php if ($data['release_date'] !== '') : ?>
                        <li><strong><?php esc_html_e('Release date', 'campwp'); ?>:</strong> <?php echo esc_html((string) $data['release_date']); ?></li>
                    <?php endif; ?>
                    <?php if ($data['label_name'] !== '') : ?>
                        <li><strong><?php esc_html_e('Label', 'campwp'); ?>:</strong> <?php echo esc_html((string) $data['label_name']); ?></li>
                    <?php endif; ?>
                </ul>
            </header>

            <?php if ($data['cover_html'] !== '') : ?>
                <figure class="campwp-release-cover"><?php echo wp_kses_post((string) $data['cover_html']); ?></figure>
            <?php endif; ?>

            <?php if ($content !== '') : ?>
                <section class="campwp-release-description">
                    <h2><?php esc_html_e('Description', 'campwp'); ?></h2>
                    <?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                </section>
            <?php endif; ?>

            <?php if ($data['release_notes'] !== '') : ?>
                <section class="campwp-release-notes">
                    <h2><?php esc_html_e('Release notes', 'campwp'); ?></h2>
                    <p><?php echo nl2br(esc_html((string) $data['release_notes'])); ?></p>
                </section>
            <?php endif; ?>

            <section class="campwp-track-list">
                <h2><?php esc_html_e('Tracks', 'campwp'); ?></h2>

                <?php if ($data['tracks'] === []) : ?>
                    <p><?php esc_html_e('No tracks assigned yet.', 'campwp'); ?></p>
                <?php else : ?>
                    <ol>
                        <?php foreach ($data['tracks'] as $track) : ?>
                            <li class="campwp-track-row" data-campwp-track-id="<?php echo esc_attr((string) $track['id']); ?>">
                                <div class="campwp-track-heading">
                                    <span class="campwp-track-number"><?php echo esc_html((string) $track['number']); ?>.</span>
                                    <a href="<?php echo esc_url((string) $track['permalink']); ?>"><?php echo esc_html((string) $track['title']); ?></a>
                                </div>
                                <?php if ($track['subtitle'] !== '') : ?>
                                    <p class="campwp-track-subtitle"><?php echo esc_html((string) $track['subtitle']); ?></p>
                                <?php endif; ?>

                                <?php if ($track['artist_display'] !== '') : ?>
                                    <p class="campwp-track-artist"><strong><?php esc_html_e('Artist', 'campwp'); ?>:</strong> <?php echo esc_html((string) $track['artist_display']); ?></p>
                                <?php endif; ?>

                                <?php if ($track['duration'] !== '') : ?>
                                    <p class="campwp-track-duration"><strong><?php esc_html_e('Duration', 'campwp'); ?>:</strong> <?php echo esc_html((string) $track['duration']); ?></p>
                                <?php endif; ?>

                                <?php if ($track['artwork_html'] !== '') : ?>
                                    <div class="campwp-track-artwork"><?php echo wp_kses_post((string) $track['artwork_html']); ?></div>
                                <?php endif; ?>

                                <?php if ($track['audio'] instanceof \CampWP\Domain\Audio\TrackAudioFile) : ?>
                                    <audio controls preload="none">
                                        <source src="<?php echo esc_url($track['audio']->getUrl()); ?>" type="<?php echo esc_attr($track['audio']->getMimeType()); ?>" />
                                    </audio>
                                <?php else : ?>
                                    <p><?php esc_html_e('No audio attached for this track.', 'campwp'); ?></p>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                <?php endif; ?>
            </section>

            <?php if ($data['bonus_assets'] !== []) : ?>
                <section class="campwp-bonus-assets">
                    <h2><?php esc_html_e('Bonus attachments', 'campwp'); ?></h2>
                    <ul>
                        <?php foreach ($data['bonus_assets'] as $asset) : ?>
                            <?php
                            $assetLabel = $asset->getReference()->getLabel();
                            $fallbackLabel = $asset->getAsset()->getTitle();
                            $displayLabel = $assetLabel !== '' ? $assetLabel : $fallbackLabel;
                            ?>
                            <li>
                                <a href="<?php echo esc_url($asset->getAsset()->getUrl()); ?>">
                                    <?php echo esc_html($displayLabel !== '' ? $displayLabel : __('Bonus file', 'campwp')); ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </section>
            <?php endif; ?>
        </article>
        <?php

        return (string) ob_get_clean();
    }
}

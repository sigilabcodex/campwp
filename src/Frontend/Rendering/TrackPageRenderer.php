<?php

declare(strict_types=1);

namespace CampWP\Frontend\Rendering;

use CampWP\Domain\Audio\TrackAudioFile;
use CampWP\Frontend\Data\TrackViewDataProvider;

final class TrackPageRenderer
{
    private TrackViewDataProvider $dataProvider;

    public function __construct(TrackViewDataProvider $dataProvider)
    {
        $this->dataProvider = $dataProvider;
    }

    public function render(\WP_Post $track, string $content): string
    {
        $data = $this->dataProvider->getTrackViewData($track);

        ob_start();
        ?>
        <article class="campwp campwp-track" data-campwp-track-id="<?php echo esc_attr((string) $data['id']); ?>">
            <header class="campwp-track-header">
                <h1 class="campwp-track-title"><?php echo esc_html((string) $data['title']); ?></h1>

                <?php if ($data['subtitle'] !== '') : ?>
                    <p class="campwp-track-subtitle"><?php echo esc_html((string) $data['subtitle']); ?></p>
                <?php endif; ?>

                <?php if ($data['artist_display'] !== '') : ?>
                    <p class="campwp-track-artist"><strong><?php esc_html_e('Artist', 'campwp'); ?>:</strong> <?php echo esc_html((string) $data['artist_display']); ?></p>
                <?php endif; ?>

                <?php if ($data['album'] !== null) : ?>
                    <p class="campwp-track-album">
                        <strong><?php esc_html_e('Album', 'campwp'); ?>:</strong>
                        <a href="<?php echo esc_url((string) $data['album']['permalink']); ?>"><?php echo esc_html((string) $data['album']['title']); ?></a>
                    </p>
                <?php endif; ?>
            </header>

            <?php if ($data['artwork_html'] !== '') : ?>
                <figure class="campwp-track-artwork"><?php echo wp_kses_post((string) $data['artwork_html']); ?></figure>
            <?php endif; ?>

            <section class="campwp-track-audio">
                <h2><?php esc_html_e('Playback', 'campwp'); ?></h2>
                <?php if ($data['audio'] instanceof TrackAudioFile) : ?>
                    <audio controls preload="none">
                        <source src="<?php echo esc_url($data['audio']->getUrl()); ?>" type="<?php echo esc_attr($data['audio']->getMimeType()); ?>" />
                    </audio>
                <?php else : ?>
                    <p><?php esc_html_e('No audio attached to this track.', 'campwp'); ?></p>
                <?php endif; ?>
            </section>


            <?php if (is_array($data['download']) && ($data['download']['enabled'] ?? false)) : ?>
                <section class="campwp-track-download">
                    <a class="button" href="<?php echo esc_url((string) $data['download']['url']); ?>"><?php esc_html_e('Download', 'campwp'); ?></a>
                    <p><em><?php echo esc_html((string) $data['download']['mode_label']); ?></em></p>
                </section>
            <?php endif; ?>

            <?php if ($data['credits'] !== '') : ?>
                <section class="campwp-track-credits">
                    <h2><?php esc_html_e('Credits', 'campwp'); ?></h2>
                    <p><?php echo nl2br(esc_html((string) $data['credits'])); ?></p>
                </section>
            <?php endif; ?>

            <?php if ($data['lyrics'] !== '') : ?>
                <section class="campwp-track-lyrics">
                    <h2><?php esc_html_e('Lyrics', 'campwp'); ?></h2>
                    <p><?php echo nl2br(esc_html((string) $data['lyrics'])); ?></p>
                </section>
            <?php endif; ?>

            <?php if ($content !== '') : ?>
                <section class="campwp-track-description">
                    <h2><?php esc_html_e('Description', 'campwp'); ?></h2>
                    <?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                </section>
            <?php endif; ?>
        </article>
        <?php

        return (string) ob_get_clean();
    }
}

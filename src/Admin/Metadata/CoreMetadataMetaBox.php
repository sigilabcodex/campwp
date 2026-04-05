<?php

declare(strict_types=1);

namespace CampWP\Admin\Metadata;

use CampWP\Domain\Audio\TrackAudioFile;
use CampWP\Domain\Audio\TrackAudioResolver;
use CampWP\Domain\Media\AlbumBonusAssetResolver;
use CampWP\Domain\Metadata\MetadataKeys;
use CampWP\Domain\Metadata\MetadataSanitizer;
use CampWP\Domain\Media\MediaStorageProviderInterface;
use CampWP\Infrastructure\Media\WordPressMediaLibraryProvider;

final class CoreMetadataMetaBox
{
    private const ALBUM_NONCE_ACTION = 'campwp_save_album_core_metadata';
    private const ALBUM_NONCE_NAME = 'campwp_album_core_metadata_nonce';
    private const TRACK_NONCE_ACTION = 'campwp_save_track_core_metadata';
    private const TRACK_NONCE_NAME = 'campwp_track_core_metadata_nonce';
    private const RELEASE_TYPE_OPTIONS = ['single', 'ep', 'album', 'compilation', 'other'];

    private MetadataSanitizer $sanitizer;

    private TrackAudioResolver $trackAudioResolver;

    private AlbumBonusAssetResolver $bonusAssetResolver;

    private MediaStorageProviderInterface $mediaProvider;

    public function __construct(?MetadataSanitizer $sanitizer = null, ?TrackAudioResolver $trackAudioResolver = null, ?AlbumBonusAssetResolver $bonusAssetResolver = null)
    {
        $this->sanitizer = $sanitizer ?? new MetadataSanitizer();
        $this->mediaProvider = new WordPressMediaLibraryProvider();
        $this->trackAudioResolver = $trackAudioResolver ?? new TrackAudioResolver($this->mediaProvider);
        $this->bonusAssetResolver = $bonusAssetResolver ?? new AlbumBonusAssetResolver($this->mediaProvider);
    }

    public function register(): void
    {
        add_action('add_meta_boxes', [$this, 'registerMetaBoxes']);
        add_action('save_post', [$this, 'saveMetadata'], 10, 2);
        add_action('admin_enqueue_scripts', [$this, 'enqueueMediaFieldAssets']);
    }

    public function registerMetaBoxes(): void
    {
        add_meta_box(
            'campwp-album-core-metadata',
            __('Album Metadata', 'campwp'),
            [$this, 'renderAlbumMetaBox'],
            $this->getAlbumPostType(),
            'normal',
            'default'
        );

        add_meta_box(
            'campwp-track-core-metadata',
            __('Track Metadata', 'campwp'),
            [$this, 'renderTrackMetaBox'],
            $this->getTrackPostType(),
            'normal',
            'default'
        );
    }

    /**
     * @param \WP_Post $post
     */
    public function renderAlbumMetaBox($post): void
    {
        wp_nonce_field(self::ALBUM_NONCE_ACTION, self::ALBUM_NONCE_NAME);

        $subtitle = $this->getMetaValue((int) $post->ID, MetadataKeys::ALBUM_SUBTITLE);
        $releaseDate = $this->getMetaValue((int) $post->ID, MetadataKeys::ALBUM_RELEASE_DATE);
        $catalogNumber = $this->getMetaValue((int) $post->ID, MetadataKeys::ALBUM_CATALOG_NUMBER);
        $artistDisplayName = $this->getMetaValue((int) $post->ID, MetadataKeys::ALBUM_ARTIST_DISPLAY);
        $creditsOverride = $this->getMetaValue((int) $post->ID, MetadataKeys::ALBUM_CREDITS_OVERRIDE);
        $labelName = $this->getMetaValue((int) $post->ID, MetadataKeys::ALBUM_LABEL_NAME);
        $releaseNotes = $this->getMetaValue((int) $post->ID, MetadataKeys::ALBUM_RELEASE_NOTES);
        $releaseType = $this->getMetaValue((int) $post->ID, MetadataKeys::ALBUM_RELEASE_TYPE);
        $releaseType = $releaseType !== '' ? $releaseType : 'album';

        echo '<p>' . esc_html__('Featured image is used as album cover art.', 'campwp') . '</p>';

        $this->renderTextField('campwp_album_metadata[subtitle]', __('Subtitle', 'campwp'), $subtitle);
        $this->renderDateField('campwp_album_metadata[release_date]', __('Release Date', 'campwp'), $releaseDate, true);
        $this->renderTextField('campwp_album_metadata[catalog_number]', __('Catalog Number', 'campwp'), $catalogNumber);
        $this->renderSelectField('campwp_album_metadata[release_type]', __('Release Type', 'campwp'), $releaseType, self::RELEASE_TYPE_OPTIONS);
        $this->renderTextField('campwp_album_metadata[artist_display_name]', __('Artist Display Name', 'campwp'), $artistDisplayName, true);
        $this->renderTextField('campwp_album_metadata[label_name]', __('Label Name', 'campwp'), $labelName);
        $this->renderTextareaField('campwp_album_metadata[credits_override]', __('Credits / Liner Notes Override', 'campwp'), $creditsOverride);
        $this->renderTextareaField('campwp_album_metadata[release_notes]', __('Release Notes', 'campwp'), $releaseNotes);
        $this->renderAlbumBonusAttachmentsField((int) $post->ID);

        echo '<p><em>' . esc_html__('Tracks can remain standalone when no album assignment is set. In v1, each track can belong to at most one album.', 'campwp') . '</em></p>';
    }

    /**
     * @param \WP_Post $post
     */
    public function renderTrackMetaBox($post): void
    {
        wp_nonce_field(self::TRACK_NONCE_ACTION, self::TRACK_NONCE_NAME);

        $trackNumber = (string) $this->getMetaIntegerValue((int) $post->ID, MetadataKeys::TRACK_NUMBER);
        $subtitle = $this->getMetaValue((int) $post->ID, MetadataKeys::TRACK_SUBTITLE);
        $duration = $this->getMetaValue((int) $post->ID, MetadataKeys::TRACK_DURATION);
        $artistDisplayName = $this->getMetaValue((int) $post->ID, MetadataKeys::TRACK_ARTIST_DISPLAY);
        $credits = $this->getMetaValue((int) $post->ID, MetadataKeys::TRACK_CREDITS);
        $lyrics = $this->getMetaValue((int) $post->ID, MetadataKeys::TRACK_LYRICS);
        $isrc = $this->getMetaValue((int) $post->ID, MetadataKeys::TRACK_ISRC);
        $artworkId = (string) $this->getMetaIntegerValue((int) $post->ID, MetadataKeys::TRACK_ARTWORK_ID);
        $audioAttachmentId = (string) $this->getMetaIntegerValue((int) $post->ID, MetadataKeys::TRACK_AUDIO_ATTACHMENT_ID);

        $this->renderNumberField('campwp_track_metadata[track_number]', __('Track Number', 'campwp'), $trackNumber, true);
        $this->renderTextField('campwp_track_metadata[subtitle]', __('Subtitle', 'campwp'), $subtitle);
        $this->renderTextField('campwp_track_metadata[duration]', __('Duration (MM:SS or HH:MM:SS)', 'campwp'), $duration);
        $this->renderTextField('campwp_track_metadata[artist_display_name]', __('Artist Display Override', 'campwp'), $artistDisplayName);
        $this->renderTextareaField('campwp_track_metadata[credits]', __('Credits', 'campwp'), $credits);
        $this->renderTextareaField('campwp_track_metadata[lyrics]', __('Lyrics', 'campwp'), $lyrics);
        $this->renderTextField('campwp_track_metadata[isrc]', __('ISRC', 'campwp'), $isrc);
        $this->renderNumberField('campwp_track_metadata[artwork_id]', __('Track Artwork Attachment ID', 'campwp'), $artworkId);
        $this->renderTrackAudioAttachmentField((int) $post->ID, $audioAttachmentId);
        echo '<p><em>' . esc_html__('Optional: store a Media Library attachment ID for track-specific artwork.', 'campwp') . '</em></p>';
    }

    /**
     * @param \WP_Post $post
     */
    public function saveMetadata(int $postId, $post): void
    {
        if ((defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) || wp_is_post_revision($postId) || wp_is_post_autosave($postId)) {
            return;
        }

        if (! current_user_can('edit_post', $postId)) {
            return;
        }

        if ($post->post_type === $this->getAlbumPostType()) {
            $this->saveAlbumMetadata($postId);
            return;
        }

        if ($post->post_type === $this->getTrackPostType()) {
            $this->saveTrackMetadata($postId);
        }
    }

    public function enqueueMediaFieldAssets(string $hookSuffix): void
    {
        if (! in_array($hookSuffix, ['post.php', 'post-new.php'], true)) {
            return;
        }

        $screen = get_current_screen();

        if (! $screen instanceof \WP_Screen || ! in_array($screen->post_type, [$this->getTrackPostType(), $this->getAlbumPostType()], true)) {
            return;
        }

        wp_enqueue_media();
        wp_enqueue_script('jquery');

        $script = <<<'JS'
(function($){
    'use strict';

    function readBonusItems($input) {
        var value = $input.val();

        if (!value) {
            return [];
        }

        try {
            var parsed = JSON.parse(value);
            return Array.isArray(parsed) ? parsed : [];
        } catch (error) {
            return [];
        }
    }

    function writeBonusItems($input, items) {
        $input.val(JSON.stringify(items));
    }

    function renderBonusList($container, items) {
        if (!Array.isArray(items) || items.length === 0) {
            $container.html('<p><em>No bonus media selected.</em></p>');
            return;
        }

        var html = '<ul>';

        items.forEach(function(item, index) {
            var label = item.label ? item.label : ('Attachment #' + item.reference_id);
            html += '<li>' +
                '<strong>' + label + '</strong> ' +
                '<code>#' + item.reference_id + '</code> ' +
                '<button type="button" class="button-link-delete campwp-bonus-remove" data-index="' + index + '">Remove</button>' +
                '</li>';
        });

        html += '</ul>';
        $container.html(html);
    }

    $(document).on('click', '.campwp-track-audio-select', function(event){
        event.preventDefault();

        var targetInputId = $(this).data('target-input');
        var $input = $('#' + targetInputId);

        if ($input.length === 0 || typeof wp === 'undefined' || typeof wp.media === 'undefined') {
            return;
        }

        var frame = wp.media({
            title: 'Select Track Audio',
            library: { type: 'audio' },
            button: { text: 'Use this audio' },
            multiple: false
        });

        frame.on('select', function(){
            var attachment = frame.state().get('selection').first().toJSON();

            if (attachment && attachment.id) {
                $input.val(attachment.id).trigger('change');
            }
        });

        frame.open();
    });

    $(document).on('click', '.campwp-track-audio-clear', function(event){
        event.preventDefault();

        var targetInputId = $(this).data('target-input');
        var $input = $('#' + targetInputId);

        if ($input.length) {
            $input.val('0').trigger('change');
        }
    });

    $(document).on('click', '.campwp-bonus-select', function(event){
        event.preventDefault();

        var targetInputId = $(this).data('target-input');
        var targetListId = $(this).data('target-list');
        var $input = $('#' + targetInputId);
        var $list = $('#' + targetListId);

        if ($input.length === 0 || $list.length === 0 || typeof wp === 'undefined' || typeof wp.media === 'undefined') {
            return;
        }

        var frame = wp.media({
            title: 'Select Bonus Media',
            button: { text: 'Use selected media' },
            multiple: true
        });

        frame.on('select', function(){
            var selection = frame.state().get('selection').toJSON();
            var existing = readBonusItems($input);
            var byKey = {};

            existing.forEach(function(item){
                byKey[item.type + ':' + item.reference_id] = item;
            });

            selection.forEach(function(attachment){
                if (!attachment || !attachment.id) {
                    return;
                }

                var item = {
                    type: 'wp_attachment',
                    reference_id: parseInt(attachment.id, 10),
                    label: attachment.title || ''
                };

                byKey[item.type + ':' + item.reference_id] = item;
            });

            var nextItems = Object.keys(byKey).map(function(key){
                return byKey[key];
            });

            writeBonusItems($input, nextItems);
            renderBonusList($list, nextItems);
        });

        frame.open();
    });

    $(document).on('click', '.campwp-bonus-clear', function(event){
        event.preventDefault();

        var targetInputId = $(this).data('target-input');
        var targetListId = $(this).data('target-list');
        var $input = $('#' + targetInputId);
        var $list = $('#' + targetListId);

        if ($input.length) {
            writeBonusItems($input, []);
        }

        if ($list.length) {
            renderBonusList($list, []);
        }
    });

    $(document).on('click', '.campwp-bonus-remove', function(event){
        event.preventDefault();

        var $button = $(this);
        var $container = $button.closest('.campwp-bonus-field');
        var $input = $container.find('.campwp-bonus-items-input');
        var $list = $container.find('.campwp-bonus-items-list');

        if ($input.length === 0 || $list.length === 0) {
            return;
        }

        var index = parseInt($button.data('index'), 10);
        var items = readBonusItems($input);

        if (Number.isNaN(index) || index < 0 || index >= items.length) {
            return;
        }

        items.splice(index, 1);
        writeBonusItems($input, items);
        renderBonusList($list, items);
    });
})(jQuery);
JS;

        wp_add_inline_script('jquery', $script);
    }

    private function saveAlbumMetadata(int $postId): void
    {
        if (! $this->isValidNonce(self::ALBUM_NONCE_NAME, self::ALBUM_NONCE_ACTION)) {
            return;
        }

        $rawValues = $_POST['campwp_album_metadata'] ?? [];
        if (! is_array($rawValues)) {
            $rawValues = [];
        }

        $values = wp_unslash($rawValues);

        $this->updateMeta($postId, MetadataKeys::ALBUM_SUBTITLE, $this->sanitizer->sanitizeText((string) ($values['subtitle'] ?? '')));
        $this->updateMeta($postId, MetadataKeys::ALBUM_RELEASE_DATE, $this->sanitizer->sanitizeReleaseDate((string) ($values['release_date'] ?? '')));
        $this->updateMeta($postId, MetadataKeys::ALBUM_CATALOG_NUMBER, $this->sanitizer->sanitizeText((string) ($values['catalog_number'] ?? '')));
        $this->updateMeta($postId, MetadataKeys::ALBUM_RELEASE_TYPE, $this->sanitizer->sanitizeReleaseType((string) ($values['release_type'] ?? 'album')));
        $this->updateMeta($postId, MetadataKeys::ALBUM_ARTIST_DISPLAY, $this->sanitizer->sanitizeText((string) ($values['artist_display_name'] ?? '')));
        $this->updateMeta($postId, MetadataKeys::ALBUM_LABEL_NAME, $this->sanitizer->sanitizeText((string) ($values['label_name'] ?? '')));
        $this->updateMeta($postId, MetadataKeys::ALBUM_CREDITS_OVERRIDE, $this->sanitizer->sanitizeTextarea((string) ($values['credits_override'] ?? '')));
        $this->updateMeta($postId, MetadataKeys::ALBUM_RELEASE_NOTES, $this->sanitizer->sanitizeTextarea((string) ($values['release_notes'] ?? '')));

        $bonusItemsRaw = $values['bonus_items'] ?? '[]';
        $sanitizedBonusItems = $this->validateBonusItemsBeforeSave($this->sanitizer->sanitizeBonusItems($bonusItemsRaw));
        $this->updateMeta($postId, MetadataKeys::ALBUM_BONUS_ITEMS, $sanitizedBonusItems);
    }

    private function saveTrackMetadata(int $postId): void
    {
        if (! $this->isValidNonce(self::TRACK_NONCE_NAME, self::TRACK_NONCE_ACTION)) {
            return;
        }

        $rawValues = $_POST['campwp_track_metadata'] ?? [];
        if (! is_array($rawValues)) {
            $rawValues = [];
        }

        $values = wp_unslash($rawValues);

        $this->updateMeta($postId, MetadataKeys::TRACK_NUMBER, $this->sanitizer->sanitizePositiveInteger((string) ($values['track_number'] ?? '0')));
        $this->updateMeta($postId, MetadataKeys::TRACK_SUBTITLE, $this->sanitizer->sanitizeText((string) ($values['subtitle'] ?? '')));
        $this->updateMeta($postId, MetadataKeys::TRACK_DURATION, $this->sanitizer->sanitizeDuration((string) ($values['duration'] ?? '')));
        $this->updateMeta($postId, MetadataKeys::TRACK_ARTIST_DISPLAY, $this->sanitizer->sanitizeText((string) ($values['artist_display_name'] ?? '')));
        $this->updateMeta($postId, MetadataKeys::TRACK_CREDITS, $this->sanitizer->sanitizeTextarea((string) ($values['credits'] ?? '')));
        $this->updateMeta($postId, MetadataKeys::TRACK_LYRICS, $this->sanitizer->sanitizeTextarea((string) ($values['lyrics'] ?? '')));
        $this->updateMeta($postId, MetadataKeys::TRACK_ISRC, $this->sanitizer->sanitizeIsrc((string) ($values['isrc'] ?? '')));
        $this->updateMeta($postId, MetadataKeys::TRACK_ARTWORK_ID, $this->sanitizer->sanitizeAttachmentId((string) ($values['artwork_id'] ?? '0')));

        $audioAttachmentId = $this->sanitizeTrackAudioAttachmentId((string) ($values['audio_attachment_id'] ?? '0'));
        $this->updateMeta($postId, MetadataKeys::TRACK_AUDIO_ATTACHMENT_ID, $audioAttachmentId);
        $this->hydrateTrackMetadataFromAudioIfEmpty($postId, $audioAttachmentId);
    }

    private function isValidNonce(string $nonceName, string $nonceAction): bool
    {
        if (! isset($_POST[$nonceName])) {
            return false;
        }

        $nonce = sanitize_text_field(wp_unslash((string) $_POST[$nonceName]));

        return wp_verify_nonce($nonce, $nonceAction) === 1;
    }

    /**
     * @param int|string $value
     */
    private function updateMeta(int $postId, string $metaKey, $value): void
    {
        if ($value === '' || $value === 0) {
            delete_post_meta($postId, $metaKey);
            return;
        }

        update_post_meta($postId, $metaKey, $value);
    }

    private function getMetaValue(int $postId, string $metaKey): string
    {
        return (string) get_post_meta($postId, $metaKey, true);
    }

    private function getMetaIntegerValue(int $postId, string $metaKey): int
    {
        return (int) get_post_meta($postId, $metaKey, true);
    }

    private function sanitizeTrackAudioAttachmentId(string $value): int
    {
        $attachmentId = $this->sanitizer->sanitizeAttachmentId($value);

        if ($attachmentId === 0) {
            return 0;
        }

        if (! $this->trackAudioResolver->isValidTrackAudioReference($attachmentId)) {
            return 0;
        }

        return $attachmentId;
    }

    private function renderTextField(string $name, string $label, string $value, bool $required = false): void
    {
        echo '<p>';
        echo '<label>';
        echo '<strong>' . esc_html($label) . '</strong><br />';
        echo '<input type="text" class="widefat" name="' . esc_attr($name) . '" value="' . esc_attr($value) . '"' . ($required ? ' required="required"' : '') . ' />';
        echo '</label>';
        echo '</p>';
    }

    private function renderDateField(string $name, string $label, string $value, bool $required = false): void
    {
        echo '<p>';
        echo '<label>';
        echo '<strong>' . esc_html($label) . '</strong><br />';
        echo '<input type="date" class="widefat" name="' . esc_attr($name) . '" value="' . esc_attr($value) . '"' . ($required ? ' required="required"' : '') . ' />';
        echo '</label>';
        echo '</p>';
    }

    private function renderNumberField(string $name, string $label, string $value, bool $required = false): void
    {
        echo '<p>';
        echo '<label>';
        echo '<strong>' . esc_html($label) . '</strong><br />';
        echo '<input type="number" min="0" step="1" class="widefat" name="' . esc_attr($name) . '" value="' . esc_attr($value) . '"' . ($required ? ' required="required"' : '') . ' />';
        echo '</label>';
        echo '</p>';
    }

    private function renderTrackAudioAttachmentField(int $postId, string $value): void
    {
        $fieldId = 'campwp-track-audio-attachment-id';
        $audioFile = $this->trackAudioResolver->getTrackAudioFile($postId);

        echo '<p>';
        echo '<label for="' . esc_attr($fieldId) . '">';
        echo '<strong>' . esc_html__('Track Audio Attachment ID', 'campwp') . '</strong><br />';
        echo '<input type="number" min="0" step="1" class="regular-text" id="' . esc_attr($fieldId) . '" name="campwp_track_metadata[audio_attachment_id]" value="' . esc_attr($value) . '" />';
        echo '</label> ';
        echo '<button type="button" class="button campwp-track-audio-select" data-target-input="' . esc_attr($fieldId) . '">' . esc_html__('Select Audio', 'campwp') . '</button> ';
        echo '<button type="button" class="button-link-delete campwp-track-audio-clear" data-target-input="' . esc_attr($fieldId) . '">' . esc_html__('Clear', 'campwp') . '</button>';
        echo '</p>';

        echo '<p><em>' . esc_html__('Choose a Media Library audio item. CAMPWP stores the attachment ID as the canonical reference.', 'campwp') . '</em></p>';

        if ($audioFile instanceof TrackAudioFile) {
            echo '<p>';
            echo '<strong>' . esc_html__('Current audio', 'campwp') . ':</strong> ';
            echo '<a href="' . esc_url($audioFile->getUrl()) . '" target="_blank" rel="noopener noreferrer">' . esc_html($audioFile->getUrl()) . '</a>';
            echo '<br /><code>' . esc_html($audioFile->getMimeType()) . '</code>';
            if ($audioFile->getFilePath() !== '') {
                echo '<br /><code>' . esc_html($audioFile->getFilePath()) . '</code>';
            }
            echo '</p>';
        }
    }

    private function renderAlbumBonusAttachmentsField(int $postId): void
    {
        $fieldId = 'campwp-album-bonus-items';
        $listId = 'campwp-album-bonus-items-list';
        $items = [];

        foreach ($this->bonusAssetResolver->getBonusReferences($postId) as $reference) {
            $items[] = $reference->toArray();
        }

        echo '<div class="campwp-bonus-field">';
        echo '<p><strong>' . esc_html__('Bonus Media Attachments', 'campwp') . '</strong></p>';
        echo '<input type="hidden" class="campwp-bonus-items-input" id="' . esc_attr($fieldId) . '" name="campwp_album_metadata[bonus_items]" value="' . esc_attr((string) wp_json_encode($items)) . '" />';
        echo '<p>';
        echo '<button type="button" class="button campwp-bonus-select" data-target-input="' . esc_attr($fieldId) . '" data-target-list="' . esc_attr($listId) . '">' . esc_html__('Select Bonus Media', 'campwp') . '</button> ';
        echo '<button type="button" class="button-link-delete campwp-bonus-clear" data-target-input="' . esc_attr($fieldId) . '" data-target-list="' . esc_attr($listId) . '">' . esc_html__('Clear All', 'campwp') . '</button>';
        echo '</p>';

        echo '<div id="' . esc_attr($listId) . '" class="campwp-bonus-items-list">';

        if ($items === []) {
            echo '<p><em>' . esc_html__('No bonus media selected.', 'campwp') . '</em></p>';
        } else {
            echo '<ul>';
            foreach ($items as $index => $item) {
                $label = $item['label'] !== '' ? (string) $item['label'] : sprintf('Attachment #%d', (int) $item['reference_id']);
                echo '<li><strong>' . esc_html($label) . '</strong> <code>#' . esc_html((string) $item['reference_id']) . '</code> ';
                echo '<button type="button" class="button-link-delete campwp-bonus-remove" data-index="' . esc_attr((string) $index) . '">' . esc_html__('Remove', 'campwp') . '</button>';
                echo '</li>';
            }
            echo '</ul>';
        }

        echo '</div>';
        echo '<p><em>' . esc_html__('Store one or more Media Library references (PDF/image/ZIP/video/etc.) for this release.', 'campwp') . '</em></p>';
        echo '</div>';
    }

    private function renderTextareaField(string $name, string $label, string $value): void
    {
        echo '<p>';
        echo '<label>';
        echo '<strong>' . esc_html($label) . '</strong><br />';
        echo '<textarea class="widefat" rows="6" name="' . esc_attr($name) . '">' . esc_textarea($value) . '</textarea>';
        echo '</label>';
        echo '</p>';
    }

    /**
     * @param list<string> $options
     */
    private function renderSelectField(string $name, string $label, string $value, array $options): void
    {
        echo '<p>';
        echo '<label>';
        echo '<strong>' . esc_html($label) . '</strong><br />';
        echo '<select class="widefat" name="' . esc_attr($name) . '">';

        foreach ($options as $option) {
            $label = $option === 'ep' ? 'EP' : ucfirst($option);
            echo '<option value="' . esc_attr($option) . '"' . selected($value, $option, false) . '>' . esc_html($label) . '</option>';
        }

        echo '</select>';
        echo '</label>';
        echo '</p>';
    }

    private function getAlbumPostType(): string
    {
        $postTypes = apply_filters('campwp_album_post_types', ['campwp_album']);

        if (! is_array($postTypes) || $postTypes === []) {
            return 'campwp_album';
        }

        $firstPostType = reset($postTypes);

        if (! is_string($firstPostType) || $firstPostType === '') {
            return 'campwp_album';
        }

        return sanitize_key($firstPostType);
    }

    private function getTrackPostType(): string
    {
        $postType = apply_filters('campwp_track_post_type', 'campwp_track');

        if (! is_string($postType) || $postType === '') {
            return 'campwp_track';
        }

        return sanitize_key($postType);
    }

    private function validateBonusItemsBeforeSave(string $value): string
    {
        $decoded = json_decode($value, true);

        if (! is_array($decoded)) {
            return '[]';
        }

        $validated = [];

        foreach ($decoded as $item) {
            if (! is_array($item)) {
                continue;
            }

            $type = isset($item['type']) ? sanitize_key((string) $item['type']) : '';
            $referenceId = isset($item['reference_id']) ? absint($item['reference_id']) : 0;
            $label = isset($item['label']) ? sanitize_text_field((string) $item['label']) : '';

            if ($type !== 'wp_attachment' || $referenceId <= 0) {
                continue;
            }

            if (! $this->mediaProvider->isValidReference($referenceId)) {
                continue;
            }

            $validated[$type . ':' . $referenceId] = [
                'type' => $type,
                'reference_id' => $referenceId,
                'label' => $label,
            ];
        }

        return (string) wp_json_encode(array_values($validated));
    }

    private function hydrateTrackMetadataFromAudioIfEmpty(int $postId, int $audioAttachmentId): void
    {
        if ($audioAttachmentId <= 0) {
            return;
        }

        $audioFile = $this->trackAudioResolver->getTrackAudioFile($postId);

        if (! $audioFile instanceof TrackAudioFile || $audioFile->getFilePath() === '') {
            return;
        }

        if (! function_exists('wp_read_audio_metadata')) {
            return;
        }

        $metadata = wp_read_audio_metadata($audioFile->getFilePath());

        if (! is_array($metadata)) {
            return;
        }

        $currentDuration = trim($this->getMetaValue($postId, MetadataKeys::TRACK_DURATION));
        if ($currentDuration === '' && isset($metadata['length_formatted']) && is_string($metadata['length_formatted'])) {
            $this->updateMeta($postId, MetadataKeys::TRACK_DURATION, $this->sanitizer->sanitizeDuration($metadata['length_formatted']));
        }

        $currentArtist = trim($this->getMetaValue($postId, MetadataKeys::TRACK_ARTIST_DISPLAY));
        if ($currentArtist === '' && isset($metadata['artist']) && is_string($metadata['artist'])) {
            $this->updateMeta($postId, MetadataKeys::TRACK_ARTIST_DISPLAY, $this->sanitizer->sanitizeText($metadata['artist']));
        }

        $post = get_post($postId);

        if (! $post instanceof \WP_Post) {
            return;
        }

        $currentTitle = trim((string) $post->post_title);
        $isTitleEmpty = $currentTitle === '' || strtolower($currentTitle) === 'auto draft';

        if ($isTitleEmpty && isset($metadata['title']) && is_string($metadata['title']) && trim($metadata['title']) !== '') {
            wp_update_post([
                'ID' => $postId,
                'post_title' => $this->sanitizer->sanitizeText($metadata['title']),
            ]);
        }
    }
}

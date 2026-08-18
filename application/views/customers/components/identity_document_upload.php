<?php
/**
 * Two-image identity document uploader used inside one identity row.
 * $idn may be NULL for a new row.
 */
$front_path = ($idn && ! empty($idn->document_path)) ? $idn->document_path : '';
$back_path  = ($idn && isset($idn->document_path_2) && ! empty($idn->document_path_2))
    ? $idn->document_path_2
    : '';
$front_url  = $front_path ? site_url('customers/identity_file/'.$idn->id.'/1') : '';
$back_url   = $back_path ? site_url('customers/identity_file/'.$idn->id.'/2') : '';
$front_kind = strtolower(pathinfo($front_path, PATHINFO_EXTENSION)) === 'pdf' ? 'pdf' : 'image';
?>
<div class="erp-form-field idn-document-field">
    <div class="idn-document-heading">
        <label>Document <span class="erp-muted idn-label-note">(2 images max or 1 PDF)</span></label>
        <button type="button" class="idn-add-second" data-idn-add-second
                aria-label="Add Image 2" aria-expanded="false" hidden>
            <i class="bi bi-plus-lg" aria-hidden="true"></i>
            <span>Image 2</span>
        </button>
    </div>

    <div class="idn-upload-widget" data-idn-upload>
        <div class="idn-upload-actions">
            <button type="button" class="erp-btn erp-btn-soft erp-btn-sm" data-idn-action="choose-all">
                <i class="bi bi-images" aria-hidden="true"></i> Add images
            </button>
            <button type="button" class="erp-btn erp-btn-soft erp-btn-sm" data-idn-action="camera-next">
                <i class="bi bi-camera" aria-hidden="true"></i> Take photo
            </button>
            <input class="idn-source-input" type="file" data-idn-source="multi" multiple
                   accept=".jpg,.jpeg,.png,.pdf,image/jpeg,image/png,application/pdf"
                   aria-label="Choose up to two identity document files">
            <input class="idn-source-input" type="file" data-idn-source="camera"
                   accept="image/jpeg,image/png" capture="environment"
                   aria-label="Take an identity document photo">
        </div>

        <div class="idn-upload-message" data-idn-message aria-live="polite" hidden></div>

        <div class="idn-document-slots">
            <?php foreach (array(
                1 => array('Image 1', $front_url, $front_kind),
                2 => array('Image 2', $back_url, 'image'),
            ) as $slot => $slot_data): ?>
                <?php $has_existing = $slot_data[1] !== ''; ?>
                <article class="idn-document-slot" data-idn-slot="<?= $slot ?>"
                         data-existing="<?= $has_existing ? '1' : '0' ?>"
                         data-existing-kind="<?= html_escape($slot_data[2]) ?>"
                         data-existing-url="<?= html_escape($slot_data[1]) ?>">
                    <div class="idn-slot-head">
                        <strong><?= html_escape($slot_data[0]) ?></strong>
                        <button type="button" class="idn-preview-remove" data-idn-action="clear"
                                <?= $has_existing ? '' : 'hidden' ?>
                                title="Remove <?= html_escape($slot_data[0]) ?>"
                                aria-label="Remove <?= html_escape($slot_data[0]) ?>">&times;</button>
                    </div>

                    <div class="idn-preview-box">
                        <img class="idn-preview-image" data-idn-preview alt="<?= html_escape($slot_data[0]) ?> preview"
                             <?= $has_existing && $slot_data[2] === 'image' ? 'src="'.html_escape($slot_data[1]).'"' : 'hidden' ?>>
                        <div class="idn-preview-placeholder" data-idn-placeholder
                             <?= $has_existing && $slot_data[2] === 'image' ? 'hidden' : '' ?>>
                            <i class="bi <?= $slot_data[2] === 'pdf' ? 'bi-file-earmark-pdf' : 'bi-card-image' ?>" aria-hidden="true"></i>
                            <span data-idn-filename><?= $has_existing ? 'Saved file' : 'No image' ?></span>
                        </div>
                    </div>

                    <input class="idn-slot-input" type="file"
                           name="identity_document_<?= $slot ?>[]"
                           data-idn-input
                           accept="<?= $slot === 1 ? '.jpg,.jpeg,.png,.pdf,image/jpeg,image/png,application/pdf' : '.jpg,.jpeg,.png,image/jpeg,image/png' ?>"
                           hidden>
                    <input type="hidden" name="identity_document_remove_<?= $slot ?>[]"
                           data-idn-remove-input value="0">

                    <div class="idn-slot-actions">
                        <button type="button" class="idn-mini-btn" data-idn-action="choose-slot">
                            <i class="bi bi-folder2-open" aria-hidden="true"></i>
                            <span data-idn-choose-label><?= $has_existing ? 'Replace' : 'Choose' ?></span>
                        </button>
                        <button type="button" class="idn-mini-btn" data-idn-action="camera-slot">
                            <i class="bi bi-camera" aria-hidden="true"></i> Camera
                        </button>
                        <button type="button" class="idn-mini-btn" data-idn-action="crop"
                                <?= $has_existing && $slot_data[2] === 'image' ? '' : 'disabled' ?>>
                            <i class="bi bi-crop" aria-hidden="true"></i> Crop
                        </button>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>

        <noscript>
            <style>.idn-slot-input[hidden] { display: block !important; width: 100%; margin-top: 8px; }</style>
            <p class="erp-muted">JavaScript is off. Use the file selector inside each image box.</p>
        </noscript>
    </div>
</div>

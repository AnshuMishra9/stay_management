<?php
/**
 * Reusable "Identity Proof" block (repeatable rows + Add More).
 * Used by the customer master (add/edit customer) AND the booking check-in form.
 *
 * Expects:
 *   $identity_types -> array  code => label  (aadhar => Aadhar Card, …)
 *   $identities     -> array  existing customer_identities rows (or empty)
 *
 * Requires on the page: assets/js/identity-rows.js  +  the .idn-* styles
 * (defined in assets/css/erp.css). The enclosing <form> must be
 * enctype="multipart/form-data" so the document files upload.
 */
?>
<!-- ===== Identity Proof (repeatable) ===== -->
<div class="erp-form-section">
    <div class="erp-section-title idn-title">
        <span>Identity Proof</span>
        <button type="button" id="idnAddMore" class="erp-btn erp-btn-soft erp-btn-sm">+ Add More</button>
    </div>

    <div id="identityRows">
        <?php
        // Existing identities when editing; at least one blank row otherwise.
        $rows = ! empty($identities) ? $identities : array(NULL);
        foreach ($rows as $idn):
            $rid  = $idn ? (int) $idn->id : '';
            $rtyp = $idn ? $idn->identity_type : '';
            $rnum = $idn ? $idn->identity_number : '';
            $rdoc = ($idn && ! empty($idn->document_path)) ? site_url('customers/identity_file/'.$idn->id) : '';
        ?>
        <div class="idn-row">
            <button type="button" class="idn-remove" title="Remove">&times;</button>
            <input type="hidden" name="identity_id[]" value="<?= html_escape($rid) ?>">
            <div class="idn-fields">
                <div class="erp-form-field">
                    <label>ID Proof Type</label>
                    <select class="erp-select" name="identity_type[]" data-search="never" data-placeholder="Select ID Proof">
                        <option value="">Select ID Proof</option>
                        <?php foreach ($identity_types as $tv => $tl): ?>
                            <option value="<?= html_escape($tv) ?>" <?= $rtyp === $tv ? 'selected' : '' ?>><?= html_escape($tl) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="erp-form-field">
                    <label>Identity Number</label>
                    <input class="erp-input" type="text" name="identity_number[]" maxlength="50" value="<?= html_escape($rnum) ?>">
                </div>
                <div class="erp-form-field">
                    <label>Document <span class="erp-muted" style="font-weight:400;">(jpg/png/pdf)</span></label>
                    <input class="erp-file" type="file" name="identity_document[]" accept=".jpg,.jpeg,.png,.pdf">
                    <?php if ($rdoc): ?>
                        <div class="erp-existing-file">
                            <a class="erp-doc-link" href="<?= $rdoc ?>" target="_blank">Current file — view</a>
                            <span class="erp-muted"> · upload to replace</span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Blank row template cloned by the "Add More" button -->
<template id="identityRowTpl">
    <div class="idn-row">
        <button type="button" class="idn-remove" title="Remove">&times;</button>
        <input type="hidden" name="identity_id[]" value="">
        <div class="idn-fields">
            <div class="erp-form-field">
                <label>ID Proof Type</label>
                <select class="erp-select" name="identity_type[]" data-search="never" data-placeholder="Select ID Proof">
                    <option value="">Select ID Proof</option>
                    <?php foreach ($identity_types as $tv => $tl): ?>
                        <option value="<?= html_escape($tv) ?>"><?= html_escape($tl) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="erp-form-field">
                <label>Identity Number</label>
                <input class="erp-input" type="text" name="identity_number[]" maxlength="50" value="">
            </div>
            <div class="erp-form-field">
                <label>Document <span class="erp-muted" style="font-weight:400;">(jpg/png/pdf)</span></label>
                <input class="erp-file" type="file" name="identity_document[]" accept=".jpg,.jpeg,.png,.pdf">
            </div>
        </div>
    </div>
</template>

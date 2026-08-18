<?php
/**
 * Reusable Identity Proof block for the customer and check-in forms.
 *
 * Expects:
 *   $identity_types -> array code => label
 *   $identities     -> existing customer_identities rows (or empty)
 *
 * The enclosing form must use enctype="multipart/form-data".
 */
?>
<div class="erp-form-section idn-section">
    <div class="erp-section-title idn-title">
        <span>Identity Proof</span>
        <button type="button" id="idnAddMore" class="erp-btn erp-btn-soft erp-btn-sm">+ Add More</button>
    </div>

    <?php if ( ! empty($identity_upload_error)): ?>
        <div class="erp-alert erp-alert-danger idn-server-error">
            <?= html_escape($identity_upload_error) ?>
        </div>
    <?php endif; ?>

    <div id="identityRows">
        <?php
        $rows = ! empty($identities) ? $identities : array(NULL);
        foreach ($rows as $idn):
            $rid  = $idn ? (int) $idn->id : '';
            $rtyp = $idn ? $idn->identity_type : '';
            $rnum = $idn ? $idn->identity_number : '';
        ?>
        <div class="idn-row">
            <button type="button" class="idn-remove" title="Remove identity proof" aria-label="Remove identity proof">&times;</button>
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
                <?php $this->load->view('customers/components/identity_document_upload', array('idn' => $idn)); ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<template id="identityRowTpl">
    <div class="idn-row">
        <button type="button" class="idn-remove" title="Remove identity proof" aria-label="Remove identity proof">&times;</button>
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
            <?php $this->load->view('customers/components/identity_document_upload', array('idn' => NULL)); ?>
        </div>
    </div>
</template>

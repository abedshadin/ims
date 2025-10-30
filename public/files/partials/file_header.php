<?php if (!isset($file) || $file === null): ?>
    <div class="alert alert-warning" role="alert">The selected file could not be found.</div>
<?php else: ?>
    <?php
    $vendorAddress = trim((string) ($file['vendor_address'] ?? ''));
    $bankName = (string) ($file['bank_name'] ?? '');
    $brandName = (string) ($file['brand'] ?? '');
    $fileReference = (string) ($file['file_name'] ?? '');
    if (is_array($bankReference) && isset($bankReference['code'])) {
        $bankReferenceCode = (string) $bankReference['code'];
    } else {
        $bankReferenceCode = (string) ($file['bank_reference'] ?? '');
    }
    ?>
    <div class="workspace-hero shadow-sm mb-4 p-2 round">
        <div class="workspace-hero__content">
            <div class="workspace-hero__row">
                <div>
                    <div class="workspace-chip">File Ref <strong><?php echo e($fileReference); ?></strong></div>
                    <h2 class="workspace-hero__title mt-3 mb-1"><?php echo e($file['vendor_name']); ?></h2>
                    <p class="workspace-hero__subtitle mb-0">
                        Bank: <strong><?php echo e($bankName); ?></strong>
                        &middot;
                        Brand: <strong><?php echo e($brandName); ?></strong>
                    </p>
                    <?php if ($vendorAddress !== ''): ?>
                        <p class="workspace-hero__subtitle mb-0 mt-2">Address: <?php echo e($vendorAddress); ?></p>
                    <?php endif; ?>
                </div>
                <div class="workspace-meta">
                    <div id="fileMetaCreated"><?php echo e($createdSummary); ?></div>
                    <div id="fileMetaUpdated"><?php echo e($updatedSummary); ?></div>
                </div>
            </div>
            <div class="workspace-hero__chips">
                <?php if ($bankReferenceCode !== ''): ?>
                    <span class="workspace-chip">Bank Ref <strong><?php echo e($bankReferenceCode); ?></strong></span>
                <?php endif; ?>
                <?php if (!empty($file['advising_bank_name'])): ?>
                    <span class="workspace-chip">Advising Bank <strong><?php echo e($file['advising_bank_name']); ?></strong></span>
                <?php endif; ?>
                <?php if (!empty($file['beneficiary_bank_name'])): ?>
                    <span class="workspace-chip">Beneficiary Bank <strong><?php echo e($file['beneficiary_bank_name']); ?></strong></span>
                <?php endif; ?>
            </div>
        </div>
    </div>
<?php endif; ?>

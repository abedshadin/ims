<?php if (!isset($file) || $file === null): ?>
    <div class="alert alert-warning" role="alert">The selected file could not be found.</div>
<?php else: ?>
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body p-4">
            <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3">
                <div>
                    <span class="badge text-bg-primary-subtle text-primary rounded-pill px-3 py-2">Reference <?php echo e($file['file_name']); ?></span>
                    <h2 class="h4 mt-3 mb-1"><?php echo e($file['vendor_name']); ?></h2>
                    <p class="text-muted mb-0">Bank: <span class="fw-semibold"><?php echo e($file['bank_name']); ?></span> &middot; Brand: <span class="fw-semibold"><?php echo e($file['brand']); ?></span></p>
                </div>
                <div class="text-lg-end text-muted">
                    <div class="small" id="fileMetaCreated"><?php echo e($createdSummary); ?></div>
                    <div class="small mt-1" id="fileMetaUpdated"><?php echo e($updatedSummary); ?></div>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

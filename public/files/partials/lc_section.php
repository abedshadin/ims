<?php
$lcNumber = $lcDetails['lc_number'] ?? '';
$lcDate = $lcDetails['lc_date'] ?? '';
$lcType = $lcDetails['lc_type'] ?? '';
$lcAmount = $lcDetails['lc_amount'] ?? '';
$subjectLine = $lcDetails['subject_line'] ?? '';
$subjectLineValue = $subjectLine !== '' ? $subjectLine : 'Opening L/C for Import';
$lcAmountFormatted = $lcDetails['lc_amount_formatted'] ?? '0.00';
$lcDateHuman = $lcDetails['lc_date_human'] ?? '';
$latestShipment = $lcDetails['latest_shipment_date'] ?? '';
$latestShipmentHuman = $lcDetails['latest_shipment_date_human'] ?? '';
$expiryDate = $lcDetails['expiry_date'] ?? '';
$expiryDateHuman = $lcDetails['expiry_date_human'] ?? '';
$hasLcDetails = $lcNumber !== '';
?>
<div class="workspace-section-card card">
    <div class="card-body">
        <div class="row g-4">
            <div class="col-xl-7">
                <h2 class="workspace-section-title mb-1">Record Letter of Credit Details</h2>
                <p class="workspace-section-subtitle mb-4">Capture the LC information that appears on bank documents and proforma correspondence.</p>
                <form id="lcForm" method="post" class="row g-3" novalidate>
                    <input type="hidden" name="file_token" value="<?php echo e($fileToken); ?>">
                    <div class="col-md-6">
                        <label class="form-label" for="lc_number">LC Number</label>
                        <input class="form-control" type="text" id="lc_number" name="lc_number" value="<?php echo e($lcNumber); ?>" placeholder="e.g. LC-2025-001" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="lc_type">LC Type</label>
                        <input class="form-control" type="text" id="lc_type" name="lc_type" value="<?php echo e($lcType); ?>" placeholder="e.g. Sight" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="subject_line">Subject Line</label>
                        <input class="form-control" type="text" id="subject_line" name="subject_line" value="<?php echo e($subjectLineValue); ?>" placeholder="e.g. Opening L/C for Import" data-default-value="Opening L/C for Import">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="lc_date">LC Date</label>
                        <input class="form-control" type="date" id="lc_date" name="lc_date" value="<?php echo e($lcDate); ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="lc_amount">LC Amount</label>
                        <div class="input-group">
                            <span class="input-group-text">$</span>
                            <input class="form-control" type="number" step="0.01" min="0" id="lc_amount" name="lc_amount" value="<?php echo e($lcAmount); ?>" placeholder="0.00" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="latest_shipment_date">Latest Date of Shipment</label>
                        <input class="form-control" type="date" id="latest_shipment_date" name="latest_shipment_date" value="<?php echo e($latestShipment); ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="expiry_date">Expiry Date</label>
                        <input class="form-control" type="date" id="expiry_date" name="expiry_date" value="<?php echo e($expiryDate); ?>" required>
                    </div>
                    <div class="col-12">
                        <div id="lcAlert" class="alert d-none" role="alert"></div>
                    </div>
                    <div class="col-12 d-flex gap-2">
                        <button class="btn btn-primary" type="submit">Save LC Details</button>
                        <a class="btn btn-outline-secondary" href="index.php">Back to Files</a>
                    </div>
                </form>
            </div>
            <div class="col-xl-5">
                <div class="workspace-side-card" id="lcSummary">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div>
                            <h3 class="h6 mb-1">Saved Details</h3>
                            <p class="text-muted small mb-0">Keep your LC information up to date for easy reference.</p>
                        </div>
                        <span class="badge">LC</span>
                    </div>
                    <dl class="row row-cols-1 gy-2 mb-0 small">
                        <div class="col">
                            <dt class="text-uppercase text-muted">LC Number</dt>
                            <dd class="fw-semibold mb-0" data-lc-field="lc_number"><?php echo $hasLcDetails ? e($lcNumber) : '—'; ?></dd>
                        </div>
                        <div class="col">
                            <dt class="text-uppercase text-muted">LC Type</dt>
                            <dd class="fw-semibold mb-0" data-lc-field="lc_type"><?php echo $hasLcDetails ? e($lcType) : '—'; ?></dd>
                        </div>
                        <div class="col">
                            <dt class="text-uppercase text-muted">Subject</dt>
                            <dd class="fw-semibold mb-0" data-lc-field="subject_line"><?php echo $hasLcDetails && $subjectLine !== '' ? e($subjectLine) : '—'; ?></dd>
                        </div>
                        <div class="col">
                            <dt class="text-uppercase text-muted">LC Date</dt>
                            <dd class="fw-semibold mb-0" data-lc-field="lc_date_human"><?php echo $hasLcDetails ? e($lcDateHuman ?: $lcDate) : '—'; ?></dd>
                        </div>
                        <div class="col">
                            <dt class="text-uppercase text-muted">Amount</dt>
                            <dd class="fw-semibold mb-0" data-lc-field="lc_amount">$<?php echo $hasLcDetails ? e($lcAmountFormatted) : '0.00'; ?></dd>
                        </div>
                        <div class="col">
                            <dt class="text-uppercase text-muted">Latest Shipment</dt>
                            <dd class="fw-semibold mb-0" data-lc-field="latest_shipment_date_human"><?php echo $hasLcDetails ? e($latestShipmentHuman ?: $latestShipment) : '—'; ?></dd>
                        </div>
                        <div class="col">
                            <dt class="text-uppercase text-muted">Expiry Date</dt>
                            <dd class="fw-semibold mb-0" data-lc-field="expiry_date_human"><?php echo $hasLcDetails ? e($expiryDateHuman ?: $expiryDate) : '—'; ?></dd>
                        </div>
                    </dl>
                    <p class="text-muted small mt-4 mb-0<?php echo $hasLcDetails ? ' d-none' : ''; ?>" data-lc-empty-message>No LC details have been saved yet.</p>
                </div>
            </div>
        </div>
    </div>
</div>

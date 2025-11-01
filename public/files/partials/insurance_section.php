<?php
$insuranceMoneyReceiptNo = $insuranceDetails['money_receipt_no'] ?? '';
$insuranceMoneyReceiptDate = $insuranceDetails['money_receipt_date'] ?? '';
$insuranceMoneyReceiptDateHuman = $insuranceDetails['money_receipt_date_human'] ?? '';
$insuranceExchangeRate = $insuranceDetails['exchange_rate'] ?? '';
$insuranceExchangeRateFormatted = $insuranceDetails['exchange_rate_formatted'] ?? '0.0000';
$insuranceValue = $insuranceDetails['insurance_value'] ?? '';
$insuranceValueFormatted = $insuranceDetails['insurance_value_formatted'] ?? '0.00';
$hasInsuranceDetails = $insuranceMoneyReceiptNo !== ''
    || $insuranceMoneyReceiptDate !== ''
    || $insuranceExchangeRate !== ''
    || $insuranceValue !== '';
?>
<div class="workspace-section-card card">
    <div class="card-body">
        <div class="row g-4">
            <div class="col-xl-7">
                <h2 class="workspace-section-title mb-1">Record Insurance Details</h2>
                <p class="workspace-section-subtitle mb-4">Save the insurance receipt information associated with this file.</p>
                <form id="insuranceForm" method="post" class="row g-3" novalidate>
                    <input type="hidden" name="file_token" value="<?php echo e($fileToken); ?>">
                    <div class="col-md-6">
                        <label class="form-label" for="money_receipt_no">Money Receipt No</label>
                        <input class="form-control" type="text" id="money_receipt_no" name="money_receipt_no" value="<?php echo e($insuranceMoneyReceiptNo); ?>" placeholder="e.g. MR-2025-001" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="money_receipt_date">Money Receipt Date</label>
                        <input class="form-control" type="date" id="money_receipt_date" name="money_receipt_date" value="<?php echo e($insuranceMoneyReceiptDate); ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="exchange_rate">Exchange Rate</label>
                        <input class="form-control" type="number" step="0.0001" min="0" id="exchange_rate" name="exchange_rate" value="<?php echo e($insuranceExchangeRate); ?>" placeholder="0.0000" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="insurance_value">Insurance Value</label>
                        <div class="input-group">
                            <span class="input-group-text">$</span>
                            <input class="form-control" type="number" step="0.01" min="0" id="insurance_value" name="insurance_value" value="<?php echo e($insuranceValue); ?>" placeholder="0.00" required>
                        </div>
                    </div>
                    <div class="col-12">
                        <div id="insuranceAlert" class="alert d-none" role="alert"></div>
                    </div>
                    <div class="col-12 d-flex gap-2">
                        <button class="btn btn-primary" type="submit">Save Insurance Details</button>
                        <a class="btn btn-outline-secondary" href="index.php">Back to Files</a>
                    </div>
                </form>
            </div>
            <div class="col-xl-5">
                <div class="workspace-side-card" id="insuranceSummary">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div>
                            <h3 class="h6 mb-1">Saved Details</h3>
                            <p class="text-muted small mb-0">Quickly reference the latest insurance information.</p>
                        </div>
                        <span class="badge">Insurance</span>
                    </div>
                    <dl class="row row-cols-1 gy-2 mb-0 small">
                        <div class="col">
                            <dt class="text-uppercase text-muted">Money Receipt No</dt>
                            <dd class="fw-semibold mb-0" data-insurance-field="money_receipt_no"><?php echo $hasInsuranceDetails ? e($insuranceMoneyReceiptNo) : '—'; ?></dd>
                        </div>
                        <div class="col">
                            <dt class="text-uppercase text-muted">Money Receipt Date</dt>
                            <dd class="fw-semibold mb-0" data-insurance-field="money_receipt_date_human"><?php echo $hasInsuranceDetails ? e($insuranceMoneyReceiptDateHuman ?: $insuranceMoneyReceiptDate) : '—'; ?></dd>
                        </div>
                        <div class="col">
                            <dt class="text-uppercase text-muted">Exchange Rate</dt>
                            <dd class="fw-semibold mb-0" data-insurance-field="exchange_rate"><?php echo $hasInsuranceDetails ? e($insuranceExchangeRateFormatted) : '0.0000'; ?></dd>
                        </div>
                        <div class="col">
                            <dt class="text-uppercase text-muted">Insurance Value</dt>
                            <dd class="fw-semibold mb-0" data-insurance-field="insurance_value">$<?php echo $hasInsuranceDetails ? e($insuranceValueFormatted) : '0.00'; ?></dd>
                        </div>
                    </dl>
                    <p class="text-muted small mt-4 mb-0<?php echo $hasInsuranceDetails ? ' d-none' : ''; ?>" data-insurance-empty-message>No insurance details have been saved yet.</p>
                </div>
            </div>
        </div>
    </div>
</div>

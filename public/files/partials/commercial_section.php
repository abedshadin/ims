<?php
/** @var array<int, array<string, mixed>> $proformas */
/** @var array<int, array<string, mixed>> $commercialInvoices */
/** @var string $fileToken */

$hasProformas = !empty($proformas);
$hasCommercialInvoices = !empty($commercialInvoices);
?>
<div class="workspace-section-card card shadow-sm border-0 mb-4">
    <div class="card-header bg-white border-0 pb-0">
        <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-2">
            <div>
                <h2 class="h5 mb-1">Create a Commercial Invoice</h2>
                <p class="text-muted mb-0">Start from an existing proforma invoice and adjust the final shipment values before submission.</p>
            </div>
        </div>
    </div>
    <div class="card-body pt-3">
        <form id="createCiForm" class="row gy-3" method="post" novalidate>
            <input type="hidden" name="file_token" value="<?php echo e($fileToken); ?>">
            <div class="col-lg-4">
                <label class="form-label text-uppercase small fw-semibold" for="ci_proforma_token">Base Proforma Invoice</label>
                <select class="form-select" id="ci_proforma_token" name="proforma_token" <?php echo $hasProformas ? '' : 'disabled'; ?> required>
                    <option value="">Select a proforma invoice</option>
                    <?php foreach ($proformas as $proforma): ?>
                        <?php $token = (string) ($proforma['token'] ?? ''); ?>
                        <?php if ($token === '') { continue; } ?>
                        <option value="<?php echo e($token); ?>">PI <?php echo e($proforma['invoice_number'] ?? ''); ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if (!$hasProformas): ?>
                    <div class="form-text text-danger">Add a proforma invoice first to create a commercial invoice.</div>
                <?php endif; ?>
            </div>
            <div class="col-lg-4">
                <label class="form-label text-uppercase small fw-semibold" for="ci_invoice_number">Commercial Invoice Number</label>
                <input class="form-control" type="text" id="ci_invoice_number" name="invoice_number" placeholder="e.g. CI-2025-001" <?php echo $hasProformas ? '' : 'disabled'; ?> required>
            </div>
            <div class="col-lg-3">
                <label class="form-label text-uppercase small fw-semibold" for="ci_invoice_date">Invoice Date</label>
                <input class="form-control" type="date" id="ci_invoice_date" name="invoice_date" value="<?php echo e(date('Y-m-d')); ?>" <?php echo $hasProformas ? '' : 'disabled'; ?> required>
            </div>
            <div class="col-lg-1 d-flex align-items-end">
                <button class="btn btn-primary w-100" type="submit" <?php echo $hasProformas ? '' : 'disabled'; ?>>Create</button>
            </div>
        </form>
        <div id="ciAlert" class="alert d-none mt-3" role="alert"></div>
    </div>
</div>

<div id="ciList" class="row gy-4 mb-4">
    <?php foreach ($commercialInvoices as $invoice): ?>
        <?php
        $ciToken = (string) ($invoice['token'] ?? '');
        $invoiceNumber = (string) ($invoice['invoice_number'] ?? '');
        $invoiceDateValue = (string) ($invoice['invoice_date'] ?? '');
        $invoiceDateDisplay = (string) ($invoice['invoice_date_formatted'] ?? '');
        $totalValueDisplay = (string) ($invoice['total_value_formatted'] ?? ($invoice['total_value'] ?? '0.00'));
        $createdAt = (string) ($invoice['created_at_human'] ?? '');
        $proformaData = is_array($invoice['proforma'] ?? null) ? $invoice['proforma'] : [];
        $proformaLabel = (string) ($proformaData['invoice_number'] ?? '');
        $proformaDisplay = $proformaLabel !== '' ? 'PI ' . $proformaLabel : '';
        $products = is_array($invoice['products'] ?? null) ? $invoice['products'] : [];
        ?>
        <div class="col-12">
            <div class="workspace-section-card card shadow-sm border-0" data-ci-token="<?php echo e($ciToken); ?>">
                <div class="card-header bg-white border-0 pb-0">
                    <div class="d-flex flex-column flex-xl-row justify-content-between align-items-start align-items-xl-center gap-4">
                        <div class="flex-grow-1">
                            <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                                <span class="badge rounded-pill text-bg-success-subtle text-success fw-semibold">CI</span>
                                <h2 class="h5 mb-0">Commercial <?php echo e($invoiceNumber); ?></h2>
                                <span class="badge text-bg-light text-primary-emphasis">Total $<?php echo e($totalValueDisplay); ?></span>
                                <?php if ($proformaDisplay !== ''): ?>
                                    <span class="badge text-bg-secondary-subtle text-secondary">Based on <?php echo e($proformaDisplay); ?></span>
                                <?php endif; ?>
                                <?php if ($invoiceDateDisplay !== ''): ?>
                                    <span class="badge text-bg-info-subtle text-info">Dated <?php echo e($invoiceDateDisplay); ?></span>
                                <?php endif; ?>
                            </div>
                            <p class="text-muted small mb-0">Created <?php echo e($createdAt); ?></p>
                        </div>
                        <?php
                        $proformaToken = (string) ($proformaData['token'] ?? '');
                        if ($proformaToken !== ''):
                        ?>
                            <div class="workspace-ci-actions d-flex flex-column flex-sm-row flex-wrap gap-2 w-100 w-xl-auto justify-content-xl-end">
                                <button class="btn btn-primary flex-fill" type="button" data-action="add-product" data-ci-token="<?php echo e($ciToken); ?>" data-pi-token="<?php echo e($proformaToken); ?>">Add Product</button>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card-body pt-4" data-ci-container>
                    <div class="alert d-none" data-ci-message role="alert"></div>
                    <form class="ci-form" data-ci-form data-ci-token="<?php echo e($ciToken); ?>" novalidate>
                        <div class="row g-3 align-items-end">
                            <div class="col-lg-4">
                                <label class="form-label text-uppercase small fw-semibold">Commercial Invoice Number</label>
                                <input class="form-control form-control-sm" type="text" name="invoice_number" value="<?php echo e($invoiceNumber); ?>" required>
                            </div>
                            <div class="col-lg-3">
                                <label class="form-label text-uppercase small fw-semibold">Invoice Date</label>
                                <input class="form-control form-control-sm" type="date" name="invoice_date" value="<?php echo e($invoiceDateValue); ?>" required>
                            </div>
                            <div class="col-lg-3">
                                <label class="form-label text-uppercase small fw-semibold">Base Proforma</label>
                                <input class="form-control form-control-sm" type="text" value="<?php echo e($proformaDisplay !== '' ? $proformaDisplay : 'Not linked'); ?>" readonly>
                            </div>
                            <div class="col-lg-2 d-flex align-items-end">
                                <button class="btn btn-outline-secondary w-100" type="submit" data-ci-submit>Save Changes</button>
                            </div>
                        </div>

                        <div class="workspace-stat-grid mt-3">
                            <div class="workspace-stat">
                                <span class="workspace-stat-label">Products</span>
                                <span class="workspace-stat-value"><?php echo e(count($products)); ?></span>
                            </div>
                            <div class="workspace-stat">
                                <span class="workspace-stat-label">Invoice Date</span>
                                <span class="workspace-stat-value"><?php echo e($invoiceDateDisplay !== '' ? $invoiceDateDisplay : 'Not set'); ?></span>
                            </div>
                            <div class="workspace-stat">
                                <span class="workspace-stat-label">Total Invoice Value</span>
                                <span class="workspace-stat-value">$<?php echo e($totalValueDisplay); ?></span>
                            </div>
                        </div>

                        <div class="table-responsive mt-4">
                            <table class="table table-sm table-hover align-middle mb-0 workspace-product-table">
                                <thead class="table-light">
                                <tr>
                                    <th scope="col">Product</th>
                                    <th scope="col">Category &amp; Details</th>
                                    <th scope="col" class="text-end">Final Qty</th>
                                    <th scope="col" class="text-end">Final Unit Price</th>
                                    <th scope="col" class="text-end">Total Item Price</th>
                                    <th scope="col" class="text-end">Unit Freight</th>
                                    <th scope="col" class="text-end">Total Freight</th>
                                    <th scope="col" class="text-end">Item Weight</th>
                                    <th scope="col" class="text-end">Total Weight</th>
                                    <th scope="col" class="text-end">Total C&amp;F</th>
                                    <th scope="col" class="text-end">Total Invoice Value</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php if (!empty($products)): ?>
                                    <?php foreach ($products as $product): ?>
                                        <?php
                                        $productToken = (string) ($product['token'] ?? '');
                                        if ($productToken === '') {
                                            continue;
                                        }
                                        $productName = (string) ($product['product_name'] ?? 'Unnamed Product');
                                        $brand = (string) ($product['brand'] ?? '');
                                        $category = (string) ($product['product_category'] ?? '');
                                        $country = (string) ($product['country_of_origin'] ?? '');
                                        $size = (string) ($product['product_size'] ?? '');
                                        $unit = (string) ($product['unit'] ?? '');
                                        $hsCode = (string) ($product['hs_code'] ?? '');
                                        $finalQuantity = (string) ($product['final_quantity'] ?? '0');
                                        $finalUnitPrice = (string) ($product['final_unit_price'] ?? '0.00');
                                        $totalItemPrice = (string) ($product['total_item_price'] ?? '0.00');
                                        $unitFreight = (string) ($product['unit_freight'] ?? '0.0000');
                                        $totalFreight = (string) ($product['total_freight'] ?? '0.00');
                                        $itemWeight = (string) ($product['item_weight'] ?? '0');
                                        $totalWeight = (string) ($product['total_weight'] ?? '0');
                                        $totalCnfValue = (string) ($product['total_cnf_value'] ?? '0.00');
                                        $invoiceTotal = (string) ($product['invoice_total'] ?? '0.00');
                                        ?>
                                        <tr data-product-token="<?php echo e($productToken); ?>">
                                            <td>
                                                <div class="fw-semibold"><?php echo e($productName); ?></div>
                                                <?php if ($brand !== ''): ?><div class="text-muted small"><?php echo e($brand); ?></div><?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($category !== ''): ?><div class="text-muted small"><?php echo e($category); ?></div><?php endif; ?>
                                                <?php if ($country !== ''): ?><div class="text-muted small">Origin: <?php echo e($country); ?></div><?php endif; ?>
                                                <?php if ($size !== ''): ?><div class="text-muted small">Size: <?php echo e($size); ?></div><?php endif; ?>
                                                <?php if ($unit !== ''): ?><div class="text-muted small">Unit: <?php echo e($unit); ?></div><?php endif; ?>
                                                <?php if ($hsCode !== ''): ?><div class="text-muted small">HS: <?php echo e($hsCode); ?></div><?php endif; ?>
                                            </td>
                                            <td class="text-end">
                                                <input class="form-control form-control-sm text-end" type="number" step="0.001" min="0" name="products[<?php echo e($productToken); ?>][final_quantity]" value="<?php echo e($finalQuantity); ?>" required>
                                            </td>
                                            <td class="text-end">
                                                <input class="form-control form-control-sm text-end" type="number" step="0.01" min="0" name="products[<?php echo e($productToken); ?>][final_unit_price]" value="<?php echo e($finalUnitPrice); ?>" required>
                                            </td>
                                            <td class="text-end">
                                                <input class="form-control form-control-sm text-end" type="number" step="0.01" min="0" name="products[<?php echo e($productToken); ?>][total_item_price]" value="<?php echo e($totalItemPrice); ?>" required>
                                            </td>
                                            <td class="text-end">
                                                <input class="form-control form-control-sm text-end" type="number" step="0.0001" min="0" name="products[<?php echo e($productToken); ?>][unit_freight]" value="<?php echo e($unitFreight); ?>" required>
                                            </td>
                                            <td class="text-end">
                                                <input class="form-control form-control-sm text-end" type="number" step="0.01" min="0" name="products[<?php echo e($productToken); ?>][total_freight]" value="<?php echo e($totalFreight); ?>" required>
                                            </td>
                                            <td class="text-end">
                                                <input class="form-control form-control-sm text-end" type="number" step="0.001" min="0" name="products[<?php echo e($productToken); ?>][item_weight]" value="<?php echo e($itemWeight); ?>" required>
                                            </td>
                                            <td class="text-end">
                                                <input class="form-control form-control-sm text-end" type="number" step="0.001" min="0" name="products[<?php echo e($productToken); ?>][total_weight]" value="<?php echo e($totalWeight); ?>" required>
                                            </td>
                                            <td class="text-end">
                                                <input class="form-control form-control-sm text-end" type="number" step="0.01" min="0" name="products[<?php echo e($productToken); ?>][total_cnf_value]" value="<?php echo e($totalCnfValue); ?>" required>
                                            </td>
                                            <td class="text-end">
                                                <input class="form-control form-control-sm text-end" type="number" step="0.01" min="0" name="products[<?php echo e($productToken); ?>][invoice_total]" value="<?php echo e($invoiceTotal); ?>" required>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td class="text-center text-muted py-4" colspan="11">No products available for this commercial invoice.</td>
                                    </tr>
                                <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div id="noCiMessage" class="alert alert-info<?php echo $hasCommercialInvoices ? ' d-none' : ''; ?>" role="alert">
    No commercial invoices have been created yet.
</div>

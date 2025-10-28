<div class="workspace-section-card card shadow-sm border-0 mb-4">
    <div class="card-header bg-white border-0 pb-0">
        <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-2">
            <div>
                <h2 class="h5 mb-1">Add a Proforma Invoice</h2>
                <p class="text-muted mb-0">Capture the headline details below, then manage freight, products, and references inside each card.</p>
            </div>
        </div>
    </div>
    <div class="card-body pt-3">
        <form id="createPiForm" class="row gy-3" method="post" novalidate>
            <input type="hidden" name="file_token" value="<?php echo e($fileToken); ?>">
            <div class="col-lg-4">
                <label class="form-label text-uppercase small fw-semibold" for="invoice_number">Proforma Invoice Number</label>
                <input class="form-control" type="text" id="invoice_number" name="invoice_number" placeholder="e.g. PI-2025-001" required>
            </div>
            <div class="col-lg-4">
                <label class="form-label text-uppercase small fw-semibold" for="pi_header">PI Header</label>
                <input class="form-control" type="text" id="pi_header" name="pi_header" placeholder="e.g. Frozen Fries">
            </div>
            <div class="col-lg-2">
                <label class="form-label text-uppercase small fw-semibold" for="freight_amount">Freight Amount</label>
                <div class="input-group">
                    <span class="input-group-text">$</span>
                    <input class="form-control" type="number" step="0.01" id="freight_amount" name="freight_amount" placeholder="0.00">
                </div>
            </div>
            <div class="col-lg-2 d-flex align-items-end">
                <button class="btn btn-primary w-100" type="submit">Add Proforma Invoice</button>
            </div>
        </form>
        <div id="piAlert" class="alert d-none mt-3" role="alert"></div>
    </div>
</div>

<div id="piList" class="row gy-4 mb-4">
    <?php foreach ($proformas as $proforma): ?>
        <?php
        $piToken = (string) ($proforma['token'] ?? '');
        $products = is_array($proforma['products'] ?? null) ? $proforma['products'] : [];
        $freightAmount = (float) ($proforma['freight_amount'] ?? 0);
        $totalWeight = 0.0;
        $totalFob = 0.0;
        $lines = [];

        foreach ($products as $product) {
            $quantity = (float) ($product['quantity'] ?? 0);
            $fobTotal = (float) ($product['fob_total'] ?? 0);
            $itemWeight = (float) ($product['item_weight'] ?? 0);
            $lineWeight = $itemWeight * $quantity;

            $totalWeight += $lineWeight;
            $totalFob += $fobTotal;

            $lines[] = [
                'product' => $product,
                'quantity' => $quantity,
                'fob_total' => $fobTotal,
                'item_weight' => $itemWeight,
                'line_weight' => $lineWeight,
            ];
        }

        $freightPerWeight = $totalWeight > 0 ? $freightAmount / $totalWeight : 0.0;
        $totalCnf = 0.0;

        foreach ($lines as $index => $line) {
            $quantity = $line['quantity'];
            $fobTotal = $line['fob_total'];
            $itemWeight = $line['item_weight'];
            $freightPerUnit = $itemWeight * $freightPerWeight;
            $fobPerUnit = $quantity > 0 ? $fobTotal / $quantity : 0.0;
            $freightShare = $freightPerUnit * $quantity;
            $cnfPerUnit = $freightPerUnit + $fobPerUnit;
            $cnfTotal = $cnfPerUnit * $quantity;
            $totalCnf += $cnfTotal;

            $lines[$index]['freight_per_unit'] = $freightPerUnit;
            $lines[$index]['freight_share'] = $freightShare;
            $lines[$index]['fob_per_unit'] = $fobPerUnit;
            $lines[$index]['cnf_per_unit'] = $cnfPerUnit;
            $lines[$index]['cnf_total'] = $cnfTotal;
        }

        $totalWeightDisplay = rtrim(rtrim(number_format($totalWeight, 3, '.', ''), '0'), '.') ?: '0';
        $freightPerWeightDisplay = $totalWeight > 0 ? number_format($freightPerWeight, 4) : '0.0000';
        $totalFobDisplay = number_format($totalFob, 2);
        $totalCnfDisplay = number_format($totalCnf, 2);
        $productCount = count($lines);
        $piHeaderValue = (string) ($proforma['pi_header'] ?? '');
        $reference = is_array($proforma['reference'] ?? null) ? $proforma['reference'] : null;
        $referenceCode = (string) ($reference['code'] ?? '');
        $referenceDate = (string) ($reference['date'] ?? '');
        $referenceDateFormatted = $referenceDate !== '' ? date('j M Y', strtotime($referenceDate)) : null;
        ?>
        <div class="col-12">
            <div class="workspace-section-card card shadow-sm border-0" data-pi-token="<?php echo e($piToken); ?>">
                <div class="card-header bg-white border-0 pb-0">
                    <div class="d-flex flex-column flex-xl-row justify-content-between align-items-start align-items-xl-center gap-4">
                        <div class="flex-grow-1">
                            <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                                <span class="badge rounded-pill text-bg-primary-subtle text-primary fw-semibold">PI</span>
                                <h2 class="h5 mb-0">Proforma <?php echo e($proforma['invoice_number'] ?? ''); ?></h2>
                                <span class="badge text-bg-light text-primary-emphasis">Freight $<?php echo e(number_format($freightAmount, 2)); ?></span>
                                <?php if ($piHeaderValue !== ''): ?>
                                    <span class="badge text-bg-info-subtle text-info">Header: <?php echo e($piHeaderValue); ?></span>
                                <?php endif; ?>
                            </div>
                            <p class="text-muted small mb-0">Created <?php echo e($proforma['created_at_human'] ?? ''); ?></p>
                        </div>
                        <div class="workspace-pi-actions d-flex flex-column flex-sm-row flex-wrap gap-2 w-100 w-xl-auto justify-content-xl-end">
                            <button class="btn btn-outline-primary flex-fill" type="button" data-action="print-cnf" data-pi-token="<?php echo e($piToken); ?>">C&amp;F Calc Print &amp; Preview</button>
                            <button class="btn btn-outline-primary flex-fill" type="button" data-action="print-bank-forwarding" data-pi-token="<?php echo e($piToken); ?>">Bank Forwarding Print &amp; Preview</button>
                            <button class="btn btn-outline-primary flex-fill" type="button" data-action="print-toc" data-pi-token="<?php echo e($piToken); ?>">ToC Print &amp; Preview</button>
                            <button class="btn btn-primary flex-fill" type="button" data-action="add-product" data-pi-token="<?php echo e($piToken); ?>">Add Product</button>
                        </div>
                    </div>
                </div>
                <div class="card-body pt-4">
                    <div class="workspace-inline-control border rounded-3 p-3 d-flex flex-column flex-md-row align-items-md-center gap-3">
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <div class="input-group input-group-sm">
                                <span class="input-group-text">$</span>
                                <input class="form-control" type="number" step="0.01" value="<?php echo e(number_format($freightAmount, 2, '.', '')); ?>" data-freight-input>
                            </div>
                            <button class="btn btn-outline-primary btn-sm" type="button" data-action="save-freight" data-pi-token="<?php echo e($piToken); ?>">Save Freight</button>
                        </div>
                        <small class="text-muted">Freight is automatically distributed by weight when calculating C&amp;F totals.</small>
                    </div>

                    <div class="card bg-light border-0 mt-3">
                        <div class="card-body py-3">
                            <div class="row g-3 align-items-end">
                                <div class="col-lg-4">
                                    <label class="form-label text-uppercase small fw-semibold" for="pi_header_<?php echo e($piToken); ?>">PI Header</label>
                                    <input class="form-control form-control-sm" type="text" id="pi_header_<?php echo e($piToken); ?>" value="<?php echo e($piHeaderValue); ?>" data-pi-header-input>
                                </div>
                                <div class="col-lg-4">
                                    <label class="form-label text-uppercase small fw-semibold">Bank Reference</label>
                                    <input class="form-control form-control-sm" type="text" value="<?php echo e($referenceCode); ?>" data-bank-reference readonly>
                                </div>
                                <div class="col-lg-3">
                                    <label class="form-label text-uppercase small fw-semibold" for="bank_ref_date_<?php echo e($piToken); ?>">Bank Ref Date</label>
                                    <input class="form-control form-control-sm" type="date" id="bank_ref_date_<?php echo e($piToken); ?>" value="<?php echo e($referenceDate); ?>" data-bank-ref-date>
                                    <?php if ($referenceDateFormatted): ?>
                                        <div class="text-muted small mt-1">Saved as <?php echo e($referenceDateFormatted); ?></div>
                                    <?php endif; ?>
                                </div>
                                <div class="col-lg-1 d-flex align-items-end">
                                    <button class="btn btn-outline-secondary w-100" type="button" data-action="save-pi-details" data-pi-token="<?php echo e($piToken); ?>">Save</button>
                                </div>
                            </div>
                            <div class="text-muted small mt-2">Bank letters append the PI header to “Opening L/C for Import”.</div>
                        </div>
                    </div>

                    <div class="workspace-stat-grid mt-3">
                        <div class="workspace-stat">
                            <span class="workspace-stat-label">Products</span>
                            <span class="workspace-stat-value"><?php echo e($productCount); ?></span>
                        </div>
                        <div class="workspace-stat">
                            <span class="workspace-stat-label">Total Weight</span>
                            <span class="workspace-stat-value"><?php echo e($totalWeightDisplay); ?></span>
                        </div>
                        <div class="workspace-stat">
                            <span class="workspace-stat-label">Freight / Weight</span>
                            <span class="workspace-stat-value">$<?php echo e($freightPerWeightDisplay); ?></span>
                        </div>
                        <div class="workspace-stat">
                            <span class="workspace-stat-label">Total FOB</span>
                            <span class="workspace-stat-value">$<?php echo e($totalFobDisplay); ?></span>
                        </div>
                        <div class="workspace-stat">
                            <span class="workspace-stat-label">Total C&amp;F</span>
                            <span class="workspace-stat-value">$<?php echo e($totalCnfDisplay); ?></span>
                        </div>
                    </div>

                    <div class="table-responsive mt-4">
                        <table class="table table-sm table-hover align-middle mb-0 workspace-product-table">
                            <thead class="table-light">
                            <tr>
                                <th scope="col">Product</th>
                                <th scope="col">Category &amp; COO</th>
                                <th scope="col">Size &amp; Unit</th>
                                <th scope="col" class="text-end">Unit Rate</th>
                                <th scope="col" class="text-end">DEC &amp; HS</th>
                                <th scope="col" class="text-end">Assessment</th>
                                <th scope="col" class="text-end">Quantity &amp; FOB</th>
                                <th scope="col" class="text-end">C&amp;F Summary</th>
                            </tr>
                            </thead>
                            <tbody data-products-for="<?php echo e($piToken); ?>">
                            <?php if (empty($lines)): ?>
                                <tr>
                                    <td colspan="8" class="text-center text-muted py-5">
                                        <div class="workspace-empty-state bg-transparent border-0 shadow-none p-0">
                                            <div class="emoji">📦</div>
                                            <p class="lead mb-1">No products yet</p>
                                            <p class="text-muted mb-0">Use the “Add Product” button to attach vendor items or create new ones.</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($lines as $line): ?>
                                <?php
                                $product = $line['product'];
                                $productToken = (string) ($product['token'] ?? '');
                                $quantityDisplay = rtrim(rtrim(number_format($line['quantity'], 3, '.', ''), '0'), '.') ?: '0';
                                $fobDisplay = number_format($line['fob_total'], 2);
                                $fobPerUnitDisplay = number_format($line['fob_per_unit'] ?? 0, 2);
                                $freightPerUnitDisplay = number_format($line['freight_per_unit'] ?? 0, 2);
                                $freightShareDisplay = number_format($line['freight_share'] ?? 0, 2);
                                $cnfPerUnitDisplay = number_format($line['cnf_per_unit'] ?? 0, 2);
                                $cnfTotalDisplay = number_format($line['cnf_total'] ?? 0, 2);
                                $lineWeightDisplay = rtrim(rtrim(number_format($line['line_weight'], 3, '.', ''), '0'), '.') ?: '0';
                                ?>
                                <tr data-product-token="<?php echo e($productToken); ?>" data-pi-token="<?php echo e($piToken); ?>">
                                    <td>
                                        <div class="fw-semibold"><?php echo e($product['product_name'] ?? ''); ?></div>
                                        <div class="text-muted small"><?php echo e($product['brand'] ?? ''); ?></div>
                                        <div class="mt-2">
                                            <button class="btn btn-outline-danger btn-sm" type="button" data-action="delete-product" data-pi-token="<?php echo e($piToken); ?>" data-product-token="<?php echo e($productToken); ?>"<?php echo $productToken === '' ? ' disabled' : ''; ?>>Remove Product</button>
                                        </div>
                                    </td>
                                    <td>
                                        <div><?php echo e($product['product_category'] ?? ''); ?></div>
                                        <div class="text-muted small">COO: <?php echo e($product['country_of_origin'] ?? ''); ?></div>
                                    </td>
                                    <td>
                                        <div><?php echo e($product['product_size'] ?? ''); ?></div>
                                        <div class="text-muted small">Unit: <?php echo e($product['unit'] ?? ''); ?></div>
                                        <div class="text-muted small">Unit Wt: <?php echo e($product['item_weight'] ?? ''); ?></div>
                                        <div class="text-muted small">Total Wt: <?php echo e($lineWeightDisplay); ?></div>
                                    </td>
                                    <td class="text-end">
                                        <div class="fw-semibold">$<?php echo e($product['rate_formatted'] ?? number_format((float) ($product['rate'] ?? 0), 2)); ?></div>
                                        <div class="text-muted small">Weight: <?php echo e($product['item_weight'] ?? ''); ?></div>
                                    </td>
                                    <td class="text-end">
                                        <div>$<?php echo e($product['dec_unit_price_formatted'] ?? number_format((float) ($product['dec_unit_price'] ?? 0), 2)); ?></div>
                                        <div class="text-muted small">HS: <?php echo e($product['hs_code'] ?? ''); ?></div>
                                    </td>
                                    <td class="text-end">
                                        <div>$<?php echo e($product['asses_unit_price_formatted'] ?? number_format((float) ($product['asses_unit_price'] ?? 0), 2)); ?></div>
                                    </td>
                                    <td class="text-end">
                                        <div class="fw-semibold"><?php echo e($quantityDisplay); ?></div>
                                        <div class="text-muted small">FOB: $<?php echo e($fobDisplay); ?></div>
                                    </td>
                                    <td class="text-end">
                                        <div class="fw-semibold">C&amp;F Total $<?php echo e($cnfTotalDisplay); ?></div>
                                        <div class="text-muted small">Per Unit $<?php echo e($cnfPerUnitDisplay); ?> (FOB $<?php echo e($fobPerUnitDisplay); ?> + Freight $<?php echo e($freightPerUnitDisplay); ?>)</div>
                                        <div class="text-muted small">Freight Share $<?php echo e($freightShareDisplay); ?></div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <p class="text-muted small mt-3<?php echo !empty($lines) ? ' d-none' : ''; ?>" data-empty-state-for="<?php echo e($piToken); ?>">
                        No products have been added to this proforma invoice yet.
                    </p>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>
<div id="noPiMessage" class="d-flex justify-content-center<?php echo empty($proformas) ? '' : ' d-none'; ?>">
    <div class="workspace-empty-state text-center">
        <div class="emoji">📄</div>
        <p class="lead mb-1">No proforma invoices yet</p>
        <p class="text-muted mb-0">Add your first proforma invoice to begin attaching vendor products.</p>
    </div>
</div>

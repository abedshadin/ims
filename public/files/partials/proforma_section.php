<div class="card shadow-sm border-0 mb-4">
    <div class="card-body p-4">
        <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
            <div>
                <h2 class="h5 mb-1">Add a Proforma Invoice</h2>
                <p class="text-muted mb-0">Create a proforma invoice record and then attach the required products below.</p>
            </div>
        </div>
        <form id="createPiForm" class="row gy-3 mt-3" method="post" novalidate>
            <input type="hidden" name="file_token" value="<?php echo e($fileToken); ?>">
            <div class="col-lg-5">
                <label class="form-label text-uppercase small fw-semibold" for="invoice_number">Proforma Invoice Number</label>
                <input class="form-control form-control-lg" type="text" id="invoice_number" name="invoice_number" placeholder="e.g. PI-2025-001" required>
            </div>
            <div class="col-lg-3">
                <label class="form-label text-uppercase small fw-semibold" for="freight_amount">Freight Amount</label>
                <div class="input-group input-group-lg">
                    <span class="input-group-text">$</span>
                    <input class="form-control" type="number" step="0.01" id="freight_amount" name="freight_amount" placeholder="0.00">
                </div>
            </div>
            <div class="col-lg-4 d-flex align-items-end justify-content-lg-end">
                <button class="btn btn-primary btn-lg w-100 w-lg-auto" type="submit">Add Proforma Invoice</button>
            </div>
        </form>
        <div id="piAlert" class="alert d-none mt-3" role="alert"></div>
    </div>
</div>

<div id="piList" class="mb-4">
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
        ?>
        <div class="card shadow-sm border-0 mb-4" data-pi-card="<?php echo e($piToken); ?>">
            <div class="card-body p-4">
                <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-start gap-3">
                    <div class="flex-grow-1">
                        <div class="d-flex align-items-center gap-3 flex-wrap">
                            <h2 class="h5 mb-0">Invoice <?php echo e($proforma['invoice_number'] ?? ''); ?></h2>
                            <span class="badge text-bg-info-subtle text-info fw-normal">Freight $<?php echo e(number_format($freightAmount, 2)); ?></span>
                        </div>
                        <p class="text-muted small mb-0">Created <?php echo e($proforma['created_at_human'] ?? ''); ?></p>
                        <div class="input-group input-group-sm mt-3" style="max-width: 22rem;">
                            <span class="input-group-text">$</span>
                            <input class="form-control" type="number" step="0.01" value="<?php echo e(number_format($freightAmount, 2, '.', '')); ?>" data-freight-input>
                            <button class="btn btn-outline-primary" type="button" data-action="save-freight" data-pi-token="<?php echo e($piToken); ?>">Save Freight</button>
                        </div>
                        <div class="text-muted small mt-1">Freight is distributed by total weight when calculating C&amp;F.</div>
                    </div>
                    <div class="d-flex flex-column align-items-lg-end gap-2 w-100 w-lg-auto">
                        <div class="d-flex flex-wrap justify-content-lg-end gap-2">
                            <button class="btn btn-outline-primary" type="button" data-action="print-cnf" data-pi-token="<?php echo e($piToken); ?>">
                                C&amp;F Calc Print &amp; Preview
                            </button>
                            <button class="btn btn-outline-primary" type="button" data-action="print-bank-forwarding" data-pi-token="<?php echo e($piToken); ?>">
                                Bank Forwarding Print &amp; Preview
                            </button>
                            <button class="btn btn-outline-primary" type="button" data-action="print-toc" data-pi-token="<?php echo e($piToken); ?>">
                                ToC Print &amp; Preview
                            </button>
                        </div>
                        <div class="d-flex flex-wrap justify-content-lg-end gap-2">
                            <button class="btn btn-primary" type="button" data-action="add-product" data-pi-token="<?php echo e($piToken); ?>">
                                Add Product
                            </button>
                        </div>
                    </div>
                </div>
                <div class="table-responsive mt-4">
                    <table class="table table-sm align-middle mb-0">
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
                                <td colspan="8" class="text-center text-muted py-4">
                                    No products have been added to this proforma invoice yet.
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
                                $totalWeightDisplay = rtrim(rtrim(number_format($line['line_weight'], 3, '.', ''), '0'), '.') ?: '0';
                                ?>
                                <tr data-product-token="<?php echo e($productToken); ?>" data-pi-token="<?php echo e($piToken); ?>">
                                    <td>
                                        <div class="fw-semibold"><?php echo e($product['product_name'] ?? ''); ?></div>
                                        <div class="text-muted small"><?php echo e($product['brand'] ?? ''); ?></div>
                                        <div class="mt-2">
                                            <button class="btn btn-danger btn-sm" type="button" data-action="delete-product" data-pi-token="<?php echo e($piToken); ?>" data-product-token="<?php echo e($productToken); ?>"<?php echo $productToken === '' ? ' disabled' : ''; ?>>Remove Product</button>
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
                                        <div class="text-muted small">Total Wt: <?php echo e($totalWeightDisplay); ?></div>
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
                <div class="row row-cols-1 row-cols-md-2 row-cols-lg-5 g-3 mt-3">
                    <div class="col">
                        <div class="text-muted text-uppercase small">Freight</div>
                        <div class="fw-semibold">$<?php echo e(number_format($freightAmount, 2)); ?></div>
                    </div>
                    <div class="col">
                        <div class="text-muted text-uppercase small">Total Weight</div>
                        <div class="fw-semibold"><?php echo e(rtrim(rtrim(number_format($totalWeight, 3, '.', ''), '0'), '.') ?: '0'); ?></div>
                    </div>
                    <div class="col">
                        <div class="text-muted text-uppercase small">Freight / Weight</div>
                        <div class="fw-semibold">$<?php echo e(number_format($freightPerWeight, 2)); ?></div>
                    </div>
                    <div class="col">
                        <div class="text-muted text-uppercase small">Total FOB</div>
                        <div class="fw-semibold">$<?php echo e(number_format($totalFob, 2)); ?></div>
                    </div>
                    <div class="col">
                        <div class="text-muted text-uppercase small">Total C&amp;F</div>
                        <div class="fw-semibold">$<?php echo e(number_format($totalCnf, 2)); ?></div>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>
<div id="noPiMessage" class="text-center text-muted py-5<?php echo empty($proformas) ? '' : ' d-none'; ?>">
    <div class="mb-2">
        <span class="display-6 d-block">📄</span>
    </div>
    <p class="lead mb-1">No proforma invoices yet</p>
    <p class="text-muted mb-0">Add your first proforma invoice to begin attaching vendor products.</p>
</div>

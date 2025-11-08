<div class="modal fade" id="productModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" data-product-modal-title>Add Products to Invoice</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="productForm" method="post" novalidate>
                    <input type="hidden" name="pi_token" id="pi_token" value="">
                    <input type="hidden" name="ci_token" id="ci_token" value="">
                    <div class="mb-4">
                        <span class="text-uppercase small fw-semibold text-muted">Product Source</span>
                        <div class="d-flex flex-wrap gap-3 mt-2">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="product_mode" id="mode_existing" value="existing" checked>
                                <label class="form-check-label" for="mode_existing">Use saved vendor product</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="product_mode" id="mode_new" value="new">
                                <label class="form-check-label" for="mode_new">Create a new product</label>
                            </div>
                        </div>
                    </div>

                    <div id="existingProductFields" class="mb-4">
                        <label class="form-label text-uppercase small fw-semibold" for="vendor_product_id">Vendor Products</label>
                        <select class="form-select" id="vendor_product_id" name="vendor_product_id">
                            <option value="">Select a product</option>
                        </select>
                        <div id="vendorProductPreview" class="mt-3 d-none">
                            <div class="bg-body-secondary rounded p-3">
                                <div class="fw-semibold" data-preview="product_name"></div>
                                <div class="small text-muted" data-preview="brand"></div>
                                <div class="row row-cols-1 row-cols-md-2 g-2 mt-2">
                                    <div class="col"><span class="text-muted small">Category</span><div data-preview="product_category" class="fw-semibold"></div></div>
                                    <div class="col"><span class="text-muted small">COO</span><div data-preview="country_of_origin" class="fw-semibold"></div></div>
                                    <div class="col"><span class="text-muted small">Size</span><div data-preview="product_size" class="fw-semibold"></div></div>
                                    <div class="col"><span class="text-muted small">Unit</span><div data-preview="unit" class="fw-semibold"></div></div>
                                    <div class="col"><span class="text-muted small">Unit Rate</span><div data-preview="rate" class="fw-semibold"></div></div>
                                    <div class="col"><span class="text-muted small">Item Weight</span><div data-preview="item_weight" class="fw-semibold"></div></div>
                                    <div class="col"><span class="text-muted small">DEC Unit Price</span><div data-preview="dec_unit_price" class="fw-semibold"></div></div>
                                    <div class="col"><span class="text-muted small">ASSES Unit Price</span><div data-preview="asses_unit_price" class="fw-semibold"></div></div>
                                    <div class="col"><span class="text-muted small">HS Code</span><div data-preview="hs_code" class="fw-semibold"></div></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div id="newProductFields" class="d-none">
                        <div class="alert alert-info" role="alert">
                            This product will be saved to the vendor catalogue and linked to the selected invoice.
                        </div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold" for="product_name">Product Name</label>
                                <input class="form-control" type="text" id="product_name" name="product_name" placeholder="Product name">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold" for="brand">Brand</label>
                                <select class="form-select" id="brand" name="brand">
                                    <option value="">Select brand</option>
                                    <option value="PH">PH</option>
                                    <option value="KFC">KFC</option>
                                    <option value="PH/KFC">PH/KFC</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold" for="country_of_origin">COO</label>
                                <input class="form-control" type="text" id="country_of_origin" name="country_of_origin" placeholder="e.g. Malaysia">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold" for="product_category">Product Category</label>
                                <select class="form-select" id="product_category" name="product_category">
                                    <option value="">Select category</option>
                                    <option value="RM">Raw Material (RM)</option>
                                    <option value="EQ">Equipment (EQ)</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold" for="product_size">Size</label>
                                <select class="form-select" id="product_size" name="product_size">
                                    <option value="">Select size</option>
                                    <option value="Carton">Carton</option>
                                    <option value="Case">Case</option>
                                    <option value="MTN">MTN</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold" for="unit">Unit</label>
                                <input class="form-control" type="text" id="unit" name="unit" placeholder="e.g. pcs, kg">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold" for="rate">Unit Rate</label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input class="form-control" type="number" step="0.01" id="rate" name="rate" placeholder="0.00">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold" for="item_weight">Item Weight</label>
                                <input class="form-control" type="text" id="item_weight" name="item_weight" placeholder="e.g. 2.5 kg">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold" for="dec_unit_price">Dec Unit Price</label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input class="form-control" type="number" step="0.01" id="dec_unit_price" name="dec_unit_price" placeholder="0.00">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold" for="asses_unit_price">Asses Unit Price</label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input class="form-control" type="number" step="0.01" id="asses_unit_price" name="asses_unit_price" placeholder="0.00">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold" for="hs_code">HS Code</label>
                                <input class="form-control" type="text" id="hs_code" name="hs_code" placeholder="Customs classification">
                            </div>
                        </div>
                    </div>

                    <div class="row g-3 mt-1">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold" for="quantity">Quantity</label>
                            <input class="form-control" type="number" step="0.001" min="0.001" id="quantity" name="quantity" placeholder="e.g. 100" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold" for="fob_total">FOB Total</label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input class="form-control" type="number" step="0.01" min="0" id="fob_total" name="fob_total" placeholder="0.00" required>
                            </div>
                        </div>
                    </div>
                    <p class="text-muted small mt-1">Quantity and FOB totals feed the C&amp;F calculation for this invoice.</p>

                    <div id="productFormAlert" class="alert d-none mt-4" role="alert"></div>
                </form>
                <div class="border-top pt-4 mt-4" data-pi-only>
                    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-2">
                        <div>
                            <span class="text-uppercase small fw-semibold text-muted">Remove Existing Product</span>
                            <p class="text-muted small mb-0">Select a product already linked to this proforma invoice to remove it instantly.</p>
                        </div>
                        <div class="d-flex flex-column flex-lg-row gap-2 w-100 w-lg-auto align-items-stretch align-items-lg-center">
                            <select class="form-select" id="pi_product_id" name="pi_product_id">
                                <option value="">Select a product to remove</option>
                            </select>
                            <button class="btn btn-outline-danger" type="button" id="piProductRemoveButton">Remove Product</button>
                        </div>
                    </div>
                    <div id="piProductRemoveAlert" class="alert d-none mt-3" role="alert"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="productFormSubmit">Add Product</button>
            </div>
        </div>
    </div>
</div>

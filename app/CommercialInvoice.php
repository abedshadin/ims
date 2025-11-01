<?php

declare(strict_types=1);

require_once __DIR__ . '/IdCipher.php';

final class CommercialInvoice
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public static function loadForFile(PDO $pdo, int $fileId): array
    {
        $statement = $pdo->prepare(
            'SELECT
                ci.id,
                ci.vendor_file_id,
                ci.proforma_invoice_id,
                ci.invoice_number,
                ci.invoice_date,
                ci.total_value,
                ci.created_at,
                ci.updated_at,
                ci.created_by,
                ci.updated_by,
                pi.invoice_number AS proforma_invoice_number
             FROM commercial_invoices ci
             INNER JOIN proforma_invoices pi ON pi.id = ci.proforma_invoice_id
             WHERE ci.vendor_file_id = :file_id
             ORDER BY ci.created_at DESC, ci.id DESC'
        );

        $statement->execute([':file_id' => $fileId]);

        $rows = $statement->fetchAll() ?: [];

        if (!$rows) {
            return [];
        }

        return self::attachProducts($pdo, $rows);
    }

    public static function loadById(PDO $pdo, int $invoiceId): ?array
    {
        $statement = $pdo->prepare(
            'SELECT
                ci.id,
                ci.vendor_file_id,
                ci.proforma_invoice_id,
                ci.invoice_number,
                ci.invoice_date,
                ci.total_value,
                ci.created_at,
                ci.updated_at,
                ci.created_by,
                ci.updated_by,
                pi.invoice_number AS proforma_invoice_number
             FROM commercial_invoices ci
             INNER JOIN proforma_invoices pi ON pi.id = ci.proforma_invoice_id
             WHERE ci.id = :id
             LIMIT 1'
        );

        $statement->execute([':id' => $invoiceId]);

        $row = $statement->fetch();

        if (!$row) {
            return null;
        }

        $invoices = self::attachProducts($pdo, [$row]);

        return $invoices[0] ?? null;
    }

    /**
     * @param array<int, array<string, mixed>> $invoiceRows
     * @return array<int, array<string, mixed>>
     */
    private static function attachProducts(PDO $pdo, array $invoiceRows): array
    {
        $invoiceIds = array_map(static fn(array $row): int => (int) $row['id'], $invoiceRows);

        if (!$invoiceIds) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($invoiceIds), '?'));

        $productStatement = $pdo->prepare(
            "SELECT
                cip.id,
                cip.commercial_invoice_id,
                cip.proforma_invoice_product_id,
                cip.product_name,
                cip.brand,
                cip.country_of_origin,
                cip.product_category,
                cip.product_size,
                cip.unit,
                cip.hs_code,
                cip.final_quantity,
                cip.final_unit_price,
                cip.total_item_price,
                cip.unit_freight,
                cip.total_freight,
                cip.item_weight,
                cip.total_weight,
                cip.total_cnf_value,
                cip.invoice_total,
                cip.created_at,
                cip.updated_at
             FROM commercial_invoice_products cip
             WHERE cip.commercial_invoice_id IN ($placeholders)
             ORDER BY cip.id ASC"
        );

        $productStatement->execute($invoiceIds);

        $productsByInvoice = [];

        while ($productRow = $productStatement->fetch()) {
            $invoiceId = (int) $productRow['commercial_invoice_id'];
            $productsByInvoice[$invoiceId][] = self::normaliseProductRow($productRow);
        }

        $invoices = [];

        foreach ($invoiceRows as $invoiceRow) {
            $invoiceId = (int) $invoiceRow['id'];
            $invoices[] = self::normaliseInvoiceRow($invoiceRow, $productsByInvoice[$invoiceId] ?? []);
        }

        return $invoices;
    }

    /**
     * @param array<int, array<string, mixed>> $products
     */
    private static function normaliseInvoiceRow(array $row, array $products): array
    {
        $invoiceId = (int) $row['id'];
        $proformaId = (int) $row['proforma_invoice_id'];
        $invoiceDateRaw = $row['invoice_date'] ?? null;
        $invoiceDate = $invoiceDateRaw ? date('Y-m-d', strtotime((string) $invoiceDateRaw)) : '';
        $invoiceDateHuman = $invoiceDate !== '' ? date('j M Y', strtotime($invoiceDate)) : '';

        $totalValue = self::normaliseCurrencyValue($row['total_value'] ?? null);
        $createdAt = isset($row['created_at']) ? (string) $row['created_at'] : null;
        $updatedAt = isset($row['updated_at']) ? (string) $row['updated_at'] : null;

        try {
            $invoiceToken = IdCipher::encode($invoiceId);
        } catch (InvalidArgumentException|RuntimeException $exception) {
            $invoiceToken = null;
        }

        try {
            $proformaToken = IdCipher::encode($proformaId);
        } catch (InvalidArgumentException|RuntimeException $exception) {
            $proformaToken = null;
        }

        return [
            'id' => $invoiceId,
            'token' => $invoiceToken,
            'vendor_file_id' => isset($row['vendor_file_id']) ? (int) $row['vendor_file_id'] : null,
            'proforma_invoice_id' => $proformaId,
            'proforma' => [
                'id' => $proformaId,
                'token' => $proformaToken,
                'invoice_number' => (string) ($row['proforma_invoice_number'] ?? ''),
            ],
            'invoice_number' => (string) ($row['invoice_number'] ?? ''),
            'invoice_date' => $invoiceDate,
            'invoice_date_formatted' => $invoiceDateHuman,
            'total_value' => $totalValue['value'],
            'total_value_formatted' => $totalValue['formatted'],
            'created_at' => $createdAt,
            'created_at_human' => $createdAt ? date('j M Y, g:i A', strtotime($createdAt)) : '',
            'updated_at' => $updatedAt,
            'updated_at_human' => $updatedAt ? date('j M Y, g:i A', strtotime($updatedAt)) : null,
            'created_by' => isset($row['created_by']) && $row['created_by'] !== null ? (int) $row['created_by'] : null,
            'updated_by' => isset($row['updated_by']) && $row['updated_by'] !== null ? (int) $row['updated_by'] : null,
            'products' => $products,
        ];
    }

    /**
     * @return array<string, string|null>
     */
    private static function normaliseCurrencyValue(mixed $value): array
    {
        if ($value === null || $value === '') {
            return [
                'value' => '0.00',
                'formatted' => '0.00',
            ];
        }

        $number = (float) $value;

        return [
            'value' => number_format($number, 2, '.', ''),
            'formatted' => number_format($number, 2),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function normaliseProductRow(array $row): array
    {
        $productId = (int) $row['id'];
        $proformaProductId = isset($row['proforma_invoice_product_id']) ? (int) $row['proforma_invoice_product_id'] : null;

        try {
            $productToken = IdCipher::encode($productId);
        } catch (InvalidArgumentException|RuntimeException $exception) {
            $productToken = null;
        }

        try {
            $proformaProductToken = $proformaProductId !== null ? IdCipher::encode($proformaProductId) : null;
        } catch (InvalidArgumentException|RuntimeException $exception) {
            $proformaProductToken = null;
        }

        $quantity = self::normaliseDecimal($row['final_quantity'] ?? null, 3);
        $unitPrice = self::normaliseDecimal($row['final_unit_price'] ?? null, 2);
        $totalItem = self::normaliseDecimal($row['total_item_price'] ?? null, 2);
        $unitFreight = self::normaliseDecimal($row['unit_freight'] ?? null, 4);
        $totalFreight = self::normaliseDecimal($row['total_freight'] ?? null, 2);
        $itemWeight = self::normaliseDecimal($row['item_weight'] ?? null, 3);
        $totalWeight = self::normaliseDecimal($row['total_weight'] ?? null, 3);
        $totalCnf = self::normaliseDecimal($row['total_cnf_value'] ?? null, 2);
        $invoiceTotal = self::normaliseDecimal($row['invoice_total'] ?? null, 2);

        return [
            'id' => $productId,
            'token' => $productToken,
            'commercial_invoice_id' => isset($row['commercial_invoice_id']) ? (int) $row['commercial_invoice_id'] : null,
            'proforma_product_id' => $proformaProductId,
            'proforma_product_token' => $proformaProductToken,
            'product_name' => (string) ($row['product_name'] ?? ''),
            'brand' => (string) ($row['brand'] ?? ''),
            'country_of_origin' => (string) ($row['country_of_origin'] ?? ''),
            'product_category' => (string) ($row['product_category'] ?? ''),
            'product_size' => (string) ($row['product_size'] ?? ''),
            'unit' => (string) ($row['unit'] ?? ''),
            'hs_code' => (string) ($row['hs_code'] ?? ''),
            'final_quantity' => $quantity['value'],
            'final_quantity_formatted' => $quantity['formatted'],
            'final_unit_price' => $unitPrice['value'],
            'final_unit_price_formatted' => $unitPrice['formatted'],
            'total_item_price' => $totalItem['value'],
            'total_item_price_formatted' => $totalItem['formatted'],
            'unit_freight' => $unitFreight['value'],
            'unit_freight_formatted' => $unitFreight['formatted'],
            'total_freight' => $totalFreight['value'],
            'total_freight_formatted' => $totalFreight['formatted'],
            'item_weight' => $itemWeight['value'],
            'item_weight_formatted' => $itemWeight['formatted'],
            'total_weight' => $totalWeight['value'],
            'total_weight_formatted' => $totalWeight['formatted'],
            'total_cnf_value' => $totalCnf['value'],
            'total_cnf_value_formatted' => $totalCnf['formatted'],
            'invoice_total' => $invoiceTotal['value'],
            'invoice_total_formatted' => $invoiceTotal['formatted'],
            'created_at' => isset($row['created_at']) ? (string) $row['created_at'] : null,
            'updated_at' => isset($row['updated_at']) ? (string) $row['updated_at'] : null,
        ];
    }

    /**
     * @return array<string, string>
     */
    private static function normaliseDecimal(mixed $value, int $precision): array
    {
        if ($value === null || $value === '') {
            $formatted = $precision > 0 ? number_format(0, $precision) : '0';

            return [
                'value' => $precision > 0 ? number_format(0, $precision, '.', '') : '0',
                'formatted' => $formatted,
            ];
        }

        $number = (float) $value;

        return [
            'value' => number_format($number, $precision, '.', ''),
            'formatted' => number_format($number, $precision),
        ];
    }
}


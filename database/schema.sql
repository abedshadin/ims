CREATE TABLE IF NOT EXISTS users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS vendors (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    vendor_name VARCHAR(255) NOT NULL,
    vendor_address VARCHAR(255) NOT NULL,
    beneficiary_bank_name VARCHAR(255) NOT NULL,
    beneficiary_bank_address VARCHAR(255) NOT NULL,
    beneficiary_swift VARCHAR(50) NOT NULL,
    advising_bank_name VARCHAR(255) NOT NULL,
    advising_bank_account VARCHAR(100) NOT NULL,
    advising_swift_code VARCHAR(50) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by BIGINT UNSIGNED NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS vendor_products (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    vendor_id BIGINT UNSIGNED NOT NULL,
    product_name VARCHAR(255) NOT NULL,
    brand VARCHAR(50) NOT NULL,
    country_of_origin VARCHAR(100) NOT NULL,
    product_category VARCHAR(50) NOT NULL,
    product_size VARCHAR(100) NOT NULL,
    unit VARCHAR(50) NOT NULL,
    rate DECIMAL(15, 2) NOT NULL,
    item_weight VARCHAR(100) NOT NULL,
    dec_unit_price DECIMAL(15, 2) NOT NULL,
    asses_unit_price DECIMAL(15, 2) NOT NULL,
    hs_code VARCHAR(100) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_vendor_products_vendor FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS vendor_files (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    file_name VARCHAR(50) NOT NULL UNIQUE,
    vendor_id BIGINT UNSIGNED NOT NULL,
    bank_name ENUM('DBBL', 'SCB', 'BBL') NOT NULL,
    brand ENUM('PH', 'KFC', 'PH/KFC') NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by BIGINT UNSIGNED NULL,
    CONSTRAINT fk_vendor_files_vendor FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS proforma_invoices (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    vendor_file_id BIGINT UNSIGNED NOT NULL,
    invoice_number VARCHAR(100) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by BIGINT UNSIGNED NULL,
    CONSTRAINT fk_proforma_invoices_file FOREIGN KEY (vendor_file_id) REFERENCES vendor_files(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS proforma_invoice_products (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    proforma_invoice_id BIGINT UNSIGNED NOT NULL,
    vendor_product_id BIGINT UNSIGNED NULL,
    product_name VARCHAR(255) NOT NULL,
    brand VARCHAR(50) NOT NULL,
    country_of_origin VARCHAR(100) NOT NULL,
    product_category VARCHAR(50) NOT NULL,
    product_size VARCHAR(100) NOT NULL,
    unit VARCHAR(50) NOT NULL,
    rate DECIMAL(15, 2) NOT NULL,
    item_weight VARCHAR(100) NOT NULL,
    dec_unit_price DECIMAL(15, 2) NOT NULL,
    asses_unit_price DECIMAL(15, 2) NOT NULL,
    hs_code VARCHAR(100) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_invoice_products_invoice FOREIGN KEY (proforma_invoice_id) REFERENCES proforma_invoices(id) ON DELETE CASCADE,
    CONSTRAINT fk_invoice_products_vendor_product FOREIGN KEY (vendor_product_id) REFERENCES vendor_products(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

-- Migration: Create contacts table
-- Individual contacts/people

CREATE TABLE IF NOT EXISTS contacts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    uuid CHAR(36) NOT NULL UNIQUE,
    account_id BIGINT UNSIGNED NULL,

    -- Personal information
    salutation VARCHAR(20) NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    title VARCHAR(100) NULL,
    department VARCHAR(100) NULL,

    -- Contact information
    email VARCHAR(255) NULL,
    phone VARCHAR(50) NULL,
    mobile VARCHAR(50) NULL,
    fax VARCHAR(50) NULL,

    -- Address
    mailing_street VARCHAR(255) NULL,
    mailing_city VARCHAR(100) NULL,
    mailing_state VARCHAR(100) NULL,
    mailing_postal_code VARCHAR(20) NULL,
    mailing_country VARCHAR(100) NULL,

    -- Additional info
    birthdate DATE NULL,
    lead_source VARCHAR(100) NULL,
    reports_to_id BIGINT UNSIGNED NULL,
    owner_id BIGINT UNSIGNED NOT NULL,
    status ENUM('active', 'inactive', 'do_not_contact') DEFAULT 'active',

    description TEXT NULL,
    custom_fields JSON NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,

    INDEX idx_name (first_name, last_name),
    INDEX idx_email (email),
    INDEX idx_account (account_id),
    INDEX idx_owner (owner_id),
    INDEX idx_status (status),
    INDEX idx_tenant (tenant_id),
    FULLTEXT idx_search (first_name, last_name, email, description),

    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE SET NULL,
    FOREIGN KEY (owner_id) REFERENCES users(id),
    FOREIGN KEY (reports_to_id) REFERENCES contacts(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

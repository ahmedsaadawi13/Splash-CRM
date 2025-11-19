-- Migration: Create accounts table
-- Business accounts/companies

CREATE TABLE IF NOT EXISTS accounts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    uuid CHAR(36) NOT NULL UNIQUE,
    account_name VARCHAR(255) NOT NULL,
    account_number VARCHAR(50) NULL UNIQUE,
    account_type ENUM('customer', 'prospect', 'partner', 'vendor', 'competitor', 'other') DEFAULT 'customer',
    industry VARCHAR(100) NULL,
    website VARCHAR(500) NULL,
    phone VARCHAR(50) NULL,
    fax VARCHAR(50) NULL,
    email VARCHAR(255) NULL,
    annual_revenue DECIMAL(15,2) NULL,
    employees INT NULL,
    rating ENUM('hot', 'warm', 'cold') NULL,
    ownership ENUM('public', 'private', 'subsidiary', 'other') NULL,

    -- Address fields
    billing_street VARCHAR(255) NULL,
    billing_city VARCHAR(100) NULL,
    billing_state VARCHAR(100) NULL,
    billing_postal_code VARCHAR(20) NULL,
    billing_country VARCHAR(100) NULL,

    shipping_street VARCHAR(255) NULL,
    shipping_city VARCHAR(100) NULL,
    shipping_state VARCHAR(100) NULL,
    shipping_postal_code VARCHAR(20) NULL,
    shipping_country VARCHAR(100) NULL,

    -- Relationships
    parent_account_id BIGINT UNSIGNED NULL,
    owner_id BIGINT UNSIGNED NOT NULL,

    -- Additional info
    description TEXT NULL,
    custom_fields JSON NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,

    INDEX idx_account_name (account_name),
    INDEX idx_account_type (account_type),
    INDEX idx_industry (industry),
    INDEX idx_owner (owner_id),
    INDEX idx_tenant (tenant_id),
    FULLTEXT idx_search (account_name, description),

    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (owner_id) REFERENCES users(id),
    FOREIGN KEY (parent_account_id) REFERENCES accounts(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Migration: Create products and pricing tables

CREATE TABLE IF NOT EXISTS products (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    uuid CHAR(36) NOT NULL UNIQUE,

    product_name VARCHAR(255) NOT NULL,
    product_code VARCHAR(100) NULL UNIQUE,
    product_category VARCHAR(100) NULL,
    product_family VARCHAR(100) NULL,

    -- Pricing
    unit_price DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    cost_price DECIMAL(15,2) NULL,
    list_price DECIMAL(15,2) NULL,

    -- Inventory
    sku VARCHAR(100) NULL,
    quantity_in_stock INT DEFAULT 0,
    reorder_level INT NULL,

    -- Details
    active BOOLEAN DEFAULT TRUE,
    taxable BOOLEAN DEFAULT TRUE,
    vendor_name VARCHAR(255) NULL,

    description TEXT NULL,
    specifications JSON NULL,
    custom_fields JSON NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,

    INDEX idx_name (product_name),
    INDEX idx_code (product_code),
    INDEX idx_category (product_category),
    INDEX idx_active (active),
    INDEX idx_tenant (tenant_id),
    FULLTEXT idx_search (product_name, description),

    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pricebooks (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    uuid CHAR(36) NOT NULL UNIQUE,

    pricebook_name VARCHAR(255) NOT NULL,
    description TEXT NULL,
    active BOOLEAN DEFAULT TRUE,
    is_standard BOOLEAN DEFAULT FALSE,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_tenant (tenant_id),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pricebook_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    pricebook_id BIGINT UNSIGNED NOT NULL,
    product_id BIGINT UNSIGNED NOT NULL,

    unit_price DECIMAL(15,2) NOT NULL,
    active BOOLEAN DEFAULT TRUE,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY unique_pricebook_product (pricebook_id, product_id),
    FOREIGN KEY (pricebook_id) REFERENCES pricebooks(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

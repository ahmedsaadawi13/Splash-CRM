-- Migration: Create quotes and invoices tables

CREATE TABLE IF NOT EXISTS quotes (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    uuid CHAR(36) NOT NULL UNIQUE,

    quote_number VARCHAR(50) NOT NULL UNIQUE,
    quote_name VARCHAR(255) NOT NULL,

    -- Relationships
    opportunity_id BIGINT UNSIGNED NULL,
    account_id BIGINT UNSIGNED NULL,
    contact_id BIGINT UNSIGNED NULL,

    -- Dates
    quote_date DATE NOT NULL,
    expiration_date DATE NULL,

    -- Status
    status ENUM('draft', 'sent', 'accepted', 'declined', 'expired') DEFAULT 'draft',

    -- Pricing
    subtotal DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    tax DECIMAL(15,2) DEFAULT 0.00,
    shipping DECIMAL(15,2) DEFAULT 0.00,
    discount DECIMAL(15,2) DEFAULT 0.00,
    total DECIMAL(15,2) NOT NULL DEFAULT 0.00,

    -- Payment terms
    payment_terms VARCHAR(255) NULL,

    -- Assignment
    owner_id BIGINT UNSIGNED NOT NULL,

    description TEXT NULL,
    terms_and_conditions TEXT NULL,
    custom_fields JSON NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,

    INDEX idx_number (quote_number),
    INDEX idx_status (status),
    INDEX idx_opportunity (opportunity_id),
    INDEX idx_account (account_id),
    INDEX idx_owner (owner_id),
    INDEX idx_tenant (tenant_id),

    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (opportunity_id) REFERENCES opportunities(id) ON DELETE SET NULL,
    FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE SET NULL,
    FOREIGN KEY (contact_id) REFERENCES contacts(id) ON DELETE SET NULL,
    FOREIGN KEY (owner_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS quote_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    quote_id BIGINT UNSIGNED NOT NULL,
    product_id BIGINT UNSIGNED NULL,

    line_number INT NOT NULL DEFAULT 1,
    product_name VARCHAR(255) NOT NULL,
    description TEXT NULL,

    quantity DECIMAL(10,2) NOT NULL DEFAULT 1.00,
    unit_price DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    discount DECIMAL(15,2) DEFAULT 0.00,
    tax DECIMAL(15,2) DEFAULT 0.00,
    line_total DECIMAL(15,2) NOT NULL DEFAULT 0.00,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_quote (quote_id),
    FOREIGN KEY (quote_id) REFERENCES quotes(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS invoices (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    uuid CHAR(36) NOT NULL UNIQUE,

    invoice_number VARCHAR(50) NOT NULL UNIQUE,
    invoice_name VARCHAR(255) NOT NULL,

    -- Relationships
    quote_id BIGINT UNSIGNED NULL,
    account_id BIGINT UNSIGNED NULL,
    contact_id BIGINT UNSIGNED NULL,

    -- Dates
    invoice_date DATE NOT NULL,
    due_date DATE NULL,
    paid_date DATE NULL,

    -- Status
    status ENUM('draft', 'sent', 'paid', 'partial', 'overdue', 'cancelled') DEFAULT 'draft',

    -- Pricing
    subtotal DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    tax DECIMAL(15,2) DEFAULT 0.00,
    shipping DECIMAL(15,2) DEFAULT 0.00,
    discount DECIMAL(15,2) DEFAULT 0.00,
    total DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    amount_paid DECIMAL(15,2) DEFAULT 0.00,
    balance DECIMAL(15,2) NOT NULL DEFAULT 0.00,

    -- Payment terms
    payment_terms VARCHAR(255) NULL,

    -- Assignment
    owner_id BIGINT UNSIGNED NOT NULL,

    description TEXT NULL,
    notes TEXT NULL,
    custom_fields JSON NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,

    INDEX idx_number (invoice_number),
    INDEX idx_status (status),
    INDEX idx_account (account_id),
    INDEX idx_owner (owner_id),
    INDEX idx_due_date (due_date),
    INDEX idx_tenant (tenant_id),

    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (quote_id) REFERENCES quotes(id) ON DELETE SET NULL,
    FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE SET NULL,
    FOREIGN KEY (contact_id) REFERENCES contacts(id) ON DELETE SET NULL,
    FOREIGN KEY (owner_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS invoice_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    invoice_id BIGINT UNSIGNED NOT NULL,
    product_id BIGINT UNSIGNED NULL,

    line_number INT NOT NULL DEFAULT 1,
    product_name VARCHAR(255) NOT NULL,
    description TEXT NULL,

    quantity DECIMAL(10,2) NOT NULL DEFAULT 1.00,
    unit_price DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    discount DECIMAL(15,2) DEFAULT 0.00,
    tax DECIMAL(15,2) DEFAULT 0.00,
    line_total DECIMAL(15,2) NOT NULL DEFAULT 0.00,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_invoice (invoice_id),
    FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS payments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    uuid CHAR(36) NOT NULL UNIQUE,

    invoice_id BIGINT UNSIGNED NOT NULL,
    payment_number VARCHAR(50) NULL UNIQUE,

    amount DECIMAL(15,2) NOT NULL,
    payment_date DATE NOT NULL,
    payment_method ENUM('cash', 'check', 'credit_card', 'wire_transfer', 'ach', 'other') DEFAULT 'other',

    reference_number VARCHAR(100) NULL,
    notes TEXT NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_invoice (invoice_id),
    INDEX idx_tenant (tenant_id),

    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

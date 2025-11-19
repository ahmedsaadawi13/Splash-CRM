-- Migration: Create leads table
-- Sales leads/prospects

CREATE TABLE IF NOT EXISTS leads (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    uuid CHAR(36) NOT NULL UNIQUE,

    -- Personal information
    salutation VARCHAR(20) NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    title VARCHAR(100) NULL,
    company VARCHAR(255) NOT NULL,

    -- Contact information
    email VARCHAR(255) NULL,
    phone VARCHAR(50) NULL,
    mobile VARCHAR(50) NULL,
    fax VARCHAR(50) NULL,
    website VARCHAR(500) NULL,

    -- Address
    street VARCHAR(255) NULL,
    city VARCHAR(100) NULL,
    state VARCHAR(100) NULL,
    postal_code VARCHAR(20) NULL,
    country VARCHAR(100) NULL,

    -- Lead details
    industry VARCHAR(100) NULL,
    lead_source VARCHAR(100) NULL,
    lead_status ENUM('new', 'contacted', 'qualified', 'unqualified', 'converted', 'lost') DEFAULT 'new',
    rating ENUM('hot', 'warm', 'cold') NULL,
    annual_revenue DECIMAL(15,2) NULL,
    employees INT NULL,

    -- Conversion tracking
    converted BOOLEAN DEFAULT FALSE,
    converted_at TIMESTAMP NULL,
    converted_account_id BIGINT UNSIGNED NULL,
    converted_contact_id BIGINT UNSIGNED NULL,
    converted_opportunity_id BIGINT UNSIGNED NULL,

    -- Assignment
    owner_id BIGINT UNSIGNED NOT NULL,

    -- Scoring
    score INT DEFAULT 0,

    description TEXT NULL,
    custom_fields JSON NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,

    INDEX idx_name (first_name, last_name),
    INDEX idx_company (company),
    INDEX idx_email (email),
    INDEX idx_status (lead_status),
    INDEX idx_owner (owner_id),
    INDEX idx_converted (converted),
    INDEX idx_tenant (tenant_id),
    FULLTEXT idx_search (first_name, last_name, company, email, description),

    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (owner_id) REFERENCES users(id),
    FOREIGN KEY (converted_account_id) REFERENCES accounts(id) ON DELETE SET NULL,
    FOREIGN KEY (converted_contact_id) REFERENCES contacts(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Migration: Create opportunities table
-- Sales opportunities/deals

CREATE TABLE IF NOT EXISTS opportunities (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    uuid CHAR(36) NOT NULL UNIQUE,

    opportunity_name VARCHAR(255) NOT NULL,
    account_id BIGINT UNSIGNED NULL,
    contact_id BIGINT UNSIGNED NULL,

    -- Opportunity details
    amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    stage VARCHAR(100) NOT NULL,
    probability INT DEFAULT 0,
    expected_close_date DATE NULL,
    closed_at TIMESTAMP NULL,

    -- Type and source
    opportunity_type ENUM('new_business', 'existing_business', 'renewal', 'upgrade') DEFAULT 'new_business',
    lead_source VARCHAR(100) NULL,

    -- Status tracking
    status ENUM('open', 'won', 'lost', 'abandoned') DEFAULT 'open',
    loss_reason VARCHAR(255) NULL,

    -- Assignment
    owner_id BIGINT UNSIGNED NOT NULL,

    -- Forecast category
    forecast_category ENUM('pipeline', 'best_case', 'commit', 'closed') DEFAULT 'pipeline',

    description TEXT NULL,
    next_step VARCHAR(500) NULL,
    custom_fields JSON NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,

    INDEX idx_name (opportunity_name),
    INDEX idx_account (account_id),
    INDEX idx_stage (stage),
    INDEX idx_status (status),
    INDEX idx_owner (owner_id),
    INDEX idx_close_date (expected_close_date),
    INDEX idx_tenant (tenant_id),
    FULLTEXT idx_search (opportunity_name, description),

    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE SET NULL,
    FOREIGN KEY (contact_id) REFERENCES contacts(id) ON DELETE SET NULL,
    FOREIGN KEY (owner_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add foreign key for converted opportunities in leads table
ALTER TABLE leads ADD FOREIGN KEY (converted_opportunity_id) REFERENCES opportunities(id) ON DELETE SET NULL;

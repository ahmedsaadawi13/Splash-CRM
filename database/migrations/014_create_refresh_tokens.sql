-- Migration: Create refresh tokens and sessions tables

CREATE TABLE IF NOT EXISTS refresh_tokens (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,

    token VARCHAR(255) NOT NULL UNIQUE,
    expires_at TIMESTAMP NOT NULL,
    revoked BOOLEAN DEFAULT FALSE,
    revoked_at TIMESTAMP NULL,

    -- Device/client info
    user_agent TEXT NULL,
    ip_address VARCHAR(45) NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_token (token),
    INDEX idx_user (user_id),
    INDEX idx_expires (expires_at),

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS password_resets (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL,
    token VARCHAR(255) NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    used BOOLEAN DEFAULT FALSE,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_email (email),
    INDEX idx_token (token),
    INDEX idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Email queue for async email sending
CREATE TABLE IF NOT EXISTS email_queue (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,

    to_email VARCHAR(255) NOT NULL,
    to_name VARCHAR(255) NULL,
    from_email VARCHAR(255) NOT NULL,
    from_name VARCHAR(255) NULL,
    subject VARCHAR(500) NOT NULL,
    body_html TEXT NULL,
    body_text TEXT NULL,

    -- Attachments
    attachments JSON NULL,

    -- Status
    status ENUM('pending', 'sent', 'failed', 'cancelled') DEFAULT 'pending',
    attempts INT DEFAULT 0,
    max_attempts INT DEFAULT 3,
    error_message TEXT NULL,

    sent_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_status (status),
    INDEX idx_tenant (tenant_id),
    INDEX idx_created (created_at),

    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ML predictions and scoring
CREATE TABLE IF NOT EXISTS predictive_scores (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,

    scorable_type VARCHAR(50) NOT NULL,  -- 'lead', 'opportunity', etc.
    scorable_id BIGINT UNSIGNED NOT NULL,

    score_type VARCHAR(50) NOT NULL,  -- 'conversion_probability', 'churn_risk', etc.
    score DECIMAL(5,4) NOT NULL,  -- 0.0000 to 1.0000
    confidence DECIMAL(5,4) NULL,

    model_version VARCHAR(50) NULL,
    features JSON NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_scorable (scorable_type, scorable_id),
    INDEX idx_score_type (score_type),
    INDEX idx_tenant (tenant_id),

    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

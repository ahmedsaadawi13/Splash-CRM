-- Migration: Create audit logs and attachments tables

CREATE TABLE IF NOT EXISTS audit_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    uuid CHAR(36) NOT NULL UNIQUE,

    -- User who made the change
    user_id BIGINT UNSIGNED NULL,
    user_name VARCHAR(255) NULL,  -- Cached in case user is deleted

    -- What was changed
    auditable_type VARCHAR(50) NOT NULL,  -- 'lead', 'contact', 'account', etc.
    auditable_id BIGINT UNSIGNED NOT NULL,

    -- Action performed
    action VARCHAR(50) NOT NULL,  -- 'created', 'updated', 'deleted', 'restored'

    -- Change details
    old_values JSON NULL,
    new_values JSON NULL,
    changed_fields JSON NULL,  -- Array of field names that changed

    -- Context
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_auditable (auditable_type, auditable_id),
    INDEX idx_user (user_id),
    INDEX idx_action (action),
    INDEX idx_created (created_at),
    INDEX idx_tenant (tenant_id),

    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Partition audit_logs by date for better performance
-- ALTER TABLE audit_logs PARTITION BY RANGE (YEAR(created_at)) (
--     PARTITION p2024 VALUES LESS THAN (2025),
--     PARTITION p2025 VALUES LESS THAN (2026),
--     PARTITION pmax VALUES LESS THAN MAXVALUE
-- );

CREATE TABLE IF NOT EXISTS attachments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    uuid CHAR(36) NOT NULL UNIQUE,

    -- Related entity (polymorphic)
    attachable_type VARCHAR(50) NOT NULL,
    attachable_id BIGINT UNSIGNED NOT NULL,

    -- File details
    file_name VARCHAR(255) NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    mime_type VARCHAR(100) NULL,
    file_size BIGINT UNSIGNED NOT NULL,  -- bytes

    -- Storage
    storage_driver VARCHAR(50) DEFAULT 's3',  -- 's3', 'local', 'azure', etc.
    storage_path VARCHAR(500) NOT NULL,
    storage_url VARCHAR(1000) NULL,

    -- Metadata
    description TEXT NULL,
    uploaded_by BIGINT UNSIGNED NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,

    INDEX idx_attachable (attachable_type, attachable_id),
    INDEX idx_uploaded_by (uploaded_by),
    INDEX idx_tenant (tenant_id),

    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notes (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    uuid CHAR(36) NOT NULL UNIQUE,

    -- Related entity (polymorphic)
    notable_type VARCHAR(50) NOT NULL,
    notable_id BIGINT UNSIGNED NOT NULL,

    title VARCHAR(255) NULL,
    content TEXT NOT NULL,

    -- Metadata
    created_by BIGINT UNSIGNED NOT NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,

    INDEX idx_notable (notable_type, notable_id),
    INDEX idx_created_by (created_by),
    INDEX idx_tenant (tenant_id),
    FULLTEXT idx_content (title, content),

    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tags (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,

    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL,
    color VARCHAR(7) NULL,  -- Hex color code

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY unique_slug_per_tenant (tenant_id, slug),
    INDEX idx_tenant (tenant_id),

    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS taggables (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tag_id BIGINT UNSIGNED NOT NULL,
    taggable_type VARCHAR(50) NOT NULL,
    taggable_id BIGINT UNSIGNED NOT NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY unique_taggable (tag_id, taggable_type, taggable_id),
    INDEX idx_taggable (taggable_type, taggable_id),

    FOREIGN KEY (tag_id) REFERENCES tags(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

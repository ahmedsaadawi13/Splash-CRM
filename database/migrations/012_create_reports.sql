-- Migration: Create reports and dashboards tables

CREATE TABLE IF NOT EXISTS reports (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    uuid CHAR(36) NOT NULL UNIQUE,

    name VARCHAR(255) NOT NULL,
    description TEXT NULL,

    -- Report configuration
    report_type ENUM('tabular', 'summary', 'matrix', 'chart') DEFAULT 'tabular',
    module VARCHAR(50) NOT NULL,  -- 'leads', 'contacts', 'opportunities', etc.

    -- Query definition (DSL)
    columns JSON NOT NULL,  -- Array of column definitions
    filters JSON NULL,  -- Array of filter conditions
    groupings JSON NULL,  -- Array of grouping fields
    aggregations JSON NULL,  -- Array of aggregation functions
    sorting JSON NULL,  -- Array of sort definitions
    chart_config JSON NULL,  -- Chart configuration if report_type is 'chart'

    -- Sharing and permissions
    visibility ENUM('private', 'shared', 'public') DEFAULT 'private',
    owner_id BIGINT UNSIGNED NOT NULL,

    -- Scheduling
    scheduled BOOLEAN DEFAULT FALSE,
    schedule_config JSON NULL,  -- Cron expression and email recipients

    -- Performance
    cache_enabled BOOLEAN DEFAULT TRUE,
    cache_ttl INT DEFAULT 300,  -- seconds

    -- Stats
    total_runs INT DEFAULT 0,
    last_run_at TIMESTAMP NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,

    INDEX idx_module (module),
    INDEX idx_owner (owner_id),
    INDEX idx_visibility (visibility),
    INDEX idx_tenant (tenant_id),
    FULLTEXT idx_search (name, description),

    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (owner_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS report_runs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    report_id BIGINT UNSIGNED NOT NULL,
    tenant_id BIGINT UNSIGNED NOT NULL,
    uuid CHAR(36) NOT NULL UNIQUE,

    run_by_user_id BIGINT UNSIGNED NULL,
    run_type ENUM('manual', 'scheduled', 'api') DEFAULT 'manual',

    -- Execution details
    status ENUM('pending', 'running', 'completed', 'failed', 'cached') DEFAULT 'pending',
    started_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    execution_time_ms INT NULL,

    -- Results
    row_count INT NULL,
    result_cache_key VARCHAR(255) NULL,
    error_message TEXT NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_report (report_id),
    INDEX idx_status (status),
    INDEX idx_tenant (tenant_id),

    FOREIGN KEY (report_id) REFERENCES reports(id) ON DELETE CASCADE,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (run_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS dashboards (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    uuid CHAR(36) NOT NULL UNIQUE,

    name VARCHAR(255) NOT NULL,
    description TEXT NULL,

    -- Dashboard configuration
    layout JSON NOT NULL,  -- Grid layout configuration
    widgets JSON NOT NULL,  -- Array of widget configurations

    -- Sharing
    visibility ENUM('private', 'shared', 'public') DEFAULT 'private',
    owner_id BIGINT UNSIGNED NOT NULL,

    is_default BOOLEAN DEFAULT FALSE,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,

    INDEX idx_owner (owner_id),
    INDEX idx_visibility (visibility),
    INDEX idx_tenant (tenant_id),

    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (owner_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Report cubes for pre-aggregated data (performance optimization)
CREATE TABLE IF NOT EXISTS report_cubes (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,

    cube_name VARCHAR(100) NOT NULL,
    module VARCHAR(50) NOT NULL,
    dimensions JSON NOT NULL,
    measures JSON NOT NULL,

    aggregated_data JSON NOT NULL,
    record_count INT NOT NULL,

    valid_from TIMESTAMP NOT NULL,
    valid_to TIMESTAMP NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_cube (cube_name, module),
    INDEX idx_validity (valid_from, valid_to),
    INDEX idx_tenant (tenant_id),

    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

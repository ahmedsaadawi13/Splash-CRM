-- Migration: Create workflow and automation tables

CREATE TABLE IF NOT EXISTS workflow_definitions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    uuid CHAR(36) NOT NULL UNIQUE,

    name VARCHAR(255) NOT NULL,
    description TEXT NULL,

    -- Trigger configuration
    trigger_type ENUM('manual', 'record_created', 'record_updated', 'record_deleted', 'field_changed', 'scheduled', 'webhook') NOT NULL,
    trigger_module VARCHAR(50) NULL,  -- e.g., 'leads', 'contacts', 'opportunities'
    trigger_conditions JSON NULL,  -- JSON array of conditions

    -- Workflow configuration
    workflow_steps JSON NOT NULL,  -- Array of step definitions

    -- Status
    active BOOLEAN DEFAULT TRUE,
    version INT DEFAULT 1,

    -- Execution stats
    total_runs INT DEFAULT 0,
    successful_runs INT DEFAULT 0,
    failed_runs INT DEFAULT 0,
    last_run_at TIMESTAMP NULL,

    created_by BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,

    INDEX idx_trigger_type (trigger_type),
    INDEX idx_trigger_module (trigger_module),
    INDEX idx_active (active),
    INDEX idx_tenant (tenant_id),

    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS workflow_runs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    workflow_id BIGINT UNSIGNED NOT NULL,
    tenant_id BIGINT UNSIGNED NOT NULL,
    uuid CHAR(36) NOT NULL UNIQUE,

    -- Trigger context
    triggered_by VARCHAR(50) NOT NULL,  -- 'user', 'system', 'webhook'
    triggered_user_id BIGINT UNSIGNED NULL,
    trigger_data JSON NULL,

    -- Related entity
    related_to_type VARCHAR(50) NULL,
    related_to_id BIGINT UNSIGNED NULL,

    -- Execution status
    status ENUM('pending', 'running', 'completed', 'failed', 'cancelled') DEFAULT 'pending',
    started_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,

    -- Results
    steps_executed INT DEFAULT 0,
    steps_total INT DEFAULT 0,
    error_message TEXT NULL,
    execution_log JSON NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_workflow (workflow_id),
    INDEX idx_status (status),
    INDEX idx_related (related_to_type, related_to_id),
    INDEX idx_tenant (tenant_id),

    FOREIGN KEY (workflow_id) REFERENCES workflow_definitions(id) ON DELETE CASCADE,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (triggered_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS workflow_actions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    workflow_run_id BIGINT UNSIGNED NOT NULL,

    step_number INT NOT NULL,
    action_type VARCHAR(50) NOT NULL,  -- 'update_record', 'create_record', 'send_email', 'webhook', etc.
    action_config JSON NOT NULL,

    status ENUM('pending', 'running', 'completed', 'failed', 'skipped') DEFAULT 'pending',
    started_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,

    result JSON NULL,
    error_message TEXT NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_workflow_run (workflow_run_id),
    INDEX idx_status (status),

    FOREIGN KEY (workflow_run_id) REFERENCES workflow_runs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

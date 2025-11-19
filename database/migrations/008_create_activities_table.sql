-- Migration: Create activities table
-- Tasks, calls, meetings, and events

CREATE TABLE IF NOT EXISTS activities (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    uuid CHAR(36) NOT NULL UNIQUE,

    -- Activity details
    subject VARCHAR(255) NOT NULL,
    activity_type ENUM('task', 'call', 'meeting', 'email', 'event', 'note') NOT NULL,
    status ENUM('planned', 'in_progress', 'completed', 'cancelled', 'deferred') DEFAULT 'planned',
    priority ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',

    -- Timing
    due_date DATE NULL,
    start_datetime TIMESTAMP NULL,
    end_datetime TIMESTAMP NULL,
    duration_minutes INT NULL,
    reminder_datetime TIMESTAMP NULL,

    -- Relationships (polymorphic - can relate to multiple entity types)
    related_to_type VARCHAR(50) NULL,  -- 'lead', 'contact', 'account', 'opportunity', etc.
    related_to_id BIGINT UNSIGNED NULL,

    -- Assignment
    owner_id BIGINT UNSIGNED NOT NULL,
    assigned_to_id BIGINT UNSIGNED NULL,

    -- Call/Meeting specifics
    location VARCHAR(255) NULL,
    attendees JSON NULL,

    description TEXT NULL,
    outcome TEXT NULL,
    custom_fields JSON NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,

    INDEX idx_type (activity_type),
    INDEX idx_status (status),
    INDEX idx_priority (priority),
    INDEX idx_due_date (due_date),
    INDEX idx_owner (owner_id),
    INDEX idx_assigned (assigned_to_id),
    INDEX idx_related (related_to_type, related_to_id),
    INDEX idx_tenant (tenant_id),
    FULLTEXT idx_search (subject, description),

    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (owner_id) REFERENCES users(id),
    FOREIGN KEY (assigned_to_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

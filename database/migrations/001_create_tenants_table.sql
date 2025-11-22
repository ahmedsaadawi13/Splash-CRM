-- Migration: Create tenants table
-- Multi-tenancy support for the CRM

CREATE TABLE IF NOT EXISTS tenants (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL UNIQUE,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(100) NOT NULL UNIQUE,
    domain VARCHAR(255) NULL,
    logo VARCHAR(500) NULL,
    settings JSON NULL,
    status ENUM('active', 'suspended', 'trial', 'inactive') DEFAULT 'active',
    trial_ends_at TIMESTAMP NULL,
    subscription_ends_at TIMESTAMP NULL,
    max_users INT UNSIGNED DEFAULT 10,
    max_storage_gb INT UNSIGNED DEFAULT 10,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    INDEX idx_slug (slug),
    INDEX idx_status (status),
    INDEX idx_domain (domain)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

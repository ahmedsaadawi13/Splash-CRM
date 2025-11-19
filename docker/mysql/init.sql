-- MySQL initialization script for Splash CRM

-- Ensure utf8mb4 charset
ALTER DATABASE splash_crm CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Grant privileges
GRANT ALL PRIVILEGES ON splash_crm.* TO 'splash_user'@'%';
FLUSH PRIVILEGES;

-- Set timezone
SET GLOBAL time_zone = '+00:00';

# Changelog

All notable changes to Splash CRM will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- Multi-tenant architecture with tenant isolation
- JWT-based authentication with refresh tokens
- Comprehensive RBAC system with roles and permissions
- Field-level permission controls
- Core CRM modules (Leads, Contacts, Accounts, Opportunities, Activities)
- Product and pricing management
- Quote and invoice generation
- Workflow definitions and execution engine (structure)
- Report definitions and query builder (structure)
- Dashboard configuration system
- Audit logging for all changes
- File attachment support with S3 integration
- Notes and tags system
- Database migrations (14 comprehensive migrations)
- Demo data seeders
- Docker development environment
- RESTful API with JSON responses
- API documentation
- Comprehensive README

### Security
- Password hashing with bcrypt
- JWT token signing and validation
- SQL injection protection via Eloquent ORM
- XSS protection
- Input validation
- CORS middleware

## [0.1.0] - 2024-01-15

### Added
- Initial project scaffolding
- Basic authentication system
- Database schema design
- Docker configuration
- API routing structure

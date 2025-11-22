# Splash CRM

> A comprehensive, enterprise-grade CRM system with advanced reporting, workflows, multi-tenancy, and ML integration capabilities.

[![PHP Version](https://img.shields.io/badge/php-%3E%3D7.3-blue.svg)](https://php.net)
[![MySQL](https://img.shields.io/badge/mysql-%3E%3D8.0-orange.svg)](https://www.mysql.com/)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)

## Features

- **Multi-Tenant Architecture**: Fully isolated tenant data with subscription management
- **Comprehensive CRM Modules**: Leads, Contacts, Accounts, Opportunities, Activities, Products, Quotes, Invoices
- **Advanced RBAC**: Role-based access control with field-level permissions
- **Workflow Automation**: Visual workflow builder with triggers, conditions, and actions
- **Powerful Reporting**: Custom report builder with exports (CSV, Excel, PDF)
- **RESTful API**: Complete JSON REST API with JWT authentication
- **Audit Logging**: Complete change history tracking
- **File Management**: S3-compatible storage for attachments
- **Multi-language Support**: i18n ready
- **PWA Ready**: Progressive web app for mobile access
- **ML/AI Integration**: Predictive scoring and analytics hooks

## Architecture

### Technology Stack

- **Backend**: PHP 7.3+ with Slim Framework
- **Database**: MySQL 8.0+ (utf8mb4)
- **Cache/Queue**: Redis
- **Authentication**: JWT with refresh tokens
- **ORM**: Eloquent (standalone)
- **API**: RESTful JSON
- **Frontend**: Vue 3 + Vite (optional, API-first design)

## Quick Start

### Prerequisites

- Docker & Docker Compose (recommended)
- OR: PHP 7.3+, MySQL 8.0+, Redis, Composer

### Installation with Docker (Recommended)

1. **Clone the repository**
   ```bash
   git clone https://github.com/your-org/splash-crm.git
   cd splash-crm
   ```

2. **Copy environment file**
   ```bash
   cp .env.example .env
   ```

3. **Update `.env` file**
   ```bash
   # Generate JWT secret
   JWT_SECRET=$(openssl rand -base64 32)

   # Update .env with the generated secret
   sed -i "s/JWT_SECRET=/JWT_SECRET=$JWT_SECRET/" .env
   ```

4. **Start Docker containers**
   ```bash
   docker-compose up -d
   ```

5. **Install dependencies**
   ```bash
   docker-compose exec app composer install
   ```

6. **Run database migrations**
   ```bash
   docker-compose exec app php bin/migrate.php
   ```

7. **Seed demo data (optional)**
   ```bash
   docker-compose exec app php bin/seed.php
   ```

8. **Access the application**
   - API: http://localhost:8000
   - PHPMyAdmin: http://localhost:8080
   - MailHog: http://localhost:8025

### Demo Credentials

After seeding, use these credentials to login:

**Administrator:**
- Email: `admin@demo.com`
- Password: `password123`

**Sales Representative:**
- Email: `sales@demo.com`
- Password: `password123`

## API Documentation

### Authentication

**Login**
```bash
POST /api/v1/auth/login
Content-Type: application/json

{
  "email": "admin@demo.com",
  "password": "password123"
}
```

**Response:**
```json
{
  "success": true,
  "message": "Login successful",
  "data": {
    "access_token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
    "refresh_token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
    "token_type": "Bearer",
    "expires_in": 3600
  }
}
```

**Using the Access Token**
```bash
GET /api/v1/leads
Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGc...
```

### Core Endpoints

#### Leads
- `GET /api/v1/leads` - List all leads
- `POST /api/v1/leads` - Create a new lead
- `GET /api/v1/leads/{id}` - Get a specific lead
- `PUT /api/v1/leads/{id}` - Update a lead
- `DELETE /api/v1/leads/{id}` - Delete a lead

#### Contacts
- `GET /api/v1/contacts` - List all contacts
- `POST /api/v1/contacts` - Create a new contact
- `GET /api/v1/contacts/{id}` - Get a specific contact
- `PUT /api/v1/contacts/{id}` - Update a contact
- `DELETE /api/v1/contacts/{id}` - Delete a contact

#### Accounts
- `GET /api/v1/accounts` - List all accounts
- `POST /api/v1/accounts` - Create a new account
- `GET /api/v1/accounts/{id}` - Get a specific account
- `PUT /api/v1/accounts/{id}` - Update an account
- `DELETE /api/v1/accounts/{id}` - Delete an account

#### Opportunities
- `GET /api/v1/opportunities` - List all opportunities
- `POST /api/v1/opportunities` - Create a new opportunity
- `GET /api/v1/opportunities/{id}` - Get a specific opportunity
- `PUT /api/v1/opportunities/{id}` - Update an opportunity
- `DELETE /api/v1/opportunities/{id}` - Delete an opportunity

## Database Schema

The system includes 14 comprehensive migration files covering:

- **Core**: Tenants, Users, Roles, Permissions
- **CRM**: Leads, Contacts, Accounts, Opportunities, Activities
- **Sales**: Products, Quotes, Invoices, Payments
- **Automation**: Workflows, Reports, Dashboards
- **Support**: Audit Logs, Attachments, Notes, Tags

## Project Structure

```
splash-crm/
├── app/
│   ├── Http/
│   │   ├── Controllers/      # API controllers
│   │   └── Middleware/       # Authentication, CORS, etc.
│   ├── Models/               # Eloquent models
│   ├── Services/             # Business logic services
│   └── helpers.php           # Helper functions
├── bin/
│   ├── migrate.php           # Migration runner
│   └── seed.php              # Seeder runner
├── config/                   # Configuration files
├── database/
│   ├── migrations/           # SQL migration files
│   └── seeders/              # Data seeders
├── docker/                   # Docker configuration
├── public/
│   └── index.php             # Application entry point
├── routes/
│   ├── api.php               # API routes
│   └── web.php               # Web routes
├── storage/                  # Logs, cache, uploads
├── composer.json             # PHP dependencies
├── docker-compose.yml        # Docker services
└── README.md                 # This file
```

## Development

### Running Tests

```bash
composer test
```

### Code Quality

```bash
# Static analysis
composer phpstan

# Code style check
composer phpcs

# Auto-fix code style
composer phpcbf
```

## Security

- **Password Hashing**: bcrypt with automatic rehashing
- **JWT Authentication**: Signed tokens with refresh capability
- **RBAC**: Role and permission-based access control
- **Field-Level Security**: Granular field permissions
- **SQL Injection Protection**: Parameterized queries via Eloquent
- **Audit Logging**: All changes tracked

## License

MIT License - see LICENSE file for details

---

**Splash CRM** - Enterprise CRM, Simplified
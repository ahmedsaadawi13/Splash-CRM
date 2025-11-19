# Getting Started with Splash CRM

This guide will help you get Splash CRM up and running in minutes.

## Prerequisites

- Docker Desktop (recommended) OR
- PHP 7.3+, MySQL 8.0+, Redis, Composer

## Quick Start (5 minutes)

### 1. Clone and Setup Environment

```bash
# Navigate to the project directory
cd splash-crm

# Copy environment file
cp .env.example .env

# Generate JWT secret
JWT_SECRET=$(openssl rand -base64 32)
sed -i "s/JWT_SECRET=/JWT_SECRET=$JWT_SECRET/" .env
```

### 2. Start Docker Services

```bash
# Start all services
docker-compose up -d

# View logs (optional)
docker-compose logs -f
```

**Services started:**
- ✅ PHP Application (port 9000)
- ✅ Nginx Web Server (port 8000)
- ✅ MySQL Database (port 3306)
- ✅ Redis Cache (port 6379)
- ✅ PHPMyAdmin (port 8080)
- ✅ MailHog (port 8025)

### 3. Install Dependencies

```bash
docker-compose exec app composer install
```

### 4. Run Database Migrations

```bash
docker-compose exec app php bin/migrate.php
```

This will create 14 tables:
- ✅ tenants, users, roles, permissions
- ✅ accounts, contacts, leads, opportunities, activities
- ✅ products, quotes, invoices, payments
- ✅ workflows, reports, dashboards
- ✅ audit_logs, attachments, notes, tags

### 5. Seed Demo Data (Optional)

```bash
docker-compose exec app php bin/seed.php
```

This creates:
- ✅ Demo tenant: "Demo Corporation"
- ✅ Admin user: admin@demo.com / password123
- ✅ Sales user: sales@demo.com / password123
- ✅ Sample accounts, contacts, leads, and opportunities
- ✅ Sample products
- ✅ All permissions

### 6. Test the API

```bash
# Health check
curl http://localhost:8000/health

# Login
curl -X POST http://localhost:8000/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "admin@demo.com",
    "password": "password123"
  }'
```

**Expected response:**
```json
{
  "success": true,
  "message": "Login successful",
  "data": {
    "access_token": "eyJ0eXAiOiJKV1Qi...",
    "refresh_token": "eyJ0eXAiOiJKV1Qi...",
    "token_type": "Bearer",
    "expires_in": 3600,
    "user": {
      "id": 1,
      "uuid": "...",
      "first_name": "Admin",
      "last_name": "User",
      "email": "admin@demo.com",
      "tenant_id": 1
    }
  }
}
```

### 7. Use the Access Token

```bash
# Get current user info
curl http://localhost:8000/api/v1/auth/me \
  -H "Authorization: Bearer YOUR_ACCESS_TOKEN"

# List leads
curl http://localhost:8000/api/v1/leads \
  -H "Authorization: Bearer YOUR_ACCESS_TOKEN"
```

## Access Points

| Service | URL | Credentials |
|---------|-----|-------------|
| **API** | http://localhost:8000 | N/A |
| **Web UI** | http://localhost:8000 | admin@demo.com / password123 |
| **PHPMyAdmin** | http://localhost:8080 | splash_user / splash_password |
| **MailHog** | http://localhost:8025 | N/A |

## Common Tasks

### View Logs

```bash
# Application logs
docker-compose exec app tail -f storage/logs/app.log

# Nginx logs
docker-compose logs nginx

# MySQL logs
docker-compose logs mysql
```

### Run Commands

```bash
# Execute any PHP script
docker-compose exec app php your-script.php

# Access MySQL CLI
docker-compose exec mysql mysql -u splash_user -psplash_password splash_crm

# Access Redis CLI
docker-compose exec redis redis-cli
```

### Stop Services

```bash
# Stop all services
docker-compose down

# Stop and remove volumes (WARNING: deletes all data)
docker-compose down -v
```

### Reset Database

```bash
# Drop all tables and re-run migrations
docker-compose exec mysql mysql -u root -proot_password -e "DROP DATABASE splash_crm; CREATE DATABASE splash_crm CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
docker-compose exec app php bin/migrate.php
docker-compose exec app php bin/seed.php
```

## Testing the API

### 1. Register a New User

```bash
curl -X POST http://localhost:8000/api/v1/auth/register \
  -H "Content-Type: application/json" \
  -d '{
    "tenant_name": "My Company",
    "first_name": "John",
    "last_name": "Doe",
    "email": "john@mycompany.com",
    "password": "secure_password_123"
  }'
```

### 2. Create a Lead

```bash
curl -X POST http://localhost:8000/api/v1/leads \
  -H "Authorization: Bearer YOUR_ACCESS_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "first_name": "Jane",
    "last_name": "Smith",
    "company": "Tech Corp",
    "email": "jane@techcorp.com",
    "phone": "555-1234",
    "lead_status": "new",
    "rating": "hot",
    "lead_source": "Website"
  }'
```

### 3. List All Leads

```bash
curl http://localhost:8000/api/v1/leads \
  -H "Authorization: Bearer YOUR_ACCESS_TOKEN"
```

### 4. Get Specific Lead

```bash
curl http://localhost:8000/api/v1/leads/1 \
  -H "Authorization: Bearer YOUR_ACCESS_TOKEN"
```

### 5. Update Lead

```bash
curl -X PUT http://localhost:8000/api/v1/leads/1 \
  -H "Authorization: Bearer YOUR_ACCESS_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "lead_status": "contacted",
    "description": "Follow up scheduled for next week"
  }'
```

### 6. Delete Lead

```bash
curl -X DELETE http://localhost:8000/api/v1/leads/1 \
  -H "Authorization: Bearer YOUR_ACCESS_TOKEN"
```

## Troubleshooting

### Port Already in Use

If you get "port already allocated" errors:

```bash
# Change ports in docker-compose.yml
# For example, change "8000:80" to "8001:80" for nginx
```

### Permission Errors

```bash
# Fix storage permissions
docker-compose exec app chmod -R 777 storage
```

### Database Connection Failed

```bash
# Check MySQL is running
docker-compose ps mysql

# View MySQL logs
docker-compose logs mysql

# Restart MySQL
docker-compose restart mysql
```

### Composer Install Fails

```bash
# Clear composer cache
docker-compose exec app rm -rf vendor
docker-compose exec app composer clear-cache
docker-compose exec app composer install
```

## Development Workflow

1. **Make code changes** in your local files
2. **Changes are reflected immediately** (mounted volumes)
3. **Run tests** (when implemented): `docker-compose exec app composer test`
4. **Check code quality**: `docker-compose exec app composer phpstan`
5. **Format code**: `docker-compose exec app composer phpcbf`

## Next Steps

### For Developers

1. **Implement Controllers**: Add CRUD logic to controllers in `app/Http/Controllers/`
2. **Add Business Logic**: Create service classes in `app/Services/`
3. **Write Tests**: Add PHPUnit tests in `tests/`
4. **Add Validation**: Implement request validation
5. **Build Frontend**: Create Vue 3 frontend

### For Users

1. **Explore API**: Try all endpoints listed in README.md
2. **Import Data**: Use API to bulk import your data
3. **Customize**: Modify models and migrations for your needs
4. **Extend**: Add custom fields using JSON columns

## Need Help?

- 📖 **Documentation**: See [README.md](README.md)
- 🐛 **Issues**: Report bugs on GitHub
- 💬 **Discussions**: Join community discussions
- 📧 **Email**: support@splashcrm.com

## What's Built

✅ **Core Infrastructure**
- Multi-tenant architecture
- JWT authentication
- RBAC with field-level permissions
- RESTful API structure
- Docker development environment

✅ **Database Schema**
- 14 comprehensive migrations
- All core CRM tables
- Proper indexes and constraints
- utf8mb4 support

✅ **API Endpoints**
- Authentication (login, register, refresh, logout)
- User management (me endpoint)
- All CRUD routes defined

✅ **Models & Services**
- Eloquent models for all entities
- JWT service
- Base controller with helpers
- Auth middleware

✅ **Demo Data**
- Sample tenant
- Admin and sales users
- Sample CRM records
- All permissions

## What's Next (MVP Completion)

⏳ **Immediate Priorities**
- [ ] Implement CRUD controller logic
- [ ] Add pagination and filtering
- [ ] Frontend UI (Vue 3)
- [ ] Unit and integration tests

⏳ **Phase 2**
- [ ] Workflow engine
- [ ] Report builder
- [ ] Email integration
- [ ] Calendar integration

⏳ **Phase 3**
- [ ] ML/AI predictive scoring
- [ ] Advanced analytics
- [ ] Plugin marketplace
- [ ] Mobile app

---

**Splash CRM** - Ready to build upon! 🚀

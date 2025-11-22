# Splash CRM API Testing Guide

Complete guide for testing all API endpoints with cURL examples.

## Prerequisites

1. **Start the application:**
   ```bash
   docker-compose up -d
   docker-compose exec app composer install
   docker-compose exec app php bin/migrate.php
   docker-compose exec app php bin/seed.php
   ```

2. **Set base URL:**
   ```bash
   API_URL="http://localhost:8000/api/v1"
   ```

---

## Authentication

### 1. Login

```bash
# Login and save token
TOKEN=$(curl -s -X POST $API_URL/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "admin@demo.com",
    "password": "password123"
  }' | jq -r '.data.access_token')

echo "Token: $TOKEN"
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

### 2. Get Current User

```bash
curl -s $API_URL/auth/me \
  -H "Authorization: Bearer $TOKEN" | jq
```

### 3. Refresh Token

```bash
curl -s -X POST $API_URL/auth/refresh \
  -H "Content-Type: application/json" \
  -d "{\"refresh_token\": \"$REFRESH_TOKEN\"}" | jq
```

### 4. Logout

```bash
curl -s -X POST $API_URL/auth/logout \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d "{\"refresh_token\": \"$REFRESH_TOKEN\"}" | jq
```

---

## Leads

### List Leads (with pagination and filters)

```bash
# Simple list
curl -s "$API_URL/leads" \
  -H "Authorization: Bearer $TOKEN" | jq

# With pagination
curl -s "$API_URL/leads?page=1&per_page=10" \
  -H "Authorization: Bearer $TOKEN" | jq

# With search
curl -s "$API_URL/leads?search=john" \
  -H "Authorization: Bearer $TOKEN" | jq

# With filters
curl -s "$API_URL/leads?status=new&rating=hot&lead_source=Website" \
  -H "Authorization: Bearer $TOKEN" | jq

# With sorting
curl -s "$API_URL/leads?sort=created_at&order=DESC" \
  -H "Authorization: Bearer $TOKEN" | jq

# Combined
curl -s "$API_URL/leads?page=1&per_page=20&search=tech&status=new&sort=score&order=DESC" \
  -H "Authorization: Bearer $TOKEN" | jq
```

### Create Lead

```bash
curl -s -X POST $API_URL/leads \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "first_name": "John",
    "last_name": "Doe",
    "company": "Tech Solutions Inc",
    "title": "CTO",
    "email": "john.doe@techsolutions.com",
    "phone": "555-1234",
    "mobile": "555-5678",
    "website": "https://techsolutions.com",
    "street": "123 Tech Street",
    "city": "San Francisco",
    "state": "CA",
    "postal_code": "94105",
    "country": "USA",
    "industry": "Technology",
    "lead_source": "Website",
    "lead_status": "new",
    "rating": "hot",
    "annual_revenue": 5000000,
    "employees": 50,
    "description": "Interested in enterprise solution"
  }' | jq
```

### Get Single Lead

```bash
curl -s "$API_URL/leads/1" \
  -H "Authorization: Bearer $TOKEN" | jq
```

### Update Lead

```bash
curl -s -X PUT $API_URL/leads/1 \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "lead_status": "contacted",
    "rating": "warm",
    "description": "Had initial call, very interested"
  }' | jq
```

### Convert Lead

```bash
# Convert lead to account + contact
curl -s -X POST $API_URL/leads/1/convert \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{}' | jq

# Convert lead to account + contact + opportunity
curl -s -X POST $API_URL/leads/1/convert \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "create_opportunity": true,
    "opportunity_name": "Enterprise Solution Deal",
    "amount": 75000,
    "stage": "Prospecting",
    "probability": 20
  }' | jq
```

### Delete Lead

```bash
curl -s -X DELETE $API_URL/leads/1 \
  -H "Authorization: Bearer $TOKEN" | jq
```

---

## Contacts

### List Contacts

```bash
# Simple list
curl -s "$API_URL/contacts" \
  -H "Authorization: Bearer $TOKEN" | jq

# Filter by account
curl -s "$API_URL/contacts?account_id=1" \
  -H "Authorization: Bearer $TOKEN" | jq

# Search
curl -s "$API_URL/contacts?search=john&page=1&per_page=20" \
  -H "Authorization: Bearer $TOKEN" | jq
```

### Create Contact

```bash
curl -s -X POST $API_URL/contacts \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "account_id": 1,
    "salutation": "Mr.",
    "first_name": "John",
    "last_name": "Smith",
    "title": "CEO",
    "department": "Executive",
    "email": "john.smith@acme.com",
    "phone": "555-0101",
    "mobile": "555-0102",
    "mailing_street": "456 Business Ave",
    "mailing_city": "New York",
    "mailing_state": "NY",
    "mailing_postal_code": "10001",
    "mailing_country": "USA",
    "birthdate": "1975-05-15",
    "lead_source": "Referral",
    "status": "active"
  }' | jq
```

### Get Single Contact

```bash
curl -s "$API_URL/contacts/1" \
  -H "Authorization: Bearer $TOKEN" | jq
```

### Update Contact

```bash
curl -s -X PUT $API_URL/contacts/1 \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "title": "Chief Executive Officer",
    "phone": "555-9999"
  }' | jq
```

### Delete Contact

```bash
curl -s -X DELETE $API_URL/contacts/1 \
  -H "Authorization: Bearer $TOKEN" | jq
```

---

## Accounts

### List Accounts

```bash
# Simple list
curl -s "$API_URL/accounts" \
  -H "Authorization: Bearer $TOKEN" | jq

# Filter by type and industry
curl -s "$API_URL/accounts?account_type=customer&industry=Technology" \
  -H "Authorization: Bearer $TOKEN" | jq

# Search
curl -s "$API_URL/accounts?search=acme" \
  -H "Authorization: Bearer $TOKEN" | jq
```

### Create Account

```bash
curl -s -X POST $API_URL/accounts \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "account_name": "Global Tech Solutions",
    "account_type": "customer",
    "industry": "Technology",
    "website": "https://globaltech.com",
    "phone": "555-1000",
    "email": "info@globaltech.com",
    "annual_revenue": 10000000,
    "employees": 200,
    "rating": "hot",
    "ownership": "private",
    "billing_street": "100 Tech Plaza",
    "billing_city": "Seattle",
    "billing_state": "WA",
    "billing_postal_code": "98101",
    "billing_country": "USA",
    "description": "Leading technology solutions provider"
  }' | jq
```

### Get Single Account

```bash
curl -s "$API_URL/accounts/1" \
  -H "Authorization: Bearer $TOKEN" | jq
```

### Update Account

```bash
curl -s -X PUT $API_URL/accounts/1 \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "annual_revenue": 15000000,
    "employees": 250
  }' | jq
```

### Delete Account

```bash
curl -s -X DELETE $API_URL/accounts/1 \
  -H "Authorization: Bearer $TOKEN" | jq
```

---

## Opportunities

### List Opportunities

```bash
# Simple list
curl -s "$API_URL/opportunities" \
  -H "Authorization: Bearer $TOKEN" | jq

# Filter by stage and status
curl -s "$API_URL/opportunities?stage=Proposal&status=open" \
  -H "Authorization: Bearer $TOKEN" | jq

# Filter by account
curl -s "$API_URL/opportunities?account_id=1" \
  -H "Authorization: Bearer $TOKEN" | jq

# Sort by amount
curl -s "$API_URL/opportunities?sort=amount&order=DESC" \
  -H "Authorization: Bearer $TOKEN" | jq
```

### Create Opportunity

```bash
curl -s -X POST $API_URL/opportunities \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "opportunity_name": "Q1 Enterprise License",
    "account_id": 1,
    "contact_id": 1,
    "amount": 100000,
    "stage": "Prospecting",
    "probability": 20,
    "expected_close_date": "2025-03-31",
    "opportunity_type": "new_business",
    "lead_source": "Website",
    "status": "open",
    "description": "Annual enterprise license opportunity",
    "next_step": "Schedule demo presentation"
  }' | jq
```

### Get Single Opportunity

```bash
curl -s "$API_URL/opportunities/1" \
  -H "Authorization: Bearer $TOKEN" | jq
```

### Update Opportunity

```bash
curl -s -X PUT $API_URL/opportunities/1 \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "stage": "Proposal",
    "probability": 60,
    "amount": 120000,
    "next_step": "Send proposal and pricing"
  }' | jq
```

### Mark Opportunity as Won

```bash
curl -s -X PUT $API_URL/opportunities/1 \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "status": "won",
    "stage": "Closed Won"
  }' | jq
```

### Delete Opportunity

```bash
curl -s -X DELETE $API_URL/opportunities/1 \
  -H "Authorization: Bearer $TOKEN" | jq
```

---

## Activities

### List Activities

```bash
# Simple list
curl -s "$API_URL/activities" \
  -H "Authorization: Bearer $TOKEN" | jq

# Filter by type
curl -s "$API_URL/activities?activity_type=task&status=planned" \
  -H "Authorization: Bearer $TOKEN" | jq

# Filter by related entity
curl -s "$API_URL/activities?related_to_type=lead&related_to_id=1" \
  -H "Authorization: Bearer $TOKEN" | jq

# Sort by due date
curl -s "$API_URL/activities?sort=due_date&order=ASC" \
  -H "Authorization: Bearer $TOKEN" | jq
```

### Create Task

```bash
curl -s -X POST $API_URL/activities \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "subject": "Follow up on proposal",
    "activity_type": "task",
    "status": "planned",
    "priority": "high",
    "due_date": "2025-02-01",
    "related_to_type": "opportunity",
    "related_to_id": 1,
    "assigned_to_id": 2,
    "description": "Follow up with client about proposal status"
  }' | jq
```

### Create Meeting

```bash
curl -s -X POST $API_URL/activities \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "subject": "Product Demo",
    "activity_type": "meeting",
    "status": "planned",
    "priority": "high",
    "start_datetime": "2025-02-05 14:00:00",
    "end_datetime": "2025-02-05 15:00:00",
    "duration_minutes": 60,
    "location": "Conference Room A",
    "related_to_type": "account",
    "related_to_id": 1,
    "attendees": ["john@acme.com", "jane@acme.com"],
    "description": "Product demonstration for key stakeholders"
  }' | jq
```

### Get Single Activity

```bash
curl -s "$API_URL/activities/1" \
  -H "Authorization: Bearer $TOKEN" | jq
```

### Update Activity

```bash
curl -s -X PUT $API_URL/activities/1 \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "status": "completed",
    "outcome": "Client approved proposal, moving to contract phase"
  }' | jq
```

### Delete Activity

```bash
curl -s -X DELETE $API_URL/activities/1 \
  -H "Authorization: Bearer $TOKEN" | jq
```

---

## Products

### List Products

```bash
# Simple list
curl -s "$API_URL/products" \
  -H "Authorization: Bearer $TOKEN" | jq

# Filter by category and active status
curl -s "$API_URL/products?product_category=Software&active=true" \
  -H "Authorization: Bearer $TOKEN" | jq

# Search
curl -s "$API_URL/products?search=enterprise" \
  -H "Authorization: Bearer $TOKEN" | jq
```

### Create Product

```bash
curl -s -X POST $API_URL/products \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "product_name": "Enterprise CRM License",
    "product_code": "CRM-ENT-2025",
    "product_category": "Software",
    "product_family": "CRM Solutions",
    "unit_price": 299.00,
    "cost_price": 100.00,
    "list_price": 399.00,
    "sku": "SKU-CRM-ENT-001",
    "quantity_in_stock": 1000,
    "reorder_level": 100,
    "active": true,
    "taxable": true,
    "vendor_name": "Internal",
    "description": "Full-featured enterprise CRM license with unlimited users"
  }' | jq
```

### Get Single Product

```bash
curl -s "$API_URL/products/1" \
  -H "Authorization: Bearer $TOKEN" | jq
```

### Update Product

```bash
curl -s -X PUT $API_URL/products/1 \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "unit_price": 349.00,
    "quantity_in_stock": 950
  }' | jq
```

### Delete Product

```bash
curl -s -X DELETE $API_URL/products/1 \
  -H "Authorization: Bearer $TOKEN" | jq
```

---

## Advanced Usage

### Bash Script for Complete Workflow

```bash
#!/bin/bash

API_URL="http://localhost:8000/api/v1"

# 1. Login
echo "1. Logging in..."
TOKEN=$(curl -s -X POST $API_URL/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@demo.com","password":"password123"}' \
  | jq -r '.data.access_token')

echo "Token obtained: ${TOKEN:0:20}..."

# 2. Create Lead
echo -e "\n2. Creating lead..."
LEAD_ID=$(curl -s -X POST $API_URL/leads \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "first_name": "Jane",
    "last_name": "Smith",
    "company": "Innovation Corp",
    "email": "jane@innovation.com",
    "lead_status": "new",
    "rating": "hot"
  }' | jq -r '.data.id')

echo "Lead created with ID: $LEAD_ID"

# 3. Update Lead
echo -e "\n3. Updating lead..."
curl -s -X PUT $API_URL/leads/$LEAD_ID \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"lead_status": "qualified"}' | jq '.message'

# 4. Convert Lead
echo -e "\n4. Converting lead..."
CONVERSION=$(curl -s -X POST $API_URL/leads/$LEAD_ID/convert \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "create_opportunity": true,
    "opportunity_name": "Innovation Corp Deal",
    "amount": 50000,
    "stage": "Prospecting"
  }')

ACCOUNT_ID=$(echo $CONVERSION | jq -r '.data.account.id')
OPPORTUNITY_ID=$(echo $CONVERSION | jq -r '.data.opportunity.id')

echo "Account created with ID: $ACCOUNT_ID"
echo "Opportunity created with ID: $OPPORTUNITY_ID"

# 5. Create Activity
echo -e "\n5. Creating follow-up activity..."
curl -s -X POST $API_URL/activities \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d "{
    \"subject\": \"Follow up on Innovation Corp deal\",
    \"activity_type\": \"task\",
    \"due_date\": \"2025-02-15\",
    \"related_to_type\": \"opportunity\",
    \"related_to_id\": $OPPORTUNITY_ID,
    \"priority\": \"high\"
  }" | jq '.message'

# 6. List all opportunities
echo -e "\n6. Listing all opportunities..."
curl -s "$API_URL/opportunities" \
  -H "Authorization: Bearer $TOKEN" \
  | jq '.data.opportunities[] | {id, name: .opportunity_name, amount, stage}'

echo -e "\n✓ Workflow complete!"
```

### Python Script Example

```python
import requests
import json

API_URL = "http://localhost:8000/api/v1"

# Login
response = requests.post(f"{API_URL}/auth/login", json={
    "email": "admin@demo.com",
    "password": "password123"
})
token = response.json()['data']['access_token']

headers = {
    "Authorization": f"Bearer {token}",
    "Content-Type": "application/json"
}

# Create lead
lead = requests.post(f"{API_URL}/leads", headers=headers, json={
    "first_name": "Alice",
    "last_name": "Johnson",
    "company": "Future Tech",
    "email": "alice@futuretech.com",
    "lead_status": "new"
})
print(f"Created lead: {lead.json()['data']['id']}")

# List leads
leads = requests.get(f"{API_URL}/leads?page=1&per_page=10", headers=headers)
print(f"Total leads: {leads.json()['data']['pagination']['total']}")
```

---

## Response Status Codes

- `200 OK` - Successful GET, PUT, DELETE
- `201 Created` - Successful POST (resource created)
- `400 Bad Request` - Invalid request data
- `401 Unauthorized` - Missing or invalid token
- `403 Forbidden` - Insufficient permissions
- `404 Not Found` - Resource not found
- `422 Unprocessable Entity` - Validation failed
- `500 Internal Server Error` - Server error

---

## Common Errors

### Invalid Token
```json
{
  "success": false,
  "message": "Invalid or expired token"
}
```

### Validation Error
```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "email": "Invalid email format",
    "amount": "Amount must be a number"
  }
}
```

### Not Found
```json
{
  "success": false,
  "message": "Lead not found"
}
```

---

## Tips

1. **Save your token** in a variable to avoid repeated logins
2. **Use jq** for pretty JSON output and parsing
3. **Check response status** before continuing workflow
4. **Use pagination** for large datasets
5. **Filter and search** to reduce response size
6. **Test with demo data** before using production data

---

**Happy Testing!** 🚀

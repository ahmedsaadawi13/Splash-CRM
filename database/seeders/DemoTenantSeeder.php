<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\User;
use App\Models\Role;
use App\Models\Account;
use App\Models\Contact;
use App\Models\Lead;
use App\Models\Opportunity;
use App\Models\Product;
use Illuminate\Database\Capsule\Manager as DB;
use Ramsey\Uuid\Uuid;

class DemoTenantSeeder
{
    private $tenant;
    private $adminUser;
    private $salesUser;

    public function run()
    {
        echo "→ Seeding demo tenant...\n";

        DB::beginTransaction();

        try {
            $this->createTenant();
            $this->createRoles();
            $this->createUsers();
            $this->createAccounts();
            $this->createContacts();
            $this->createLeads();
            $this->createOpportunities();
            $this->createProducts();

            DB::commit();

            echo "✓ Demo tenant seeded successfully\n";
            echo "\n=================================\n";
            echo "Demo Credentials:\n";
            echo "=================================\n";
            echo "Admin User:\n";
            echo "  Email: admin@demo.com\n";
            echo "  Password: password123\n";
            echo "\nSales User:\n";
            echo "  Email: sales@demo.com\n";
            echo "  Password: password123\n";
            echo "=================================\n\n";

        } catch (\Exception $e) {
            DB::rollBack();
            echo "✗ Error seeding demo tenant: " . $e->getMessage() . "\n";
            throw $e;
        }
    }

    private function createTenant()
    {
        $this->tenant = new Tenant();
        $this->tenant->uuid = Uuid::uuid4()->toString();
        $this->tenant->name = 'Demo Corporation';
        $this->tenant->slug = 'demo-corp';
        $this->tenant->domain = 'demo.splashcrm.local';
        $this->tenant->status = 'active';
        $this->tenant->max_users = 50;
        $this->tenant->max_storage_gb = 100;
        $this->tenant->save();

        echo "  ✓ Created tenant: {$this->tenant->name}\n";
    }

    private function createRoles()
    {
        // Admin role
        $adminRole = new Role();
        $adminRole->tenant_id = $this->tenant->id;
        $adminRole->name = 'Administrator';
        $adminRole->slug = 'admin';
        $adminRole->description = 'Full system access';
        $adminRole->is_system = true;
        $adminRole->save();

        // Assign all permissions to admin
        $permissions = DB::table('permissions')->pluck('id');
        foreach ($permissions as $permissionId) {
            DB::table('role_permission')->insert([
                'role_id' => $adminRole->id,
                'permission_id' => $permissionId,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        }

        // Sales role
        $salesRole = new Role();
        $salesRole->tenant_id = $this->tenant->id;
        $salesRole->name = 'Sales Representative';
        $salesRole->slug = 'sales';
        $salesRole->description = 'Standard sales user';
        $salesRole->save();

        // Assign sales permissions
        $salesPermissions = DB::table('permissions')
            ->whereIn('module', ['leads', 'contacts', 'accounts', 'opportunities', 'activities', 'products'])
            ->whereIn('slug', [
                'leads.view', 'leads.create', 'leads.edit',
                'contacts.view', 'contacts.create', 'contacts.edit',
                'accounts.view', 'accounts.create', 'accounts.edit',
                'opportunities.view', 'opportunities.create', 'opportunities.edit',
                'activities.view', 'activities.create', 'activities.edit',
                'products.view',
            ])
            ->pluck('id');

        foreach ($salesPermissions as $permissionId) {
            DB::table('role_permission')->insert([
                'role_id' => $salesRole->id,
                'permission_id' => $permissionId,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        }

        echo "  ✓ Created roles\n";
    }

    private function createUsers()
    {
        // Admin user
        $this->adminUser = new User();
        $this->adminUser->tenant_id = $this->tenant->id;
        $this->adminUser->uuid = Uuid::uuid4()->toString();
        $this->adminUser->first_name = 'Admin';
        $this->adminUser->last_name = 'User';
        $this->adminUser->email = 'admin@demo.com';
        $this->adminUser->setPassword('password123');
        $this->adminUser->status = 'active';
        $this->adminUser->email_verified_at = date('Y-m-d H:i:s');
        $this->adminUser->save();

        // Assign admin role
        DB::table('role_user')->insert([
            'user_id' => $this->adminUser->id,
            'role_id' => Role::where('slug', 'admin')->where('tenant_id', $this->tenant->id)->first()->id,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        // Sales user
        $this->salesUser = new User();
        $this->salesUser->tenant_id = $this->tenant->id;
        $this->salesUser->uuid = Uuid::uuid4()->toString();
        $this->salesUser->first_name = 'Sales';
        $this->salesUser->last_name = 'Representative';
        $this->salesUser->email = 'sales@demo.com';
        $this->salesUser->setPassword('password123');
        $this->salesUser->status = 'active';
        $this->salesUser->email_verified_at = date('Y-m-d H:i:s');
        $this->salesUser->save();

        // Assign sales role
        DB::table('role_user')->insert([
            'user_id' => $this->salesUser->id,
            'role_id' => Role::where('slug', 'sales')->where('tenant_id', $this->tenant->id)->first()->id,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        echo "  ✓ Created users\n";
    }

    private function createAccounts()
    {
        $accounts = [
            [
                'account_name' => 'Acme Corporation',
                'account_type' => 'customer',
                'industry' => 'Technology',
                'website' => 'https://acme.example.com',
                'phone' => '555-0001',
                'email' => 'contact@acme.example.com',
                'annual_revenue' => 5000000,
                'employees' => 150,
            ],
            [
                'account_name' => 'Global Solutions Inc',
                'account_type' => 'prospect',
                'industry' => 'Consulting',
                'website' => 'https://globalsolutions.example.com',
                'phone' => '555-0002',
                'email' => 'info@globalsolutions.example.com',
                'annual_revenue' => 2000000,
                'employees' => 75,
            ],
        ];

        foreach ($accounts as $data) {
            $account = new Account();
            $account->tenant_id = $this->tenant->id;
            $account->uuid = Uuid::uuid4()->toString();
            $account->fill($data);
            $account->owner_id = $this->adminUser->id;
            $account->save();
        }

        echo "  ✓ Created accounts\n";
    }

    private function createContacts()
    {
        $account = Account::where('tenant_id', $this->tenant->id)->first();

        $contacts = [
            [
                'first_name' => 'John',
                'last_name' => 'Smith',
                'title' => 'CEO',
                'email' => 'john.smith@acme.example.com',
                'phone' => '555-0101',
            ],
            [
                'first_name' => 'Jane',
                'last_name' => 'Doe',
                'title' => 'VP of Sales',
                'email' => 'jane.doe@acme.example.com',
                'phone' => '555-0102',
            ],
        ];

        foreach ($contacts as $data) {
            $contact = new Contact();
            $contact->tenant_id = $this->tenant->id;
            $contact->uuid = Uuid::uuid4()->toString();
            $contact->account_id = $account->id;
            $contact->fill($data);
            $contact->owner_id = $this->salesUser->id;
            $contact->save();
        }

        echo "  ✓ Created contacts\n";
    }

    private function createLeads()
    {
        $leads = [
            [
                'first_name' => 'Michael',
                'last_name' => 'Brown',
                'company' => 'Tech Startup LLC',
                'title' => 'Founder',
                'email' => 'michael@techstartup.example.com',
                'phone' => '555-0201',
                'lead_status' => 'new',
                'rating' => 'hot',
                'lead_source' => 'Website',
            ],
            [
                'first_name' => 'Sarah',
                'last_name' => 'Johnson',
                'company' => 'Enterprise Corp',
                'title' => 'Procurement Manager',
                'email' => 'sarah@enterprise.example.com',
                'phone' => '555-0202',
                'lead_status' => 'contacted',
                'rating' => 'warm',
                'lead_source' => 'Referral',
            ],
        ];

        foreach ($leads as $data) {
            $lead = new Lead();
            $lead->tenant_id = $this->tenant->id;
            $lead->uuid = Uuid::uuid4()->toString();
            $lead->fill($data);
            $lead->owner_id = $this->salesUser->id;
            $lead->save();
        }

        echo "  ✓ Created leads\n";
    }

    private function createOpportunities()
    {
        $account = Account::where('tenant_id', $this->tenant->id)->first();
        $contact = Contact::where('tenant_id', $this->tenant->id)->first();

        $opportunities = [
            [
                'opportunity_name' => 'Q1 Enterprise License',
                'amount' => 50000,
                'stage' => 'Proposal',
                'probability' => 60,
                'expected_close_date' => date('Y-m-d', strtotime('+30 days')),
                'status' => 'open',
            ],
            [
                'opportunity_name' => 'Annual Support Contract',
                'amount' => 25000,
                'stage' => 'Negotiation',
                'probability' => 80,
                'expected_close_date' => date('Y-m-d', strtotime('+15 days')),
                'status' => 'open',
            ],
        ];

        foreach ($opportunities as $data) {
            $opportunity = new Opportunity();
            $opportunity->tenant_id = $this->tenant->id;
            $opportunity->uuid = Uuid::uuid4()->toString();
            $opportunity->account_id = $account->id;
            $opportunity->contact_id = $contact->id;
            $opportunity->fill($data);
            $opportunity->owner_id = $this->salesUser->id;
            $opportunity->save();
        }

        echo "  ✓ Created opportunities\n";
    }

    private function createProducts()
    {
        $products = [
            [
                'product_name' => 'CRM Professional License',
                'product_code' => 'CRM-PRO-001',
                'product_category' => 'Software',
                'unit_price' => 99.00,
                'cost_price' => 30.00,
            ],
            [
                'product_name' => 'CRM Enterprise License',
                'product_code' => 'CRM-ENT-001',
                'product_category' => 'Software',
                'unit_price' => 299.00,
                'cost_price' => 90.00,
            ],
            [
                'product_name' => 'Implementation Services',
                'product_code' => 'SVC-IMP-001',
                'product_category' => 'Services',
                'unit_price' => 150.00,
                'cost_price' => 75.00,
            ],
        ];

        foreach ($products as $data) {
            $product = new Product();
            $product->tenant_id = $this->tenant->id;
            $product->uuid = Uuid::uuid4()->toString();
            $product->fill($data);
            $product->active = true;
            $product->save();
        }

        echo "  ✓ Created products\n";
    }
}

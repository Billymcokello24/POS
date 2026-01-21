<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Define permissions
        $permissions = [
            // Sales
            ['name' => 'view_sales', 'display_name' => 'View Sales', 'group' => 'sales'],
            ['name' => 'create_sales', 'display_name' => 'Create Sales', 'group' => 'sales'],
            ['name' => 'edit_sales', 'display_name' => 'Edit Sales', 'group' => 'sales'],
            ['name' => 'delete_sales', 'display_name' => 'Delete Sales', 'group' => 'sales'],
            ['name' => 'refund_sales', 'display_name' => 'Refund Sales', 'group' => 'sales'],

            // Products
            ['name' => 'view_products', 'display_name' => 'View Products', 'group' => 'products'],
            ['name' => 'create_products', 'display_name' => 'Create Products', 'group' => 'products'],
            ['name' => 'edit_products', 'display_name' => 'Edit Products', 'group' => 'products'],
            ['name' => 'delete_products', 'display_name' => 'Delete Products', 'group' => 'products'],

            // Inventory
            ['name' => 'view_inventory', 'display_name' => 'View Inventory', 'group' => 'inventory'],
            ['name' => 'adjust_inventory', 'display_name' => 'Adjust Inventory', 'group' => 'inventory'],
            ['name' => 'view_inventory_history', 'display_name' => 'View Inventory History', 'group' => 'inventory'],

            // Reports
            ['name' => 'view_reports', 'display_name' => 'View Reports', 'group' => 'reports'],
            ['name' => 'export_reports', 'display_name' => 'Export Reports', 'group' => 'reports'],

            // Customers
            ['name' => 'view_customers', 'display_name' => 'View Customers', 'group' => 'customers'],
            ['name' => 'create_customers', 'display_name' => 'Create Customers', 'group' => 'customers'],
            ['name' => 'edit_customers', 'display_name' => 'Edit Customers', 'group' => 'customers'],
            ['name' => 'delete_customers', 'display_name' => 'Delete Customers', 'group' => 'customers'],

            // Settings
            ['name' => 'view_settings', 'display_name' => 'View Settings', 'group' => 'settings'],
            ['name' => 'edit_settings', 'display_name' => 'Edit Settings', 'group' => 'settings'],

            // Users
            ['name' => 'view_users', 'display_name' => 'View Users', 'group' => 'users'],
            ['name' => 'create_users', 'display_name' => 'Create Users', 'group' => 'users'],
            ['name' => 'edit_users', 'display_name' => 'Edit Users', 'group' => 'users'],
            ['name' => 'delete_users', 'display_name' => 'Delete Users', 'group' => 'users'],
        ];

        foreach ($permissions as $permission) {
            Permission::create($permission);
        }

        // Define roles
        $roles = [
            [
                'name' => 'admin',
                'display_name' => 'Administrator',
                'description' => 'Full access to all features',
                'level' => 100,
                'permissions' => Permission::all()->pluck('id')->toArray(),
            ],
            [
                'name' => 'manager',
                'display_name' => 'Manager',
                'description' => 'Can manage products, inventory, and view reports',
                'level' => 75,
                'permissions' => Permission::whereIn('name', [
                    'view_sales', 'create_sales', 'edit_sales',
                    'view_products', 'create_products', 'edit_products',
                    'view_inventory', 'adjust_inventory', 'view_inventory_history',
                    'view_reports', 'export_reports',
                    'view_customers', 'create_customers', 'edit_customers',
                    'view_settings',
                ])->pluck('id')->toArray(),
            ],
            [
                'name' => 'cashier',
                'display_name' => 'Cashier',
                'description' => 'Can process sales and view products',
                'level' => 50,
                'permissions' => Permission::whereIn('name', [
                    'view_sales', 'create_sales',
                    'view_products',
                    'view_customers', 'create_customers',
                ])->pluck('id')->toArray(),
            ],
            [
                'name' => 'stock_clerk',
                'display_name' => 'Stock Clerk',
                'description' => 'Can manage inventory and products',
                'level' => 40,
                'permissions' => Permission::whereIn('name', [
                    'view_products', 'create_products', 'edit_products',
                    'view_inventory', 'adjust_inventory', 'view_inventory_history',
                ])->pluck('id')->toArray(),
            ],
        ];

        foreach ($roles as $roleData) {
            $permissions = $roleData['permissions'];
            unset($roleData['permissions']);

            $role = Role::create($roleData);
            $role->permissions()->attach($permissions);
        }
    }
}


<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Define all permissions organized by module
        $permissionsData = [
            // Dashboard
            'Dashboard' => [
                ['code' => 'dashboard.view', 'name' => 'View Dashboard'],
            ],

            // Master Data
            'Master Data' => [
                ['code' => 'products.view', 'name' => 'View Products'],
                ['code' => 'products.create', 'name' => 'Create Products'],
                ['code' => 'products.edit', 'name' => 'Edit Products'],
                ['code' => 'products.delete', 'name' => 'Delete Products'],
                ['code' => 'suppliers.view', 'name' => 'View Suppliers'],
                ['code' => 'suppliers.manage', 'name' => 'Manage Suppliers'],
                ['code' => 'clients.view', 'name' => 'View Clients'],
                ['code' => 'clients.manage', 'name' => 'Manage Clients'],
                ['code' => 'instrument_sets.view', 'name' => 'View Instrument Sets'],
                ['code' => 'instrument_sets.manage', 'name' => 'Manage Instrument Sets'],
            ],

            // Stock-In
            'Stock-In' => [
                ['code' => 'stock_in.create', 'name' => 'Create Stock-In Session'],
                ['code' => 'stock_in.view', 'name' => 'View Stock-In Sessions'],
                ['code' => 'stock_in.confirm', 'name' => 'Confirm Stock-In Session'],
                ['code' => 'stock_in.edit_draft', 'name' => 'Edit Stock-In (Pre-confirmation)'],
                ['code' => 'stock_in.correct_confirmed', 'name' => 'Correct Immutable Fields (Post-confirmation)'],
            ],

            // QR Labels
            'QR Labels' => [
                ['code' => 'qr_labels.print', 'name' => 'Print QR Labels'],
                ['code' => 'qr_labels.reprint', 'name' => 'Reprint QR Labels'],
                ['code' => 'qr_labels.view_jobs', 'name' => 'View Print Jobs'],
            ],

            // Consignment
            'Consignment' => [
                ['code' => 'consignments.create', 'name' => 'Create Consignment Note'],
                ['code' => 'consignments.view', 'name' => 'View Consignment Notes'],
                ['code' => 'consignments.confirm', 'name' => 'Confirm Consignment Note'],
                ['code' => 'consignments.edit_draft', 'name' => 'Edit Consignment (Pre-confirmation)'],
                ['code' => 'consignments.edit_confirmed', 'name' => 'Edit Consignment (Post-confirmation)'],
            ],

            // Returns & Reconciliation
            'Returns & Reconciliation' => [
                ['code' => 'returns.create', 'name' => 'Create Return Session'],
                ['code' => 'returns.view', 'name' => 'View Return Sessions'],
                ['code' => 'returns.finalize', 'name' => 'Finalize Return Session'],
                ['code' => 'returns.reopen_reconciliation', 'name' => 'Reopen Reconciliation'],
            ],

            // Disposal & Returns
            'Disposal & Returns' => [
                ['code' => 'disposals.create', 'name' => 'Dispose Units'],
                ['code' => 'supplier_returns.view', 'name' => 'View Supplier Returns'],
                ['code' => 'supplier_returns.create', 'name' => 'Return Units to Supplier'],
                ['code' => 'disposals.view', 'name' => 'View Disposal/Return History'],
            ],

            // Holding Area
            'Holding Area' => [
                ['code' => 'holding_area.view', 'name' => 'View Holding Area Units'],
                ['code' => 'holding_area.assign_lot', 'name' => 'Assign Lot Number'],
            ],

            // Reporting & Analytics
            'Reporting & Analytics' => [
                ['code' => 'reports.view', 'name' => 'View Reports'],
                ['code' => 'reports.stock_analytics', 'name' => 'View Stock Analytics'],
                ['code' => 'reports.consignments', 'name' => 'View Consignment Reports'],
                ['code' => 'reports.returns_analysis', 'name' => 'View Returns vs Used Analysis'],
                ['code' => 'reports.disposal', 'name' => 'View Disposal Reports'],
                ['code' => 'reports.expiry', 'name' => 'View Expiry Dashboard'],
                ['code' => 'reports.export', 'name' => 'Export Reports (CSV/XLSX/PDF)'],
            ],

            // Usage Summary & Integration
            'Usage Summary & Integration' => [
                ['code' => 'usage_summary.view', 'name' => 'View Usage Summary'],
                ['code' => 'usage_summary.generate', 'name' => 'Generate Usage Summary'],
                ['code' => 'usage_summary.view_logs', 'name' => 'View Push Logs'],
            ],

            // Audit & Governance
            'Audit & Governance' => [
                ['code' => 'audit.view_logs', 'name' => 'View Audit Logs'],
                ['code' => 'audit.export_logs', 'name' => 'Export Audit Logs'],
            ],

            // System Configuration
            'System Configuration' => [
                ['code' => 'system.configure', 'name' => 'Configure System Settings'],
                ['code' => 'system.manage_users', 'name' => 'Manage User Accounts'],
                ['code' => 'system.manage_roles', 'name' => 'Manage Roles & Permissions'],
            ],
        ];

        // Create or update permissions
        $permissionsMap = [];
        foreach ($permissionsData as $module => $permissions) {
            foreach ($permissions as $perm) {
                $permission = Permission::firstOrCreate(
                    ['permission_code' => $perm['code']],
                    [
                        'permission_name' => $perm['name'],
                        'module' => $module,
                        'description' => "{$perm['name']} in {$module}",
                    ]
                );
                $permissionsMap[$perm['code']] = $permission->id;
            }
        }

        // Define role permissions mapping
        $rolePermissionsMap = [
            'admin' => [
                // All permissions for Admin
                'dashboard.view',
                'products.view', 'products.create', 'products.edit', 'products.delete',
                'suppliers.view', 'suppliers.manage',
                'clients.view', 'clients.manage',
                'instrument_sets.view', 'instrument_sets.manage',
                'stock_in.create', 'stock_in.view', 'stock_in.confirm', 'stock_in.edit_draft', 'stock_in.correct_confirmed',
                'qr_labels.print', 'qr_labels.reprint', 'qr_labels.view_jobs',
                'consignments.create', 'consignments.view', 'consignments.confirm', 'consignments.edit_draft', 'consignments.edit_confirmed',
                'returns.create', 'returns.view', 'returns.finalize', 'returns.reopen_reconciliation',
                'disposals.create', 'supplier_returns.view', 'supplier_returns.create', 'disposals.view',
                'holding_area.view', 'holding_area.assign_lot',
                'reports.view', 'reports.stock_analytics', 'reports.consignments', 'reports.returns_analysis', 'reports.disposal', 'reports.expiry', 'reports.export',
                'usage_summary.view', 'usage_summary.generate', 'usage_summary.view_logs',
                'audit.view_logs', 'audit.export_logs',
                'system.configure', 'system.manage_users', 'system.manage_roles',
            ],
            'logistic_staff' => [
                // Logistic Staff permissions
                'dashboard.view',
                'suppliers.view',
                'clients.view',
                'instrument_sets.view',
                'stock_in.create', 'stock_in.view', 'stock_in.confirm', 'stock_in.edit_draft',
                'qr_labels.print', 'qr_labels.reprint', 'qr_labels.view_jobs',
                'consignments.create', 'consignments.view', 'consignments.confirm', 'consignments.edit_draft',
                'returns.create', 'returns.view', 'returns.finalize',
                'disposals.create', 'supplier_returns.view', 'supplier_returns.create', 'disposals.view',
                'holding_area.view',
                'reports.view', 'reports.stock_analytics', 'reports.consignments', 'reports.returns_analysis', 'reports.disposal', 'reports.expiry', 'reports.export',
            ],
        ];

        // Ensure roles exist
        $adminRole = Role::firstOrCreate(
            ['role_code' => 'admin'],
            ['role_name' => 'Administrator']
        );
        $staffRole = Role::firstOrCreate(
            ['role_code' => 'logistic_staff'],
            ['role_name' => 'Logistic Staff']
        );

        // Assign permissions to roles
        foreach ($rolePermissionsMap as $roleCode => $permissionCodes) {
            $role = $roleCode === 'admin' ? $adminRole : $staffRole;

            // Sync permissions (remove old ones, add new ones)
            $permissionIds = array_map(
                fn ($code) => $permissionsMap[$code],
                $permissionCodes
            );

            $role->permissions()->sync($permissionIds);
            $role->flushPermissionCache();
        }

        $this->command->info('Permissions and role permissions seeded successfully!');
        $this->command->info('Total permissions created: '.count($permissionsMap));
        $this->command->info('Admin role assigned: '.count($rolePermissionsMap['admin']).' permissions');
        $this->command->info('Logistic Staff role assigned: '.count($rolePermissionsMap['logistic_staff']).' permissions');
    }
}

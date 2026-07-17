<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Marvel\Database\Models\Role;
use Spatie\Permission\Models\Permission;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */

    public function run()
    {
        $permissions = [

            // 🔓 Public (view only)
            'view-products',
            'view-product',
            'view-categories',
            'view-category',
            'view-shops',
            'view-shop',
            'view-types',
            'view-type',
            'view-tags',
            'view-tag',
            'view-authors',
            'view-author',
            'view-manufacturers',
            'view-manufacturer',
            'view-brands',
            'view-brand',
            'view-contacts',
            'view-coupons',
            'verify-coupon',
            'view-cms-pages',
            'view-cms-page',
            'view-faqs',
            'view-settings',
            "view-flash-sale",
            'create-flash-sale',
            'update-flash-sale',
            'delete-flash-sale',
            "view-attributes",
            "view-promotion",
            "delete-promotion",
            "create-promotion",
            "update-promotion",
            'view-country',
            'create-country',
            'update-country',
            'delete-country',
            'view-order',
            'view-orders',
            'view-activity-log',
            'view-notifications',
            'manage-notifications',

            'view-city',
            'create-city',
            'update-city',
            'delete-city',

            'view-governorate',
            'create-governorate',
            'update-governorate',
            'delete-governorate',
            'manage-shipping-prices',

            // 👤 Customer
            'view-profile',
            'update-profile',
            'change-password',
            'view-orders',
            'view-order',
            'create-order',
            'track-order',
            'create-review',
            'update-review',
            'view-wishlist',
            'add-wishlist',
            'remove-wishlist',
            'toggle-wishlist',
            'view-refunds',
            'create-refund',
            'view-conversations',
            'create-conversation',
            'ask-question',
            'view-my-questions',
            'follow-shop',
            'view-followed-shops',

            // 👷 Staff / Store Owner
            'create-product',
            'update-product',
            'delete-product',
            'create-attribute',
            'update-attribute',
            'delete-attribute',
            'update-order-status',
            'answer-question',
            'create-author',
            'create-manufacturer',
            'update-contact',
            'delete-contact',
            'delete-read-contacts',
            'view-analytics',
            'view-low-stock-products',
            'view-draft-products',
            'update-coupon',
            'create-store-notice',
            'update-store-notice',
            'delete-store-notice',
            'create-faq',
            'update-faq',
            'delete-faq',

            // 🏪 Store Owner
            'create-shop',
            'update-shop',
            'delete-shop',
            'view-my-shops',
            'add-staff',
            'remove-staff',
            'view-staffs',
            'transfer-shop-ownership',
            'view-withdraws',
            'create-withdraw',
            'create-coupon',
            'delete-coupon',

            // ✏️ Editor
            'create-cms-page',
            'update-cms-page',
            'delete-cms-page',
            'save-puck-page',

            // 🔐 Super Admin
            'view-admins',
            'view-vendors',
            'view-customers',
            'view-users',
            'create-user',
            'update-user',
            'delete-user',
            'ban-user',
            'activate-user',
            'make-admin',
            'create-type',
            'update-type',
            'delete-type',
            'create-category',
            'update-category',
            'delete-category',
            'create-brand',
            'update-brand',
            'delete-brand',
            'create-tag',
            'update-tag',
            'delete-tag',
            'update-author',
            'delete-author',
            'update-manufacturer',
            'delete-manufacturer',
            'approve-withdraw',
            'approve-shop',
            'disapprove-shop',
            'view-new-shops',
            'update-settings',
            'delete-review',
            'delete-question',
            'create-tax',
            'create-shipping',
            'add-points',
            'approve-coupon',
            'disapprove-coupon',
            'view-abusive-reports',
            'accept-abusive-report',
            'reject-abusive-report',
            'view-roles',
            'view-role',
            'create-roles',
            'update-roles',
            'delete-roles',
            'view-banners',
            'create-banners',
            'update-banners',
            'delete-banners',
            'view-slider',
            "create-slider",
            "update-slider",
            "delete-slider",
            'assign-role',
            'remove-role',
            'edit-user',
            'approve-reviews',
            'delete-reviews',
            'restore-user',

            //fast-shipping
            'view-fast-shipping',
            'update-fast-shipping',

            // 📍 Pickup Locations
            'view-pickup-locations',
            'create-pickup-location',
            'update-pickup-location',
            'delete-pickup-location',
        ];

        $customerPermission = [
            // 👤 Customer
            'view-profile',
            'update-profile',
            'change-password',
            'view-orders',
            'view-order',
            'create-order',
            'track-order',
            'create-review',
            'update-review',
            'view-wishlist',
            'add-wishlist',
            'remove-wishlist',
            'toggle-wishlist',
            'view-refunds',
            'create-refund',
            'view-conversations',
            'create-conversation',
            'ask-question',
            'view-my-questions',
            'follow-shop',
            'view-followed-shops',
            'view-banners',
        ];

        $staffAndOnwner = [
            // 👷 Staff / Store Owner
            'create-product',
            'update-product',
            'delete-product',
            'create-attribute',
            'update-attribute',
            'delete-attribute',
            'update-order-status',
            'answer-question',
            'create-author',
            'create-manufacturer',
            'view-analytics',
            'view-low-stock-products',
            'view-draft-products',
            'update-coupon',
            'create-store-notice',
            'update-store-notice',
            'delete-store-notice',
            'create-faq',
            'update-faq',
            'delete-faq',
        ];

        $onwnerPermission = [
            // 🏪 Store Owner
            'create-shop',
            'update-shop',
            'delete-shop',
            'view-my-shops',
            'add-staff',
            'remove-staff',
            'view-staffs',
            'transfer-shop-ownership',
            'view-withdraws',
            'create-withdraw',
            'create-coupon',
            'delete-coupon',
        ];


        $editorPermission = [
            // ✏️ Editor
            'create-cms-page',
            'update-cms-page',
            'delete-cms-page',
            'save-puck-page',
        ];

        $superAdminPermission = [
            // 🔐 Super Admin
            'view-admins',
            'view-role',
            'view-vendors',
            'view-customers',
            'view-users',
            'create-user',
            'update-user',
            'delete-user',
            'ban-user',
            'activate-user',
            'make-admin',
            'create-type',
            'update-type',
            'delete-type',
            'create-category',
            'update-category',
            'delete-category',
            'create-brand',
            'update-brand',
            'delete-brand',
            'create-tag',
            'update-tag',
            'delete-tag',
            'update-author',
            'delete-author',
            'update-manufacturer',
            'delete-manufacturer',
            'approve-withdraw',
            'approve-shop',
            'disapprove-shop',
            'view-new-shops',
            'update-settings',
            'delete-review',
            'delete-question',
            'create-tax',
            'create-shipping',
            'add-points',
            'approve-coupon',
            'disapprove-coupon',
            'view-abusive-reports',
            'accept-abusive-report',
            'reject-abusive-report',
        ];

        $viewPermission = [
            'view-products',
            'view-product',
            'view-categories',
            'view-category',
            'view-shops',
            'view-shop',
            'view-types',
            'view-type',
            'view-tags',
            'view-tag',
            'view-authors',
            'view-author',
            'view-manufacturers',
            'view-manufacturer',
            'view-brands',
            'view-brand',
            'view-contacts',
            'view-contact',
            'view-coupons',
            'verify-coupon',
            'view-cms-pages',
            'view-cms-page',
            'view-faqs',
            'view-settings',
        ];

        $permissionsData = [];
        foreach (array_unique($permissions) as $permission) {
            $permissionsData[] = Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'api',
            ]);
        }

        $roleSuperAdmin = Role::firstOrCreate([
            'name' => 'super_admin',
            'display_name' => [
                'en' => 'Super Admin',
                'ar' => 'مدير_النظام',
            ],
            'guard_name' => 'api',
        ]);
        $roleOwner = Role::firstOrCreate([
            'name' => 'owner',
            'display_name' => [
                'en' => 'Owner',
                'ar' => 'مالك',
            ],
            'guard_name' => 'api',
        ]);
        $roleStaff = Role::firstOrCreate([
            'name' => 'staff',
            'display_name' => [
                'en' => 'Staff',
                'ar' => 'موظف',
            ],
            'guard_name' => 'api',
        ]);
        $roleCustomer = Role::firstOrCreate([
            'name' => 'customer',
            'display_name' => [
                'en' => 'Customer',
                'ar' => 'عميل',
            ],
            'guard_name' => 'api',
        ]);
        $roleEditor = Role::firstOrCreate([
            'name' => 'editor',
            'display_name' => [
                'en' => 'Editor',
                'ar' => 'محرر',
            ],
            'guard_name' => 'api',
        ]);

        $roleSuperAdmin = Role::firstOrCreate(
            ['name' => 'super_admin', 'guard_name' => 'api'],
            ['display_name' => 'Super Admin']
        );
        $roleOwner = Role::firstOrCreate(
            ['name' => 'owner', 'guard_name' => 'api'],
            ['display_name' => 'Owner']
        );
        $roleStaff = Role::firstOrCreate(
            ['name' => 'staff', 'guard_name' => 'api'],
            ['display_name' => 'Staff']
        );
        $roleCustomer = Role::firstOrCreate(
            ['name' => 'customer', 'guard_name' => 'api'],
            ['display_name' => 'Customer']
        );
        $roleEditor = Role::firstOrCreate(
            ['name' => 'editor', 'guard_name' => 'api'],
            ['display_name' => 'Editor']
        );
        $roleSuperAdmin->syncPermissions($permissionsData);
        $roleOwner->syncPermissions($onwnerPermission);
        $roleStaff->syncPermissions($staffAndOnwner);
        $roleCustomer->syncPermissions($customerPermission);
        $roleEditor->syncPermissions($editorPermission);
    }
}
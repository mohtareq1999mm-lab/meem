<?php

return [
    'welcome' => 'Welcome',

    // Attributes
    'MESSAGE.ATTRIBUTE_CREATED_SUCCESSFULLY' => 'Attribute created successfully',
    'MESSAGE.ATTRIBUTE_UPDATED_SUCCESSFULLY' => 'Attribute updated successfully',
    'MESSAGE.ATTRIBUTE_DELETED_SUCCESSFULLY' => 'Attribute deleted successfully',
    'MESSAGE.ATTRIBUTE_VALUE_CREATED_SUCCESSFULLY' => 'Attribute value created successfully',
    'MESSAGE.ATTRIBUTE_VALUE_UPDATED_SUCCESSFULLY' => 'Attribute value updated successfully',
    'MESSAGE.ATTRIBUTE_VALUE_DELETED_SUCCESSFULLY' => 'Attribute value deleted successfully',

    // Brands
    'MESSAGE.BRAND_CREATED_SUCCESSFULLY' => 'Brand created successfully',
    'MESSAGE.BRAND_UPDATED_SUCCESSFULLY' => 'Brand updated successfully',
    'MESSAGE.BRAND_DELETED_SUCCESSFULLY' => 'Brand deleted successfully',
    'MESSAGE.BRANDS_REORDERED_SUCCESSFULLY' => 'Brands reordered successfully',

    // Sliders
    'MESSAGE.SLIDER_CREATED_SUCCESSFULLY' => 'Slider created successfully',
    'MESSAGE.SLIDER_UPDATED_SUCCESSFULLY' => 'Slider updated successfully',
    'MESSAGE.SLIDER_DELETED_SUCCESSFULLY' => 'Slider deleted successfully',
    'MESSAGE.SLIDER_STATUS_CHANGED' => 'Slider status changed successfully',
    'MESSAGE.SLIDERS_REORDERED_SUCCESSFULLY' => 'Sliders reordered successfully',

    // Banners
    'MESSAGE.BANNER_CREATED_SUCCESSFULLY' => 'Banner created successfully',
    'MESSAGE.BANNER_UPDATED_SUCCESSFULLY' => 'Banner updated successfully',
    'MESSAGE.BANNER_DELETED_SUCCESSFULLY' => 'Banner deleted successfully',
    'MESSAGE.BANNER_STATUS_CHANGED' => 'Banner status changed successfully',
    'MESSAGE.BANNERS_REORDERED_SUCCESSFULLY' => 'Banners reordered successfully',

    // Content Pages & Sections
    'MESSAGE.SECTION_CREATED_SUCCESSFULLY' => 'Section created successfully',
    'MESSAGE.SECTION_UPDATED_SUCCESSFULLY' => 'Section updated successfully',
    'MESSAGE.SECTION_DELETED_SUCCESSFULLY' => 'Section deleted successfully',
    'MESSAGE.SECTIONS_REORDERED_SUCCESSFULLY' => 'Sections reordered successfully',
    'MESSAGE.TYPE_CREATED_SUCCESSFULLY' => 'Type created successfully',
    'MESSAGE.TYPE_UPDATED_SUCCESSFULLY' => 'Type updated successfully',
    'MESSAGE.TYPE_DELETED_SUCCESSFULLY' => 'Type deleted successfully',
    'MESSAGE.SETTINGS_UPDATED_SUCCESSFULLY' => 'Settings updated successfully',

    // Categories
    'MESSAGE.CATEGORY_CREATED_SUCCESSFULLY' => 'Category created successfully',
    'MESSAGE.CATEGORY_UPDATED_SUCCESSFULLY' => 'Category updated successfully',
    'MESSAGE.CATEGORY_DELETED_SUCCESSFULLY' => 'Category deleted successfully',
    'MESSAGE.CATEGORY_FEATURE_TOGGLED_SUCCESSFULLY' => 'Category feature toggled successfully',
    'ERROR.CANNOT_DELETE_CATEGORY_WITH_ASSOCIATED_RESOURCES' => 'Cannot delete category with existing associated resources',

    // Users
    'MESSAGE.USER_CREATED_SUCCESSFULLY' => 'User created successfully',
    'MESSAGE.USER_UPDATED_SUCCESSFULLY' => 'User updated successfully',
    'MESSAGE.USER_DELETED_SUCCESSFULLY' => 'User deleted successfully',
    'MESSAGE.USER_RESTORED_SUCCESSFULLY' => 'User restored successfully',
    'MESSAGE.USER_BANNED_SUCCESSFULLY' => 'User banned successfully',
    'MESSAGE.USER_ACTIVATED_SUCCESSFULLY' => 'User activated successfully',
    'MESSAGE.USER_NOT_FOUND' => 'User not found',
    'MESSAGE.USER_CANNOT_BE_UPDATED' => 'User cannot be updated',
    'MESSAGE.USER_CANNOT_BE_RESTORED' => 'User cannot be restored',

    // Roles & Permissions
    'MESSAGE.ROLE_FETCHED_SUCCESSFULLY' => 'Role fetched successfully',
    'MESSAGE.ROLES_FETCHED_SUCCESSFULLY' => 'Roles fetched successfully',
    'MESSAGE.ROLE_ADDED_SUCCESSFULLY' => 'Role added successfully',
    'MESSAGE.ROLE_UPDATED_SUCCESSFULLY' => 'Role updated successfully',
    'MESSAGE.ROLE_DELETED_SUCCESSFULLY' => 'Role deleted successfully',
    'ERROR.CANNOT_DELETE_ROLE_WITH_ASSIGNED_USERS' => 'Cannot delete role with assigned users',
    'MESSAGE.ROLE_ASSIGNED_SUCCESSFULLY' => 'Role assigned successfully',
    'MESSAGE.ROLE_REMOVED_SUCCESSFULLY' => 'Role removed successfully',
    'MESSAGE.PERMISSIONS_FETCHED_SUCCESSFULLY' => 'Permissions fetched successfully',
    'MESSAGE.PERMISSION_ASSIGNED_SUCCESSFULLY' => 'Permission assigned successfully',
    'ERROR.CANNOT_ASSIGN_ROLE_TO_USER' => 'Cannot assign role to this user',

    // Pickup Locations
    'MESSAGE.PICKUP_LOCATION_CREATED_SUCCESSFULLY' => 'Pickup location created successfully',
    'MESSAGE.PICKUP_LOCATION_UPDATED_SUCCESSFULLY' => 'Pickup location updated successfully',
    'MESSAGE.PICKUP_LOCATION_DELETED_SUCCESSFULLY' => 'Pickup location deleted successfully',
    'ERROR.COD_NOT_AVAILABLE_FOR_PICKUP' => 'COD is not available for pickup. Use pay_at_cashier instead.',

    // General
    'MESSAGE.WRONG_CSV' => 'Invalid CSV format. Please check the file and try again.',
    'MESSAGE.NOT_AUTHORIZED' => 'You are not authorized to perform this action',
    'MESSAGE.SOMETHING_WENT_WRONG' => 'Something went wrong',

    // Cart
    'MESSAGE.FETCH_DATA_SUCCESSFULLY' => 'Data fetched successfully',
    'MESSAGE.CREATE_CART_SUCCESSFULLY' => 'Cart created successfully',
    'MESSAGE.UPDATE_CART_SUCCESSFULLY' => 'Cart updated successfully',
    'MESSAGE.DELETE_CART_SUCCESSFULLY' => 'Cart deleted successfully',
    'MESSAGE.DELETE_CART_ITEM_SUCCESSFULLY' => 'Cart item deleted successfully',
    'ERROR.DELETE_CART_ITEM_FAILED' => 'Failed to delete cart item',
    'ERROR.CART_NOT_FOUND' => 'Cart not found',
    'MESSAGE.COUPON_DELETE_CART_WARNING' => 'This cart has a coupon applied. Please confirm to proceed with deletion.',
    'MESSAGE.COUPON_ADDED_TO_CART_SUCCESSFULLY' => 'Coupon added to cart successfully',
    'ERROR.COULD_NOT_ADD_COUPON_TO_CART' => 'Could not add coupon to cart',
    'QUANTITY_MINIMUM' => 'Quantity must be at least 1.',
    'QUANTITY_EXCEEDS_STOCK' => 'Quantity exceeds available stock.',

    // Cart inventory
    'cart.inventory.quantity_minimum' => 'Quantity must be at least 1.',
    'cart.inventory.gift_variant_not_available' => 'The selected gift variant is not available.',
    'cart.inventory.gift_variant_no_stock' => 'No stock available for the gift variant.',
    'cart.inventory.quantity_exceeds_stock' => 'Quantity exceeds available stock.',
    'cart.inventory.reserved_stock_insufficient' => 'Reserved stock is insufficient.',
    'cart.inventory.physical_stock_insufficient' => 'Physical stock is insufficient.',

    // Dashboard
    'DASHBOARD.OVERVIEW_FETCHED' => 'Dashboard overview fetched successfully.',
    'DASHBOARD.REVENUE_FETCHED' => 'Revenue data fetched successfully.',
    'DASHBOARD.ORDER_STATS_FETCHED' => 'Order statistics fetched successfully.',
    'DASHBOARD.RECENT_ORDERS_FETCHED' => 'Recent orders fetched successfully.',
    'DASHBOARD.TOP_PRODUCTS_FETCHED' => 'Top selling products fetched successfully.',
    'DASHBOARD.CATEGORY_STATS_FETCHED' => 'Category statistics fetched successfully.',
    'DASHBOARD.LOW_STOCK_FETCHED' => 'Low stock products fetched successfully.',
    'DASHBOARD.SALES_ANALYTICS_FETCHED' => 'Sales analytics fetched successfully.',
    'DASHBOARD.CUSTOMER_ANALYTICS_FETCHED' => 'Customer analytics fetched successfully.',
    'DASHBOARD.PRODUCT_ANALYTICS_FETCHED' => 'Product analytics fetched successfully.',
    'DASHBOARD.ORDER_ANALYTICS_FETCHED' => 'Order analytics fetched successfully.',
    'DASHBOARD.CATEGORY_ANALYTICS_FETCHED' => 'Category analytics fetched successfully.',
    'DASHBOARD.COUPON_ANALYTICS_FETCHED' => 'Coupon analytics fetched successfully.',
    'DASHBOARD.CART_ANALYTICS_FETCHED' => 'Cart analytics fetched successfully.',
    'DASHBOARD.RECONCILIATION_FETCHED' => 'Reconciliation summary fetched successfully.',
    'DASHBOARD.FINANCE_ANALYTICS_FETCHED' => 'Finance analytics fetched successfully.',
    'DASHBOARD.DATABASE_ERROR' => 'A database error occurred. Please check your request and try again.',
    'ERROR.SOMETHING_WENT_WRONG' => 'Something went wrong.',
];

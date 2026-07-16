<?php


namespace Marvel\Enums;

use BenSampo\Enum\Enum;

/**
 * Class RoleType
 * @package App\Enums
 */
final class Permission extends Enum
{
    public const SUPER_ADMIN = 'super_admin';
    public const STORE_OWNER = 'store_owner';
    public const STAFF = 'staff';
    public const CUSTOMER = 'customer';
    public const EDITOR = 'editor';
    public const VIEW_ACTIVITY_LOG = 'view-activity-log';
    public const VIEW_INVOICES = 'view-invoices';
    public const ISSUE_CORRECTION_INVOICE = 'issue-correction-invoice';
    public const REGENERATE_INVOICE_PDF = 'regenerate-invoice-pdf';
    public const EXPORT_INVOICES = 'export-invoices';
}

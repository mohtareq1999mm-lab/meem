# Database — Invoice Module

## Table: `invoices`

| Column | Type | Default | Constraints | Notes |
|--------|------|---------|-------------|-------|
| id | bigint unsigned | AUTO_INCREMENT | PK | |
| uuid | uuid | | UNIQUE, NOT NULL | Auto-generated via Str::orderedUuid() |
| order_id | bigint unsigned | | FK → orders.id, RESTRICT ON DELETE | |
| transaction_id | bigint unsigned | NULL | FK → transactions.id, NULL ON DELETE | |
| user_id | bigint unsigned | | FK → users.id, RESTRICT ON DELETE | |
| correction_to_id | bigint unsigned | NULL | FK → invoices.id, NULL ON DELETE | Self-referencing for corrections |
| invoice_number | varchar(50) | | UNIQUE, NOT NULL | INV-{YEAR}-{SEQUENCE} |
| invoice_series | varchar(10) | 'INV' | | |
| sequence_number | bigint unsigned | | | |
| sequence_year | year | | | |
| subtotal | decimal(10,3) | 0 | | |
| shipping_price | decimal(10,3) | 0 | | |
| coupon_discount | decimal(10,3) | 0 | | |
| promotion_discount | decimal(10,3) | 0 | | |
| total_discount | decimal(10,3) | 0 | | |
| total | decimal(10,3) | 0 | | |
| amount_paid | decimal(10,3) | 0 | | |
| currency | varchar(3) | 'EGP' | | |
| payment_method | varchar(30) | NULL | | |
| payment_gateway | varchar(50) | NULL | | |
| status | varchar(20) | 'generated' | | See InvoiceStatus enum |
| data | json | | NOT NULL | Full snapshot |
| snapshot_hash | varchar(64) | NULL | | SHA-256 of sorted snapshot JSON |
| verification_hash | varchar(64) | NULL | | SHA-256(snapshot_hash + app_key) |
| pdf_generated_at | timestamp | NULL | | |
| pdf_regenerated_at | timestamp | NULL | | |
| pdf_path | varchar(500) | NULL | | Relative path in storage/invoices/ |
| pdf_checksum | varchar(64) | NULL | | MD5 checksum of PDF |
| generation_attempts | tinyint unsigned | 0 | | |
| last_generation_error | text | NULL | | |
| is_correction | boolean | false | | |
| correction_reason | varchar(500) | NULL | | |
| corrected_at | timestamp | NULL | | |
| cancelled_at | timestamp | NULL | | |
| cancellation_reason | varchar(500) | NULL | | |
| generated_at | timestamp | CURRENT_TIMESTAMP | | |
| generated_by | varchar(50) | 'system' | | |
| verified_at | timestamp | NULL | | Set only on first verification |
| downloaded_at | timestamp | NULL | | Set only on first download |
| printed_at | timestamp | NULL | | |
| archived_at | timestamp | NULL | | |
| last_verified_at | timestamp | NULL | | Updated on every verification |
| verify_count | smallint unsigned | 0 | | |
| created_at | timestamp | NULL | | |
| updated_at | timestamp | NULL | | |

### Indexes

| Index Name | Columns | Type | Purpose |
|------------|---------|------|---------|
| PRIMARY | `id` | PK | |
| `invoices_uuid_unique` | `uuid` | UNIQUE | UUID lookup |
| `uq_invoices_invoice_number` | `invoice_number` | UNIQUE | Invoice number uniqueness |
| `uq_invoices_order_id` | `order_id` | UNIQUE | One invoice per order |
| `idx_invoices_user_id` | `user_id` | INDEX | User invoices |
| `idx_invoices_status` | `status` | INDEX | Status filtering |

### Foreign Keys

| Column | Referenced | On Delete |
|--------|------------|-----------|
| `order_id` | `orders.id` | RESTRICT |
| `transaction_id` | `transactions.id` | SET NULL |
| `user_id` | `users.id` | RESTRICT |
| `correction_to_id` | `invoices.id` | SET NULL |

---

## Table: `invoice_sequences`

| Column | Type | Constraints |
|--------|------|-------------|
| series | varchar(10) | PK (part of composite) |
| sequence_year | year | PK (part of composite) |
| last_sequence | bigint unsigned | Default 0 |
| created_at | timestamp | |
| updated_at | timestamp | |

**Primary Key:** `(series, sequence_year)`

---

## Table: `invoice_timeline`

| Column | Type | Constraints |
|--------|------|-------------|
| id | bigint unsigned | PK |
| invoice_id | bigint unsigned | FK → invoices.id, CASCADE ON DELETE |
| event | varchar(50) | |
| old_status | varchar(20) | NULL |
| new_status | varchar(20) | NULL |
| actor_type | string | NULL (morph) |
| actor_id | bigint unsigned | NULL (morph) |
| metadata | json | NULL |
| ip_address | varchar(45) | NULL |
| created_at | timestamp | |
| updated_at | timestamp | |

**Indexes:** `invoice_id`, `event`, `(actor_type, actor_id)`

---

## Table: `debit_notes`

| Column | Type | Constraints |
|--------|------|-------------|
| id | bigint unsigned | PK |
| uuid | uuid | UNIQUE |
| invoice_id | bigint unsigned | FK → invoices.id, CASCADE ON DELETE |
| debit_note_number | varchar(50) | UNIQUE |
| debit_note_series | varchar(10) | Default 'DN' |
| sequence_number | bigint unsigned | |
| sequence_year | year | |
| reason | varchar(500) | |
| type | varchar(30) | Default 'correction' |
| amount | decimal(10,3) | |
| currency | varchar(3) | Default 'EGP' |
| created_by | bigint unsigned | NULL, FK → users.id, SET NULL |
| line_items | json | NULL |
| notes | text | NULL |
| issued_at | timestamp | USE CURRENT_TIMESTAMP |
| created_at | timestamp | |
| updated_at | timestamp | |

---

## Table: `credit_notes`

| Column | Type | Constraints |
|--------|------|-------------|
| id | bigint unsigned | PK |
| uuid | uuid | UNIQUE |
| invoice_id | bigint unsigned | FK → invoices.id, CASCADE ON DELETE |
| credit_note_number | varchar(50) | UNIQUE |
| credit_note_series | varchar(10) | Default 'CN' |
| sequence_number | bigint unsigned | |
| sequence_year | year | |
| reason | varchar(500) | |
| type | varchar(30) | Default 'refund' |
| amount | decimal(10,3) | |
| currency | varchar(3) | Default 'EGP' |
| refund_transaction_id | bigint unsigned | NULL, FK → transactions.id, SET NULL |
| created_by | bigint unsigned | NULL, FK → users.id, SET NULL |
| line_items | json | NULL |
| notes | text | NULL |
| issued_at | timestamp | USE CURRENT_TIMESTAMP |
| created_at | timestamp | |
| updated_at | timestamp | |

---

## Soft Deletes

**Not implemented.** No table uses `SoftDeletes`. Records are permanently stored (immutable financial records).

## Migration Files

| File | Table(s) |
|------|----------|
| `2026_07_16_000001_create_invoice_sequences_table.php` | `invoice_sequences` |
| `2026_07_16_000002_create_invoices_table.php` | `invoices` |
| `2026_07_27_082000_add_uuid_and_verification_to_invoices_table.php` | invoices (add columns) |
| `2026_07_28_000001_create_invoice_timeline_table.php` | `invoice_timeline` |
| `2026_07_28_000005_add_invoice_lifecycle_columns.php` | invoices (add lifecycle columns) |
| `2026_07_28_000006_remove_unique_order_id_from_invoices.php` | invoices (remove unique on order_id) |

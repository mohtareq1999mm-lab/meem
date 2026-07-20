# Database — Slider Module

## Table: `sliders`

| Column | Type | Default | Constraints | Notes |
|--------|------|---------|-------------|-------|
| id | bigint unsigned | AUTO_INCREMENT | PK | |
| title | string | NULL | | Translatable JSON |
| slug | string | | NOT NULL | Auto-generated from English title |
| order | integer | | NOT NULL | Sort order (Spatie Sortable) |
| status | tinyint(1) | false | | 0 = inactive, 1 = active |
| deleted_at | timestamp | NULL | | Soft deletes |
| created_at | timestamp | NULL | | |
| updated_at | timestamp | NULL | | |

**Note:** The `sliders` table migration is missing from the production migrations directory. The schema is defined in `tests/Concerns/CreatesTestTables.php` for test purposes only.

### Translatable Columns
- `title` — stored as JSON: `{"en": "Summer Sale", "ar": "تخفيضات الصيف"}`

### Soft Deletes
The model uses `Illuminate\Database\Eloquent\SoftDeletes`. Records are soft-deleted by setting `deleted_at`.

### Sortable
The Spatie Sortable trait manages the `order` column with `sort_when_creating: true`.

---

## Table: `slider_product` (Pivot)

| Column | Type | Default | Constraints | Notes |
|--------|------|---------|-------------|-------|
| id | bigint unsigned | AUTO_INCREMENT | PK | |
| slider_id | bigint unsigned | | FK → sliders.id ON DELETE CASCADE | |
| product_id | bigint unsigned | | FK → products.id ON DELETE CASCADE | |
| created_at | timestamp | NULL | | |
| updated_at | timestamp | NULL | | |

### Indexes
- Primary: `id`
- Unique: `(slider_id, product_id)` — prevents duplicate associations

### Foreign Keys
- `slider_id` → `sliders.id` ON DELETE CASCADE
- `product_id` → `products.id` ON DELETE CASCADE

---

## Fillable Mass Assignment

```php
protected $fillable = [
    'title', 'slug', 'order', 'status'
];
```

---

## Media Collections

| Collection | Upload Method | Purpose |
|------------|---------------|---------|
| `slider-image-desktop` | `createSlider` | Desktop slider image |
| `slider-image-mobile` | `createSlider` | Mobile slider image |
| `sliders-desktop` | `updateSlider` | Desktop slider image (update) |
| `sliders-mobile` | `updateSlider` | Mobile slider image (update) |

**Note:** Create and update use different collection names (`slider-image-*` vs `sliders-*`).

---

## Migration Files

| File | Table | Notes |
|------|-------|-------|
| `database/migrations/2026_06_17_000003_create_slider_product_table.php` | `slider_product` | Pivot table |
| **(missing)** | `sliders` | No migration file exists in the project |

---

## Related Tables

| Table | Relation | Column |
|-------|----------|--------|
| `products` | BelongsToMany (via slider_product) | `slider_product.product_id` → `products.id` |
| `media` | MorphMany | model_type=`Marvel\Database\Models\Slider` |

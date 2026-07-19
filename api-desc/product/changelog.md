# Product Module — Changelog

## v1.0.0 (Current)

### Endpoints
- 5 CRUD endpoints: list, create, show, update, delete
- Additional: bulk-delete, destroy-all, toggle-fast-shipping
- Public read-only for index+show
- Authenticated for store/update/destroy

### Architecture
- Controller → Repository → Model pattern
- Separate FormRequests for create/update (ProductCreateRequest, ProductUpdateRequest)
- ProductResource with 40+ response fields
- ProductPricingService for discount/flash sale/variant pricing
- ProductFilter for advanced query filtering
- Soft deletes (deleted_at)

### Identified Issues
- **Missing DB transaction** in `ProductRepository::updateProduct()` (BUG-PRD-004)
- **Public helper methods** (`ProductStore()`, `updateProduct()`, `destroyProduct()`) should be private (BUG-PRD-001, BUG-PRD-002, BUG-PRD-003)
- **Missing English translation keys** for product CRUD messages (BUG-PRD-005)
- **No test coverage** — zero product CRUD tests exist
- Helper methods type-hint generic `Request` instead of specific FormRequests (BUG-PRD-006)

### Known Limitations
- No restore endpoint for soft-deleted products
- No product policy (authorization via middleware permissions only)
- No DTOs or Action classes
- `ProductStore()` method name uses PascalCase (inconsistent with camelCase convention)

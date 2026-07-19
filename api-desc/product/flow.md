# Product Module — Request Flow Diagrams

## 1. List Products (GET /products)

```
Client                  Routes.php            ProductController           Repository/Model
  │                         │                       │                         │
  │──── GET /products ─────>│                       │                         │
  │                         │── index(Request) ────>│                         │
  │                         │                       │── with('variations') ──>│
  │                         │                       │── with('categories') ───>│
  │                         │                       │── with('flash_sales') ──>│
  │                         │                       │                         │
  │                         │                       │── apply search ─────────>│
  │                         │                       │── apply filters ────────>│
  │                         │                       │── paginate(limit) ──────>│
  │                         │                       │                         │
  │                         │                       │<── ProductCollection ────│
  │                         │<── JSON 200 ──────────│                         │
  │<── JSON 200 ────────────│                       │                         │
```

## 2. Create Product (POST /products)

```
Client                  Routes.php            ProductController      ProductCreateRequest   ProductRepository     DB
  │                         │                       │                         │                    │              │
  │──── POST /products ────>│                       │                         │                    │              │
  │                         │── store(CreateRequest)│                         │                    │              │
  │                         │                       │── validate() ──────────>│                    │              │
  │                         │                       │<── validated ───────────│                    │              │
  │                         │                       │                         │                    │              │
  │                         │                       │── storeProduct(request)─>│                    │              │
  │                         │                       │                         │── beginTransaction >│              │
  │                         │                       │                         │── create product ──>│              │
  │                         │                       │                         │── addVariants() ───>│              │
  │                         │                       │                         │── upload images ───>│              │
  │                         │                       │                         │── sync relations ──>│              │
  │                         │                       │                         │── commit ──────────>│              │
  │                         │                       │                         │<── product ────────│              │
  │                         │                       │<── ProductResource ─────│                    │              │
  │                         │<── JSON 201 ──────────│                         │                    │              │
  │<── JSON 201 ────────────│                       │                         │                    │              │
```

## 3. Show Product (GET /products/{id})

```
Client                  Routes.php            ProductController           Repository/Model
  │                         │                       │                         │
  │──── GET /products/1 ───>│                       │                         │
  │                         │── show(Request, $id) >│                         │
  │                         │                       │── fetchSingleProduct() ─>│
  │                         │                       │── findOrFail(id) ───────>│
  │                         │                       │── load relations ───────>│
  │                         │                       │── fetchRelated() ───────>│
  │                         │                       │<── ProductResource ──────│
  │                         │<── JSON 200 ──────────│                         │
  │<── JSON 200 ────────────│                       │                         │
```

## 4. Update Product (PUT /products/{id})

```
Client                  Routes.php            ProductController      ProductUpdateRequest   ProductRepository     DB
  │                         │                       │                         │                    │              │
  │─── PUT /products/1 ────>│                       │                         │                    │              │
  │                         │── update(UpdateReq, $id│                        │                    │              │
  │                         │                       │── validate() ──────────>│                    │              │
  │                         │                       │<── validated ───────────│                    │              │
  │                         │                       │                         │                    │              │
  │                         │                       │── updateProduct(req,id) >│                    │              │
  │                         │                       │                         │── find product ────>│              │
  │                         │                       │                         │── delete old vars ─>│              │
  │                         │                       │                         │── addVariants() ───>│              │
  │                         │                       │                         │── update images ───>│              │
  │                         │                       │                         │── sync relations ──>│              │
  │                         │                       │                         │<── product ────────│              │
  │                         │                       │<── ProductResource ─────│                    │              │
  │                         │<── JSON 200 ──────────│                         │                    │              │
  │<── JSON 200 ────────────│                       │                         │                    │              │
```

## 5. Delete Product (DELETE /products/{id})

```
Client                  Routes.php            ProductController           Repository/Model
  │                         │                       │                         │
  │─── DELETE /products/1 ──>│                       │                         │
  │                         │── destroy($id) ───────>│                         │
  │                         │                       │── findOrFail(id) ───────>│
  │                         │                       │── softDelete() ─────────>│
  │                         │                       │                         │
  │                         │<── JSON 200 ──────────│                         │
  │<── JSON 200 ────────────│                       │                         │
```

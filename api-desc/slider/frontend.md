# Slider Module — Frontend Integration Guide

## Public Endpoints

---

### GET /api/v1/general/sliders

Fetch list of active sliders for display (e.g., homepage banner carousel).

**Request:**
```js
fetch('/api/v1/general/sliders')
  .then(res => res.json())
  .then(data => console.log(data.data));
// Returns: [{ id, title, slug, status, image: { desktop, mobile } }]
```

**Response Schema:**
```json
{
  "data": [
    {
      "id": 1,
      "title": "Summer Sale",
      "slug": "summer-sale",
      "status": true,
      "image": {
        "desktop": "https://example.com/storage/sliders/desktop.jpg",
        "mobile": "https://example.com/storage/sliders/mobile.jpg"
      }
    }
  ]
}
```

**Usage:**
- Display as a hero/carousel banner on the homepage
- Use `image.desktop` for large screens, `image.mobile` for small screens
- Link sliders to product listings via slug or custom routing

---

### GET /api/v1/general/sliders/{slug}

Fetch a single slider with associated products.

**Response Schema:**
```json
{
  "data": {
    "id": 1,
    "title": "Summer Sale",
    "slug": "summer-sale",
    "status": true,
    "image": { "desktop": "...", "mobile": "..." },
    "products": [
      { "id": 5, "name": "Product Name", "slug": "product-name", "price": 100 }
    ]
  }
}
```

**Usage:**
- Fetch slider detail page with product listing
- Display products associated with the slider

---

## Admin Endpoints

### GET /api/v1/sliders
Admin slider listing (paginated, filterable by active status).

### POST /api/v1/sliders
Create slider (multipart/form-data with images).

### GET /api/v1/sliders/{id}
Show slider by ID.

### PUT /api/v1/sliders/{id}
Update slider (multipart/form-data).

### DELETE /api/v1/sliders/{id}
Soft delete slider.

### PATCH /api/v1/sliders/change-status
Toggle active/inactive.

### PUT /api/v1/sliders/reorder
Reorder sliders via sorted ID array.

---

## Frontend Patterns

### Loading State
```jsx
function SliderBanner() {
  const [loading, setLoading] = useState(true);
  const [sliders, setSliders] = useState([]);

  useEffect(() => {
    fetch('/api/v1/general/sliders')
      .then(res => res.json())
      .then(data => {
        setSliders(data.data || []);
        setLoading(false);
      })
      .catch(() => setLoading(false));
  }, []);

  if (loading) return <Skeleton variant="rectangular" height={400} />;
  // ...
}
```

### Empty State
```jsx
{sliders.length === 0 && (
  <EmptyState
    icon={<ImageIcon />}
    title="No sliders available"
    description="Check back later for new promotions"
  />
)}
```

### Banner Carousel Component
```jsx
function SliderCarousel({ sliders }) {
  const [current, setCurrent] = useState(0);

  return (
    <div className="carousel">
      {sliders.map((slider, index) => (
        <div key={slider.id} className={index === current ? 'active' : 'hidden'}>
          <picture>
            <source media="(min-width: 768px)" srcSet={slider.image.desktop} />
            <img src={slider.image.mobile} alt={slider.title} />
          </picture>
          <div className="overlay">
            <h2>{slider.title}</h2>
          </div>
        </div>
      ))}
      <button onClick={() => setCurrent((current + 1) % sliders.length)}>Next</button>
    </div>
  );
}
```

### Admin CRUD Table
```jsx
// Columns: ID, Title, Slug, Status, Order, Image Preview, Actions (Edit, Delete, Toggle)
// Filters: Active/Inactive toggle
// Drag-and-drop: Reorder via PUT /sliders/reorder
```

### Admin Create/Edit Form
```jsx
// Fields:
//   title (multilingual: en, ar text inputs)
//   image_desktop (file upload with preview)
//   image_mobile (file upload with preview)
//   status (toggle)
//   products (multi-select, optional)
```

### Reorder UI
```jsx
// Drag-and-drop list:
//   1. User drags slider items to reorder
//   2. On drop, collect IDs in new order
//   3. PUT /api/v1/sliders/reorder { sliders: [3, 1, 2] }
//   4. Refresh list
```

### Key Considerations
1. **Responsive images** — Use `<picture>` element with `image.desktop` and `image.mobile`
2. **Translatable titles** — Send `title` as `{"en": "...", "ar": "..."}`
3. **Image upload** — Use `multipart/form-data` for create/update
4. **Soft deletes** — Deleted sliders disappear from listings
5. **Status toggle** — Use PATCH endpoint for quick toggle without opening edit form
6. **Product associations** — Sliders can link to products for promotional sections

# Products Catalog

A product catalog application that proxies the [DummyJSON](https://dummyjson.com) public API.
Delivered in two independent forms:

1. **Django microservice** — Python backend that renders the full HTML page server-side.
2. **WordPress plugin** — Self-contained PHP plugin with the same features, rendered server-side via PHP.

> **Architecture:** All API calls, search, and pagination are handled on the backend.
> JavaScript is used **only** for the gallery toggle (show/hide a pre-rendered row).
> It does not fetch, filter, paginate, or render any product data.

---

## Features

- Server-rendered product table (Thumbnail, Title, Description, Price, Rating, Stock, Brand, Category)
- Search — `method="GET"` form submission, handled entirely by the backend
- Pagination — plain anchor links with backend-generated URLs
- Gallery toggle — reveals a hidden row with up to 3 pre-rendered product images (no extra network request)
- Graceful error handling — upstream failures show a banner instead of crashing
- Provider pattern — swap the data source by changing one line, no other code changes needed

---

## Part 1 — Django Microservice

### Requirements

| Tool | Version |
|------|---------|
| Python | 3.11+ |
| [uv](https://github.com/astral-sh/uv) (recommended) or pip | any |

### Installation

```bash
# 1. Clone and enter the project directory
git clone https://github.com/SapKi/seperiaProject.git
cd seperiaProject

# 2. Create a virtual environment and install dependencies
uv venv .venv
uv pip install -r requirements.txt

# — or with plain pip —
python -m venv .venv
.venv\Scripts\activate          # Windows
pip install -r requirements.txt
```

### Configuration

Copy `.env.example` to `.env` (defaults work out of the box for local dev):

```
DEBUG=True
SECRET_KEY=django-insecure-replace-this-in-production
ALLOWED_HOSTS=localhost,127.0.0.1
DUMMYJSON_BASE_URL=https://dummyjson.com
DUMMYJSON_TIMEOUT=10
```

### Run

```bash
uv run python manage.py runserver
# or, with the venv activated:
python manage.py runserver
```

Open **http://127.0.0.1:8000/** in your browser.

---

### Project Structure

```
pythonProject/
│
├── manage.py                          Django CLI entry point (runserver, check, etc.)
├── requirements.txt                   Python dependencies: Django, requests
├── .env.example                       Template for environment variables
├── postman_collection.json            Ready-to-import Postman collection for the JSON API
│
├── config/                            Django project configuration package
│   ├── settings.py                    All settings: installed apps, middleware, DummyJSON config
│   ├── urls.py                        Root URL router — delegates everything to products/urls.py
│   └── wsgi.py                        WSGI entry point for production servers (gunicorn, etc.)
│
└── products/                          The single Django app — all product logic lives here
    │
    ├── providers/                     Data source layer — isolated from views and service logic
    │   ├── base.py                    Defines the interface every data source must implement:
    │   │                              • ProductsProvider (abstract base class)
    │   │                              • ProductsPage (dataclass: products, total, skip, limit)
    │   │                              • ProviderError (base exception for all provider errors)
    │   │
    │   └── dummyjson.py               DummyJSON implementation of ProductsProvider.
    │                                  The ONLY file that knows about DummyJSON's URL structure,
    │                                  query parameters, and response shape.
    │                                  To swap the data source: create a new file here,
    │                                  implement fetch(), change one line in views.py.
    │
    ├── services.py                    Orchestration layer.
    │                                  ProductsService accepts any ProductsProvider — it has
    │                                  zero knowledge of DummyJSON or any specific source.
    │                                  Converts ProviderError → ProductServiceError so views
    │                                  stay decoupled from provider internals.
    │
    ├── views.py                       HTTP layer. Two views:
    │                                  • ProductListView — parses query params, calls the service,
    │                                    passes a ProductsPage to the template, returns full HTML.
    │                                  • ProductListAPIView — same logic, returns JSON instead.
    │                                  Views only import ProductsService and ProductServiceError —
    │                                  they never reference DummyJSON directly.
    │
    ├── urls.py                        URL routing:
    │                                  • /               → ProductListView  (HTML)
    │                                  • /api/products/  → ProductListAPIView (JSON)
    │
    ├── apps.py                        Registers the app with Django (required boilerplate)
    │
    └── templates/products/
        ├── base.html                  Shared layout: header, container, and all CSS
        └── product_list.html          The product page:
                                       • Search form (method="GET")
                                       • Server-rendered <table> with one row per product
                                       • Hidden gallery <tr> after each product row
                                       • Pagination links (backend-generated <a> tags)
                                       • 10-line gallery toggle script (the only JS)
```

---

### Layered Architecture

The Django backend is split into four distinct layers. Each layer only depends on the layer below it — never above.

```
┌─────────────────────────────────────────────┐
│  views.py  (HTTP layer)                     │
│  Knows: ProductsService, ProductServiceError│
└──────────────────┬──────────────────────────┘
                   │ calls
┌──────────────────▼──────────────────────────┐
│  services.py  (Orchestration layer)         │
│  Knows: ProductsProvider (interface only)   │
└──────────────────┬──────────────────────────┘
                   │ calls
┌──────────────────▼──────────────────────────┐
│  providers/base.py  (Contract)              │
│  Defines: ProductsProvider, ProductsPage,   │
│           ProviderError                     │
└──────────────────┬──────────────────────────┘
                   │ implemented by
┌──────────────────▼──────────────────────────┐
│  providers/dummyjson.py  (Data source)      │
│  Knows: DummyJSON URLs, params, response    │
└─────────────────────────────────────────────┘
```

**To replace DummyJSON with any other API or database:**

```python
# 1. Create products/providers/my_source.py
from .base import ProductsProvider, ProductsPage

class MySourceProvider(ProductsProvider):
    def fetch(self, *, page, limit, search) -> ProductsPage:
        # your logic here
        return ProductsPage(products=[...], total=100, skip=0, limit=limit)

# 2. Change ONE line in views.py — nothing else changes
from .providers.my_source import MySourceProvider
_service = ProductsService(provider=MySourceProvider())
```

---

### API Endpoints

| Method | URL | Description |
|--------|-----|-------------|
| `GET` | `/` | Server-rendered HTML catalog page |
| `GET` | `/api/products/` | JSON — all products |
| `GET` | `/api/products/?search=phone` | JSON — keyword search |
| `GET` | `/api/products/?page=2&limit=5` | JSON — paginated |
| `GET` | `/api/products/?search=apple&page=1&limit=20` | JSON — combined |

**Query parameters**

| Parameter | Default | Max | Description |
|-----------|---------|-----|-------------|
| `page` | `1` | — | Page number |
| `limit` | `10` | `100` | Items per page |
| `search` | `""` | — | Keyword filter |

**JSON response shape**

```json
{
  "products":    [ { "id": 1, "title": "...", ... } ],
  "total":       194,
  "skip":        0,
  "limit":       10,
  "page":        1,
  "total_pages": 20
}
```

---

## Part 2 — WordPress Plugin

### Installation

1. Copy `wordpress-plugin/products-catalog/` into your WordPress installation:
   ```
   wp-content/plugins/products-catalog/
   ```
2. Go to **WP Admin → Plugins** and activate **Products Catalog**.
3. The plugin automatically creates a page titled **"Compare Assignment"** containing `[products_catalog]`.
4. Visit that page — the catalog renders immediately.

You can also place `[products_catalog]` on any other page or post.

---

### Plugin Structure

```
wordpress-plugin/
└── products-catalog/
    │
    ├── products-catalog.php           Main plugin file.
    │                                  Declares the plugin to WordPress, loads all classes,
    │                                  and registers activation hook and shortcode.
    │
    ├── includes/
    │   ├── class-activator.php        Runs once on plugin activation.
    │                                  Creates the "Compare Assignment" page with [products_catalog]
    │                                  in its content. Idempotent — safe to re-activate.
    │
    │   └── class-shortcode.php        Registers [products_catalog] shortcode.
    │                                  Reads pc_page / pc_search / pc_limit from $_GET,
    │                                  calls DummyJSON from PHP, and renders the full HTML table
    │                                  server-side — same approach as the Django implementation.
    │                                  Enqueues CSS and the gallery-toggle JS.
    │
    └── assets/
        ├── css/products-catalog.css   All styles scoped under #products-catalog-app.
        │                              Uses pc- prefix to avoid conflicts with theme styles.
        └── js/products-catalog.js     Gallery toggle only (10 lines).
                                       Does NOT fetch, filter, paginate, or render product data.
```

---

## How the Application Works

### Request flow (Django)

```
1. Browser sends:  GET /?search=phone&page=2

2. ProductListView._parse_params() extracts and validates query params

3. ProductsService.get_products() delegates to DummyJSONProvider.fetch()

4. DummyJSONProvider calls:  GET https://dummyjson.com/products/search?q=phone&limit=10&skip=10

5. DummyJSON responds → ProductsPage dataclass is returned up through the layers

6. Django renders product_list.html — every row including hidden gallery rows
   is written to HTML on the server before the browser receives the page

7. Browser receives the complete, fully-rendered HTML page

8. User searches / paginates → new GET request → back to step 1
```

### Request flow (WordPress)

```
1. Browser sends:  GET /compare-assignment/?pc_search=phone&pc_page=2

2. WordPress renders the page → encounters [products_catalog] shortcode

3. class-shortcode.php reads $_GET params, calls DummyJSON from PHP

4. PHP renders the full product table HTML and returns it as shortcode output

5. Browser receives the complete page — no AJAX, no JS data loading
```

### Gallery (the only JavaScript in both implementations)

The gallery `<tr>` for each product is already in the HTML at page load, hidden with the `hidden` attribute. Clicking "Gallery" only flips that attribute — no network request is made.

```javascript
document.addEventListener('click', function (e) {
  var button = e.target.closest('.btn-gallery');
  if (!button) return;
  var galleryRow = document.getElementById(button.dataset.galleryId);
  if (!galleryRow) return;
  var opening = galleryRow.hidden;
  galleryRow.hidden  = !opening;
  button.textContent = opening ? 'Close Gallery' : 'Open Gallery';
  button.setAttribute('aria-expanded', String(opening));
});
```

---

## Assumptions & Design Decisions

| Decision | Reason |
|----------|--------|
| Provider pattern (abstract base + implementations) | Decouples the data source from all other layers. Swapping DummyJSON for another API or a database requires creating one new file and changing one line in `views.py`. |
| No database | Pure proxy — all data lives in DummyJSON. A DB would add complexity with no benefit. |
| Server-side rendering | Assignment requirement. Search and pagination are standard HTML form/link GET requests — no JS data loading needed. |
| `ProductsPage` dataclass | Gives the service and view layers a typed, source-agnostic return value instead of a raw dict keyed on DummyJSON field names. |
| `uv` for Python env | Fast, single-binary tool. Falls back to standard `pip + venv`. |
| `urlencode` on search in pagination links | Prevents broken URLs when the search query contains spaces or special characters. |
| Gallery rows pre-rendered and hidden | Keeps JS to a minimum — no fetch, no DOM construction from data. |
| WordPress `pc_` query param prefix | Avoids conflicts with WordPress's own reserved query variables (`page`, `search`, etc.). |
| WordPress CSS prefix `pc-` | Avoids collisions with theme or other plugin styles. |
| WordPress page creation is idempotent | Activator checks for an existing "Compare Assignment" page before inserting — safe to re-activate. |

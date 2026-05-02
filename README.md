# Products Catalog

A product catalog application that proxies the [DummyJSON](https://dummyjson.com) public API.
Delivered in two independent forms:

1. **Django microservice** — Python backend that renders the full HTML page server-side.
2. **WordPress plugin** — Self-contained PHP plugin with the same features via WordPress AJAX.

> **Architecture:** All API calls, search, and pagination are handled on the backend.
> JavaScript is used **only** for the gallery toggle (show/hide a pre-rendered row).
> It does not fetch, filter, paginate, or render any product data.

---

## Features

- Server-rendered product table (Thumbnail, Title, Description, Price, Rating, Stock, Brand, Category)
- Search — form `GET` submission, handled entirely by the backend
- Pagination — plain anchor links, page computed by the backend
- Gallery toggle — clicking "Gallery" reveals a hidden row with up to 3 product images (pre-rendered, no extra request)
- Graceful error handling — DummyJSON failures show a banner instead of crashing

---

## Part 1 — Django Microservice

### Requirements

| Tool | Version |
|------|---------|
| Python | 3.11+ |
| [uv](https://github.com/astral-sh/uv) (recommended) or pip | any |

### Installation

```bash
# 1. Enter the project directory
cd pythonProject

# 2. Create a virtual environment and install dependencies
uv venv .venv
uv pip install -r requirements.txt

# — or with plain pip —
python -m venv .venv
.venv\Scripts\activate        # Windows
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
├── manage.py                       Django CLI entry point (start server, run checks, etc.)
├── requirements.txt                Python dependencies (Django, requests)
├── .env.example                    Template for environment variables
├── postman_collection.json         Ready-to-import Postman collection for the JSON API
│
├── config/                         Django project configuration package
│   ├── settings.py                 All settings: installed apps, middleware, DummyJSON config
│   ├── urls.py                     Root URL router — delegates everything to products/urls.py
│   └── wsgi.py                     WSGI entry point used by production servers (gunicorn, etc.)
│
└── products/                       The single Django app — all product logic lives here
    │
    ├── services.py                 DummyJSON API client
    │                               Builds the correct URL (search vs. browse), sends the
    │                               HTTP request, handles timeouts and errors, returns JSON.
    │
    ├── views.py                    Two views:
    │                               • ProductListView — reads query params, calls services.py,
    │                                 passes data to the template, returns full HTML page.
    │                               • ProductListAPIView — same logic but returns raw JSON
    │                                 (used by the Postman collection).
    │
    ├── urls.py                     Maps URLs to views:
    │                               • /               → ProductListView
    │                               • /api/products/  → ProductListAPIView
    │
    ├── apps.py                     Registers the app with Django (required boilerplate)
    │
    └── templates/
        └── products/
            ├── base.html           Shared page layout: header, container, and all CSS styles
            └── product_list.html   The product page:
                                    • Search form (method="GET")
                                    • Server-rendered <table> with one row per product
                                    • Hidden gallery <tr> after each row (toggled by JS)
                                    • Pagination links (plain <a> tags with backend-generated URLs)
                                    • 8-line gallery toggle script (the only JS in the project)
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
    ├── products-catalog.php        Main plugin file — declares the plugin to WordPress,
    │                               loads all classes, and registers activation + AJAX hooks.
    │
    ├── includes/
    │   ├── class-activator.php     Runs once on plugin activation.
    │                               Creates the "Compare Assignment" page (idempotent —
    │                               safe to re-activate without creating duplicates).
    │
    │   ├── class-api.php           WordPress AJAX handler (/wp-admin/admin-ajax.php).
    │                               Reads page/limit/search params, calls DummyJSON from PHP,
    │                               and returns a JSON response to the browser.
    │
    │   └── class-shortcode.php     Registers the [products_catalog] shortcode.
    │                               Outputs the HTML shell and enqueues the CSS + JS assets.
    │
    └── assets/
        ├── css/products-catalog.css   All styles scoped under #products-catalog-app
        │                              (pc- prefix avoids conflicts with theme styles).
        └── js/products-catalog.js     Gallery-toggle script — identical behaviour to the
                                       Django version. Reads config injected by
                                       wp_localize_script (AJAX URL + action name).
```

---

## How the Application Works

### Request flow (Django)

```
1. Browser sends:  GET /?search=phone&page=2

2. ProductListView reads query params → calls ProductsService.get_products()

3. ProductsService sends:  GET https://dummyjson.com/products/search?q=phone&limit=10&skip=10

4. DummyJSON responds with matching products

5. Django renders product_list.html with the product data
   • Every product row is written to HTML on the server
   • The gallery <tr> (hidden) is written right after each product row

6. Browser receives the complete, fully-rendered HTML page

7. User searches / paginates → browser sends a new GET request → repeat from step 1
```

### Gallery (the only JS)

The gallery `<tr>` for each product is already in the HTML when the page loads — hidden with the `hidden` attribute. Clicking "Gallery" only flips that attribute. No network request is made.

```javascript
// The entire JavaScript in this project:
document.addEventListener('click', function (e) {
  var button = e.target.closest('.btn-gallery');
  if (!button) return;
  var galleryRow = document.getElementById(button.dataset.galleryId);
  if (!galleryRow) return;
  var opening = galleryRow.hidden;
  galleryRow.hidden  = !opening;
  button.textContent = opening ? 'Close Gallery' : 'Gallery';
  button.setAttribute('aria-expanded', String(opening));
});
```

---

## Assumptions & Design Decisions

| Decision | Reason |
|----------|--------|
| No database | Pure proxy — all data lives in DummyJSON. A DB would add complexity with no benefit. |
| Server-side rendering | Assignment requirement. Search and pagination are standard HTML form/link GET requests. |
| `uv` for Python env | Fast, single-binary tool. Falls back to standard `pip + venv`. |
| `urlencode` on search in pagination links | Prevents broken URLs when the search query contains spaces or special characters. |
| Gallery rows pre-rendered and hidden | Keeps JS minimal — no fetch, no DOM construction from data. |
| WordPress CSS prefix `pc-` | Avoids collisions with theme or other plugin styles. |
| WordPress AJAX over REST API | `admin-ajax.php` works on every WordPress install without extra configuration. |
| Page creation is idempotent | Activator checks for an existing "Compare Assignment" page before inserting. |

# Products Catalog

A full-stack product catalog application that fetches data from the [DummyJSON](https://dummyjson.com) public API. It is delivered in two independent forms:

1. **Django microservice** — a Python backend with a JSON REST API and a server-served HTML shell driven by vanilla JavaScript.
2. **WordPress plugin** — a self-contained PHP plugin that mirrors all features via WordPress AJAX.

Both implementations share the same approach: all calls to the DummyJSON API are made **server-side**; the browser only ever talks to your own server.

> **Architecture note:** To comply with the assignment requirements, all API calls, search, and pagination are handled on the backend. JavaScript is used only for the gallery toggle interaction and does not fetch, filter, paginate, or render product data.

---

## Features

- Paginated product table (Title, Description, Price, Rating, Stock, Brand, Category, Thumbnail)
- Live search — filters results via the DummyJSON search endpoint
- Gallery toggle — clicking "Gallery" opens an inline image strip between rows (up to 3 images, no extra network request)
- Graceful error handling — network failures surface as a banner, never a broken page
- No frontend framework — 100 % vanilla JavaScript

---

## Part 1 — Django Microservice

### Requirements

| Tool | Version |
|------|---------|
| Python | 3.11 + |
| [uv](https://github.com/astral-sh/uv) (recommended) or pip | any |

### Installation

```bash
# 1. Clone / enter the project directory
cd pythonProject

# 2. Create a virtual environment and install dependencies
uv venv .venv
uv pip install -r requirements.txt

# — or with plain pip —
python -m venv .venv
.venv\Scripts\activate          # Windows
pip install -r requirements.txt
```

### Configuration

Copy `.env.example` to `.env` and adjust if needed (defaults work out of the box):

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

### API Endpoints

| Method | URL | Description |
|--------|-----|-------------|
| GET | `/` | HTML product catalog page |
| GET | `/api/products/` | JSON — all products |
| GET | `/api/products/?search=phone` | JSON — filtered |
| GET | `/api/products/?page=2&limit=5` | JSON — paginated |
| GET | `/api/products/?search=apple&page=1&limit=20` | JSON — combined |

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
  "total":       100,
  "skip":        0,
  "limit":       10,
  "page":        1,
  "total_pages": 10
}
```

### Project Structure

```
pythonProject/
├── manage.py
├── requirements.txt
├── .env.example
├── config/
│   ├── settings.py       environment-driven configuration
│   ├── urls.py
│   └── wsgi.py
└── products/
    ├── services.py       DummyJSON API client + DummyJSONError
    ├── views.py          ProductListView (HTML shell) + ProductListAPIView (JSON)
    ├── urls.py
    ├── templatetags/
    │   └── product_filters.py   tojson custom filter
    └── templates/products/
        ├── base.html     shared layout + CSS
        └── list.html     HTML shell + vanilla JS application
```

---

## Part 2 — WordPress Plugin

### Installation

1. Copy the `wordpress-plugin/products-catalog/` folder into your WordPress installation:
   ```
   wp-content/plugins/products-catalog/
   ```
2. Log in to **WP Admin → Plugins** and activate **Products Catalog**.
3. On activation the plugin automatically creates a page titled **"Compare Assignment"** with the `[products_catalog]` shortcode in its content.
4. Visit that page in your browser — the catalog renders immediately.

You can also add `[products_catalog]` to any other page or post.

### Plugin Structure

```
products-catalog/
├── products-catalog.php           main plugin file; registers hooks
├── includes/
│   ├── class-activator.php        creates the "Compare Assignment" page on activation
│   ├── class-api.php              WordPress AJAX handler — proxies DummyJSON calls
│   └── class-shortcode.php        registers [products_catalog] shortcode; enqueues assets
└── assets/
    ├── css/products-catalog.css   scoped styles (prefix: pc-)
    └── js/products-catalog.js     vanilla JS — identical logic to Django frontend
```

### How it works

```
Browser                  WordPress (PHP)              DummyJSON
  |                           |                           |
  | GET /wp-admin/admin-ajax  |                           |
  |   ?action=products_catalog_fetch&page=1&search=phone  |
  |-------------------------> |                           |
  |                           | GET /products/search?q=phone
  |                           |-------------------------> |
  |                           | <------------------------ |
  |  { success: true, data: { products, total, ... } }    |
  | <------------------------ |                           |
  | renders table via JS      |                           |
```

---

## How the Application Works

### Data flow (Django)

```
Browser → GET /api/products/?page=1&search=phone
            ↓
        ProductListAPIView._parse_params()
            ↓
        ProductsService.get_products()   ← calls DummyJSON
            ↓
        JSON response → Browser
            ↓
        vanilla JS renders table, pagination, gallery
```

### Gallery

When the user clicks "Gallery," the JavaScript reads the `data-images` attribute that was embedded in the table row during rendering. It creates a `<tr class="gallery-row">` immediately after the clicked row and appends up to 3 `<img>` elements. No additional network request is made. Clicking the button again removes the gallery row.

### Search & Pagination

Both are handled by the backend:
- **Search** forwards the `q` parameter to `GET /products/search` on DummyJSON.
- **Pagination** converts `page` + `limit` to `skip` = `(page − 1) × limit` for DummyJSON.
- The JavaScript sends a new fetch on every search submit or page link click, replaces the table body, and re-renders the pagination controls.

---

## Assumptions & Design Decisions

| Decision | Reason |
|----------|--------|
| No database | This is a pure proxy — all data lives in DummyJSON. Adding a DB would add complexity with no benefit. |
| `uv` for Python env | Fast, single-binary, no system Python conflict. Falls back to standard `pip + venv`. |
| Vanilla JS only | Requirement. An IIFE module pattern keeps the global namespace clean. |
| Event delegation for gallery | One listener on `<tbody>` handles all gallery buttons, including rows added dynamically on page change. |
| `esc()` helper | Manually replaces `& < > "` instead of relying on `element.textContent` so the result is safe inside both text nodes *and* attribute values in template literals. |
| WordPress CSS prefix `pc-` | Avoids collisions with theme or other plugin styles. |
| WordPress AJAX (no REST API) | `admin-ajax.php` works on every WordPress install without additional routing configuration. |
| Page created idempotently | The activator checks for an existing "Compare Assignment" page before inserting, so deactivating and re-activating does not duplicate it. |

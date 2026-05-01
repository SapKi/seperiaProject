/**
 * Products Catalog — WordPress Plugin Frontend
 *
 * Reads configuration injected by wp_localize_script:
 *   window.ProductsCatalogConfig = { ajaxUrl, action }
 *
 * All DummyJSON API calls are made by the PHP backend (class-api.php).
 * This script calls the WordPress AJAX endpoint, which proxies the request.
 */
(function () {
  'use strict';

  // ── Configuration (injected by wp_localize_script) ──────────────
  var CFG = window.ProductsCatalogConfig || {};

  // ── State ──────────────────────────────────────────────────────────
  var state = {
    page:    1,
    limit:   10,
    search:  '',
    loading: false,
  };

  // ── DOM references ─────────────────────────────────────────────────
  var dom = {
    app:         document.getElementById('products-catalog-app'),
    searchForm:  document.getElementById('pc-search-form'),
    searchInput: document.getElementById('pc-search-input'),
    clearBtn:    document.getElementById('pc-clear-btn'),
    metaInfo:    document.getElementById('pc-meta-info'),
    errorBanner: document.getElementById('pc-error-banner'),
    tbody:       document.getElementById('pc-products-body'),
    pagination:  document.getElementById('pc-pagination'),
  };

  // Guard: abort if the shortcode is not on this page.
  if ( ! dom.app ) return;

  // ── Utilities ──────────────────────────────────────────────────────

  function esc(value) {
    return String(value == null ? '' : value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function stockClass(stock) {
    if (stock <= 10) return 'pc-stock-low';
    if (stock <= 30) return 'pc-stock-mid';
    return 'pc-stock-ok';
  }

  // ── API ────────────────────────────────────────────────────────────

  function buildUrl(page, limit, search) {
    var url = CFG.ajaxUrl + '?action=' + encodeURIComponent(CFG.action) +
              '&page='  + encodeURIComponent(page) +
              '&limit=' + encodeURIComponent(limit);
    if (search) url += '&search=' + encodeURIComponent(search);
    return url;
  }

  function fetchProducts(page, limit, search) {
    return fetch(buildUrl(page, limit, search))
      .then(function (res) {
        if (!res.ok) throw new Error('Server returned ' + res.status);
        return res.json();
      })
      .then(function (json) {
        // WordPress AJAX wraps payload in { success, data }.
        if (!json.success) {
          throw new Error((json.data && json.data.message) || 'Request failed.');
        }
        return json.data;
      });
  }

  // ── Rendering ──────────────────────────────────────────────────────

  function renderRows(products) {
    dom.tbody.innerHTML = '';

    if (!products || !products.length) {
      var empty = document.createElement('tr');
      empty.innerHTML =
        '<td colspan="9">' +
          '<div class="pc-empty-state">' +
            '<h3>No products found</h3>' +
            '<p>Try a different search term or clear the filter.</p>' +
          '</div>' +
        '</td>';
      dom.tbody.appendChild(empty);
      return;
    }

    products.forEach(function (p) {
      var tr = document.createElement('tr');
      tr.className = 'pc-product-row';
      tr.dataset.productId = p.id;
      tr.dataset.images    = JSON.stringify(p.images || []);

      tr.innerHTML =
        '<td>' +
          '<img class="pc-thumbnail"' +
              ' src="'     + esc(p.thumbnail) + '"' +
              ' alt="'     + esc(p.title) + ' thumbnail"' +
              ' loading="lazy" />' +
        '</td>' +
        '<td class="pc-td-title">'  + esc(p.title)  + '</td>' +
        '<td>' +
          '<span class="pc-td-desc" title="' + esc(p.description) + '">' +
            esc(p.description) +
          '</span>' +
        '</td>' +
        '<td class="pc-td-price">$' + esc(p.price)  + '</td>' +
        '<td class="pc-td-rating">' +
          '<span class="pc-stars" aria-hidden="true">&#9733;</span> ' +
          esc(p.rating) +
        '</td>' +
        '<td>' +
          '<span class="' + stockClass(p.stock) + '">' + esc(p.stock) + '</span>' +
        '</td>' +
        '<td>' + esc(p.brand) + '</td>' +
        '<td><span class="pc-badge">' + esc(p.category) + '</span></td>' +
        '<td>' +
          '<button class="pc-btn pc-btn-gallery" aria-expanded="false">' +
            'Gallery' +
          '</button>' +
        '</td>';

      dom.tbody.appendChild(tr);
    });
  }

  function renderPagination(totalPages, currentPage) {
    dom.pagination.innerHTML = '';
    if (totalPages <= 1) return;

    function makeBtn(label, targetPage, disabled, active) {
      if (disabled) {
        var s = document.createElement('span');
        s.className = 'pc-disabled';
        s.innerHTML = label;
        return s;
      }
      var a = document.createElement('a');
      a.href = '#';
      a.innerHTML = label;
      if (active) {
        a.className = 'pc-current';
        a.setAttribute('aria-current', 'page');
      }
      a.addEventListener('click', function (e) {
        e.preventDefault();
        load(targetPage);
      });
      return a;
    }

    dom.pagination.appendChild(makeBtn('&laquo; Prev', currentPage - 1, currentPage === 1));

    var winStart = Math.max(1, currentPage - 2);
    var winEnd   = Math.min(totalPages, currentPage + 2);
    for (var p = winStart; p <= winEnd; p++) {
      dom.pagination.appendChild(makeBtn(p, p, false, p === currentPage));
    }

    dom.pagination.appendChild(makeBtn('Next &raquo;', currentPage + 1, currentPage === totalPages));
  }

  function renderMeta(total, totalPages) {
    if (state.search) {
      dom.metaInfo.innerHTML =
        '<strong>' + total + '</strong> ' +
        'result' + (total !== 1 ? 's' : '') +
        ' for &ldquo;' + esc(state.search) + '&rdquo;';
    } else {
      dom.metaInfo.innerHTML =
        '<strong>' + total + '</strong> ' +
        'product' + (total !== 1 ? 's' : '') +
        ' &mdash; page ' + state.page + ' of ' + totalPages;
    }
  }

  // ── Load ────────────────────────────────────────────────────────────

  function load(page) {
    if (state.loading) return;
    state.page  = page;
    state.loading = true;

    dom.tbody.innerHTML = '<tr><td colspan="9" class="pc-loading">Loading&hellip;</td></tr>';
    dom.errorBanner.hidden   = true;
    dom.pagination.innerHTML = '';

    fetchProducts(state.page, state.limit, state.search)
      .then(function (data) {
        renderRows(data.products);
        renderPagination(data.total_pages, data.page);
        renderMeta(data.total, data.total_pages);
      })
      .catch(function (err) {
        dom.errorBanner.textContent = err.message || 'Failed to load products.';
        dom.errorBanner.hidden = false;
        dom.tbody.innerHTML = '';
      })
      .finally(function () {
        state.loading = false;
      });
  }

  // ── Gallery ─────────────────────────────────────────────────────────

  function toggleGallery(button) {
    var row       = button.closest('tr');
    var productId = row.dataset.productId;
    var galleryId = 'pc-gallery-' + productId;
    var existing  = document.getElementById(galleryId);

    if (existing) {
      existing.remove();
      button.textContent = 'Gallery';
      button.classList.remove('open');
      button.setAttribute('aria-expanded', 'false');
      return;
    }

    var images = [];
    try { images = JSON.parse(row.dataset.images).slice(0, 3); } catch (_) {}

    var galleryRow    = document.createElement('tr');
    galleryRow.id     = galleryId;
    galleryRow.className = 'pc-gallery-row';

    var td     = document.createElement('td');
    td.colSpan = 9;

    var strip  = document.createElement('div');
    strip.className = 'pc-gallery-strip';

    if (!images.length) {
      var msg       = document.createElement('span');
      msg.className   = 'pc-gallery-empty';
      msg.textContent = 'No images available for this product.';
      strip.appendChild(msg);
    } else {
      images.forEach(function (src) {
        var img   = document.createElement('img');
        img.src   = src;
        img.alt   = 'Product image';
        img.loading = 'lazy';
        strip.appendChild(img);
      });
    }

    td.appendChild(strip);
    galleryRow.appendChild(td);
    row.insertAdjacentElement('afterend', galleryRow);

    button.textContent = 'Close Gallery';
    button.classList.add('open');
    button.setAttribute('aria-expanded', 'true');
  }

  // ── Event delegation ─────────────────────────────────────────────────

  dom.tbody.addEventListener('click', function (e) {
    var btn = e.target.closest('.pc-btn-gallery');
    if (btn) toggleGallery(btn);
  });

  dom.searchForm.addEventListener('submit', function (e) {
    e.preventDefault();
    state.search = dom.searchInput.value.trim();
    dom.clearBtn.hidden = !state.search;
    load(1);
  });

  dom.clearBtn.addEventListener('click', function () {
    dom.searchInput.value = '';
    state.search = '';
    dom.clearBtn.hidden = true;
    load(1);
  });

  // ── Boot ─────────────────────────────────────────────────────────────
  load(1);

}());

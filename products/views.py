import logging

from django.http import JsonResponse
from django.shortcuts import render
from django.views import View

from .providers.dummyjson import DummyJSONProvider  # ← swap this line to change source
from .services import ProductServiceError, ProductsService

logger = logging.getLogger(__name__)

# To use a different data source, replace DummyJSONProvider with any class
# that implements products.providers.base.ProductsProvider.
_service = ProductsService(provider=DummyJSONProvider())

DEFAULT_PAGE_SIZE = 10
MAX_PAGE_SIZE = 100


def _parse_params(request) -> tuple[int, int, str]:
    """Extract and validate page, limit, and search from query params."""
    try:
        page = max(1, int(request.GET.get("page", 1)))
    except (ValueError, TypeError):
        page = 1

    try:
        limit = min(MAX_PAGE_SIZE, max(1, int(request.GET.get("limit", DEFAULT_PAGE_SIZE))))
    except (ValueError, TypeError):
        limit = DEFAULT_PAGE_SIZE

    search = request.GET.get("search", "").strip()
    return page, limit, search


def _build_pagination(page: int, limit: int, total: int) -> dict:
    total_pages = max(1, (total + limit - 1) // limit)
    window_start = max(1, page - 2)
    window_end = min(total_pages + 1, page + 3)
    return {
        "total_pages": total_pages,
        "has_prev": page > 1,
        "has_next": page < total_pages,
        "prev_page": page - 1,
        "next_page": page + 1,
        "page_range": range(window_start, window_end),
    }


class ProductListAPIView(View):
    """JSON endpoint: GET /api/products/?page=1&limit=10&search=phone"""

    def get(self, request):
        page, limit, search = _parse_params(request)

        try:
            result = _service.get_products(page=page, limit=limit, search=search)
        except ProductServiceError as exc:
            return JsonResponse({"error": str(exc)}, status=503)

        pagination = _build_pagination(page, limit, result.total)

        return JsonResponse({
            "products":    result.products,
            "total":       result.total,
            "skip":        result.skip,
            "limit":       result.limit,
            "page":        page,
            "total_pages": pagination["total_pages"],
        })


class ProductListView(View):
    """
    Server-rendered product catalog.

    Reads query params, delegates to ProductsService, and passes the result
    to the template. No client-side fetching, filtering, or pagination.
    """

    template_name = "products/product_list.html"

    def get(self, request):
        page, limit, search = _parse_params(request)

        try:
            result = _service.get_products(page=page, limit=limit, search=search)
            error = None
        except ProductServiceError as exc:
            logger.warning("Products fetch failed: %s", exc)
            result = None
            error = str(exc)

        total = result.total if result else 0
        pagination = _build_pagination(page, limit, total)

        context = {
            "products": result.products if result else [],
            "total":    total,
            "page":     page,
            "limit":    limit,
            "search":   search,
            "error":    error,
            **pagination,
        }
        return render(request, self.template_name, context)

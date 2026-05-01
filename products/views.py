import logging

from django.http import JsonResponse
from django.shortcuts import render
from django.views import View

from .services import DummyJSONError, ProductsService

logger = logging.getLogger(__name__)

_service = ProductsService()

DEFAULT_PAGE_SIZE = 10
MAX_PAGE_SIZE = 100


def _parse_params(request) -> tuple[int, int, str]:
    """Return (page, limit, search) extracted and clamped from query params."""
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
            data = _service.get_products(page=page, limit=limit, search=search)
        except DummyJSONError as exc:
            return JsonResponse({"error": str(exc)}, status=503)

        pagination = _build_pagination(page, limit, data["total"])

        return JsonResponse(
            {
                "products": data["products"],
                "total": data["total"],
                "skip": data["skip"],
                "limit": data["limit"],
                "page": page,
                "total_pages": pagination["total_pages"],
            }
        )


class ProductListView(View):
    """Serves the HTML shell. All data is loaded client-side via ProductListAPIView."""

    template_name = "products/list.html"

    def get(self, request):
        return render(request, self.template_name)

import logging

import requests
from django.conf import settings

logger = logging.getLogger(__name__)


class DummyJSONError(Exception):
    """Raised when the DummyJSON upstream API cannot be reached or returns an error."""


class ProductsService:
    """Thin client for the DummyJSON products API."""

    def get_products(self, *, page: int = 1, limit: int = 30, search: str = "") -> dict:
        """
        Fetch a paginated (and optionally filtered) list of products.

        Returns the raw DummyJSON payload:
            { products: [...], total: int, skip: int, limit: int }

        Raises:
            DummyJSONError: on network failure, timeout, or non-2xx HTTP status.
        """
        skip = (page - 1) * limit

        if search:
            url = f"{settings.DUMMYJSON_BASE_URL}/products/search"
            params: dict = {"q": search, "limit": limit, "skip": skip}
        else:
            url = f"{settings.DUMMYJSON_BASE_URL}/products"
            params = {"limit": limit, "skip": skip}

        try:
            response = requests.get(url, params=params, timeout=settings.DUMMYJSON_TIMEOUT)
            response.raise_for_status()
            return response.json()
        except requests.Timeout:
            logger.error("DummyJSON request timed out: %s", url)
            raise DummyJSONError("The products service timed out. Please try again.")
        except requests.HTTPError as exc:
            logger.error("DummyJSON returned HTTP %s for %s", exc.response.status_code, url)
            raise DummyJSONError(
                f"The products service returned an error ({exc.response.status_code})."
            )
        except requests.RequestException as exc:
            logger.error("DummyJSON request failed: %s", exc)
            raise DummyJSONError("Could not reach the products service.")

import logging

import requests
from django.conf import settings

from .base import ProductsPage, ProductsProvider, ProviderError

logger = logging.getLogger(__name__)


class DummyJSONProvider(ProductsProvider):
    """
    Fetches products from https://dummyjson.com.

    This is the only file that knows about DummyJSON's URL structure,
    query parameters, and response shape. Replacing this with another
    provider does not require changes anywhere else in the codebase.
    """

    def fetch(self, *, page: int, limit: int, search: str) -> ProductsPage:
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
            data = response.json()
            return ProductsPage(
                products=data["products"],
                total=data["total"],
                skip=data["skip"],
                limit=data["limit"],
            )
        except requests.Timeout:
            logger.error("DummyJSON timed out: %s", url)
            raise ProviderError("The products service timed out. Please try again.")
        except requests.HTTPError as exc:
            logger.error("DummyJSON HTTP %s: %s", exc.response.status_code, url)
            raise ProviderError(
                f"The products service returned an error ({exc.response.status_code})."
            )
        except requests.RequestException as exc:
            logger.error("DummyJSON request failed: %s", exc)
            raise ProviderError("Could not reach the products service.")

import logging

from .providers.base import ProductsPage, ProductsProvider, ProviderError

logger = logging.getLogger(__name__)


class ProductServiceError(Exception):
    """Public error type exposed to the view layer.

    Views catch this — they never need to know which provider is in use
    or what that provider's internal error type is.
    """


class ProductsService:
    """
    Orchestrates product retrieval via an injected provider.

    Accepts any ProductsProvider implementation — the service itself has
    zero knowledge of DummyJSON or any other specific data source.
    """

    def __init__(self, provider: ProductsProvider) -> None:
        self._provider = provider

    def get_products(self, *, page: int, limit: int, search: str) -> ProductsPage:
        """
        Fetch a page of products.

        Raises:
            ProductServiceError: wraps any ProviderError so the view layer
            stays decoupled from provider-specific exceptions.
        """
        try:
            return self._provider.fetch(page=page, limit=limit, search=search)
        except ProviderError as exc:
            logger.error("Provider error: %s", exc)
            raise ProductServiceError(str(exc)) from exc

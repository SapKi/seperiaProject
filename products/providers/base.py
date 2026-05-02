from abc import ABC, abstractmethod
from dataclasses import dataclass


class ProviderError(Exception):
    """Raised by any ProductsProvider implementation when a fetch fails."""


@dataclass
class ProductsPage:
    """A single page of product results, source-agnostic."""
    products: list
    total: int
    skip: int
    limit: int


class ProductsProvider(ABC):
    """
    Interface every data source must implement.

    To swap DummyJSON for another API or a database:
      1. Create a new file in products/providers/
      2. Subclass ProductsProvider and implement fetch()
      3. Change one line in views.py
    """

    @abstractmethod
    def fetch(self, *, page: int, limit: int, search: str) -> ProductsPage:
        """Return one page of products, optionally filtered by search."""

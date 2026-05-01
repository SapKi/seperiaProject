from django.urls import path

from .views import ProductListAPIView, ProductListView

urlpatterns = [
    path("", ProductListView.as_view(), name="product-list"),
    path("api/products/", ProductListAPIView.as_view(), name="product-list-api"),
]

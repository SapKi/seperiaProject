import json

from django import template
from django.utils.safestring import mark_safe

register = template.Library()


@register.filter
def tojson(value):
    """Serialize a Python value to a JSON string safe for use in HTML attributes."""
    return mark_safe(json.dumps(value))

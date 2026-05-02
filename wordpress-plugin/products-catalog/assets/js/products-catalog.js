/**
 * Products Catalog — WordPress Plugin
 *
 * Gallery toggle only. All product data, search, and pagination are handled
 * server-side by class-shortcode.php. This file does NOT fetch, filter,
 * paginate, or render any product data.
 */
document.addEventListener('click', function (e) {
  var button = e.target.closest('.pc-btn-gallery');
  if (!button) return;

  var galleryRow = document.getElementById(button.dataset.galleryId);
  if (!galleryRow) return;

  var opening = galleryRow.hidden;
  galleryRow.hidden  = !opening;
  button.textContent = opening ? 'Close Gallery' : 'Gallery';
  button.setAttribute('aria-expanded', String(opening));
});

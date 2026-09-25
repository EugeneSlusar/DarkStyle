(function () {
    'use strict';

    function initProductGalleries() {
        document.querySelectorAll('[data-product-gallery]').forEach(function (gallery) {
            if (gallery.dataset.productGalleryInitialized === 'true') {
                return;
            }

            var image = gallery.querySelector('[data-product-gallery-image]');
            var segments = Array.prototype.slice.call(gallery.querySelectorAll('[data-product-gallery-image-src]'));
            if (!image || segments.length < 2) {
                return;
            }

            gallery.dataset.productGalleryInitialized = 'true';
            var currentIndex = 0;

            function showImage(index) {
                var nextIndex = Math.max(0, Math.min(segments.length - 1, index));
                if (currentIndex === nextIndex && image.getAttribute('src') === segments[nextIndex].dataset.productGalleryImageSrc) {
                    return;
                }

                currentIndex = nextIndex;
                image.src = segments[nextIndex].dataset.productGalleryImageSrc;
                segments.forEach(function (segment, segmentIndex) {
                    var active = segmentIndex === currentIndex;
                    segment.classList.toggle('is-active', active);
                    segment.setAttribute('aria-pressed', active ? 'true' : 'false');
                });
            }

            gallery.addEventListener('pointerenter', function () {
                segments.forEach(function (segment) {
                    var preload = new Image();
                    preload.src = segment.dataset.productGalleryImageSrc;
                });
            }, { once: true });

            gallery.addEventListener('pointermove', function (event) {
                if (event.pointerType === 'touch') {
                    return;
                }

                var rect = gallery.getBoundingClientRect();
                var ratio = Math.max(0, Math.min(0.99999, (event.clientX - rect.left) / rect.width));
                showImage(Math.floor(ratio * segments.length));
            });

            gallery.addEventListener('pointerleave', function () {
                showImage(0);
            });

            segments.forEach(function (segment, index) {
                segment.addEventListener('click', function () {
                    showImage(index);
                });

                segment.addEventListener('keydown', function (event) {
                    if (event.key === 'Enter' || event.key === ' ') {
                        event.preventDefault();
                        showImage(index);
                    }
                });
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initProductGalleries);
    } else {
        initProductGalleries();
    }
}());

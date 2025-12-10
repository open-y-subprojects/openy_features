(function (Drupal, once, debounce) {
  'use strict';

  Drupal.behaviors.mediaLibraryAutoApply = {
    attach: function (context) {
      once('media-library-auto-apply', '.views-exposed-form', context)
        .forEach(function (form) {
          // Only apply to media_library views.
          if (!form.querySelector('[name="media_tags"]')) {
            return;
          }

          var submitBtn = form.querySelector('[type="submit"]');
          if (!submitBtn) {
            return;
          }

          // Find the scrollable container (media library modal).
          var scrollContainer = form.closest('.ui-dialog-content') ||
                               form.closest('.media-library-wrapper') ||
                               document.querySelector('.ui-dialog-content');

          // Submit with scroll preservation.
          function submitWithScroll() {
            var scrollTop = scrollContainer ? scrollContainer.scrollTop : window.scrollY;

            // Restore scroll after AJAX completes.
            if (typeof Drupal.ajax !== 'undefined') {
              var originalSuccess = Drupal.Ajax.prototype.success;
              Drupal.Ajax.prototype.success = function (response, status) {
                originalSuccess.call(this, response, status);
                setTimeout(function () {
                  if (scrollContainer) {
                    scrollContainer.scrollTop = scrollTop;
                  } else {
                    window.scrollTo(0, scrollTop);
                  }
                }, 50);
                Drupal.Ajax.prototype.success = originalSuccess;
              };
            }

            submitBtn.click();
          }

          // Debounced submit function to avoid rapid submissions.
          var debouncedSubmit = debounce(submitWithScroll, 300);

          // Auto-submit on name field change (with debounce for typing).
          var nameField = form.querySelector('[name="name"]');
          if (nameField) {
            nameField.addEventListener('input', debounce(submitWithScroll, 500));
          }

          // Auto-submit on sort field change.
          var sortField = form.querySelector('[name="sort_by"]');
          if (sortField) {
            sortField.addEventListener('change', debouncedSubmit);
          }

          // Auto-submit on media_tags hidden input change.
          // Watch for changes via MutationObserver since it's updated by JS.
          var tagsHidden = form.querySelector('input[type="hidden"][name="media_tags"]');
          if (tagsHidden) {
            var observer = new MutationObserver(function (mutations) {
              mutations.forEach(function (mutation) {
                if (mutation.type === 'attributes' && mutation.attributeName === 'value') {
                  debouncedSubmit();
                }
              });
            });
            observer.observe(tagsHidden, { attributes: true });

            // Also listen for programmatic value changes via setter.
            var originalValue = tagsHidden.value;
            Object.defineProperty(tagsHidden, 'value', {
              get: function () {
                return this.getAttribute('value') || '';
              },
              set: function (val) {
                var changed = val !== originalValue;
                this.setAttribute('value', val);
                originalValue = val;
                if (changed) {
                  debouncedSubmit();
                }
              }
            });
          }
        });
    }
  };

})(Drupal, once, Drupal.debounce);

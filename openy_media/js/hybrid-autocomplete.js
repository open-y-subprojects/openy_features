(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.hybridAutocomplete = {
    attach: function (context) {
      once('hybrid-autocomplete', 'input.hybrid-autocomplete', context)
        .forEach(function (element) {
          var dropdown = null;
          var wrapper = null;
          var hiddenInput = null;
          var allItems = [];
          var selectedValues = [];

          function createWrapper() {
            if (wrapper) return wrapper;
            wrapper = document.createElement('div');
            wrapper.className = 'hybrid-autocomplete-wrapper';
            wrapper.style.cssText = 'display:flex;flex-wrap:wrap;align-items:center;gap:4px;padding:4px;border:1px solid #ccc;border-radius:3px;background:#fff;min-height:36px;cursor:text;';
            element.parentNode.insertBefore(wrapper, element);
            wrapper.appendChild(element);
            element.style.cssText = 'flex:1;min-width:60px;border:none;outline:none;padding:4px;';
            wrapper.addEventListener('click', function () { element.focus(); });

            // Create hidden input to store actual form value
            hiddenInput = document.createElement('input');
            hiddenInput.type = 'hidden';
            hiddenInput.name = element.name;
            element.name = '';
            wrapper.parentNode.insertBefore(hiddenInput, wrapper);

            return wrapper;
          }

          function createBadge(value, label) {
            var badge = document.createElement('span');
            badge.className = 'hybrid-autocomplete-badge';
            badge.style.cssText = 'display:inline-flex;align-items:center;background:#e0e0e0;border-radius:3px;padding:2px 6px;font-size:0.85em;white-space:nowrap;';
            badge.dataset.value = value;

            var text = document.createElement('span');
            text.textContent = label;
            badge.appendChild(text);

            var closeBtn = document.createElement('span');
            closeBtn.innerHTML = '&times;';
            closeBtn.style.cssText = 'margin-left:6px;cursor:pointer;font-weight:bold;color:#666;';
            closeBtn.addEventListener('click', function (e) {
              e.stopPropagation();
              removeBadge(value);
            });
            badge.appendChild(closeBtn);

            return badge;
          }

          function removeBadge(value) {
            selectedValues = selectedValues.filter(function (v) { return v !== value; });
            updateHiddenValue();
            renderBadges();
          }

          function renderBadges() {
            var w = createWrapper();
            var badges = w.querySelectorAll('.hybrid-autocomplete-badge');
            badges.forEach(function (b) { b.remove(); });
            selectedValues.forEach(function (value) {
              var label = extractLabel(value);
              w.insertBefore(createBadge(value, label), element);
            });
          }

          function extractLabel(value) {
            var match = value.match(/^(.+)\s*\(\d+\)$/);
            return match ? match[1].trim() : value;
          }

          function updateHiddenValue() {
            if (hiddenInput) {
              hiddenInput.value = selectedValues.join(', ');
            }
          }

          function parseInitialValue() {
            var current = element.value.trim();
            createWrapper();
            if (!current) return;
            selectedValues = current.split(',').map(function (v) { return v.trim(); }).filter(Boolean);
            element.value = '';
            renderBadges();
            updateHiddenValue();
          }

          function createDropdown() {
            if (dropdown) return dropdown;
            var w = createWrapper();
            dropdown = document.createElement('ul');
            dropdown.className = 'hybrid-autocomplete-dropdown';
            dropdown.style.cssText = 'position:absolute;z-index:9999;background:#fff;border:1px solid #ccc;max-height:200px;overflow-y:auto;list-style:none;margin:0;padding:0;width:100%;left:0;top:100%;box-sizing:border-box;';
            w.style.position = 'relative';
            w.appendChild(dropdown);
            return dropdown;
          }

          function showDropdown(items) {
            var list = createDropdown();
            list.innerHTML = '';
            items.forEach(function (item) {
              var isSelected = selectedValues.indexOf(item.value) !== -1;
              if (isSelected) return;

              var li = document.createElement('li');
              var depth = item.depth || 0;
              var indent = depth * 20;
              li.textContent = item.label;
              li.dataset.value = item.value;
              li.style.cssText = 'padding:8px 12px;padding-left:' + (12 + indent) + 'px;cursor:pointer;';
              if (depth > 0) {
                li.style.fontSize = '0.95em';
              }
              li.addEventListener('mouseenter', function () { li.style.background = '#f0f0f0'; });
              li.addEventListener('mouseleave', function () { li.style.background = '#fff'; });
              li.addEventListener('mousedown', function (e) {
                e.preventDefault();
                selectItem(item);
              });
              list.appendChild(li);
            });
            list.style.display = list.children.length ? 'block' : 'none';
          }

          function hideDropdown() {
            if (dropdown) dropdown.style.display = 'none';
          }

          function selectItem(item) {
            if (selectedValues.indexOf(item.value) === -1) {
              selectedValues.push(item.value);
              updateHiddenValue();
              renderBadges();
            }
            element.value = '';
            hideDropdown();
            element.focus();
          }

          function filterItems(query) {
            var q = query.toLowerCase();
            return allItems.filter(function (item) {
              return item.label.toLowerCase().indexOf(q) !== -1;
            });
          }

          function fetchItems(query) {
            var path = '/openy-media/media-tags/autocomplete';
            fetch(path + '?q=' + encodeURIComponent(query || ''))
              .then(function (r) { return r.json(); })
              .then(function (data) {
                allItems = data;
                showDropdown(data);
              });
          }

          parseInitialValue();

          function openDropdown() {
            if (allItems.length) {
              showDropdown(filterItems(element.value.trim()));
            } else {
              fetchItems();
            }
          }

          element.addEventListener('focus', openDropdown);
          element.addEventListener('click', openDropdown);

          element.addEventListener('input', function () {
            if (allItems.length) {
              showDropdown(filterItems(element.value.trim()));
            }
          });

          element.addEventListener('blur', function () {
            setTimeout(hideDropdown, 200);
          });

          element.addEventListener('keydown', function (e) {
            if (e.key === 'Backspace' && element.value === '' && selectedValues.length) {
              selectedValues.pop();
              updateHiddenValue();
              renderBadges();
            }
          });
        });
    }
  };

})(Drupal, once);

(function () {
  function escAttr(s) {
    return String(s)
      .replace(/&/g, '&amp;')
      .replace(/"/g, '&quot;')
      .replace(/</g, '&lt;')
      .replace(/'/g, '&#039;');
  }
  function escHtml(s) {
    return String(s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/"/g, '&quot;');
  }

  function normalizeTagKey(s) {
    return String(s)
      .trim()
      .replace(/\s+/g, ' ')
      .toLowerCase();
  }

  function ensureLiveSearchStyles() {
    if (document.getElementById('live-search-kbd-styles')) return;
    var s = document.createElement('style');
    s.id = 'live-search-kbd-styles';
    s.textContent =
      '.input-group .live-search-combo,.main-search .live-search-combo{flex:1 1 auto;min-width:0;}' +
      '.live-search-combo{display:flex;flex-wrap:wrap;align-items:center;gap:8px;flex:1;min-width:0;max-width:100%;' +
      'border:1px solid rgba(0,0,0,0.1);border-radius:12px;background:rgba(255,255,255,0.95);backdrop-filter:blur(10px);' +
      'padding:6px 12px;min-height:48px;box-sizing:border-box;transition:all 0.2s ease;box-shadow:0 2px 8px rgba(0,0,0,0.05);}' +
      '.live-search-combo:focus-within{border-color:#4a90d9;box-shadow:0 0 0 4px rgba(74,144,217,0.15), 0 4px 12px rgba(0,0,0,0.08);background:#fff;}' +
      '.live-search-combo>.twitter-typeahead{flex:1 1 120px;min-width:80px;position:relative!important;display:block!important;}' +
      '.live-search-combo .twitter-typeahead input.ac_input,.live-search-combo .twitter-typeahead input.tt-input,.live-search-combo .twitter-typeahead input.tt-hint{' +
      'border:none!important;box-shadow:none!important;outline:none!important;background:transparent!important;font-size:1rem;color:#333;}' +
      '.live-search-combo .twitter-typeahead input.tt-input{position:relative!important;}' +
      '.live-search-tags-wrap{display:flex;flex-wrap:wrap;align-items:center;gap:6px;margin:0;min-height:0;max-width:100%;flex:0 1 auto;}' +
      '.live-search-tag{display:inline-flex;align-items:center;gap:6px;max-width:100%;background:linear-gradient(135deg, #e8f4fc 0%, #d1e9f9 100%);' +
      'border:1px solid #b3d7f2;color:#0a3d62;border-radius:8px;padding:4px 8px 4px 10px;font-size:0.85rem;font-weight:500;box-shadow:0 1px 2px rgba(0,0,0,0.05);transition:transform 0.1s ease;}' +
      '.live-search-tag:hover{transform:translateY(-1px);}' +
      '.live-search-tag span{overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:180px;}' +
      '.live-search-tag button{border:none;background:transparent;cursor:pointer;padding:0 4px;font-size:1.2rem;line-height:1;color:#5c7c94;border-radius:4px;flex-shrink:0;transition:color 0.2s;}' +
      '.live-search-tag button:hover{color:#d00;}' +
      '.live-search-dropdown{display:block;opacity:0;transform:translateY(10px);pointer-events:none;transition:all 0.3s cubic-bezier(0.4, 0, 0.2, 1);}' +
      '.live-search-dropdown.open{opacity:1;transform:translateY(0);pointer-events:auto;}' +
      '.live-search-dropdown .live-search-suggest.live-search-active,' +
      '.live-search-dropdown a.live-search-item.live-search-active{' +
      'background:#f0f7ff!important;box-shadow:inset 4px 0 0 #4a90d9;}' +
      '.live-search-dropdown a.live-search-item.live-search-active{color:#000!important;}' +
      '.live-search-suggest,.live-search-item{padding:12px 16px!important;}' +
      '.live-search-serie-thumb{width:60px;height:60px;object-fit:cover;border-radius:8px;box-shadow:0 2px 4px rgba(0,0,0,0.1);}' +
      '.live-search-serie-thumb-ph{width:60px;height:60px;background:#f0f0f0;border-radius:8px;display:block;}' +
      '.live-search-hint{padding:12px 16px;background:rgba(232,244,252,0.5);border-bottom:1px solid #e1e9f0;}' +
      '.live-search-hint .live-search-hint-title{font-weight:700;font-size:0.85rem;color:#0a3d62;text-transform:uppercase;letter-spacing:0.5px;}' +
      '.live-search-hint .live-search-hint-sub{font-size:0.75rem;color:#5a6b7d;margin-top:2px;}' +
      '.live-search-loading-spinner{width:18px;height:18px;border:2px solid rgba(74,144,217,0.2);border-top-color:#4a90d9;border-radius:50%;animation:ls-spin 0.6s linear infinite;margin-left:auto;}' +
      '@keyframes ls-spin{to{transform:rotate(360deg);}}' +
      '.live-search-combo .tt-menu,.live-search-combo .tt-dropdown-menu{display:none!important;' +
      'visibility:hidden!important;pointer-events:none!important;opacity:0!important;height:0!important;overflow:hidden!important;}';
    document.head.appendChild(s);
  }

  function initLiveSearch() {
    ensureLiveSearchStyles();
    var raw = document.querySelectorAll('input[name="suche"], input[name="qs"]');
    var inputs = [];
    for (var ri = 0; ri < raw.length; ri++) {
      var inp = raw[ri];
      if (inp.classList.contains('tt-hint')) continue;
      if (inp.getAttribute('data-livesearch-bound') === '1') continue;
      inputs.push(inp);
    }
    if (!inputs.length) return;

    function attachLiveSearch(input) {
      if (input.classList.contains('tt-hint')) return;
      if (input.getAttribute('data-livesearch-bound') === '1') return;

      function buildSearchUrl(draft) {
        var baseEl = document.querySelector('base[href]');
        var base = baseEl ? baseEl.href : window.location.origin + '/';
        var u = new URL('livesearch', base);
        u.searchParams.set('q', draft);
        if (selectedValues.length) {
          u.searchParams.set('merkmale', JSON.stringify(selectedValues));
        }
        return u.pathname + u.search;
      }

      function buildCacheKey(draft) {
        return JSON.stringify({ m: selectedValues.slice(), q: draft });
      }

      var tagsWrap = document.createElement('div');
      tagsWrap.className = 'live-search-tags-wrap';
      tagsWrap.setAttribute('aria-label', 'Aktive Filter');
      var combo = document.createElement('div');
      combo.className = 'live-search-combo';
      combo.setAttribute('data-livesearch', '1');
      combo.style.position = 'relative';

      var typeahead = input.closest('.twitter-typeahead');
      if (typeahead && typeahead.parentNode) {
        typeahead.parentNode.insertBefore(combo, typeahead);
        combo.appendChild(tagsWrap);
        combo.appendChild(typeahead);
      } else if (input.parentNode) {
        input.parentNode.insertBefore(combo, input);
        combo.appendChild(tagsWrap);
        combo.appendChild(input);
      }

      input.setAttribute('data-livesearch-bound', '1');

      var dropdown = document.createElement('div');
      dropdown.setAttribute('role', 'region');
      dropdown.setAttribute('aria-label', 'Suchvorschläge');
      dropdown.setAttribute('aria-live', 'polite');
      dropdown.className = 'live-search-dropdown';
      dropdown.style.cssText =
        'position:absolute;z-index:9999;left:0;top:100%;margin-top:2px;background:#fff;border:1px solid #ddd;' +
        'display:none;max-height:350px;overflow-y:auto;box-shadow:0 4px 12px rgba(0,0,0,.15);' +
        'border-radius:0 0 4px 4px;width:100%;min-width:300px;box-sizing:border-box;';

      combo.appendChild(dropdown);

      var spinner = document.createElement('div');
      spinner.className = 'live-search-loading-spinner';
      spinner.style.display = 'none';
      combo.appendChild(spinner);

      function updatePos() {
        var w = combo.offsetWidth;
        if (w < 300) w = 300;
        dropdown.style.width = w + 'px';
      }
      updatePos();
      window.addEventListener('resize', updatePos);
      window.addEventListener('scroll', updatePos, true);

      var timer = null;
      var currentController = null;
      var cache = {};
      var selectedValues = [];
      var activeIndex = -1;

      function getRows() {
        return dropdown.querySelectorAll('.live-search-suggest, a.live-search-item');
      }

      function highlightActive() {
        var rows = getRows();
        for (var i = 0; i < rows.length; i++) {
          rows[i].classList.toggle('live-search-active', i === activeIndex);
          if (i === activeIndex) {
            try {
              rows[i].scrollIntoView({ block: 'nearest', behavior: 'smooth' });
            } catch (err) {
              try {
                rows[i].scrollIntoView(false);
              } catch (e2) {}
            }
          }
        }
      }

      function setActiveIndex(i) {
        var rows = getRows();
        var n = rows.length;
        if (n === 0) {
          activeIndex = -1;
          return;
        }
        if (i < 0) {
          activeIndex = -1;
          highlightActive();
          return;
        }
        if (i >= n) i = n - 1;
        activeIndex = i;
        highlightActive();
      }

      function moveActive(delta) {
        var rows = getRows();
        if (rows.length === 0) return;
        if (activeIndex < 0) {
          if (delta > 0) setActiveIndex(0);
          return;
        }
        var next = activeIndex + delta;
        if (next < 0) {
          activeIndex = -1;
          highlightActive();
          return;
        }
        if (next >= rows.length) next = rows.length - 1;
        setActiveIndex(next);
      }

      function closeDropdown() {
        dropdown.classList.remove('open');
        setTimeout(function() {
          if (!dropdown.classList.contains('open')) {
            dropdown.style.display = 'none';
          }
        }, 300);
        activeIndex = -1;
        var rows = getRows();
        for (var i = 0; i < rows.length; i++) {
          rows[i].classList.remove('live-search-active');
        }
      }

      function hasInteractiveRows() {
        return getRows().length > 0;
      }

      function clearHint() {
        var hintInput = combo.querySelector('.tt-hint');
        if (hintInput) hintInput.value = '';
      }

      function renderTags() {
        tagsWrap.innerHTML = '';
        selectedValues.forEach(function (label) {
          var tag = document.createElement('span');
          tag.className = 'live-search-tag';
          tag.innerHTML =
            '<span title="' +
            escAttr(label) +
            '">' +
            escHtml(label) +
            '</span>' +
            '<button type="button" aria-label="Entfernen">\u00D7</button>';
          var btn = tag.querySelector('button');
          (function (storedLabel) {
            btn.addEventListener('click', function (ev) {
              ev.preventDefault();
              ev.stopPropagation();
              var k = normalizeTagKey(storedLabel);
              for (var r = selectedValues.length - 1; r >= 0; r--) {
                if (normalizeTagKey(selectedValues[r]) === k) {
                  selectedValues.splice(r, 1);
                  break;
                }
              }
              renderTags();
              cache = {};
              input.dispatchEvent(new Event('input', { bubbles: true }));
            });
          })(label);
          tagsWrap.appendChild(tag);
        });
      }

      function addTagFromMerkmal(value) {
        var v = String(value).trim().replace(/\s+/g, ' ');
        if (!v) return;
        var key = normalizeTagKey(v);
        for (var d = 0; d < selectedValues.length; d++) {
          if (normalizeTagKey(selectedValues[d]) === key) {
            input.value = '';
            clearHint();
            closeDropdown();
            dropdown.innerHTML = '';
            return;
          }
        }
        selectedValues.push(v);
        renderTags();
        input.value = '';
        clearHint();
        input.focus();
        cache = {};
        closeDropdown();
        dropdown.innerHTML = '';
        setTimeout(function () {
          input.dispatchEvent(new Event('input', { bubbles: true }));
        }, 50);
      }

      function selectSuggestionByValue(selectedName) {
        addTagFromMerkmal(selectedName);
      }

      input.addEventListener('keydown', function (e) {
        if (e.key === 'Backspace' && input.value === '' && selectedValues.length > 0) {
          e.preventDefault();
          selectedValues.pop();
          renderTags();
          cache = {};
          input.dispatchEvent(new Event('input', { bubbles: true }));
          return;
        }

        if (e.key === 'Escape') {
          if (dropdown.style.display !== 'none') {
            e.preventDefault();
            closeDropdown();
            dropdown.innerHTML = '';
            return;
          }
        }

        if (e.key === 'ArrowDown') {
          if (dropdown.style.display === 'none' || !hasInteractiveRows()) return;
          e.preventDefault();
          moveActive(1);
          return;
        }

        if (e.key === 'ArrowUp') {
          if (dropdown.style.display === 'none' || !hasInteractiveRows()) return;
          e.preventDefault();
          moveActive(-1);
          return;
        }

        if (e.key === 'Home' && dropdown.style.display !== 'none' && hasInteractiveRows()) {
          e.preventDefault();
          setActiveIndex(0);
          return;
        }

        if (e.key === 'End' && dropdown.style.display !== 'none' && hasInteractiveRows()) {
          e.preventDefault();
          setActiveIndex(getRows().length - 1);
          return;
        }

        if (e.key === 'Enter') {
          var rowsEnter = getRows();
          if (activeIndex >= 0 && activeIndex < rowsEnter.length) {
            var elEnter = rowsEnter[activeIndex];
            if (elEnter.classList.contains('live-search-suggest')) {
              e.preventDefault();
              var merkmal = elEnter.getAttribute('data-value');
              if (merkmal) selectSuggestionByValue(merkmal);
              return;
            }
            if (elEnter.tagName === 'A' && elEnter.classList.contains('live-search-item') && elEnter.href) {
              e.preventDefault();
              window.location.href = elEnter.href;
              return;
            }
          }
        }
      });

      function renderItems(items, userQueryFull) {
        activeIndex = -1;

        if (!items || !items.length) {
          dropdown.innerHTML =
            '<div style="padding:10px 14px;color:#888;font-size:.875rem">Keine Ergebnisse.</div>';
          dropdown.style.display = 'block';
          setTimeout(function() { dropdown.classList.add('open'); }, 10);
          updatePos();
          return;
        }

        var suggestions = items.filter(function (i) {
          return i.type === 'suggest';
        });
        var series = items.filter(function (i) {
          return i.type === 'serie';
        });
        var hasFilteredSuggest = suggestions.some(function (s) {
          return s.scope === 'filtered';
        });

        var html = '';
        var merkmalHeaderDone = false;
        var serieHeaderDone = false;
        var merkmalSectionLabel = hasFilteredSuggest ? 'Passende Merkmale' : 'Merkmale';

        for (var ii = 0; ii < items.length; ii++) {
          var i = items[ii];
          if (i.type === 'hint') {
            html +=
              '<div class="live-search-hint" role="note">' +
              '<div class="live-search-hint-title">' +
              escHtml(i.title || '') +
              '</div>' +
              (i.subtitle
                ? '<div class="live-search-hint-sub">' + escHtml(i.subtitle) + '</div>'
                : '') +
              '</div>';
            continue;
          }
          if (i.type === 'suggest') {
            if (!merkmalHeaderDone) {
              merkmalHeaderDone = true;
              html +=
                '<div style="padding:6px 14px;font-size:.7rem;color:#999;' +
                'border-bottom:1px solid #eee;text-transform:uppercase;letter-spacing:.5px">' +
                merkmalSectionLabel +
                ' <span style="color:#bbb;font-size:.65rem">(Pfeiltasten, Enter)</span></div>';
            }
            html +=
              '<div class="live-search-suggest" role="option" data-value="' +
              escAttr(i.name) +
              '" ' +
              'style="display:flex;align-items:center;padding:8px 14px;color:#333;' +
              'cursor:pointer;border-bottom:1px solid #f5f5f5;font-size:.875rem;' +
              'transition:background .15s" ' +
              'onmouseover="this.style.background=\'#f0f7ff\'" ' +
              'onmouseout="if(!this.classList.contains(\'live-search-active\'))this.style.background=\'#fff\'">' +
              '<span style="margin-right:8px;color:#4a90d9">&#9656;</span>' +
              '<span style="flex:1">' +
              escHtml(i.name) +
              '</span>' +
              '<span style="font-size:.7rem;color:#aaa;margin-left:8px">+ Filter</span>' +
              '</div>';
            continue;
          }
          if (i.type === 'serie') {
            if (!serieHeaderDone) {
              serieHeaderDone = true;
              html +=
                '<div style="padding:6px 14px;font-size:.7rem;color:#999;' +
                'border-bottom:1px solid #eee;text-transform:uppercase;letter-spacing:.5px">' +
                'Serien (' +
                series.length +
                ') <span style="color:#bbb;font-size:.65rem">(Pfeiltasten, Enter)</span></div>';
            }
            var filterInfo = i.filters
              ? '<span style="display:block;font-size:.75rem;color:#888;margin-top:2px">' +
                'Filter: ' +
                escHtml(i.filters) +
                '</span>'
              : '';
            var thumb =
              i.thumbUrl && String(i.thumbUrl).trim() !== ''
                ? '<img class="live-search-serie-thumb" src="' +
                  escAttr(i.thumbUrl) +
                  '" alt="" width="40" height="40" loading="lazy" decoding="async">'
                : '<span class="live-search-serie-thumb-ph" aria-hidden="true"></span>';
            html +=
              '<a href="' +
              escAttr(i.url) +
              '" class="live-search-item" role="option" ' +
              'style="display:block;padding:10px 14px;color:#333;' +
              'text-decoration:none;border-bottom:1px solid #f5f5f5;font-size:.875rem;' +
              'transition:background .15s" ' +
              'onmouseover="this.style.background=\'#f8f8f8\'" ' +
              'onmouseout="if(!this.classList.contains(\'live-search-active\'))this.style.background=\'#fff\'">' +
              '<div style="display:flex;align-items:center;gap:10px">' +
              thumb +
              '<span style="margin-right:0;color:#666">&#9656;</span>' +
              '<span style="flex:1;font-weight:500">' +
              escHtml(i.name) +
              '</span>' +
              '<span style="font-size:.75rem;color:#aaa">&#8594;</span>' +
              '</div>' +
              filterInfo +
              '</a>';
          }
        }

        dropdown.innerHTML = html;
        dropdown.style.display = 'block';
        setTimeout(function() { dropdown.classList.add('open'); }, 10);
        updatePos();

        var suggestEls = dropdown.querySelectorAll('.live-search-suggest');
        suggestEls.forEach(function (el) {
          el.addEventListener('mousedown', function (e) {
            e.preventDefault();
            e.stopPropagation();
            var selectedName = this.getAttribute('data-value');
            selectSuggestionByValue(selectedName);
          });
          el.addEventListener('mouseenter', function () {
            var rows = getRows();
            activeIndex = Array.prototype.indexOf.call(rows, el);
            highlightActive();
          });
        });

        var serieEls = dropdown.querySelectorAll('a.live-search-item');
        serieEls.forEach(function (el) {
          el.addEventListener('mouseenter', function () {
            var rows = getRows();
            activeIndex = Array.prototype.indexOf.call(rows, el);
            highlightActive();
          });
        });
      }

      input.addEventListener('input', function () {
        activeIndex = -1;
        var draft = input.value.trim();

        clearTimeout(timer);
        if (currentController) {
          currentController.abort();
          currentController = null;
        }

        if (draft.length < 2 && selectedValues.length === 0) {
          dropdown.innerHTML = '';
          closeDropdown();
          return;
        }

        var cacheKey = buildCacheKey(draft);
        if (cache[cacheKey]) {
          renderItems(cache[cacheKey], cacheKey);
          return;
        }

        dropdown.style.display = 'block';
        setTimeout(function() { dropdown.classList.add('open'); }, 10);
        dropdown.innerHTML =
          '<div style="padding:10px 14px;color:#888;font-size:.875rem">Suche läuft …</div>';
        spinner.style.display = 'block';
        updatePos();

        timer = setTimeout(function () {
          var controller = new AbortController();
          currentController = controller;

          fetch(buildSearchUrl(draft), {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            signal: controller.signal
          })
            .then(function (res) {
              return res.text();
            })
            .then(function (text) {
              currentController = null;
              spinner.style.display = 'none';
              var items = [];
              try {
                items = JSON.parse(text);
              } catch (e) {
                items = [];
              }
              if (Array.isArray(items)) {
                cache[cacheKey] = items;
                renderItems(items, cacheKey);
              } else {
                renderItems([], cacheKey);
              }
            })
            .catch(function (err) {
              if (err.name === 'AbortError') return;
              currentController = null;
              spinner.style.display = 'none';
              dropdown.innerHTML =
                '<div style="padding:10px 14px;color:#c00;font-size:.875rem">Es ist ein Fehler aufgetreten.</div>';
            });
        }, 300);
      });

      document.addEventListener('click', function (e) {
        if (combo.contains(e.target)) return;
        closeDropdown();
        dropdown.innerHTML = '';
      });
    }

    inputs.forEach(function (input) {
      attachLiveSearch(input);
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initLiveSearch);
  } else {
    initLiveSearch();
  }
})();

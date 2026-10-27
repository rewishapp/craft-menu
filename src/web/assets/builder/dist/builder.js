(function () {
  function csrfBody() {
    var body = new FormData();
    body.append(window.Craft.csrfTokenName, window.Craft.csrfTokenValue);
    return body;
  }

  function showError(msg) {
    if (window.Craft && Craft.cp) {
      Craft.cp.displayError(msg);
    } else {
      alert(msg);
    }
  }

  function showNotice(msg) {
    if (window.Craft && Craft.cp) {
      Craft.cp.displayNotice(msg);
    }
  }

  function updateSessionMoves(count) {
    document.querySelectorAll('[data-session-moves]').forEach(function (el) {
      el.textContent = String(count);
    });
    document.querySelectorAll('[data-discard-session]').forEach(function (btn) {
      btn.disabled = !count;
    });
  }

  function replaceHostHtml(html) {
    var host = document.querySelector('[data-menu-builder-host]');
    if (!host || !html) return;
    host.innerHTML = html;
    host.querySelectorAll('[data-menu-builder]').forEach(initBuilder);
  }

  function isDescendantRow(sourceRow, candidateRow) {
    if (!sourceRow || !candidateRow || sourceRow === candidateRow) return false;
    var sourceLevel = parseInt(sourceRow.getAttribute('data-level') || '1', 10);
    var node = sourceRow.nextElementSibling;
    while (node) {
      if (node === candidateRow) return true;
      var level = parseInt(node.getAttribute('data-level') || '1', 10);
      if (level <= sourceLevel) break;
      node = node.nextElementSibling;
    }
    return false;
  }

  function initBuilder(root) {
    if (!root || root.dataset.menuBuilderReady) return;
    root.dataset.menuBuilderReady = '1';

    var moveUrl = root.getAttribute('data-move-url');
    var menuId = root.getAttribute('data-menu-id');
    var siteId = root.getAttribute('data-site-id') || '';
    var dragId = null;
    var dragRow = null;
    var busy = false;
    var activeTarget = null;
    var activePos = null;

    var rows = function () {
      return Array.prototype.slice.call(root.querySelectorAll('[data-node-id]'));
    };

    function clearDropClasses() {
      rows().forEach(function (r) {
        r.classList.remove('is-drop-before', 'is-drop-after', 'is-drop-into');
      });
      activeTarget = null;
      activePos = null;
    }

    function positionForEvent(row, e) {
      var rect = row.getBoundingClientRect();
      var y = e.clientY - rect.top;
      var ratio = y / Math.max(rect.height, 1);
      if (ratio < 0.28) return 'before';
      if (ratio > 0.72) return 'after';
      return 'into';
    }

    function setDropTarget(row, pos) {
      if (activeTarget === row && activePos === pos) return;
      clearDropClasses();
      if (!row || !pos) return;
      row.classList.add('is-drop-' + pos);
      activeTarget = row;
      activePos = pos;
    }

    function rowFromPoint(x, y) {
      var el = document.elementFromPoint(x, y);
      if (!el) return null;
      var row = el.closest('[data-node-id]');
      if (!row || !root.contains(row)) return null;
      return row;
    }

    rows().forEach(function (row) {
      var handle = row.querySelector('[data-drag-handle]');
      if (!handle) return;

      handle.setAttribute('draggable', 'true');

      handle.addEventListener('dragstart', function (e) {
        if (busy) {
          e.preventDefault();
          return;
        }
        dragId = row.getAttribute('data-node-id');
        dragRow = row;
        row.classList.add('is-dragging');
        root.classList.add('is-dragging-active');
        e.dataTransfer.effectAllowed = 'move';
        e.dataTransfer.setData('text/plain', dragId);
        // Ghost image looks better slightly delayed.
        try {
          e.dataTransfer.setDragImage(row, 24, 20);
        } catch (err) {}
      });

      handle.addEventListener('dragend', function () {
        if (dragRow) dragRow.classList.remove('is-dragging');
        root.classList.remove('is-dragging-active');
        clearDropClasses();
        dragId = null;
        dragRow = null;
      });
    });

    // Use the list as a single dragover surface so dragleave flicker doesn't kill indicators.
    root.addEventListener('dragover', function (e) {
      if (!dragId || busy) return;
      e.preventDefault();
      e.dataTransfer.dropEffect = 'move';

      var row = rowFromPoint(e.clientX, e.clientY);
      if (!row || row.getAttribute('data-node-id') === dragId || isDescendantRow(dragRow, row)) {
        setDropTarget(null, null);
        return;
      }
      setDropTarget(row, positionForEvent(row, e));
    });

    root.addEventListener('dragleave', function (e) {
      if (!root.contains(e.relatedTarget)) {
        clearDropClasses();
      }
    });

    root.addEventListener('drop', function (e) {
      e.preventDefault();
      var sourceId = dragId || e.dataTransfer.getData('text/plain');
      var targetRow = activeTarget || rowFromPoint(e.clientX, e.clientY);
      var pos = activePos || (targetRow ? positionForEvent(targetRow, e) : null);
      clearDropClasses();
      if (dragRow) dragRow.classList.remove('is-dragging');
      root.classList.remove('is-dragging-active');

      if (!sourceId || !targetRow || !pos || busy) return;
      var targetId = targetRow.getAttribute('data-node-id');
      if (!targetId || sourceId === targetId || isDescendantRow(dragRow, targetRow)) return;
      moveNode(sourceId, pos, targetId);
      dragId = null;
      dragRow = null;
    });

    function moveNode(nodeId, position, targetId) {
      busy = true;
      root.classList.add('is-fetching');
      var body = csrfBody();
      body.append('menuId', menuId);
      body.append('siteId', siteId);
      body.append('nodeId', nodeId);
      body.append('targetId', targetId);
      body.append('position', position);

      fetch(moveUrl, {
        method: 'POST',
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        body: body,
        credentials: 'same-origin',
      })
        .then(function (res) {
          return res.json().then(function (data) {
            return { ok: res.ok, data: data };
          });
        })
        .then(function (result) {
          busy = false;
          root.classList.remove('is-fetching');
          if (result.ok && result.data && result.data.success !== false) {
            if (result.data.html) {
              replaceHostHtml(result.data.html);
            }
            if (typeof result.data.sessionMoves !== 'undefined') {
              updateSessionMoves(result.data.sessionMoves);
            }
            if (result.data.message) {
              showNotice(result.data.message);
            }
            return;
          }
          showError(
            (result.data && (result.data.message || result.data.error)) ||
              'Could not move node.'
          );
        })
        .catch(function () {
          busy = false;
          root.classList.remove('is-fetching');
          showError('Could not move node.');
        });
    }
  }

  function initDiscard() {
    document.querySelectorAll('[data-discard-session]').forEach(function (btn) {
      if (btn.dataset.ready) return;
      btn.dataset.ready = '1';
      btn.addEventListener('click', function () {
        if (btn.disabled) return;
        var msg = btn.getAttribute('data-confirm') || 'Discard?';
        if (!window.confirm(msg)) return;
        var body = csrfBody();
        body.append('menuId', btn.getAttribute('data-menu-id'));
        body.append('siteId', btn.getAttribute('data-site-id') || '');
        btn.disabled = true;
        fetch(btn.getAttribute('data-discard-url'), {
          method: 'POST',
          headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
          body: body,
          credentials: 'same-origin',
        })
          .then(function (res) {
            return res.json().then(function (data) {
              return { ok: res.ok, data: data };
            });
          })
          .then(function (result) {
            if (result.ok && result.data && result.data.success !== false) {
              if (result.data.html) {
                replaceHostHtml(result.data.html);
              }
              updateSessionMoves(result.data.sessionMoves || 0);
              if (result.data.message) {
                showNotice(result.data.message);
              }
              return;
            }
            btn.disabled = false;
            showError(
              (result.data && (result.data.message || result.data.error)) ||
                'Could not restore structure.'
            );
          })
          .catch(function () {
            btn.disabled = false;
            showError('Could not restore structure.');
          });
      });
    });
  }

  function initTypeChips() {
    var select = document.getElementById('node-type');
    if (!select) return;
    var chips = document.querySelectorAll('[data-node-type-chip]');
    if (!chips.length) return;

    var syncChips = function () {
      chips.forEach(function (chip) {
        chip.classList.toggle('is-active', chip.getAttribute('data-node-type-chip') === select.value);
      });
    };

    chips.forEach(function (chip) {
      chip.addEventListener('click', function () {
        var value = chip.getAttribute('data-node-type-chip');
        if (!value || select.value === value) return;
        select.value = value;
        select.dispatchEvent(new Event('change', { bubbles: true }));
        syncChips();
      });
    });

    select.addEventListener('change', syncChips);
    syncChips();
  }

  function initTypePanels() {
    var select = document.getElementById('node-type');
    if (!select) return;

    var sync = function () {
      document.querySelectorAll('[data-node-type-panel]').forEach(function (panel) {
        var on = panel.getAttribute('data-node-type-panel') === select.value;
        panel.hidden = !on;
        panel.querySelectorAll('input, select, textarea').forEach(function (input) {
          // Keep Craft element-select chrome usable; only disable named fields when off.
          if (input.closest('.elementselect')) return;
          input.disabled = !on;
        });
      });
      syncDynamic();
    };

    var dyn = document.getElementById('dynamic-source');
    var syncDynamic = function () {
      if (!dyn) return;
      var src = dyn.value;
      document.querySelectorAll('[data-dynamic-panel]').forEach(function (panel) {
        var on = panel.getAttribute('data-dynamic-panel') === src;
        panel.hidden = !on;
        panel.querySelectorAll('input, select, textarea').forEach(function (input) {
          input.disabled = !on;
        });
      });
    };

    select.addEventListener('change', sync);
    if (dyn) dyn.addEventListener('change', syncDynamic);
    sync();
  }

  function initCopyButtons() {
    document.querySelectorAll('[data-copy]').forEach(function (btn) {
      if (btn.dataset.copyReady) return;
      btn.dataset.copyReady = '1';
      btn.addEventListener('click', function () {
        var text = btn.getAttribute('data-copy') || '';
        if (!text) return;
        var done = function () {
          var original = btn.textContent;
          btn.classList.add('is-copied');
          btn.textContent = 'Copied';
          showNotice('Copied to clipboard.');
          setTimeout(function () {
            btn.classList.remove('is-copied');
            btn.textContent = original;
          }, 1400);
        };
        if (navigator.clipboard && navigator.clipboard.writeText) {
          navigator.clipboard.writeText(text).then(done).catch(function () {
            fallbackCopy(text, done);
          });
        } else {
          fallbackCopy(text, done);
        }
      });
    });
  }

  function fallbackCopy(text, done) {
    var area = document.createElement('textarea');
    area.value = text;
    area.setAttribute('readonly', '');
    area.style.position = 'fixed';
    area.style.left = '-9999px';
    document.body.appendChild(area);
    area.select();
    try {
      document.execCommand('copy');
      done();
    } catch (e) {
      showError('Could not copy.');
    }
    document.body.removeChild(area);
  }

  function initSnippetTabs() {
    document.querySelectorAll('[data-menu-snippets]').forEach(function (root) {
      var tabs = root.querySelectorAll('[data-snippet-tab]');
      var panels = root.querySelectorAll('[data-snippet-panel]');
      if (!tabs.length) return;
      tabs.forEach(function (tab) {
        tab.addEventListener('click', function () {
          var name = tab.getAttribute('data-snippet-tab');
          tabs.forEach(function (t) {
            var on = t === tab;
            t.classList.toggle('is-active', on);
            t.setAttribute('aria-selected', on ? 'true' : 'false');
          });
          panels.forEach(function (panel) {
            panel.hidden = panel.getAttribute('data-snippet-panel') !== name;
          });
        });
      });
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-menu-builder]').forEach(initBuilder);
    initDiscard();
    initTypeChips();
    initTypePanels();
    initCopyButtons();
    initSnippetTabs();
  });
})();

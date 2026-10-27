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

  function initBuilder(root) {
    if (!root || root.dataset.menuBuilderReady) return;
    root.dataset.menuBuilderReady = '1';

    var moveUrl = root.getAttribute('data-move-url');
    var menuId = root.getAttribute('data-menu-id');
    var siteId = root.getAttribute('data-site-id') || '';
    var dragId = null;
    var busy = false;

    var rows = function () {
      return Array.prototype.slice.call(root.querySelectorAll('[data-node-id]'));
    };

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
        row.classList.add('is-dragging');
        root.classList.add('is-busy');
        e.dataTransfer.effectAllowed = 'move';
        e.dataTransfer.setData('text/plain', dragId);
      });
      handle.addEventListener('dragend', function () {
        row.classList.remove('is-dragging');
        root.classList.remove('is-busy');
        clearDropClasses();
        dragId = null;
      });

      row.addEventListener('dragover', function (e) {
        if (!dragId || dragId === row.getAttribute('data-node-id') || busy) return;
        e.preventDefault();
        var pos = positionForEvent(row, e);
        clearDropClasses();
        row.classList.add('is-drop-' + pos);
      });

      row.addEventListener('dragleave', function () {
        row.classList.remove('is-drop-before', 'is-drop-after', 'is-drop-into');
      });

      row.addEventListener('drop', function (e) {
        e.preventDefault();
        var sourceId = dragId || e.dataTransfer.getData('text/plain');
        var targetId = row.getAttribute('data-node-id');
        var pos = positionForEvent(row, e);
        clearDropClasses();
        if (!sourceId || sourceId === targetId || busy) return;
        moveNode(sourceId, pos, targetId);
      });
    });

    function positionForEvent(row, e) {
      var rect = row.getBoundingClientRect();
      var y = e.clientY - rect.top;
      var ratio = y / rect.height;
      if (ratio < 0.25) return 'before';
      if (ratio > 0.75) return 'after';
      return 'into';
    }

    function clearDropClasses() {
      rows().forEach(function (r) {
        r.classList.remove('is-drop-before', 'is-drop-after', 'is-drop-into');
      });
    }

    function moveNode(nodeId, position, targetId) {
      busy = true;
      root.classList.add('is-busy');
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
          root.classList.remove('is-busy');
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
          root.classList.remove('is-busy');
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

  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-menu-builder]').forEach(initBuilder);
    initDiscard();
  });
})();

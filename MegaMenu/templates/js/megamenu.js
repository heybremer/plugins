(function () {
  'use strict';

  var HOVER_DELAY = 90;

  function activate(panel, idx) {
    var tabs = panel.querySelectorAll('.mm-tab[data-mm-tab]');
    var panes = panel.querySelectorAll('.mm-pane[data-mm-pane]');
    for (var i = 0; i < tabs.length; i++) {
      tabs[i].classList.toggle('is-active', tabs[i].getAttribute('data-mm-tab') === idx);
    }
    for (var j = 0; j < panes.length; j++) {
      panes[j].classList.toggle('is-active', panes[j].getAttribute('data-mm-pane') === idx);
    }
  }

  function bind(panel) {
    if (panel.getAttribute('data-mm-ready') === '1') return;
    panel.setAttribute('data-mm-ready', '1');
    var timer = null;
    var tabs = panel.querySelectorAll('.mm-tab[data-mm-tab]');
    Array.prototype.forEach.call(tabs, function (tab) {
      var idx = tab.getAttribute('data-mm-tab');
      tab.addEventListener('mouseenter', function () {
        clearTimeout(timer);
        timer = setTimeout(function () { activate(panel, idx); }, HOVER_DELAY);
      });
      tab.addEventListener('mouseleave', function () { clearTimeout(timer); });
      tab.addEventListener('focus', function () { activate(panel, idx); });
    });
  }

  function init() {
    Array.prototype.forEach.call(document.querySelectorAll('[data-mm-panel]'), bind);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();

(function () {
  'use strict';
  function focusErrors() {
    var errors = document.getElementById('qtrad-errors');
    if (errors && document.activeElement === document.body) errors.focus();
  }
  // WordPress common.js moves notices in its ready callback. Focus afterwards.
  window.jQuery(function () { window.setTimeout(focusErrors, 0); });

  // Settings tabs: one form, one section shown at a time. Without this script every section is visible.
  var nav = document.querySelector('.qtrad-tabs');
  if (!nav) return;
  var tabs = Array.prototype.slice.call(nav.querySelectorAll('a.nav-tab'));
  var panels = tabs.map(function (tab) { return document.getElementById(tab.getAttribute('href').slice(1)); });
  var field = document.getElementById('qtrad-tab-field');
  if (!tabs.length || panels.indexOf(null) !== -1) return;
  var rtl = (document.documentElement.getAttribute('dir') || '').toLowerCase() === 'rtl';
  function activate(n, remember) {
    tabs.forEach(function (tab, i) {
      var on = i === n;
      tab.classList.toggle('nav-tab-active', on);
      tab.setAttribute('aria-selected', on ? 'true' : 'false');
      tab.tabIndex = on ? 0 : -1;
      panels[i].classList.toggle('qtrad-panel--active', on);
      panels[i].hidden = !on;
    });
    if (field) field.value = panels[n].id.replace('qtrad-panel-', '');
    if (remember && window.history && window.history.replaceState) window.history.replaceState(null, '', '#' + panels[n].id);
  }
  nav.setAttribute('role', 'tablist');
  tabs.forEach(function (tab, i) {
    tab.setAttribute('role', 'tab');
    tab.setAttribute('aria-controls', panels[i].id);
    panels[i].setAttribute('role', 'tabpanel');
    // The selected tab names the section; the heading stays for screen readers and for the no-script page.
    var title = panels[i].querySelector('.qtrad-panel__title');
    if (title) title.classList.add('screen-reader-text');
    tab.addEventListener('click', function (event) { event.preventDefault(); activate(i, true); });
    tab.addEventListener('keydown', function (event) {
      var next = i, forward = rtl ? 'ArrowLeft' : 'ArrowRight', back = rtl ? 'ArrowRight' : 'ArrowLeft';
      if (event.key === forward) next = (i + 1) % tabs.length;
      else if (event.key === back) next = (i - 1 + tabs.length) % tabs.length;
      else if (event.key === 'Home') next = 0;
      else if (event.key === 'End') next = tabs.length - 1;
      else return;
      event.preventDefault();
      activate(next, true);
      tabs[next].focus();
    });
  });
  var current = 0, hash = window.location.hash.slice(1), errors = document.getElementById('qtrad-errors');
  tabs.forEach(function (tab, i) { if (tab.classList.contains('nav-tab-active')) current = i; });
  // A remembered tab wins over the server's default, except after a validation error, where the server opened the tab that holds the field.
  if (!errors) panels.forEach(function (panel, i) { if (panel.id === hash) current = i; });
  activate(current, !!errors);
  // The section sits right under the tabs, so a remembered tab starts at the top of the page, where notices are.
  if (hash) window.scrollTo(0, 0);
})();

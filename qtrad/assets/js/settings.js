(function () {
  'use strict';
  function focusErrors() {
    var errors = document.getElementById('qtn-errors');
    if (errors && document.activeElement === document.body) errors.focus();
  }
  // WordPress common.js moves notices in its ready callback. Focus afterwards.
  window.jQuery(function () { window.setTimeout(focusErrors, 0); });
})();

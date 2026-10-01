(function () {
  'use strict';
  function enhance() {
    document.querySelectorAll('.qtu-language-form').forEach(function (form) {
      form.addEventListener('submit', function (event) {
        event.preventDefault();
        var url = form.querySelector('select').value;
        if (url) window.location.assign(url);
      });
      form.hidden = false;
      var links = form.parentNode.querySelector('.qtrans_language_chooser');
      if (links) links.hidden = true;
    });
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', enhance);
  else enhance();
})();

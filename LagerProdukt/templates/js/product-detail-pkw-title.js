/* PKW-Lagerseite: gewähltes Fahrzeug (?fahrzeug=Marke Modell Baujahr) im Titel und Warenkorb. */
(function () {
  var vehicle = '';
  try {
    vehicle = String(new URLSearchParams(window.location.search).get('fahrzeug') || '').replace(/\s+/g, ' ').trim();
  } catch (e) {
    return;
  }
  if (!vehicle) {
    return;
  }
  vehicle = vehicle.slice(0, 120);

  function addHidden(form, name, value) {
    var existing = form.querySelector('input[name="' + name + '"]');
    if (existing) {
      existing.value = value;
      return;
    }
    var input = document.createElement('input');
    input.type = 'hidden';
    input.name = name;
    input.value = value;
    form.appendChild(input);
  }

  function run() {
    var titleNode = document.querySelector('h1.product-title.h2, h1.product-title, .product-title.h2');
    if (!titleNode) {
      return;
    }
    var original = titleNode.getAttribute('data-lp-original-title') || titleNode.textContent.trim();
    titleNode.setAttribute('data-lp-original-title', original);
    var title = original + ' – ' + vehicle;
    titleNode.textContent = title;

    ['buy_form', 'buy_form-quickview'].forEach(function (id) {
      var form = document.getElementById(id);
      if (form) {
        addHidden(form, 'lp_dyn_title', title);
      }
    });

    Array.prototype.forEach.call(
      document.querySelectorAll('#lp-detail-variants a[href], .lp-detail-variants a[href]'),
      function (link) {
        try {
          var url = new URL(link.getAttribute('href'), window.location.origin);
          url.searchParams.set('fahrzeug', vehicle);
          link.setAttribute('href', url.pathname + url.search + url.hash);
        } catch (e) {}
      }
    );
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', run);
  } else {
    run();
  }
})();

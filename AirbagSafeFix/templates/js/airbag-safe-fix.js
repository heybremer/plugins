(function () {
    'use strict';

    var form      = document.getElementById('asfForm');
    var errBox    = document.getElementById('asfErrors');
    var succBox   = document.getElementById('asfSuccess');
    var submitBtn = document.getElementById('asfSubmitBtn');
    var btnText   = submitBtn && submitBtn.querySelector('.asf-btn-text');
    var btnSpinner = submitBtn && submitBtn.querySelector('.asf-btn-spinner');

    if (!form) return;

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        clearMessages();

        var data = collectFormData();
        var clientErrors = validateClient(data);

        if (clientErrors.length > 0) {
            showErrors(clientErrors);
            return;
        }

        setLoading(true);

        var xhr = new XMLHttpRequest();
        xhr.open('POST', '/airbag-safe-fix-form', true);
        xhr.setRequestHeader('Content-Type', 'application/json');
        xhr.setRequestHeader('Accept', 'application/json');

        xhr.onload = function () {
            setLoading(false);
            try {
                var res = JSON.parse(xhr.responseText);
                if (res.success) {
                    form.reset();
                    showSuccess();
                    window.scrollTo({ top: succBox.getBoundingClientRect().top + window.scrollY - 80, behavior: 'smooth' });
                } else {
                    showErrors(res.errors || ['Ein unbekannter Fehler ist aufgetreten.']);
                }
            } catch (err) {
                showErrors(['Server-Fehler. Bitte versuchen Sie es erneut.']);
            }
        };

        xhr.onerror = function () {
            setLoading(false);
            showErrors(['Netzwerkfehler. Bitte Verbindung prüfen.']);
        };

        xhr.send(JSON.stringify(data));
    });

    function collectFormData() {
        var fd = new FormData(form);
        var out = {};
        fd.forEach(function (val, key) {
            out[key] = typeof val === 'string' ? val.trim() : val;
        });
        out['datenschutz'] = document.getElementById('asfDatenschutz').checked ? '1' : '';
        return out;
    }

    function validateClient(d) {
        var errs = [];
        if (!d.vorname) errs.push('Bitte Vornamen angeben.');
        if (!d.nachname) errs.push('Bitte Nachnamen angeben.');
        if (!d.email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(d.email)) {
            errs.push('Bitte eine gültige E-Mail-Adresse angeben.');
        }
        if (!d.produktName) errs.push('Bitte Produktname/Modell angeben.');
        if (!d.datenschutz) errs.push('Bitte die Datenschutzerklärung akzeptieren.');
        return errs;
    }

    function showErrors(errors) {
        errBox.innerHTML = errors.map(function (e) {
            return '<div>• ' + escHtml(e) + '</div>';
        }).join('');
        errBox.style.display = '';
        errBox.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    function showSuccess() {
        succBox.style.display = '';
    }

    function clearMessages() {
        errBox.style.display = 'none';
        errBox.innerHTML = '';
        succBox.style.display = 'none';
    }

    function setLoading(on) {
        if (!submitBtn) return;
        submitBtn.disabled = on;
        if (btnText)    btnText.style.display    = on ? 'none' : '';
        if (btnSpinner) btnSpinner.style.display = on ? '' : 'none';
    }

    function escHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }
})();

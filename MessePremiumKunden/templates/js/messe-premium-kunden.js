(function () {
    'use strict';

    if (window.__mpkFormBound) {
        return;
    }
    window.__mpkFormBound = true;

    var form = document.getElementById('mpkForm');
    var errBox = document.getElementById('mpkErrors');
    var succBox = document.getElementById('mpkSuccess');
    var submitBtn = document.getElementById('mpkSubmitBtn');
    var btnText = submitBtn && submitBtn.querySelector('.mpk-btn-text');
    var btnSpinner = submitBtn && submitBtn.querySelector('.mpk-btn-spinner');
    var markeSel = document.getElementById('mpkMarke');
    var modellSel = document.getElementById('mpkModell');
    var fotoInput = document.getElementById('mpkFotos');

    if (!form) return;

    var modelleUrl = form.getAttribute('data-modelle-url') || '/messe-premium-kunden-modelle';
    var maxFotos = parseInt(form.getAttribute('data-max-fotos') || '5', 10);
    var maxKb = parseInt(form.getAttribute('data-max-kb') || '5120', 10);

    if (markeSel) {
        markeSel.addEventListener('change', function () {
            loadModelle(markeSel.value);
        });
    }

    if (fotoInput) {
        fotoInput.addEventListener('change', function () {
            if (fotoInput.files && fotoInput.files.length > maxFotos) {
                showErrors(['Es sind höchstens ' + maxFotos + ' Fotos erlaubt.']);
                fotoInput.value = '';
            }
        });
    }

    var sending = false;
    form.addEventListener('submit', function (e) {
        e.preventDefault();
        e.stopPropagation();
        if (sending) {
            return;
        }
        clearMessages();

        var clientErrors = validateClient();
        if (clientErrors.length > 0) {
            showErrors(clientErrors);
            return;
        }

        sending = true;
        setLoading(true);
        var xhr = new XMLHttpRequest();
        xhr.open('POST', form.getAttribute('action') || '/messe-premium-kunden', true);
        xhr.setRequestHeader('Accept', 'application/json');
        xhr.onload = function () {
            sending = false;
            setLoading(false);
            try {
                var res = JSON.parse(xhr.responseText);
                if (res.success) {
                    form.reset();
                    resetModell();
                    showSuccess(res);
                    window.scrollTo({ top: succBox.getBoundingClientRect().top + window.scrollY - 80, behavior: 'smooth' });
                } else {
                    showErrors(res.errors || ['Ein unbekannter Fehler ist aufgetreten.']);
                }
            } catch (err) {
                showErrors(['Server-Fehler. Bitte versuchen Sie es erneut.']);
            }
        };
        xhr.onerror = function () {
            sending = false;
            setLoading(false);
            showErrors(['Netzwerkfehler. Bitte Verbindung prüfen.']);
        };
        xhr.send(new FormData(form));
    });

    function loadModelle(marke) {
        resetModell();
        if (!marke || marke === 'Andere') {
            modellSel.innerHTML = '<option value="">– Manuell eingeben –</option>';
            return;
        }
        modellSel.innerHTML = '<option value="">Lädt…</option>';
        var xhr = new XMLHttpRequest();
        xhr.open('GET', modelleUrl + '?marke=' + encodeURIComponent(marke), true);
        xhr.setRequestHeader('Accept', 'application/json');
        xhr.onload = function () {
            try {
                var res = JSON.parse(xhr.responseText);
                var list = (res && res.modelle) ? res.modelle : [];
                var html = '<option value="">– Bitte wählen –</option>';
                list.forEach(function (m) {
                    html += '<option value="' + escAttr(m) + '">' + escHtml(m) + '</option>';
                });
                html += '<option value="Andere">Andere / manuell</option>';
                modellSel.innerHTML = html;
            } catch (err) {
                modellSel.innerHTML = '<option value="">– Manuell eingeben –</option><option value="Andere">Andere / manuell</option>';
            }
        };
        xhr.onerror = function () {
            modellSel.innerHTML = '<option value="">– Manuell eingeben –</option><option value="Andere">Andere / manuell</option>';
        };
        xhr.send();
    }

    function resetModell() {
        if (modellSel) {
            modellSel.innerHTML = '<option value="">– Zuerst Marke wählen –</option>';
        }
    }

    function validateClient() {
        var errs = [];
        var name = val('mpkName');
        var tel = val('mpkTelefon');
        var email = val('mpkEmail');
        var adresse = val('mpkAdresse');
        var marke = markeSel ? markeSel.value : '';
        var modell = modellSel ? modellSel.value : '';
        var manuell = val('mpkManuell');
        var ds = document.getElementById('mpkDatenschutz');

        if (!name) errs.push('Bitte Vor- und Nachname angeben.');
        if (!tel) errs.push('Bitte eine Telefonnummer angeben.');
        if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
            errs.push('Bitte eine gültige E-Mail-Adresse angeben.');
        }
        if (!adresse) errs.push('Bitte eine Adresse angeben.');
        if (!marke && !manuell) {
            errs.push('Bitte Marke/Modell wählen oder manuell eingeben.');
        }
        if (marke === 'Andere' && !manuell && !modell) {
            errs.push('Bitte Marke und Modell manuell eingeben.');
        }
        if (!ds || !ds.checked) {
            errs.push('Bitte die Datenschutzerklärung akzeptieren.');
        }
        if (fotoInput && fotoInput.files) {
            if (fotoInput.files.length > maxFotos) {
                errs.push('Es sind höchstens ' + maxFotos + ' Fotos erlaubt.');
            }
            var maxBytes = maxKb * 1024;
            for (var i = 0; i < fotoInput.files.length; i++) {
                if (fotoInput.files[i].size > maxBytes) {
                    errs.push('Foto „' + fotoInput.files[i].name + '“ ist zu groß.');
                }
            }
        }
        return errs;
    }

    function showSuccess(res) {
        var html = '<strong>Vielen Dank!</strong> Ihre Registrierung wurde übermittelt.';
        if (res.emailOk) {
            html += '<div>Den Gutscheincode haben wir Ihnen per E-Mail gesendet.</div>';
        } else {
            html += '<div>Die Bestätigung per E-Mail konnte nicht zugestellt werden. Bitte kontaktieren Sie uns.</div>';
        }
        succBox.innerHTML = html;
        succBox.style.display = '';
    }

    function showErrors(errors) {
        errBox.innerHTML = errors.map(function (e) {
            return '<div>• ' + escHtml(e) + '</div>';
        }).join('');
        errBox.style.display = '';
        errBox.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    function clearMessages() {
        errBox.style.display = 'none';
        errBox.innerHTML = '';
        succBox.style.display = 'none';
        succBox.innerHTML = '';
    }

    function setLoading(on) {
        if (!submitBtn) return;
        submitBtn.disabled = on;
        if (btnText) btnText.style.display = on ? 'none' : '';
        if (btnSpinner) btnSpinner.style.display = on ? '' : 'none';
    }

    function val(id) {
        var el = document.getElementById(id);
        return el ? String(el.value || '').trim() : '';
    }

    function escHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function escAttr(str) {
        return escHtml(str).replace(/'/g, '&#39;');
    }
})();

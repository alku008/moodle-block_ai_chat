// Lokale Erweiterung (20.11.2026, Wunsch der Nutzerin, nicht vom Hersteller): Abfrage beim Hochladen von Wissensdateien.
// Sobald im Upload-Feld ein neues PDF erscheint, fragt ein Fenster, ob die KI Bilder, Grafiken und Scans darin in Text umwandeln
// soll (kostet je Seite eine KI-Anfrage). Die Antwort geht als verstecktes Feld „bildki[]“ mit dem Formular mit – man speichert
// nur einmal. Die Häkchen in der Dateiliste bleiben, um die Wahl später zu ändern.
// Nur aktiv, wenn die KI hier Bilder lesen kann (local_ai_content + Zweck „Bild zu Text“).
define(['core/modal_save_cancel', 'core/modal_events', 'core/str'], function(ModalSaveCancel, ModalEvents, Str) {
    'use strict';

    /**
     * Dateinamen im Entwurfsbereich des Upload-Feldes (wie das Upload-Feld selbst sie abruft).
     *
     * @param {number} itemid Entwurfs-ID des Upload-Feldes
     * @returns {Promise<string[]>}
     */
    function dateinamen(itemid) {
        var daten = new URLSearchParams({action: 'list', itemid: String(itemid), filepath: '/', sesskey: M.cfg.sesskey});
        return fetch(M.cfg.wwwroot + '/repository/draftfiles_ajax.php?action=list', {method: 'POST', body: daten})
            .then(function(antwort) {
                return antwort.json();
            })
            .then(function(json) {
                return (json && Array.isArray(json.list) ? json.list : [])
                    .filter(function(eintrag) {
                        return eintrag.type !== 'folder';
                    })
                    .map(function(eintrag) {
                        return eintrag.filename;
                    });
            })
            .catch(function() {
                return null;
            });
    }

    /**
     * Text sicher in HTML einsetzen.
     *
     * @param {string} text
     * @returns {string}
     */
    function html(text) {
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    /**
     * Versteckte Felder „bildki[]“ für die gewählten Dateien setzen bzw. entfernen und die Auswahl unter dem Upload-Feld anzeigen.
     *
     * @param {HTMLFormElement} formular
     * @param {HTMLElement} anzeige Zeile unter dem Upload-Feld
     * @param {Object} wahl Dateiname → true/false
     * @param {string} beschriftung Text vor der Liste
     */
    function uebernehmen(formular, anzeige, wahl, beschriftung) {
        Object.keys(wahl).forEach(function(name) {
            formular.querySelectorAll('input[type="hidden"][data-bildki-abfrage]').forEach(function(feld) {
                if (feld.value === name) {
                    feld.remove();
                }
            });
            // Steht die Datei schon in der Liste mit Häkchen (gleicher Name), das Häkchen mitziehen.
            formular.querySelectorAll('input[type="checkbox"][name="bildki[]"]').forEach(function(box) {
                if (box.value === name) {
                    box.checked = wahl[name];
                }
            });
            if (wahl[name]) {
                var feld = document.createElement('input');
                feld.type = 'hidden';
                feld.name = 'bildki[]';
                feld.value = name;
                feld.setAttribute('data-bildki-abfrage', '1');
                formular.appendChild(feld);
            }
        });
        var gewaehlt = Array.prototype.map.call(formular.querySelectorAll('input[data-bildki-abfrage]'), function(feld) {
            return feld.value;
        });
        anzeige.textContent = gewaehlt.length ? '🤖 ' + beschriftung + ' ' + gewaehlt.join(', ') : '';
        anzeige.style.display = gewaehlt.length ? '' : 'none';
    }

    /**
     * Fenster mit der Frage für die neuen PDFs öffnen.
     *
     * @param {string[]} neu Namen der neuen PDFs
     * @param {Object} texte Sprachtexte
     * @returns {Promise<Object>} Dateiname → true/false
     */
    function fragen(neu, texte) {
        var zeilen = neu.map(function(name, i) {
            return '<div class="form-check" style="margin: 6px 0;">'
                + '<input class="form-check-input" type="checkbox" id="bildki-neu-' + i + '" data-name="' + html(name) + '">'
                + '<label class="form-check-label" for="bildki-neu-' + i + '"><strong>'
                + html(name) + '</strong> – ' + html(texte.haken) + '</label></div>';
        }).join('');
        var inhalt = '<p>' + texte.frage + '</p>' + zeilen
            + '<p style="margin: 10px 0 0 0; font-size: 0.85rem; color: #5b6670;">' + texte.kosten + '</p>';
        return ModalSaveCancel.create({
            title: texte.titel,
            body: inhalt,
            buttons: {save: texte.uebernehmen, cancel: texte.nurtext},
            show: true,
            removeOnClose: true,
        }).then(function(modal) {
            return new Promise(function(fertig) {
                var ergebnis = null;
                modal.getRoot().on(ModalEvents.save, function() {
                    ergebnis = {};
                    modal.getRoot()[0].querySelectorAll('input[data-name]').forEach(function(box) {
                        ergebnis[box.getAttribute('data-name')] = box.checked;
                    });
                });
                modal.getRoot().on(ModalEvents.hidden, function() {
                    if (ergebnis === null) {
                        ergebnis = {};
                        neu.forEach(function(name) {
                            ergebnis[name] = false;
                        });
                    }
                    fertig(ergebnis);
                });
            });
        });
    }

    var modul = {
        /**
         * Warten, bis ein Upload-Feld mit diesem Namen erscheint (z. B. im Persona-Formular, das erst später in ein Fenster
         * geladen wird), und es dann beobachten.
         *
         * @param {string} feldname
         */
        beobachten: function(feldname) {
            new MutationObserver(function() {
                var feld = document.querySelector('input[name="' + feldname + '"]');
                if (feld && !feld.dataset.bildkiBeobachtet) {
                    modul.init(feldname);
                }
            }).observe(document.body, {childList: true, subtree: true});
        },

        /**
         * Upload-Feld beobachten.
         *
         * @param {string} feldname Name des Upload-Feldes („dateien“ bzw. „wissensdateien“)
         */
        init: function(feldname) {
            var versteckt = document.querySelector('input[name="' + feldname + '"]');
            if (!versteckt || versteckt.dataset.bildkiBeobachtet) {
                return;
            }
            versteckt.dataset.bildkiBeobachtet = '1';
            var formular = versteckt.closest('form');
            var bereich = versteckt.closest('.fitem') || versteckt.parentElement;
            if (!formular || !bereich) {
                return;
            }
            var itemid = parseInt(versteckt.value, 10);
            var anzeige = document.createElement('div');
            anzeige.style.cssText = 'display: none; margin: 4px 0 0 0; font-size: 0.85rem; color: #5b6670;';
            (bereich.querySelector('.filemanager') || bereich).insertAdjacentElement('afterend', anzeige);

            var texte = null;
            var bekannt = null;
            var laeuft = false;
            var warten = null;

            Str.get_strings([
                {key: 'bildkiabfragetitel', component: 'block_ai_chat'},
                {key: 'bildkiabfrage', component: 'block_ai_chat'},
                {key: 'bildkihaken', component: 'block_ai_chat'},
                {key: 'bildkiabfragekosten', component: 'block_ai_chat'},
                {key: 'bildkiabfrageja', component: 'block_ai_chat'},
                {key: 'bildkiabfragenein', component: 'block_ai_chat'},
                {key: 'bildkiabfragegewaehlt', component: 'block_ai_chat'},
            ]).then(function(s) {
                texte = {titel: s[0], frage: s[1], haken: s[2], kosten: s[3], uebernehmen: s[4], nurtext: s[5], gewaehlt: s[6]};
                return dateinamen(itemid);
            }).then(function(namen) {
                bekannt = namen || [];
                return null;
            }).catch(function() {
                bekannt = [];
            });

            var pruefen = function() {
                if (laeuft || texte === null || bekannt === null) {
                    return;
                }
                laeuft = true;
                dateinamen(itemid).then(function(namen) {
                    if (namen === null) {
                        return null;
                    }
                    var neu = namen.filter(function(name) {
                        return bekannt.indexOf(name) === -1 && /\.pdf$/i.test(name);
                    });
                    bekannt = namen;
                    if (!neu.length) {
                        return null;
                    }
                    return fragen(neu, texte).then(function(wahl) {
                        uebernehmen(formular, anzeige, wahl, texte.gewaehlt);
                        return null;
                    });
                }).catch(function() {
                    return null;
                }).then(function() {
                    laeuft = false;
                    return null;
                });
            };

            // Das Upload-Feld baut seine Dateiliste nach jedem Hochladen neu auf – dann nachsehen, ob ein neues PDF dabei ist.
            new MutationObserver(function() {
                clearTimeout(warten);
                warten = setTimeout(pruefen, 600);
            }).observe(bereich, {childList: true, subtree: true});
        },
    };
    return modul;
});

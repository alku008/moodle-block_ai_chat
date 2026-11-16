// Lokale Erweiterung (15.11.2026, nicht vom Hersteller): Datei im KI-Chat anhängen (für alle, auch Lernende).
// Ein 📎-Knopf neben dem Eingabefeld liest eine Textdatei im Browser (txt, md, py, ipynb, csv, json, html, …; Notebooks werden in
// Zellen mit Ausgaben umgewandelt) und setzt sie beim Absenden mit Markierungen vor die Frage. Der Server zeigt im Chatverlauf statt
// des Inhalts nur „📎 Dateiname“ (block_ai_chat\local\anhang::anzeige). Die Datei geht mit der Frage an die KI und bleibt für die
// nächsten Fragen im Verlauf (letzte Nachrichten). Keine Speicherung als Moodle-Datei.
// Ohne Build-Werkzeug: amd/build/anhang.min.js ist eine unveränderte Kopie dieser Datei.
define([], function() {
    'use strict';

    var EINSTELLUNG = window.blockAiChatAnhang || {erlaubt: true, maxzeichen: 20000};
    var MAXZEICHEN = EINSTELLUNG.maxzeichen || 20000;
    var ENDUNGEN = ['txt', 'md', 'py', 'ipynb', 'csv', 'json', 'html', 'htm', 'xml', 'js', 'java', 'c', 'cpp', 'sql', 'tex', 'log'];

    /**
     * Notebook (JSON) in lesbaren Text umwandeln: Markdown-Zellen, Code-Zellen und kurze Ausgaben/Fehlermeldungen.
     *
     * @param {string} json Inhalt der .ipynb-Datei
     * @returns {string} Text
     */
    function notebookText(json) {
        var nb = JSON.parse(json);
        var teile = [];
        (nb.cells || []).forEach(function(zelle, i) {
            var quelle = Array.isArray(zelle.source) ? zelle.source.join('') : String(zelle.source || '');
            if (quelle.trim() === '') {
                return;
            }
            if (zelle.cell_type === 'code') {
                var text = '# Zelle ' + (i + 1) + ' (Code)\n' + quelle.trim();
                var ausgaben = [];
                (zelle.outputs || []).forEach(function(a) {
                    if (a.output_type === 'error') {
                        ausgaben.push('FEHLER: ' + a.ename + ': ' + a.evalue);
                    } else if (a.text) {
                        ausgaben.push(Array.isArray(a.text) ? a.text.join('') : String(a.text));
                    } else if (a.data && a.data['text/plain']) {
                        var t = a.data['text/plain'];
                        ausgaben.push(Array.isArray(t) ? t.join('') : String(t));
                    }
                });
                if (ausgaben.length) {
                    text += '\n# Ausgabe:\n' + ausgaben.join('\n').slice(0, 1500);
                }
                teile.push(text);
            } else {
                teile.push('# Zelle ' + (i + 1) + ' (Text)\n' + quelle.trim());
            }
        });
        return teile.join('\n\n');
    }

    /**
     * 📎-Knopf in das Eingabefeld des Chats einbauen.
     *
     * @param {HTMLElement} wurzel Element, in dem das Eingabefeld liegt
     */
    function init(wurzel) {
        var eingabe = wurzel.querySelector('.block_ai_chat-input');
        var textfeld = wurzel.querySelector("[data-block_ai_chat-element='inputtextarea']");
        var senden = wurzel.querySelector("[data-block_ai_chat-element='submitbutton']");
        if (!EINSTELLUNG.erlaubt || !eingabe || !textfeld || !senden || eingabe.querySelector('.kichat-anhang-knopf')) {
            return;
        }
        var anhang = null;

        var datei = document.createElement('input');
        datei.type = 'file';
        datei.accept = ENDUNGEN.map(function(e) {
            return '.' + e;
        }).join(',');
        datei.style.display = 'none';

        var knopf = document.createElement('button');
        knopf.type = 'button';
        knopf.className = 'btn btn-icon kichat-anhang-knopf';
        knopf.title = 'Datei anhängen (Text, Code, Notebook)';
        knopf.setAttribute('aria-label', 'Datei anhängen');
        knopf.textContent = '📎';
        // Wie das KI-Werkzeug-Symbol links: absolut im Eingabefeld, hier links neben dem Senden-Knopf.
        knopf.style.cssText = 'position: absolute; right: 4.7rem; bottom: 0.65rem; z-index: 2; font-size: 1.3rem; line-height: 1; '
            + 'padding: 6px; background: none; border: none; cursor: pointer;';
        textfeld.style.paddingRight = '7.2rem';

        var marke = document.createElement('div');
        marke.style.cssText = 'display: none; margin: 0 0 4px 0; padding: 3px 8px; border-radius: 12px; background: #ede9fe; '
            + 'color: #4c1d95; font-size: 0.85rem; width: fit-content; max-width: 100%;';

        senden.parentNode.insertBefore(knopf, senden);
        eingabe.parentNode.insertBefore(marke, eingabe);
        eingabe.appendChild(datei);

        /**
         * Marke über dem Eingabefeld anzeigen bzw. ausblenden.
         *
         * @param {string} hinweis Zusatztext (z. B. gekürzt)
         */
        function zeigeMarke(hinweis) {
            marke.textContent = '';
            if (!anhang) {
                marke.style.display = 'none';
                return;
            }
            marke.appendChild(document.createTextNode('📎 ' + anhang.name + (hinweis ? ' – ' + hinweis : '') + ' '));
            var weg = document.createElement('button');
            weg.type = 'button';
            weg.textContent = '✕';
            weg.setAttribute('aria-label', 'Anhang entfernen');
            weg.style.cssText = 'border: none; background: none; color: #4c1d95; cursor: pointer; padding: 0 2px;';
            weg.addEventListener('click', function() {
                anhang = null;
                zeigeMarke('');
            });
            marke.appendChild(weg);
            marke.style.display = 'block';
        }

        knopf.addEventListener('click', function() {
            datei.value = '';
            datei.click();
        });

        datei.addEventListener('change', function() {
            var f = datei.files && datei.files[0];
            if (!f) {
                return;
            }
            var endung = (f.name.split('.').pop() || '').toLowerCase();
            if (ENDUNGEN.indexOf(endung) === -1) {
                window.alert('Diese Datei kann der Chat nicht lesen. Möglich sind Text- und Code-Dateien: ' + ENDUNGEN.join(', ')
                    + '. Word oder PDF bitte als Text kopieren und einfügen.');
                return;
            }
            var leser = new FileReader();
            leser.onload = function() {
                var text = String(leser.result || '');
                try {
                    if (endung === 'ipynb') {
                        text = notebookText(text);
                    }
                } catch (e) {
                    window.alert('Das Notebook ließ sich nicht lesen (kein gültiges JSON).');
                    return;
                }
                var hinweis = '';
                if (text.length > MAXZEICHEN) {
                    text = text.slice(0, MAXZEICHEN) + '\n… (gekürzt)';
                    hinweis = 'gekürzt auf ' + MAXZEICHEN + ' Zeichen';
                }
                anhang = {name: f.name.replace(/[„“\n]/g, ''), text: text};
                zeigeMarke(hinweis);
                textfeld.focus();
            };
            leser.readAsText(f);
        });

        /**
         * Vor dem Absenden (Erfassungsphase, also vor dem Chat-Programm) die Datei vor die Frage setzen.
         */
        function einsetzen() {
            if (!anhang) {
                return;
            }
            if (textfeld.value.trim() === '') {
                textfeld.value = 'Bitte sieh dir diese Datei an.';
            }
            textfeld.value = '📎 Datei „' + anhang.name + '“:\n````\n' + anhang.text + '\n````\n(Ende der Datei „' + anhang.name
                + '“)\n\n' + textfeld.value;
            anhang = null;
            zeigeMarke('');
        }
        eingabe.addEventListener('click', function(e) {
            if (senden.contains(e.target)) {
                einsetzen();
            }
        }, true);
        eingabe.addEventListener('keydown', function(e) {
            if (e.target === textfeld && e.key === 'Enter' && !e.shiftKey) {
                einsetzen();
            }
        }, true);
    }

    return {init: init};
});

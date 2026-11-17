// Lokale Erweiterung (17.11.2026, Wunsch der Nutzerin, nicht vom Hersteller): Vorschläge im KI-Chat.
//  - Leerer Chat: Begrüßung und Startvorschläge zum Antippen; sie werden ins Eingabefeld geschrieben (zum Ergänzen, nicht sofort gesendet).
//  - Nach der letzten Antwort der KI: Knöpfe für gestufte Hilfe („💡 Noch ein Tipp“ …), die sofort gesendet werden.
// Die Texte legt die Administration fest (Einstellungen startvorschlaege, folgevorschlaege; window.blockAiChatVorschlaege).
// Ohne Build-Werkzeug: amd/build/vorschlaege.min.js ist eine unveränderte Kopie dieser Datei.
define([], function() {
    'use strict';

    var STANDARD = {
        start: ['Ich komme bei der Aufgabe … nicht weiter.', 'Erklär mir bitte noch einmal: …',
            'Was bedeutet eigentlich …?'],
        folge: [{text: '💡 Noch ein Tipp', prompt: 'Gib mir bitte noch einen Tipp – aber noch nicht die Lösung.'},
            {text: '🧩 Ähnliches Beispiel', prompt: 'Zeig mir bitte ein ähnliches, einfacheres Beispiel – nicht die Lösung meiner Aufgabe.'}]
    };

    /**
     * Knopf im Stil der Vorschläge (Rahmen in heller Kachelfarbe).
     *
     * @param {string} text Beschriftung
     * @returns {HTMLButtonElement}
     */
    function chip(text) {
        var b = document.createElement('button');
        b.type = 'button';
        b.textContent = text;
        b.style.cssText = 'border: 1px solid var(--format-tiles-colour-5, #9fc4e5); background: var(--format-tiles-colour-05, #f3f8fc); '
            + 'color: var(--format-tiles-colour, #0f6cbf); border-radius: 16px; padding: 4px 12px; margin: 0 6px 6px 0; '
            + 'font-size: 0.85rem; cursor: pointer; text-align: left;';
        return b;
    }

    /**
     * Vorschläge für ein Chatfenster einrichten.
     *
     * @param {HTMLElement} wrapper Der Eingabebereich (.block_ai_chat-input-wrapper)
     */
    function init(wrapper) {
        var einstellung = window.blockAiChatVorschlaege || STANDARD;
        var start = (einstellung.start && einstellung.start.length) ? einstellung.start : [];
        var folge = (einstellung.folge && einstellung.folge.length) ? einstellung.folge : [];
        var dialog = wrapper.parentNode;
        var ausgabe = dialog.querySelector("[data-block_ai_chat-element='chatoutput']");
        var textfeld = wrapper.querySelector("[data-block_ai_chat-element='inputtextarea']");
        var senden = wrapper.querySelector("[data-block_ai_chat-element='submitbutton']");
        if (!ausgabe || !textfeld || !senden || ausgabe.dataset.vorschlaege) {
            return;
        }
        ausgabe.dataset.vorschlaege = '1';

        var startfeld = document.createElement('div');
        startfeld.className = 'kichat-start';
        startfeld.style.cssText = 'margin: 18px 12px 8px 0;';
        var gruss = document.createElement('div');
        gruss.style.cssText = 'font-size: 1rem; margin-bottom: 10px;';
        gruss.textContent = '👋 Hallo! Wobei kann ich dir helfen?';
        startfeld.appendChild(gruss);
        start.forEach(function(text) {
            var b = chip(text.replace(/\s+$/, ''));
            b.addEventListener('click', function() {
                textfeld.value = text;
                textfeld.dispatchEvent(new Event('input', {bubbles: true}));
                textfeld.focus();
                // Platzhalter „…“ markieren, damit man ihn gleich überschreiben kann.
                var pos = text.indexOf('…');
                if (pos >= 0) {
                    textfeld.setSelectionRange(pos, pos + 1);
                } else {
                    textfeld.setSelectionRange(text.length, text.length);
                }
            });
            startfeld.appendChild(b);
        });

        var folgefeld = document.createElement('div');
        folgefeld.className = 'kichat-folge';
        folgefeld.style.cssText = 'margin: 4px 0 4px 2rem;';
        folge.forEach(function(f) {
            var b = chip(f.text);
            b.addEventListener('click', function() {
                if (textfeld.disabled) {
                    return;
                }
                textfeld.value = f.prompt;
                textfeld.dispatchEvent(new Event('input', {bubbles: true}));
                senden.click();
            });
            folgefeld.appendChild(b);
        });

        /** Startfeld nur im leeren Chat, Folgeknöpfe nur unter der letzten fertigen Antwort der KI. */
        function aktualisieren() {
            var nachrichten = ausgabe.querySelectorAll('.message');
            if (nachrichten.length === 0) {
                if (start.length && startfeld.parentNode !== ausgabe) {
                    ausgabe.appendChild(startfeld);
                }
                folgefeld.remove();
                return;
            }
            startfeld.remove();
            var letzte = nachrichten[nachrichten.length - 1];
            var fertig = letzte.classList.contains('ai') && !letzte.querySelector('.awaitanswer');
            if (folge.length && fertig && !textfeld.disabled) {
                if (folgefeld.previousElementSibling !== letzte) {
                    letzte.after(folgefeld);
                }
            } else {
                folgefeld.remove();
            }
        }
        new MutationObserver(function(liste) {
            // Eigene Änderungen nicht erneut auswerten.
            var fremd = liste.some(function(m) {
                return !Array.prototype.some.call(m.addedNodes, function(n) {
                    return n === startfeld || n === folgefeld;
                }) && !Array.prototype.some.call(m.removedNodes, function(n) {
                    return n === startfeld || n === folgefeld;
                });
            });
            if (fremd) {
                aktualisieren();
            }
        }).observe(ausgabe, {childList: true, subtree: true});
        aktualisieren();
    }

    return {init: init};
});

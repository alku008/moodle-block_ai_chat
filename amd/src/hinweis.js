// Lokale Erweiterung (16.11.2026, Wunsch der Nutzerin, nicht vom Hersteller): KI-Hinweise im Chatfenster.
// Statt der beiden Kästen oben (Infobox und Warnhinweis des AI Managers) gibt es
//  - beim ersten Öffnen (je Browser) einen ausführlichen Hinweis zum Wegklicken, mit den Texten und Links der Administration,
//  - eine kleine Zeile unter dem Eingabefeld; „ⓘ Hinweise“ öffnet den ausführlichen Hinweis erneut.
// Ohne Build-Werkzeug: amd/build/hinweis.min.js ist eine unveränderte Kopie dieser Datei.
define(['local_ai_manager/infobox', 'local_ai_manager/warningbox'], function(Infobox, Warningbox) {
    'use strict';

    var SCHLUESSEL = 'block_ai_chat_hinweis_gelesen';

    /**
     * Wurde der Hinweis in diesem Browser schon weggeklickt?
     *
     * @returns {boolean}
     */
    function gelesen() {
        try {
            return window.localStorage.getItem(SCHLUESSEL) === '1';
        } catch (e) {
            return false;
        }
    }

    /**
     * Ausführlichen Hinweis über dem Chat anzeigen.
     *
     * @param {HTMLElement} rahmen Element, über das sich der Hinweis legt
     */
    function zeigeHinweis(rahmen) {
        if (rahmen.querySelector('.kichat-ki-hinweis')) {
            return;
        }
        if (getComputedStyle(rahmen).position === 'static') {
            rahmen.style.position = 'relative';
        }
        var ebene = document.createElement('div');
        ebene.className = 'kichat-ki-hinweis';
        ebene.setAttribute('role', 'dialog');
        ebene.setAttribute('aria-modal', 'true');
        ebene.setAttribute('aria-label', 'Hinweise zum KI-Chat');
        ebene.style.cssText = 'position: absolute; inset: 0; z-index: 20; background: rgba(255, 255, 255, 0.92); display: flex; '
            + 'align-items: center; justify-content: center; padding: 16px; border-radius: inherit;';
        var karte = document.createElement('div');
        karte.style.cssText = 'max-width: 440px; width: 100%; background: #fff; border: 1px solid #d5dbe3; border-radius: 12px; '
            + 'box-shadow: 0 8px 28px rgba(0, 0, 0, 0.18); padding: 18px 20px; font-size: 0.92rem; line-height: 1.45; '
            + 'max-height: 100%; '
            + 'overflow: auto;';
        var titel = document.createElement('div');
        titel.style.cssText = 'font-weight: bold; font-size: 1.05rem; margin-bottom: 8px;';
        titel.textContent = '🤖 Bevor du startest';
        var liste = document.createElement('ul');
        liste.style.cssText = 'margin: 0 0 10px 0; padding-left: 1.2rem;';
        var punkte = ['Deine Eingaben gehen an ein externes KI-System.',
            'Gib keine persönlichen Daten ein – keine Namen, Adressen, Noten oder Fotos von Personen.',
            'Die KI kann Fehler machen. Prüfe die Antworten, bevor du sie verwendest.'];
        // Ob Lehrkräfte die Fragen einsehen können, entscheidet die Administration (Recht „Nutzerprompts einsehen“).
        var einsicht = (window.blockAiChatAnhang || {}).prompteinsicht;
        if (einsicht === false) {
            punkte.push('Deine Lehrkraft sieht nicht, was du die KI fragst. Du nutzt sie eigenverantwortlich.');
        } else if (einsicht === true) {
            punkte.push('Deine Lehrkraft kann einsehen, was du die KI fragst.');
        }
        punkte.forEach(function(t) {
            var li = document.createElement('li');
            li.textContent = t;
            li.style.margin = '3px 0';
            liste.appendChild(li);
        });
        // Texte und Links, die die Administration im AI Manager hinterlegt hat.
        var admin = document.createElement('div');
        admin.style.cssText = 'font-size: 0.82rem; color: #5b6670; margin-bottom: 12px;';
        var info = document.createElement('div');
        var warnung = document.createElement('div');
        admin.appendChild(info);
        admin.appendChild(warnung);
        var knopf = document.createElement('button');
        knopf.type = 'button';
        knopf.className = 'btn btn-primary';
        knopf.textContent = 'Verstanden';
        knopf.addEventListener('click', function() {
            try {
                window.localStorage.setItem(SCHLUESSEL, '1');
            } catch (e) {
                // Ohne Speicher erscheint der Hinweis beim nächsten Öffnen wieder.
            }
            ebene.remove();
        });
        karte.appendChild(titel);
        karte.appendChild(liste);
        karte.appendChild(admin);
        karte.appendChild(knopf);
        ebene.appendChild(karte);
        rahmen.appendChild(ebene);
        Infobox.renderInfoBox('block_ai_chat', 0, info, ['chat'], true).catch(function() {
            return null;
        });
        Warningbox.renderWarningBox(warnung, true).catch(function() {
            return null;
        });
        knopf.focus();
    }

    /**
     * Mausrad im Chatfenster nicht an die Kursseite weitergeben (17.11.2026): Scrollt kein Bereich zwischen Mauszeiger und Fenster
     * mehr in diese Richtung (Ende des Verlaufs, Kopfzeile, Eingabefeld), wird das Scrollen der Seite verhindert.
     *
     * @param {HTMLElement} rahmen Das Chatfenster (.modal-content)
     */
    function scrollSperre(rahmen) {
        if (rahmen.dataset.kichatScroll) {
            return;
        }
        rahmen.dataset.kichatScroll = '1';
        rahmen.addEventListener('wheel', function(e) {
            var nachUnten = e.deltaY > 0;
            for (var el = e.target; el && el !== rahmen.parentNode; el = el.parentElement) {
                var stil = getComputedStyle(el);
                var scrollbar = /(auto|scroll)/.test(stil.overflowY) && el.scrollHeight > el.clientHeight + 1;
                if (scrollbar) {
                    var kannWeiter = nachUnten ? el.scrollTop + el.clientHeight < el.scrollHeight - 1 : el.scrollTop > 0;
                    if (kannWeiter) {
                        return;   // Dieser Bereich scrollt selbst.
                    }
                }
                if (el === rahmen) {
                    break;
                }
            }
            e.preventDefault();
        }, {passive: false});
    }

    /**
     * Kleine Zeile unter dem Eingabefeld einbauen und beim ersten Öffnen den Hinweis zeigen.
     *
     * @param {HTMLElement} wrapper Der Eingabebereich (.block_ai_chat-input-wrapper)
     */
    function init(wrapper) {
        var rahmen = wrapper.closest('.modal-content') || wrapper.parentNode;
        if (wrapper.closest('.modal-content')) {
            scrollSperre(rahmen);
        }
        if (!wrapper.querySelector('.kichat-ki-zeile')) {
            var zeile = document.createElement('div');
            zeile.className = 'kichat-ki-zeile';
            zeile.style.cssText = 'font-size: 0.72rem; color: #6a737d; text-align: center; margin: 4px 0 2px 0; line-height: 1.3;';
            zeile.appendChild(document.createTextNode('KI kann Fehler machen – Antworten prüfen, keine persönlichen Daten.'));
            zeile.appendChild(document.createElement('br'));
            var link = document.createElement('a');
            link.href = '#';
            link.textContent = 'ⓘ Hinweise';
            link.style.cssText = 'display: inline-block; margin-top: 1px; color: #6a737d; text-decoration: underline; '
                + 'white-space: nowrap;';
            link.addEventListener('click', function(e) {
                e.preventDefault();
                zeigeHinweis(rahmen);
            });
            zeile.appendChild(link);
            wrapper.appendChild(zeile);
        }
        if (!gelesen()) {
            zeigeHinweis(rahmen);
        }
    }

    return {init: init};
});

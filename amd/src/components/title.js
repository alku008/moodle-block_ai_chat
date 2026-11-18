// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

import {BaseComponent} from 'core/reactive';
import {getString} from 'core/str';
import {stripHtmlTags} from 'block_ai_chat/utils';
import {MODES} from 'block_ai_chat/constants';

/**
 * Component representing a message in the ai_chat.
 */
class Title extends BaseComponent {

    // Lokal (16.11.2026, Wunsch der Nutzerin): In der Kopfzeile steht im Chat der Name der gewählten Persona (sonst „KI-Chat“),
    // nicht mehr die erste Frage der Unterhaltung – die wurde abgeschnitten und war wenig hilfreich. Die Kurzbeschreibung der
    // Persona steht darunter im Chatfenster (components/boxes.js).
    defaultTitle = null;

    /**
     * Function to initialize component, called by mustache template.
     *
     * @param {*} target The id of the HTMLElement to attach to
     * @returns {BaseComponent} New component attached to the HTMLElement represented by target
     */
    static init(target) {
        let element = document.querySelector(target);
        return new this({
            element: element,
        });
    }

    create() {
        this.name = 'title';
        this.selectors = {
            TITLE: `[data-block_ai_chat-element='titleheading']`,
        };
    }

    async stateReady() {
        this.defaultTitle = await getString('pluginname', 'block_ai_chat');
        this._showChatTitle();
        // Lokal (18.11.2026): Persona-Auswahl im KI-Kontrollzentrum derselben Seite abgleichen – in beide Richtungen.
        this._meldePersonas();
        if (!this.kontrollzentrumListener) {
            this.kontrollzentrumListener = (e) => {
                const detail = e.detail || {};
                const personaId = parseInt(detail.personaid);
                if (parseInt(detail.chatcontextid) !== parseInt(this.reactive.state.static.contextid) || isNaN(personaId)
                        || personaId === parseInt(this.reactive.state.config.currentPersona)) {
                    return;
                }
                this.reactive.dispatch('selectCurrentPersona', personaId);
            };
            document.addEventListener('block_ai_control:persona', this.kontrollzentrumListener);
        }
    }

    /**
     * Lokal (18.11.2026): Teilt anderen Teilen der Seite (KI-Kontrollzentrum) mit, welche Personas es gibt und welche gewählt ist,
     * damit deren Auswahl ohne Neuladen stimmt (z. B. nach dem Umbenennen oder Wählen einer Persona im Chatfenster).
     */
    _meldePersonas() {
        const state = this.reactive.state;
        const personas = [];
        state.personas.forEach((p) => personas.push({id: parseInt(p.id), name: stripHtmlTags(p.name)}));
        document.dispatchEvent(new CustomEvent('block_ai_chat:personas', {detail: {
            chatcontextid: parseInt(state.static.contextid),
            currentPersona: parseInt(state.config.currentPersona),
            personas: personas,
        }}));
    }

    /**
     * Watchers for this component.
     * @returns {array}
     */
    getWatchers() {
        return [
            {watch: `config.view:updated`, handler: this._updateTitle},
            {watch: `config.currentPersona:updated`, handler: this._personasGeaendert},
            {watch: `config.mode:updated`, handler: this._showChatTitle},
            {watch: `personas:updated`, handler: this._personasGeaendert},
            {watch: `personas:created`, handler: this._personasGeaendert},
            {watch: `personas:deleted`, handler: this._personasGeaendert},
        ];
    }

    _personasGeaendert() {
        this._showChatTitle();
        this._meldePersonas();
    }

    _showChatTitle() {
        const state = this.reactive.state;
        if (state.config.view !== 'chat' || this.defaultTitle === null) {
            return;
        }
        const personaId = parseInt(state.config.currentPersona);
        const persona = personaId !== 0 && state.config.mode !== MODES.AGENT ? state.personas.get(personaId) : null;
        const title = persona ? stripHtmlTags(persona.name) : this.defaultTitle;
        this.getElement(this.selectors.TITLE).innerText = title;
        this.getElement(this.selectors.TITLE).title = title;
        this._fitTitle();
    }

    /**
     * Schriftgröße so wählen, dass der Titel die Breite ausnutzt (1,3 rem bis 0,95 rem in einer Zeile, sonst zweizeilig).
     * Ein ResizeObserver passt sie an, wenn das Fenster seine Größe ändert (Fenster, Vollbild, angedockt).
     */
    _fitTitle() {
        const el = this.getElement(this.selectors.TITLE);
        if (!this.resizeObserver && window.ResizeObserver) {
            this.resizeObserver = new ResizeObserver(() => this._fitTitle());
            this.resizeObserver.observe(el.parentElement);
        }
        if (el.clientWidth === 0) {
            return;
        }
        el.style.display = 'block';
        el.style.whiteSpace = 'nowrap';
        el.style.webkitLineClamp = '';
        let size = 1.3;
        el.style.fontSize = size + 'rem';
        while (el.scrollWidth > el.clientWidth && size > 0.95) {
            size = Math.round((size - 0.05) * 100) / 100;
            el.style.fontSize = size + 'rem';
        }
        if (el.scrollWidth > el.clientWidth) {
            el.style.whiteSpace = 'normal';
            el.style.display = '-webkit-box';
            el.style.webkitBoxOrient = 'vertical';
            el.style.webkitLineClamp = '2';
        }
    }

    async _updateTitle({element}) {
        if (element.view === 'history') {
            this.getElement(this.selectors.TITLE).innerText = await getString('history', 'block_ai_chat');
        } else if (element.view === 'personalist') {
            this.getElement(this.selectors.TITLE).innerText = await getString('managepersona', 'block_ai_chat');
        } else if (element.view === 'chat') {
            this._showChatTitle();
        }
    }
}

export default Title;

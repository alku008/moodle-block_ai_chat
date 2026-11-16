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
import {MODES} from 'block_ai_chat/constants';

/**
 * Lokal (16.11.2026, Wunsch der Nutzerin): Unter der Kopfzeile steht nur noch die Kurzbeschreibung der gewählten Persona.
 * Die KI-Hinweise (Infobox und Warnhinweis des AI Managers) zeigt amd/src/hinweis.js: ausführlich beim ersten Öffnen zum
 * Wegklicken und als kleine Zeile unter dem Eingabefeld.
 */
class Boxes extends BaseComponent {

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
        this.name = 'boxes';
        this.selectors = {
            PERSONADESCRIPTION: `[data-block_ai_chat-element='personadescription']`,
        };
    }

    getWatchers() {
        return [
            {watch: `config.view:updated`, handler: this._showPersonaDescription},
            {watch: `config.currentPersona:updated`, handler: this._showPersonaDescription},
            {watch: `config.mode:updated`, handler: this._showPersonaDescription},
            {watch: `personas:updated`, handler: this._showPersonaDescription},
            {watch: `personas:deleted`, handler: this._showPersonaDescription},
        ];
    }

    stateReady() {
        this._showPersonaDescription();
    }

    _showPersonaDescription() {
        const state = this.reactive.state;
        const target = this.getElement(this.selectors.PERSONADESCRIPTION);
        const personaId = parseInt(state.config.currentPersona);
        const persona = personaId !== 0 && state.config.mode !== MODES.AGENT ? state.personas.get(personaId) : null;
        if (state.config.view !== 'chat' || !persona || !persona.userinfo) {
            target.classList.add('d-none');
            return;
        }
        // Die Beschreibung kann <br /> enthalten (Formular-Text); nur als reinen Text anzeigen.
        target.textContent = persona.userinfo.replace(/<br\s*\/?>/gi, ' ').replace(/<[^>]*>/g, '').replace(/\s+/g, ' ').trim();
        target.classList.remove('d-none');
    }
}

export default Boxes;

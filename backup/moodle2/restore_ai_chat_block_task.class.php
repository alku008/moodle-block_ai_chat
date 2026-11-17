<?php
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

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/blocks/ai_chat/backup/moodle2/restore_ai_chat_block_stepslib.php');

/**
 * Restore class for block_ai_chat
 *
 * @package    block_ai_chat
 * @copyright  2026 ISB Bayern
 * @author     Stefan Hanauska <stefan.hanauska@csg-in.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_ai_chat_block_task extends restore_block_task {
    /**
     * Does nothing.
     *
     * @return void
     */
    protected function define_my_settings() {
    }

    /**
     * Does nothing.
     *
     * @return void
     */
    protected function define_my_steps() {
        $this->add_step(new restore_ai_chat_block_structure_step('aichat', 'aichat.xml'));
    }

    /**
     * Lokal (17.11.2026): Nach der Wiederherstellung die Wissensdateien des Kurses im Hintergrund neu zerlegen (Embeddings).
     */
    public function after_restore() {
        $kurs = \context_course::instance($this->get_courseid(), IGNORE_MISSING);
        if ($kurs) {
            \block_ai_chat\task\wissen_zerlegen::planen($kurs->instanceid);
        }
    }

    /**
     * Lokal (17.11.2026): Wissensdateien des Kurses liegen im Dateibereich „kurswissen“ des KI-Chat-Blocks und reisen so mit
     * Kurssicherungen mit (die Abschnitte werden nach der Wiederherstellung neu berechnet).
     *
     * @return array
     */
    public function get_fileareas() {
        return ['kurswissen'];
    }

    /**
     * No encoded attributes in configdata yet.
     *
     * @return array
     */
    public function get_configdata_encoded_attributes() {
        return [];
    }

    /**
     * Returns empty array
     *
     * @return array
     */
    public static function define_decode_contents() {
        return [];
    }

    /**
     * Returns empty array
     *
     * @return array
     */
    public static function define_decode_rules() {
        return [];
    }
}

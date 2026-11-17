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

/**
 * Upgrade code for block_ai_chat.
 *
 * @package   block_ai_chat
 * @copyright 2025 Tobias Garske, ISB Bayern
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * upgrade code
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_block_ai_chat_upgrade($oldversion) {
    global $CFG, $DB;
    require_once($CFG->dirroot . '/blocks/ai_chat/db/upgradelib.php');

    $dbman = $DB->get_manager();

    if ($oldversion < 2025021000) {
        // Install persona DB tables.
        $installxmlfile = $CFG->dirroot . '/blocks/ai_chat/db/install.xml';
        if (file_exists($installxmlfile)) {
            $dbman->install_from_xmldb_file($installxmlfile);
        } else {
            throw new moodle_exception('installxmlmissing', 'block_ai_chat');
        }
        // Fill with default personas.
        \block_ai_chat\local\persona::install_default_personas();

        upgrade_plugin_savepoint(true, 2025021000, 'block', 'ai_chat');
    }

    if ($oldversion < 2025121300) {
        $table = new xmldb_table('block_ai_chat_personas');
        $field = new xmldb_field('type', XMLDB_TYPE_INTEGER, '1', null, null, null, null, 'userinfo');

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        foreach ($DB->get_records('block_ai_chat_personas') as $persona) {
            $persona->type = intval($persona->userid) === 0
                ? \block_ai_chat\local\persona::TYPE_TEMPLATE
                : \block_ai_chat\local\persona::TYPE_USER;
            if (intval($persona->userid) === 0) {
                $persona->userid = get_admin()->id;
            }
            $DB->update_record('block_ai_chat_personas', $persona);
        }
        upgrade_block_savepoint(true, 2025121300, 'ai_chat');
    }

    if ($oldversion < 2025121301) {
        $table = new xmldb_table('block_ai_chat_aicontext');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('name', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $table->add_field('description', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('content', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('enabled', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        $table = new xmldb_table('block_ai_chat_aicontext_usage');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('pagetype', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $table->add_field('aicontextid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('aicontextid', XMLDB_KEY_FOREIGN, ['aicontextid'], 'block_ai_chat_aicontext', ['id']);
        $table->add_index('pagetype-aicontextid', XMLDB_INDEX_UNIQUE, ['pagetype', 'aicontextid']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_block_savepoint(true, 2025121301, 'ai_chat');
    }

    if ($oldversion < 2026012200) {
        block_ai_chat_cleanup_left_over_data();
        upgrade_block_savepoint(true, 2026012200, 'ai_chat');
    }

    if ($oldversion < 2026111503) {
        // Lokal: Wissensdateien des KI-Chats pro Kurs (KI-Kontrollzentrum) und pro Persona. Ersetzt die Zwischentabelle
        // block_ai_chat_personachunks vom 15.11.2026 (Abschnitte danach mit wissen::persona_zerlegen()/kurs_zerlegen() neu erzeugen).
        $alt = new xmldb_table('block_ai_chat_personachunks');
        if ($dbman->table_exists($alt)) {
            $dbman->drop_table($alt);
        }
        unset_config('personamaxbytes', 'block_ai_chat');
        $table = new xmldb_table('block_ai_chat_wissen');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('personaid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('contextid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('filename', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('chunkno', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('content', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
        $table->add_field('embedding', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('model', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_index('personaid-contextid', XMLDB_INDEX_NOTUNIQUE, ['personaid', 'contextid']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }
        upgrade_block_savepoint(true, 2026111503, 'ai_chat');
    }

    if ($oldversion < 2026111700) {
        // Lokal: Kurs-Wissensdateien vom Kurskontext in den Kontext des KI-Chat-Blocks verschieben (dann reisen sie mit Sicherungen).
        $fs = get_file_storage();
        $kurse = $DB->get_fieldset_sql("SELECT DISTINCT contextid FROM {files} WHERE component = 'block_ai_chat' AND filearea = 'kurswissen'");
        foreach ($kurse as $contextid) {
            $ctx = context::instance_by_id($contextid, IGNORE_MISSING);
            if (!$ctx || !$ctx instanceof context_course) {
                continue;
            }
            $ziel = \block_ai_chat\local\wissen::kursablage($ctx);
            if ($ziel->id == $ctx->id) {
                continue;
            }
            foreach ($fs->get_area_files($ctx->id, 'block_ai_chat', 'kurswissen', 0, 'id', false) as $datei) {
                if (!$fs->file_exists($ziel->id, 'block_ai_chat', 'kurswissen', 0, $datei->get_filepath(), $datei->get_filename())) {
                    $fs->create_file_from_storedfile(['contextid' => $ziel->id], $datei);
                }
            }
            $fs->delete_area_files($ctx->id, 'block_ai_chat', 'kurswissen', 0);
        }
        upgrade_block_savepoint(true, 2026111700, 'ai_chat');
    }

    if ($oldversion < 2026111702) {
        // Lokal: Quellen („📚 Aus: …“) der Antworten.
        $table = new xmldb_table('block_ai_chat_quellen');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('logid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('quellen', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_index('logid', XMLDB_INDEX_NOTUNIQUE, ['logid']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }
        upgrade_block_savepoint(true, 2026111702, 'ai_chat');
    }

    return true;
}

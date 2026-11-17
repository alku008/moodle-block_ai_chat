<?php
// Lokale Erweiterung: Quellen-Einträge löschen, deren Protokolleintrag im AI Manager gelöscht wurde (z. B. nach einer
// Datenschutz-Löschung oder dem Aufräumen des Protokolls). So bleiben keine verwaisten Zeilen zurück.

namespace block_ai_chat\task;

class quellen_aufraeumen extends \core\task\scheduled_task {

    public function get_name() {
        return get_string('task_quellen_aufraeumen', 'block_ai_chat');
    }

    public function execute() {
        global $DB;
        $DB->execute("DELETE FROM {block_ai_chat_quellen}
                       WHERE NOT EXISTS (SELECT 1 FROM {local_ai_manager_request_log} l WHERE l.id = logid AND l.deleted = 0)");
    }
}

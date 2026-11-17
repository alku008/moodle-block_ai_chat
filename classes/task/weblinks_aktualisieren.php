<?php
// Lokale Erweiterung (17.11.2026): Webseiten der Wissensquellen einmal pro Woche neu abrufen; nur wenn sich ihr Text geändert hat,
// werden die Abschnitte (mit Embeddings) neu berechnet.

namespace block_ai_chat\task;

use block_ai_chat\local\wissen;

class weblinks_aktualisieren extends \core\task\scheduled_task {

    public function get_name() {
        return get_string('task_weblinks', 'block_ai_chat');
    }

    public function execute() {
        global $DB;
        if (wissen::webmax() === 0) {
            return;
        }
        // Kurse: Adressen stehen im configdata des KI-Chat-Blocks.
        $kurse = [];
        foreach ($DB->get_records('block_instances', ['blockname' => 'ai_chat'], 'id', 'id, parentcontextid, configdata') as $bi) {
            $config = $bi->configdata ? unserialize_object(base64_decode($bi->configdata)) : null;
            if (!empty($config->weblinks)) {
                $ctx = \context::instance_by_id($bi->parentcontextid, IGNORE_MISSING);
                if ($ctx instanceof \context_course) {
                    $kurse[$ctx->id] = $ctx;
                }
            }
        }
        foreach ($kurse as $kurs) {
            mtrace('Kurs ' . $kurs->instanceid . ': ' . (wissen::web_aktualisieren(wissen::bereich_kurs($kurs)) ? 'neu zerlegt' : 'unverändert'));
        }
        // Personas: Einstellung personalinks_<id>.
        foreach ($DB->get_fieldset_select('config_plugins', 'name', "plugin = 'block_ai_chat' AND " . $DB->sql_like('name', '?'),
                ['personalinks\_%']) as $name) {
            $id = (int)substr($name, strlen('personalinks_'));
            if ($id && $DB->record_exists('block_ai_chat_personas', ['id' => $id])) {
                mtrace('Persona ' . $id . ': ' . (wissen::web_aktualisieren(wissen::bereich_persona($id)) ? 'neu zerlegt' : 'unverändert'));
            }
        }
    }
}

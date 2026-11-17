<?php
// Lokale Erweiterung (17.11.2026): Wissensdateien eines Kurses im Hintergrund neu zerlegen (nach einer Kurswiederherstellung oder
// wenn Abschnitte fehlen). Die Embeddings werden über die AIS.chat-API berechnet; das kann bei großen Dateien einige Minuten dauern.

namespace block_ai_chat\task;

class wissen_zerlegen extends \core\task\adhoc_task {

    /** Aufgabe für einen Kurs einplanen (doppelte Einträge fasst Moodle zusammen). */
    public static function planen(int $courseid): void {
        $task = new self();
        $task->set_custom_data(['courseid' => $courseid]);
        \core\task\manager::queue_adhoc_task($task, true);
    }

    public function execute() {
        $daten = $this->get_custom_data();
        $kurs = \context_course::instance((int)$daten->courseid, IGNORE_MISSING);
        if ($kurs) {
            mtrace('Wissensdateien Kurs ' . $kurs->instanceid . ': ' . \block_ai_chat\local\wissen::kurs_zerlegen($kurs) . ' Abschnitte');
        }
    }
}

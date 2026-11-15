<?php
// Lokale Erweiterung (15.11.2026, nicht vom Hersteller): Wissensdateien des KI-Chats für einen Kurs verwalten.
// Aufruf aus dem KI-Kontrollzentrum (Knopf „📚 Wissensdateien für den KI-Chat“). Die Dateien liegen im Kurskontext
// (block_ai_chat/kurswissen); bei jeder Chat-Frage in diesem Kurs gehen die passendsten Abschnitte mit (siehe classes/local/wissen.php).
require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/formslib.php');

use block_ai_chat\local\wissen;

$courseid = required_param('courseid', PARAM_INT);
$course = get_course($courseid);
require_login($course);
$kurs = context_course::instance($courseid);
require_capability('moodle/course:update', $kurs);

$url = new moodle_url('/blocks/ai_chat/kurswissen.php', ['courseid' => $courseid]);
$PAGE->set_url($url);
$PAGE->set_context($kurs);
$PAGE->set_pagelayout('incourse');
$titel = get_string('kurswissen', 'block_ai_chat');
$PAGE->set_title($titel);
$PAGE->set_heading($course->fullname);

/** Formular mit dem Dateimanager. */
class block_ai_chat_kurswissen_form extends moodleform {
    protected function definition() {
        $m = $this->_form;
        $m->addElement('hidden', 'courseid');
        $m->setType('courseid', PARAM_INT);
        $m->addElement('filemanager', 'dateien', get_string('wissensdateien', 'block_ai_chat'), null, wissen::kursoptionen());
        $m->addHelpButton('dateien', 'kurswissen', 'block_ai_chat');
        $this->add_action_buttons(true, get_string('savechanges'));
    }
}

$zurueck = new moodle_url('/course/view.php', ['id' => $courseid]);
$form = new block_ai_chat_kurswissen_form($url);
if ($form->is_cancelled()) {
    redirect($zurueck);
}
if ($daten = $form->get_data()) {
    core_php_time_limit::raise(300);
    $anzahl = wissen::kurs_speichern($kurs, (int)$daten->dateien);
    $namen = wissen::kurs_dateinamen($kurs);
    $text = $anzahl < 0 ? get_string('kurswissenunveraendert', 'block_ai_chat')
        : get_string('kurswissengespeichert', 'block_ai_chat', (object)['dateien' => count($namen), 'abschnitte' => $anzahl]);
    redirect($zurueck, $text, null, \core\output\notification::NOTIFY_SUCCESS);
}
$form->set_data(['courseid' => $courseid, 'dateien' => wissen::kurs_entwurf($kurs)]);

echo $OUTPUT->header();
echo $OUTPUT->heading('📚 ' . $titel);
echo html_writer::div(get_string('kurswisseninfo', 'block_ai_chat'),
    '', ['style' => 'margin-bottom: 16px; padding: 10px 12px; border-left: 4px solid #6d28d9; background: #f5f3ff; border-radius: 6px;']);
if (wissen::kursmaxdateien() === 0) {
    echo $OUTPUT->notification(get_string('kurswissenaus', 'block_ai_chat'), 'warning');
} else {
    $form->display();
}
echo $OUTPUT->footer();

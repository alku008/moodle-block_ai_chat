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
// Lokal (17.11.2026): Wissensdateien aus den Kursinhalten erzeugen (ersetzt die bisherigen Dateien).
if (optional_param('erzeugen', 0, PARAM_BOOL) && confirm_sesskey()) {
    core_php_time_limit::raise(300);
    $dateien = \block_ai_chat\local\kursinhalte::erzeugen($kurs);
    redirect($url, get_string('kurswissenerzeugt', 'block_ai_chat', count($dateien)), null, \core\output\notification::NOTIFY_SUCCESS);
}
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
// Kachelfarbe auch auf dieser Seite (format_tiles setzt die Variablen nur auf Kursseiten): Knöpfe und Rahmen wie im Kurs.
$optionen = course_get_format($course)->get_format_options();
if (!empty($optionen['basecolour']) && preg_match('/^#?([0-9a-f]{6})$/i', $optionen['basecolour'], $m)) {
    [$r, $g, $b] = array_map('hexdec', str_split($m[1], 2));
    echo '<style>:root { --format-tiles-colour: rgb(' . "$r,$g,$b" . '); --format-tiles-colour-elems: ' . "$r,$g,$b"
        . '; --format-tiles-colour-5: rgba(' . "$r,$g,$b" . ', 0.5); --format-tiles-colour-1: rgba(' . "$r,$g,$b" . ', 0.1); }'
        . ' #region-main .btn-primary { background-color: var(--format-tiles-colour); border-color: var(--format-tiles-colour); color: #fff; }'
        . ' #region-main .btn-primary:hover { filter: brightness(0.88); }</style>';
}
echo $OUTPUT->heading('📚 ' . $titel);
echo html_writer::div(get_string('kurswisseninfo', 'block_ai_chat'),
    '', ['style' => 'margin-bottom: 16px; padding: 10px 12px; border-left: 4px solid #6d28d9; background: #f5f3ff; border-radius: 6px;']);
if (wissen::kursmaxdateien() === 0) {
    echo $OUTPUT->notification(get_string('kurswissenaus', 'block_ai_chat'), 'warning');
} else {
    $form->display();
    // Erzeugen aus den Kursinhalten (mit Rückfrage, weil die Dateien oben ersetzt werden).
    $erzeugen = new moodle_url($url, ['erzeugen' => 1, 'sesskey' => sesskey()]);
    echo html_writer::div(
        html_writer::tag('div', '✨ ' . get_string('kurswissenerzeugen', 'block_ai_chat'), ['style' => 'font-weight: bold; margin-bottom: 4px;'])
        . html_writer::div(get_string('kurswissenerzeugeninfo', 'block_ai_chat'), '', ['style' => 'font-size: 0.9rem; color: #5b6670; margin-bottom: 8px;'])
        . html_writer::link($erzeugen, '✨ ' . get_string('kurswissenerzeugen', 'block_ai_chat'), ['class' => 'btn btn-primary',
            'style' => 'background-color: var(--format-tiles-colour, #0f6cbf); border-color: var(--format-tiles-colour, #0f6cbf); color: #fff;',
            'onclick' => 'return confirm(' . json_encode(get_string('kurswissenerzeugenfrage', 'block_ai_chat')) . ');']),
        '', ['style' => 'margin-top: 24px; padding: 12px 14px; border: 1px solid #d5dbe3; border-radius: 8px; background: #f8fafd;']);
}
echo $OUTPUT->footer();

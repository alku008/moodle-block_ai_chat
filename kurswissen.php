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
        // Lokal (19.11.2026): je Datei, ob Text gefunden wurde (⚠️ z. B. bei eingescannten PDFs – sonst fiele das niemandem auf).
        if (!empty($this->_customdata['dateistatus'])) {
            $zeilen = '';
            foreach ($this->_customdata['dateistatus'] as $name => $anzahl) {
                $zeilen .= '<li style="overflow-wrap: anywhere;">' . ($anzahl > 0 ? '✅ ' : '⚠️ ') . s($name) . ' – '
                    . ($anzahl > 0 ? get_string('weblinkok', 'block_ai_chat', $anzahl)
                        : '<span style="color: #b3261e;">' . get_string('dateiohnetext', 'block_ai_chat') . '</span>')
                    . '</li>';
            }
            $m->addElement('static', 'dateistatus', '', '<ul style="margin: 0; padding-left: 1.1rem; font-size: 0.85rem;">'
                . $zeilen . '</ul>');
        }
        if (wissen::webmax() > 0) {
            $m->addElement('textarea', 'weblinks', get_string('weblinks', 'block_ai_chat', wissen::webmax()),
                ['rows' => 5, 'cols' => 70, 'placeholder' => 'https://…']);
            $m->setType('weblinks', PARAM_RAW);
            $m->addHelpButton('weblinks', 'weblinkshilfe', 'block_ai_chat');
            if (!empty($this->_customdata['status'])) {
                $zeilen = '';
                foreach ($this->_customdata['status'] as $adresse => $anzahl) {
                    $zeilen .= '<li style="overflow-wrap: anywhere;">' . ($anzahl > 0 ? '✅ ' : '⚠️ ') . s($adresse) . ' – '
                        . ($anzahl > 0 ? get_string('weblinkok', 'block_ai_chat', $anzahl) : get_string('weblinkfehler', 'block_ai_chat'))
                        . '</li>';
                }
                $m->addElement('static', 'weblinkstatus', '', '<ul style="margin: 0; padding-left: 1.1rem; font-size: 0.85rem;">'
                    . $zeilen . '</ul>');
            }
        }
        $this->add_action_buttons(true, get_string('savechanges'));
    }
}

$zurueck = new moodle_url('/course/view.php', ['id' => $courseid]);
// Lokal (17.11.2026): Wissensdateien aus den Kursinhalten erzeugen – erst Vorschau mit Häkchen, dann erzeugen (ersetzt die Dateien).
if (optional_param('erzeugen', 0, PARAM_BOOL) && confirm_sesskey()) {
    core_php_time_limit::raise(300);
    $auswahl = optional_param_array('auswahl', [], PARAM_INT);
    $dateien = \block_ai_chat\local\kursinhalte::erzeugen($kurs, $auswahl);
    redirect($url, get_string('kurswissenerzeugt', 'block_ai_chat', count($dateien)), null, \core\output\notification::NOTIFY_SUCCESS);
}
$vorschau = optional_param('vorschau', 0, PARAM_BOOL);
$form = new block_ai_chat_kurswissen_form($url, ['status' => wissen::kurs_links($kurs),
    'dateistatus' => wissen::kurs_dateistatus($kurs)]);
if ($form->is_cancelled()) {
    redirect($zurueck);
}
if ($daten = $form->get_data()) {
    core_php_time_limit::raise(300);
    $anzahl = wissen::kurs_speichern($kurs, (int)$daten->dateien, isset($daten->weblinks) ? (string)$daten->weblinks : null);
    $namen = wissen::kurs_dateinamen($kurs);
    $text = $anzahl < 0 ? get_string('kurswissenunveraendert', 'block_ai_chat')
        : get_string('kurswissengespeichert', 'block_ai_chat', (object)['dateien' => count($namen), 'abschnitte' => $anzahl]);
    // Dateien ohne lesbaren Text: auf dieser Seite bleiben und deutlich warnen (Liste mit ⚠️ unter den Dateien).
    if ($ohnetext = wissen::ohne_text(wissen::kurs_dateistatus($kurs))) {
        redirect($url, $text . ' ' . get_string('dateienohnetext', 'block_ai_chat', s(implode(', ', $ohnetext))), null,
            \core\output\notification::NOTIFY_WARNING);
    }
    redirect($zurueck, $text, null, \core\output\notification::NOTIFY_SUCCESS);
}
$form->set_data(['courseid' => $courseid, 'dateien' => wissen::kurs_entwurf($kurs), 'weblinks' => wissen::kurs_links_text($kurs)]);

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
if ($vorschau) {
    // Vorschau: alle Inhalte nach Kacheln mit Häkchen; nicht vorausgewählte mit Grund.
    $kandidaten = \block_ai_chat\local\kursinhalte::kandidaten($kurs);
    echo html_writer::div(get_string('kurswissenvorschauinfo', 'block_ai_chat'), '',
        ['style' => 'margin-bottom: 14px; padding: 10px 12px; border-left: 4px solid var(--format-tiles-colour, #0f6cbf); background: #f8fafd; border-radius: 6px;']);
    echo '<form method="post" action="' . $url->out() . '">';
    echo '<input type="hidden" name="sesskey" value="' . sesskey() . '"><input type="hidden" name="erzeugen" value="1">';
    foreach ($kandidaten as $nr => $kachel) {
        echo '<fieldset style="border: 1px solid #d5dbe3; border-radius: 8px; padding: 8px 14px; margin: 0 0 10px 0;">'
            . '<legend style="font-size: 1rem; font-weight: bold; width: auto; padding: 0 6px; margin: 0;">' . s($kachel['titel']) . '</legend>';
        foreach ($kachel['eintraege'] as $cmid => $e) {
            $id = 'auswahl' . $cmid;
            echo '<div style="margin: 3px 0;"><input type="checkbox" name="auswahl[]" value="' . $cmid . '" id="' . $id . '"'
                . ($e['auswahl'] ? ' checked' : '') . '> <label for="' . $id . '" style="display: inline;">' . s($e['name'])
                . ' <span style="color: #6a737d; font-size: 0.85rem;">(' . s($e['art'])
                . ($e['dateien'] ? ', ' . $e['dateien'] . ' Datei(en)' : '') . ')</span>'
                . ($e['grund'] ? ' <span style="color: #a15c00; font-size: 0.85rem;">⚠️ ' . s($e['grund']) . '</span>' : '')
                . '</label></div>';
        }
        echo '</fieldset>';
    }
    echo '<button type="submit" class="btn btn-primary" onclick="return confirm('
        . s(json_encode(get_string('kurswissenerzeugenfrage', 'block_ai_chat'))) . ');">✨ '
        . get_string('kurswissenerzeugenjetzt', 'block_ai_chat') . '</button> '
        . html_writer::link($url, get_string('cancel'), ['class' => 'btn btn-secondary']) . '</form>';
    echo $OUTPUT->footer();
    exit;
}
echo html_writer::div(get_string('kurswisseninfo', 'block_ai_chat'),
    '', ['style' => 'margin-bottom: 16px; padding: 10px 12px; border-left: 4px solid #6d28d9; background: #f5f3ff; border-radius: 6px;']);
if (wissen::kursmaxdateien() === 0) {
    echo $OUTPUT->notification(get_string('kurswissenaus', 'block_ai_chat'), 'warning');
} else {
    $form->display();
    // Erzeugen aus den Kursinhalten (mit Rückfrage, weil die Dateien oben ersetzt werden).
    $erzeugen = new moodle_url($url, ['vorschau' => 1]);
    echo html_writer::div(
        html_writer::tag('div', '✨ ' . get_string('kurswissenerzeugen', 'block_ai_chat'), ['style' => 'font-weight: bold; margin-bottom: 4px;'])
        . html_writer::div(get_string('kurswissenerzeugeninfo', 'block_ai_chat'), '', ['style' => 'font-size: 0.9rem; color: #5b6670; margin-bottom: 8px;'])
        . html_writer::link($erzeugen, '✨ ' . get_string('kurswissenerzeugen', 'block_ai_chat'), ['class' => 'btn btn-primary',
            'style' => 'background-color: var(--format-tiles-colour, #0f6cbf); border-color: var(--format-tiles-colour, #0f6cbf); color: #fff;']),
        '', ['style' => 'margin-top: 24px; padding: 12px 14px; border: 1px solid #d5dbe3; border-radius: 8px; background: #f8fafd;']);
}
echo $OUTPUT->footer();

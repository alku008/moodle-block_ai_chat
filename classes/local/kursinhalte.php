<?php
// Lokale Erweiterung (17.11.2026, Wunsch der Nutzerin): Wissensdateien des KI-Chats aus den Inhalten des Kurses erzeugen.
// Ablauf: kandidaten() listet alle Inhalte nach Kacheln mit einer Vorauswahl; die Lehrkraft prüft die Liste (Häkchen) und
// erzeugen() baut aus der Auswahl höchstens „Wissensdateien pro Kurs“ Markdown-Dateien. Jede Kachel beginnt mit „# Kachel N: Name“.
// Vorausgewählt ist nur, was sicher für Lernende gedacht ist. NICHT vorausgewählt (aber in der Liste, mit Grund):
//   - verborgene Kacheln/Aktivitäten, - Aktivitäten mit irgendeiner Zugangsbedingung (Gruppe, nach Abgabe, ab Datum …),
//   - Namen, die nach Lösung oder Lehrkräften klingen (AUSSCHLUSS).
// Gar nicht aufgenommen: Tests, Umfragen, H5P (Quizfragen), Foren, Abgaben der Lernenden (nur Dateien von mod_* und unilabeltype_*,
// ohne Abgabe-/Feedback-Bereiche). Lösungen dürfen mit (18.11.2026, Wunsch der Nutzerin): Ob die KI sie nennt, legt die Persona fest.

namespace block_ai_chat\local;

class kursinhalte {

    /** Namen, die auf Lösungen oder Lehrkräfte-Inhalte hindeuten. */
    public const AUSSCHLUSS = '/(l(ö|oe)sung|musterl|erwartungshorizont|aufl(ö|oe)sung|solution|answer\s*key|🔒|🔑|lehrkr(ä|ae)ft|'
        . 'kursleitung|teacher|nur\s+f(ü|ue)r\s+lehr)/iu';

    /** Aktivitäten, deren Text gelesen wird. */
    private const ARTEN = ['label', 'page', 'assign', 'resource', 'folder', 'unilabel', 'lesson', 'book'];

    /** Dateitypen, die mitgenommen werden. */
    private const ENDUNGEN = ['ipynb', 'pdf', 'docx', 'txt', 'md', 'py', 'csv', 'html', 'htm'];

    /**
     * Alle Inhalte des Kurses mit Vorauswahl, nach Kacheln.
     *
     * @return array Kachel-Nr. => ['titel' => …, 'eintraege' => [cmid => ['name', 'art', 'dateien', 'auswahl', 'grund']]]
     */
    public static function kandidaten(\context_course $kurs): array {
        $modinfo = get_fast_modinfo($kurs->instanceid);
        $ergebnis = [];
        foreach ($modinfo->get_section_info_all() as $section) {
            if ($section->component ?? null) {
                continue;   // Unterabschnitte (Moodle 5) erscheinen in ihrer Elternkachel.
            }
            $titel = trim(get_section_name($kurs->instanceid, $section));
            $eintraege = [];
            foreach ($modinfo->sections[$section->section] ?? [] as $cmid) {
                $cm = $modinfo->get_cm($cmid);
                if ($cm->deletioninprogress || !in_array($cm->modname, self::ARTEN)) {
                    continue;
                }
                $grund = '';
                if (!$section->visible || !$cm->visible) {
                    $grund = 'verborgen';
                } else if (!empty($cm->availability) && $cm->availability !== '{"op":"&","c":[],"showc":[]}') {
                    $grund = strpos($cm->availability, '"type":"group"') !== false ? 'nur für eine Gruppe' : 'mit Zugangsbedingung';
                } else if (preg_match(self::AUSSCHLUSS, $cm->name . ' ' . $titel)) {
                    $grund = 'Name klingt nach Lösung oder Lehrkräften';
                }
                $eintraege[$cm->id] = ['name' => $cm->get_formatted_name(), 'art' => get_string('modulename', $cm->modname),
                    'dateien' => count(self::dateien($cm)), 'auswahl' => $grund === '', 'grund' => $grund];
            }
            if ($eintraege) {
                $ergebnis[$section->section] = ['titel' => $titel, 'eintraege' => $eintraege];
            }
        }
        return $ergebnis;
    }

    /**
     * Wissensdateien aus den gewählten Aktivitäten erzeugen und speichern (ersetzt die bisherigen); Embeddings im Hintergrund.
     *
     * @param int[] $auswahl cmids
     * @return array [Dateiname => Zeichen]
     */
    public static function erzeugen(\context_course $kurs, array $auswahl): array {
        $auswahl = array_flip(array_map('intval', $auswahl));
        $modinfo = get_fast_modinfo($kurs->instanceid);
        $gesehen = [];
        $teile = [];
        foreach (self::kandidaten($kurs) as $nr => $kachel) {
            $text = '';
            foreach (array_keys($kachel['eintraege']) as $cmid) {
                if (isset($auswahl[$cmid])) {
                    $text .= self::aktivitaet($modinfo->get_cm($cmid), $gesehen);
                }
            }
            if (trim($text) !== '') {
                $teile[$nr] = '# Kachel ' . $nr . ': ' . $kachel['titel'] . "\n\n" . trim($text);
            }
        }
        $max = max(1, wissen::kursmaxdateien());
        $ziel = max(1, (int)ceil(array_sum(array_map('strlen', $teile)) / $max));
        $dateien = [];
        $aktuell = '';
        $von = $bis = null;
        foreach ($teile as $nr => $text) {
            if ($aktuell !== '' && strlen($aktuell) + strlen($text) > $ziel * 1.15 && count($dateien) < $max - 1) {
                $dateien[sprintf('%d_kursinhalte_kachel_%02d-%02d.md', count($dateien) + 1, $von, $bis)] = $aktuell;
                $aktuell = '';
                $von = null;
            }
            $von = $von ?? $nr;
            $bis = $nr;
            $aktuell .= $text . "\n\n";
        }
        if (trim($aktuell) !== '') {
            $dateien[sprintf('%d_kursinhalte_kachel_%02d-%02d.md', count($dateien) + 1, $von, $bis)] = $aktuell;
        }
        $fs = get_file_storage();
        $ablage = wissen::kursablage($kurs);
        $fs->delete_area_files($ablage->id, 'block_ai_chat', wissen::KURSAREA, 0);
        foreach ($dateien as $name => $inhalt) {
            $fs->create_file_from_string(['contextid' => $ablage->id, 'component' => 'block_ai_chat', 'filearea' => wissen::KURSAREA,
                'itemid' => 0, 'filepath' => '/', 'filename' => $name], $inhalt);
        }
        \block_ai_chat\task\wissen_zerlegen::planen((int)$kurs->instanceid);
        return array_map('strlen', $dateien);
    }

    /**
     * Unterrichtsdateien einer Aktivität (nur Bereiche der Aktivität selbst bzw. von Unilabel – keine Abgaben, kein Feedback).
     *
     * @return \stored_file[]
     */
    private static function dateien(\cm_info $cm): array {
        global $DB;
        $ctx = \context_module::instance($cm->id);
        $sql = "SELECT id FROM {files} WHERE contextid = ? AND filename <> '.'
                   AND (component = ? OR component LIKE 'unilabeltype\\_%')
                   AND filearea NOT IN ('submission_files', 'submission_onlinetext', 'feedback_files', 'combined', 'download')";
        $fs = get_file_storage();
        $ergebnis = [];
        foreach ($DB->get_fieldset_sql($sql, [$ctx->id, 'mod_' . $cm->modname]) as $id) {
            $d = $fs->get_file_by_id($id);
            if ($d && in_array(strtolower(pathinfo($d->get_filename(), PATHINFO_EXTENSION)), self::ENDUNGEN)) {
                $ergebnis[] = $d;
            }
        }
        return $ergebnis;
    }

    /** Text einer Aktivität samt ihrer Dateien. */
    private static function aktivitaet(\cm_info $cm, array &$gesehen): string {
        global $DB;
        $html = '';
        switch ($cm->modname) {
            case 'label':
                $html = $DB->get_field('label', 'intro', ['id' => $cm->instance]);
                break;
            case 'page':
                $html = $DB->get_field('page', 'content', ['id' => $cm->instance]);
                break;
            case 'assign':
            case 'resource':
            case 'folder':
                $html = $DB->get_field($cm->modname, 'intro', ['id' => $cm->instance]);
                break;
            case 'unilabel':
                $html = (string)$DB->get_field('unilabel', 'intro', ['id' => $cm->instance]);
                $dbman = $DB->get_manager();
                foreach (!$dbman->table_exists('unilabeltype_accordion_seg') ? [] : $DB->get_records_sql("SELECT s.heading, s.content FROM {unilabeltype_accordion_seg} s
                        JOIN {unilabeltype_accordion} a ON a.id = s.accordionid WHERE a.unilabelid = ? ORDER BY s.sortorder",
                        [$cm->instance]) as $s) {
                    $html .= '<h3>' . $s->heading . '</h3>' . $s->content;
                }
                foreach (!$dbman->table_exists('unilabeltype_grid_tile') ? [] : $DB->get_records_sql("SELECT t.title, t.content FROM {unilabeltype_grid_tile} t
                        JOIN {unilabeltype_grid} g ON g.id = t.gridid WHERE g.unilabelid = ? ORDER BY t.sortorder",
                        [$cm->instance]) as $t) {
                    $html .= '<h3>' . $t->title . '</h3>' . $t->content;
                }
                break;
            case 'lesson':
                foreach ($DB->get_records('lesson_pages', ['lessonid' => $cm->instance], 'id', 'title, contents') as $p) {
                    $html .= '<h3>' . $p->title . '</h3>' . $p->contents;
                }
                break;
            case 'book':
                if ($DB->get_manager()->table_exists('book_chapters')) {
                    foreach ($DB->get_records('book_chapters', ['bookid' => $cm->instance, 'hidden' => 0], 'pagenum', 'title, content') as $c) {
                        $html .= '<h3>' . $c->title . '</h3>' . $c->content;
                    }
                }
                break;
        }
        $text = '';
        $inhalt = trim(html_to_text((string)$html, 0, false));
        if ($inhalt !== '') {
            $text .= ($cm->modname === 'label' ? '' : '## ' . $cm->name . "\n\n") . $inhalt . "\n\n";
        }
        foreach (self::dateien($cm) as $datei) {
            if (isset($gesehen[$datei->get_contenthash()])) {
                continue;
            }
            $gesehen[$datei->get_contenthash()] = true;
            $dateitext = trim(wissen::text($datei));
            if ($dateitext !== '') {
                $text .= '## Datei: ' . $datei->get_filename() . "\n\n" . $dateitext . "\n\n";
            }
        }
        return $text;
    }
}

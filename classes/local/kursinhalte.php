<?php
// Lokale Erweiterung (17.11.2026, Wunsch der Nutzerin): Wissensdateien des KI-Chats aus den Inhalten des Kurses erzeugen.
// Nimmt sichtbare Abschnitte und Aktivitäten (Textfelder, Seiten, Aufgabenbeschreibungen) und die Notebooks (.ipynb) darin bzw. in
// Ordnern, ohne Lösungen und Lehrkräfte-Inhalte: ausgelassen werden verborgene Abschnitte/Aktivitäten, Aktivitäten mit
// Gruppen-Bedingung (z. B. nur Gruppe „Lehrkräfte“) und alles, dessen Name nach Lösung/Lehrkraft klingt. Tests und Feedbacks
// bleiben draußen (keine Testfragen im Chat). Ergebnis: höchstens „Wissensdateien pro Kurs“ Markdown-Dateien, nach Abschnitten
// aufgeteilt; jede Kachel beginnt mit „# Kachel N: Name“, damit die Suche den Abschnitt kennt.

namespace block_ai_chat\local;

class kursinhalte {

    /** Namen, die auf Lösungen oder Lehrkräfte-Inhalte hindeuten. */
    private const AUSSCHLUSS = '/(l(ö|oe)sung|musterl|solution|🔒|🔑|lehrkr(ä|ae)ft|kursleitung|teacher)/iu';

    /**
     * Wissensdateien erzeugen und speichern (ersetzt die bisherigen); die Abschnitte berechnet eine Hintergrundaufgabe.
     *
     * @return array [Dateinamen => Zeichen]
     */
    public static function erzeugen(\context_course $kurs): array {
        $teile = self::sammeln($kurs);
        $max = max(1, wissen::kursmaxdateien());
        // Kacheln der Reihe nach auf höchstens $max Dateien verteilen (etwa gleich groß).
        $gesamt = array_sum(array_map('strlen', $teile));
        $ziel = max(1, (int)ceil($gesamt / $max));
        $dateien = [];
        $aktuell = '';
        $von = $bis = null;
        foreach ($teile as $nr => $text) {
            if ($aktuell !== '' && strlen($aktuell) + strlen($text) > $ziel * 1.15 && count($dateien) < $max - 1) {
                $dateien[self::name(count($dateien) + 1, $von, $bis)] = $aktuell;
                $aktuell = '';
                $von = null;
            }
            $von = $von ?? $nr;
            $bis = $nr;
            $aktuell .= $text . "\n\n";
        }
        if (trim($aktuell) !== '') {
            $dateien[self::name(count($dateien) + 1, $von, $bis)] = $aktuell;
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

    private static function name(int $nr, ?int $von, ?int $bis): string {
        return sprintf('%d_kursinhalte_kachel_%02d-%02d.md', $nr, $von, $bis);
    }

    /**
     * Text je Kachel (Abschnittsnummer => Markdown).
     *
     * @return array
     */
    public static function sammeln(\context_course $kurs): array {
        $modinfo = get_fast_modinfo($kurs->instanceid);
        $fs = get_file_storage();
        $gesehen = [];
        $teile = [];
        foreach ($modinfo->get_section_info_all() as $section) {
            if (!$section->visible || ($section->component ?? null)) {
                continue;
            }
            $titel = trim(get_section_name($kurs->instanceid, $section));
            if (preg_match(self::AUSSCHLUSS, $titel)) {
                continue;
            }
            $text = '';
            foreach ($modinfo->sections[$section->section] ?? [] as $cmid) {
                $cm = $modinfo->get_cm($cmid);
                if (!$cm->visible || $cm->deletioninprogress || !in_array($cm->modname, ['label', 'page', 'assign', 'folder'])) {
                    continue;
                }
                if ($cm->availability && strpos($cm->availability, '"type":"group"') !== false) {
                    continue;
                }
                if (preg_match(self::AUSSCHLUSS, $cm->name)) {
                    continue;
                }
                $text .= self::aktivitaet($cm, $fs, $gesehen);
            }
            if (trim(strip_tags($section->summary ?? '')) !== '') {
                $text = trim(html_to_text($section->summary, 0, false)) . "\n\n" . $text;
            }
            if (trim($text) !== '') {
                $teile[$section->section] = '# Kachel ' . $section->section . ': ' . $titel . "\n\n" . trim($text);
            }
        }
        return $teile;
    }

    /** Text einer Aktivität samt ihrer Notebooks. */
    private static function aktivitaet(\cm_info $cm, \file_storage $fs, array &$gesehen): string {
        global $DB;
        $ctx = \context_module::instance($cm->id);
        $text = '';
        $bereiche = [];
        switch ($cm->modname) {
            case 'label':
                $html = $DB->get_field('label', 'intro', ['id' => $cm->instance]);
                $bereiche = [['mod_label', 'intro']];
                break;
            case 'page':
                $html = $DB->get_field('page', 'content', ['id' => $cm->instance]);
                $bereiche = [['mod_page', 'content']];
                break;
            case 'assign':
                $html = $DB->get_field('assign', 'intro', ['id' => $cm->instance]);
                $bereiche = [['mod_assign', 'intro'], ['mod_assign', 'introattachment']];
                break;
            default:   // Ordner: nur die Notebooks
                $html = '';
                $bereiche = [['mod_folder', 'content']];
        }
        $inhalt = trim(html_to_text((string)$html, 0, false));
        if ($inhalt !== '') {
            $text .= ($cm->modname === 'label' ? '' : '## ' . $cm->name . "\n\n") . $inhalt . "\n\n";
        }
        foreach ($bereiche as [$komponente, $bereich]) {
            foreach ($fs->get_area_files($ctx->id, $komponente, $bereich, false, 'filename', false) as $datei) {
                $n = $datei->get_filename();
                if (strtolower(pathinfo($n, PATHINFO_EXTENSION)) !== 'ipynb' || preg_match(self::AUSSCHLUSS, $n)
                        || isset($gesehen[$datei->get_contenthash()])) {
                    continue;
                }
                $gesehen[$datei->get_contenthash()] = true;
                $text .= '## Notebook: ' . $n . "\n\n" . wissen::text($datei) . "\n\n";
            }
        }
        return $text;
    }
}

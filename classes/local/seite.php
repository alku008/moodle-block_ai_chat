<?php
// Lokale Erweiterung (17.11.2026, Wunsch der Nutzerin): Auf welcher Kursseite stellt die Person ihre Frage?
// Der Chat läuft über einen Webservice; die aufrufende Seite steht im Referer (gleiche Website). Daraus werden Aktivität und Kachel
// ermittelt – sie gehen als Hinweis an die KI und verbessern die Suche in den Wissensdateien.

namespace block_ai_chat\local;

class seite {

    /**
     * Seite der Frage, z. B. ['aktivitaet' => '📤 Abgabe: deine Notebooks – Listen', 'kachel' => 'Listen (🕒 90 min, 🏅)'].
     *
     * @return array|null
     */
    public static function aus_referer(?\context $kurs): ?array {
        global $CFG;
        $referer = $_SERVER['HTTP_REFERER'] ?? '';
        if (!$kurs || $referer === '' || strpos($referer, $CFG->wwwroot) !== 0) {
            return null;
        }
        $url = parse_url($referer);
        parse_str($url['query'] ?? '', $p);
        $pfad = $url['path'] ?? '';
        $courseid = (int)$kurs->instanceid;
        try {
            $modinfo = get_fast_modinfo($courseid);
            if (preg_match('#/mod/[a-z0-9_]+/view\.php$#', $pfad) && !empty($p['id'])) {
                $cm = $modinfo->get_cm((int)$p['id']);
                if (!$cm->uservisible) {
                    return null;
                }
                $section = $modinfo->get_section_info($cm->sectionnum);
                return ['aktivitaet' => $cm->get_formatted_name(), 'kachel' => get_section_name($courseid, $section)];
            }
            if (preg_match('#/course/section\.php$#', $pfad) && !empty($p['id'])) {
                $section = $modinfo->get_section_info_by_id((int)$p['id']);
                return $section ? ['aktivitaet' => '', 'kachel' => get_section_name($courseid, $section)] : null;
            }
            if (preg_match('#/course/view\.php$#', $pfad) && isset($p['section'])) {
                $section = $modinfo->get_section_info((int)$p['section']);
                return $section ? ['aktivitaet' => '', 'kachel' => get_section_name($courseid, $section)] : null;
            }
        } catch (\Throwable $e) {
            return null;
        }
        return null;
    }

    /** Hinweis für die KI. */
    public static function hinweis(?array $seite): string {
        if (!$seite) {
            return '';
        }
        $wo = $seite['aktivitaet'] !== '' ? '„' . $seite['aktivitaet'] . '“ in der Kachel „' . $seite['kachel'] . '“'
            : 'die Kachel „' . $seite['kachel'] . '“';
        return "\n\n## Wo die Person gerade ist\nSie schreibt von der Kursseite " . $wo
            . '. Beziehe dich darauf, wenn die Frage dazu passt (z. B. „diese Aufgabe“).';
    }
}

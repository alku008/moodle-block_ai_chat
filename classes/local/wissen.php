<?php
// Lokale Erweiterung (15.11.2026, nicht vom Hersteller): Wissensdateien für den KI-Chat (pro Kurs und pro Persona).
// Zwei Orte: im KI-Kontrollzentrum pro Kurs (Knopf „📚 Dateien hochladen und verwalten“ → kurswissen.php) und im Persona-Formular
// pro Persona (gilt in allen Kursen mit dieser Persona); je bis zu 5 Dateien (txt, md, ipynb, py, html, csv, docx, pdf). Moodle zerlegt sie in Abschnitte und berechnet für jeden Abschnitt ein Embedding über die
// AIS.chat-API (/v1/embeddings, Modell in block_ai_chat/embeddingmodel). Bei jeder Chat-Frage im Kurs gehen die passendsten Abschnitte
// mit (Kosinus-Ähnlichkeit + Wortvergleich; ohne Embedding-Modell nur Wortvergleich) – mit und ohne Persona.
// Patch: ki-plugins-bayern/patches/block_ai_chat_lokal.diff.

namespace block_ai_chat\local;

class wissen {

    /** Dateibereich im Kurskontext (itemid 0). */
    public const KURSAREA = 'kurswissen';

    /** Tabelle mit den Abschnitten. */
    private const TABELLE = 'block_ai_chat_wissen';

    /** Erlaubte Dateitypen. PDF liest pdftotext (falls auf dem Server) oder der mitgelieferte PdfParser (thirdparty/pdfparser). */
    public const TYPEN = ['.txt', '.md', '.ipynb', '.py', '.html', '.htm', '.csv', '.docx', '.pdf'];

    /** Zielgröße eines Abschnitts in Zeichen. */
    private const ABSCHNITT = 1200;

    /** So viele Abschnitte gehen höchstens mit jeder Frage mit (und höchstens MAXZEICHEN Zeichen). */
    private const TREFFER = 4;
    private const MAXZEICHEN = 5000;

    private static function pdftotext(): string {
        global $CFG;
        $programm = !empty($CFG->pathtopdftotext) ? $CFG->pathtopdftotext : trim((string)@shell_exec('command -v pdftotext'));
        return ($programm !== '' && is_executable($programm)) ? $programm : '';
    }

    // ---- Zwei Orte für Wissensdateien ----
    // Kurs (KI-Kontrollzentrum → kurswissen.php): Bereich „kurswissen“ im Kurskontext, gilt nur in diesem Kurs, für jede Persona.
    // Persona (Persona-Formular im KI-Chat): Bereich „personafiles“ im Systemkontext (itemid = Persona-id), gilt in allen Kursen,
    // in denen die Persona gewählt ist. Bei einer Frage gehen die besten Abschnitte aus beiden zusammen mit.

    /** Dateibereich der Personas (Systemkontext, itemid = Persona-id). */
    public const PERSONAAREA = 'personafiles';

    /** Höchstens so viele Dateien pro Kurs (Einstellung kursmaxfiles, Standard 5, 0 = aus). */
    public static function kursmaxdateien(): int {
        $wert = get_config('block_ai_chat', 'kursmaxfiles');
        return $wert === false || $wert === '' ? 5 : max(0, (int)$wert);
    }

    /** Höchstens so viele Dateien pro Persona (Einstellung personamaxfiles, Standard 5, 0 = aus). */
    public static function personamaxdateien(): int {
        $wert = get_config('block_ai_chat', 'personamaxfiles');
        return $wert === false || $wert === '' ? 5 : max(0, (int)$wert);
    }

    private static function optionen(int $max): array {
        $mb = (int)(get_config('block_ai_chat', 'wissenmaxbytes') ?: 5);
        return ['subdirs' => 0, 'maxfiles' => max(1, $max), 'maxbytes' => $mb * 1024 * 1024, 'accepted_types' => self::TYPEN];
    }

    public static function kursoptionen(): array {
        return self::optionen(self::kursmaxdateien());
    }

    public static function personaoptionen(): array {
        return self::optionen(self::personamaxdateien());
    }

    /** Bereich als [contextid, filearea, itemid, Spalten der Tabelle]. */
    private static function bereich(?\context_course $kurs, int $personaid = 0): array {
        return $kurs ? [$kurs->id, self::KURSAREA, 0, ['personaid' => 0, 'contextid' => $kurs->id]]
            : [\context_system::instance()->id, self::PERSONAAREA, $personaid, ['personaid' => $personaid, 'contextid' => 0]];
    }

    private static function dateien(array $b): array {
        return get_file_storage()->get_area_files($b[0], 'block_ai_chat', $b[1], $b[2], 'filename', false);
    }

    private static function fingerabdruck(array $b): string {
        return implode('|', array_map(fn($d) => $d->get_filename() . ':' . $d->get_contenthash(), self::dateien($b)));
    }

    private static function entwurf(array $b, array $optionen): int {
        $draftitemid = 0;
        file_prepare_draft_area($draftitemid, $b[0], 'block_ai_chat', $b[1], $b[2], $optionen);
        return $draftitemid;
    }

    private static function speichern(array $b, int $draftitemid, array $optionen): int {
        $vorher = self::fingerabdruck($b);
        file_save_draft_area_files($draftitemid, $b[0], 'block_ai_chat', $b[1], $b[2], $optionen);
        return self::fingerabdruck($b) !== $vorher ? self::zerlegen($b) : -1;
    }

    // Kurs
    public static function kurs_entwurf(\context_course $kurs): int {
        return self::entwurf(self::bereich($kurs), self::kursoptionen());
    }

    /** Speichern und neu zerlegen; gibt die Zahl der Abschnitte zurück (-1 = unverändert). */
    public static function kurs_speichern(\context_course $kurs, int $draftitemid): int {
        return self::speichern(self::bereich($kurs), $draftitemid, self::kursoptionen());
    }

    public static function kurs_dateinamen(\context_course $kurs): array {
        return array_values(array_map(fn($d) => $d->get_filename(), self::dateien(self::bereich($kurs))));
    }

    public static function kurs_zerlegen(\context_course $kurs): int {
        return self::zerlegen(self::bereich($kurs));
    }

    // Persona
    public static function persona_entwurf(int $personaid): int {
        return self::entwurf(self::bereich(null, $personaid), self::personaoptionen());
    }

    public static function persona_speichern(int $personaid, int $draftitemid): int {
        return self::speichern(self::bereich(null, $personaid), $draftitemid, self::personaoptionen());
    }

    public static function persona_zerlegen(int $personaid): int {
        return self::zerlegen(self::bereich(null, $personaid));
    }

    /** Dateien und Abschnitte einer Persona auf eine andere übertragen (Duplizieren). */
    public static function persona_kopieren(int $vonid, int $nachid): void {
        global $DB;
        $fs = get_file_storage();
        foreach (self::dateien(self::bereich(null, $vonid)) as $datei) {
            $fs->create_file_from_storedfile(['itemid' => $nachid], $datei);
        }
        foreach ($DB->get_records(self::TABELLE, ['personaid' => $vonid, 'contextid' => 0]) as $r) {
            unset($r->id);
            $r->personaid = $nachid;
            $DB->insert_record(self::TABELLE, $r);
        }
    }

    /** Dateien und Abschnitte einer gelöschten Persona entfernen. */
    public static function persona_loeschen(int $personaid): void {
        global $DB;
        $b = self::bereich(null, $personaid);
        get_file_storage()->delete_area_files($b[0], 'block_ai_chat', $b[1], $b[2]);
        $DB->delete_records(self::TABELLE, ['personaid' => $personaid, 'contextid' => 0]);
    }

    /** Dateien eines Bereichs zerlegen, Embeddings berechnen, Abschnitte speichern. */
    private static function zerlegen(array $b): int {
        global $DB;
        $DB->delete_records(self::TABELLE, $b[3]);
        $abschnitte = [];
        foreach (self::dateien($b) as $datei) {
            $nr = 0;
            foreach (self::abschnitte(self::text($datei)) as $text) {
                $abschnitte[] = (object)($b[3] + ['filename' => $datei->get_filename(), 'chunkno' => $nr++,
                    'content' => $text, 'embedding' => null, 'model' => '', 'timecreated' => time()]);
            }
        }
        if (!$abschnitte) {
            return 0;
        }
        // Embeddings in Paketen zu 16 Abschnitten; schlägt das fehl, bleibt die Suche per Wortvergleich.
        foreach (array_chunk(array_keys($abschnitte), 16) as $paket) {
            $texte = array_map(fn($i) => $abschnitte[$i]->filename . "\n" . $abschnitte[$i]->content, $paket);
            $vektoren = self::embeddings($texte);
            if ($vektoren === null) {
                break;
            }
            foreach ($paket as $j => $i) {
                $abschnitte[$i]->embedding = json_encode(array_map(fn($x) => round($x, 6), $vektoren[$j]));
                $abschnitte[$i]->model = self::modell();
            }
        }
        $DB->insert_records(self::TABELLE, $abschnitte);
        return count($abschnitte);
    }

    /** Text einer Datei je nach Typ. */
    public static function text(\stored_file $datei): string {
        $endung = strtolower(pathinfo($datei->get_filename(), PATHINFO_EXTENSION));
        $inhalt = $datei->get_content();
        switch ($endung) {
            case 'ipynb':
                $nb = json_decode($inhalt, true);
                if (!is_array($nb) || empty($nb['cells'])) {
                    return '';
                }
                $teile = [];
                foreach ($nb['cells'] as $zelle) {
                    $quelle = is_array($zelle['source'] ?? '') ? implode('', $zelle['source']) : (string)($zelle['source'] ?? '');
                    if (trim($quelle) === '') {
                        continue;
                    }
                    $teile[] = ($zelle['cell_type'] ?? '') === 'code' ? "```python\n" . trim($quelle) . "\n```" : trim($quelle);
                }
                return implode("\n\n", $teile);
            case 'html':
            case 'htm':
                return html_to_text($inhalt, 0, false);
            case 'docx':
                $tmp = $datei->copy_content_to_temp();
                $zip = new \ZipArchive();
                $text = '';
                if ($tmp && $zip->open($tmp) === true) {
                    $xml = (string)$zip->getFromName('word/document.xml');
                    $zip->close();
                    $xml = preg_replace('~</w:p>~', "\n\n", $xml);
                    $text = html_entity_decode(strip_tags($xml), ENT_QUOTES | ENT_XML1, 'UTF-8');
                }
                @unlink($tmp);
                return $text;
            case 'pdf':
                return self::pdftext($datei);
            default:
                return \core_text::convert($inhalt, mb_detect_encoding($inhalt, 'UTF-8, ISO-8859-1', true) ?: 'UTF-8', 'UTF-8');
        }
    }

    /** PDF-Text: mit pdftotext, falls auf dem Server vorhanden (sonst leer). */
    private static function pdftext(\stored_file $datei): string {
        $programm = self::pdftotext();
        if ($programm !== '') {
            $tmp = $datei->copy_content_to_temp();
            $text = (string)@shell_exec(escapeshellarg($programm) . ' -q -enc UTF-8 ' . escapeshellarg($tmp) . ' -');
            @unlink($tmp);
            if (trim($text) !== '') {
                return $text;
            }
        }
        // Mitgelieferter PDF-Leser in reinem PHP (smalot/pdfparser 2.13.1, LGPL-3.0), Seite für Seite.
        static $geladen = false;
        $geladen = $geladen || spl_autoload_register(function ($klasse) {
            if (strpos($klasse, 'Smalot\\PdfParser\\') === 0) {
                $datei = __DIR__ . '/../../thirdparty/pdfparser/src/' . str_replace('\\', '/', $klasse) . '.php';
                if (is_readable($datei)) {
                    require_once($datei);
                }
            }
        });
        try {
            $konfig = new \Smalot\PdfParser\Config();
            $konfig->setRetainImageContent(false);
            $pdf = (new \Smalot\PdfParser\Parser([], $konfig))->parseContent($datei->get_content());
            $seiten = [];
            foreach ($pdf->getPages() as $nr => $seite) {
                $seiten[] = '# Seite ' . ($nr + 1) . "\n\n" . trim($seite->getText());
            }
            return implode("\n\n", $seiten);
        } catch (\Throwable $e) {
            debugging('block_ai_chat Wissensdateien: PDF nicht lesbar (' . $e->getMessage() . ')', DEBUG_DEVELOPER);
            return '';
        }
    }

    /** Text in Abschnitte von etwa ABSCHNITT Zeichen teilen, an Absatzgrenzen; Überschriften gehen in den nächsten Abschnitt mit. */
    public static function abschnitte(string $text): array {
        $text = trim(preg_replace("/\r\n?/", "\n", $text));
        if ($text === '') {
            return [];
        }
        $absaetze = preg_split("/\n\s*\n/", $text);
        $ergebnis = [];
        $aktuell = '';
        $ueberschrift = '';
        foreach ($absaetze as $absatz) {
            $absatz = trim($absatz);
            if ($absatz === '') {
                continue;
            }
            if (preg_match('/^#{1,6}\s/', $absatz)) {
                $ueberschrift = strtok($absatz, "\n");
            }
            // Sehr lange Absätze hart teilen.
            foreach (str_split($absatz, self::ABSCHNITT * 2) as $stueck) {
                if ($aktuell !== '' && \core_text::strlen($aktuell) + \core_text::strlen($stueck) > self::ABSCHNITT) {
                    $ergebnis[] = $aktuell;
                    $aktuell = ($ueberschrift !== '' && strpos($stueck, $ueberschrift) !== 0) ? $ueberschrift . " (Fortsetzung)\n\n" : '';
                }
                $aktuell .= ($aktuell === '' || substr($aktuell, -2) === "\n\n" ? '' : "\n\n") . $stueck;
            }
        }
        if (trim($aktuell) !== '') {
            $ergebnis[] = $aktuell;
        }
        return $ergebnis;
    }

    /** Name des Embedding-Modells bei AIS.chat. */
    public static function modell(): string {
        return trim((string)get_config('block_ai_chat', 'embeddingmodel')) ?: 'BAAI/bge-m3';
    }

    /**
     * Embeddings über die AIS.chat-API (OpenAI-kompatibel, Telli-Anschluss des AI Managers: Basis-URL und globaler Schlüssel).
     *
     * @return array|null Liste von Vektoren oder null, wenn kein Embedding-Modell erreichbar ist
     */
    public static function embeddings(array $texte): ?array {
        global $CFG;
        require_once($CFG->libdir . '/filelib.php');
        $basis = rtrim((string)get_config('aitool_telli', 'baseurl'), '/');
        $schluessel = (string)get_config('aitool_telli', 'globalapikey');
        if ($basis === '' || $schluessel === '' || !$texte) {
            return null;
        }
        $curl = new \curl();
        $curl->setHeader(['Authorization: Bearer ' . $schluessel, 'Content-Type: application/json']);
        $antwort = $curl->post($basis . '/v1/embeddings', json_encode(['model' => self::modell(), 'input' => array_values($texte)]),
            ['CURLOPT_TIMEOUT' => 60]);
        $daten = json_decode((string)$antwort, true);
        if (empty($daten['data']) || count($daten['data']) !== count($texte)) {
            debugging('block_ai_chat Wissensdateien: keine Embeddings (' . substr((string)$antwort, 0, 200) . ')', DEBUG_DEVELOPER);
            return null;
        }
        usort($daten['data'], fn($a, $b) => ($a['index'] ?? 0) <=> ($b['index'] ?? 0));
        return array_map(fn($d) => $d['embedding'], $daten['data']);
    }

    /**
     * Passende Auszüge zur Frage als Text für die Persona-Anweisung (leer, wenn die Persona keine Wissensdateien hat).
     */
    public static function kontext(string $frage, int $personaid, int $kurscontextid): string {
        global $DB;
        // Wissensdateien der gewählten Persona (alle Kurse) und des Kurses (nur dieser Kurs).
        $abschnitte = $DB->get_records_select(self::TABELLE,
            '(personaid > 0 AND personaid = ? AND contextid = 0) OR (personaid = 0 AND contextid > 0 AND contextid = ?)',
            [$personaid, $kurscontextid], 'filename, chunkno');
        if (!$abschnitte) {
            return '';
        }
        $punkte = [];
        $mitvektor = array_filter($abschnitte, fn($a) => !empty($a->embedding));
        $fragevektor = $mitvektor ? (self::embeddings([$frage])[0] ?? null) : null;
        foreach ($abschnitte as $id => $a) {
            // Gemischte Suche: Bedeutung (Embedding) + Wortvergleich + Bonus, wenn eine genannte Aufgabe genau getroffen ist.
            $text = $a->filename . ' ' . $a->content;
            $wort = self::wortpunkte($frage, $text) + self::aufgabenbonus($frage, $a->content);
            if ($fragevektor && !empty($a->embedding)) {
                $punkte[$id] = self::kosinus($fragevektor, json_decode($a->embedding, true)) + 0.3 * $wort;
            } else {
                $punkte[$id] = $wort - ($fragevektor ? 1 : 0);
            }
        }
        arsort($punkte);
        $auszuege = [];
        $zeichen = 0;
        foreach (array_slice(array_keys($punkte), 0, self::TREFFER) as $id) {
            if ($punkte[$id] <= 0 && $auszuege) {
                break;
            }
            $a = $abschnitte[$id];
            if ($zeichen + \core_text::strlen($a->content) > self::MAXZEICHEN && $auszuege) {
                break;
            }
            $zeichen += \core_text::strlen($a->content);
            $auszuege[] = '--- Aus der Datei „' . $a->filename . '“ ---' . "\n" . $a->content;
        }
        return "\n\n## Wissensdateien\nDie Lehrkraft hat dir Wissensdateien gegeben. Hier sind die Auszüge, die am besten zur aktuellen Frage passen. "
            . "Nutze sie für deine Antwort und nenne bei Bedarf die Datei bzw. Aufgabe. Wenn die Auszüge nicht zur Frage passen, sag das "
            . "und frag nach.\n\n" . implode("\n\n", $auszuege);
    }

    private static function kosinus(array $a, ?array $b): float {
        if (!$b || count($a) !== count($b)) {
            return 0.0;
        }
        $skalar = $na = $nb = 0.0;
        foreach ($a as $i => $x) {
            $skalar += $x * $b[$i];
            $na += $x * $x;
            $nb += $b[$i] * $b[$i];
        }
        return ($na && $nb) ? $skalar / sqrt($na * $nb) : 0.0;
    }

    /** Bonus, wenn die Frage „Aufgabe 3“ o. Ä. nennt und der Abschnitt genau diese Aufgabe enthält (Embeddings erkennen Nummern schlecht). */
    private static function aufgabenbonus(string $frage, string $text): float {
        if (!preg_match_all('/\b(aufgabe|teil|schritt|stufe|lernpfad)\s*(\d+[a-z]?)\b/iu', $frage, $m, PREG_SET_ORDER)) {
            return 0.0;
        }
        foreach ($m as $x) {
            if (preg_match('/' . preg_quote($x[1], '/') . '\s*' . preg_quote($x[2], '/') . '\b/iu', $text)) {
                return 1.0;
            }
        }
        return 0.0;
    }

    /** Ersatz ohne Embedding-Modell: Anteil der Frage-Wörter (ab 3 Zeichen), die im Abschnitt vorkommen. */
    private static function wortpunkte(string $frage, string $text): float {
        $woerter = array_unique(array_filter(preg_split('/[^\p{L}\p{N}_]+/u', \core_text::strtolower($frage)),
            fn($w) => \core_text::strlen($w) >= 3 || ctype_digit($w)));
        if (!$woerter) {
            return 0.0;
        }
        $text = \core_text::strtolower($text);
        $treffer = 0;
        foreach ($woerter as $w) {
            if (strpos($text, $w) !== false) {
                $treffer++;
            }
        }
        return $treffer / count($woerter);
    }
}

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

    /** Titel der zuletzt mitgeschickten Abschnitte (für „📚 Aus: …“ unter der Antwort, siehe manager::request_ai). */
    public static array $quellen = [];

    /** Tabelle mit den Abschnitten. */
    private const TABELLE = 'block_ai_chat_wissen';

    /** Erlaubte Dateitypen. PDF liest der mitgelieferte PdfParser (thirdparty/pdfparser, reines PHP, kein Programm auf dem Server nötig). */
    public const TYPEN = ['.txt', '.md', '.ipynb', '.py', '.html', '.htm', '.csv', '.docx', '.pdf'];

    /** Zielgröße eines Abschnitts in Zeichen. */
    private const ABSCHNITT = 1200;

    /** So viele Abschnitte gehen höchstens mit jeder Frage mit (und höchstens MAXZEICHEN Zeichen). */
    private const TREFFER = 4;
    private const MAXZEICHEN = 5000;

    /** Höchstens so viele Abschnitte pro Bereich (Kurs bzw. Persona) – begrenzt Speicher und Rechenzeit bei jeder Frage. */
    private const MAXABSCHNITTE = 400;

    /** Zeitlimit (Sekunden) für das Embedding einer Frage im Chat; beim Zerlegen der Dateien länger. */
    private const TIMEOUT_FRAGE = 8;
    private const TIMEOUT_ZERLEGEN = 60;

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

    /**
     * Wo die Wissensdateien eines Kurses liegen: im Kontext des KI-Chat-Blocks des Kurses (so reisen sie mit Kurssicherungen mit,
     * seit 17.11.2026); ohne Block im Kurskontext.
     */
    public static function kursablage(\context_course $kurs): \context {
        global $DB;
        $bi = $DB->get_records('block_instances', ['blockname' => 'ai_chat', 'parentcontextid' => $kurs->id], 'id', 'id', 0, 1);
        return $bi ? \context_block::instance(reset($bi)->id) : $kurs;
    }

    /** Bereich als [contextid, filearea, itemid, Spalten der Tabelle]. */
    private static function bereich(?\context_course $kurs, int $personaid = 0): array {
        return $kurs ? [self::kursablage($kurs)->id, self::KURSAREA, 0, ['personaid' => 0, 'contextid' => $kurs->id]]
            : [\context_system::instance()->id, self::PERSONAAREA, $personaid, ['personaid' => $personaid, 'contextid' => 0]];
    }

    private static function dateien(array $b): array {
        return get_file_storage()->get_area_files($b[0], 'block_ai_chat', $b[1], $b[2], 'filename', false);
    }

    private static function fingerabdruck(array $b): string {
        return implode('|', array_map(fn($d) => $d->get_filename() . ':' . $d->get_contenthash(), self::dateien($b)))
            . '|' . implode('|', self::links($b));
    }

    // ---- Webseiten als Wissen (17.11.2026, Wunsch der Nutzerin) ----
    // Bis zu „weblinksmax“ Adressen je Kurs bzw. Persona. Moodle ruft die Seiten beim Speichern (und wöchentlich, Aufgabe
    // weblinks_aktualisieren) über seine cURL-Klasse ab – mit den Sperrlisten der Website, also keine internen Adressen –,
    // nimmt den Text heraus und zerlegt ihn wie eine Wissensdatei. Keine freie Websuche: Die KI kennt nur diese Seiten.
    // Ablage: Kurs = configdata des KI-Chat-Blocks (reist mit Sicherungen mit), Persona = Einstellung personalinks_<id>.

    /** Höchstens so viele Webseiten pro Kurs bzw. Persona (Einstellung weblinksmax, Standard 5, 0 = aus). */
    public static function webmax(): int {
        $wert = get_config('block_ai_chat', 'weblinksmax');
        return $wert === false || $wert === '' ? 5 : max(0, (int)$wert);
    }

    /** Text (eine Adresse pro Zeile) → gültige http(s)-Adressen, ohne Doppelte, höchstens webmax(). */
    public static function links_lesen(string $text): array {
        $liste = [];
        foreach (preg_split('/\s+/', trim($text)) as $zeile) {
            $url = clean_param(trim($zeile), PARAM_URL);
            if ($url !== '' && preg_match('#^https?://[^/\s]+#i', $url) && \core_text::strlen($url) <= 255) {
                $liste[$url] = true;
            }
        }
        return array_slice(array_keys($liste), 0, self::webmax());
    }

    /** Gespeicherte Adressen eines Bereichs. */
    private static function links(array $b): array {
        if (self::webmax() === 0) {
            return [];
        }
        if ($b[3]['personaid'] > 0) {
            return self::links_lesen((string)get_config('block_ai_chat', 'personalinks_' . $b[3]['personaid']));
        }
        $ctx = \context::instance_by_id($b[0], IGNORE_MISSING);
        if ($ctx instanceof \context_block) {
            global $DB;
            $cd = $DB->get_field('block_instances', 'configdata', ['id' => $ctx->instanceid]);
            $config = $cd ? unserialize_object(base64_decode($cd)) : new \stdClass();
            return self::links_lesen((string)($config->weblinks ?? ''));
        }
        return self::links_lesen((string)get_config('block_ai_chat', 'kurslinks_' . $b[3]['contextid']));
    }

    /** Adressen eines Bereichs speichern (ohne neu zu zerlegen). */
    private static function links_setzen(array $b, string $text): void {
        global $DB;
        $wert = implode("\n", self::links_lesen($text));
        if ($b[3]['personaid'] > 0) {
            set_config('personalinks_' . $b[3]['personaid'], $wert === '' ? null : $wert, 'block_ai_chat');
            return;
        }
        $ctx = \context::instance_by_id($b[0], IGNORE_MISSING);
        if ($ctx instanceof \context_block) {
            $cd = $DB->get_field('block_instances', 'configdata', ['id' => $ctx->instanceid]);
            $config = $cd ? unserialize_object(base64_decode($cd)) : new \stdClass();
            $config->weblinks = $wert;
            $DB->set_field('block_instances', 'configdata', base64_encode(serialize($config)), ['id' => $ctx->instanceid]);
            return;
        }
        set_config('kurslinks_' . $b[3]['contextid'], $wert === '' ? null : $wert, 'block_ai_chat');
    }

    /**
     * Eine Webseite abrufen: ['titel' => …, 'text' => …] oder null (nicht erreichbar, gesperrt, kein lesbarer Text).
     * HTML: Hauptinhalt (main/article, sonst body) ohne Skripte, Navigation, Kopf- und Fußzeilen; Überschriften bleiben als „#“.
     */
    public static function webseite(string $url): ?array {
        global $CFG;
        require_once($CFG->libdir . '/filelib.php');
        $curl = new \curl();   // mit den cURL-Sperrlisten der Website (keine internen Adressen)
        $inhalt = $curl->get($url, [], ['CURLOPT_TIMEOUT' => 20, 'CURLOPT_CONNECTTIMEOUT' => 5, 'CURLOPT_FOLLOWLOCATION' => true,
            'CURLOPT_MAXREDIRS' => 3, 'CURLOPT_USERAGENT' => 'Moodle KI-Chat (Wissensquelle)']);
        $info = $curl->get_info();
        if ($curl->get_errno() || (int)($info['http_code'] ?? 0) >= 400 || !is_string($inhalt) || $inhalt === ''
                || strlen($inhalt) > 10 * 1024 * 1024) {
            return null;
        }
        $typ = strtolower((string)($info['content_type'] ?? ''));
        $titel = preg_replace('#^https?://(www\.)?#i', '', $url);
        if (strpos($typ, 'pdf') !== false || strpos($inhalt, '%PDF-') === 0) {
            $text = self::pdf_aus_inhalt($inhalt);
        } else if (strpos($typ, 'html') !== false || preg_match('/<html|<body/i', substr($inhalt, 0, 2000))) {
            if (preg_match('#<title[^>]*>(.*?)</title>#si', $inhalt, $m)) {
                $titel = trim(html_entity_decode(strip_tags($m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?: $titel;
            }
            $html = preg_replace('#<(script|style|noscript|nav|header|footer|aside|form|svg|iframe)\b.*?</\1>#si', ' ', $inhalt);
            if (preg_match('#<(main|article)\b[^>]*>(.*)</\1>#si', $html, $m)) {
                $html = $m[2];
            } else if (preg_match('#<body\b[^>]*>(.*)</body>#si', $html, $m)) {
                $html = $m[1];
            }
            $html = preg_replace_callback('#<h([1-3])\b[^>]*>(.*?)</h\1>#si', fn($h) => '<p>' . str_repeat('#', (int)$h[1]) . ' '
                . trim(strip_tags($h[2])) . '</p>', $html);
            $text = html_to_text($html, 0, false);
        } else if (strpos($typ, 'text/') === 0 || $typ === '') {
            $text = $inhalt;
        } else {
            return null;
        }
        $text = trim(preg_replace("/\n{3,}/", "\n\n", (string)$text));
        if (\core_text::strlen($text) < 50) {
            return null;
        }
        return ['titel' => \core_text::substr($titel, 0, 120), 'text' => \core_text::substr($text, 0, 200000)];
    }

    /** Für die Anzeige: je Adresse die Zahl der gespeicherten Abschnitte (0 = nicht lesbar). */
    public static function link_status(array $b): array {
        global $DB;
        $status = [];
        foreach (self::links($b) as $url) {
            $status[$url] = $DB->count_records(self::TABELLE, $b[3] + ['filename' => $url]);
        }
        return $status;
    }

    /**
     * Lokal (19.11.2026): je Wissensdatei die Zahl der gespeicherten Abschnitte (0 = kein lesbarer Text, z. B. eingescanntes PDF
     * oder Foto). Der mitgelieferte PDF-Leser liest nur die Textebene; Bilder liest er nicht (keine KI-Kosten).
     */
    private static function datei_status(array $b): array {
        global $DB;
        $status = [];
        foreach (self::dateien($b) as $datei) {
            $status[$datei->get_filename()] = $DB->count_records(self::TABELLE, $b[3] + ['filename' => $datei->get_filename()]);
        }
        return $status;
    }

    public static function kurs_dateistatus(\context_course $kurs): array {
        return self::datei_status(self::bereich($kurs));
    }

    public static function persona_dateistatus(int $personaid): array {
        return self::datei_status(self::bereich(null, $personaid));
    }

    /** Liste „✅ Datei – N Abschnitte / ⚠️ kein lesbarer Text“ mit Erklärung darüber (Kurs-Seite und Persona-Formular). */
    public static function dateistatus_html(array $status): string {
        if (!$status) {
            return '';
        }
        $zeilen = '';
        foreach ($status as $name => $anzahl) {
            $zeilen .= '<li style="overflow-wrap: anywhere;">' . ($anzahl > 0 ? '✅ ' : '⚠️ ') . s($name) . ' – '
                . ($anzahl > 0 ? get_string('weblinkok', 'block_ai_chat', $anzahl)
                    : '<span style="color: #b3261e;">' . get_string('dateiohnetext', 'block_ai_chat') . '</span>')
                . '</li>';
        }
        return '<div style="font-size: 0.85rem; color: #5b6670; margin-bottom: 2px;">' . get_string('dateistatusinfo', 'block_ai_chat')
            . '</div><ul style="margin: 0; padding-left: 1.1rem; font-size: 0.85rem;">' . $zeilen . '</ul>';
    }

    /** Namen der Dateien ohne lesbaren Text aus einer Status-Liste. */
    public static function ohne_text(array $status): array {
        return array_keys(array_filter($status, fn($n) => $n === 0));
    }

    public static function kurs_links(\context_course $kurs): array {
        return self::link_status(self::bereich($kurs));
    }

    public static function persona_links_text(int $personaid): string {
        return implode("\n", self::links(self::bereich(null, $personaid)));
    }

    public static function kurs_links_text(\context_course $kurs): string {
        return implode("\n", self::links(self::bereich($kurs)));
    }

    /** Fingerabdruck der abgerufenen Seiten (für die wöchentliche Aktualisierung: nur bei Änderungen neu zerlegen). */
    public static function web_fingerabdruck(array $b): string {
        $teile = [];
        foreach (self::links($b) as $url) {
            $seite = self::webseite($url);
            $teile[] = $url . ':' . ($seite ? sha1($seite['text']) : '-');
        }
        return sha1(implode('|', $teile));
    }

    /** Bereich für die Aufgabe weblinks_aktualisieren. */
    public static function bereich_kurs(\context_course $kurs): array {
        return self::bereich($kurs);
    }

    public static function bereich_persona(int $personaid): array {
        return self::bereich(null, $personaid);
    }

    /** Neu zerlegen, wenn sich die Webseiten eines Bereichs geändert haben; gibt true zurück, wenn neu zerlegt wurde. */
    public static function web_aktualisieren(array $b): bool {
        if (!self::links($b)) {
            return false;
        }
        $schluessel = 'webhash_' . ($b[3]['personaid'] > 0 ? 'p' . $b[3]['personaid'] : 'k' . $b[3]['contextid']);
        $neu = self::web_fingerabdruck($b);
        if ($neu === get_config('block_ai_chat', $schluessel)) {
            return false;
        }
        self::zerlegen($b);
        set_config($schluessel, $neu, 'block_ai_chat');
        return true;
    }

    private static function entwurf(array $b, array $optionen): int {
        $draftitemid = 0;
        file_prepare_draft_area($draftitemid, $b[0], 'block_ai_chat', $b[1], $b[2], $optionen);
        return $draftitemid;
    }

    private static function speichern(array $b, int $draftitemid, array $optionen, ?string $links = null): int {
        $vorher = self::fingerabdruck($b);
        if ($links !== null) {
            self::links_setzen($b, $links);
        }
        file_save_draft_area_files($draftitemid, $b[0], 'block_ai_chat', $b[1], $b[2], $optionen);
        return self::fingerabdruck($b) !== $vorher ? self::zerlegen($b) : -1;
    }

    // Kurs
    public static function kurs_entwurf(\context_course $kurs): int {
        return self::entwurf(self::bereich($kurs), self::kursoptionen());
    }

    /** Speichern und neu zerlegen; gibt die Zahl der Abschnitte zurück (-1 = unverändert). */
    public static function kurs_speichern(\context_course $kurs, int $draftitemid, ?string $links = null): int {
        return self::speichern(self::bereich($kurs), $draftitemid, self::kursoptionen(), $links);
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

    public static function persona_speichern(int $personaid, int $draftitemid, ?string $links = null): int {
        return self::speichern(self::bereich(null, $personaid), $draftitemid, self::personaoptionen(), $links);
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
        if ($links = get_config('block_ai_chat', 'personalinks_' . $vonid)) {
            set_config('personalinks_' . $nachid, $links, 'block_ai_chat');
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
        unset_config('personalinks_' . $personaid, 'block_ai_chat');
        unset_config('webhash_p' . $personaid, 'block_ai_chat');
    }

    /** Dateien eines Bereichs zerlegen, Embeddings berechnen, Abschnitte speichern. */
    private static function zerlegen(array $b): int {
        global $DB;
        \core_php_time_limit::raise(600);
        $DB->delete_records(self::TABELLE, $b[3]);
        $abschnitte = [];
        foreach (self::dateien($b) as $datei) {
            $nr = 0;
            foreach (self::abschnitte(self::text($datei)) as $text) {
                $abschnitte[] = (object)($b[3] + ['filename' => $datei->get_filename(), 'chunkno' => $nr++,
                    'content' => $text, 'embedding' => null, 'model' => '', 'timecreated' => time()]);
            }
        }
        foreach (self::links($b) as $url) {
            $seite = self::webseite($url);
            if (!$seite) {
                continue;
            }
            $nr = 0;
            foreach (self::abschnitte($seite['text']) as $text) {
                // Titel der Seite vorn (Quelle), Überschriften der Seite bleiben dahinter.
                $abschnitte[] = (object)($b[3] + ['filename' => $url, 'chunkno' => $nr++,
                    'content' => '[🌐 ' . str_replace([']', '['], ')', $seite['titel']) . "]\n" . $text, 'embedding' => null, 'model' => '',
                    'timecreated' => time()]);
            }
        }
        if (!$abschnitte) {
            return 0;
        }
        if (count($abschnitte) > self::MAXABSCHNITTE) {
            debugging('block_ai_chat Wissensdateien: nur die ersten ' . self::MAXABSCHNITTE . ' von ' . count($abschnitte)
                . ' Abschnitten werden verwendet.', DEBUG_DEVELOPER);
            $abschnitte = array_slice($abschnitte, 0, self::MAXABSCHNITTE);
        }
        // Embeddings in Paketen zu 16 Abschnitten; schlägt das fehl, bleibt die Suche per Wortvergleich.
        foreach (array_chunk(array_keys($abschnitte), 16) as $paket) {
            $texte = array_map(fn($i) => $abschnitte[$i]->filename . "\n" . $abschnitte[$i]->content, $paket);
            $vektoren = self::embeddings($texte, self::TIMEOUT_ZERLEGEN);
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
                $stat = ($tmp && $zip->open($tmp) === true) ? $zip->statName('word/document.xml') : false;
                if ($stat && $stat['size'] <= 30 * 1024 * 1024) {   // entpackt höchstens 30 MB
                    $xml = (string)$zip->getFromName('word/document.xml');
                    $zip->close();
                    $xml = preg_replace('~</w:p>~', "\n\n", $xml);
                    $text = html_entity_decode(strip_tags($xml), ENT_QUOTES | ENT_XML1, 'UTF-8');
                } else if ($stat) {
                    $zip->close();
                }
                @unlink($tmp);
                return $text;
            case 'pdf':
                return self::pdftext($datei);
            default:
                return \core_text::convert($inhalt, mb_detect_encoding($inhalt, 'UTF-8, ISO-8859-1', true) ?: 'UTF-8', 'UTF-8');
        }
    }

    /** PDF-Text über den mitgelieferten PDF-Leser (leer, wenn die Datei nicht lesbar ist). */
    private static function pdftext(\stored_file $datei): string {
        return self::pdf_aus_inhalt($datei->get_content());
    }

    /** PDF-Text aus dem Inhalt einer PDF-Datei. */
    private static function pdf_aus_inhalt(string $inhalt): string {
        raise_memory_limit(MEMORY_EXTRA);
        \core_php_time_limit::raise(120);
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
            $pdf = (new \Smalot\PdfParser\Parser([], $konfig))->parseContent($inhalt);
            $seiten = [];
            foreach ($pdf->getPages() as $nr => $seite) {
                // Seiten ohne Textebene (Scan, Foto) auslassen – sonst entstünden leere Abschnitte nur mit „# Seite N“.
                $text = trim($seite->getText());
                if ($text !== '') {
                    $seiten[] = '# Seite ' . ($nr + 1) . "\n\n" . $text;
                }
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
        $oben = '';          // oberste Ebene: Hauptüberschrift mit „Lernpfad/Kapitel/Modul/Thema/Teil N“ (bleibt bis zur nächsten solchen).
        $haupt = '';         // letzte Hauptüberschrift (# …), z. B. „# Listen in Python“ – beide stehen über jedem Abschnitt.
        $ueberschrift = '';  // letzte Überschrift beliebiger Ebene (für „(Fortsetzung)“).
        $imcode = false;     // Kommentare in Code-Blöcken („# a) …“) sind keine Überschriften.
        $beginn = function () use (&$oben, &$haupt, &$aktuell): void {
            $teile = array_unique(array_filter([trim(ltrim($oben, '#')), trim(ltrim($haupt, '#'))]));
            $aktuell = $teile ? '[' . implode(' › ', $teile) . "]\n\n" : '';
        };
        $beginn();
        foreach ($absaetze as $absatz) {
            $absatz = trim($absatz);
            if ($absatz === '') {
                continue;
            }
            if (!$imcode && preg_match('/^(#{1,6})\s/', $absatz, $m)) {
                $ueberschrift = strtok($absatz, "\n");
                if (strlen($m[1]) === 1 && $ueberschrift !== $haupt) {
                    // Neuer Hauptteil: laufenden Abschnitt abschließen.
                    if (trim(preg_replace('/^\[[^\]]*\]/', '', $aktuell)) !== '') {
                        $ergebnis[] = $aktuell;
                    }
                    if (preg_match('/^#\s+(Lernpfad|Kapitel|Modul|Thema|Teil|Unit|Kachel|Abschnitt)\s+\d+/iu', $ueberschrift)) {
                        $oben = preg_replace('/\s+[–-]\s+.*$/u', '', $ueberschrift);   // „Lernpfad 8: Dictionaries – Worum“ → „Lernpfad 8: Dictionaries“
                        $haupt = $ueberschrift;
                    } else {
                        $haupt = $ueberschrift;
                    }
                    $beginn();
                }
            }
            if (substr_count($absatz, '```') % 2 === 1) {
                $imcode = !$imcode;
            }
            // Sehr lange Absätze hart teilen.
            foreach (str_split($absatz, self::ABSCHNITT * 2) as $stueck) {
                $leer = trim(preg_replace('/^\[[^\]]*\]/', '', $aktuell)) === '';
                if (!$leer && \core_text::strlen($aktuell) + \core_text::strlen($stueck) > self::ABSCHNITT) {
                    $ergebnis[] = $aktuell;
                    $beginn();
                    if ($ueberschrift !== '' && $ueberschrift !== $haupt && strpos($stueck, $ueberschrift) !== 0) {
                        $aktuell .= $ueberschrift . " (Fortsetzung)\n\n";
                    }
                }
                $aktuell .= (substr($aktuell, -2) === "\n\n" || $aktuell === '' ? '' : "\n\n") . $stueck;
            }
        }
        if (trim(preg_replace('/^\[[^\]]*\]/', '', $aktuell)) !== '') {
            $ergebnis[] = $aktuell;
        }
        return $ergebnis;
    }


    /**
     * Lässt der AI Manager eine Chat-Anfrage dieser Person hier überhaupt zu (Mandant, Sperren, Kontrollzentrum, Limits)?
     * Erst dann wird die Frage für die Suche in den Wissensdateien an den Embedding-Dienst geschickt.
     */
    public static function anfrage_erlaubt(int $contextid): bool {
        global $USER;
        try {
            $konfig = \local_ai_manager\ai_manager_utils::get_ai_config($USER, $contextid, null, ['chat']);
        } catch (\Throwable $e) {
            return false;
        }
        $frei = \local_ai_manager\ai_manager_utils::AVAILABILITY_AVAILABLE;
        if (($konfig['availability']['available'] ?? '') !== $frei) {
            return false;
        }
        foreach ($konfig['purposes'] ?? [] as $p) {
            if (($p['purpose'] ?? '') === 'chat') {
                return ($p['available'] ?? '') === $frei;
            }
        }
        return false;
    }

    /** Abschnitte der Kurs-Wissensdateien löschen (wenn der KI-Chat-Block mit seinen Dateien gelöscht wird). */
    public static function kurs_loeschen(\context_course $kurs): void {
        global $DB;
        $DB->delete_records(self::TABELLE, ['personaid' => 0, 'contextid' => $kurs->id]);
    }

    /**
     * Wird über den AI Manager eingebettet (Einsatzzweck „Embedding“)? Sonst der frühere Weg direkt über den Telli/AIS.chat-Anschluss.
     */
    public static function ueber_ai_manager(): bool {
        static $ja = null;
        if ($ja === null) {
            $ja = class_exists('\\local_ai_manager\\local\\embedding') && \local_ai_manager\local\embedding::available();
        }
        return $ja;
    }

    /** Name des Embedding-Modells: aus dem AI Manager (Einsatzzweck „Embedding“) oder sonst aus der Einstellung embeddingmodel. */
    public static function modell(): string {
        if (self::ueber_ai_manager()) {
            return \local_ai_manager\local\embedding::model_name();
        }
        return trim((string)get_config('block_ai_chat', 'embeddingmodel')) ?: 'BAAI/bge-m3';
    }

    /**
     * Embeddings über die AIS.chat-API (OpenAI-kompatibel, Telli-Anschluss des AI Managers: Basis-URL und globaler Schlüssel).
     *
     * @return array|null Liste von Vektoren oder null, wenn kein Embedding-Modell erreichbar ist
     */
    public static function embeddings(array $texte, int $timeout = self::TIMEOUT_FRAGE): ?array {
        global $CFG;
        if (!$texte) {
            return null;
        }
        // Bevorzugt über den AI Manager (Einsatzzweck „Embedding“): Verbindung und Modell wählt die Schule dort, mit jedem Anschluss,
        // der Embeddings kann (AIS.chat/telli, OpenAI, Ollama …). Nicht protokolliert und nicht auf die Anfragen-Limits angerechnet.
        if (self::ueber_ai_manager()) {
            $status = \cache::make_from_params(\cache_store::MODE_APPLICATION, 'block_ai_chat', 'embeddingstatus');
            if ((int)$status->get('fehler') > time() - 300) {
                return null;
            }
            $vektoren = \local_ai_manager\local\embedding::embed($texte, $timeout);
            if ($vektoren === null) {
                $status->set('fehler', time());
            }
            return $vektoren;
        }
        require_once($CFG->libdir . '/filelib.php');
        $basis = rtrim((string)get_config('aitool_telli', 'baseurl'), '/');
        $schluessel = (string)get_config('aitool_telli', 'globalapikey');
        if ($basis === '' || $schluessel === '' || !$texte) {
            return null;
        }
        // War der Dienst eben nicht erreichbar, 5 Minuten nicht erneut versuchen (sonst wartet jede Chatfrage auf das Zeitlimit).
        $status = \cache::make_from_params(\cache_store::MODE_APPLICATION, 'block_ai_chat', 'embeddingstatus');
        if ((int)$status->get('fehler') > time() - 300) {
            return null;
        }
        $curl = new \curl();
        $curl->setHeader(['Authorization: Bearer ' . $schluessel, 'Content-Type: application/json']);
        $antwort = $curl->post($basis . '/v1/embeddings', json_encode(['model' => self::modell(), 'input' => array_values($texte)]),
            ['CURLOPT_TIMEOUT' => $timeout, 'CURLOPT_CONNECTTIMEOUT' => min(5, $timeout)]);
        $daten = json_decode((string)$antwort, true);
        if (empty($daten['data']) || count($daten['data']) !== count($texte)) {
            debugging('block_ai_chat Wissensdateien: keine Embeddings (' . substr((string)$antwort, 0, 200) . ')', DEBUG_DEVELOPER);
            $status->set('fehler', time());
            return null;
        }
        usort($daten['data'], fn($a, $b) => ($a['index'] ?? 0) <=> ($b['index'] ?? 0));
        return array_map(fn($d) => $d['embedding'], $daten['data']);
    }

    /**
     * Passende Auszüge zur Frage als Text für die Persona-Anweisung (leer, wenn die Persona keine Wissensdateien hat).
     */
    public static function kontext(string $frage, int $personaid, int $kurscontextid, string $seite = ''): string {
        global $DB;
        self::$quellen = [];
        // Wissensdateien der gewählten Persona (alle Kurse) und des Kurses (nur dieser Kurs).
        $abschnitte = $DB->get_records_select(self::TABELLE,
            '(personaid > 0 AND personaid = ? AND contextid = 0) OR (personaid = 0 AND contextid > 0 AND contextid = ?)',
            [$personaid, $kurscontextid], 'filename, chunkno');
        if (!$abschnitte) {
            return '';
        }
        $punkte = [];
        // Nur Vektoren des eingestellten Modells sind vergleichbar (nach einem Modellwechsel: Dateien neu speichern bzw. Aufgabe abwarten).
        $modell = self::modell();
        foreach ($abschnitte as $a) {
            if ($a->model !== $modell) {
                $a->embedding = null;
            }
        }
        $mitvektor = array_filter($abschnitte, fn($a) => !empty($a->embedding));
        $fragevektor = $mitvektor ? (self::embeddings([$frage])[0] ?? null) : null;
        foreach ($abschnitte as $id => $a) {
            // Gemischte Suche: Bedeutung (Embedding) + Wortvergleich + Bonus, wenn eine genannte Aufgabe genau getroffen ist.
            $text = $a->filename . ' ' . $a->content;
            $titel = preg_match('/^\[([^\]]*)\]/u', $a->content, $tm) ? $tm[1] : '';
            $wort = self::wortpunkte($frage, $text) + self::aufgabenbonus($frage, $a->content)
                + self::titelbonus($frage, $titel)   // Thema der Frage im Lernpfad-/Notebook-Titel
                + ($seite !== '' ? 1.0 * self::titelbonus($seite, $titel) : 0);   // Kachel, von der aus gefragt wird
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
            $web = preg_match('#^https?://#i', $a->filename);
            $auszuege[] = ($web ? '--- Von der Webseite ' . $a->filename . ' ---' : '--- Aus der Datei „' . $a->filename . '“ ---')
                . "\n" . $a->content;
            $titel = preg_match('/^\[([^\]]*)\]/u', $a->content, $tm) ? $tm[1] : pathinfo($a->filename, PATHINFO_FILENAME);
            $teile = explode(' › ', $titel);
            if (count($teile) === 2 && strpos($teile[1], $teile[0]) === 0) {
                $titel = $teile[1];   // „Lernpfad 8: Dictionaries › Lernpfad 8: Dictionaries – Worum“ → nur der zweite Teil
            }
            self::$quellen[$titel . ($web ? "\t" . $a->filename : '')] = true;   // Webseite: Titel + Tab + Adresse (Link)
        }
        return "\n\n## Wissensdateien\nDie Lehrkraft hat dir Wissensdateien gegeben. Hier sind die Auszüge, die am besten zur aktuellen Frage passen. "
            . "Nutze sie für deine Antwort und nenne bei Bedarf die Datei bzw. Aufgabe. Wenn die Auszüge nicht zur Frage passen, sag das "
            . "und frag nach.\n\n" . implode("\n\n", $auszuege);
    }

    /** Quellen einer Antwort speichern (Log-Eintrag des AI Managers). */
    public static function quellen_speichern(int $logid): void {
        global $DB;
        if ($logid && self::$quellen) {
            $DB->insert_record('block_ai_chat_quellen', ['logid' => $logid,
                'quellen' => json_encode(array_slice(array_keys(self::$quellen), 0, 4), JSON_UNESCAPED_UNICODE)]);
        }
    }

    /** HTML-Zeile „📚 Aus: …“ für eine Antwort (leer, wenn keine Wissensdateien benutzt wurden). */
    public static function quellen_html(int $logid): string {
        global $DB;
        $json = $logid ? $DB->get_field('block_ai_chat_quellen', 'quellen', ['logid' => $logid]) : false;
        $liste = $json ? json_decode($json, true) : [];
        if (!$liste) {
            return '';
        }
        // Kurz halten (Wunsch der Nutzerin, 17.11.2026): nur der Lernpfad bzw. die Kachel, ohne Notebook- und Abschnittstitel.
        $kurz = [];
        foreach ($liste as $titel) {
            [$titel, $url] = explode("\t", $titel, 2) + [1 => ''];
            $t = trim(preg_replace('/\s+[–-]\s+.*$/u', '', explode(' › ', $titel)[0]));
            $t = preg_replace('/\s*\(🕒[^)]*\)/u', '', $t);
            if ($t !== '' && !isset($kurz[$t])) {
                $kurz[$t] = $url;
            }
        }
        // Eine Quelle pro Zeile (Wunsch der Nutzerin, 17.11.2026), höchstens drei; Webseiten als Link (neuer Tab).
        $kurz = array_slice($kurz, 0, 3, true);
        $html = [];
        foreach ($kurz as $t => $url) {
            $html[] = ($url !== '' && preg_match('#^https?://#i', $url))
                ? '<a href="' . s($url) . '" target="_blank" rel="noopener" style="color: inherit; text-decoration: underline;">' . s($t) . '</a>'
                : s($t);
        }
        $stil = 'font-size: 0.75rem; color: #8a939c; margin-top: 6px; line-height: 1.35;';
        if (count($html) === 1) {
            return '<div class="kichat-quellen" style="' . $stil . '">📚 Quelle: ' . $html[0] . '</div>';
        }
        return '<div class="kichat-quellen" style="' . $stil . '">📚 Quellen:<ul style="margin: 2px 0 0 0; padding-left: 1.4rem;">'
            . implode('', array_map(fn($t) => '<li style="margin: 0;">' . $t . '</li>', $html)) . '</ul></div>';
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

    /**
     * Bonus, wenn die Frage „Aufgabe 3“ o. Ä. nennt (Embeddings erkennen Nummern schlecht): 1,5, wenn der Abschnitt mit genau dieser
     * Aufgabe beginnt (Überschrift), 0,5, wenn sie nur irgendwo darin vorkommt.
     */
    private static function aufgabenbonus(string $frage, string $text): float {
        if (!preg_match_all('/\b(aufgabe|teil|schritt|stufe|lernpfad)\s*(\d+[a-z]?)\b/iu', $frage, $m, PREG_SET_ORDER)) {
            return 0.0;
        }
        $rumpf = preg_replace('/^\[[^\]]*\]\s*/u', '', $text);
        $kopf = strtok($rumpf, "\n");
        $bonus = 0.0;
        foreach ($m as $x) {
            $muster = '/' . preg_quote($x[1], '/') . '\s*' . preg_quote($x[2], '/') . '\b/iu';
            if (preg_match('/^#/', (string)$kopf) && preg_match($muster, (string)$kopf)) {
                $bonus = max($bonus, 1.5);
            } else if (preg_match($muster, $rumpf)) {
                $bonus = max($bonus, 0.5);
            }
        }
        return $bonus;
    }


    /** Füllwörter, die im Titelvergleich nicht zählen. */
    private const FUELLWOERTER = ['aufgabe', 'notebook', 'nicht', 'weiter', 'komme', 'hilfe', 'helfen', 'bitte', 'warum', 'wieso',
        'weshalb', 'kannst', 'könnte', 'habe', 'meine', 'meinem', 'meiner', 'diese', 'dieser', 'dieses', 'dass', 'wird', 'sind', 'eine',
        'einen', 'einer', 'beim', 'immer', 'schon', 'noch', 'erklär', 'erkläre', 'verstehe', 'funktioniert', 'lernpfad', 'datei', 'notebooks', 'abgabe', 'deine', 'deinen', 'finde', 'finden', 'gibt', 'geht', 'mache', 'machen'];

    /** 0,6, wenn ein Inhaltswort der Frage (Wortanfang, ab 4 Buchstaben) im Titel des Abschnitts steht („Listen“ ~ „Liste“). */
    private static function titelbonus(string $frage, string $titel): float {
        if ($titel === '') {
            return 0.0;
        }
        $teile = fn($t) => array_filter(preg_split('/[^\p{L}\p{N}]+/u', \core_text::strtolower($t)), fn($w) => \core_text::strlen($w) >= 4);
        $titelwoerter = $teile($titel);
        foreach ($teile($frage) as $w) {
            if (in_array($w, self::FUELLWOERTER)) {
                continue;
            }
            $stamm = \core_text::substr($w, 0, 5);
            foreach ($titelwoerter as $t) {
                if (strpos($t, $stamm) === 0) {
                    return 0.6;
                }
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

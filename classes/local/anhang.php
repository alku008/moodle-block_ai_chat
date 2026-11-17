<?php
// Lokale Erweiterung (15.11.2026, nicht vom Hersteller): im Chat angehängte Dateien (amd/src/anhang.js) stehen mit Markierungen in der
// Frage. Für die Anzeige im Chatverlauf und in der Liste der Unterhaltungen wird der Inhalt durch „📎 Dateiname“ ersetzt; an die KI
// (Verlauf) geht der volle Text. Im KI-Kontrollzentrum sehen berechtigte Lehrkräfte die ganze Frage samt Datei.

namespace block_ai_chat\local;

class anhang {

    /** Frage für die Anzeige: Dateiinhalte durch „📎 Dateiname“ ersetzen. */
    public static function anzeige(string $prompt): string {
        return preg_replace('/📎 Datei „([^“\n]+)“:\n````\n.*?\n````\n\(Ende der Datei „\1“\)\n*/su', "📎 $1\n", $prompt);
    }

    /** Einstellungen der Administration (Website-Administration → Plugins → Blöcke → KI-Chat). */
    public static function erlaubt(): bool {
        $wert = get_config('block_ai_chat', 'chatupload');
        return $wert === false || (bool)$wert;
    }

    public static function maxzeichen(): int {
        return (int)(get_config('block_ai_chat', 'chatuploadmaxchars') ?: 20000);
    }

    /** Anhänge pro Person und Tag (0 = unbegrenzt). */
    public static function protag(): int {
        $wert = get_config('block_ai_chat', 'chatuploadsperday');
        return $wert === false || $wert === '' ? 10 : max(0, (int)$wert);
    }

    /** Für das JavaScript (anhang.js). */
    public static function jsconfig(): array {
        return ['erlaubt' => self::erlaubt(), 'maxzeichen' => self::maxzeichen()];
    }

    /** Start- und Folgevorschläge aus den Einstellungen (eine Zeile je Vorschlag; Folge: „Beschriftung | Prompt“). */
    public static function vorschlaege(): array {
        $zeilen = fn(string $name, string $standard) => array_values(array_filter(array_map('trim',
            explode("\n", (string)(get_config('block_ai_chat', $name) === false ? $standard : get_config('block_ai_chat', $name)))), 'strlen'));
        $start = $zeilen('startvorschlaege', self::STARTVORSCHLAEGE);
        $folge = [];
        foreach ($zeilen('folgevorschlaege', self::FOLGEVORSCHLAEGE) as $z) {
            [$text, $prompt] = array_map('trim', explode('|', $z, 2) + [1 => '']);
            if ($text !== '' && $prompt !== '') {
                $folge[] = ['text' => $text, 'prompt' => $prompt];
            }
        }
        return ['start' => $start, 'folge' => $folge];
    }

    public const STARTVORSCHLAEGE = "Ich komme bei Aufgabe … im Notebook … nicht weiter.\nErklär mir bitte diese Fehlermeldung: …\nWas bedeutet eigentlich …?";
    public const FOLGEVORSCHLAEGE = "💡 Noch ein Tipp | Gib mir bitte noch einen Tipp – aber noch nicht die Lösung.\n"
        . "🧩 Ähnliches Beispiel | Zeig mir bitte ein ähnliches, einfacheres Beispiel – nicht die Lösung meiner Aufgabe.";

    /** Wie viele Dateien die Person in den letzten 24 Stunden angehängt hat. */
    public static function heute(int $userid): int {
        global $DB;
        return $DB->count_records_select('local_ai_manager_request_log',
            'userid = ? AND component = ? AND timecreated > ? AND ' . $DB->sql_like('prompttext', '?'),
            [$userid, 'block_ai_chat', time() - DAYSECS, '📎 Datei „%']);
    }

    /**
     * Prüfung vor der Anfrage (der Browser lässt sich umgehen, deshalb auch auf dem Server).
     *
     * @return string Fehlermeldung oder leer
     */
    public static function pruefen(string $prompt, int $userid): string {
        if (strpos($prompt, '📎 Datei „') !== 0) {
            return '';
        }
        if (!self::erlaubt()) {
            return 'Dateien im Chat sind auf dieser Website ausgeschaltet.';
        }
        if (\core_text::strlen($prompt) > self::maxzeichen() + 2000) {
            return 'Die angehängte Datei ist zu groß (höchstens ' . self::maxzeichen() . ' Zeichen).';
        }
        if (self::protag() > 0 && self::heute($userid) >= self::protag()) {
            return 'Du kannst höchstens ' . self::protag() . ' Dateien pro Tag im Chat anhängen. Morgen geht es weiter.';
        }
        return '';
    }
}

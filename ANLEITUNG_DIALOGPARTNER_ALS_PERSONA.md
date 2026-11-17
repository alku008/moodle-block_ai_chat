# Einen AIS.chat-Dialogpartner als Persona im KI-Chat nachbauen

Die AIS.chat-Schnittstelle liefert Moodle nur **Sprachmodelle**. Die Dialogpartner, Assistenten und KIKIs, die man in AIS.chat baut,
kann Moodle nicht abrufen. Ein Dialogpartner besteht aber im Kern aus Anweisung und Wissensdateien, und genau das kann eine **Persona**
im KI-Chat auch. So baust du ihn in Moodle nach.

**Vorteil:** Lernende brauchen keinen Link und kein AIS.chat-Konto. Der Chat läuft im Kurs, mit den Limits, dem Zeitplan und der
Test-Sperre des KI-Kontrollzentrums. Die Kosten trägt die Schule (Schlüssel im AI Manager), nicht die Lehrkraft.

---

## 1. Was wohin gehört

| In AIS.chat | Im Moodle-KI-Chat |
|---|---|
| Name | Persona → **Name** |
| Kurzbeschreibung | Persona → **Info, die dem Nutzer angezeigt wird** (steht unter dem Titel im Chatfenster) |
| Instruktionen / Systemprompt | Persona → **Prompt** |
| Begrüßung, Einstiegsfrage | im **Prompt** als Satz: „Beginne das Gespräch mit: …“ |
| Wissensdateien (bis 5) | Persona → **Wissensdateien dieser Persona** (gilt in allen Kursen mit dieser Persona) **oder** KI-Kontrollzentrum → **📚 Wissen für den KI-Chat** (gilt nur in diesem Kurs) |
| Sprachmodell | KI-Kontrollzentrum → **🧠 KI-Modell für diesen Kurs** (nur wenn die Administration Modelle freigegeben hat), sonst die Vorgabe der Schule |
| Promptvorschläge | Start- und Folgevorschläge legt die Administration für alle fest (Einstellungen des KI-Chats) |
| Link mit Code teilen | entfällt: Die Persona gilt für alle im Kurs |
| Websuche, Sprachausgabe, Bilder | gibt es im KI-Chat nicht |

Erlaubte Dateitypen für Wissensdateien: txt, md, ipynb, py, html, csv, docx, pdf (Standard: bis 5 Dateien zu je 5 MB).

---

## 2. Persona anlegen

Du brauchst das Recht, den KI-Chat zu bearbeiten (Lehrkräfte im Kurs).

1. Kurs öffnen und den KI-Chat mit dem **✦-Knopf** öffnen.
2. Oben rechts **⋮ → Personas verwalten** → **Neue Persona anlegen**.
3. **Name** eintragen, z. B. „Python-Lerncoach“.
4. **Prompt:** die Instruktionen aus AIS.chat hineinkopieren. Am besten als reinen Text einfügen (Mac: ⌥⇧⌘V, Windows: Strg+Umschalt+V), denn
   Formatierungen gehen als HTML an die KI. Die Begrüßung als letzten Satz ergänzen, z. B.:
   „Beginne das Gespräch mit: Hallo! Ich bin dein Python-Lerncoach. Woran arbeitest du gerade?“
5. **Wissensdateien dieser Persona:** die Dateien des Dialogpartners hineinziehen. Sie gelten dann in **allen Kursen**, in denen
   die Persona gewählt ist. Gehören die Dateien nur zu einem Kurs, lass das Feld leer und nimm Schritt 4.
6. **Info, die dem Nutzer angezeigt wird:** die Kurzbeschreibung, z. B. „Hilft dir bei den Notebooks – mit Tipps, nicht mit fertigen Lösungen.“
7. **Speichern.** Die Dateien werden dabei zerlegt und für die Suche vorbereitet. Das dauert bei großen Dateien einen Moment.

Die Persona steht dann unter **Meine Personas**. Personas für die ganze Schule (**Globale Vorlagen**) kann nur die Administration anlegen.

## 3. Persona im Kurs einschalten

Im Bearbeitungsmodus: **KI-Kontrollzentrum → ⚙️ Weitere KI-Einstellungen → 🎭 Persona für den KI-Chat** → Persona wählen →
**Einstellungen speichern**. Sie gilt für alle im Kurs; Lernende können sie nicht ändern.

## 4. Kurs-Wissensdateien (optional)

**KI-Kontrollzentrum → 📚 Wissen für den KI-Chat → Dateien verwalten.** Diese Dateien gelten nur in diesem Kurs, für jede Persona.
Bei jeder Frage sucht der Chat die passenden Abschnitte aus Persona- und Kurs-Dateien heraus. Unter jeder Antwort steht, woraus
er geschöpft hat („📚 Quelle: …“).

Statt Dateien hochzuladen, kann man sie auch **aus den Kursinhalten erzeugen** (Vorschau mit Häkchen). Das ersetzt die bisherigen Kurs-Dateien.

## 5. Ausprobieren

Wechsle die Rolle zu „Teilnehmer/in“ oder melde dich mit einem Testkonto an. Stell eine Frage zu einer Aufgabe aus den Wissensdateien
und prüfe die Antwort und die Quellenzeile.

---

## Tipps

- **Keine Lösungen hochladen.** Die KI könnte sie verraten, auch wenn der Prompt das verbietet.
- **Dateien gut gliedern:** Überschriften (`#`, `##`) und Aufgabennummern („Aufgabe 3“) helfen der Suche. Eine Frage wie „Ich hänge bei
  Aufgabe 3“ findet dann genau den richtigen Abschnitt.
- **Lieber wenige, gebündelte Dateien** als viele kleine: Es gibt höchstens 5 Dateien je Persona bzw. Kurs.
- **Prompt kurz prüfen:** Hinweise auf AIS.chat-Funktionen, die es in Moodle nicht gibt (Websuche, Bilder, „lade eine Datei hoch“), streichen.
  Dateien anhängen können die Lernenden im Chat trotzdem: mit 📎 (Text-, Code- und Notebook-Dateien).
- **Du-Anrede:** Steht im Prompt „Sprich die Person immer mit du an“, bleibt die KI dabei.

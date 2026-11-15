<?php
// Lokale Erweiterung (15.11.2026): deutsche Texte für die Wissensdateien der Personas (übrige Texte kommen aus dem Sprachpaket).
defined('MOODLE_INTERNAL') || die();
$string['embeddingmodel'] = 'Embedding-Modell für Wissensdateien der Personas';
$string['embeddingmodeldesc'] = 'Modellname für /v1/embeddings des Telli-Anschlusses (AIS.chat-API). Ohne erreichbares Embedding-Modell sucht der Chat die passenden Abschnitte per Wortvergleich.';
$string['wissensdateien'] = 'Wissensdateien';
$string['wissensdateien_help'] = 'Bis zu 5 Dateien (Anzahl legt die Administration fest) (txt, md, ipynb, py, html, csv, docx, pdf). Bei jeder Frage schickt der Chat nur die am besten passenden Abschnitte zusammen mit der Persona-Anweisung an die KI. Keine Lösungen hochladen, die Lernende nicht sehen sollen.';
$string['wissensdateienheading'] = 'Wissensdateien und Dateien im Chat';
$string['wissensdateienheadingdesc'] = 'Personas können Wissensdateien haben; bei jeder Frage gehen die passenden Abschnitte mit. Nutzerinnen und Nutzer können an eine Chat-Frage eine Textdatei anhängen.';
$string['personamaxfiles'] = 'Wissensdateien pro Persona';
$string['personamaxfilesdesc'] = '0 = keine Wissensdateien.';
$string['personamaxbytes'] = 'Höchstgröße je Wissensdatei (MB)';
$string['embeddingok'] = 'Embedding-Modell erreichbar ({$a} Dimensionen).';
$string['embeddingfehlt'] = 'Embedding-Modell nicht erreichbar – Wissensdateien werden per Wortvergleich durchsucht.';
$string['chatupload'] = 'Dateien im Chat erlauben';
$string['chatuploaddesc'] = 'Alle, auch Lernende, können an eine Frage eine Textdatei anhängen (txt, md, py, ipynb, csv …).';
$string['chatuploadsperday'] = 'Anhänge pro Person und Tag';
$string['chatuploadsperdaydesc'] = '0 = unbegrenzt.';
$string['chatuploadmaxchars'] = 'Höchstens Zeichen je Anhang';
$string['chatuploadmaxcharsdesc'] = 'Längere Dateien werden gekürzt. Etwa 4 Zeichen entsprechen einem Token.';
$string['kursmaxfiles'] = 'Wissensdateien pro Kurs';
$string['kursmaxfilesdesc'] = 'Lehrkräfte verwalten sie über das KI-Kontrollzentrum des Kurses. 0 = keine Kurs-Wissensdateien.';
$string['kurswissen'] = 'Wissensdateien für den KI-Chat in diesem Kurs';
$string['kurswissen_help'] = 'Text-, Code-, Notebook-, Word- oder PDF-Dateien. Bei jeder Chat-Frage in diesem Kurs gehen nur die am besten passenden Abschnitte an die KI. Keine Lösungen hochladen.';
$string['kurswisseninfo'] = 'Bei jeder Frage im KI-Chat dieses Kurses gehen die am besten passenden Abschnitte dieser Dateien mit – mit und ohne Persona. Lade Aufgaben, Notebooks oder Merkblätter hoch, aber keine Lösungen: Die KI könnte sie verraten.';
$string['kurswissengespeichert'] = 'Wissensdateien gespeichert: {$a->dateien} Dateien, {$a->abschnitte} Abschnitte.';
$string['kurswissenunveraendert'] = 'Die Wissensdateien sind unverändert.';
$string['kurswissenaus'] = 'Kurs-Wissensdateien sind von der Administration ausgeschaltet.';

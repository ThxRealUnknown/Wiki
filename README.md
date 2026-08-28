# Worldbuilder

Worldbuilder is a self-hosted, single-user wiki for organizing a fictional world, 
running locally on PHP with a SQLite backend. Content lives in nestable archives 
(Characters, Species, Magic Systems, etc.) made up of entries. 
It prides itself in full customizability, allowing for a free design of ones own
structure and entries.
Each each entry's structure comes from a user-editable layout — an ordered list 
of fields (text, dates/eras on a custom calendar, choices, tags, images, 
shapes/pins/paths and links to other entries that auto-generate 
backlinks). 
Beyond reference material, it has a Draft/Story mode for writing and publishing 
story chapters, tools like a fully customizable Calendar, 
a Pinboard for visually mapping connections, a layered World map, sitewide Search, 
and full change history with diff/restore. Everything can be exported and imported 
with a guid-based restore that merges cleanly rather than clobbering newer work.

**Open it at:** http://localhost:8080/

---

## Archives

### Nesting archives

An archive can sit under another one to any depth. The sidebar indents each
level against a guide line, every ancestor of the open archive stays lit, and
breadcrumbs read the whole chain — *Worlds › Locations › Regions › Cities*.

The only moves refused are the ones that would make a loop: an archive cannot be
its own parent, and it cannot be filed under one of its own descendants. 
A refused move leaves the archive exactly where it was. 
Deleting an archive does not delete its children: they move up to take its place, so the rest of the
branch keeps its shape.

---

## Entries

### Field types

| Type | What it is |
|---|---|
| Short text | One line — a name, an epithet |
| Long text | Plain multi-line text |
| Rich text | Headings, bold, lists, quotes, links |
| Number | Sortable, with an optional unit ("years", "people") |
| Date | A day under your own calendar — shown, and clickable, on the timeline |
| Era | A span of years, drawn as a bar on the timeline, coloured by its archive |
| Choice | One option from a list you define |
| Tags | Several labels at once, free-form or from a list |
| Image | Uploaded picture, stored on disk |
| Banner | A wide picture across the top of the entry, above its title |
| Link to entries | Points at other entries — **backlinks appear automatically** |
| Map area | A traced shape on the world map |
| Map point | A single pin on the world map |
| Map path | A traced route on the world map |

A link field can be restricted to **any number of archives**, or left open to all
of them, and can allow one target or many.

Ticking several matters when a thing belongs to more than one kind of thing. A
*Part of* field can reach both **Locations** and **Worlds**, so a place can
belong to another place *or* to a whole world, in the same field.

### Fields are archived, not deleted

Removing a field in the layout editor does not destroy anything. The field is
**archived**: it disappears from forms and entry pages, and every value entries
stored in it is kept. *Layouts → Fields* lists archived fields with how many
entries still hold content in each, and offers **Put it back** — which restores
the field and its content intact.

Destroying a field for good can only happen on that page.

### Tags

Tags are able to be searched site-wide. They can be configured and removed under **Settings**

### Banners

- **Site banner** — *Settings → Site banner*. A strip across the very top of
  every page, above the sidebar. Stored in the `settings` table and carried in
  the JSON backup.
- **Entry banner** — the `banner` field type. Add it to any layout and it renders
  full width above that entry's title.

### Copying and moving entries

The *Copy/Move* button on an entry's page opens a dialog with two independent
choices: **Copy** (the original stays put, a new entry appears elsewhere) or
**Move** (the same entry relocates — nothing is left behind), and which archive
to send it to. Picking the entry's own archive with Copy is a plain duplicate.

If the destination archive does not have the same layout as the one the copied
entry had, one is created with the same fields.

### Favorites

Any entry can be pinned from its own page. Favorited entries sit in a fold-out
panel reachable from every page, so the things you're actively working on 
are never more than a click away.

---

## Connections

Every entry and every chapter can be connected to every other one, in any
combination. Connections are drawn by hand from the **right-hand rail**: search,
pick, done.

The rail gathers three different things in one place:

| Section | What it is |
|---|---|
| *(grouped by archive)* | Free-form connections you drew. Removable from either side. |
| **Linked in fields** | Targets of the entry's own relation fields. Edit these in the entry form. |
| **Referenced by** | Other entries whose relation fields point *here*. Automatic. |

Can be turned off in *Settings → Features* if a wiki doesn't need it.

---

## Timeline and Calendar

Every Date and Era field lives on **your own calendar**, not a generic one:
named months (each with its own day count), named weekdays, optional
intercalary day blocks (days that belong to no month — a Yearsend, say),
leap-year rules, and holidays that recur yearly. Design it at *Settings →
Design the calendar*; every Date/Era field on every archive follows it
immediately.

Two views onto the same data:

- **Timeline** — every Date and Era value across every archive on one
  pannable, zoomable year axis. Points are dots, eras are bars, coloured by
  their archive.
- **Calendar** — a month grid (plus the intercalary strips, if any), with the
  same points and eras laid out on actual days, holidays highlighted.

Can be turned off in *Settings → Features* if a wiki doesn't need it.

---

## Pinboard

A visual board for tracing how things connect: pin an entry, click it to open
out everything tied to it, drag pins to arrange them. Two kinds of string,
drawn differently — a **connection** (hand-drawn, reads the same from both
ends) and a **field link** (belongs to one entry's relation field, points one
way, and can only be changed by editing the entry that holds it). A new string
can be started from either kind of handle directly on the board.

Can be turned off in *Settings → Features* if a wiki doesn't need it.

---

## World map

Multiple maps (a surface map, an underground one, or whatever the world needs). 
You can add shapes, paths and pins to the map in order to define the features:

- **Trace a shape** (a Map area field) — a region's outline.
- **Trace a path** (a Map path field) — a route, with no interior to fill.
- **Drop a pin** (a Map point field) — a single spot, with its own symbol.

An entry with any of these shows a cropped **cutout** of the map on its own
page — just the area around its shape, not the whole continent — and clicking
it opens the full map centred on exactly that spot.

Can be turned off in *Settings → Features* if a wiki doesn't need it.

---

## Draft and Story

**Draft** is the workshop. Chapters list with their title, creation date and last
edit; each one has a title, rich-text **content**, rich-text **notes**, a chapter
number, and a switch for whether readers see it. Chapter numbers accept decimals,
so 12.5 sits between 12 and 13. Chapters can be grouped under a part or book
heading.

**Story** is the same chapters as a reader meets them: only the ones flagged as
shown, in number order, with no editing controls anywhere. Notes never appear
there.

Can be turned off in *Settings → Features* if a wiki doesn't need it.

---

## Change history

Every entry edit and deletion is recorded — a snapshot of the title, values and
links, taken right before the change lands — reachable from *Settings → Change
history*. 

---

## Languages

The interface itself — buttons, labels, help text — can be shown in more than
one language, picked at *Settings → Language*.

---

## Export

*Export* in the sidebar offers two downloads, kept strictly apart — the world
never contains chapters, the book never contains archives.


| Format | What you get |
|---|---|
| `.html` | One self-contained file — styles inlined, images embedded as data URIs, zero external requests. Linked table of contents. Print → Save as PDF gives a clean PDF; the page breaks are already in the stylesheet. |
| `.docx` | A Word document with real heading styles, so Word's navigation pane works and Insert → Table of Contents finds everything. Opens in Word, LibreOffice and Google Docs. Chapters keep their paragraph breaks and emphasis; entry fields become labelled sections. |
| `.md` | Plain Markdown. Rich text is converted properly — headings nest below their container rather than colliding with it, and lists, quotes, emphasis and links all survive. |


### Backup and restore

The HTML and Markdown exports are for reading. **The JSON backup is the one that
can come back in** — archives, layouts, every field (archived ones included),
entries, values, links, connections and chapters.

Every row carries a **guid**: a hidden, permanent identifier. Restores match on
it rather than on names, which means:

- A record the file recognises is **overwritten**, even if you renamed it since.
- A record the file does not have is **left completely alone** — work done after
  the backup survives a restore.
- Importing the same file twice changes nothing the second time.
- A backup restores correctly into an *empty* database, so it is a real backup
  and not just a diff.

> Uploaded images are **not** inside the JSON. Keep a copy of `public/uploads` beside it.

---

## Running it

```
wiki.bat
```

Starts PHP's own web server on <http://localhost:8080/> using
`public\router.php`. Nothing to install, no administrator rights, no server to
start beforehand — the whole database is one SQLite file inside this folder
(`data/worldbuilder.sqlite`), created the first time anything connects to it.


### First time on a new machine

```
setup.bat
```

Applies every migration and, if a `backup\*.json` file is present, restores it.
Safe to run more than once. After that, `wiki.bat` is all you need.

### Moving it to another computer

Unlike a server-backed database, nothing lives outside this folder — copy the
whole project and the SQLite file comes with it.


### Resetting to seed data

```
reset_to_seed.bat
```

For when you genuinely want to throw the database away and start over from
`bin\seed_data.php`'s starter archives. 
Writes a full timestamped backup to `backup\` first — restorable with
`bin\setup.php --restore`.

---

---

# Worldbuilder (Deutsch)

Worldbuilder ist ein selbst gehostetes Wiki für eine einzelne Person, um eine
fiktive Welt zu organisieren, das lokal mit PHP und einem SQLite-Backend läuft.
Der Inhalt liegt in verschachtelbaren Archiven (Charaktere, Spezies,
Magiesysteme usw.), die aus Einträgen bestehen. Es legt großen Wert auf volle
Anpassbarkeit und erlaubt die freie Gestaltung der eigenen Struktur und
Einträge. Die Struktur jedes Eintrags ergibt sich aus einem frei bearbeitbaren
Layout — einer geordneten Liste von Feldern (Text, Datum/Ära nach einem
eigenen Kalender, Auswahlfelder, Tags, Bilder, Formen/Pins/Pfade und Links zu
anderen Einträgen, die automatisch Backlinks erzeugen). Über das
Referenzmaterial hinaus gibt es einen Entwurf/Geschichte-Modus zum Schreiben
und Veröffentlichen von Kapiteln, Werkzeuge wie einen voll anpassbaren
Kalender, eine Pinnwand zur visuellen Darstellung von Verbindungen, eine
mehrschichtige Weltkarte, eine seitenweite Suche und eine vollständige
Änderungshistorie mit Diff/Wiederherstellung. Alles kann exportiert und
importiert werden, mit einer GUID-basierten Wiederherstellung, die sauber
zusammenführt, statt neuere Arbeit zu überschreiben.

**Hier öffnen:** http://localhost:8080/

---

## Archive

### Verschachtelte Archive

Ein Archiv kann unter einem anderen liegen, beliebig tief. Die Seitenleiste
rückt jede Ebene an einer Führungslinie ein, jeder Vorfahre des geöffneten
Archivs bleibt hervorgehoben, und die Brotkrumen-Navigation zeigt die gesamte
Kette — *Welten › Orte › Regionen › Städte*.

Nur Verschiebungen, die eine Schleife erzeugen würden, werden abgelehnt: Ein
Archiv kann nicht sein eigenes übergeordnetes Archiv sein und kann nicht einem
seiner eigenen Nachkommen untergeordnet werden. Eine abgelehnte Verschiebung
lässt das Archiv genau dort, wo es war. Das Löschen eines Archivs löscht nicht
seine Unterarchive: Sie rücken auf und nehmen seinen Platz ein, sodass der
Rest des Zweigs seine Form behält.

---

## Einträge

### Feldtypen

| Typ | Was es ist |
|---|---|
| Kurztext | Eine Zeile — ein Name, ein Beiname |
| Langtext | Einfacher mehrzeiliger Text |
| Rich-Text | Überschriften, Fett, Listen, Zitate, Links |
| Zahl | Sortierbar, mit optionaler Einheit ("Jahre", "Personen") |
| Datum | Ein Tag nach deinem eigenen Kalender — wird auf der Zeitleiste angezeigt und ist anklickbar |
| Ära | Eine Zeitspanne von Jahren, als Balken auf der Zeitleiste dargestellt, eingefärbt nach ihrem Archiv |
| Auswahl | Eine Option aus einer von dir definierten Liste |
| Tags | Mehrere Schlagwörter gleichzeitig, frei oder aus einer Liste |
| Bild | Hochgeladenes Bild, auf der Festplatte gespeichert |
| Banner | Ein breites Bild über dem Titel des Eintrags |
| Link zu Einträgen | Verweist auf andere Einträge — **Backlinks erscheinen automatisch** |
| Kartenfläche | Eine nachgezeichnete Form auf der Weltkarte |
| Kartenpunkt | Ein einzelner Pin auf der Weltkarte |
| Kartenpfad | Eine nachgezeichnete Route auf der Weltkarte |

Ein Link-Feld kann auf **eine beliebige Anzahl von Archiven** beschränkt oder
für alle offen gelassen werden, und kann ein oder mehrere Ziele erlauben.

Mehrere anzukreuzen ist wichtig, wenn etwas zu mehr als einer Art von Sache
gehört. Ein *Teil von*-Feld kann sowohl **Orte** als auch **Welten**
erreichen, sodass ein Ort zu einem anderen Ort *oder* zu einer ganzen Welt
gehören kann, im selben Feld.

### Felder werden archiviert, nicht gelöscht

Das Entfernen eines Feldes im Layout-Editor zerstört nichts. Das Feld wird
**archiviert**: Es verschwindet aus Formularen und Eintragsseiten, und jeder
Wert, den Einträge darin gespeichert haben, bleibt erhalten. *Layouts →
Felder* listet archivierte Felder mit der Anzahl der Einträge auf, die noch
Inhalte in jedem enthalten, und bietet **Zurückholen** an — was das Feld und
seinen Inhalt intakt wiederherstellt.

Ein Feld endgültig zu zerstören ist nur auf dieser Seite möglich.

### Tags

Tags können seitenweit durchsucht werden. Sie können unter **Einstellungen**
konfiguriert und entfernt werden.

### Banner

- **Seitenbanner** — *Einstellungen → Seitenbanner*. Ein Streifen ganz oben
  auf jeder Seite, über der Seitenleiste. Wird in der `settings`-Tabelle
  gespeichert und im JSON-Backup mitgeführt.
- **Eintragsbanner** — der Feldtyp `banner`. Zu jedem Layout hinzufügbar, wird
  über dem Titel des Eintrags in voller Breite dargestellt.

### Einträge kopieren und verschieben

Die Schaltfläche *Kopieren/Verschieben* auf der Seite eines Eintrags öffnet
einen Dialog mit zwei unabhängigen Auswahlmöglichkeiten: **Kopieren** (das
Original bleibt bestehen, ein neuer Eintrag erscheint an anderer Stelle) oder
**Verschieben** (derselbe Eintrag wird verlagert — nichts bleibt zurück),
sowie in welches Archiv er gesendet werden soll. Das eigene Archiv des
Eintrags mit Kopieren auszuwählen ist ein einfaches Duplikat.

Falls das Zielarchiv nicht dasselbe Layout wie der kopierte Eintrag hat, wird
eines mit denselben Feldern erstellt.

### Favoriten

Jeder Eintrag kann von seiner eigenen Seite aus angeheftet werden.
Favorisierte Einträge liegen in einem ausklappbaren Panel, das von jeder
Seite aus erreichbar ist, sodass die Dinge, an denen du gerade aktiv
arbeitest, nie mehr als einen Klick entfernt sind.

---

## Verbindungen

Jeder Eintrag und jedes Kapitel kann mit jedem anderen verbunden werden, in
beliebiger Kombination. Verbindungen werden von Hand aus der **rechten
Seitenleiste** gezogen: suchen, auswählen, fertig.

Die Seitenleiste bringt drei verschiedene Dinge an einem Ort zusammen:

| Abschnitt | Was es ist |
|---|---|
| *(gruppiert nach Archiv)* | Frei gezogene Verbindungen. Von beiden Seiten entfernbar. |
| **Verlinkt in Feldern** | Ziele der eigenen Relationsfelder des Eintrags. Diese im Eintragsformular bearbeiten. |
| **Referenziert von** | Andere Einträge, deren Relationsfelder *hierher* zeigen. Automatisch. |

Kann in *Einstellungen → Funktionen* deaktiviert werden, falls ein Wiki es
nicht braucht.

---

## Zeitleiste und Kalender

Jedes Datums- und Ära-Feld liegt auf **deinem eigenen Kalender**, nicht auf
einem generischen: benannte Monate (jeder mit eigener Tagesanzahl), benannte
Wochentage, optionale Schalttage-Blöcke (Tage, die zu keinem Monat gehören —
ein Jahresende zum Beispiel), Schaltjahresregeln und Feiertage, die jährlich
wiederkehren. Gestalte ihn unter *Einstellungen → Kalender gestalten*; jedes
Datums-/Ära-Feld in jedem Archiv folgt ihm sofort.

Zwei Ansichten auf dieselben Daten:

- **Zeitleiste** — jeder Datums- und Ära-Wert über alle Archive hinweg auf
  einer schwenk- und zoombaren Jahresachse. Punkte sind Punkte, Ären sind
  Balken, eingefärbt nach ihrem Archiv.
- **Kalender** — ein Monatsraster (plus die Schalttage-Streifen, falls
  vorhanden), mit denselben Punkten und Ären auf tatsächlichen Tagen
  angeordnet, Feiertage hervorgehoben.

Kann in *Einstellungen → Funktionen* deaktiviert werden, falls ein Wiki es
nicht braucht.

---

## Pinnwand

Ein visuelles Board, um nachzuvollziehen, wie Dinge zusammenhängen: einen
Eintrag anheften, anklicken, um alles damit Verbundene aufzuklappen, Pins zum
Anordnen ziehen. Zwei Arten von Fäden, unterschiedlich gezeichnet — eine
**Verbindung** (von Hand gezogen, liest sich von beiden Enden gleich) und ein
**Feld-Link** (gehört zum Relationsfeld eines Eintrags, zeigt in eine
Richtung und kann nur durch Bearbeiten des Eintrags geändert werden, der ihn
hält). Ein neuer Faden kann direkt auf dem Board von jeder Art von
Anfasspunkt aus begonnen werden.

Kann in *Einstellungen → Funktionen* deaktiviert werden, falls ein Wiki es
nicht braucht.

---

## Weltkarte

Mehrere Karten (eine Oberflächenkarte, eine unterirdische, oder was auch
immer die Welt braucht). Du kannst Formen, Pfade und Pins zur Karte
hinzufügen, um die Merkmale zu definieren:

- **Eine Form nachzeichnen** (ein Kartenflächen-Feld) — der Umriss einer
  Region.
- **Einen Pfad nachzeichnen** (ein Kartenpfad-Feld) — eine Route, ohne
  Innenfläche.
- **Einen Pin setzen** (ein Kartenpunkt-Feld) — ein einzelner Punkt mit
  eigenem Symbol.

Ein Eintrag mit einem dieser Elemente zeigt auf seiner eigenen Seite einen
zugeschnittenen **Ausschnitt** der Karte — nur den Bereich um seine Form,
nicht den ganzen Kontinent — und ein Klick darauf öffnet die vollständige
Karte, zentriert genau auf diese Stelle.

Kann in *Einstellungen → Funktionen* deaktiviert werden, falls ein Wiki es
nicht braucht.

---

## Entwurf und Geschichte

**Entwurf** ist die Werkstatt. Kapitel werden mit Titel, Erstellungsdatum und
letzter Bearbeitung aufgelistet; jedes hat einen Titel, Rich-Text-**Inhalt**,
Rich-Text-**Notizen**, eine Kapitelnummer und einen Schalter dafür, ob Leser
es sehen. Kapitelnummern akzeptieren Dezimalstellen, sodass 12.5 zwischen 12
und 13 liegt. Kapitel können unter einer Teil- oder Buchüberschrift gruppiert
werden.

**Geschichte** zeigt dieselben Kapitel, wie ein Leser sie vorfindet: nur die
als sichtbar markierten, in Nummernreihenfolge, ohne jegliche
Bearbeitungswerkzeuge. Notizen erscheinen dort nie.

Kann in *Einstellungen → Funktionen* deaktiviert werden, falls ein Wiki es
nicht braucht.

---

## Änderungshistorie

Jede Eintragsbearbeitung und -löschung wird aufgezeichnet — ein Schnappschuss
von Titel, Werten und Links, unmittelbar vor der Änderung erstellt —
erreichbar über *Einstellungen → Änderungshistorie*.

---

## Sprachen

Die Oberfläche selbst — Schaltflächen, Beschriftungen, Hilfetexte — kann in
mehr als einer Sprache angezeigt werden, wählbar unter *Einstellungen →
Sprache*.

---

## Export

*Export* in der Seitenleiste bietet zwei Downloads an, die strikt getrennt
gehalten werden — die Welt enthält nie Kapitel, das Buch nie Archive.

| Format | Was du bekommst |
|---|---|
| `.html` | Eine einzige eigenständige Datei — Styles eingebettet, Bilder als Data-URIs eingebettet, keine externen Anfragen. Verlinktes Inhaltsverzeichnis. Drucken → Als PDF speichern ergibt ein sauberes PDF; die Seitenumbrüche sind bereits im Stylesheet enthalten. |
| `.docx` | Ein Word-Dokument mit echten Überschriftenformaten, sodass Words Navigationsbereich funktioniert und Einfügen → Inhaltsverzeichnis alles findet. Öffnet sich in Word, LibreOffice und Google Docs. Kapitel behalten ihre Absatzumbrüche und Hervorhebungen; Eintragsfelder werden zu beschrifteten Abschnitten. |
| `.md` | Reines Markdown. Rich-Text wird korrekt umgewandelt — Überschriften verschachteln sich unterhalb ihres Containers, statt mit ihm zu kollidieren, und Listen, Zitate, Hervorhebungen und Links bleiben erhalten. |

### Sicherung und Wiederherstellung

Die HTML- und Markdown-Exporte sind zum Lesen gedacht. **Das JSON-Backup ist
dasjenige, das wieder eingespielt werden kann** — Archive, Layouts, jedes
Feld (auch archivierte), Einträge, Werte, Links, Verbindungen und Kapitel.

Jede Zeile trägt eine **GUID**: eine verborgene, dauerhafte Kennung.
Wiederherstellungen gleichen anhand dieser ab statt anhand von Namen, was
bedeutet:

- Ein Datensatz, den die Datei erkennt, wird **überschrieben**, selbst wenn
  du ihn seitdem umbenannt hast.
- Ein Datensatz, den die Datei nicht enthält, wird **vollständig
  unangetastet gelassen** — Arbeit, die nach dem Backup erledigt wurde,
  übersteht eine Wiederherstellung.
- Dieselbe Datei zweimal zu importieren ändert beim zweiten Mal nichts.
- Ein Backup lässt sich korrekt in eine *leere* Datenbank wiederherstellen,
  es ist also ein echtes Backup und kein bloßer Diff.

> Hochgeladene Bilder sind **nicht** im JSON enthalten. Bewahre eine Kopie
> von `public/uploads` daneben auf.

---

## Ausführen

```
wiki.bat
```

Startet PHPs eigenen Webserver unter <http://localhost:8080/> mittels
`public\router.php`. Nichts zu installieren, keine Administratorrechte
nötig, kein Server, der vorher gestartet werden müsste — die gesamte
Datenbank ist eine einzige SQLite-Datei in diesem Ordner
(`data/worldbuilder.sqlite`), die beim ersten Verbindungsaufbau erstellt
wird.

### Erstmalig auf einem neuen Rechner

```
setup.bat
```

Wendet jede Migration an und stellt, falls eine `backup\*.json`-Datei
vorhanden ist, diese wieder her. Kann gefahrlos mehrfach ausgeführt werden.
Danach reicht `wiki.bat`.

### Umzug auf einen anderen Computer

Anders als bei einer serverbasierten Datenbank liegt nichts außerhalb dieses
Ordners — kopiere das gesamte Projekt, und die SQLite-Datei ist dabei.

### Zurücksetzen auf Startdaten

```
reset_to_seed.bat
```

Für den Fall, dass du die Datenbank wirklich wegwerfen und mit den
Startarchiven aus `bin\seed_data.php` neu beginnen willst. Schreibt zuerst
ein vollständiges, zeitgestempeltes Backup nach `backup\` — wiederherstellbar
mit `bin\setup.php --restore`.

---


---

## Layout of the code

```
config/config.php          settings; copy to config.local.php for machine-specific ones
migrations/sqlite/         schema, one numbered file per change
bin/install.php            creates the database, applies pending migrations, seeds
bin/seed_data.php          the starter archives — plain data, edit freely
bin/setup.php              installs, then restores the newest backup/*.json if one is present
bin/reset_to_seed.php      wipes the database and rebuilds it from seed_data.php, backup first
bin/check_translations.php diffs every lang/language_*.php against the English catalog
lang/                      one file per interface language
src/                       Database, repositories, field types, sanitiser, controllers
views/                     templates
public/                    document root: front controller, CSS, JS, uploads
```

Twelve tables: `categories`, `layouts`, `layout_fields`, `entries`,
`entry_values`, `entry_links`, `entry_revisions`, `chapters`, `connections`,
`world_maps`, `settings`, `schema_migrations`. Field values live in
`entry_values`; relation-field targets live in `entry_links`, which is what
makes backlinks a single query instead of a scan; hand-drawn connections live in
`connections`, which stores each pair once in a canonical order so A–B and B–A
cannot both exist.

`bin/install.php` applies any migration files that have not run yet and records
them, so adding a numbered pair of `.sql` files under `migrations/` is all a
future schema change needs.

All runtime SQL is plain SQLite — `src/Database.php` is the one place the
connection itself is built.

---

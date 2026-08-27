# Worldbuilder

This readme was created using AI

A private archive for a fictional world. Archives down the left, entries in the
middle, the entry itself on the right — and the shape of an entry is something
you edit in the app, not in the code.

**Open it at:** http://localhost:8080/

---

## The idea in one paragraph

An **archive** (Characters, Species, Magic Systems, …) holds **entries**. Every
entry is built from a **layout**, and a layout is simply an ordered list of
**fields**. Editing a layout — adding a field, renaming one, dragging it higher
— changes every entry built from that layout, immediately. Layouts belong to one
archive; a Character layout cannot be used for a Species.

Renaming a field keeps what entries stored in it. Deleting a field deletes that
content, and the editor warns you before it does.

Alongside the archives sit **Draft** and **Story**, which are the book itself
rather than reference material — and a handful of cross-cutting tools
(**Timeline**, **Pinboard**, **World map**, **Search**) that work across every
archive at once.

---

## Archives

### Nesting archives

An archive can sit under another one, **to any depth**. The sidebar indents each
level against a guide line, every ancestor of the open archive stays lit, and
breadcrumbs read the whole chain — *Worlds › Locations › Regions › Cities*. Each
one with children also gets a small triangle to its left in the sidebar to
collapse or expand its own sub-archives; the choice is remembered per archive,
except the branch containing whatever page you're actually on, which always
stays visible regardless of what's remembered.

The only moves refused are the ones that would make a loop: an archive cannot be
its own parent, and it cannot be filed under one of its own descendants. The
parent picker hides that whole branch, and the repository refuses it as well, so
a value posted by hand cannot produce a cycle either. If a cycle ever reached the
table anyway — through a hand-edited backup — the tree builder detects it and
treats those rows as top level rather than looping forever.

A refused move leaves the archive exactly where it was. Deleting an archive does
not delete its children: they move up to take its place, so the rest of the
branch keeps its shape.

Set it in the app: *Manage archives → Edit → "Sits under"*. Reordering by drag is
restricted to siblings, so dragging a sub-archive can never look like a
re-parent it is not.

### Systems vs. Laws

Two archives that are easy to confuse, so the line is drawn deliberately:

- A **system** is a standing structure — who holds power, how it is organised,
  how it transfers. Its `Kind` flag has twelve values, most of them not
  political: Military, Judicial, Economic, Social, Religious, Educational,
  Logistical, Magical, Infernal, Natural, Other.
- A **law** is a rule that binds people, with a jurisdiction and a consequence.
  It has fields a system does not — penalty, enforcement, who enacted it — and a
  **Part of** field pointing back at the system it belongs to.

Because laws point at systems, a system's page lists its own laws automatically
under *Referenced by* in the rail.

Not every law is a statute. `Kind` covers Decree, Prohibition, Duty,
Constitutional rule, Custom, Unwritten and Taboo, and `Status` can say *Never
written down* or *Selectively ignored* rather than pretending otherwise.

An archive can also choose the order it opens in. A **Chronological** order
sorts on the entry's Date/Era field and puts undated entries last.

### Sorting by a choice field

Any **Choice** field in a layout can be ticked *Sort the entry list by this*.
It then appears under *By choice* in the sort menu above the entry list, and in
an archive's "Opens sorted by" setting.

Entries follow the order the options are written in the layout — tick it on a
Status field listing Alive, Missing, Dead and that is the order you get, not
alphabetical. Entries that chose nothing come last, and titles order A–Z within
each group. If an archive has several layouts that each carry a *Status*, one
menu entry covers them all; untick the field, or archive it, and the option
disappears while any list still asking for it quietly falls back to A–Z.

Whatever you pick sticks. The order is remembered per archive for the rest of
the session — open an entry, edit it, look at the layouts, come back, and the
list is still the way you left it, filter included. History can sit
chronologically while Characters sits sorted by Status. "Opens sorted by" is
where an archive starts before you have chosen anything.

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
belong to another place *or* to a whole world, in the same field. Nesting an
archive under another does not do this on its own — the sidebar tree and what a
field may point at are deliberately separate, so moving an archive never
silently changes what its entries can link to.

Fields written before this allowed only one archive; they keep working exactly as
they did, and widening one is a matter of ticking another box.

### Fields are archived, not deleted

Removing a field in the layout editor does not destroy anything. The field is
**archived**: it disappears from forms and entry pages, and every value entries
stored in it is kept. *Layouts → Fields* lists archived fields with how many
entries still hold content in each, and offers **Put it back** — which restores
the field and its content intact.

Destroying a field for good happens only on that page, only for a field that is
already archived, and only after typing its name. Both guards are enforced
server-side.

### Tags

A tag is not a row anywhere. A tags field stores its values as a JSON list
inside `entry_values`, and the suggestion lists are derived from those values
live. So a tag exists exactly as long as an entry names it and **stops existing
the moment none does** — there is nothing to clean up and no orphans to collect.

*Settings → Tags* shows the whole vocabulary: every tag, how many entries use
it, which archives and fields it appears in, and any variant spellings folded
together. Deleting one there removes it from **every** entry at once, and from
any layout that offered it as a predefined option. Other tags on those entries
are untouched.

The one thing that does persist unused is a tag listed in a tags field's
predefined **Options** — that belongs to the layout rather than to any entry, so
it stays until removed from the layout (or deleted from this page).

### Banners

Two, independent of each other:

- **Site banner** — *Settings → Site banner*. A strip across the very top of
  every page, above the sidebar. Stored in the `settings` table and carried in
  the JSON backup.
- **Entry banner** — the `banner` field type. Add it to any layout and it renders
  full width above that entry's title.

### Copying and moving entries

The *Duplicate* button on an entry's page opens a dialog with two independent
choices: **Copy** (the original stays put, a new entry appears elsewhere) or
**Move** (the same entry relocates — nothing is left behind), and which archive
to send it to. Picking the entry's own archive with Copy is a plain in-place
duplicate, same as before the dialog existed.

The destination archive doesn't need a matching layout ahead of time: if none of
its layouts already have the exact same fields (same label and type each, order
doesn't matter), one is built there automatically before the entry lands, so
nothing about it is ever lost in the move.

Under the hood, `EntryRepo::moveToCategory()` keeps the entry's id, so its
connections, backlinks and inbound links all survive; `EntryRepo::duplicate()`
remaps field values by label when the destination isn't the entry's original
layout.

### Favorites

Any entry can be pinned from its own page. Favorited entries sit in a fold-out
panel reachable from every page, closest edge first, so the things you're
actively working on are never more than a click away — independent of which
archive they're filed under.

### Pickers never offer what is already chosen

Relation fields, the connections rail and the tag input all leave out anything
already picked — filtered in the SQL, so the result limit is spent on usable
choices rather than on options that would be refused. The tag suggestion chips
hide and reappear as tags are added and removed.

---

## Connections

Every entry and every chapter can be connected to every other one, in any
combination. Connections are drawn by hand from the **right-hand rail**: search,
pick, done. They read the same from both ends — connect a chapter to a character
and the character's page shows the chapter.

The rail gathers three different things in one place:

| Section | What it is |
|---|---|
| *(grouped by archive)* | Free-form connections you drew. Removable from either side. |
| **Linked in fields** | Targets of the entry's own relation fields. Edit these in the entry form. |
| **Referenced by** | Other entries whose relation fields point *here*. Automatic. |

Turn this whole feature off from *Settings → Features* if a wiki doesn't need it
— nothing already drawn is lost, it's just hidden until switched back on.

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

Both share an **archive filter** so a crowded wiki can be narrowed down, and
both link straight back to the entry that owns whatever you're looking at.

An epoch name and abbreviation (*Settings → Timeline*) is what turns a bare year
into "204 A.F." everywhere one is shown; leave it blank and years show as plain
numbers.

Switched off from *Settings → Features* like Connections and Draft/Story above
— hides both views without touching any Date or Era value already stored.

---

## Pinboard

A visual board for tracing how things connect: pin an entry, click it to open
out everything tied to it, drag pins to arrange them. Two kinds of string,
drawn differently — a **connection** (hand-drawn, reads the same from both
ends) and a **field link** (belongs to one entry's relation field, points one
way, and can only be changed by editing the entry that holds it). A new string
can be started from either kind of handle directly on the board.

The board force-arranges freshly-opened pins so they don't overlap, but never
moves a pin you've placed yourself. Chapters don't appear here — the pinboard is
entries only; a chapter's own connections still show in its rail.

Shares the Connections feature flag — off there means off here too.

---

## World map

Multiple layers (a surface map, an underground one, a set of floating islands —
whatever a world needs) sharing one coordinate space, so a shape traced on one
layer aligns with the others. Three ways to mark a place:

- **Trace a shape** (a Map area field) — a region's outline.
- **Trace a path** (a Map path field) — a route, with no interior to fill.
- **Drop a pin** (a Map point field) — a single spot, with its own symbol.

An entry with any of these shows a cropped **cutout** of the map on its own
page — just the area around its shape, not the whole continent — and clicking
it opens the full map centred on exactly that spot.

Switched off from *Settings → Features* like the others above.

---

## Draft and Story

**Draft** is the workshop. Chapters list with their title, creation date and last
edit; each one has a title, rich-text **content**, rich-text **notes**, a chapter
number, and a switch for whether readers see it. Chapter numbers accept decimals,
so 12.5 sits between 12 and 13. Chapters can be grouped under a part or book
heading.

**Story** is the same chapters as a reader meets them: only the ones flagged as
shown, in number order, with no editing controls anywhere. Notes never appear
there, and a hidden chapter's URL returns 404 rather than leaking it.

Like Connections, this whole feature can be switched off from *Settings →
Features* without losing anything already written.

---

## Search

One search box, reachable from every page, that looks across every archive
(and chapters, if the book is switched on) at once — title, then every field's
text content. Results group by archive, same as the sidebar does.

---

## Change history

Every entry edit and deletion is recorded — a snapshot of the title, values and
links, taken right before the change lands — reachable from *Settings → Change
history*. Two things you can do with a revision: see exactly **what changed**
(a field-by-field diff), or **restore** it, which brings the entry back even if
it was deleted since. A restored deletion re-creates the entry; a restored edit
overwrites the current values.

---

## Languages

The interface itself — buttons, labels, help text — can be shown in more than
one language, picked at *Settings → Language*. Adding one means a new
`lang/language_{code}.php` file and a line in `App\Locales`;
`bin\check_translations.php` diffs every catalog against English and flags
missing or seemingly-untranslated strings. This is separate from your own
content — nothing about what you write is ever translated for you.

---

## Export

*Export* in the sidebar offers two downloads, kept strictly apart — the world
never contains chapters, the book never contains archives.

**The world** walks every archive in sidebar order, sub-archives following their
parent, each entry rendered through its layout's fields. An archive with a
chronological order keeps it. Optionally includes connections and backlinks, and
empty fields.

**The book** is the chapters in reading order, with a word count on the cover.
Hidden chapters and your private notes are left out unless you tick them.

| Format | What you get |
|---|---|
| `.html` | One self-contained file — styles inlined, images embedded as data URIs, zero external requests. Linked table of contents. Print → Save as PDF gives a clean PDF; the page breaks are already in the stylesheet. |
| `.docx` | A Word document with real heading styles, so Word's navigation pane works and Insert → Table of Contents finds everything. Opens in Word, LibreOffice and Google Docs. Chapters keep their paragraph breaks and emphasis; entry fields become labelled sections. |
| `.md` | Plain Markdown. Rich text is converted properly — headings nest below their container rather than colliding with it, and lists, quotes, emphasis and links all survive. |

The `.docx` writer needs no PHP extensions. PHP's `zip` extension often isn't
enabled, and asking anyone to edit `php.ini` before they can save a Word file is
a poor trade for eighty lines, so `App\Export\Zip` writes the package directly —
stored, uncompressed, which Word reads perfectly well. Lists are styled
paragraphs carrying their own marker rather than Word numbering, which would
need a numbering part and buys nothing for a document nobody will renumber.

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

A restore always shows you what it would do first — counts of what will be
created and overwritten, produced by running the import inside a transaction
that is then rolled back, so the numbers are exact rather than estimated.

> Uploaded images are **not** inside the JSON. Keep a copy of `public/uploads`
> beside it.

---

## Running it

```
serve.bat
```

Starts PHP's own web server on <http://localhost:8080/> using
`public\router.php`. Nothing to install, no administrator rights, no server to
start beforehand — the whole database is one SQLite file inside this folder
(`data/worldbuilder.sqlite`), created the first time anything connects to it.
This is also the easiest way to run the project straight off a USB stick on a
machine you do not own.

`serve.bat` looks for the copy of PHP that ships with the project (`php\php.exe`)
before falling back to whatever `php` is on your PATH. Every `.bat` in this
folder follows the same rule, and passes through any arguments given after the
batch file's own name straight to the PHP script underneath.

### First time on a new machine

```
setup.bat
```

Applies every migration and, if a `backup\*.json` file is present, restores it.
Safe to run more than once. After that, `serve.bat` is all you need.

### Moving it to another computer

Unlike a server-backed database, nothing lives outside this folder — copy the
whole project and the SQLite file comes with it. The only exception is a plain
`git clone`, since `data/` is git-ignored (it's the live database, not source):
in that case, put a JSON backup in `backup\` and run `setup.bat` once to load it.

### Re-running the installer

```
php bin\install.php
```

Safe to repeat: it skips whatever already exists.

### Resetting to seed data

```
reset_to_seed.bat
```

For when you genuinely want to throw the database away and start over from
`bin\seed_data.php`'s starter archives. Asks for a `y`/`n` confirmation, writes
a full timestamped backup to `backup\` first — restorable with
`bin\setup.php --restore` — and only then wipes and reseeds. Pass `--yes` (or
run `php bin\reset_to_seed.php --yes` directly) to skip the prompt for scripted
use.

---

## Two versions of this project

This project lives as two long-running branches with the same code but
different content:

- **`main`** — your own archive, full of entries.
- **`template`** — the same software with the database emptied back to just
  its archive/layout structure (no entries, chapters, connections or history),
  and none of the one-off scripts that were used to build the content on
  `main`. This is the version to hand to someone who wants the tool, not your
  world.

Code changes (`src/`, `views/`, `public/`, `config/`, `migrations/`, this file,
the generic `bin/` tooling) are meant to reach both branches — make the change
as its own commit on whichever branch you're working in, then
`git cherry-pick` it onto the other. Cherry-pick rather than merge: a full
merge would fight over `backup/worldbuilder-backup-newest.json` (which is
supposed to differ between the branches) and could delete or resurrect
`main`'s content-specific `bin/` scripts depending on which direction it ran.
Entry-content changes — exporting a fresh backup after editing in the app —
only ever belong on `main`.

One thing this doesn't do on its own: switching branches doesn't switch your
data. `data/worldbuilder.sqlite` is git-ignored, so it belongs to neither
branch — it's just whatever file happens to be sitting in this folder. Running
two versions side by side for real means two separate folder copies (same idea
as moving the project to another computer, above), each restored from its own
branch's `backup/worldbuilder-backup-newest.json`.

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

## Notes

- **No login.** The site assumes one person on localhost. Anyone who can reach
  the URL can edit everything — worth remembering before exposing the port.
- **Rich text is sanitised on save** against a tag whitelist, so pasted markup
  can't smuggle in scripts.
- **Uploads** land in `public/uploads/YYYY/MM` under random names, are verified
  to be real images by inspecting the bytes, and the folder is configured to
  refuse to execute anything.
- **Deleting an archive** deletes its layouts and every entry inside it, which is
  why it makes you type the name.
- **`php bin\install.php --fresh`** and **`reset_to_seed.bat`** both drop every
  table and rebuild — the difference is the latter asks first and backs up
  first. Prefer it unless you're scripting something that already handles both.

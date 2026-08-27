-- Snapshot of an entry's title/values/links, taken before an edit or delete.
-- No FK to entries: a revision must outlive its row. entry_id is nulled on
-- delete (EntryRepo::delete) so a reused id can't inherit history; entry_guid
-- is what survives.

CREATE TABLE entry_revisions (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    entry_id    INTEGER NULL,
    entry_guid  TEXT NOT NULL,
    category_id INTEGER NULL,
    layout_id   INTEGER NULL,
    title       TEXT NOT NULL,
    kind        TEXT NOT NULL,
    values_json TEXT NULL,
    links_json  TEXT NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX entry_revisions_entry_idx ON entry_revisions (entry_id);
CREATE INDEX entry_revisions_created_idx ON entry_revisions (created_at);

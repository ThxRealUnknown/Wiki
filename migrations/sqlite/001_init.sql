-- Worldbuilder schema — SQLite, mirroring migrations/mysql and migrations/pgsql.
--
-- A column only auto-increments if declared exactly INTEGER PRIMARY KEY (not
-- INT). ALTER TABLE can't add a constraint, so parent_id's FK is inline here.
-- Foreign keys are off by default in SQLite; src/Database.php enables them
-- per connection, or the ON DELETE CASCADE rules below are decorative.

CREATE TABLE categories (
    id           INTEGER PRIMARY KEY AUTOINCREMENT,
    name         TEXT NOT NULL,
    slug         TEXT NOT NULL,
    icon         TEXT NULL,
    color        TEXT NULL,
    description  TEXT NULL,
    sort_order   INTEGER NOT NULL DEFAULT 0,
    default_sort TEXT NOT NULL DEFAULT 'title',
    parent_id    INTEGER NULL REFERENCES categories (id) ON DELETE SET NULL,
    guid         TEXT NULL,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT categories_slug_unique UNIQUE (slug)
);

CREATE INDEX categories_parent_idx ON categories (parent_id);
CREATE UNIQUE INDEX categories_guid_unique ON categories (guid);

CREATE TABLE layouts (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    category_id INTEGER NOT NULL,
    name        TEXT NOT NULL,
    is_default  INTEGER NOT NULL DEFAULT 0,
    sort_order  INTEGER NOT NULL DEFAULT 0,
    guid        TEXT NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT layouts_category_fk FOREIGN KEY (category_id)
        REFERENCES categories (id) ON DELETE CASCADE
);

CREATE INDEX layouts_category_idx ON layouts (category_id);
CREATE UNIQUE INDEX layouts_guid_unique ON layouts (guid);

CREATE TABLE layout_fields (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    layout_id   INTEGER NOT NULL,
    field_key   TEXT NOT NULL,
    label       TEXT NOT NULL,
    field_type  TEXT NOT NULL,
    help        TEXT NULL,
    width       TEXT NOT NULL DEFAULT 'full',
    config      TEXT NULL,
    sort_order  INTEGER NOT NULL DEFAULT 0,
    guid        TEXT NULL,
    archived_at DATETIME NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT layout_fields_layout_fk FOREIGN KEY (layout_id)
        REFERENCES layouts (id) ON DELETE CASCADE,
    CONSTRAINT layout_fields_key_unique UNIQUE (layout_id, field_key)
);

CREATE UNIQUE INDEX layout_fields_guid_unique ON layout_fields (guid);
CREATE INDEX layout_fields_archived_idx ON layout_fields (layout_id, archived_at);

CREATE TABLE entries (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    category_id INTEGER NOT NULL,
    layout_id   INTEGER NOT NULL,
    title       TEXT NOT NULL,
    slug        TEXT NOT NULL,
    guid        TEXT NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT entries_category_fk FOREIGN KEY (category_id)
        REFERENCES categories (id) ON DELETE CASCADE,
    CONSTRAINT entries_layout_fk FOREIGN KEY (layout_id)
        REFERENCES layouts (id) ON DELETE RESTRICT,
    CONSTRAINT entries_slug_unique UNIQUE (category_id, slug)
);

CREATE INDEX entries_layout_idx ON entries (layout_id);
CREATE INDEX entries_title_idx ON entries (title);
CREATE UNIQUE INDEX entries_guid_unique ON entries (guid);

CREATE TABLE entry_values (
    id           INTEGER PRIMARY KEY AUTOINCREMENT,
    entry_id     INTEGER NOT NULL,
    field_id     INTEGER NOT NULL,
    value_text   TEXT NULL,
    value_number REAL NULL,
    CONSTRAINT entry_values_entry_fk FOREIGN KEY (entry_id)
        REFERENCES entries (id) ON DELETE CASCADE,
    CONSTRAINT entry_values_field_fk FOREIGN KEY (field_id)
        REFERENCES layout_fields (id) ON DELETE CASCADE,
    CONSTRAINT entry_values_unique UNIQUE (entry_id, field_id)
);

CREATE TABLE entry_links (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    entry_id        INTEGER NOT NULL,
    field_id        INTEGER NOT NULL,
    target_entry_id INTEGER NOT NULL,
    sort_order      INTEGER NOT NULL DEFAULT 0,
    CONSTRAINT entry_links_entry_fk FOREIGN KEY (entry_id)
        REFERENCES entries (id) ON DELETE CASCADE,
    CONSTRAINT entry_links_field_fk FOREIGN KEY (field_id)
        REFERENCES layout_fields (id) ON DELETE CASCADE,
    CONSTRAINT entry_links_target_fk FOREIGN KEY (target_entry_id)
        REFERENCES entries (id) ON DELETE CASCADE
);

CREATE INDEX entry_links_source_idx ON entry_links (entry_id, field_id);
CREATE INDEX entry_links_target_idx ON entry_links (target_entry_id);

CREATE TABLE chapters (
    id             INTEGER PRIMARY KEY AUTOINCREMENT,
    title          TEXT NOT NULL,
    slug           TEXT NOT NULL,
    chapter_number REAL NULL,
    content        TEXT NULL,
    notes          TEXT NULL,
    is_visible     INTEGER NOT NULL DEFAULT 0,
    guid           TEXT NULL,
    created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT chapters_slug_unique UNIQUE (slug)
);

CREATE INDEX chapters_number_idx ON chapters (chapter_number);
CREATE UNIQUE INDEX chapters_guid_unique ON chapters (guid);

-- Joins any two of entry/chapter, pair stored once and canonically ordered so
-- A–B and B–A can't both exist. No FK (two possible target tables), so
-- repositories clean up after a delete.
CREATE TABLE connections (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    a_type     TEXT NOT NULL,
    a_id       INTEGER NOT NULL,
    b_type     TEXT NOT NULL,
    b_id       INTEGER NOT NULL,
    note       TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT connections_pair_unique UNIQUE (a_type, a_id, b_type, b_id)
);

CREATE INDEX connections_a_idx ON connections (a_type, a_id);
CREATE INDEX connections_b_idx ON connections (b_type, b_id);

CREATE TABLE settings (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    name       TEXT NOT NULL,
    value      TEXT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT settings_name_unique UNIQUE (name)
);

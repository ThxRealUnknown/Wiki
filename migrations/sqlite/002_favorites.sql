-- A column rather than a table: a favourite is a property of the entry and
-- should vanish with it. A timestamp rather than a flag so it also records
-- when something was pinned.

ALTER TABLE entries ADD COLUMN favorited_at DATETIME NULL;

CREATE INDEX entries_favorited_idx ON entries (favorited_at);

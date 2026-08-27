-- An entry can be archived rather than deleted: it stops appearing anywhere
-- else in the wiki until restored. Mirrors layout_fields.archived_at.

ALTER TABLE entries ADD COLUMN archived_at DATETIME NULL;

CREATE INDEX entries_archived_idx ON entries (category_id, archived_at);

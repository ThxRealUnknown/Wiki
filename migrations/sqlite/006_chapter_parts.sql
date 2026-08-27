-- Chapters can be grouped under a part/book heading. Plain text rather than a
-- table: a part has no properties beyond its name, and reordering parts falls
-- out of reordering chapter_number. Nothing enforces spelling consistency.

ALTER TABLE chapters ADD COLUMN part TEXT NULL;

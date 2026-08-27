-- A relation field can optionally be "typed": each link then carries a label
-- from a list the field defines (Mother, Brother, Ally...). A column rather
-- than a table, since the label is a property of one link, not its own thing.

ALTER TABLE entry_links ADD COLUMN relation_type TEXT NULL;

-- Map layers, no longer a fixed set baked into the code. Seeded under the
-- existing slugs so it's a no-op for entries that already point at one.

CREATE TABLE world_maps (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    slug       TEXT NOT NULL,
    label      TEXT NOT NULL,
    image      TEXT NULL,
    sort_order INTEGER NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT world_maps_slug_unique UNIQUE (slug)
);

CREATE INDEX world_maps_sort_idx ON world_maps (sort_order);

INSERT INTO world_maps (slug, label, image, sort_order, created_at) VALUES
    ('sky', 'Floating Islands', 'uploads/worldmap/map_test_sky.jpg', 0, CURRENT_TIMESTAMP),
    ('surface', 'Continents', 'uploads/worldmap/map_test_surface.jpg', 1, CURRENT_TIMESTAMP),
    ('deep', 'Deep Cities', 'uploads/worldmap/map_test_underground.jpg', 2, CURRENT_TIMESTAMP);

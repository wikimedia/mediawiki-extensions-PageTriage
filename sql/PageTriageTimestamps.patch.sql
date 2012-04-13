-- Update table to have separate timestamps for created and updated
ALTER TABLE /*_*/pagetriage_page CHANGE ptrp_timestamp ptrp_created VARBINARY( 14 ) NOT NULL;
ALTER TABLE /*_*/pagetriage_page ADD ptrp_tags_updated VARBINARY( 14 ) NOT NULL;
CREATE INDEX /*i*/ptrp_tags_updated ON /*_*/pagetriage_page (ptrp_tags_updated);
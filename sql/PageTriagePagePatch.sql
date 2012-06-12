ALTER TABLE /*_*/pagetriage_page ADD COLUMN ptrp_reviewed_updated VARBINARY(14) NOT NULL;
ALTER TABLE /*_*/pagetriage_page ADD COLUMN ptrp_last_reviewed_by int unsigned NOT NULL default 0;
CREATE INDEX /*i*/ptrp_reviewed_updated ON /*_*/pagetriage_page (ptrp_reviewed_updated);

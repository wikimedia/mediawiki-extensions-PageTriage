-- Store the list of articles to be reviewed or being reviewed already
CREATE TABLE /*_*/pagetriage_page (
	ptrp_page_id int unsigned NOT NULL PRIMARY KEY,
	ptrp_reviewed tinyint unsigned NOT NULL DEFAULT 0,
	ptrp_deleted tinyint unsigned NOT NULL DEFAULT 0,
	ptrp_created VARBINARY(14) NOT NULL,
	ptrp_tags_updated VARBINARY(14) NOT NULL
) /*$wgDBTableOptions*/;

CREATE INDEX /*i*/ptrp_reviewed_created_page_del ON /*_*/pagetriage_page (ptrp_reviewed, ptrp_created, ptrp_page_id, ptrp_deleted);
CREATE INDEX /*i*/ptrp_created_page_del ON /*_*/pagetriage_page (ptrp_created, ptrp_page_id, ptrp_deleted);
CREATE INDEX /*i*/ptrp_del_created_page_reviewed ON /*_*/pagetriage_page (ptrp_deleted, ptrp_created, ptrp_page_id, ptrp_reviewed);
CREATE INDEX /*i*/ptrp_updated_page_reviewed ON /*_*/pagetriage_page (ptrp_tags_updated, ptrp_page_id, ptrp_reviewed);

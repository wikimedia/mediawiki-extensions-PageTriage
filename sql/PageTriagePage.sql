-- Store the list of articles to be reviewed or being reviewed already
CREATE TABLE /*_*/pagetriage_page (
	ptrp_page_id int unsigned NOT NULL PRIMARY KEY,
	ptrp_reviewed tinyint unsigned not null default 0,
	ptrp_timestamp varbinary(14) NOT NULL COMMENT 'when the page was created'
) /*$wgDBTableOptions*/;

CREATE INDEX /*i*/ptrp_reviewed_timestamp_page_id ON /*_*/pagetriage_page (ptrp_reviewed, ptrp_timestamp, ptrp_page_id);
CREATE INDEX /*i*/ptrp_timestamp_page_id ON /*_*/pagetriage_page (ptrp_timestamp, ptrp_page_id);

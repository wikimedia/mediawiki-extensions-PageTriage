-- Store the list of articles to be triaged or being triaged already
CREATE TABLE /*_*/pagetriage_page (
	ptrp_id int unsigned NOT NULL PRIMARY KEY auto_increment,
	ptrp_page_id int unsigned NOT NULL,
	ptrp_triaged tinyint unsigned not null default 0,
	ptrp_timestamp varbinary(14) NOT NULL
) /*$wgDBTableOptions*/;

CREATE UNIQUE INDEX /*i*/ptrp_page_id ON /*_*/pagetriage_page (ptrp_page_id);
CREATE INDEX /*i*/ptrp_timestamp_triaged_id ON /*_*/pagetriage_page (ptrp_triaged, ptrp_timestamp, ptrp_id);

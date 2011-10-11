
-- mapping table for user to pages
CREATE TABLE /*_*/pagetriage (
	ptr_user_id int UNSIGNED NOT NULL,
	ptr_recentchanges_id int NOT NULL,
	ptr_timestamp varbinary(14) NOT NULL
) /*$wgDBTableOptions*/;

CREATE UNIQUE INDEX /*i*/ptr_user_rc ON /*_*/pagetriage (ptr_user_id,ptr_recentchanges_id);

-- this stores when a page was checked.  we'll be interested in that sometimes.
CREATE INDEX /*i*/ptr_timestamp ON /*_*/pagetriage (ptr_timestamp);
-- Store the triage log for articles
CREATE TABLE /*_*/pagetriage_log (
	ptrl_id int unsigned NOT NULL PRIMARY KEY auto_increment,
	ptrl_page_id int unsigned NOT NULL,
	ptrl_user_id int unsigned NOT NULL,
	ptrl_reviewed tinyint unsigned not null default 0,
	ptrl_timestamp varbinary(14) NOT NULL
) /*$wgDBTableOptions*/;

CREATE INDEX /*i*/ptrl_page_id_timestamp ON /*_*/pagetriage_log (ptrl_page_id, ptrl_timestamp);
CREATE INDEX /*i*/ptrl_timestamp ON /*_*/pagetriage_log (ptrl_timestamp);

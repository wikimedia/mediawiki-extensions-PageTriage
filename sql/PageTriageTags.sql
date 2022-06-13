-- Article metadata types
CREATE TABLE /*_*/pagetriage_tags (
	ptrt_tag_id int unsigned NOT NULL PRIMARY KEY auto_increment,
	ptrt_tag_name varbinary(20) NOT NULL,
	ptrt_tag_desc varbinary(255) NOT NULL
) /*$wgDBTableOptions*/;

CREATE UNIQUE INDEX /*i*/ptrt_tag_id ON /*_*/pagetriage_tags (ptrt_tag_name);

-- Store the metadata for article to be reviewed
CREATE TABLE /*_*/pagetriage_page_tags (
	ptrpt_page_id int unsigned NOT NULL,
	ptrpt_tag_id int unsigned NOT NULL,
	ptrpt_value varbinary(255) NOT NULL,

	PRIMARY KEY (ptrpt_page_id,ptrpt_tag_id)
) /*$wgDBTableOptions*/;

CREATE INDEX /*i*/ptrpt_tag_id_value ON /*_*/pagetriage_page_tags (ptrpt_tag_id,ptrpt_value);

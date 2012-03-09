-- Store the metadata for article to be triaged
CREATE TABLE /*_*/pagetriage_page_tags (
	ptrpt_page_id int unsigned NOT NULL,
	ptrpt_tag_id int unsigned NOT NULL,
	ptrpt_value varbinary(255) NOT NULL
) /*$wgDBTableOptions*/;

CREATE UNIQUE INDEX /*i*/ptrpt_page_tag_id ON /*_*/pagetriage_page_tags (ptrpt_page_id,ptrpt_tag_id);
CREATE INDEX /*i*/ptrpt_tag_id_value ON /*_*/pagetriage_page_tags (ptrpt_tag_id,ptrpt_value);

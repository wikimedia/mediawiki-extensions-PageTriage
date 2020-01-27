-- Convert unique index to primary key
-- See T243073
ALTER TABLE /*_*/pagetriage_page_tags
DROP INDEX /*i*/ptrpt_page_tag_id,
ADD PRIMARY KEY (ptrpt_page_id,ptrpt_tag_id);

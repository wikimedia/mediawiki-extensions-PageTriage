-- Article metadata types
CREATE TABLE /*_*/pagetriage_tags (
	ptrt_tag_id int unsigned NOT NULL PRIMARY KEY auto_increment,
	ptrt_tag_name varbinary(20) NOT NULL,
	ptrt_tag_desc varbinary(255) NOT NULL
) /*$wgDBTableOptions*/;

CREATE UNIQUE INDEX /*i*/ptrt_tag_id ON /*_*/pagetriage_tags (ptrt_tag_name);

INSERT INTO /*_*/pagetriage_tags (ptrt_tag_name, ptrt_tag_desc)
VALUES ('linkcount', 'Number of inbound links');
INSERT INTO /*_*/pagetriage_tags (ptrt_tag_name, ptrt_tag_desc)
VALUES ('category_count', 'Category mapping count');
INSERT INTO /*_*/pagetriage_tags (ptrt_tag_name, ptrt_tag_desc)
VALUES ('csd_status', 'CSD status');
INSERT INTO /*_*/pagetriage_tags (ptrt_tag_name, ptrt_tag_desc)
VALUES ('prod_status', 'PROD status');
INSERT INTO /*_*/pagetriage_tags (ptrt_tag_name, ptrt_tag_desc)
VALUES ('blp_prod_status', 'BLP PROD status');
INSERT INTO /*_*/pagetriage_tags (ptrt_tag_name, ptrt_tag_desc)
VALUES ('afd_status', 'AFD status');
INSERT INTO /*_*/pagetriage_tags (ptrt_tag_name, ptrt_tag_desc)
VALUES ('rev_count', 'Number of edits to the article');
INSERT INTO /*_*/pagetriage_tags (ptrt_tag_name, ptrt_tag_desc)
VALUES ('page_len', 'Number of bytes of article');
INSERT INTO /*_*/pagetriage_tags (ptrt_tag_name, ptrt_tag_desc)
VALUES ('snippet', 'Beginning of article snippet');
INSERT INTO /*_*/pagetriage_tags (ptrt_tag_name, ptrt_tag_desc)
VALUES ('user_name', 'User name');
INSERT INTO /*_*/pagetriage_tags (ptrt_tag_name, ptrt_tag_desc)
VALUES ('user_editcount', 'User total edit');
INSERT INTO /*_*/pagetriage_tags (ptrt_tag_name, ptrt_tag_desc)
VALUES ('user_creation_date', 'User registration date');
INSERT INTO /*_*/pagetriage_tags (ptrt_tag_name, ptrt_tag_desc)
VALUES ('user_autoconfirmed', 'Check if user is autoconfirmed' );
INSERT INTO /*_*/pagetriage_tags (ptrt_tag_name, ptrt_tag_desc)
VALUES ('user_experience', 'Experience level: newcomer, learner, experienced or anonymous' );
INSERT INTO /*_*/pagetriage_tags (ptrt_tag_name, ptrt_tag_desc)
VALUES ('user_bot', 'Check if user is in bot group');
INSERT INTO /*_*/pagetriage_tags (ptrt_tag_name, ptrt_tag_desc)
VALUES ('user_block_status', 'User block status');
INSERT INTO /*_*/pagetriage_tags (ptrt_tag_name, ptrt_tag_desc)
VALUES ('user_id', 'User id');
INSERT INTO /*_*/pagetriage_tags (ptrt_tag_name, ptrt_tag_desc)
VALUES ('reference', 'Check if page has references');
INSERT INTO /*_*/pagetriage_tags (ptrt_tag_name, ptrt_tag_desc)
VALUES ('afc_state', 'The submission state of drafts');
INSERT INTO /*_*/pagetriage_tags (ptrt_tag_name, ptrt_tag_desc)
VALUES ('copyvio', 'Latest revision ID that has been tagged as a likely copyright violation, if any');
INSERT INTO /*_*/pagetriage_tags (ptrt_tag_name, ptrt_tag_desc)
VALUES ('recreated', 'Check if the page has been previously deleted.');

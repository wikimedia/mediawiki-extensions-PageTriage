-- Article metadata types
CREATE TABLE /*_*/pagetriage_tags (
	ptrt_tag_id int unsigned NOT NULL PRIMARY KEY auto_increment,
	ptrt_tag_name varbinary(20) NOT NULL,
	ptrt_tag_desc varbinary(255) NOT NULL
) /*$wgDBTableOptions*/;

CREATE UNIQUE INDEX /*i*/ptrt_tag_id ON /*_*/pagetriage_tags (ptrt_tag_name);

INSERT INTO pagetriage_tags (ptrt_tag_name, ptrt_tag_desc) 
VALUES 
('title', 'Article title'),
('linkcount', 'Number of inbound links'),
('category_count', 'Category mapping count'), 
('csd_status', 'CSD status'),
('prod_status', 'PROD status'),
('blp_prod_status', 'BLP PROD status'),
('afd_status', 'AFD status'),
('patrol_status', 'Already review/triaged'),
('rev_count', 'Number of edits to the article'),
('page_len', 'Number of bytes of article'),
('creation_date', 'Article creation date'),
('snippet', 'Beginning of article snippet'),
('partial_url', 'Internal link fragment'),
('user_name', 'User name'),
('user_editcount', 'User total edit'),
('user_creation_date', 'User registration date'),
('user_autoconfirmed', 'Check if user is autoconfirmed' ),
('user_bot', 'Check if user is in bot group'),
('user_block_status', 'User block status'),
('user_id', 'User id');
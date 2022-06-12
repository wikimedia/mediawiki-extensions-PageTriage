ALTER TABLE  /*_*/pagetriage_page
CHANGE  ptrp_created ptrp_created BINARY(14) NOT NULL,
CHANGE  ptrp_tags_updated ptrp_tags_updated BINARY(14) NOT NULL,
CHANGE  ptrp_reviewed_updated ptrp_reviewed_updated BINARY(14) NOT NULL;

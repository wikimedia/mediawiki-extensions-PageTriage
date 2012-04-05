alter table /*_*/pagetriage_log change ptrl_triaged ptrl_reviewed tinyint unsigned not null;
alter table /*_*/pagetriage_page change ptrp_triaged ptrp_reviewed tinyint unsigned not null;

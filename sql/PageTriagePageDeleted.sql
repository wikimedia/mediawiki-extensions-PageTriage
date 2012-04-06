-- temporary patch for devs who don't want to replace their tables.  will delete this later.

alter table pagetriage_page add ptrp_deleted tinyint unsigned not null default 0 after ptrp_reviewed;
alter table pagetriage_page drop index /*i*/ptrp_reviewed_timestamp_page_id;
alter table pagetriage_page drop index /*i*/ptrp_timestamp_page_id;

CREATE INDEX /*i*/ptrp_reviewed_timestamp_page_id ON /*_*/pagetriage_page (ptrp_reviewed, ptrp_timestamp, ptrp_page_id, ptrp_deleted);
CREATE INDEX /*i*/ptrp_deleted_reviewed_etc ON /*_*/pagetriage_page (ptrp_deleted, ptrp_timestamp, ptrp_page_id, ptrp_reviewed);
CREATE INDEX /*i*/ptrp_timestamp_page_id ON /*_*/pagetriage_page (ptrp_timestamp, ptrp_page_id, ptrp_deleted);

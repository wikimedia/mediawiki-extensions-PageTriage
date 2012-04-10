-- temporary patch for devs who don't want to replace their tables.  will delete this later.

DELETE pagetriage_page_tags, pagetriage_tags FROM pagetriage_page_tags, pagetriage_tags WHERE ptrpt_tag_id = ptrt_tag_id AND ptrt_tag_name IN ( 'patrol_status', 'creation_date' );

-- This file is automatically generated using maintenance/generateSchemaChangeSql.php.
-- Source: extensions/PageTriage/sql/patch_ptrp_tags_updated_nullable.json
-- Do not modify this file directly.
-- See https://www.mediawiki.org/wiki/Manual:Schema_changes
ALTER TABLE  /*_*/pagetriage_page
CHANGE  ptrp_tags_updated ptrp_tags_updated BINARY(14) DEFAULT NULL;
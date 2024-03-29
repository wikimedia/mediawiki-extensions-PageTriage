-- This file is automatically generated using maintenance/generateSchemaSql.php.
-- Source: extensions/PageTriage/sql/tables.json
-- Do not modify this file directly.
-- See https://www.mediawiki.org/wiki/Manual:Schema_changes
CREATE TABLE /*_*/pagetriage_tags (
  ptrt_tag_id INT UNSIGNED AUTO_INCREMENT NOT NULL,
  ptrt_tag_name VARBINARY(20) NOT NULL,
  ptrt_tag_desc VARBINARY(255) NOT NULL,
  UNIQUE INDEX ptrt_tag_id (ptrt_tag_name),
  PRIMARY KEY(ptrt_tag_id)
) /*$wgDBTableOptions*/;


CREATE TABLE /*_*/pagetriage_page_tags (
  ptrpt_page_id INT UNSIGNED NOT NULL,
  ptrpt_tag_id INT UNSIGNED NOT NULL,
  ptrpt_value VARBINARY(255) NOT NULL,
  INDEX ptrpt_tag_id_value (ptrpt_tag_id, ptrpt_value),
  PRIMARY KEY(ptrpt_page_id, ptrpt_tag_id)
) /*$wgDBTableOptions*/;


CREATE TABLE /*_*/pagetriage_page (
  ptrp_page_id INT UNSIGNED NOT NULL,
  ptrp_reviewed TINYINT UNSIGNED DEFAULT 0 NOT NULL,
  ptrp_deleted TINYINT UNSIGNED DEFAULT 0 NOT NULL,
  ptrp_created BINARY(14) NOT NULL,
  ptrp_tags_updated BINARY(14) DEFAULT NULL,
  ptrp_reviewed_updated BINARY(14) NOT NULL,
  ptrp_last_reviewed_by INT UNSIGNED DEFAULT 0 NOT NULL,
  INDEX ptrp_reviewed_created_page_del (
    ptrp_reviewed, ptrp_created, ptrp_page_id,
    ptrp_deleted
  ),
  INDEX ptrp_created_page_del (
    ptrp_created, ptrp_page_id, ptrp_deleted
  ),
  INDEX ptrp_del_created_page_reviewed (
    ptrp_deleted, ptrp_created, ptrp_page_id,
    ptrp_reviewed
  ),
  INDEX ptrp_updated_page_reviewed (
    ptrp_tags_updated, ptrp_page_id,
    ptrp_reviewed
  ),
  INDEX ptrp_reviewed_updated (ptrp_reviewed_updated),
  PRIMARY KEY(ptrp_page_id)
) /*$wgDBTableOptions*/;

<?php

namespace MediaWiki\Extension\PageTriage\ArticleCompile;

use Title;

/**
 * Article page length, creation date, number of edit, title, article triage status
 */
class ArticleCompileBasicData extends ArticleCompileInterface {

	public function __construct( $pageId, $componentDb = DB_MASTER, $articles = null ) {
		parent::__construct( $pageId, $componentDb, $articles );
	}

	public function compile() {
		$count = 0;
		// Process page individually because MIN() GROUP BY is slow
		foreach ( $this->mPageId as $pageId ) {
			$table = [ 'revision', 'page' ];
			$conds = [ 'rev_page' => $pageId, 'page_id = rev_page' ];

			$row = $this->db->selectRow( $table, [ 'MIN(rev_timestamp) AS creation_date' ],
						$conds, __METHOD__ );
			if ( $row ) {
				$this->metadata[$pageId]['creation_date'] = wfTimestamp( TS_MW, $row->creation_date );
				$this->processEstimatedCount( $pageId, $table, $conds, $maxNumToProcess = 100, 'rev_count' );
				$count++;
			}
		}

		// no record in page table
		if ( $count == 0 ) {
			return false;
		}

		$res = $this->db->select(
				[ 'page', 'pagetriage_page', 'user' ],
				[
					'page_id', 'page_namespace', 'page_title', 'page_len',
					'ptrp_reviewed', 'page_is_redirect', 'ptrp_last_reviewed_by',
					'ptrp_reviewed_updated', 'user_name AS reviewer'
				],
				[ 'page_id' => $this->mPageId, 'page_id = ptrp_page_id' ],
				__METHOD__,
				[],
				[ 'user' => [ 'LEFT JOIN', 'user_id = ptrp_last_reviewed_by' ] ]
		);
		foreach ( $res as $row ) {
			if ( isset( $this->articles[$row->page_id] ) ) {
				$title = $this->articles[$row->page_id]->getTitle();
			} else {
				$title = Title::makeTitle( $row->page_namespace, $row->page_title );
			}
			$this->metadata[$row->page_id]['page_len'] = $row->page_len;
			// The following data won't be saved into metadata since they are not metadata tags
			// just for saving into cache later
			$this->metadata[$row->page_id]['patrol_status'] = $row->ptrp_reviewed;
			$this->metadata[$row->page_id]['is_redirect'] = $row->page_is_redirect;
			$this->metadata[$row->page_id]['ptrp_last_reviewed_by'] = $row->ptrp_last_reviewed_by;
			$this->metadata[$row->page_id]['ptrp_reviewed_updated'] = wfTimestamp(
				TS_MW,
				$row->ptrp_reviewed_updated
			);
			$this->metadata[$row->page_id]['reviewer'] = $row->reviewer;
			if ( $title ) {
				$this->metadata[$row->page_id]['title'] = $title->getPrefixedText();
			}
		}

		return true;
	}

}

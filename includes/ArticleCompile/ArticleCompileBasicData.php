<?php

namespace MediaWiki\Extension\PageTriage\ArticleCompile;

use MediaWiki\Title\Title;

/**
 * Article page length, creation date, number of edit, title, article triage status
 */
class ArticleCompileBasicData extends ArticleCompile {

	public function compile() {
		$count = 0;
		// Process page individually because MIN() GROUP BY is slow
		foreach ( $this->mPageId as $pageId ) {
			$row = $this->db->newSelectQueryBuilder()
				->select( [ 'creation_date' => 'MIN(rev_timestamp)' ] )
				->from( 'revision' )
				->join( 'page', 'page', 'page_id = rev_page' )
				->where( [ 'rev_page' => $pageId ] )
				->caller( __METHOD__ )
				->fetchRow();

			if ( $row ) {
				$this->metadata[$pageId]['creation_date'] = wfTimestamp( TS_MW, $row->creation_date );
				$res = $this->db->newSelectQueryBuilder()
					->select( '1' )
					->from( 'revision' )
					->join( 'page', null, [ 'page_id = rev_page' ] )
					->where( [ 'rev_page' => $pageId ] )
					->limit( 101 )
					->caller( __METHOD__ )->fetchResultSet()->numRows();
				$this->processEstimatedCount( $pageId, $res, 100, 'rev_count' );
				$count++;
			}
		}

		// no record in page table
		if ( $count === 0 ) {
			return false;
		}
		$res = $this->db->newSelectQueryBuilder()
			->select( [
				'page_id', 'page_namespace', 'page_title', 'page_len',
				'ptrp_reviewed', 'page_is_redirect', 'ptrp_last_reviewed_by',
				'ptrp_reviewed_updated', 'reviewer' => 'user_name'
			] )
			->from( 'page' )
			->join( 'pagetriage_page', null, 'page_id = ptrp_page_id' )
			->leftJoin( 'user', 'user', 'user_id = ptrp_last_reviewed_by' )
			->where( [ 'page_id' => $this->mPageId ] )
			->caller( __METHOD__ )
			->fetchResultSet();

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

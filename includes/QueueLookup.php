<?php

namespace MediaWiki\Extension\PageTriage;

use IDatabase;

/**
 * Service class for retrieving PageTriage queue records.
 */
class QueueLookup {

	/** @var IDatabase */
	private IDatabase $dbr;

	/**
	 * @param IDatabase $dbr
	 */
	public function __construct( IDatabase $dbr ) {
		$this->dbr = $dbr;
	}

	/**
	 * @param int $pageId
	 * @return QueueRecord|null
	 */
	public function getByPageId( int $pageId ): ?QueueRecord {
		$row = $this->dbr->selectRow(
			'pagetriage_page',
			[
				'ptrp_page_id',
				'ptrp_reviewed',
				'ptrp_created',
				'ptrp_deleted',
				'ptrp_tags_updated',
				'ptrp_reviewed_updated',
				'ptrp_last_reviewed_by'
			],
			[ 'ptrp_page_id' => $pageId ],
			__METHOD__
		);
		if ( !$row ) {
			return null;
		}
		return QueueRecord::newFromRow( $row );
	}

}

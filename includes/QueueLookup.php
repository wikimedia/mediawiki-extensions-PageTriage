<?php

namespace MediaWiki\Extension\PageTriage;

use IDatabase;
use stdClass;

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
				'ptrp_last_reviewed_by',
			],
			[ 'ptrp_page_id' => $pageId ],
			__METHOD__
		);
		if ( !$row ) {
			return null;
		}
		return $this->newFromRow( $row );
	}

	/**
	 * @param stdClass $row
	 * @return QueueRecord
	 */
	public function newFromRow( stdClass $row ): QueueRecord {
		return new QueueRecord(
			$row->ptrp_page_id,
			$row->ptrp_reviewed,
			// '0' casts to false so this will work as expected.
			(bool)$row->ptrp_deleted,
			$row->ptrp_created,
			$row->ptrp_tags_updated,
			$row->ptrp_reviewed_updated,
			$row->ptrp_last_reviewed_by
		);
	}

}

<?php

namespace MediaWiki\Extension\PageTriage;

use stdClass;
use Wikimedia\Rdbms\IConnectionProvider;

/**
 * Service class for retrieving PageTriage queue records.
 */
class QueueLookup {

	/** @var IConnectionProvider */
	private IConnectionProvider $dbProvider;

	/**
	 * @param IConnectionProvider $dbProvider
	 */
	public function __construct( IConnectionProvider $dbProvider ) {
		$this->dbProvider = $dbProvider;
	}

	/**
	 * @param int $pageId
	 * @return QueueRecord|null
	 */
	public function getByPageId( int $pageId ): ?QueueRecord {
		$dbr = $this->dbProvider->getReplicaDatabase();
		$row = $dbr->newSelectQueryBuilder()
			->select( [
				'ptrp_page_id',
				'ptrp_reviewed',
				'ptrp_created',
				'ptrp_deleted',
				'ptrp_tags_updated',
				'ptrp_reviewed_updated',
				'ptrp_last_reviewed_by',
			] )
			->from( 'pagetriage_page' )
			->where( [ 'ptrp_page_id' => $pageId ] )
			->caller( __METHOD__ )
			->fetchRow();

		if ( !$row ) {
			return null;
		}
		return $this->newFromRow( $row );
	}

	/**
	 * @param stdClass $row
	 * @return QueueRecord
	 */
	private function newFromRow( stdClass $row ): QueueRecord {
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

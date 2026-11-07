<?php

namespace MediaWiki\Extension\PageTriage\Maintenance;

use MediaWiki\Maintenance\LoggedUpdateMaintenance;
use MediaWiki\Maintenance\LoggedUpdateOutcome;
use Wikimedia\Rdbms\RawSQLValue;

// @codeCoverageIgnoreStart
$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";
// @codeCoverageIgnoreEnd

/**
 * Backfill missing PageTriage enqueue timestamps from review timestamps.
 *
 * This is only needed when first adding the ptrp_enqueued field. After that, PageTriage code
 * will keep ptrp_enqueued up to date. A fresh wiki does not need a backfill because no pages
 * are in the queue.'
 *
 * LoggedUpdateMaintenance will cause this class to run only once.
 */
class BackfillPtrpEnqueued extends LoggedUpdateMaintenance {

	public function __construct() {
		parent::__construct();
		$this->addDescription( 'Backfill missing PageTriage enqueue timestamps.' );
		$this->requireExtension( 'PageTriage' );
	}

	/** @inheritDoc */
	protected function getUpdateKey() {
		return 'PageTriage-backfill-ptrp-enqueued';
	}

	/** @inheritDoc */
	protected function doDBUpdates(): LoggedUpdateOutcome {
		$db = $this->getPrimaryDB();
		$lastId = 0;
		$total = 0;
		do {
			$ids = $db->newSelectQueryBuilder()
				->select( 'ptrp_page_id' )
				->from( 'pagetriage_page' )
				->where( [ 'ptrp_enqueued' => null ] )
				->andWhere( $db->expr( 'ptrp_page_id', '>', $lastId ) )
				->orderBy( 'ptrp_page_id' )
				->limit( $this->getBatchSize() )
				->caller( __METHOD__ )
				->fetchFieldValues();

			if ( $ids ) {
				$this->beginTransactionRound( __METHOD__ );
				// The old behavior is that PageTriage sorts by ptrp_reviewed_updated, so use that for
				// our backfill. It is good enough for now, until the new code can kick in and start
				// writing more accurate ptrp_enqueued values.
				$db->newUpdateQueryBuilder()
					->update( 'pagetriage_page' )
					->set( [ 'ptrp_enqueued' => new RawSQLValue( 'ptrp_reviewed_updated' ) ] )
					->where( [ 'ptrp_enqueued' => null ] )
					->andWhere( [ 'ptrp_page_id' => $ids ] )
					->caller( __METHOD__ )
					->execute();
				$total += $db->affectedRows();
				$lastId = (int)end( $ids );
				$this->commitTransactionRound( __METHOD__ );
			}
		} while ( count( $ids ) === $this->getBatchSize() );

		$this->output( "Backfilled $total PageTriage enqueue timestamps.\n" );
		return LoggedUpdateOutcome::COMPLETE;
	}
}

// @codeCoverageIgnoreStart
$maintClass = BackfillPtrpEnqueued::class;
require_once RUN_MAINTENANCE_IF_MAIN;
// @codeCoverageIgnoreEnd

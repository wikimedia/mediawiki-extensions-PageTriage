<?php
namespace MediaWiki\Extension\PageTriage\Maintenance;

use Maintenance;
use MediaWiki\Extension\PageTriage\PageTriageUtil;
use MediaWiki\MediaWikiServices;

/**
 * Maintenance script that updates parameter name from '4::tags' to 'tags' in
 * pagetriage-curation and (now defunct) pagetriage-deletion log
 *
 * @ingroup Maintenance
 */
class CleanupPageTriageLog extends Maintenance {

	public function __construct() {
		parent::__construct();
		$this->requireExtension( 'PageTriage' );
		$this->setBatchSize( 100 );
	}

	public function execute() {
		$dbw = PageTriageUtil::getPrimaryConnection();
		$dbr = PageTriageUtil::getReplicaConnection();
		$lbFactory = MediaWikiServices::getInstance()->getDBLoadBalancerFactory();

		// clean up the following type and action
		$logTypes = [
			[ 'type' => 'pagetriage-curation', 'action' => 'tag' ],
			[ 'type' => 'pagetriage-curation', 'action' => 'delete' ],
			// The below log_type no longer exists. Leaving for backwards compatibility.
			[ 'type' => 'pagetriage-deletion', 'action' => 'delete' ]
		];
		$batchSize = $this->getBatchSize();

		foreach ( $logTypes as $type ) {
			$count = $batchSize;
			$startTime = (int)wfTimestamp( TS_UNIX ) - 60 * 24 * 60 * 60;

			while ( $count == $batchSize ) {
				$res = $dbr->newSelectQueryBuilder()
					->select( [ 'log_id', 'log_timestamp', 'log_params' ] )
					->from( 'logging' )
					->where(
						[
							'log_type' => $type['type'],
							'log_action' => $type['action'],
							'log_timestamp > ' . $dbr->addQuotes( $dbr->timestamp( $startTime ) )
						]
					)
					->limit( $batchSize )
					->orderBy( 'log_timestamp' )
					->caller( __METHOD__ )
					->fetchResultSet();

				$count = 0;
				foreach ( $res as $row ) {
					$newLogParams = str_replace( 's:7:"4::tags";', 's:4:"tags";', $row->log_params );

					$dbw->update(
						'logging',
						[ 'log_params' => $newLogParams ],
						[ 'log_id' => $row->log_id ],
						__METHOD__
					);

					$startTime = wfTimestamp( TS_UNIX, $row->log_timestamp );
					$count++;
				}

				$this->output( "processed " . $type['type'] . ' ' . $type['action'] . ': ' . $count . "\n" );
				$lbFactory->waitForReplication();
			}
		}
	}
}

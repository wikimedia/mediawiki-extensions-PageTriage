<?php
/**
 * update parameter name from '4::tags' to 'tags' in pagetriage-curation
 * and pagetriage-deletion log
 *
 * @ingroup Maintenance
 */

require_once( dirname( __FILE__ ) . '/../../../maintenance/Maintenance.php' );

/**
 * Maintenance script that updates parameter name from '4::tags' to 'tags' in pagetriage-curation
 * and pagetriage-deletion log
 *
 * @ingroup Maintenance
 */
class CleanupPageTriageLog extends Maintenance {

	protected $batchSize = 100;

	public function execute() {
		$dbw = wfGetDB( DB_MASTER );
		$dbr = wfGetDB( DB_SLAVE );

		// clean up the following type and action
		$logTypes = array(
			array( 'type' => 'pagetriage-curation', 'action' => 'tag' ),
			array( 'type' => 'pagetriage-curation', 'action' => 'delete' ),
			array( 'type' => 'pagetriage-deletion', 'action' => 'delete' )
		);

		foreach ( $logTypes as $type ) {
			$count = $this->batchSize;
			$startTime = wfTimestamp( TS_UNIX ) - 60 * 24 * 60 * 60;

			while ( $count == $this->batchSize ) {
				$res = $dbr->select(
					array( 'logging' ),
					array( 'log_id', 'log_timestamp', 'log_params' ),
					array(
						'log_type' => $type['type'],
						'log_action' => $type['action'],
						'log_timestamp > ' . $dbr->addQuotes( $dbr->timestamp( $startTime ) )
					),
					__METHOD__,
					array( 'LIMIT' => $this->batchSize, 'ORDER BY' => 'log_timestamp' )
				);

				$count = 0;
				foreach( $res as $row ) {
					$newLogParams = str_replace( 's:7:"4::tags";', 's:4:"tags";', $row->log_params );

					$dbw->update(
						'logging',
						array( 'log_params' => $newLogParams ),
						array( 'log_id' => $row->log_id )
					);

					$startTime = wfTimestamp( TS_UNIX, $row->log_timestamp );
					$count++;
				}

				$this->output( "processed " . $type['type'] . ' ' . $type['action'] . ': ' . $count . "\n" );
				wfWaitForSlaves();
			}
		}
	}
}

$maintClass = 'CleanupPageTriageLog'; // Tells it to run the class
require_once( RUN_MAINTENANCE_IF_MAIN );

<?php

/**
 * @group PageTriage
 * @group extensions
 */
abstract class PageTriageTestCase extends ApiTestCase {

	/** @var int */
	protected $draftNsId = 150;

	protected function setUp() {
		global $wgContLang;
		parent::setUp();

		// Define a Draft NS unless there already is one.
		$draftNsId = MWNamespace::getCanonicalIndex( 'draft' );
		if ( $draftNsId === null ) {
			$this->setMwGlobals( [
				'wgExtraNamespaces' => [ $this->draftNsId => 'Draft' ],
				'wgPageTriageDraftNamespaceId' => $this->draftNsId
			] );
			// Clear NS caches.
			MWNamespace::clearCaches();
			$wgContLang->resetNamespaces();
		} else {
			$this->draftNsId = $draftNsId;
		}

		// Insert minimal required data (subset of what's done in PageTriage/sql/PageTriageTags.sql)
		// @TODO figure out why this is only run for the first test method when its in addDbData().
		$db = wfGetDB( DB_MASTER );
		$db->insert(
			'pagetriage_tags',
			[
				[ 'ptrt_tag_name' => 'afc_state' ],
				[ 'ptrt_tag_name' => 'user_name' ],
			],
			__METHOD__,
			[ 'IGNORE' ]
		);
	}

	/**
	 * Helper method to query the PageTriageList API. Defaults to unreviewed draft pages.
	 * @param array $params
	 * @return array
	 * @throws ApiUsageException
	 */
	protected function getPageTriageList( array $params = [] ) {
		$list = $this->doApiRequest( array_merge( [
			'action' => 'pagetriagelist',
			'showunreviewed' => '1',
			'namespace' => $this->draftNsId,
		], $params ) );

		return $list[0]['pagetriagelist']['pages'];
	}

}

<?php

/**
 * Tests the inclusion of the Draft namespace.
 *
 * @group PageTriage
 * @group extensions
 * @group medium
 */
class ApiPageTriageStatsTest extends PageTriageTestCase {

	/**
	 * @covers \MediaWiki\Extension\PageTriage\Api\ApiPageTriageStats
	 */
	public function testFilteredArticleCount() {
		$apiParams = [
			'action' => 'pagetriagestats',
			'showunreviewed' => '1',
			'namespace' => $this->draftNsId,
		];
		$list1 = $this->doApiRequest( $apiParams );
		$initialCount = $list1[0]['pagetriagestats']['stats']['filteredarticle'];

		$this->insertPage( 'AfC stats test page', '', $this->draftNsId );
		$this->insertPage( 'AfC stats test page 2', '', $this->draftNsId );

		$list2 = $this->doApiRequest( $apiParams );
		// Check that the filtered and unreviewed article counts have increased correctly.
		$this->assertEquals(
			$initialCount + 2,
			$list2[0]['pagetriagestats']['stats']['filteredarticle']
		);
		$this->assertEquals(
			$initialCount + 2,
			$list2[0]['pagetriagestats']['stats']['unreviewedarticle']['count']
		);
	}
}

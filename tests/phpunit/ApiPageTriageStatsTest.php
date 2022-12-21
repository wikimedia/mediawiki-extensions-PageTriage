<?php

namespace MediaWiki\Extension\PageTriage\Test;

use ApiUsageException;

/**
 * Tests the inclusion of the Draft namespace.
 *
 * @covers \MediaWiki\Extension\PageTriage\Api\ApiPageTriageStats
 *
 * @group PageTriage
 * @group extensions
 * @group medium
 * @group Database
 */
class ApiPageTriageStatsTest extends PageTriageTestCase {

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
		// Check that namespace ID appears in results.
		$this->assertEquals(
			$this->draftNsId,
			$list2[0]['pagetriagestats']['stats']['namespace']
		);
	}

	/**
	 * Verify that endpoint-specific API params are defined properly.
	 *
	 * @throws ApiUsageException
	 * @covers \MediaWiki\Extension\PageTriage\PageTriageUtil::getCommonApiParams()
	 */
	public function testApiParamsByEndpoint() {
		// Test valid param to PageTriageStats.
		$response = $this->doApiRequest( [
			'action' => 'pagetriagestats',
			'recreated' => '1',
		] );
		static::assertArrayNotHasKey( 'warnings', $response[0] );
		// Test invalid param to PageTriageStats.
		$response = $this->doApiRequest( [
			'action' => 'pagetriagestats',
			'offset' => '56789',
		] );
		static::assertEquals( 'Unrecognized parameter: offset.',
			$response[0]['warnings']['main']['warnings'] );
	}
}

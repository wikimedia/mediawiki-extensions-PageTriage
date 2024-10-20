<?php

namespace MediaWiki\Extension\PageTriage\Test;

use MediaWiki\Api\ApiUsageException;

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
		$this->assertArrayNotHasKey( 'warnings', $response[0] );
		// Test invalid param to PageTriageStats.
		$response = $this->doApiRequest( [
			'action' => 'pagetriagestats',
			'offset' => '56789',
		] );
		$this->assertEquals( 'Unrecognized parameter: offset.',
			$response[0]['warnings']['main']['warnings'] );
	}

	/**
	 * Regression test for T354900. Checks for database error when filtering with both
	 * the copyvio potential issue and non-autoconfirmed users selected.
	 */
	public function testCopyvioAndNonAutoconfirmedFilterCombination() {
		$this->markTestSkippedIfExtensionNotLoaded( 'ORES' );
		$this->overrideConfigValue( 'OresWikiId', 'enwiki' );
		$this->overrideConfigValue( 'OresModels', [
			'articlequality' => [
				'enabled' => true,
				'namespaces' => [ 0 ],
				'cleanParent' => true
			],
			'draftquality' => [
				'enabled' => true,
				'namespaces' => [ 0 ],
				'types' => [ 1 ]
			],
		] );
		$response = $this->doApiRequest( [
			'action' => 'pagetriagestats',
			'show_predicted_issues_copyvio' => 1,
			'non_autoconfirmed_users' => 1,
		] );
		$this->assertEquals(
			'success',
			$response[0]['pagetriagestats']['result'],
			'Trying to get a pagetriage list with filters copyvio and non-autoconfirmed users ' .
			'should not throw database error'
		);
	}
}

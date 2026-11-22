<?php

namespace MediaWiki\Extension\PageTriage\Test;

use MediaWiki\Extension\PageTriage\ArticleMetadata;

/**
 * @coversNothing
 * @group medium
 * @group Database
 */
class PageTriageTestCaseTest extends PageTriageTestCase {

	public function testMakeMainspacePage() {
		$user = $this->getTestUser()->getUser();
		$text = 'Mainspace page content';
		$pageId = $this->makeMainspacePage( __METHOD__, false, false, $user, $text );
		$page = $this->getServiceContainer()->getWikiPageFactory()->newFromID( $pageId );
		$metadata = ArticleMetadata::getMetadataForArticles( [ $pageId ] );

		$this->assertSame( NS_MAIN, $page->getNamespace() );
		$this->assertSame( (string)$user->getId(), $metadata[$pageId]['user_id'] );
		$this->assertSame( (string)strlen( $text ), $metadata[$pageId]['page_len'] );
	}

	public function testMakeMainspacePageWithQualityAndCopyvio() {
		$this->markTestSkippedIfExtensionNotLoaded( 'ORES' );
		$pageId = $this->makeMainspacePage( __METHOD__, 2, true );
		$page = $this->getServiceContainer()->getWikiPageFactory()->newFromID( $pageId );
		$metadata = ArticleMetadata::getMetadataForArticles( [ $pageId ] );
		$classificationCount = $this->getDb()->newSelectQueryBuilder()
			->select( '*' )
			->from( 'ores_classification' )
			->where( [
				'oresc_rev' => $page->getLatest(),
				'oresc_model' => $this->ensureOresModel( 'draftquality' ),
				'oresc_class' => 2,
				'oresc_is_predicted' => 1,
			] )
			->caller( __METHOD__ )
			->fetchRowCount();

		$this->assertSame( 1, $classificationCount );
		$this->assertSame( (string)$page->getLatest(), $metadata[$pageId]['copyvio'] );
	}
}

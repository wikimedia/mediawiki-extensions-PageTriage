<?php
namespace MediaWiki\Extension\PageTriage\Test\Integration;

use MediaWiki\Extension\PageTriage\ArticleCompile\ArticleCompileCheckRecreated;
use MediaWikiIntegrationTestCase;
use Wikimedia\TestingAccessWrapper;

/**
 * @covers \MediaWiki\Extension\PageTriage\ArticleCompile\ArticleCompileCheckRecreated
 * @group Database
 */
class ArticleCompileCheckRecreatedTest extends MediaWikiIntegrationTestCase {
	public function testCheckRecreated() {
		$user = self::getTestUser()->getUser();
		$pageTitle = 'Recreated Page';
		$pageContent = 'some content over here with a link http://example.com';

		$originalPage = $this->insertPage( $pageTitle, $pageContent, 0 );
		$originalPageTitle = $this->getServiceContainer()->getWikiPageFactory()->newFromTitle( $originalPage['title'] );
		$originalPageContent = $originalPageTitle->getContent()->getNativeData();

		$page = $this->getServiceContainer()->getWikiPageFactory()->newFromTitle( $originalPage['title'] );
		$this->deletePage( $page, 'Test', $user );

		$recreatedPage = $this->insertPage( $pageTitle, $pageContent, 0 );
		$recreatedPageTitle =
		$this->getServiceContainer()->getWikiPageFactory()->newFromTitle( $recreatedPage['title'] );
		$recreatedPageContent = $recreatedPageTitle->getContent()->getNativeData();

		$articleCompileCheckRecreated = new ArticleCompileCheckRecreated( [ $recreatedPage['id'] ], 0, [], [] );
		$articleCompileCheckRecreated = TestingAccessWrapper::newFromObject( $articleCompileCheckRecreated );

		$this->assertEquals(
			100,
			$articleCompileCheckRecreated->compareContent( $recreatedPageContent, $originalPageContent )
		);
	}

	/**
	 * Special cases where similar_text inbuilt function fails to calculate accurate similarity hence needing manual
	 * similarity code implementation
	 * The following test case illustrates the limitations of similar_text in evaluating content similarity when the
	 * recreated content is merely a subset or reordering of the original content.
	 */
	public function testCheckRecreated2() {
		$user = self::getTestUser()->getUser();
		$pageTitle = 'Recreated Page with Partial Similarity';
		$pageContent =
		'The aggregate fruit of the rose is a berry-like structure called a rose hip. Many of the domestic cultivars 
		do not produce hips, as the flowers are so tightly petalled that they do not provide access for pollination.
		The hips of most species are red, but a few (e.g. Rosa pimpinellifolia) have dark purple to black hips.
		Each hip comprises an outer fleshy layer, the hypanthium, which contains 5–160 "seeds" (technically dry
		single-seeded fruits called achenes) embedded in a matrix of fine, but stiff, hairs. Rose hips
		of some species, especially the dog rose (Rosa canina) and rugosa rose (Rosa rugosa),are very rich in vitamin C,
		 among the richest sources of any plant. The hips are eaten by fruit-eating birds such as thrushes and waxwings,
		which then disperse the seeds in their droppings. Some birds, particularly finches, also eat the seeds.';
		$originalPage = $this->insertPage( $pageTitle, $pageContent, 0 );
		$originalPageTitle = $this->getServiceContainer()->getWikiPageFactory()->newFromTitle( $originalPage['title'] );
		$originalPageContent = $originalPageTitle->getContent()->getNativeData();

		$page = $this->getServiceContainer()->getWikiPageFactory()->newFromTitle( $originalPage['title'] );
		$this->deletePage( $page, 'Test', $user );

		$recreatedPageContent =
		'The aggregate fruit of the rose is a berry-like structure called a rose hip.Rose hips of some species,
		especially the dog rose (Rosa canina) and rugosa rose (Rosa rugosa), are very rich in vitamin C, among
		the richest sources of any plant. The hips are eaten by fruit-eating birds such as thrushes and
		waxwings, which then disperse the seeds in their droppings. Some birds, particularly finches,
		also eat the seeds.  Each hip comprises an outer fleshylayer, the hypanthium, which contains 5–160 "seeds"
		 (technically dry single-seeded fruits called achenes) embedded in a matrix of fine, but stiff, hairs. ';
		$recreatedPage = $this->insertPage( $pageTitle, $recreatedPageContent, 0 );
		$recreatedPageTitle =
		$this->getServiceContainer()->getWikiPageFactory()->newFromTitle( $recreatedPage['title'] );

		$articleCompileCheckRecreated = new ArticleCompileCheckRecreated( [ $recreatedPage['id'] ], 0, [], [] );
		$articleCompileCheckRecreated = TestingAccessWrapper::newFromObject( $articleCompileCheckRecreated );
		// The recreated content is 92% contextually similar, but the inbuilt similar_text function
		//calculates it at 56%, focusing on character-wise similarity.
		//Our implementation assesses phrases and words, making a custom function essential for accurate comparisons.
		$this->assertEquals(
			92,
			$articleCompileCheckRecreated->compareContent( $recreatedPageContent, $originalPageContent )
		);
	}

	public function testCheckRecreatedWithPartialSimilarity() {
		$user = self::getTestUser()->getUser();
		$pageTitle = 'Recreated Page with Partial Similarity';
		$pageContent =
		'This is some original content with unique details and links http://example1.com and http://example2.com';

		$originalPage = $this->insertPage( $pageTitle, $pageContent, 0 );
		$originalPageTitle = $this->getServiceContainer()->getWikiPageFactory()->newFromTitle( $originalPage['title'] );
		$originalPageContent = $originalPageTitle->getContent()->getNativeData();

		$page = $this->getServiceContainer()->getWikiPageFactory()->newFromTitle( $originalPage['title'] );
		$this->deletePage( $page, 'Test', $user );

		$recreatedPageContent =
		'Recreated content with unique details and links http://example2.com and http://example3.com';
		$recreatedPage = $this->insertPage( $pageTitle, $recreatedPageContent, 0 );
		$recreatedPageTitle =
		$this->getServiceContainer()->getWikiPageFactory()->newFromTitle( $recreatedPage['title'] );

		$articleCompileCheckRecreated = new ArticleCompileCheckRecreated( [ $recreatedPage['id'] ], 0, [], [] );
		$articleCompileCheckRecreated = TestingAccessWrapper::newFromObject( $articleCompileCheckRecreated );

		$this->assertEquals(
			41,
			$articleCompileCheckRecreated->compareContent( $recreatedPageContent, $originalPageContent )
		);
	}
}

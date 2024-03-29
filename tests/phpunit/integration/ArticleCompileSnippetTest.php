<?php

namespace MediaWiki\Extension\PageTriage\Test\Integration;

use MediaWiki\Extension\PageTriage\ArticleCompile\ArticleCompileSnippet;
use MediaWikiIntegrationTestCase;
use Wikimedia\TestingAccessWrapper;

/**
 * @covers \MediaWiki\Extension\PageTriage\ArticleCompile\ArticleCompileSnippet
 * @group Database
 */
class ArticleCompileSnippetTest extends MediaWikiIntegrationTestCase {

	public function testCheckReferenceTag() {
		// FIXME: This could be a MediaWikiUnitTestCase if ArticleCompileSnippet accepted a DB interface.
		$articleCompileSnippet = new ArticleCompileSnippet(
			[],
			0,
			[],
			[],
		);
		/** @var ArticleCompileSnippet $articleCompileSnippet */
		$articleCompileSnippet = TestingAccessWrapper::newFromObject( $articleCompileSnippet );
		// <ref></ref> style references
		$this->assertFalse( $articleCompileSnippet->hasReferenceTag( 'no ref text' ) );
		$this->assertFalse( $articleCompileSnippet->hasReferenceTag( 'no ref text<ref>' ) );
		$this->assertFalse( $articleCompileSnippet->hasReferenceTag( 'no closing tag<ref name="some ref"/>' ) );

		$this->assertTrue( $articleCompileSnippet->hasReferenceTag( '1 ref<ref>some ref</ref>' ) );
		$this->assertTrue( $articleCompileSnippet->hasReferenceTag(
			'2 refs<ref>some ref</ref><ref>second ref</ref>' ) );
		$this->assertTrue( $articleCompileSnippet->hasReferenceTag( '1 refs<ref name="x">some ref</ref>' ) );

		// Check case insensitivity for <ref> and </ref>
		$this->assertTrue( $articleCompileSnippet->hasReferenceTag( '1 ref <Ref>some ref</Ref>' ) );
		$this->assertTrue( $articleCompileSnippet->hasReferenceTag( '1 ref <ref>some ref</Ref>' ) );
		$this->assertTrue( $articleCompileSnippet->hasReferenceTag( '1 ref <Ref>some ref</ref>' ) );
		$this->assertTrue( $articleCompileSnippet->hasReferenceTag( '1 ref <ReF>some ref</Ref>' ) );
		$this->assertTrue( $articleCompileSnippet->hasReferenceTag( '1 ref <REF>some ref</REF>' ) );
		$this->assertTrue( $articleCompileSnippet->hasReferenceTag( '1 ref <ref>some ref</REF>' ) );
		$this->assertTrue( $articleCompileSnippet->hasReferenceTag( '1 ref <REF>some ref</ref>' ) );

		// sfn - short footnote style references
		$this->assertTrue( $articleCompileSnippet->hasReferenceTag( '1 ref{{sfn|short footnote}}' ) );
		$this->assertTrue( $articleCompileSnippet->hasReferenceTag( '1 ref{{Sfn|short footnote}}' ) );

		// Harvnb style references
		$this->assertTrue( $articleCompileSnippet->hasReferenceTag( '1 ref{{harvnb|harvard footnote}}' ) );
		$this->assertTrue( $articleCompileSnippet->hasReferenceTag( '1 ref{{Harvnb|harvard footnote}}' ) );

		// Mixed references
		$this->assertTrue( $articleCompileSnippet->hasReferenceTag(
			'2 refs<ref>some ref</ref>{{Sfn|short footnote}}' ) );
	}

}

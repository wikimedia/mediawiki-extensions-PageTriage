<?php

use MediaWiki\Extension\PageTriage\ArticleCompile\ArticleCompileSnippet;

/**
 * @coversClass MediaWiki\Extension\PageTriage\ArticleCompileSnippet
 */
class ArticleCompileSnippetTest extends MediaWikiUnitTestCase {

	/**
	 * @covers \MediaWiki\Extension\PageTriage\ArticleCompile\ArticleCompileSnippet::checkReferenceTag()
	 */
	public function testCheckReferenceTag() {
		// <ref></ref> style references
		self::assertSame( "0", ArticleCompileSnippet::checkReferenceTag( 'no ref text' ) );
		self::assertSame( "0", ArticleCompileSnippet::checkReferenceTag( 'no ref text<ref>' ) );
		self::assertSame( "0", ArticleCompileSnippet::checkReferenceTag( 'no closing tag<ref name="some ref"/>' ) );

		self::assertSame( "1", ArticleCompileSnippet::checkReferenceTag( '1 ref<ref>some ref</ref>' ) );
		self::assertSame( "1", ArticleCompileSnippet::checkReferenceTag(
			'2 refs<ref>some ref</ref><ref>second ref</ref>' ) );
		self::assertSame( "1", ArticleCompileSnippet::checkReferenceTag( '1 refs<ref name="x">some ref</ref>' ) );

		// sfn - short footnote style references
		self::assertSame( "1", ArticleCompileSnippet::checkReferenceTag( '1 ref{{sfn|short footnote}}' ) );
		self::assertSame( "1", ArticleCompileSnippet::checkReferenceTag( '1 ref{{Sfn|short footnote}}' ) );

		// Harvnb style references
		self::assertSame( "1", ArticleCompileSnippet::checkReferenceTag( '1 ref{{harvnb|harvard footnote}}' ) );
		self::assertSame( "1", ArticleCompileSnippet::checkReferenceTag( '1 ref{{Harvnb|harvard footnote}}' ) );

		// Mixed references
		self::assertSame( "1", ArticleCompileSnippet::checkReferenceTag(
			'2 refs<ref>some ref</ref>{{Sfn|short footnote}}' ) );
	}

}

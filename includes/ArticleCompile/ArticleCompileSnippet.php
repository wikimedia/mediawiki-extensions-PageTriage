<?php

namespace MediaWiki\Extension\PageTriage\ArticleCompile;

use MediaWiki\Content\TextContent;
use MediaWiki\Language\Language;
use MediaWiki\Language\MessageParser;
use MediaWiki\MediaWikiServices;
use MediaWiki\Parser\Sanitizer;
use MediaWiki\Title\Title;

/**
 * Article snippet
 */
class ArticleCompileSnippet extends ArticleCompile {

	/** @inheritDoc */
	public function compile() {
		$services = MediaWikiServices::getInstance();
		$lang = $services->getContentLanguage();
		$msgParser = $services->getMessageParser();
		foreach ( $this->mPageId as $pageId ) {
			$content = $this->getContentByPageId( $pageId );
			if ( $content ) {
				$text = ( $content instanceof TextContent ) ? $content->getText() : null;
				if ( $text !== null ) {
					$this->metadata[$pageId]['snippet'] = self::generateArticleSnippet(
						$text, $pageId, $lang, $msgParser
					);
					// Reference tag (and other tags) have text strings as the value.
					$this->metadata[$pageId]['reference'] = $this->hasReferenceTag( $text ) ? '1' : '0';
				}
			}
		}
		return true;
	}

	/**
	 * Generate article snippet for listview from article text
	 * @param string $text page text
	 * @param int $pageId Id of the page
	 * @param Language $lang
	 * @param MessageParser $msgParser
	 * @return string
	 */
	public static function generateArticleSnippet( string $text, int $pageId, Language $lang,
		MessageParser $msgParser ) {
		$text = strip_tags( $text );
		$attempt = 0;
		$title = Title::newFromID( $pageId );

		// 10 attempts at most, the logic here is to find the first }} and
		// find the matching {{ for that }}
		while ( $attempt < 10 ) {
			$closeCurPos = strpos( $text, '}}' );

			if ( $closeCurPos === false ) {
				break;
			}
			$tempStr = substr( $text, 0, $closeCurPos + 2 );

			$openCurPos = strrpos( $tempStr, '{{' );
			if ( $openCurPos === false ) {
				$text = substr_replace( $text, '', $closeCurPos, 2 );
			} else {
				$text = substr_replace( $text, '', $openCurPos, $closeCurPos - $openCurPos + 2 );
			}
			$attempt++;
		}

		$text = trim( Sanitizer::stripAllTags(
			$msgParser->parse( $text, $title, true, false, $lang )->getContentHolderText()
		) );
		// strip out non-useful data for snippet
		$text = str_replace( [ '{', '}', '[edit]' ], '', $text );

		return $lang->truncateForDatabase( $text, 255 );
	}

	/**
	 * Check if a page has a reference. This checks <ref> and </ref>
	 * tags along with the presence of the sfn or harvb templates
	 *
	 * @param string $wikitext Article content in wikitext format.
	 * @return bool
	 */
	private function hasReferenceTag( string $wikitext ): bool {
		$closeTag = stripos( $wikitext, '</ref>' );

		if ( $closeTag !== false ) {
			$openTag = stripos( $wikitext, '<ref ' );
			if ( $openTag !== false && $openTag < $closeTag ) {
				return true;
			}
			$openTag = stripos( $wikitext, '<ref>' );
			if ( $openTag !== false && $openTag < $closeTag ) {
				return true;
			}
		}

		$refStyleArray = [ '{{sfn', '{{harvnb' ];
		foreach ( $refStyleArray as $refStyle ) {
			if ( stripos( $wikitext, $refStyle ) !== false ) {
				return true;
			}
		}

		return false;
	}

}

<?php

namespace MediaWiki\Extension\PageTriage\ArticleCompile;

use MediaWiki\MediaWikiServices;
use Sanitizer;
use TextContent;

/**
 * Article snippet
 */
class ArticleCompileSnippet extends ArticleCompileInterface {

	public function compile() {
		foreach ( $this->mPageId as $pageId ) {
			$content = $this->getContentByPageId( $pageId );
			if ( $content ) {
				$text = ( $content instanceof TextContent ) ? $content->getText() : null;
				if ( $text !== null ) {
					$this->metadata[$pageId]['snippet'] = self::generateArticleSnippet( $text );
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
	 * @return string
	 */
	public static function generateArticleSnippet( $text ) {
		global $wgLang;

		$text = strip_tags( $text );
		$attempt = 0;

		// 10 attempts at most, the logic here is to find the first }} and
		// find the matching {{ for that }}
		while ( $attempt < 10 ) {
			$closeCurPos = strpos( $text, '}}' );

			if ( $closeCurPos === false ) {
				break;
			}
			$tempStr = substr( $text, 0, $closeCurPos + 2 );

			$openCurPos = strrpos( $tempStr,  '{{' );
			if ( $openCurPos === false ) {
				$text = substr_replace( $text, '', $closeCurPos, 2 );
			} else {
				$text = substr_replace( $text, '', $openCurPos, $closeCurPos - $openCurPos + 2 );
			}
			$attempt++;
		}

		$text = trim( Sanitizer::stripAllTags(
			MediaWikiServices::getInstance()->getMessageCache()->parse( $text )->getText( [
				'enableSectionEditLinks' => false,
			] )
		) );
		// strip out non-useful data for snippet
		$text = str_replace( [ '{', '}', '[edit]' ], '', $text );

		return $wgLang->truncateForDatabase( $text, 255 );
	}

	/**
	 * Check if a page has a reference. This checks <ref> and </ref>
	 * tags along with the presence of the sfn or harvb templates
	 *
	 * @param string $wikitext Article content in wikitext format.
	 * @return bool
	 */
	private function hasReferenceTag( string $wikitext ): bool {
		$closeTag = strpos( $wikitext, '</ref>' );

		if ( $closeTag !== false ) {
			$openTag = strpos( $wikitext, '<ref ' );
			if ( $openTag !== false && $openTag < $closeTag ) {
				return true;
			}
			$openTag = strpos( $wikitext, '<ref>' );
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

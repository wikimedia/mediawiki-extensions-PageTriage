<?php

namespace MediaWiki\Extension\PageTriage\ArticleCompile;

use ContentHandler;
use MediaWiki\MediaWikiServices;
use Sanitizer;

/**
 * Article snippet
 */
class ArticleCompileSnippet extends ArticleCompileInterface {

	public function compile() {
		foreach ( $this->mPageId as $pageId ) {
			$content = $this->getContentByPageId( $pageId );
			if ( $content ) {
				$text = ContentHandler::getContentText( $content );
				if ( $text !== null ) {
					$this->metadata[$pageId]['snippet'] = self::generateArticleSnippet( $text );
					$this->metadata[$pageId]['reference'] = self::checkReferenceTag( $text );
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
	 * Check if a page has reference, this just checks <ref> and </ref> tags
	 * this is sufficient since we just want to get an estimate
	 * @param string $text
	 * @return string
	 */
	public static function checkReferenceTag( $text ) {
		$closeTag = strpos( $text, '</ref>' );

		if ( $closeTag !== false ) {
			$openTag = strpos( $text, '<ref ' );
			if ( $openTag !== false && $openTag < $closeTag ) {
				return '1';
			}
			$openTag = strpos( $text, '<ref>' );
			if ( $openTag !== false && $openTag < $closeTag ) {
				return '1';
			}
		}

		return '0';
	}

}

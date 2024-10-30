<?php
namespace MediaWiki\Extension\PageTriage\ArticleCompile;

use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\Title\Title;

/**
 * Detects recreated content by comparing two sets of terms to find matching phrases of a specified length.
 * Extracts phrases from recreated content, checks their existence in original content, and collects matches.
 */
class ArticleCompileCheckRecreated extends ArticleCompile {

	/**
	 * @return bool
	 */
	public function compile() {
		$pageIdArray = $this->mPageId;
		$pageId = $pageIdArray[0];
		$titleObj = Title::newFromID( $pageId );
		$title = '';
		if ( $titleObj ) {
			$title = $titleObj->getPrefixedText();
		}
		$logDelSubquery = $this->db->newSelectQueryBuilder()
		->select( '1' )
		->from( 'logging', 'log_del' )
		->where( [
			'log_del.log_type' => 'delete',
			'log_del.log_title' => $title,
		] )
		->limit( 1 );

		$logDelSubquerySQL = $logDelSubquery->getSQL();

		$res = $this->db->newSelectQueryBuilder()
		->select( [ 'DISTINCT page_id' ] )
		->from( 'logging' )
		->join( 'page', null, 'page_title = log_title ' )
		->where( [
			'page_id' => $pageId,
			'EXISTS (' . $logDelSubquerySQL . ')' => true
		] )
		->caller( __METHOD__ )
		->fetchResultSet();

		// Early return if no recreated articles are found
		if ( $res->numRows() === 0 ) {
			return true;
		}

		if ( $res->numRows() > 0 ) {
			$row = $res->current();

			$recreatedContent = $this->getContentByPageId( $row->page_id )->serialize();

			$archiveRes = $this->db->newSelectQueryBuilder()
			->select( [ 'ar_rev_id' ] )
			->from( 'archive' )
			->where( [
				'ar_title' => $title,
			] )
			->orderBy( 'ar_timestamp', 'DESC' )
			->limit( 1 )
			->caller( __METHOD__ )
			->fetchResultSet();

			$archivedContent = '';
			$archiveRow = $archiveRes->current();
			if ( $archiveRow ) {
				$archiveRevId = $archiveRow->ar_rev_id;

				$archivedContent = MediaWikiServices::getInstance()
					->getArchivedRevisionLookup()
					->getArchivedRevisionRecord( null, $archiveRevId )->
					getContent( SlotRecord::MAIN, RevisionRecord::RAW )->serialize();

			}

			if ( abs( strlen( $recreatedContent ) - strlen( $archivedContent ) ) >= 1000 ) {
				$this->metadata[$row->page_id]['content_similarity'] =
					$this->compareContent( $recreatedContent, $archivedContent );
			}
		}

		return true;
	}

	/**
	 * Compares the content of two strings and returns the similarity percentage.
	 *
	 * @param string $content1 The first content string to compare.
	 * @param string $content2 The second content string to compare.
	 * @return float The percentage of matched characters.
	 */
	protected function compareContent( $content1, $content2 ) {
	// Note that these values are default in Duplication Detector Tool
		// github.com/wikigit/Duplication-Detector/blob/master/compare.php#L70
		$minWordCount = 2;
		// github.com/wikigit/Duplication-Detector/blob/master/compare.php#L72
		$minCharCount = 13;

		$terms1 = preg_split( '/\s+/', $content1 );
		$terms2 = preg_split( '/\s+/', $content2 );
		$terms1Positions = $this->computePosts( $terms1, $minWordCount );
		$totalCharacters = strlen( $content1 );
		$matchedPhrases = $this->computeMatches( $terms1, $terms2, $terms1Positions, $minWordCount );
		$alreadyMatchedPhrases = [];
		$matchedCharacterCount = 0;

		foreach ( $matchedPhrases as $matchDetails ) {
			[ $positionInTerms1, $positionInTerms2, $length, $matchedPhrase ] = $matchDetails;
			$skipMatching = false;

			foreach ( $alreadyMatchedPhrases as $existingPhrase ) {
				if ( strpos( $existingPhrase, $matchedPhrase ) !== false ) {
					$skipMatching = true;
					break;
				}
			}

			$phraseCharacterCount = strlen( $matchedPhrase );
			if ( !$skipMatching && $phraseCharacterCount >= $minCharCount ) {
				$matchedCharacterCount += $phraseCharacterCount;
			}

			$alreadyMatchedPhrases[] = $matchedPhrase;
		}

		return $totalCharacters != 0 ? (float)round( $matchedCharacterCount / $totalCharacters * 100 ) : 0.0;
	}

	/**
	 * Computes the positions of word groups (posts) of a given size within an array of terms.
	 *
	 * @param array $terms An array of terms.
	 * @param int $groupSize The size of the word groups to compute positions for.
	 * @return array An associative array where keys are word groups and values are arrays of positions.
	 */
	protected function computePosts( $terms, $groupSize ) {
		$result = [];
		for ( $startIndex = 0; $startIndex <= count( $terms ) - $groupSize; $startIndex++ ) {
			$phrase = implode( ' ', array_slice( $terms, $startIndex, $groupSize ) );
			$result[$phrase][] = $startIndex;
		}
		return $result;
	}

	/**
	 * Computes the matching word groups between two sets of terms.
	 *
	 * @param array $terms1 The first set of terms.
	 * @param array $terms2 The second set of terms.
	 * @param array $terms1Positions The positions of word groups in the first set of terms.
	 * @param int $groupSize The size of the word groups to match.
	 * @return array An array of matching word groups with their positions and lengths.
	 */
	protected function computeMatches( $terms1, $terms2, $terms1Positions, $groupSize ) {
		$matches = [];
	// Iterates through recreated content to find matching phrases of length groupSize
		for ( $startIndexInTerms2 = 0; $startIndexInTerms2 <= count( $terms2 ) - $groupSize; $startIndexInTerms2++ ) {
		// Extract a phrase of length groupSize from terms2 starting at startIndexInTerms2
			$phrase = implode( ' ', array_slice( $terms2, $startIndexInTerms2, $groupSize ) );

		// Check if the extracted phrase exists in terms1Positions
			if ( isset( $terms1Positions[$phrase] ) ) {
				// Iterate through all positions of the phrase in terms1
				foreach ( $terms1Positions[$phrase] as $positionInTerms1 ) {
					// Check for longer matching phrases starting from the current positions
					for ( $length = 0; ; $length++ ) {
						// Check boundaries and string equality
						if ( $startIndexInTerms2 + $length >= count( $terms2 ) ||
						$positionInTerms1 + $length >= count( $terms1 ) ||
						$terms1[$positionInTerms1 + $length] !== $terms2[$startIndexInTerms2 + $length] ) {
							break;
						}
					}
					$matches[] = [
					$positionInTerms1,
					$startIndexInTerms2,
					$length,
					implode( ' ', array_slice( $terms1, $positionInTerms1, $length ) )
					];
				}
			}
		}
		return $matches;
	}

}

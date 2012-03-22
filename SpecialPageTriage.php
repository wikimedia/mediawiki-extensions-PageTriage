<?php
/**
 * This file defines the SpecialPageTriage class which handles the functionality for the 
 * PageTriage list view (Special:PageTriage).
 *
 * @file
 * @ingroup Extensions
 * @author Ryan Kaldari
 */ 
class SpecialPageTriage extends SpecialPage {

	// Holds the various options for viewing the list
	protected $opts;
	
	/**
	 * Initialize the special page.
	 */
	public function __construct() {
		parent::__construct( 'PageTriage' );
	}
	
	/**
	 * Define what happens when the special page is loaded by the user.
	 * @param $sub string The subpage, if any
	 */
	public function execute( $sub ) {
		global $wgOut;

		// Initialize variable to hold list view options
		$opts = new FormOptions();
		
		// Set the defaults for the list view options
		$opts->add( 'showbots', true );
		$opts->add( 'showredirs', false );
		$opts->add( 'showtriaged', false );
		$opts->add( 'limit', (int)$this->getUser()->getOption( 'rclimit' ) );
		$opts->add( 'offset', '' );
		$opts->add( 'namespace', '0' );
		
		// Get the option values from the page request
		$opts->fetchValuesFromRequest( $this->getRequest() );
		
		// Validate the data for the options
		$opts->validateIntBounds( 'limit', 0, 5000 );
		
		// Bind options to member variable
		$this->opts = $opts;
		
		// Output the title of the page
		$wgOut->setPageTitle( wfMessage( 'pagetriage' ) );
		
		// This will hold the HTML for the triage interface
		$triageInterface = '';
		
		// Get triage header
		$triageInterface .= $this->getTriageHeader();
		
		// Get the list of articles
		$triageInterface .= $this->getFormattedTriageList();
		
		// Get triage footer
		$triageInterface .= $this->getTriageFooter();
		
		// Output the HTML for the page
		$wgOut->addHtml( $triageInterface );
		
	}
	
	/**
	 * Builds the header for the list.
	 * This will include the filtering interface and some metadata about the list.
	 * @return string HTML for the header
	 */
	public function getTriageHeader() {
		return Html::Element( 'p', array(), 'Page Triage Header goes here' );
	}
	
	/**
	 * Builds the footer for the list.
	 * This will include the Top Triagers, more list metadata, and a link to detailed statastics.
	 * @return string HTML for the footer
	 */
	public function getTriageFooter() {
		return Html::Element( 'p', array(), 'Page Triage Footer goes here' );
	}

	/**
	 * Builds the list of articles to triage.
	 * This is a list of articles and associated metadata.
	 * @return string HTML for the list
	 */
	public function getFormattedTriageList() {
		
		// Retrieve the IDs of all the pages that match our filtering options
		$pageList = ApiPageTriageList::getPageIds( $this->opts->getAllValues() );
		
		$htmlOut = '';
		
		if ( $pageList ) {
			$articleMetadata = new ArticleMetadata( $pageList );
			$metaData = $articleMetadata->getMetadata();
			foreach ( $pageList as $pageId ) {
				if ( isset( $metaData[$pageId] ) ) {
					$formattedRow = $this->buildRow( $pageId, $metaData[$pageId] );
					$htmlOut .= $formattedRow;
				}
			}
		} else {
			$htmlOut .= wfMessage( 'specialpage-empty' );
		}
		
		return $htmlOut;
	}
	
	/**
	 * Builds a single row for the article list.
	 * @param $pageId integer ID for a single page
	 * @param $metaData array the meta data for $pageId
	 * @return string HTML for the row
	 */
	protected function buildRow( $pageId, $metaData ) {
		
		// TODO: Build the row from metadata provided
		return '<div>'	
				. $pageId . ' '
				. htmlspecialchars( $metaData['title'] ) . ' '
				. htmlspecialchars( $metaData['user_name'] ) .
			'</div>';
		
	}
	
}

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
		$out = $this->getOutput();

		// TODO: check user permissions, make sure they're logged in and have the pagepatrol userright

		global $wgUser;
		$wgUser->setOption( 'pagetriage-lastuse', wfTimestampNow() );
		$wgUser->saveSettings();
		$wgUser->invalidateCache();
		
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
		$out->setPageTitle( wfMessage( 'pagetriage' ) );

		// load the JS
		$out->addModules( array( 'ext.pageTriage.external', 'ext.pageTriage.models', 'ext.pageTriage.views' ) );
				
		// This will hold the HTML for the triage interface
		$triageInterface = '';
		
		$triageInterface .= "<div id='pageTriageHeader'></div>";
		// TODO: this should load with a spinner instead of "please wait"
		$triageInterface .= "<div id='listView'>Please wait...</div>";
		$triageInterface .= "<div id='pageTriageFooter'></div>";
		$triageInterface .= "<div id='backboneTemplates'></div>";
				
		// Get the list of articles
		//$triageInterface .= $this->getFormattedTriageList();
		
		// Output the HTML for the page
		$out->addHtml( $triageInterface );
		
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

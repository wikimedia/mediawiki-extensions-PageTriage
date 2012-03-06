<?php
/**
 * This file defines the SpecialPageTriageList class which handles the functionality for the 
 * PageTriage list view (Special:PageTriageList).
 *
 * @file
 * @ingroup Extensions
 * @author Ryan Kaldari
 */
class SpecialPageTriageList extends SpecialPage {
	
	/**
	 * Initialize the special page.
	 */
	public function __construct() {
		parent::__construct( 'PageTriageList' );
	}
	
	/**
	 * Define what happens when the special page is loaded by the user.
	 * @param $sub string The subpage, if any
	 */
	public function execute( $sub ) {
		global $wgOut;
		
		// Output the title of the page
		$wgOut->setPageTitle( wfMessage( 'pagetriagelist' ) );
		
		// Output the list (or something)
		$wgOut->addHtml( 'Hello World!' );
	}

}

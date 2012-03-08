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
		$triageInterface .= $this->getTriageList();
		
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
	 * This is a paginated list of articles and associated metadata.
	 * @return string HTML for the list
	 */
	public function getTriageList() {
		global $wgOut;
		
		$pager = new TriagePager( $this, $this->opts );
		
		if( $pager->getNumRows() ) {
			$navigation = $pager->getNavigationBar();
			$htmlOut = $navigation . $pager->getBody() . $navigation;
		} else {
			$htmlOut = wfMessage( 'specialpage-empty' );
		}
		
		return $htmlOut;
	}
	
}

class TriagePager extends ReverseChronologicalPager {

	// Holds the various options for viewing the list
	protected $opts;
	
	public function __construct( $special, FormOptions $opts ) {
		parent::__construct( $special->getContext() );
		$this->mLimit = $opts->getValue( 'limit' );
		$this->mOffset = $opts->getValue( 'offset' );
		$this->opts = $opts;
	}
	
	/**
	 * Sort the list by rc_timestamp
	 * @return string
	 */
	public function getIndexField() {
		return 'rc_timestamp';
	}
	
	/**
	 * Set the database query to retrieve all the pages that need triaging
	 * @return array of query settings
	 */
	public function getQueryInfo() {
	
		$conds = array();
		$conds['rc_new'] = 1;
		
		$namespace = $this->opts->getValue( 'namespace' );
		if ( $namespace === 'all' ) {
			$namespace = false;
		} else {
			$namespace = intval( $namespace );
		}
		
		if( $namespace !== false ) {
			$conds['rc_namespace'] = $namespace;
			$rcIndexes = array( 'new_name_timestamp' );
		} else {
			$rcIndexes = array( 'rc_timestamp' );
		}
		
		if( !$this->opts->getValue( 'showbots' ) ) {
			$conds['rc_bot'] = 0;
		}
		
		if ( !$this->opts->getValue( 'showredirs' ) ) {
			$conds['page_is_redirect'] = 0;
		}
		
		$tables = array( 'recentchanges', 'page' );
		
		$fields = array(
			'rc_namespace', 'rc_title', 'rc_cur_id', 'rc_user', 'rc_user_text',
			'rc_comment', 'rc_timestamp', 'rc_patrolled','rc_id', 'rc_deleted',
			'page_len AS length', 'page_latest AS rev_id', 'rc_this_oldid',
			'page_namespace', 'page_title'
		);
		$join_conds = array( 'page' => array( 'INNER JOIN', 'page_id=rc_cur_id' ) );
		
		$info = array(
			'tables' 	 => $tables,
			'fields' 	 => $fields,
			'conds' 	 => $conds,
			'join_conds' => $join_conds
		);
		
		return $info;
	}
	
	public function formatRow( $result ) {
	
		// Create a revision object to work with
		$row = array(
			'comment' => $result->rc_comment,
			'deleted' => $result->rc_deleted,
			'user_text' => $result->rc_user_text,
			'user' => $result->rc_user,
		);
		$rev = new Revision( $row );

		$lang = $this->getLanguage();

		$title = Title::newFromRow( $result );
		$spanTime = Html::element( 'span', array( 'class' => 'mw-pagetriage-time' ),
			$lang->timeanddate( $result->rc_timestamp, true )
		);
		$time = Linker::linkKnown(
			$title,
			$spanTime,
			array(),
			array( 'oldid' => $result->rc_this_oldid ),
			array()
		);

		$query = array( 'redirect' => 'no' );

		// If the user is allowed to triage and the page hasn't been triaged yet, add an rcid param
		// to the article link.
		if( $this->getUser()->useNPPatrol() && !$result->rc_patrolled ) {
			$query['rcid'] = $result->rc_id;
		}

		$pageLink = Linker::linkKnown(
			$title,
			null,
			array( 'class' => 'mw-pagetriage-pagename' ),
			$query
		);
		$histLink = Linker::linkKnown(
			$title,
			wfMsgHtml( 'hist' ),
			array(),
			array( 'action' => 'history' )
		);
		$history = Html::rawElement( 'span', array( 'class' => 'mw-pagetriage-history' ), wfMsg( 'parentheses', $histLink ) );

		$length = Html::element( 'span', array( 'class' => 'mw-pagetriage-length' ),
				$this->msg( 'nbytes' )->numParams( $result->length )->text()
		);

		$userLink = Linker::revUserTools( $rev );
		$comment = Linker::revComment( $rev );

		if ( $result->rc_patrolled ) {
			$class = 'mw-pagetriage-triaged';
		} else {
			$class = 'mw-pagetriage-not-triaged';
		}

		$htmlOut = '';
		$htmlOut .= Xml::openElement( 'div', array(
			'style' => 'border: 1px solid #CCCCCC; border-top: none;',
			'class' => $class,
		) );
		$htmlOut .= "$pageLink $history &#183; $length<br/>";
		$htmlOut .= "&#160;&#160;&#160;&#160;By $userLink";
		$htmlOut .= Xml::closeElement( 'div' );
		
		return $htmlOut;
	}
	
	/**
	 * Begin div at the start of the list
	 * @return string HTML
	 */
	public function getStartBody() {
		$htmlOut = Xml::openElement( 'div', array( 'style' => 'border-top: 1px solid #CCCCCC' ) );
		return $htmlOut;
	}
	
	/**
	 * Close div at the end of the list
	 * @return string HTML
	 */
	public function getEndBody() {
		$htmlOut = Xml::closeElement( 'div' );
		return $htmlOut;
	}
	
}

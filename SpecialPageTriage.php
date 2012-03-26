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
		
		// this template is repeated many times, once for each item in list view.
		$triageInterface .= <<<HTML
			<div id="backboneTemplates">
				<script type="text/template" id="listItemTemplate">
					<div class="mwe-pt-article-row">
						<% if ( afd_status == "1" || blp_prod_status == "1" || csd_status == "1" || prod_status == "1" ) { %>
							<div class="mwe-pt-status-icon mwe-pt-status-icon-deleted">
								[DEL] <!-- deleted -->
							</div>
						<% } else if ( patrol_status == "1" ) { %>
							<div class="mwe-pt-status-icon mwe-pt-status-icon-triaged">
								[TRI] <!-- triaged -->
							</div>
						<% } else { %>
							<div class="mwe-pt-status-icon mwe-pt-status-icon-new">
								[NEW] <!-- not patrolled -->
							</div>
						<% } %>
						</div>
						<div class="mwe-pt-info-pane">
							<div class="mwe-pt-article">
								<span class="mwe-pt-page-title"><a href="<%= partial_url %>"><%= title %></a></span>
								<span class="mwe-pt-histlink">
									(<a href="<%= mw.config.get("wgScriptPath") + "/index.php?title=" + partial_url + "&action=history" %>"><%= gM( "pagetriage-hist" ) %></a>)
								</span>
								<span class="mwe-pt-metadata">
									&#xb7;
									<%= gM( "pagetriage-bytes", page_len ) %>
									&#xb7;
									<%= gM( "pagetriage-edits", rev_count ) %>
									&#xb7;
									<% if( category_count == "0" ) { %>
										<span class="mwe-pt-metadata-warning"><%= gM( "pagetriage-no-categories" ) %></span>
										<% } else { %>
											<%= gM( "pagetriage-categories", category_count ) %>
										<% } %>
										<% if( linkcount == "0" ) { %>
											&#xb7; <span class="mwe-pt-metadata-warning"><%= gM("pagetriage-orphan") %></span>
										<% } %>
								</span>
							</div>
							<div class="mwe-pt-author">
							</div>
							<div class="mwe-pt-snippet">
								<%= snippet %>
							</div>
						</div>
					</div>
					<br/>
				</script>
			</div>
HTML;
				
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

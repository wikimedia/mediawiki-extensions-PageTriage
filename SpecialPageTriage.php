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
		
		$triageInterface .= "<div id='mwe-pt-list-control-nav' class='mwe-pt-navigation-bar'></div>";
		// TODO: this should load with a spinner instead of "please wait"
		$triageInterface .= "<div id='mwe-pt-list-view'>Please wait...</div>";
		$triageInterface .= "<div id='mwe-pt-list-stats-nav' class='mwe-pt-navigation-bar'></div>";
		
		// These are the templates that backbone/underscore render on the client.
		$triageInterface .= <<<HTML
				<!-- individual list item template -->
				<script type="text/template" id="listItemTemplate">
					<% if ( afd_status == "1" || blp_prod_status == "1" || csd_status == "1" || prod_status == "1" ) { %>
						<div class="mwe-pt-article-row mwe-pt-deleted">
							<div class="mwe-pt-status-icon">
								[DEL] <!-- deleted -->
							</div>
					<% } else if ( patrol_status == "1" ) { %>
						<div class="mwe-pt-article-row mwe-pt-triaged">
							<div class="mwe-pt-status-icon mwe-pt-status-icon-triaged">
								[TRI] <!-- triaged -->
							</div>
					<% } else { %>
						<div class="mwe-pt-article-row mwe-pt-new">
							<div class="mwe-pt-status-icon mwe-pt-status-icon-new">
								[NEW] <!-- not triaged -->
							</div>
					<% } %>
					<% if ( position % 2 == 0 ) { %>
						<div class="mwe-pt-info-pane mwe-pt-info-pane-even">
					<% } else { %>
						<div class="mwe-pt-info-pane mwe-pt-info-pane-odd">
					<% } %>
							<div class="mwe-pt-article">
								<span class="mwe-pt-page-title"><a href="<%= mw.util.wikiGetlink( title ) %>"><%= title %></a></span>
								<span class="mwe-pt-histlink">
									(<a href="<%= mw.config.get("wgScriptPath") + "/index.php?title=" + title_url + "&action=history" %>"><%= gM( "pagetriage-hist" ) %></a>)
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
								<span class="mwe-pt-creation-date">
									<%= creation_date_pretty %>
								</span>
							</div>
							<div class="mwe-pt-author">
							<% if( typeof( user_name ) != 'undefined' ) { %>
								<%= gM( 'pagetriage-byline' ) %>
								<a href="<%= user_title.getUrl() %>"><%= user_name %></a>
								<span class="mwe-pt-talk-contribs">
									(<a href="<%= user_talk_title.getUrl() %>">talk</a>
									&#xb7;
									<a href="<%= user_contribs_title.getUrl() %>">contribs</a>)
								</span>
								<!-- editcount is undefined for IP users -->
								<% if( typeof ( user_editcount ) != 'undefined' ) { %>
									&#xb7;
									<%= gM( 'pagetriage-editcount', user_editcount, user_creation_date_pretty ) %>
									<% if( user_bot == "1" ) { %>
										&#xb7;
										<%= gM( 'pagetriage-author-bot' ) %>
									<% } %>
									<% if( user_autoconfirmed == "0" ) { %>
										&#xb7;
										<span class="mwe-pt-metadata-warning">
										<%= gM( 'pagetriage-author-not-autoconfirmed' ) %>
										</span>
									<% } %>
								<% } %>
								<% if( user_block_status == "1" ) { %>
									&#xb7;
									<span class="mwe-pt-metadata-warning">
									<%= gM( 'pagetriage-author-blocked' ) %>
									</span>
								<% } %>
							<% } else { %>
								<%= gM('pagetriage-no-author') %>
							<% } %>
							</div>
							<div class="mwe-pt-snippet">
								<%= snippet %>
							</div>
						</div>
					</div>
				</script>
				
				<script type="text/template" id="listControlNavTemplate">
					<span class="mwe-pt-control-label"><b><%= gM( 'pagetriage-showing' ) %></b> some things</span>
					<span class="mwe-pt-control-label-right"><%= gM( 'pagetriage-article-count', 100, 'untriaged' ) %></span><br/>
					<span class="mwe-pt-control-label"><b><%= gM( 'pagetriage-filter-list-prompt' ) %> &#x25b8;</b></span><!-- &#x25be; is the down arrow -->
					<span class="mwe-pt-control-label-right"><b><%= gM( 'pagetriage-viewing' ) %></b> Sort Controls</span>
				</script>
				
				<script type="text/template" id="listStatsNavTemplate">
					stats navbar
				</script>
				
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

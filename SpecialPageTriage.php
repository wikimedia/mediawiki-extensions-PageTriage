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
		global $wgRequest, $wgPageTriageInfiniteScrolling;
		$out = $this->getOutput();

		// TODO: check user permissions, make sure they're logged in and have the pagepatrol userright

		global $wgUser;
		$wgUser->setOption( 'pagetriage-lastuse', wfTimestampNow() );
		$wgUser->saveSettings();
		$wgUser->invalidateCache();
		
		// Output the title of the page
		$out->setPageTitle( wfMessage( 'pagetriage' ) );

		// Set whether or not to do infinite scrolling based on config variable
		if ( is_bool( $wgPageTriageInfiniteScrolling ) ) {
			// Convert to string
			$infiniteScroll = $wgPageTriageInfiniteScrolling ? 'true' : 'false';
		} else {
			$infiniteScroll = $wgPageTriageInfiniteScrolling;
		}
		
		// Allow infinite scrolling override from query string parameter
		// We don't use getBool() here since the param is optional
		if ( $wgRequest->getText( 'infinite' ) === 'true' ) {
			$infiniteScroll = 'true';
		} else if ( $wgRequest->getText( 'infinite' ) === 'false' ) {
			$infiniteScroll = 'false';
		}

		// Set the infinite scrolling flag in JavaScript
		$out->addScript( "<script type=\"text/javascript\">mw.config.set({\"wgPageTriageInfiniteScrolling\":" . 
			$infiniteScroll . "});</script>" );

		// Load the JS
		$out->addModules( array( 'ext.pageTriage.external', 'ext.pageTriage.models', 'ext.pageTriage.views.list' ) );
				
		// This will hold the HTML for the triage interface
		$triageInterface = '';

		$triageInterface .= "<div id='mwe-pt-list-control-nav' class='mwe-pt-navigation-bar mwe-pt-control-gradient'>";
		$triageInterface .= "<div id='mwe-pt-list-control-nav-content'></div>";
		$triageInterface .= "</div>";

		// TODO: this should load with a spinner instead of "please wait"
		$triageInterface .= "<div id='mwe-pt-list-view'>Please wait...</div>";
		$triageInterface .= "<div id='mwe-pt-list-more' style='display: none;'>";
		$triageInterface .= "<a href='#' id='mwe-pt-list-more-link'>".wfMessage( 'pagetriage-more' )."</a>";
		$triageInterface .= "</div>";
		$triageInterface .= "<div id='mwe-pt-list-load-more-anchor'></div>";
		$triageInterface .= "<div id='mwe-pt-list-stats-nav' class='mwe-pt-navigation-bar mwe-pt-control-gradient'>";
		$triageInterface .= "<div id='mwe-pt-list-stats-nav-content'></div>";
		$triageInterface .= "</div>";
		$triageInterface .= "<div id='mwe-pt-list-stats-nav-anchor'></div>";

		// These are the templates that backbone/underscore render on the client.
		// It would be awesome if they lived in separate files, but we need to figure out how to make RL do that for us.
		// Syntax documentation can be found at http://documentcloud.github.com/underscore/#template.
		$triageInterface .= <<<HTML
				<!-- individual list item template -->
				<script type="text/template" id="listItemTemplate">
					<% if ( afd_status == "1" || blp_prod_status == "1" || csd_status == "1" || prod_status == "1" ) { %>
						<div class="mwe-pt-article-row mwe-pt-deleted">
							<div class="mwe-pt-status-icon">&#160;</div>
					<% } else if ( patrol_status == "1" ) { %>
						<div class="mwe-pt-article-row mwe-pt-reviewed">
							<div class="mwe-pt-status-icon">&#160;</div>
					<% } else { %>
						<div class="mwe-pt-article-row mwe-pt-new">
							<div class="mwe-pt-status-icon">&#160;</div>
					<% } %>
					<% if ( position % 2 == 0 ) { %>
						<div class="mwe-pt-info-pane mwe-pt-info-pane-even">
					<% } else { %>
						<div class="mwe-pt-info-pane mwe-pt-info-pane-odd">
					<% } %>
							<div class="mwe-pt-article">
								<span class="mwe-pt-page-title"><a href="<%= mw.util.wikiGetlink( title ) %>"><%= mw.html.escape( title ) %></a></span>
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
								<a class="mwe-pt-list-triage-button ui-button-blue" href="<%= mw.util.wikiGetlink( title ) %>"></a>
							</div>
							<div class="mwe-pt-author">
							<% if( typeof( user_name ) != 'undefined' ) { %>
								<%= gM( 'pagetriage-byline' ) %>
								<a <%= userPageLinkClass %> href="<%= user_title.getUrl() %>"><%= mw.html.escape( user_name ) %></a>
								<span class="mwe-pt-talk-contribs">
									(<a <%= talkPageLinkClass %> href="<%= user_talk_title.getUrl()+'&redlink=1' %>">talk</a>
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
								<%= mw.html.escape( snippet ) %>
							</div>
						</div>
					</div>
				</script>

				<!-- top nav template -->
				<script type="text/template" id="listControlNavTemplate">
					<span class="mwe-pt-control-label"><b><%= gM( 'pagetriage-showing' ) %></b> <%= filterStatus %></span>
					<span class="mwe-pt-control-label-right" id="mwe-pt-control-stats"></span><br/>
					<span id="mwe-pt-filter-dropdown-control" class="mwe-pt-control-label">
						<b>
							<%= gM( 'pagetriage-filter-list-prompt' ) %>
							<span id="mwe-pt-dropdown-arrow">&#x25b8;</span>
							<!--<span class="mwe-pt-dropdown-open">&#x25be;</span>-->
						</b>
						<div id="mwe-pt-control-dropdown" class="mwe-pt-control-gradient shadow">
							<div id="mwe-pt-control-dropdown-pokey"></div>
							<form>
								<div class="mwe-pt-control-section">
									<span class="mwe-pt-control-label"><b><%= gM( 'pagetriage-filter-show-heading' ) %></b></span>
									<div class="mwe-pt-control-options">
										<input type="checkbox" id="mwe-pt-filter-reviewed-edits" /> <%= gM( 'pagetriage-filter-reviewed-edits' ) %> <br/>
										<input type="checkbox" id="mwe-pt-filter-nominated-for-deletion" /> <%= gM( 'pagetriage-filter-nominated-for-deletion' ) %> <br/>
										<input type="checkbox" id="mwe-pt-filter-redirects" /> <%= gM( 'pagetriage-filter-redirects' ) %> <br/>
									</div>
								</div>
								<div class="mwe-pt-control-section">
									<span class="mwe-pt-control-label"><b><%= gM( 'pagetriage-filter-namespace-heading' ) %></b></span>
									<div class="mwe-pt-control-options">
										<select id="mwe-pt-filter-namespace">
											<option value=""><%= gM( 'pagetriage-filter-ns-all' ) %></option>
											<%
												var wgFormattedNamespaces = mw.config.get( 'wgFormattedNamespaces' );
												var nsOptions = '';
												for ( var key in wgFormattedNamespaces ) {
													if ( wgFormattedNamespaces[key] == '' ) {
														nsOptions += String('<option value="' + String(key) + '">' + gM( 'pagetriage-filter-ns-article' ) + '</option>');
													} else if( key > 0 ) {
														nsOptions += String('<option value="' + String(key) + '">' + wgFormattedNamespaces[key] + '</option>');
													}
												}
												print(nsOptions);
											%>
										</select>
									</div>
								</div>
								<!-- abusefilter tags come later.
								<div class="mwe-pt-control-section">
									<span class="mwe-pt-control-label"><b><%= gM( 'pagetriage-filter-tag-heading' ) %></b></span>
									<div class="mwe-pt-control-options">
										<input type=text id="mwe-pt-filter-tag" />
									</div>
								</div>
								-->
								<div class="mwe-pt-control-section">
									<span class="mwe-pt-control-label"><b><%= gM( 'pagetriage-filter-second-show-heading' ) %></b></span>
									<div class="mwe-pt-control-options">
										<input type="radio" name="mwe-pt-filter-radio" id="mwe-pt-filter-all" /> <%= gM( 'pagetriage-filter-all' ) %> <br/>
										<input type="radio" name="mwe-pt-filter-radio" id="mwe-pt-filter-no-categories" /> <%= gM( 'pagetriage-filter-no-categories' ) %> <br/>
										<input type="radio" name="mwe-pt-filter-radio" id="mwe-pt-filter-orphan" /> <%= gM( 'pagetriage-filter-orphan' ) %> <br/>
										<input type="radio" name="mwe-pt-filter-radio" id="mwe-pt-filter-non-autoconfirmed" /> <%= gM( 'pagetriage-filter-non-autoconfirmed' ) %> <br/>
										<input type="radio" name="mwe-pt-filter-radio" id="mwe-pt-filter-blocked" /> <%= gM( 'pagetriage-filter-blocked' ) %> <br/>
										<input type="radio" name="mwe-pt-filter-radio" id="mwe-pt-filter-bot-edits" /> <%= gM( 'pagetriage-filter-bot-edits' ) %> <br/>
										<input type="radio" name="mwe-pt-filter-radio" id="mwe-pt-filter-user-selected" /> <%= gM( 'pagetriage-filter-user-heading' ) %>
										<div class="mwe-pt-control-options">
											<span class="mwe-pt-filter-sub-input"><input type=text id="mwe-pt-filter-user" /></span>
										</div>
									</div>
								</div>
								<div class="mwe-pt-control-buttons">
									<div id="mwe-pt-filter-set-button" class="mwe-pt-filter-set-button ui-button-green"></div>
								</div>
							</form>
						</div>
					</span>
					<span class="mwe-pt-control-label-right"><b><%= gM( 'pagetriage-sort-by' ) %></b>
						<a href="#" id="mwe-pt-sort-newest"><%= gM( 'pagetriage-newest' ) %></a>
						<a href="#" id="mwe-pt-sort-oldest"><%= gM( 'pagetriage-oldest' ) %></a>
					</span>
				</script>

				<!-- bottom nav template -->
				<script type="text/template" id="listStatsNavTemplate">
					<div id="mwe-pt-stats-nav">
						<div class="mwe-pt-top-triager">
							<%
							if ( toptriager.total ) {
							%>
								<span class="mwe-pt-stats-label"><%= ptrTopTriagerStr %></span>
								<%
								var triagerLinks = new Array();
								for ( var m in ptrTopTriager ) {
									triagerLinks.push( "<a " + ptrTopTriager[m].linkCSS + " href=\"" + ptrTopTriager[m].title.getUrl() + "\">" + mw.html.escape(ptrTopTriager[m].userName ) + "</a>" );
								}
								var triagers = triagerLinks.join( gM( 'comma-separator' ) );
								%>
								<%= triagers %>
							<%
							}
							%>
						</div>
						<div class="mwe-pt-article-age-stats">
							<% if ( ptrAverage ) { %> <%= gM( 'pagetriage-stats-unreviewed-age', ptrAverage, ptrOldest ) %> <% } %>
						</div>
					</div>
				</script>

HTML;

		// Output the HTML for the page
		$out->addHtml( $triageInterface );

	}

}

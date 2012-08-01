<?php
/**
 * This file defines the SpecialNewPagesFeed class which handles the functionality for the
 * New Pages Feed (Special:NewPagesFeed).
 *
 * @file
 * @ingroup Extensions
 * @author Ryan Kaldari
 */
class SpecialNewPagesFeed extends SpecialPage {

	// Holds the various options for viewing the list
	protected $opts;

	/**
	 * Initialize the special page.
	 */
	public function __construct() {
		parent::__construct( 'NewPagesFeed' );
	}

	/**
	 * Define what happens when the special page is loaded by the user.
	 * @param $sub string The subpage, if any
	 */
	public function execute( $sub ) {
		global $wgRequest, $wgPageTriageInfiniteScrolling,
		       $wgPageTriageStickyControlNav, $wgPageTriageStickyStatsNav,
		       $wgPageTriageLearnMoreUrl, $wgPageTriageFeedbackUrl,
		       $wgPageTriageNamespaces;

		$out = $this->getOutput();

		global $wgUser;

		if ( !$wgUser->isAnon() ) {
			$wgUser->setOption( 'pagetriage-lastuse', wfTimestampNow() );
			$wgUser->saveSettings();
			$wgUser->invalidateCache();
		}

		// Output the title of the page
		$out->setPagetitle( $this->msg( 'newpagesfeed' ) );

		// Make sure global vars are strings rather than booleans (for passing to mw.config)
		$wgPageTriageInfiniteScrolling = $this->booleanToString( $wgPageTriageInfiniteScrolling );
		$wgPageTriageStickyControlNav = $this->booleanToString( $wgPageTriageStickyControlNav );
		$wgPageTriageStickyStatsNav = $this->booleanToString( $wgPageTriageStickyStatsNav );

		// Allow infinite scrolling override from query string parameter
		// We don't use getBool() here since the param is optional
		if ( $wgRequest->getText( 'infinite' ) === 'true' ) {
			$wgPageTriageInfiniteScrolling = 'true';
		} else if ( $wgRequest->getText( 'infinite' ) === 'false' ) {
			$wgPageTriageInfiniteScrolling = 'false';
		}

		// Set the config flags in JavaScript
		$globalVars = array(
			'wgPageTriageNamespaces' => $wgPageTriageNamespaces,
			'wgPageTriageInfiniteScrolling' => $wgPageTriageInfiniteScrolling,
			'wgPageTriageStickyControlNav' => $wgPageTriageStickyControlNav,
			'wgPageTriageStickyStatsNav' => $wgPageTriageStickyStatsNav,
			'wgPageTriageEnableReviewButton' => $wgUser->isLoggedIn() && $wgUser->isAllowed( 'patrol' ),
		);
		$out->addJsConfigVars( $globalVars );

		// Load the JS
		$out->addModules( array( 'ext.pageTriage.external', 'ext.pageTriage.util', 'ext.pageTriage.models', 'ext.pageTriage.views.list' ) );

		$warnings = '';
		$warnings .= "<div id='mwe-pt-list-warnings' style='display: none;'>";
		$warnings .= "<div>".$this->msg( 'pagetriage-warning-prototype', $wgPageTriageLearnMoreUrl, $wgPageTriageFeedbackUrl )->text()."</div>";
		$warnings .= "</div>";
		$out->addHtml( $warnings );

		// This will hold the HTML for the triage interface
		$triageInterface = '';

		$triageInterface .= "<div id='mwe-pt-list-control-nav-anchor'></div>";
		$triageInterface .= "<div id='mwe-pt-list-control-nav' class='mwe-pt-navigation-bar mwe-pt-control-gradient'>";
		$triageInterface .= "<div id='mwe-pt-list-control-nav-content'></div>";
		$triageInterface .= "</div>";

		// TODO: this should load with a spinner instead of "please wait"
		$triageInterface .= "<div id='mwe-pt-list-view'>".$this->msg( 'pagetriage-please-wait' )."</div>";
		$triageInterface .= "<div id='mwe-pt-list-errors' style='display: none;'></div>";
		$triageInterface .= "<div id='mwe-pt-list-more' style='display: none;'>";
		$triageInterface .= "<a href='#' id='mwe-pt-list-more-link'>".$this->msg( 'pagetriage-more' )."</a>";
		$triageInterface .= "</div>";
		$triageInterface .= "<div id='mwe-pt-list-load-more-anchor'></div>";
		$triageInterface .= "<div id='mwe-pt-list-stats-nav' class='mwe-pt-navigation-bar mwe-pt-control-gradient' style='display: none;'>";
		$triageInterface .= "<div id='mwe-pt-list-stats-nav-content'></div>";
		$triageInterface .= "</div>";
		$triageInterface .= "<div id='mwe-pt-list-stats-nav-anchor'></div>";

		// These are the templates that backbone/underscore render on the client.
		// It would be awesome if they lived in separate files, but we need to figure out how to make RL do that for us.
		// Syntax documentation can be found at http://documentcloud.github.com/underscore/#template.
		$triageInterface .= <<<HTML
				<!-- top nav template -->
				<script type="text/template" id="listControlNavTemplate">
					<span class="mwe-pt-control-label"><b><%= gM( 'pagetriage-showing' ) %></b> <span id="mwe-pt-filter-status"></span></span>
					<span class="mwe-pt-control-label-right" id="mwe-pt-control-stats"></span><br/>
					<span id="mwe-pt-filter-dropdown-control" class="mwe-pt-control-label">
						<b>
							<%= gM( 'pagetriage-filter-list-prompt' ) %>
							<span id="mwe-pt-dropdown-arrow">&#x25b8;</span>
							<!--<span class="mwe-pt-dropdown-open">&#x25be;</span>-->
						</b>
						<div id="mwe-pt-control-dropdown-pokey"></div>
						<div id="mwe-pt-control-dropdown" class="mwe-pt-control-gradient shadow">
							<form>
								<div class="mwe-pt-control-section">
									<span class="mwe-pt-control-label"><b><%= gM( 'pagetriage-filter-show-heading' ) %></b></span>
									<div class="mwe-pt-control-options">
										<input type="checkbox" id="mwe-pt-filter-unreviewed-edits" /> <%= gM( 'pagetriage-filter-unreviewed-edits' ) %> <br/>
										<input type="checkbox" id="mwe-pt-filter-reviewed-edits" /> <%= gM( 'pagetriage-filter-reviewed-edits' ) %> <br/>
										<input type="checkbox" id="mwe-pt-filter-nominated-for-deletion" /> <%= gM( 'pagetriage-filter-nominated-for-deletion' ) %> <br/>
										<input type="checkbox" id="mwe-pt-filter-redirects" /> <%= gM( 'pagetriage-filter-redirects' ) %> <br/>
									</div>
								</div>
								<div class="mwe-pt-control-section">
									<span class="mwe-pt-control-label"><b><%= gM( 'pagetriage-filter-namespace-heading' ) %></b></span>
									<div class="mwe-pt-control-options">
										<select id="mwe-pt-filter-namespace">
											<!--<option value=""><%= gM( 'pagetriage-filter-ns-all' ) %></option>-->
											<%
												var wgFormattedNamespaces = mw.config.get( 'wgFormattedNamespaces' );
												var wgPageTriageNamespaces = mw.config.get( 'wgPageTriageNamespaces' );
												var nsOptions = '', namespaceNumber;
												for ( var key in wgFormattedNamespaces ) {
													namespaceNumber = wgPageTriageNamespaces[key];
													if ( typeof wgFormattedNamespaces[namespaceNumber] === 'undefined' ) {
														continue;
													}
													if ( wgFormattedNamespaces[namespaceNumber] === '' ) {
														nsOptions += String('<option value="' + String(namespaceNumber) + '">' + gM( 'pagetriage-filter-article' ) + '</option>');
													} else {
														nsOptions += String('<option value="' + String(namespaceNumber) + '">' + wgFormattedNamespaces[namespaceNumber] + '</option>');
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
										<input type="radio" name="mwe-pt-filter-radio" id="mwe-pt-filter-no-categories" /> <%= gM( 'pagetriage-filter-no-categories' ) %> <br/>
										<input type="radio" name="mwe-pt-filter-radio" id="mwe-pt-filter-orphan" /> <%= gM( 'pagetriage-filter-orphan' ) %> <br/>
										<input type="radio" name="mwe-pt-filter-radio" id="mwe-pt-filter-non-autoconfirmed" /> <%= gM( 'pagetriage-filter-non-autoconfirmed' ) %> <br/>
										<input type="radio" name="mwe-pt-filter-radio" id="mwe-pt-filter-blocked" /> <%= gM( 'pagetriage-filter-blocked' ) %> <br/>
										<input type="radio" name="mwe-pt-filter-radio" id="mwe-pt-filter-bot-edits" /> <%= gM( 'pagetriage-filter-bot-edits' ) %> <br/>
										<input type="radio" name="mwe-pt-filter-radio" id="mwe-pt-filter-user-selected" /> <%= gM( 'pagetriage-filter-user-heading' ) %>
										<input type="text" id="mwe-pt-filter-user" placeholder="<%= gM( 'pagetriage-filter-username' ) %>" /> <br/>
										<input type="radio" name="mwe-pt-filter-radio" id="mwe-pt-filter-all" /> <%= gM( 'pagetriage-filter-all' ) %>
									</div>
								</div>
								<div class="mwe-pt-control-buttons">
									<div id="mwe-pt-filter-set-button" class="ui-button-green"></div>
								</div>
							</form>
						</div>
					</span>
					<span class="mwe-pt-control-label-right"><b><%= gM( 'pagetriage-sort-by' ) %></b>
						<span id="mwe-pt-sort-buttons">
							<input type="radio" id="mwe-pt-sort-newest" name="sort" /><label for="mwe-pt-sort-newest"><%= gM( 'pagetriage-newest' ) %></label>
							<input type="radio" id="mwe-pt-sort-oldest" name="sort" /><label for="mwe-pt-sort-oldest"><%= gM( 'pagetriage-oldest' ) %></label>
						</span>
					</span>
				</script>

				<!-- bottom nav template -->
				<script type="text/template" id="listStatsNavTemplate">
					<div id="mwe-pt-refresh-button-holder"><button id="mwe-pt-refresh-button"><%= gM( 'pagetriage-refresh-list' ) %></button></div>
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
				</script>

HTML;

		// Output the HTML for the triage interface
		$out->addHtml( $triageInterface );

	}

	/**
	 * Helper function to convert booleans to strings (for passing to mw.config)
	 * @param boolean $value The value to convert into a string
	 * @return bool|string
	 */
	private function booleanToString( $value ) {
		if ( is_string( $value ) ) {
			return $value;
		} else {
			// Convert to string
			return $value ? 'true' : 'false';
		}
	}

}

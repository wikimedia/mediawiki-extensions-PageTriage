( function( $ ) { 

var	$currentArticle = 'Hello World'; // the title of the current article being reviewed
	
$.pageTriage = {
	tagArticle: function() {
		var tagsToApply = new Array;
		$.each( $( '#ptr-checkboxes input:checkbox' ), function( index, value ) {
			if ( $( this ).is( ':checked' ) ) {
				// Add it to the list
			}
		} );
		
		var sendData = {
			'action': 'edit',
			'title': $currentArticle,
			'text' : $newText,
			'token': mw.user.tokens.get( 'editToken' ), // MW 1.18 and later
			'summary': 'Triaging the page',
			'notminor': true
		};

		$.ajax( {
			'url': mw.util.wikiScript( 'api' ),
			'data': sendData,
			'dataType': 'json',
			'type': 'POST'
		} );
	},

	loadPage: function() {
	
		// Get some info about the latest revision of the article
		var sendData = {
			'action': 'query',
			'prop': 'revisions',
			'titles': $currentArticle,
			'rvlimit': 1,
			'rvprop': 'timestamp',
			'format': 'json'
		};
		$.ajax( {
			'url': mw.util.wikiScript( 'api' ),
			'data': sendData,
			'dataType': 'json',
			'type': 'GET',
			'success': function( data ) {
				if ( !data || !data.query || !data.query.pages ) {
					// Show error
					return;
				}
				$.each( data.query.pages, function( id, page ) {
					if ( page.revisions[0].timestamp && page.revisions[0].timestamp.length ) {
						//$( '#ptr-stuff' ).append( page.revisions[0].timestamp );
					}
				});
			}
		} );
		
		// Load the article into the page
		$( '#ptr-stuff' ).load( 
			mw.config.get( 'wgServer' ) 
			+ mw.config.get( 'wgScriptPath' ) 
			+ '/index.php?title=' + encodeURIComponent( $currentArticle ) + '&action=render'
		);
		
	}
};

$( document ).ready( $.pageTriage.loadPage );
} ) ( jQuery );

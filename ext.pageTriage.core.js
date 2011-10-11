( function( $ ) { 

var	$currentArticle = null; // the title of the current article being reviewed
	
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
			'token': $token,
			'summary': 'Triaging the page',
			'notminor': true
		};

		$.ajax( {
			url: mw.util.wikiScript( 'api' ),
			data: sendData,
			dataType: 'json',
			type: 'POST'
		} );
	},

	loadPage: function() {
		$( '#ptr-stuff' ).append( "Article goes here!" );
		// Load in an article
	}
};

$( document ).ready( $.pageTriage.loadPage );
} ) ( jQuery );

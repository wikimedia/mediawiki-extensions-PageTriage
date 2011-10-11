( function( $ ) { 

var	$currentArticle = null, // the title of the current article being reviewed
	
$.pageTriage = {
	tagArticle: function() {
		var sendData = {
			'action': 'edit',
			'title': $currentArticle,
			'text' : $newText,
			'token': $token,
			'summary': 'Triaging the page,
			'notminor': true,
		};

		$.ajax( {
			url: mw.config.get( 'wgScriptPath' ) + '/api.php',
			data: sendData,
			dataType: 'json',
			type: 'POST',
		} );
	},

	loadArticle: function() {
		// Load in an article
	}

	$( document ).ready( $.pageTriage.loadArticle );
}

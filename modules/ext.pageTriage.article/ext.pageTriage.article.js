// Handles the interface for actually marking an article as reviewed
//
( function( $ ) {
	if ( !mw.pageTriage ) {
		mw.pageTriage = {};
	}
	mw.pageTriage.action = {
		submit: function() {
			apiRequest = {
				'action': 'pagetriageaction',
				'pageid': wgArticleId,
				'reviewed': '1',
				'token': mw.user.tokens.get('editToken'),
				'format': 'json'
			};

			return $.ajax( {
				type: 'post',
				url: mw.util.wikiScript( 'api' ),
				data: apiRequest,
				success: this.callback,
				dataType: 'json'
			} );
		},

		callback: function( data ) {
			$( '.mw-pagetriage-markpatrolled' ).html(
				data.error ?
					mw.msg( 'pagetriage-mark-as-reviewed-error' ) :
					mw.msg( 'pagetriage-reviewed' )
			);
		}
	};

	$( '.mw-pagetriage-markpatrolled-link' )
		.click( function() {
				mw.pageTriage.action.submit();
				return false;
			} )
		.end();
} ) ( jQuery );

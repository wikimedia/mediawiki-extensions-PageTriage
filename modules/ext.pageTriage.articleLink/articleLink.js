/**
 * Handles the interface for the "Mark this page as reviewed" link that is shown in
 * the bottom right corner of articles when $wgPageTriageEnableCurationToolbar = false.
 *
 * This is different from the [Mark this page as patrolled] link, which is also placed
 * at the bottom right of articles by the MediaWiki core patrolled edits system. In fact,
 * these two links end up adjacent to each other.
 */

const action = {
	submit: function () {
		return new mw.Api().postWithToken( 'csrf', {
			action: 'pagetriageaction',
			pageid: mw.config.get( 'wgArticleId' ),
			reviewed: '1'
		} )
			.then( function () {
				$( '.mw-pagetriage-markpatrolled' ).text( mw.msg( 'pagetriage-reviewed' ) );
			} )
			.catch( function ( _errorCode, data ) {
				$( '.mw-pagetriage-markpatrolled' ).text( mw.msg( 'pagetriage-mark-as-reviewed-error', data.error.info ) );
			} );
	}
};

$( function () {
	$( '.mw-pagetriage-markpatrolled-link' )
		.on( 'click', function () {
			action.submit();
			return false;
		} );
} );

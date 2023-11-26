const portletConfig = [
	'p-tb',
	'#',
	mw.msg( 'pagetriage-unreview-title' ),
	'p-pagetriage-unreview',
	mw.msg( 'pagetriage-unreview-tooltip' )
];

function onClick() {
	OO.ui.prompt( mw.msg( 'pagetriage-unreview-summary' ), {
	} ).then( function ( unreviewNote ) {
		if ( !unreviewNote ) {
			return;
		}

		new mw.Api().postWithToken( 'csrf', {
			action: 'pagetriageaction',
			pageid: mw.config.get( 'wgArticleId' ),
			reviewed: 0,
			note: unreviewNote
		} ).then( function ( result ) {
			if ( result.pagetriageaction && result.pagetriageaction.result === 'success' ) {
				document.location.reload();
			}
		} );
	} );
}

module.exports = {
	portletConfig,
	onClick
};

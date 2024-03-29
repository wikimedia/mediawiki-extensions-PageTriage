const portletConfig = [
	'p-tb',
	'#',
	mw.msg( 'pagetriage-enqueue-title' ),
	'p-pagetriage-enqueue',
	mw.msg( 'pagetriage-enqueue-tooltip' )
];

function onClick() {
	OO.ui.confirm( mw.msg( 'pagetriage-enqueue-confirmation' ), {
	} ).then( function ( enqueue ) {
		if ( !enqueue ) {
			return;
		}

		new mw.Api().postWithToken( 'csrf', {
			action: 'pagetriageaction',
			pageid: mw.config.get( 'wgArticleId' ),
			enqueue: 1
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

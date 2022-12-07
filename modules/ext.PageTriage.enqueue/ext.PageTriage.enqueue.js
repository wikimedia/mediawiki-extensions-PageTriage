( ( function ( mw, $ ) {
	const sidebarLink = mw.util.addPortletLink(
		'p-tb',
		'#',
		mw.msg( 'pagetriage-enqueue-title' ),
		'p-pagetriage-enqueue',
		mw.msg( 'pagetriage-enqueue-tooltip' )
	);
	let loading = false;

	$( sidebarLink ).on( 'click', function () {
		if ( loading ) {
			return false;
		}
		loading = true;
		mw.loader.using( 'oojs-ui-windows' ).then( function () {
			loading = false;
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
		} );

		// Don't follow the link
		return false;
	} );
} )( mediaWiki, jQuery ) );

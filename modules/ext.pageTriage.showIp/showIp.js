// makes an API request to retrieve IP information for a given temp account/revision combination
// see https://www.mediawiki.org/wiki/Extension:CheckUser for more details
const getIpAddress = ( target, token, deferred, revId ) => {
	const restApi = new mw.Rest();
	restApi.post(
		`/checkuser/v0/temporaryaccount/${ target }/revisions/${ revId }`,
		{ token: token }
	).then(
		( data ) => {
			deferred.resolve( data );
		},
		( err, errObject ) => {
			deferred.reject( err, errObject );
		}
	);
};

// makes the API request to retrieve the revision id for the new page creation record
const getRevisionId = ( title, api ) => {
	const revisionQueryParams = {
		action: 'query',
		prop: 'revisions',
		titles: title,
		rvprop: 'ids',
		rvlimit: 1,
		rvslots: 'main',
		formatversion: '2',
		format: 'json',
		rvdir: 'newer'
	};
	return api.get( revisionQueryParams )
		.then(
			( data ) => {
				const pages = data.query.pages;
				if ( pages && pages.length === 0 ) {
					return undefined;
				}
				const revisions = pages[ 0 ].revisions;
				if ( revisions && revisions.length === 0 ) {
					return undefined;
				}
				return revisions[ 0 ].revid;
			}
		);
};

const getTempAccountUserIpAddress = ( target, title ) => {
	const api = new mw.Api();
	const deferred = $.Deferred();
	api.getToken( 'csrf' )
		.then( ( token ) => {
			getRevisionId( title, api )
				.then( ( revid ) => getIpAddress( target, token, deferred, revid ) );
		// eslint-disable-next-line no-restricted-syntax
		} ).fail( ( err, errObject ) => {
			deferred.reject( err, errObject );
		} );
	return deferred.promise();
};

// finds the element to add the show ip link to
function createShowIpLink( k, v, title ) {
	const $element = $( v );
	// get the temporary account element
	const $tempUserLink = $element.find( '.mw-tempuserlink' );
	// get the empty showIp link element
	const $showIpLink = $element.find( '.ext-page-triage-tempaccount-show-ip-link' );
	if ( $showIpLink ) {
		$showIpLink.append( mw.msg( 'pagetriage-new-page-feed-show-ip' ) );
		$showIpLink.on( 'click', () => {
			const username = $tempUserLink.text();
			getTempAccountUserIpAddress( username, title ).then( ( { ips } ) => {
				let ipResult;
				for ( const key in ips ) {
					ipResult = ips[ key ];
				}
				if ( ipResult ) {
					$showIpLink.replaceWith( `<a class="cdx-link" href="/wiki/Special:IPContributions/${ ipResult }">${ ipResult }</a>` );
				} else {
					$showIpLink.replaceWith( `${ mw.msg( 'pagetriage-new-page-feed-show-ip-not-found' ) }` );
				}
			} );
		} );
	}
	return ( k, v );
}

const showIp = ( content ) => {
	const row = content.find( '.mwe-vue-pt-info-row-block-left' );
	const title = content.find( '[data-page-title]' ).text();
	row.each( ( index, element ) => {
		createShowIpLink( index, element, title );
	} );
};

const useShowIpHook = () => {
	mw.hook( 'wikipage.content' ).add( ( $content ) => {
		showIp( $content );
	} );
};

module.exports = {
	getIpAddress,
	getRevisionId,
	getTempAccountUserIpAddress,
	showIp,
	useShowIpHook
};

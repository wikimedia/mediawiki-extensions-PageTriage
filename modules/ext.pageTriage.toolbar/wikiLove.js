// view for sending WikiLove to the article contributors

const ToolView = require( './ToolView.js' );
module.exports = ToolView.extend( {
	id: 'mwe-pt-wikilove',
	icon: 'icon_wikilove.png', // the default icon
	title: mw.msg( 'wikilove' ),
	tooltip: 'pagetriage-wikilove-tooltip',
	template: mw.template.get( 'ext.pageTriage.toolbar', 'wikilove.underscore' ),

	bySortedValue: function ( obj, callback, context ) {
		const tuples = [];
		for ( const key in obj ) {
			tuples.push( [ key, obj[ key ] ] );
		}
		tuples.sort( ( a, b ) => a[ 1 ] - b[ 1 ] );

		let length = tuples.length;
		while ( length-- ) {
			callback.call( context, tuples[ length ][ 0 ], tuples[ length ][ 1 ] );
		}
	},

	render: function () {
		// get the article's creator
		const creator = this.model.get( 'user_name' );
		const creatorHidden = this.model.get( 'creator_hidden' );

		// get the last 20 editors of the article
		const contributorArray = [];
		this.model.revisions.each( ( revision ) => {
			if ( typeof ( revision.get( 'userhidden' ) ) === 'undefined' ) {
				contributorArray.push( revision.get( 'user' ) );
			}
		} );

		// count how many times each editor edited the article
		const contributorCounts = {};
		for ( let i = 0; i < contributorArray.length; i++ ) {
			const contributor = contributorArray[ i ];
			contributorCounts[ contributor ] = ( contributorCounts[ contributor ] || 0 ) + 1;
		}

		// construct the info for the article creator
		let link = mw.html.element( 'a', { href: this.model.get( 'creator_user_page_url' ) }, creator );

		let creatorContribCount = 1;
		if ( contributorArray.indexOf( creator ) !== -1 ) {
			creatorContribCount = contributorCounts[ creator ];
		}

		// create the WikiLove flyout content here.
		this.$tel.html( this.template( this.model.toJSON() ) );

		// set the Learn More link URL
		$( '#mwe-pt-wikilove .mwe-pt-flyout-help-link' ).attr( 'href', this.moduleConfig.helplink );

		if ( mw.user.getName() !== creator && !creatorHidden ) {
			// add the creator info to the top of the list
			$( '#mwe-pt-article-contributor-list' ).append(
				'<input type="checkbox" class="mwe-pt-recipient-checkbox" value="' + _.escape( creator ) + '"/>' +
				link + ' <span class="mwe-pt-info-text">– ' +
				mw.message( 'pagetriage-wikilove-edit-count', creatorContribCount ).escaped() +
				mw.message( 'comma-separator' ).escaped() + mw.message( 'pagetriage-wikilove-page-creator' ).escaped() + '</span><br/>'
			);
		}

		let x = 0;
		// sort contributors by their number of edits
		this.bySortedValue( contributorCounts, ( name, count ) => {
			// include up to 9 additional editors (this corresponds to the limit in WikiLove)
			if ( name !== creator && name !== mw.user.getName() && x < 9 ) {
				try {
					const userTitle = new mw.Title( name, mw.config.get( 'wgNamespaceIds' ).user );
					const linkUrl = userTitle.getUrl();
					link = mw.html.element( 'a', { href: linkUrl }, name );
				} catch ( e ) {
					link = _.escape( name );
				}

				$( '#mwe-pt-article-contributor-list' ).append(
					'<input type="checkbox" class="mwe-pt-recipient-checkbox" value="' + _.escape( name ) + '"/>' +
					link + ' <span class="mwe-pt-info-text">– ' +
					mw.message( 'pagetriage-wikilove-edit-count', count ).escaped() +
					'</span><br/>'
				);
				x++;
			}
		} );

		// If there are no possible recipients, display an error message
		if ( $( '#mwe-pt-article-contributor-list' ).text() === '' ) {
			$( '#mwe-pt-article-contributor-list' ).css( 'font-style', 'italic' );
			$( '#mwe-pt-article-contributor-list' ).append( mw.message( 'pagetriage-wikilove-no-recipients' ).escaped() );
		}

		$( '.mwe-pt-recipient-checkbox' ).on( 'click', () => {
			if ( $( '.mwe-pt-recipient-checkbox:checked' ).length > 0 ) {
				$( '#mwe-pt-wikilove-button' ).button( 'enable' );
			} else {
				$( '#mwe-pt-wikilove-button' ).button( 'disable' );
			}
		} );

		// initialize the button
		const that = this;
		$( '#mwe-pt-wikilove-button' )
			.button( { icons: { secondary: 'ui-icon-triangle-1-e' } } )
			.on( 'click', ( e ) => {
				e.preventDefault();
				const recipients = $( '.mwe-pt-recipient-checkbox:checked' ).map( ( i, el ) => el.value ).get();
				$.wikiLove.openDialog( recipients, [ 'pagetriage' ] );
				that.hide();
			} );

		// Disable the submit button to start with, will be re-enabled once a checkbox is selected
		$( '#mwe-pt-wikilove-button' ).button( 'disable' );

	}

} );

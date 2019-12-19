// view for sending WikiLove to the article contributors

var ToolView = require( './ToolView.js' );
module.exports = ToolView.extend( {
	id: 'mwe-pt-wikilove',
	icon: 'icon_wikilove.png', // the default icon
	title: mw.msg( 'wikilove' ),
	tooltip: 'pagetriage-wikilove-tooltip',
	template: mw.template.get( 'ext.pageTriage.views.toolbar', 'wikilove.underscore' ),

	bySortedValue: function ( obj, callback, context ) {
		var tuples = [];
		for ( var key in obj ) {
			tuples.push( [ key, obj[ key ] ] );
		}
		tuples.sort( function ( a, b ) {
			return a[ 1 ] - b[ 1 ];
		} );

		var length = tuples.length;
		while ( length-- ) {
			callback.call( context, tuples[ length ][ 0 ], tuples[ length ][ 1 ] );
		}
	},

	render: function () {
		// get the article's creator
		var creator = this.model.get( 'user_name' );

		// get the last 20 editors of the article
		var contributorArray = [];
		this.model.revisions.each( function ( revision ) {
			contributorArray.push( revision.get( 'user' ) );
		} );

		// count how many times each editor edited the article
		var contributorCounts = {};
		for ( var i = 0; i < contributorArray.length; i++ ) {
			var contributor = contributorArray[ i ];
			contributorCounts[ contributor ] = ( contributorCounts[ contributor ] || 0 ) + 1;
		}

		// construct the info for the article creator
		var link = mw.html.element( 'a', { href: this.model.get( 'creator_user_page_url' ) }, creator );

		var creatorContribCount = 1;
		if ( contributorArray.indexOf( creator ) !== -1 ) {
			creatorContribCount = contributorCounts[ creator ];
		}

		// create the WikiLove flyout content here.
		this.$tel.html( this.template( this.model.toJSON() ) );

		// set the Learn More link URL
		$( '#mwe-pt-wikilove .mwe-pt-flyout-help-link' ).attr( 'href', this.moduleConfig.helplink );

		if ( mw.user.getName() !== creator ) {
			// add the creator info to the top of the list
			$( '#mwe-pt-article-contributor-list' ).append(
				'<input type="checkbox" class="mwe-pt-recipient-checkbox" value="' + _.escape( creator ) + '"/>' +
				link + ' <span class="mwe-pt-info-text">– ' +
				mw.message( 'pagetriage-wikilove-edit-count', creatorContribCount ).escaped() +
				mw.message( 'comma-separator' ).escaped() + mw.message( 'pagetriage-wikilove-page-creator' ).escaped() + '</span><br/>'
			);
		}

		var x = 0;
		// sort contributors by their number of edits
		this.bySortedValue( contributorCounts, function ( name, count ) {
			// include up to 9 additional editors (this corresponds to the limit in WikiLove)
			if ( name !== creator && name !== mw.user.getName() && x < 9 ) {
				try {
					var userTitle = new mw.Title( name, mw.config.get( 'wgNamespaceIds' ).user );
					var linkUrl = userTitle.getUrl();
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

		$( '.mwe-pt-recipient-checkbox' ).on( 'click', function () {
			if ( $( '.mwe-pt-recipient-checkbox:checked' ).length > 0 ) {
				$( '#mwe-pt-wikilove-button' ).button( 'enable' );
			} else {
				$( '#mwe-pt-wikilove-button' ).button( 'disable' );
			}
		} );

		// initialize the button
		var that = this;
		$( '#mwe-pt-wikilove-button' )
			.button( { icons: { secondary: 'ui-icon-triangle-1-e' } } )
			.on( 'click', function ( e ) {
				e.preventDefault();
				var recipients = $( 'input:checkbox:checked.mwe-pt-recipient-checkbox' ).map( function () {
					return this.value;
				} ).get();
				$.wikiLove.openDialog( recipients );
				that.hide();
			} );

		// Disable the submit button to start with, will be re-enabled once a checkbox is selected
		$( '#mwe-pt-wikilove-button' ).button( 'disable' );

	}

} );

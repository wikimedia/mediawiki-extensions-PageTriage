// view for sending WikiLove to the article contributors

$( function () {
	mw.pageTriage.WikiLoveView = mw.pageTriage.ToolView.extend( {
		id: 'mwe-pt-wikilove',
		icon: 'icon_wikilove.png', // the default icon
		title: mw.msg( 'wikilove' ),
		tooltip: 'pagetriage-wikilove-tooltip',
		template: mw.template.get( 'ext.pageTriage.views.toolbar', 'wikilove.underscore' ),

		bySortedValue: function ( obj, callback, context ) {
			var key, length,
				tuples = [];
			for ( key in obj ) {
				tuples.push( [ key, obj[ key ] ] );
			}
			tuples.sort( function ( a, b ) { return a[ 1 ] - b[ 1 ]; } );

			length = tuples.length;
			while ( length-- ) {
				callback.call( context, tuples[ length ][ 0 ], tuples[ length ][ 1 ] );
			}
		},

		render: function () {
			var userTitle, linkUrl, link, creator, i, contributor, modules, x,
				that = this,
				contributorArray = [],
				contributorCounts = {},
				creatorContribCount = 1,
				recipients = [];

			// get the article's creator
			creator = this.model.get( 'user_name' );

			// get the last 20 editors of the article
			this.model.revisions.each( function ( revision ) {
				contributorArray.push( revision.get( 'user' ) );
			} );

			// count how many times each editor edited the article
			for ( i = 0; i < contributorArray.length; i++ ) {
				contributor = contributorArray[ i ];
				contributorCounts[ contributor ] = ( contributorCounts[ contributor ] || 0 ) + 1;
			}

			// construct the info for the article creator
			link = mw.html.element( 'a', { href: this.model.get( 'creator_user_page_url' ) }, creator );

			if ( $.inArray( creator, contributorArray ) > -1 ) {
				creatorContribCount = contributorCounts[ creator ];
			}

			// create the WikiLove flyout content here.
			this.$tel.html( this.template( this.model.toJSON() ) );

			// set the Learn More link URL
			modules = mw.config.get( 'wgPageTriageCurationModules' );
			$( '#mwe-pt-wikilove .mwe-pt-flyout-help-link' ).attr( 'href', modules.wikiLove.helplink );

			if ( mw.user.getName() !== creator ) {
				// add the creator info to the top of the list
				$( '#mwe-pt-article-contributor-list' ).append(
					'<input type="checkbox" class="mwe-pt-recipient-checkbox" value="' + _.escape( creator ) + '"/>' +
					link + ' <span class="mwe-pt-info-text">– ' +
					mw.msg( 'pagetriage-wikilove-edit-count', creatorContribCount ) +
					mw.msg( 'comma-separator' ) + mw.msg( 'pagetriage-wikilove-page-creator' ) + '</span><br/>'
				);
			}

			x = 0;
			// sort contributors by their number of edits
			this.bySortedValue( contributorCounts, function ( name, count ) {
				// include up to 9 additional editors (this corresponds to the limit in WikiLove)
				if ( name !== creator && name !== mw.user.getName() && x < 9 ) {
					try {
						userTitle = new mw.Title( name, mw.config.get( 'wgNamespaceIds' ).user );
						linkUrl = userTitle.getUrl();
						link = mw.html.element( 'a', { href: linkUrl }, name );
					} catch ( e ) {
						link = _.escape( name );
					}

					$( '#mwe-pt-article-contributor-list' ).append(
						'<input type="checkbox" class="mwe-pt-recipient-checkbox" value="' + _.escape( name ) + '"/>' +
						link + ' <span class="mwe-pt-info-text">– ' +
						mw.msg( 'pagetriage-wikilove-edit-count', count ) +
						'</span><br/>'
					);
					x++;
				}
			} );

			// If there are no possible recipients, display an error message
			if ( $( '#mwe-pt-article-contributor-list' ).text() === '' ) {
				$( '#mwe-pt-article-contributor-list' ).css( 'font-style', 'italic' );
				$( '#mwe-pt-article-contributor-list' ).append( mw.msg( 'pagetriage-wikilove-no-recipients' ) );
			}

			// initialize the button
			$( '#mwe-pt-wikilove-button' )
				.button( { icons: { secondary: 'ui-icon-triangle-1-e' } } )
				.click( function ( e ) {
					e.preventDefault();
					recipients = $( 'input:checkbox:checked.mwe-pt-recipient-checkbox' ).map( function () {
						return this.value;
					} ).get();
					$.wikiLove.openDialog( recipients );
					that.hide();
				} );

		}

	} );

} );

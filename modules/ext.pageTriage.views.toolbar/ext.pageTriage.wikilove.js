// view for sending WikiLove to the article contributors

$( function() {
	mw.pageTriage.WikiLoveView = mw.pageTriage.ToolView.extend( {
		id: 'mwe-pt-wikilove',
		icon: 'icon_wikilove.png', // the default icon
		title: gM( 'wikilove' ),
		template: mw.pageTriage.viewUtil.template( { 'view': 'toolbar', 'template': 'wikilove.html' } ),

		bySortedValue: function( obj, callback, context ) {
			var tuples = [];
			for ( var key in obj ) {
				tuples.push( [key, obj[key]] )
			};
			tuples.sort( function( a, b ) { return a[1] - b[1] } );
			var length = tuples.length;
			while ( length-- ) {
				callback.call( context, tuples[length][0], tuples[length][1] )
			};
		},

		render: function() {
			var _this = this;
			var contributorArray = [];
			var contributorCounts = {};
			var creatorContribCount = 1;
			var userTitle, linkUrl, link;
			var recipients = [];

			// get the article's creator
			var creator = this.model.get( 'user_name' );

			// get the last 20 editors of the article
			this.model.revisions.each( function ( revision ) {
				contributorArray.push( revision.get( 'user' ) );
			} );

			// count how many times each editor edited the article
			for( var i = 0; i < contributorArray.length; i++ ) {
				var contributor = contributorArray[i];
				contributorCounts[contributor] = contributorCounts[contributor] ? contributorCounts[contributor] + 1 : 1;
			}

			// construct the info for the article creator
			userTitle = new mw.Title( creator, mw.config.get('wgNamespaceIds')['user'] );
			linkUrl = userTitle.getUrl();
			link = mw.html.element( 'a', { 'href': linkUrl }, creator );

			if ( $.inArray( creator, contributorArray ) > -1 ) {
				creatorContribCount = contributorCounts[creator];
			}

			// create the WikiLove flyout content here.
			this.$tel.html( this.template( this.model.toJSON() ) );

			if ( mw.user.name() !== creator ) {
				// add the creator info to the top of the list
				$( '#mwe-pt-article-contributor-list' ).append(
					'<input type="checkbox" class="mwe-pt-recipient-checkbox" value="' + _.escape( creator ) + '"/>' +
					link + ' <span class="mwe-pt-info-text">– ' +
					mw.msg( 'pagetriage-wikilove-edit-count', creatorContribCount ) +
					mw.msg( 'comma-separator' ) + mw.msg( 'pagetriage-wikilove-page-creator' ) + '</span><br/>'
				);
			}

			var x = 0;
			// sort contributors by their number of edits
			this.bySortedValue( contributorCounts, function( name, count ) {
				// include up to 9 additional editors (this corresponds to the limit in WikiLove)
				if ( name !== creator && name !== mw.user.name() && x < 9 ) {
					userTitle = new mw.Title( name, mw.config.get('wgNamespaceIds')['user'] );
					linkUrl = userTitle.getUrl();
					link = mw.html.element( 'a', { 'href': linkUrl }, name );
					$( '#mwe-pt-article-contributor-list' ).append(
						'<input type="checkbox" class="mwe-pt-recipient-checkbox" value="' +  _.escape( name ) + '"/>' +
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
				.button( { icons: { secondary:'ui-icon-triangle-1-e' } } )
				.click( function( e ) {
					e.preventDefault();
					recipients = $( 'input:checkbox:checked.mwe-pt-recipient-checkbox' ).map( function () {
						return this.value;
					} ).get();
					$.wikiLove.openDialog( recipients );
					_this.hide();
				} );

		}

	} );

} );

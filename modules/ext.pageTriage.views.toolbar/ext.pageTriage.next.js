// Move to the next page

$( function() {
	mw.pageTriage.NextView = mw.pageTriage.ToolView.extend( {
		id: 'mwe-pt-next',
		icon: 'icon_skip.png', // the default icon
		title: 'Next',
		
		initialize: function( options ) {
			this.eventBus = options.eventBus;
			
			var lastSearch = JSON.parse( $.cookie( 'NewPageFeedLastSearch' ) );
			if( lastSearch instanceof Array ) {
				var position = lastSearch.indexOf( String( mw.config.get( 'wgArticleId' ) ) );
				if( position > -1 && position < lastSearch.length ) {
					// this article is in the list, and it's not at the end.
					this.nextId = lastSearch[position + 1];
				}
			}
			
			if( ! this.nextId ) {
				this.disabledIcon = true;
			}
		},
		
		click: function() {
			var page, _this = this;
			
			// find the next page.
			this.eventBus.trigger( 'showTool', this );

			apiRequest = {
				'action': 'pagetriagelist',
				'page_id': this.nextId,
				'format': 'json'
			};
			
			$.ajax( {
				type: 'post',
				url: mw.util.wikiScript( 'api' ),
				data: apiRequest,
				dataType: 'json',
				async: false,
				success: function( result ) {
					if ( result.pagetriagelist !== undefined && result.pagetriagelist.result === 'success' ) {
						page = result.pagetriagelist.pages[0];
					}
				},
				error: function( xhr ) {
					_this.disable();
				}
			} );

			if( page.title ) {
				var url = mw.config.get('wgArticlePath').replace(
					'$1', mw.util.wikiUrlencode( page.title )
				);
				if( page.is_redirect == '1' ) {
					var mark = ( url.indexOf( '?' ) === -1 ) ? '?' : '&';
					url += mark + "redirect=no";
				}
				window.location.href = url;
			} else {
				this.disable();
			}
		}

	} );

} );

// view for marking a page as reviewed or unreviewed

$( function() {
	mw.pageTriage.MarkView = mw.pageTriage.ToolView.extend( {
		id: 'mwe-pt-mark',
		icon: 'icon_mark_reviewed.png', // the default icon
		title: 'Mark as Reviewed',
		template: mw.pageTriage.viewUtil.template( { 'view': 'toolbar', 'template': 'mark.html' } ),

		submit: function( action ) {
			var _this = this;
			apiRequest = {
				'action': 'pagetriageaction',
				'pageid': mw.config.get( 'wgArticleId' ),
				'reviewed': ( action === 'reviewed' ) ? '1' : '0',
				'token': mw.user.tokens.get('editToken'),
				'format': 'json'
			};
			return $.ajax( {
				type: 'post',
				url: mw.util.wikiScript( 'api' ),
				data: apiRequest,
				success: function( data ) {
					if ( data.error ) {
						// TODO: update for both review and unreview actions
						alert( mw.msg( 'pagetriage-mark-as-reviewed-error' ) );
					} else {
						_this.hide();
					}
				},
				dataType: 'json'
			} );
		},

		render: function() {
			// create the mark as reviewed flyout content here.
			this.$tel.html( this.template( { 'iconPath':this.iconPath( 'active' ), 'title':this.title } ) );
			
			// initialize the buttons
			$( '#mwe-pt-mark-as-reviewed-button' )
				.button( { icons: {secondary:'ui-icon-triangle-1-e'} } )
				.click( function( e ) {
					_this.submit( 'reviewed' );
					e.stopPropagation();
				} );
			$( '#mwe-pt-mark-as-reviewed-button' ).addClass( 'ui-button-green' );
			/*
			$( '#mwe-pt-mark-as-unreviewed-button' ).button().click( function( e ) {
				_this.submit( 'unreviewed' );
				e.stopPropagation();
			} );
			*/
		}

	} );

} );

// view for marking a page as reviewed or unreviewed

$( function() {
	mw.pageTriage.MarkView = mw.pageTriage.ToolView.extend( {
		id: 'mwe-pt-mark',
		icon: 'icon_mark_reviewed.png', // the default icon
		title: gM( 'pagetriage-mark-as-reviewed' ),
		tooltip: 'pagetriage-mark-tooltip',
		template: mw.pageTriage.viewUtil.template( { 'view': 'toolbar', 'template': 'mark.html' } ),
		noteChanged: false,

		initialize: function( options ) {
			this.eventBus = options.eventBus;
			this.model.on( 'change', this.setIcon, this );
		},

		// overwrite parent function
		setIcon: function( dir ) {
			if ( typeof( dir ) !== 'string' )  {
				dir = 'normal';
			}
			if ( dir === 'normal' && this.model.get( 'patrol_status' ) > 0 ) {
				dir = 'special';
			}
			this.$icon.attr('src', this.iconPath( dir ) );
		},

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
				cache: false,
				success: function( data ) {
					if ( typeof data.pagetriageaction !== 'undefined' && data.pagetriageaction.result === 'success' ) {
						var note = $.trim( $( '#mwe-pt-review-note-input' ).val() );
						if ( _this.noteChanged && note.length ) {
							_this.talkPageNote( note, action );
						} else {
							// update the article model, since it's now changed.
							_this.model.fetch();
							_this.hide();
						}
					} else {
						_this.showMarkError( action );
					}
				},
				error: function() {
					_this.showMarkError( action );
				},
				dataType: 'json'
			} );
		},

		talkPageNote: function( note, action ) {
			var _this = this, title = new mw.Title( this.model.get( 'user_name' ), mw.config.get( 'wgNamespaceIds' )['user_talk'] ),
			curPage = new mw.Title(  mw.config.get( 'wgPageName' ), mw.config.get( 'wgNamespaceNumber' ) );

			note = '{{subst:' + mw.config.get( 'wgTalkPageNoteTemplate' )['Mark']
				+ '|' + curPage.getPrefixedText()
				+ '|' + mw.config.get( 'wgUserName' )
				+ '|' + note + '}}';

			$.ajax( {
				type: 'post',
				url: mw.util.wikiScript( 'api' ),
				data: {
					action: 'edit',
					title: title.getPrefixedText(),
					appendtext: "\n" + note,
					token: mw.user.tokens.get( 'editToken' ),
					format: 'json'
				},
				success: function( data ) {
					if ( data.edit && data.edit.result === 'Success' ) {
						// update the article model, since it's now changed.
						_this.model.fetch();
						_this.hide();
					} else {
						_this.showMarkError( action );
					}
				},
				dataType: 'json'
			} );
		},

		showMarkError: function( action ) {
			if ( action === 'reviewed' ) {
				alert( mw.msg( 'pagetriage-mark-as-reviewed-error' ) );
			} else {
				alert( mw.msg( 'pagetriage-mark-as-unreviewed-error' ) );
			}
		},

		render: function() {
			var _this = this, status = this.model.get( 'patrol_status' ) == "0" ? 'reviewed' : 'unreviewed', maxLength = 250;

			// create the mark as reviewed flyout content here.
			this.$tel.html( this.template( $.extend( this.model.toJSON(), { 'status': status, 'maxLength': maxLength, 'creator': this.model.get( 'user_name' ) } ) ) );

			// override the flyout title based on the current reviewed state of the page
			$( '#mwe-pt-mark .mwe-pt-tool-title' ).text( mw.msg( 'pagetriage-mark-as-' + status ) );

			$( '#mwe-pt-review-note-input' ).keyup( function() {
				var length = $.trim( $('#mwe-pt-review-note-input').val() ).length;
				var buttonId = 'mwe-pt-mark-as-' + status + '-button';
				var charLeft = maxLength - length;

				$( '#mwe-pt-review-note-char-count' ).text( mw.msg( 'pagetriage-characters-left', charLeft ) );

				if ( charLeft <= 0 ) {
					$( '#' + buttonId ).button( 'disable' );
				} else {
					$( '#' + buttonId ).button( 'enable' );
				}
			} ).live( 'focus', function(e) {
				$( this ).val( '' );
				$( this ).css( 'color', 'black' );
				$( this ).unbind( e );
			} ).change( function() {
				_this.noteChanged = true;
			} );

			// set the Learn More link URL
			var modules = mw.config.get( 'wgPageTriageCurationModules' );
			$( '#mwe-pt-mark .mwe-pt-flyout-help-link' ).attr( 'href', modules.mark );

			// initialize the buttons
			$( '#mwe-pt-mark-as-reviewed-button' )
				.button( { icons: { secondary:'ui-icon-triangle-1-e' } } )
				.click( function( e ) {
					_this.submit( 'reviewed' );
					e.stopPropagation();
				} );

			$( '#mwe-pt-mark-as-unreviewed-button' )
				.button( { icons: { secondary:'ui-icon-triangle-1-e' } } )
				.click( function( e ) {
					_this.submit( 'unreviewed' );
					e.stopPropagation();
				} );

			// bind down here so it doesn't happen before the first render
			this.model.unbind( 'change:patrol_status', function() { _this.render(); } );
			this.model.bind( 'change:patrol_status', function() { _this.render(); } );
		}

	} );

} );

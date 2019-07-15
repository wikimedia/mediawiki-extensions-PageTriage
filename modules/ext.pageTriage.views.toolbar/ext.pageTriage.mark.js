// view for marking a page as reviewed or unreviewed

$( function () {
	mw.pageTriage.MarkView = mw.pageTriage.ToolView.extend( {
		id: 'mwe-pt-mark',
		icon: 'icon_mark_reviewed.png', // the default icon
		title: mw.msg( 'pagetriage-mark-as-reviewed' ),
		tooltip: '',
		template: mw.template.get( 'ext.pageTriage.views.toolbar', 'mark.underscore' ),

		initialize: function ( options ) {
			this.eventBus = options.eventBus;
			this.model.on( 'change', this.setIcon, this );
			this.model.on( 'change', this.changeTooltip, this );
		},

		changeTooltip: function () {
			if ( this.model.get( 'patrol_status' ) > 0 ) {
				this.tooltip = 'pagetriage-markunpatrolled';
			} else {
				this.tooltip = 'pagetriage-markpatrolled';
			}
			if ( this.$icon ) {
				this.$icon.attr( 'title', mw.msg( this.tooltip ) );
			}
		},

		// overwrite parent function
		setIcon: function ( dir ) {
			if ( typeof ( dir ) !== 'string' ) {
				dir = 'normal';
			}
			if ( dir === 'normal' && this.model.get( 'patrol_status' ) > 0 ) {
				dir = 'special';
			}
			this.$icon.attr( 'src', this.iconPath( dir ) );
		},

		submit: function ( action ) {
			var that = this,
				note = '';

			new mw.Api().postWithToken( 'csrf', {
				action: 'pagetriageaction',
				pageid: mw.config.get( 'wgArticleId' ),
				reviewed: ( action === 'reviewed' ) ? '1' : '0'
			} )
				.done( function () {
					that.talkPageNote( note, action );
				} )
				.fail( function ( errorCode, data ) {
					that.showMarkError( action, data.error.info || mw.msg( 'unknown-error' ) );
				} );
		},

		submitNote: function () {
			var that = this,
				action = 'noteSent',
				note = $( '#mwe-pt-review-note-input' ).val().trim();

			if ( !note.length ) {
				return;
			}
			that.talkPageNote( note, action );
		},

		talkPageNote: function ( note, action ) {
			var talkPageTitle,
				messagePosterPromise,
				topicTitle,
				that = this,
				pageTitle = mw.config.get( 'wgPageTriagePagePrefixedText' );

			// mark as unreviewed
			if ( action === 'unreviewed' ) {

				// only send note if there was a reviewer and it's not the current user
				if (
					this.model.get( 'ptrp_last_reviewed_by' ) > 0 &&
					mw.config.get( 'wgUserName' ) !== this.model.get( 'reviewer' )
				) {
					talkPageTitle = this.model.get( 'reviewer_user_talk_page' );
					messagePosterPromise = mw.messagePoster.factory.create(
						new mw.Title( talkPageTitle )
					);

					topicTitle = mw.pageTriage.contentLanguageMessage(
						'pagetriage-mark-unmark-talk-page-notify-topic-title'
					).text();

					note = '{{subst:' + mw.config.get( 'wgTalkPageNoteTemplate' ).UnMark.nonote +
						'|1=' + mw.config.get( 'wgUserName' ) +
						'|2=' + pageTitle +
						'}}';
				} else {
					that.hideFlyout( action );
					return;
				}
			// note was sent to creator
			} else if ( action === 'noteSent' || action === 'reviewed' ) {
				// there is no note, should not write anything in user talk page
				if ( !note ) {
					that.hideFlyout( action );
					return;
				}

				talkPageTitle = this.model.get( 'creator_user_talk_page' );
				messagePosterPromise = mw.messagePoster.factory.create(
					new mw.Title( talkPageTitle )
				);

				topicTitle = mw.pageTriage.contentLanguageMessage(
					'pagetriage-note-sent-talk-page-notify-topic-title',
					this.model.get( 'user_name' )
				).text();

				note = '{{subst:' + mw.config.get( 'wgTalkPageNoteTemplate' ).SendNote +
					'|1=' + pageTitle +
					'|2=' + mw.config.get( 'wgUserName' ) +
					'|3=' + note + '}}';
			}

			messagePosterPromise.then( function ( messagePoster ) {
				return messagePoster.post( topicTitle, note, { tags: 'pagetriage' } );
			} ).then( function () {
				that.hideFlyout( action );
			}, function ( errorCode, error ) {
				if ( error !== undefined ) {
					that.showMarkError( action, error );
				} else {
					that.showMarkError( action, mw.msg( 'unknown-error' ) );
				}
			} );
		},

		hideFlyout: function ( action ) {
			$.removeSpinner( 'mark-spinner' );
			$( '#mwe-pt-mark-as-' + action + '-button' ).button( 'enable' );
			$( '#mwe-pt-review-note-input' ).val( '' );
			$( '#mwe-pt-send-message-button' ).button( 'disable' );
			this.model.fetch();
			this.hide();
		},

		/**
		 * Handle an error occurring after submit
		 *
		 * @param {string} action Whether the action was reviewing or unreviewing
		 * @param {string} errorMsg The specific error that occurred
		 */
		showMarkError: function ( action, errorMsg ) {
			// Give grep a chance to find the usages:
			// pagetriage-mark-as-reviewed-error, pagetriage-mark-as-unreviewed-error
			// eslint-disable-next-line no-alert
			alert( mw.msg( 'pagetriage-mark-as-' + action + '-error', errorMsg ) );
			$.removeSpinner( 'mark-spinner' );
			$( '#mwe-pt-mark-as-' + action + '-button' ).button( 'enable' );
		},

		render: function () {
			var that = this,
				status = this.model.get( 'patrol_status' ) === '0' ? 'reviewed' : 'unreviewed',
				modules = mw.config.get( 'wgPageTriageCurationModules' ),
				noteTarget = this.model.get( 'user_name' ),
				note = '';

			function handleFocus() {
				$( this ).css( 'color', 'black' );
				$( this ).off( 'focus', handleFocus );
			}

			this.changeTooltip();
			// create the mark as reviewed flyout content here.
			this.$tel.html( this.template( $.extend(
				this.model.toJSON(),
				{
					status: status,
					noteTarget: noteTarget
				}
			) ) );

			// override the flyout title based on the current reviewed state of the page
			// Give grep a chance to find the usages:
			// pagetriage-mark-as-reviewed-error, pagetriage-mark-as-unreviewed-error
			$( '#mwe-pt-mark .mwe-pt-tool-title' ).text( mw.msg( 'pagetriage-mark-as-' + status ) );

			// check if note is enabled for this namespace
			if ( modules.mark.note.indexOf( mw.config.get( 'wgNamespaceNumber' ) ) !== -1 ) {
				$( '#mwe-pt-review-note' ).show();
				$( '#mwe-pt-review-note-input' ).on( 'focus', handleFocus ).on( 'input', function () {
					note = $( '#mwe-pt-review-note-input' ).val().trim();
					if ( note.length ) {
						$( '#mwe-pt-send-message-button' ).button( 'enable' );
					} else {
						$( '#mwe-pt-send-message-button' ).button( 'disable' );
					}
				} );
			}

			// set the Learn More link URL
			$( '#mwe-pt-mark .mwe-pt-flyout-help-link' ).attr( 'href', modules.mark.helplink );

			// initialize the buttons
			$( '#mwe-pt-mark-as-' + status + '-button' )
				.button( { icons: { secondary: 'ui-icon-triangle-1-e' } } )
				.on( 'click', function ( e ) {
					$( '#mwe-pt-mark-as-' + status + '-button' ).button( 'disable' );
					$( '#mwe-pt-mark-as-' + status ).append( $.createSpinner( 'mark-spinner' ) ); // show spinner
					that.submit( status );
					e.stopPropagation();
				} );

			$( '#mwe-pt-send-message-button' )
				.button( { disabled: true, icons: { secondary: 'ui-icon-triangle-1-e' } } )
				.on( 'click', function ( e ) {
					$( '#mwe-pt-send-message-button' ).button( 'disable' );
					$( '#mwe-pt-send-message' ).append( $.createSpinner( 'mark-spinner' ) ); // show spinner
					that.submitNote();
					e.stopPropagation();
				} );

			// bind down here so it doesn't happen before the first render
			this.model.unbind( 'change:patrol_status', function () {
				that.render();
			} );
			this.model.bind( 'change:patrol_status', function () {
				that.render();
			} );
		}

	} );

} );

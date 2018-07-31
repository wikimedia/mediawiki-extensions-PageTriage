// view for marking a page as reviewed or unreviewed

$( function () {
	mw.pageTriage.MarkView = mw.pageTriage.ToolView.extend( {
		id: 'mwe-pt-mark',
		icon: 'icon_mark_reviewed.png', // the default icon
		title: mw.msg( 'pagetriage-mark-as-reviewed' ),
		tooltip: '',
		template: mw.template.get( 'ext.pageTriage.views.toolbar', 'mark.underscore' ),
		noteChanged: false,

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
			var apiRequest,
				that = this,
				note = $.trim( $( '#mwe-pt-review-note-input' ).val() );

			if ( !that.noteChanged || !note.length ) {
				note = '';
			}
			apiRequest = {
				action: 'pagetriageaction',
				pageid: mw.config.get( 'wgArticleId' ),
				reviewed: ( action === 'reviewed' ) ? '1' : '0',
				token: mw.user.tokens.get( 'editToken' ),
				format: 'json',
				note: note
			};
			return $.ajax( {
				type: 'post',
				url: mw.util.wikiScript( 'api' ),
				data: apiRequest,
				cache: false,
				success: function ( data ) {
					if (
						typeof data.pagetriageaction !== 'undefined' &&
						data.pagetriageaction.result === 'success'
					) {
						that.talkPageNote( note, action );
					} else {
						if ( typeof data.error.info !== 'undefined' ) {
							that.showMarkError( action, data.error.info );
						} else {
							that.showMarkError( action, mw.msg( 'unknown-error' ) );
						}
					}
				},
				error: function () {
					that.showMarkError( action, mw.msg( 'unknown-error' ) );
				},
				dataType: 'json'
			} );
		},

		talkPageNote: function ( note, action ) {
			var talkPageTitle,
				messagePosterPromise,
				topicTitle,
				that = this,
				pageTitle = mw.config.get( 'wgPageTriagePagePrefixedText' );

			// mark as unreviewed
			if ( action !== 'reviewed' ) {
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

					if ( note ) {
						note = '{{subst:' + mw.config.get( 'wgTalkPageNoteTemplate' ).UnMark.note +
							'|1=' + pageTitle +
							'|2=' + mw.config.get( 'wgUserName' ) +
							'|3=' + note + '}}';
					} else {
						note = '{{subst:' + mw.config.get( 'wgTalkPageNoteTemplate' ).UnMark.nonote +
							'|1=' + mw.config.get( 'wgUserName' ) +
							'|2=' + pageTitle +
							'}}';
					}
				} else {
					that.hideFlyout( action );
					return;
				}
			// mark as reviewed
			} else {
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
					'pagetriage-mark-mark-talk-page-notify-topic-title',
					pageTitle
				).text();

				note = '{{subst:' + mw.config.get( 'wgTalkPageNoteTemplate' ).Mark +
					'|1=' + pageTitle +
					'|2=' + mw.config.get( 'wgUserName' ) +
					'|3=' + note + '}}';
			}

			messagePosterPromise.then( function ( messagePoster ) {
				return messagePoster.post(
					topicTitle,
					note
				);
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
			this.model.fetch();
			this.hide();
		},

		/**
		 * Handle an error occuring after submit
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
				showNoteSection = true,
				noteTarget = '',
				noteTitle;

			function handleFocus() {
				$( this ).val( '' );
				$( this ).css( 'color', 'black' );
				$( this ).off( 'focus', handleFocus );
			}

			if ( status === 'unreviewed' ) {
				noteTitle = 'pagetriage-add-a-note-reviewer';
				// there is no reviewer recorded or the reviewer is reverting their previous reviewed status
				if (
					this.model.get( 'ptrp_last_reviewed_by' ) <= 0 ||
					mw.config.get( 'wgUserName' ) === this.model.get( 'reviewer' )
				) {
					showNoteSection = false;
				} else {
					noteTarget = this.model.get( 'reviewer' );
				}
			} else {
				noteTitle = 'pagetriage-add-a-note-creator';
				noteTarget = this.model.get( 'user_name' );
			}

			this.changeTooltip();
			// create the mark as reviewed flyout content here.
			this.$tel.html( this.template( $.extend(
				this.model.toJSON(),
				{
					status: status,
					noteTarget: noteTarget,
					noteTitle: noteTitle
				}
			) ) );

			// override the flyout title based on the current reviewed state of the page
			// Give grep a chance to find the usages:
			// pagetriage-mark-as-reviewed-error, pagetriage-mark-as-unreviewed-error
			$( '#mwe-pt-mark .mwe-pt-tool-title' ).text( mw.msg( 'pagetriage-mark-as-' + status ) );

			// check if note is enabled for this namespace and if the note section should be shown
			if ( $.inArray( mw.config.get( 'wgNamespaceNumber' ), modules.mark.note ) !== -1 && showNoteSection ) {
				$( '#mwe-pt-review-note' ).show();
				$( '#mwe-pt-review-note-input' ).on( 'focus', handleFocus ).change( function () {
					that.noteChanged = true;
				} );
			}

			// set the Learn More link URL
			$( '#mwe-pt-mark .mwe-pt-flyout-help-link' ).attr( 'href', modules.mark.helplink );

			// initialize the buttons
			$( '#mwe-pt-mark-as-' + status + '-button' )
				.button( { icons: { secondary: 'ui-icon-triangle-1-e' } } )
				.click( function ( e ) {
					$( '#mwe-pt-mark-as-' + status + '-button' ).button( 'disable' );
					$( '#mwe-pt-mark-as-' + status ).append( $.createSpinner( 'mark-spinner' ) ); // show spinner
					that.submit( status );
					e.stopPropagation();
				} );

			// bind down here so it doesn't happen before the first render
			this.model.unbind( 'change:patrol_status', function () { that.render(); } );
			this.model.bind( 'change:patrol_status', function () { that.render(); } );
		}

	} );

} );

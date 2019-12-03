// view for marking a page as reviewed or unreviewed

var ToolView = require( './ToolView.js' ),
	config = require( './config.json' );
module.exports = ToolView.extend( {
	id: 'mwe-pt-mark',
	icon: 'icon_mark_reviewed.png', // the default icon
	renderWasBound: false,
	title: mw.msg( 'pagetriage-mark-as-reviewed' ),
	tooltip: '',
	template: mw.template.get( 'ext.pageTriage.views.toolbar', 'mark.underscore' ),

	initialize: function ( options ) {
		this.eventBus = options.eventBus;
		this.moduleConfig = options.moduleConfig || {};
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
			note = '',
			reviewed = action === 'reviewed';

		new mw.Api().postWithToken( 'csrf', {
			action: 'pagetriageaction',
			pageid: mw.config.get( 'wgArticleId' ),
			reviewed: reviewed ? '1' : '0'
		} )
			.done( function () {
				// Data to be sent back to consumers of the actionQueue API.
				var actionData = that.getDataForActionQueue( {
					reviewed: reviewed,
					reviewer: mw.config.get( 'wgUserName' )
				} );

				if ( note ) {
					actionData.note = note;
				}

				// a note needs to be posted to article talk page when an article is marked as
				// unreviewed and it meets the use case set in talkPageNote
				if ( action === 'unreviewed' ) {
					that.talkPageNote( note, action, '' );
				} else {
					that.hideFlyout( action );
				}

				mw.pageTriage.actionQueue.run( 'mark', actionData );
			} )
			.fail( function ( errorCode, data ) {
				that.showMarkError( action, data.error.info || mw.msg( 'unknown-error' ) );
			} );
	},

	submitNote: function () {
		var that = this,
			action = 'noteSent',
			recipient = 'creator',
			note = $( '#mwe-pt-review-note-input' ).val().trim();

		if ( !note.length ) {
			return;
		}
		// Get selected recipient from available options if there is a previous reviewer
		if ( this.model.get( 'ptrp_last_reviewed_by' ) > 0 ) {
			recipient = $( '#mwe-pt-review-note-recipient' ).val().trim();
		}
		that.talkPageNote( note, action, recipient );
	},

	talkPageNote: function ( note, action, noteRecipient ) {
		var talkPageTitle,
			topicTitle,
			topicMessage,
			that = this,
			pageTitle = mw.config.get( 'wgPageName' ).replace( /_/g, ' ' ),
			sendNote1,
			sendNote2,
			sendNotePromise,
			sendNoteToArticleTalkPage = false;

		if ( action === 'unreviewed' ) {
			// only send note if there was a previous reviewer and it's not the current user
			if (
				this.model.get( 'ptrp_last_reviewed_by' ) > 0 &&
				mw.config.get( 'wgUserName' ) !== this.model.get( 'reviewer' )
			) {
				talkPageTitle = this.model.get( 'reviewer_user_talk_page' );
				topicTitle = mw.pageTriage.contentLanguageMessage(
					'pagetriage-mark-unmark-talk-page-notify-topic-title'
				).text();
				topicMessage = '{{subst:' + config.TalkPageNoteTemplate.UnMark.nonote +
					'|1=' + mw.config.get( 'wgUserName' ) +
					'|2=' + pageTitle +
					'}}';
			} else {
				that.hideFlyout( action );
				return;
			}
		} else {
			// there is no note, should not write anything in user talk page
			if ( !note ) {
				that.hideFlyout( action );
				return;
			}

			talkPageTitle = noteRecipient === 'reviewer' ?
				this.model.get( 'reviewer_user_talk_page' ) : this.model.get( 'creator_user_talk_page' );
			topicTitle = mw.pageTriage.contentLanguageMessage(
				noteRecipient === 'reviewer' ?
					'pagetriage-note-sent-talk-page-notify-topic-title-reviewer' :
					'pagetriage-note-sent-talk-page-notify-topic-title'
			).text();
			topicMessage = '{{subst:' + config.TalkPageNoteTemplate.SendNote +
				'|1=' + pageTitle +
				'|2=' + mw.config.get( 'wgUserName' ) +
				'|3=' + note + '}}';
			// Only post on article talk page if note is sent to creator
			sendNoteToArticleTalkPage = noteRecipient === 'creator';
		}

		sendNote1 = that.sendNote( talkPageTitle, topicTitle, topicMessage );

		// If the note needs to be posted to article talk page as well then we handle
		// both post note promises resolve/reject states through a single promise
		if ( sendNoteToArticleTalkPage ) {
			talkPageTitle = this.model.get( 'talk_page_title' );
			topicTitle = mw.pageTriage.contentLanguageMessage(
				'pagetriage-feedback-from-new-page-review-process-title'
			).text();
			topicMessage = mw.pageTriage.contentLanguageMessage(
				'pagetriage-feedback-from-new-page-review-process-message',
				note
			).text();
			sendNote2 = that.sendNote( talkPageTitle, topicTitle, topicMessage );
			sendNotePromise = $.when( sendNote1, sendNote2 );
		} else { // Do not post note to article talk page
			sendNotePromise = sendNote1;
		}

		sendNotePromise.then( function () {
			that.hideFlyout( action );
		}, function ( errorCode, error ) {
			if ( error !== undefined ) {
				that.showMarkError( action, error );
			} else {
				that.showMarkError( action, mw.msg( 'unknown-error' ) );
			}
		} );
	},

	sendNote: function ( talkPageTitle, topicTitle, note ) {
		var messagePosterPromise = mw.messagePoster.factory.create(
			new mw.Title( talkPageTitle )
		);

		return messagePosterPromise.then( function ( messagePoster ) {
			return messagePoster.post( topicTitle, note, { tags: 'pagetriage' } );
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
			note = '',
			hasPreviousReviewer = this.model.get( 'ptrp_last_reviewed_by' ) > 0,
			articleCreator = this.model.get( 'user_name' ),
			previousReviewer = hasPreviousReviewer ? this.model.get( 'reviewer' ) : '',
			noteTarget = hasPreviousReviewer ? previousReviewer : articleCreator,
			notePlaceholder = hasPreviousReviewer ? 'pagetriage-message-for-reviewer-placeholder' :
				'pagetriage-message-for-creator-default-note';

		this.changeTooltip();

		// create the mark as reviewed flyout content here.
		this.$tel.html( this.template( $.extend(
			this.model.toJSON(),
			{
				status: status,
				hasPreviousReviewer: hasPreviousReviewer,
				noteTarget: noteTarget,
				notePlaceholder: notePlaceholder,
				previousReviewer: previousReviewer,
				articleCreator: articleCreator
			}
		) ) );

		// override the flyout title based on the current reviewed state of the page
		// Give grep a chance to find the usages:
		// pagetriage-mark-as-reviewed-error, pagetriage-mark-as-unreviewed-error
		$( '#mwe-pt-mark .mwe-pt-tool-title' ).text( mw.msg( 'pagetriage-mark-as-' + status ) );

		// check if note is enabled for this namespace
		if ( this.moduleConfig.note.indexOf( mw.config.get( 'wgNamespaceNumber' ) ) !== -1 ) {
			$( '#mwe-pt-review-note' ).show();
			$( '#mwe-pt-review-note-input' ).on( 'input', function () {
				note = $( '#mwe-pt-review-note-input' ).val().trim();
				if ( note.length ) {
					$( '#mwe-pt-send-message-button' ).button( 'enable' );
				} else {
					$( '#mwe-pt-send-message-button' ).button( 'disable' );
				}
			} );

			if ( hasPreviousReviewer ) {
				$( '#mwe-pt-review-note-recipient' ).on( 'change', function () {
					if ( $( this ).val() === 'reviewer' ) {
						noteTarget = previousReviewer;
						notePlaceholder = 'pagetriage-message-for-reviewer-placeholder';
					} else {
						noteTarget = articleCreator;
						notePlaceholder = 'pagetriage-message-for-creator-default-note';
					}
					$( '#mwe-pt-review-note-input' ).attr( 'placeholder', mw.msg( notePlaceholder, noteTarget ) );
				} );
			}
		}

		// set the Learn More link URL
		$( '#mwe-pt-mark .mwe-pt-flyout-help-link' ).attr( 'href', this.moduleConfig.helplink );

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
		// Only bind this once
		if ( !this.renderWasBound ) {
			this.model.bind( 'change:patrol_status', function () {
				that.render();
			} );
			this.renderWasBound = true;
		}
	}

} );

// view for marking a page as reviewed or unreviewed
const { contentLanguageMessage } = require( 'ext.pageTriage.util' );

const ToolView = require( './ToolView.js' ),
	config = require( './config.json' );
module.exports = ToolView.extend( {
	id: 'mwe-pt-mark',
	icon: 'icon_mark_reviewed.png', // the default icon
	renderWasBound: false,
	title: mw.msg( 'pagetriage-mark-as-reviewed' ),
	tooltip: '',
	template: mw.template.get( 'ext.pageTriage.toolbar', 'mark.underscore' ),

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
			// The following messages are used here:
			// * pagetriage-markunpatrolled
			// * pagetriage-markpatrolled
			this.$icon.attr( 'title', mw.msg( this.tooltip ) );
		}
	},

	// overwrite parent function
	setIcon: function ( dir ) {
		if ( this.model.get( 'patrol_status' ) === '3' ) {
			this.icon = 'icon_mark_autopatrolled.png'; // autopatrolled icon
		}
		if ( typeof dir !== 'string' ) {
			dir = 'normal';
		}
		if ( dir === 'normal' && this.model.get( 'patrol_status' ) > 0 ) {
			dir = 'special';
		}
		this.$icon.attr( 'src', this.iconPath( dir ) );
	},

	submit: function ( action ) {
		const that = this,
			note = '',
			reviewed = action === 'reviewed';

		new mw.Api().postWithToken( 'csrf', {
			action: 'pagetriageaction',
			pageid: mw.config.get( 'wgArticleId' ),
			reviewed: reviewed ? '1' : '0'
		} )
			.then( () => {
				// Data to be sent back to consumers of the actionQueue API.
				const actionData = that.getDataForActionQueue( {
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
			.catch( ( _errorCode, data ) => {
				that.showMarkError( action, data.error.info || mw.msg( 'unknown-error' ) );
			} );
	},

	submitNote: function () {
		const that = this;
		const action = 'sendnote';
		let recipient = 'creator';
		const note = $( '#mwe-pt-review-note-input' ).val().trim();

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
		const that = this;
		const pageTitle = mw.config.get( 'wgPageName' ).replace( /_/g, ' ' );
		let talkPageTitle,
			topicTitle,
			topicMessage,
			sendNotePromise,
			sendNoteToArticleTalkPage = false;

		if ( action === 'unreviewed' ) {
			// only send note if there was a previous reviewer and it's not the current user
			if (
				this.model.get( 'ptrp_last_reviewed_by' ) > 0 && note &&
				mw.config.get( 'wgUserName' ) !== this.model.get( 'reviewer' )
			) {
				talkPageTitle = this.model.get( 'reviewer_user_talk_page' );
				topicTitle = contentLanguageMessage(
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
			topicTitle = contentLanguageMessage(
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

		const sendNote1 = that.sendNote( talkPageTitle, topicTitle, topicMessage );

		// If the note needs to be posted to article talk page as well then we handle
		// both post note promises resolve/reject states through a single promise
		if ( sendNoteToArticleTalkPage ) {
			talkPageTitle = this.model.get( 'talk_page_title' );
			topicTitle = contentLanguageMessage(
				'pagetriage-feedback-from-new-page-review-process-title'
			).text();
			topicMessage = contentLanguageMessage(
				'pagetriage-feedback-from-new-page-review-process-message',
				note
			).text();
			const sendNote2 = that.sendNote( talkPageTitle, topicTitle, topicMessage );
			sendNotePromise = $.when( sendNote1, sendNote2 );
		} else { // Do not post note to article talk page
			sendNotePromise = sendNote1;
		}

		sendNotePromise
			.then( () => {
				that.hideFlyout( action );
			} )
			.catch( ( _errorCode, error ) => {
				if ( error !== undefined ) {
					that.showMarkError( action, error );
				} else {
					that.showMarkError( action, mw.msg( 'unknown-error' ) );
				}
			} );
	},

	sendNote: function ( talkPageTitle, topicTitle, note ) {
		const messagePosterPromise = mw.messagePoster.factory.create(
			new mw.Title( talkPageTitle )
		);

		return messagePosterPromise.then( ( messagePoster ) => messagePoster.post( topicTitle, note, { tags: 'pagetriage' } ) );
	},

	hideFlyout: function ( action ) {
		$.removeSpinner( 'mark-spinner' );

		if ( action === 'sendnote' ) {
			$( '#mwe-pt-send-message-button' ).attr( 'disabled', true );
		} else {
			$( '#mwe-pt-mark-as-' + action + '-button' ).attr( 'disabled', false );
		}

		$( '#mwe-pt-review-note-input' ).val( '' );
		this.model.fetch();
		this.hide();
	},

	/**
	 * Handle an error occurring after submit
	 *
	 * @param {string} action One of 'reviewed', 'unreviewed' or 'sendnote'
	 * @param {string} errorMsg The specific error that occurred
	 */
	showMarkError: function ( action, errorMsg ) {
		if ( action === 'sendnote' ) {
			$( '#mwe-pt-send-message-button' ).attr( 'disabled', false );
		} else {
			action = 'mark-as-' + action;
			$( '#mwe-pt-' + action + '-button' ).attr( 'disabled', true );
		}

		// The following messages are used here:
		// * pagetriage-mark-as-reviewed-error
		// * pagetriage-mark-as-unreviewed-error
		// * pagetriage-sendnote-error
		// eslint-disable-next-line no-alert
		alert( mw.msg( 'pagetriage-' + action + '-error', errorMsg ) );
		$.removeSpinner( 'mark-spinner' );
	},

	render: function () {
		let note = '';
		const that = this,
			status = this.model.get( 'patrol_status' ) === '0' ? 'reviewed' : 'unreviewed',
			hasPreviousReviewer = this.model.get( 'ptrp_last_reviewed_by' ) > 0,
			articleCreator = this.model.get( 'user_name' ),
			articleCreatorHidden = this.model.get( 'creator_hidden' ),
			previousReviewer = hasPreviousReviewer ? this.model.get( 'reviewer' ) : '';
		let noteTarget = articleCreator,
			notePlaceholder = 'pagetriage-message-for-creator-default-note',
			numRecipients = 2,
			noteRecipientRole,
			noteMessage = 'pagetriage-add-a-note-for-options-label';

		this.changeTooltip();

		if ( !hasPreviousReviewer ||
			mw.config.get( 'wgUserName' ) === previousReviewer ) {
			numRecipients--;
			noteTarget = articleCreator;
			noteRecipientRole = 'creator';
			noteMessage = 'pagetriage-add-a-note-creator-required';
			notePlaceholder = 'pagetriage-message-for-creator-default-note';
		}

		if ( mw.config.get( 'wgUserName' ) === articleCreator || articleCreatorHidden ) {
			numRecipients--;
			noteTarget = previousReviewer;
			noteRecipientRole = 'reviewer';
			noteMessage = 'pagetriage-add-a-note-previous-reviewer';
			notePlaceholder = 'pagetriage-message-for-reviewer-placeholder';
		}

		// create the mark as reviewed flyout content here.
		this.$tel.html( this.template( Object.assign(
			this.model.toJSON(),
			{
				status: status,
				hasPreviousReviewer: hasPreviousReviewer,
				noteTarget: noteTarget,
				notePlaceholder: notePlaceholder,
				previousReviewer: previousReviewer,
				articleCreator: articleCreator,
				numRecipients: numRecipients,
				noteRecipientRole: noteRecipientRole,
				noteMessage: noteMessage
			}
		) ) );

		// override the flyout title based on the current reviewed state of the page
		// The following messages are used here:
		// * pagetriage-mark-as-reviewed-error
		// * pagetriage-mark-as-unreviewed-error
		$( '#mwe-pt-mark .mwe-pt-tool-title' ).text( mw.msg( 'pagetriage-mark-as-' + status ) );

		const noteIsEnabledForThisNamespace = this.moduleConfig.note.indexOf( mw.config.get( 'wgNamespaceNumber' ) ) !== -1;
		if ( noteIsEnabledForThisNamespace && numRecipients > 0 ) {
			$( '#mwe-pt-review-note' ).show();
			$( '#mwe-pt-review-note-input, #mwe-pt-review-note-recipient' ).on( 'input', () => {
				const recipient = $( '#mwe-pt-review-note-recipient' ).val();
				note = $( '#mwe-pt-review-note-input' ).val().trim();
				if ( note.length && recipient.length ) {
					$( '#mwe-pt-send-message-button' ).attr( 'disabled', false );
				} else {
					$( '#mwe-pt-send-message-button' ).attr( 'disabled', true );
				}
			} );

			if ( numRecipients > 1 ) {
				$( '#mwe-pt-review-note-recipient' ).on( 'change', function () {
					if ( $( this ).val() === 'reviewer' ) {
						noteTarget = previousReviewer;
						notePlaceholder = 'pagetriage-message-for-reviewer-placeholder';
					} else {
						noteTarget = articleCreator;
						notePlaceholder = 'pagetriage-message-for-creator-default-note';
					}
					// The following messages are used here:
					// * pagetriage-message-for-reviewer-placeholder
					// * pagetriage-message-for-creator-default-note
					$( '#mwe-pt-review-note-input' ).attr( 'placeholder', mw.msg( notePlaceholder, noteTarget ) );
				} );
			}
		}

		// set the Learn More link URL
		$( '#mwe-pt-mark .mwe-pt-flyout-help-link' ).attr( 'href', this.moduleConfig.helplink );

		// initialize the buttons
		$( '#mwe-pt-mark-as-' + status + '-button' )
			.on( 'click', ( e ) => {
				$( '#mwe-pt-mark-as-' + status + '-button' ).attr( 'disabled', true );
				$( '#mwe-pt-mark-as-' + status ).append( $.createSpinner( 'mark-spinner' ) ); // show spinner
				that.submit( status );
				e.stopPropagation();
			} );

		$( '#mwe-pt-send-message-button' ).attr( 'disabled', true )
			.on( 'click', ( e ) => {
				$( '#mwe-pt-send-message-button' ).attr( 'disabled', true );
				$( '#mwe-pt-send-message' ).append( $.createSpinner( 'mark-spinner' ) ); // show spinner
				that.submitNote();
				e.stopPropagation();
			} );

		// bind down here so it doesn't happen before the first render
		// Only bind this once
		if ( !this.renderWasBound ) {
			this.model.bind( 'change:patrol_status', () => {
				that.render();
			} );
			this.renderWasBound = true;
		}
	}

} );

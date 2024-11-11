// view for displaying all the article metadata

const ToolView = require( './ToolView.js' );
const config = require( './config.json' );

const ArticleInfoHistoryView = Backbone.View.extend( {
	id: 'mwe-pt-info-history',
	template: mw.template.get( 'ext.pageTriage.toolbar', 'articleInfoHistory.underscore' ),

	render: function () {
		const offset = parseInt( mw.user.options.get( 'timecorrection' ).split( '|' )[ 1 ] );
		const that = this;
		let lastDate = null;
		let addedRevisions = 0;

		this.model.each( ( historyItem ) => {
			// Display the 5 most recent revisions
			if ( addedRevisions >= 5 ) {
				return;
			}
			// I'd rather do this date parsing in the model, but the change event isn't properly
			// passed through to nested models, and switching to backbone-relational in order to
			// move these few lines of code seems silly.
			const parsedTimestamp = moment( historyItem.get( 'timestamp' ) ); // ISO date format

			historyItem.set(
				'timestamp_date',
				parsedTimestamp.utcOffset( offset ).format( mw.msg( 'pagetriage-info-timestamp-date-format' ) )
			);
			historyItem.set(
				'timestamp_time',
				parsedTimestamp.utcOffset( offset ).format( mw.msg( 'pagetriage-info-timestamp-time-format' ) )
			);
			historyItem.set( 'new_date', historyItem.get( 'timestamp_date' ) !== lastDate );
			lastDate = historyItem.get( 'timestamp_date' );

			// get a userlink.
			// can't set link color since no userpage status is returned by the history api
			if ( historyItem.get( 'user' ) ) {
				try {
					const userTitle = new mw.Title( historyItem.get( 'user' ), mw.config.get( 'wgNamespaceIds' ).user );
					historyItem.set( 'user_title_url', userTitle.getUrl() );
				} catch ( e ) {
					historyItem.set( 'user_title_url', '' );
				}
			}

			historyItem.set(
				'revision_url',
				mw.util.getUrl( mw.config.get( 'wgPageName' ), { oldid: historyItem.get( 'revid' ) } )
			);

			that.$el.append( that.template( historyItem.toJSON() ) );
			addedRevisions++;
		} );
		return this;
	}
} );

module.exports = ToolView.extend( {
	id: 'mwe-pt-info',
	icon: 'icon_info.png', // the default icon
	title: mw.msg( 'pagetriage-info-title' ),
	tooltip: 'pagetriage-info-tooltip',
	template: mw.template.get( 'ext.pageTriage.toolbar', 'articleInfo.underscore' ),
	renderWasBound: false,

	badgeCount: function () {
		this.enumerateProblems();
		return this.problemCount;
	},

	setBadge: function () {
		// Call parent view to set the problem-count badge (top right).
		ToolView.prototype.setBadge.call( this );
		// Add a second badge (bottom right) if there is talk page feedback for this article.
		const feedbackCount = this.model.get( 'talkpage_feedback_count' );
		if ( feedbackCount <= 0 ) {
			return;
		}
		let $talkpageFeedbackBadge = this.$el.find( '.mwe-pt-talkpage-feedback-badge' );
		if ( $talkpageFeedbackBadge.length === 0 ) {
			$talkpageFeedbackBadge = $( '<span>' ).addClass( 'mwe-pt-talkpage-feedback-badge' );
			this.$el.find( '.mwe-pt-tool-icon-container' ).append( $talkpageFeedbackBadge );
		}
		$talkpageFeedbackBadge.badge( feedbackCount, 'bottom', true );
		// Use the same message (without link) as is used on the flyout, for the badge's tooltip.
		const badgeTooltip = mw.msg( 'pagetriage-has-talkpage-feedback', feedbackCount, mw.msg( 'pagetriage-has-talkpage-feedback-link' ) );
		// Add OOUI classes to the badge element that was added in .badge(), in order to get the envelope icon.
		$talkpageFeedbackBadge.find( '.notification-badge' )
			.addClass( 'oo-ui-iconElement oo-ui-icon-message oo-ui-image-invert' )
			.attr( 'title', badgeTooltip );
	},

	render: function () {
		this.enumerateProblems();
		// set the history link
		const historyUrl = new mw.Uri( mw.util.getUrl( mw.config.get( 'wgPageName' ), { action: 'history' } ) );
		this.model.set(
			'history_link',
			historyUrl.toString()
		);

		// set the logs link
		const logUrl = new mw.Uri( mw.util.getUrl( 'Special:Log', { page: mw.config.get( 'wgPageName' ) } ) );
		this.model.set(
			'logs_link',
			logUrl.toString()
		);

		const offset = parseInt( mw.user.options.get( 'timecorrection' ).split( '|' )[ 1 ] );

		// creator information
		if ( this.model.get( 'user_name' ) ) {
			// show new editor message only if the user is not anonymous and not autoconfirmed
			let bylineMessage;
			if ( this.model.get( 'user_id' ) > '0' && this.model.get( 'user_autoconfirmed' ) === '0' ) {
				bylineMessage = 'pagetriage-articleinfo-byline-new-editor';
			} else {
				bylineMessage = 'pagetriage-articleinfo-byline';
			}

			// put it all together in the byline
			// The following messages are used here:
			// * pagetriage-articleinfo-byline-new-editor
			// * pagetriage-articleinfo-byline
			const articleByline = mw.message(
				bylineMessage,
				moment.utc(
					this.model.get( 'creation_date_utc' ),
					'YYYYMMDDHHmmss'
				).utcOffset( offset ).format(
					mw.msg( 'pagetriage-info-timestamp-date-format' )
				),
				this.model.buildLinkTag(
					this.model.get( 'creator_user_page_url' ),
					this.model.get( 'user_name' ),
					this.model.get( 'creator_user_page_exist' )
				),
				this.model.buildLinkTag(
					this.model.get( 'creator_user_talk_page_url' ),
					mw.msg( 'sp-contributions-talk' ),
					this.model.get( 'creator_user_talk_page_exist' )
				),
				mw.msg( 'pipe-separator' ),
				this.model.buildLinkTag(
					this.model.get( 'creator_contribution_page_url' ),
					mw.msg( 'contribslink' ),
					true
				)
			).parse();
			this.model.set( 'articleByline_html', articleByline );
		} else if ( this.model.get( 'creator_hidden' ) ) {
			this.model.set( 'articleByline_html', mw.msg( 'pagetriage-articleinfo-byline-hidden-username', moment.utc(
				this.model.get( 'creation_date_utc' ),
				'YYYYMMDDHHmmss'
			).utcOffset( offset ).format(
				mw.msg( 'pagetriage-info-timestamp-date-format' )
			), $( '<span>' ).text( mw.msg( 'rev-deleted-user' ) ) ).html() );
		}

		const stats = [
			mw.msg( 'pagetriage-bytes', this.model.get( 'page_len' ) ),
			mw.msg( 'pagetriage-edits', this.model.get( 'rev_count' ) ),
			mw.msg( 'pagetriage-categories', this.model.get( 'category_count' ) )
		];
		this.model.set( 'articleStat', mw.msg( 'pagetriage-articleinfo-stat', stats.join( mw.msg( 'pagetriage-dot-separator' ) ) ) );

		this.$tel.html( this.template( this.model.toJSON() ) );
		const history = new ArticleInfoHistoryView( { eventBus: this.eventBus, model: this.model.revisions } );
		this.$tel.find( '#mwe-pt-info-history-container' ).append( history.render().$el );

		// set the Learn More link URL
		$( '#mwe-pt-info .mwe-pt-flyout-help-link' ).attr( 'href', this.moduleConfig.helplink );

		// bind down here so it doesn't happen before the first render
		// Only bind this once
		const that = this;
		if ( !this.renderWasBound ) {
			this.model.bind( 'change', () => {
				that.render();
			} );
			this.renderWasBound = true;
		}

		return this;
	},

	formatProblem: function ( problem, link = '' ) {
		// The following messages are used here:
		// * pagetriage-info-problem-non-autoconfirmed
		// * pagetriage-info-problem-blocked
		// * pagetriage-info-problem-no-categories
		// * pagetriage-info-problem-orphan
		// * pagetriage-info-problem-recreated
		// * pagetriage-info-problem-no-references
		// * pagetriage-info-problem-copyvio
		const problemHtml = '<span class="mwe-pt-info-problem-name">' +
			mw.message( 'pagetriage-info-problem-' + problem ).escaped() +
			'</span>';

		// The following messages are used here:
		// * pagetriage-info-problem-non-autoconfirmed-desc
		// * pagetriage-info-problem-blocked-desc
		// * pagetriage-info-problem-no-categories-desc
		// * pagetriage-info-problem-orphan-desc
		// * pagetriage-info-problem-recreated-desc
		// * pagetriage-info-problem-no-references-desc
		// * pagetriage-info-problem-copyvio-desc
		let descHtml = '<span class="mwe-pt-info-problem-desc">' +
			mw.message( 'pagetriage-info-problem-' + problem + '-desc' ).escaped() +
			'</span>';

		if ( link ) {
			descHtml = `<a href="${ link }" target="_blank">${ descHtml }</a>`;
		}

		return `<li class="mwe-pt-info-problem">${ problemHtml } - ${ descHtml }</li>`;
	},

	formatOresProblem: function ( classification ) {
		// classification is already translated; see OresMetadata::fetchScores().
		return '<li class="mwe-pt-info-problem"><span class="mwe-pt-info-problem-name">' +
			classification + '</span></li>';
	},

	enumerateProblems: function () {
		let problems = '';
		this.problemCount = 0;

		// Give grep a chance to find the usages:
		// pagetriage-info-problem-blocked, pagetriage-info-problem-no-categories,
		// pagetriage-info-problem-orphan, pagetriage-info-problem-recreated,
		// pagetriage-info-problem-no-references, pagetriage-info-problem-copyvio
		if ( parseInt( this.model.get( 'user_block_status' ) ) === 1 ) {
			this.problemCount++;
			const blockLogLink = mw.util.getUrl( 'Special:Log', {
				type: 'block',
				page: this.model.get( 'user_name' )
			} );
			problems += this.formatProblem( 'blocked', blockLogLink );
		}
		if ( parseInt( this.model.get( 'is_redirect' ) ) === 0 ) {
			if ( parseInt( this.model.get( 'category_count' ) ) < 1 ) {
				this.problemCount++;
				problems += this.formatProblem( 'no-categories' );
			}
			if ( this.model.get( 'is_orphan' ) ) {
				this.problemCount++;
				const whatLinksHereLink = mw.util.getUrl( 'Special:WhatLinksHere', {
					namespace: 0,
					hideredirs: 1,
					target: this.model.get( 'title' )
				} );
				problems += this.formatProblem( 'orphan', whatLinksHereLink );
			}
			if ( typeof this.model.get( 'reference' ) !== 'undefined' &&
				parseInt( this.model.get( 'reference' ) ) === 0 ) {
				this.problemCount++;
				problems += this.formatProblem( 'no-references' );
			}
		}
		if ( parseInt( this.model.get( 'recreated' ) ) === 1 ) {
			this.problemCount++;
			const previouslyDeletedLogLink = mw.util.getUrl( 'Special:Log', {
				type: 'delete',
				page: this.model.get( 'title' )
			} );
			problems += this.formatProblem( 'recreated', previouslyDeletedLogLink );
		}
		if ( config.PageTriageEnableCopyvio && parseInt( this.model.get( 'copyvio' ) ) ) {
			this.problemCount++;
			problems += this.formatProblem( 'copyvio', this.model.get( 'copyvio_link_url' ) );
		}
		if ( config.PageTriageEnableOresFilters && this.model.get( 'ores_draftquality' ) ) {
			this.problemCount++;
			problems += this.formatOresProblem( this.model.get( 'ores_draftquality' ) );
		}
		if ( problems ) {
			problems = '<ul>' + problems + '</ul>';
		}
		this.model.set( 'problems_html', problems );
	}
} );

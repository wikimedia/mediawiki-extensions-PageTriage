// view for displaying all the article metadata

$( function() {
	mw.pageTriage.ArticleInfoView = mw.pageTriage.ToolView.extend( {
		id: 'mwe-pt-info',
		icon: 'icon_info.png', // the default icon
		title: gM( 'pagetriage-info-title' ),
		tooltip: 'pagetriage-info-tooltip',
		template: mw.pageTriage.viewUtil.template( { 'view': 'toolbar', 'template': 'articleInfo.html' } ),

		badgeCount: function() {
			this.enumerateProblems();
			return this.problemCount;
		},

		render: function() {
			var _this = this;
			this.enumerateProblems();
			// set the history link
			this.model.set(
				'history_link',
				this.model.buildLink(
					mw.config.get( 'wgArticlePath' ).replace( '$1', mw.config.get( 'wgPageName' ) ),
					'action=history'
				)
			);

			// creator information
			if (  this.model.get( 'user_name' ) ) {
				var info = this.model.userInfo( this.model.get( 'user_name' ) );

				// show new editor message only if the user is not anonymous and not autoconfirmed
				if ( this.model.get( 'user_id' ) > '0' && this.model.get( 'user_autoconfirmed' ) == '0' ) {
					var bylineMessage = 'pagetriage-articleinfo-byline-new-editor';
				} else {
					var bylineMessage = 'pagetriage-articleinfo-byline';
				}

				// put it all together in the byline
				var articleByline = mw.msg(
					bylineMessage,
					Date.parseExact( this.model.get( 'creation_date' ), 'yyyyMMddHHmmss' ).toString( gM( 'pagetriage-info-timestamp-date-format' ) ),
					info.userPageLink,
					info.userTalkPageLink,
					mw.msg( 'pipe-separator' ),
					info.userContribsLink
				);
				this.model.set( 'articleByline', articleByline );
			}

			var stats = [
				gM( "pagetriage-bytes", this.model.get( 'page_len' ) ),
				gM( "pagetriage-edits", this.model.get( 'rev_count' ) ),
				gM( "pagetriage-categories", this.model.get( 'category_count' ) )
			];
			this.model.set( 'articleStat', gM( 'pagetriage-articleinfo-stat', stats.join( gM( 'pagetriage-dot-separator' ) ) ) );

			this.$tel.html( this.template( this.model.toJSON() ) );
			var history = new mw.pageTriage.ArticleInfoHistoryView( { eventBus: this.eventBus, model: this.model.revisions } );
			this.$tel.find( '#mwe-pt-info-history-container' ).append( history.render().$el );

			// set the Learn More link URL
			$( '#mwe-pt-info .mwe-pt-flyout-help-link' ).attr( 'href', mw.config.get( 'wgPageTriageCurationModules' ).articleInfo );

			// bind down here so it doesn't happen before the first render
			this.model.unbind( 'change:patrol_status', function() { _this.render(); } );
			this.model.bind( 'change:patrol_status', function() { _this.render(); } );

			return this;
		},

		formatProblem: function( problem ) {
			return '<li class="mwe-pt-info-problem"><span class="mwe-pt-info-problem-name">' +
				gM( 'pagetriage-info-problem-' + problem ) +
				'</span> - <span class="mwe-pt-info-problem-desc">' +
				gM('pagetriage-info-problem-' + problem + '-desc') +
				'</span></li>';
		},

		enumerateProblems: function() {
			this.problemCount = 0;
			var problems = '';

			if ( this.model.get( 'user_block_status' ) == 1 ) {
				this.problemCount++;
				problems += this.formatProblem( 'blocked' );
			}
			if(  this.model.get( 'category_count' ) < 1 && this.model.get( 'is_redirect' ) == 0 ) {
				this.problemCount++;
				problems += this.formatProblem( 'no-categories' );
			}
			if ( this.model.get( 'linkcount' ) < 1 && this.model.get( 'is_redirect' ) == 0 ) {
				this.problemCount++;
				problems += this.formatProblem( 'orphan' );
			}
			if ( this.model.get( 'rev_count' ) < 1 ) {
				this.problemCount++;
				problems += this.formatProblem( 'no-references' );
			}
			if ( problems ) {
				problems = '<ul>' + problems + '</ul>';
			}
			this.model.set( 'problems', problems );
		}
	} );

	mw.pageTriage.ArticleInfoHistoryView = Backbone.View.extend( {
		id: 'mwe-pt-info-history',
		template: mw.pageTriage.viewUtil.template( { 'view': 'toolbar', 'template': 'articleInfoHistory.html' } ),

		render: function() {
			var _this = this;
			var lastDate = null;
			var x = 0;

			this.model.each( function ( historyItem ) {
				// Display the 5 most recent revisions
				if ( x < 5 ) {
					// I'd rather do this date parsing in the model, but the change event isn't properly
					// passed through to nested models, and switching to backbone-relational in order to
					// move these few lines of code seems silly.
					var timestamp_parsed = Date.parseExact( historyItem.get( 'timestamp' ), 'yyyy-MM-ddTHH:mm:ssZ' );
					historyItem.set('timestamp_date', timestamp_parsed.toString( gM( 'pagetriage-info-timestamp-date-format' ) ) );
					historyItem.set('timestamp_time', timestamp_parsed.toString( gM( 'pagetriage-info-timestamp-time-format' ) ) );
					if( historyItem.get( 'timestamp_date' ) !== lastDate ) {
						historyItem.set( 'new_date', true );
					} else {
						historyItem.set( 'new_date', false );
					}
					lastDate = historyItem.get( 'timestamp_date' );

					// get a userlink.
					// can't set link color since no userpage status is returned by the history api
					if( historyItem.get( 'user' ) ) {
						var userTitle = new mw.Title( historyItem.get( 'user' ), mw.config.get('wgNamespaceIds')['user'] );
						historyItem.set( 'user_title_url', userTitle.getUrl() );
					}

					historyItem.set( 'revision_url', mw.config.get( 'wgScriptPath' ) + '/index.php?title=' +
										mw.util.wikiUrlencode( mw.config.get( 'wgPageName' ) ) +
										'&oldid=' + historyItem.get( 'revid' ) );

					_this.$el.append( _this.template( historyItem.toJSON() ) );
				}
				x++;
			} );
			return this;
		}
	} );

} );

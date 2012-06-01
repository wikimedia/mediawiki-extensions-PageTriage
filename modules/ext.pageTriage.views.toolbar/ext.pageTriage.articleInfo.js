// view for displaying all the article metadata

$( function() {
	mw.pageTriage.ArticleInfoView = mw.pageTriage.ToolView.extend( {
		id: 'mwe-pt-info',
		icon: 'icon_info.png', // the default icon
		title: gM( 'pagetriage-info-title'),
		template: mw.pageTriage.viewUtil.template( { 'view': 'toolbar', 'template': 'articleInfo.html' } ),

		badgeCount: function() {
			this.enumerateProblems();
			return this.problemCount;
		},

		render: function() {
			// create the info view content here.
			// return the HTML that gets inserted.
			this.enumerateProblems();
			this.$tel.html( this.template( this.model.toJSON() ) );
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
			if( this.model.get('user_autoconfirmed') == 0 ) {
				this.problemCount++;
				problems += this.formatProblem( 'non-autoconfirmed' );
			}
			if( this.model.get('user_block_status') == 1 ) {
				this.problemCount++;
				problems += this.formatProblem( 'blocked' );
			}
			if( this.model.get('category_count') < 1 ) {
				this.problemCount++;
				problems += this.formatProblem( 'no-categories' );
			}
			if( this.model.get('linkcount') < 1 ) {
				this.problemCount++;
				problems += this.formatProblem( 'orphan' );
			}
			if( this.model.get('rev_count') < 1 ) {
				this.problemCount++;
				problems += this.formatProblem( 'no-references' );
			}
			this.model.set( 'problems', problems );
		}
	} );

} );

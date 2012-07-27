$( function() {
	if ( !mw.pageTriage ) {
		mw.pageTriage = {};
	}
	mw.pageTriage.viewUtil = {
		// define templates which should be cached in here, the key is the template view:
		// list, toolbar etc
		cache: { 'toolbar': {
					'articleInfo.html': '',
					'articleInfoHistory.html': '',
					'delete.html': '',
					'mark.html': '',
					'tags.html': '',
					'wikilove.html': '',
					'toolView.html': '',
					'toolbarView.html': ''
				}
			},
		// fetch and compile a template, then return it.
		// args: view, template
		template: function( arg ) {
			var _this = this;

			apiRequest = {
				'action': 'pagetriagetemplate',
				'view': arg.view,
				'format': 'json',
				'template': ''
			};

			if ( this.cache[arg.view] && this.cache[arg.view][arg.template] !== undefined ) {
				if ( this.cache[arg.view][arg.template] ) {
					return _.template( this.cache[arg.view][arg.template] );
				} else {
					for ( var template in this.cache[arg.view]) {
						if ( apiRequest.template ) {
							apiRequest.template += '|' + template;
						} else {
							apiRequest.template = template;
						}
					}
				}
			} else {
				apiRequest.template = arg.template;
			}

			var templateText;

			$.ajax( {
				type: 'post',
				url: mw.util.wikiScript( 'api' ),
				data: apiRequest,
				dataType: 'json',
				async: false,
				success: function( result ) {
					if ( result.pagetriagetemplate !== undefined && result.pagetriagetemplate.result === 'success' ) {
						if ( _this.cache[arg.view] && _this.cache[arg.view][arg.template] !== undefined ) {
							for ( var i in result.pagetriagetemplate.template ) {
								_this.cache[arg.view][i] = result.pagetriagetemplate.template[i];
							}
						}
						templateText = result.pagetriagetemplate.template[arg.template];
					}
				},
				error: function( xhr ) {
					$( '#mwe-pt-list-view' ).empty();
					$( '#mwe-pt-list-errors' ).html( mw.msg( 'pagetriage-api-error' ) );
					$( '#mwe-pt-list-errors' ).show();
				}
			} );

			return _.template( templateText );
		}
	};
} );

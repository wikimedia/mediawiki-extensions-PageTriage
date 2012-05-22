$( function() {
	if ( !mw.pageTriage ) {
		mw.pageTriage = {};
	}
	mw.pageTriage.viewUtil = {
		// fetch and compile a template, then return it.
		// args: view, template
		template: function( arg ) {
			apiRequest = {
				'action': 'pagetriagetemplate',
				'view': arg.view,
				'format': 'json'
			};

			var templateText;

			if( arg.template instanceof Array ) {
				apiRequest.template = arg.template.join('|');
			} else {
				apiRequest.template = arg.template;
			}

			$.ajax( {
				type: 'post',
				url: mw.util.wikiScript( 'api' ),
				data: apiRequest,
				dataType: 'json',
				async: false,
				success: function( result ) {
					if ( result.pagetriagetemplate !== undefined && result.pagetriagetemplate.result === 'success' ) {
						templateText = result.pagetriagetemplate.template;
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

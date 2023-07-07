const { Article } = require( 'ext.pageTriage.util' );
let DeleteToolView;

describe( 'DeleteToolView', () => {
	beforeEach( () => {
		mw.config.get = jest.fn( ( key ) => {
			switch ( key ) {
				case 'wgPageName':
					return 'PageName';
				default:
					return null;
			}
		} );
		// needs to be loaded after mw.config.get has been defined to avoid fatal.
		DeleteToolView = require( '../../modules/ext.pageTriage.views.toolbar/delete.js' );
	} );

	test( 'notifyUser', () => {
		const eventBus = _.extend( {}, Backbone.Events );
		const model = new Article( {
			eventBus,
			pageId: 5,
			includeHistory: true
		} );
		const toolbar = new DeleteToolView( { eventBus, model } );
		toolbar.selectedTag.tagKey = {
			usesSubpages: false
		};

		const msg = toolbar.notifyUser( {
			tagCount: 1,
			tagKey: 'tagKey'
		} );

		return msg.then( function () {
			expect( true ).toBe( true );
		} );
	} );
} );

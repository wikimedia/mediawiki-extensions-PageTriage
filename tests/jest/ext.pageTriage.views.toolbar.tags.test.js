const { Article } = require( 'ext.pageTriage.util' );
const TagToolView = require( '../../modules/ext.pageTriage.views.toolbar/tags.js' );

describe( 'TagToolView', () => {
	beforeEach( () => {
		mw.config.get = jest.fn( ( key ) => {
			switch ( key ) {
				case 'wgPageName':
					return 'PageName';
				default:
					return null;
			}
		} );
	} );

	test( 'talkPageNote', () => {
		const actionQueue = {
			runAndRefresh: jest.fn()
		};
		mw.pageTriage = { actionQueue };
		const eventBus = _.extend( {}, Backbone.Events );
		const model = new Article( {
			eventBus,
			pageId: 5,
			includeHistory: true
		} );
		const toolbar = new TagToolView( { eventBus, model } );
		return toolbar.talkPageNote( 'foo' ).then( () => {
			expect( actionQueue.runAndRefresh ).toBeCalled();
		} );
	} );
} );

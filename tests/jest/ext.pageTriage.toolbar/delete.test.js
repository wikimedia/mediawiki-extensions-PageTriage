let Article, DeleteToolView, model, eventBus;

describe( 'DeleteToolView', () => {
	beforeEach( () => {
		eventBus = _.extend( {}, Backbone.Events );
		mw.config.get = jest.fn( ( key ) => {
			switch ( key ) {
				case 'wgPageName':
					return 'PageName';
				default:
					return null;
			}
		} );
		mw.user.options.get = jest.fn( ( key ) => {
			switch ( key ) {
				case 'timecorrection':
					return 'ZoneInfo|-480|America/Los_Angeles';
				default:
					return null;
			}
		} );
		// needs to be loaded after mw.config.get has been defined to avoid fatal.
		Article = require( 'ext.pageTriage.util' ).Article;
		model = new Article( {
			eventBus,
			pageId: 5,
			includeHistory: true
		} );

		DeleteToolView = require( '../../../modules/ext.pageTriage.toolbar/delete.js' );
	} );

	test( 'notifyUser with no talk page template', () => {
		const toolbar = new DeleteToolView( { eventBus, model } );
		toolbar.selectedTag.tagKey = {
			usesSubpages: false
		};

		const msg = toolbar.notifyUser( {
			tagCount: 1,
			tagKey: 'tagKey'
		} );

		return msg.then( () => {
			expect( true ).toBe( true );
		} );
	} );

	test( 'notifyUser with talk page template', () => {
		const toolbar = new DeleteToolView( { eventBus, model } );
		toolbar.selectedTag.tagKey = {
			usesSubpages: false,
			talkpagenotiftopictitle: 'pagetriage-del-tags-speedy-deletion-nomination-notify-topic-title',
			talkpagenotiftpl: 'Db-foreign-notice-NPF'
		};

		const msg = toolbar.notifyUser( {
			tagCount: 1,
			tagKey: 'tagKey'
		} );

		return msg.then( () => {
			expect( true ).toBe( true );
		} );
	} );
} );

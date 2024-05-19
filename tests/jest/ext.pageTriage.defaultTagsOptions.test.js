let defaultTagsOptions;

describe( 'defaultTagsOptions', () => {
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
		defaultTagsOptions = require( '../../modules/ext.pageTriage.defaultTagsOptions/main.js' );
	} );

	test( 'defaultTagsOptions should exist', () => {
		expect( defaultTagsOptions.tags.redirectCategoryShell ).not.toBe( undefined );
		expect( defaultTagsOptions.tags.multiple ).not.toBe( undefined );
		expect( defaultTagsOptions.tags.tagOptions ).not.toBe( undefined );
	} );

	test( 'defaultDeletionTagsOptions should exist', () => {
		expect( defaultTagsOptions.$.pageTriageDeletionTagsMultiple ).not.toBe( undefined );
		expect( defaultTagsOptions.$.pageTriageDeletionTagsOptions ).not.toBe( undefined );
	} );

	test( 'defaultTagsOptions should match snapshot', () => {
		expect( defaultTagsOptions.tags.redirectCategoryShell ).toMatchSnapshot();
		expect( defaultTagsOptions.tags.multiple ).toMatchSnapshot();
		expect( defaultTagsOptions.tags.tagOptions ).toMatchSnapshot();
	} );

	test( 'defaultDeletionTagsOptions should match snapshot', () => {
		expect( defaultTagsOptions.$.pageTriageDeletionTagsMultiple ).toMatchSnapshot();
		expect( defaultTagsOptions.$.pageTriageDeletionTagsOptions ).toMatchSnapshot();
	} );
} );

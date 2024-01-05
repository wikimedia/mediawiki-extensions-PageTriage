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
		expect( defaultTagsOptions.$.pageTriageTagsRedirectCategoryShell ).not.toBe( undefined );
		expect( defaultTagsOptions.$.pageTriageTagsMultiple ).not.toBe( undefined );
		expect( defaultTagsOptions.$.pageTriageTagsOptions ).not.toBe( undefined );
	} );

	test( 'defaultDeletionTagsOptions should exist', () => {
		expect( defaultTagsOptions.$.pageTriageDeletionTagsMultiple ).not.toBe( undefined );
		expect( defaultTagsOptions.$.pageTriageDeletionTagsOptions ).not.toBe( undefined );
	} );

	test( 'defaultTagsOptions should match snapshot', () => {
		expect( defaultTagsOptions.$.pageTriageTagsRedirectCategoryShell ).toMatchSnapshot();
		expect( defaultTagsOptions.$.pageTriageTagsMultiple ).toMatchSnapshot();
		expect( defaultTagsOptions.$.pageTriageTagsOptions ).toMatchSnapshot();
	} );

	test( 'defaultDeletionTagsOptions should match snapshot', () => {
		expect( defaultTagsOptions.$.pageTriageDeletionTagsMultiple ).toMatchSnapshot();
		expect( defaultTagsOptions.$.pageTriageDeletionTagsOptions ).toMatchSnapshot();
	} );
} );

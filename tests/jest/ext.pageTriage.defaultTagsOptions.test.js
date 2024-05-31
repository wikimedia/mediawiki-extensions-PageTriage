let defaultTagsOptions;

describe( 'defaultTagsOptions', () => {
	beforeEach( () => {
		// needs to be loaded after mw.config.get has been defined to avoid fatal.
		defaultTagsOptions = require( '../../modules/ext.pageTriage.defaultTagsOptions/main.js' );
	} );

	test( 'defaultTagsOptions should exist', () => {
		expect( defaultTagsOptions.tags.redirectCategoryShell ).not.toBe( undefined );
		expect( defaultTagsOptions.tags.multiple ).not.toBe( undefined );
		expect( defaultTagsOptions.tags.tagOptions ).not.toBe( undefined );
	} );

	test( 'defaultDeletionTagsOptions should exist', () => {
		expect( defaultTagsOptions.deletion.multiple ).not.toBe( undefined );
		expect( defaultTagsOptions.deletion.main ).not.toBe( undefined );
	} );
} );

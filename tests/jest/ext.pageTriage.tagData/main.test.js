let tagData;

describe( 'tagData', () => {
	beforeEach( () => {
		// needs to be loaded after mw.config.get has been defined to avoid fatal.
		tagData = require( '../../../modules/ext.pageTriage.tagData/main.js' );
	} );

	test( 'tagData should exist', () => {
		expect( tagData.tags.redirectCategoryShell ).not.toBe( undefined );
		expect( tagData.tags.multiple ).not.toBe( undefined );
		expect( tagData.tags.tagOptions ).not.toBe( undefined );
	} );

	test( 'defaultDeletionTagsOptions should exist', () => {
		expect( tagData.deletion.multiple ).not.toBe( undefined );
		expect( tagData.deletion.main ).not.toBe( undefined );
	} );
} );

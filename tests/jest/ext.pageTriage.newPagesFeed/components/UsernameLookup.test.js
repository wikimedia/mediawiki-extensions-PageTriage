const utils = require( '@vue/test-utils' );
const mixins = require( '../../../mocks/mixins.js' );
const UsernameLookup = require( '../../../../modules/ext.pageTriage.newPagesFeed/components/UsernameLookup.vue' );
const { MAX_FILTER_USERNAMES } = require( '../../../../modules/ext.pageTriage.newPagesFeed/usernames.js' );

function mountLookup( usernames ) {
	return utils.mount( UsernameLookup, {
		props: usernames ? { usernames } : {},
		global: { mixins: [ mixins ] }
	} );
}

function tenUsernames() {
	const usernames = [];
	for ( let i = 0; i < MAX_FILTER_USERNAMES; i++ ) {
		usernames.push( `user-${ i }` );
	}
	return usernames;
}

let wrapper;
describe( 'UsernameLookup.vue', () => {
	afterEach( () => {
		if ( wrapper ) {
			wrapper.unmount();
			wrapper = null;
		}
	} );

	it( 'mounts', () => {
		wrapper = mountLookup();
		expect( wrapper.exists() ).toBe( true );
		expect( wrapper.vm.selection ).toEqual( [] );
	} );

	it( 'mounts and shows a chip for each username passed in', () => {
		wrapper = mountLookup( [ 'test-user', 'other-user' ] );
		expect( wrapper.vm.selection ).toEqual( [ 'test-user', 'other-user' ] );
		expect( wrapper.vm.chips ).toEqual( [
			{ value: 'test-user' },
			{ value: 'other-user' }
		] );
	} );

	it( 'shows the limit message when created at the maximum', () => {
		wrapper = mountLookup( tenUsernames() );
		expect( wrapper.vm.limitReached ).toBe( true );
	} );

	it( 'emits the selection when it changes', async () => {
		wrapper = mountLookup( [ 'test-user' ] );
		wrapper.vm.selection = [ 'test-user', 'other-user' ];
		await wrapper.vm.$nextTick();
		expect( wrapper.emitted( 'update:usernames' ) ).toEqual( [
			[ [ 'test-user', 'other-user' ] ]
		] );
	} );

	it( 'adds the search term immediately on blur', () => {
		wrapper = mountLookup();
		wrapper.vm.currentSearchTerm = ' typed-user ';
		wrapper.vm.addTypedUsername();
		expect( wrapper.vm.selection ).toEqual( [ 'typed-user' ] );
		expect( wrapper.vm.chips ).toEqual( [ { value: 'typed-user' } ] );
		expect( wrapper.vm.currentSearchTerm ).toBe( '' );
	} );

	it( 'adds the search term as a username when it was not selected from the menu', async () => {
		wrapper = mountLookup();
		wrapper.vm.currentSearchTerm = ' typed-user ';
		wrapper.vm.addSearchTerm();
		await wrapper.vm.$nextTick();
		await wrapper.vm.$nextTick();
		await wrapper.vm.$nextTick();
		expect( wrapper.vm.selection ).toEqual( [ 'typed-user' ] );
		expect( wrapper.vm.chips ).toEqual( [ { value: 'typed-user' } ] );
		expect( wrapper.vm.currentSearchTerm ).toBe( '' );
	} );

	it( 'does not add a username twice', async () => {
		wrapper = mountLookup( [ 'typed-user' ] );
		wrapper.vm.currentSearchTerm = 'typed-user';
		wrapper.vm.addSearchTerm();
		await wrapper.vm.$nextTick();
		await wrapper.vm.$nextTick();
		expect( wrapper.vm.selection ).toEqual( [ 'typed-user' ] );
		expect( wrapper.emitted( 'update:usernames' ) ).toBeUndefined();
	} );

	it( 'does not add the search term that produced a menu selection', async () => {
		wrapper = mountLookup();
		wrapper.vm.currentSearchTerm = 'Ali';
		// Selecting from the menu keeps the search term in the input.
		wrapper.vm.selection = [ 'Alice' ];
		wrapper.vm.addSearchTerm();
		await wrapper.vm.$nextTick();
		await wrapper.vm.$nextTick();
		expect( wrapper.vm.selection ).toEqual( [ 'Alice' ] );
	} );

	it( 'still adds a typed name after a chip is removed', async () => {
		wrapper = mountLookup( [ 'Alice' ] );
		wrapper.vm.currentSearchTerm = 'Bob';
		wrapper.vm.selection = [];
		await wrapper.vm.$nextTick();
		wrapper.vm.addTypedUsername();
		expect( wrapper.vm.selection ).toEqual( [ 'Bob' ] );
	} );

	it( 'commits a typed name on mousedown outside the lookup', () => {
		wrapper = mountLookup();
		wrapper.vm.currentSearchTerm = 'typed-user';
		document.dispatchEvent( new MouseEvent( 'mousedown', { bubbles: true } ) );
		expect( wrapper.vm.selection ).toEqual( [ 'typed-user' ] );
	} );

	it( 'does not commit a typed name on mousedown inside the lookup', () => {
		wrapper = mountLookup();
		wrapper.vm.currentSearchTerm = 'typed-user';
		const lookup = document.createElement( 'span' );
		lookup.className = 'mwe-vue-pt-username-lookup';
		const inside = document.createElement( 'input' );
		lookup.appendChild( inside );
		document.body.appendChild( lookup );
		inside.dispatchEvent( new MouseEvent( 'mousedown', { bubbles: true } ) );
		expect( wrapper.vm.selection ).toEqual( [] );
		lookup.remove();
	} );

	it( 'does not commit a typed name on mousedown in a teleported menu', () => {
		wrapper = mountLookup();
		wrapper.vm.currentSearchTerm = 'typed-user';
		const menu = document.createElement( 'div' );
		menu.className = 'cdx-menu';
		document.body.appendChild( menu );
		menu.dispatchEvent( new MouseEvent( 'mousedown', { bubbles: true } ) );
		expect( wrapper.vm.selection ).toEqual( [] );
		menu.remove();
	} );

	it( 'caps the number of usernames', async () => {
		const usernames = tenUsernames();
		wrapper = mountLookup( usernames );

		wrapper.vm.selection = usernames.concat( 'one-too-many' );
		await wrapper.vm.$nextTick();

		expect( wrapper.vm.selection ).toEqual( usernames );
		expect( wrapper.vm.chips ).toHaveLength( MAX_FILTER_USERNAMES );
		expect( wrapper.vm.limitReached ).toBe( true );
		expect( wrapper.emitted( 'update:usernames' ) ).toEqual( [ [ usernames ] ] );
	} );
} );

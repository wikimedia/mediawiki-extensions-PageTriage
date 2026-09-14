<template>
	<span
		ref="root"
		class="mwe-vue-pt-username-lookup"
	>
		<cdx-multiselect-lookup
			v-bind="$attrs"
			v-model:input-chips="chips"
			v-model:selected="selection"
			v-model:input-value="currentSearchTerm"
			:menu-items="menuItems"
			:menu-config="menuConfig"
			:keep-input-on-selection="true"
			@input="onInput"
			@blur="addTypedUsername"
			@keydown.enter="addSearchTerm"
			@focus="$emit( 'focus' )"
		>
			<template #no-results>
				{{ $i18n( 'pagetriage-filter-username-lookup-nousernamefound' ).text() }}
			</template>
		</cdx-multiselect-lookup>
		<span v-if="limitReached" class="mwe-vue-pt-username-lookup__limit">
			{{ $i18n( 'pagetriage-filter-username-lookup-limit', maxUsernames ).text() }}
		</span>
	</span>
</template>

<script>
/**
 * @author Sohom Datta
 */
const { CdxMultiselectLookup } = require( '@wikimedia/codex' );
const { nextTick, onBeforeUnmount, onMounted, ref, watch } = require( 'vue' );
const { MAX_FILTER_USERNAMES } = require( '../usernames.js' );

/**
 * Build the chips shown in the input for a list of usernames.
 *
 * @param {string[]} usernames
 * @return {Object[]}
 */
function toChips( usernames ) {
	return usernames.map( ( username ) => ( { value: username } ) );
}

/**
 * Whether a click landed inside this lookup or a teleported Codex menu.
 *
 * @param {Event} event
 * @param {HTMLElement|null} rootEl
 * @return {boolean}
 */
function shouldIgnoreOutsideClick( event, rootEl ) {
	const target = event.target;
	if ( !( target instanceof Element ) ) {
		return false;
	}
	if ( rootEl && rootEl.contains( target ) ) {
		return true;
	}
	// Codex may teleport the menu to the document body.
	return !!target.closest( '.mwe-vue-pt-username-lookup, .cdx-menu' );
}

// @vue/component
module.exports = {
	name: 'UsernameLookup',
	components: {
		CdxMultiselectLookup
	},
	inheritAttrs: false,
	props: {
		usernames: { type: Array, default: () => [] }
	},
	emits: [ 'update:usernames', 'focus' ],
	setup( props, { emit } ) {
		const root = ref( null );
		const menuItems = ref( [] );
		const currentSearchTerm = ref( '' );
		const selection = ref( props.usernames.slice() );
		const chips = ref( toChips( props.usernames ) );
		const limitReached = ref( props.usernames.length >= MAX_FILTER_USERNAMES );
		// Search term that produced the most recent menu selection. Because the input
		// is kept on selection, it must not also be added as a free-text username.
		let searchTermAtSelection = null;

		function fetchUsernames( searchTerm ) {
			const api = new mw.Api();
			return api.get( {
				action: 'query',
				list: 'allusers',
				aufrom: searchTerm,
				auexcludegroup: 'bot',
				aulimit: 3,
				auwitheditsonly: '1'
			} ).then( ( resp ) => resp.query.allusers );
		}

		function onInput( value ) {
			currentSearchTerm.value = value;
			if ( value !== searchTermAtSelection ) {
				searchTermAtSelection = null;
			}

			if ( !value ) {
				menuItems.value = [];
				return;
			}

			fetchUsernames( value ).then( ( data ) => {
				if ( currentSearchTerm.value !== value ) {
					return;
				}

				// Reset the menu items if there are no results.
				if ( data.length === 0 ) {
					menuItems.value = [];
					return;
				}

				// Update menuItems.
				menuItems.value = data.map( ( result ) => ( {
					label: result.name,
					value: result.name
				} ) );

			} ).catch( () => {
				// On error, set results to empty.
				menuItems.value = [];
			} );
		}

		function addTypedUsername() {
			const username = String( currentSearchTerm.value || '' ).trim();
			if (
				!username ||
				username === searchTermAtSelection ||
				selection.value.includes( username )
			) {
				return;
			}
			if ( selection.value.length >= MAX_FILTER_USERNAMES ) {
				limitReached.value = true;
				return;
			}
			chips.value = chips.value.concat( { value: username } );
			selection.value = selection.value.concat( username );
			currentSearchTerm.value = '';
			menuItems.value = [];
		}

		/**
		 * Add whatever has been typed as a username, so that names missing from the
		 * menu (bots, users without edits, names beyond the three suggestions) can
		 * still be filtered on. MultiselectLookup does not add free text itself.
		 *
		 * Enter may have just picked a menu item; wait for that selection to land
		 * before treating leftover text as a username. Blur and outside clicks
		 * commit immediately via addTypedUsername.
		 */
		function addSearchTerm() {
			nextTick( () => {
				nextTick( addTypedUsername );
			} );
		}

		function onDocumentMouseDown( event ) {
			if ( !shouldIgnoreOutsideClick( event, root.value ) ) {
				addTypedUsername();
			}
		}

		// Keep up with the filter being changed elsewhere, for example when the
		// filters are reset.
		watch( () => props.usernames, ( newUsernames ) => {
			limitReached.value = newUsernames.length >= MAX_FILTER_USERNAMES;
			if ( newUsernames.join( '|' ) === selection.value.join( '|' ) ) {
				return;
			}
			chips.value = toChips( newUsernames );
			selection.value = newUsernames.slice();
		} );

		watch( selection, ( newSelection, oldSelection ) => {
			if ( newSelection.length > MAX_FILTER_USERNAMES ) {
				limitReached.value = true;
				// Keep the earlier selections and drop the extras; this watcher runs
				// again with the trimmed list and emits that.
				chips.value = chips.value.slice( 0, MAX_FILTER_USERNAMES );
				selection.value = newSelection.slice( 0, MAX_FILTER_USERNAMES );
				return;
			}
			limitReached.value = newSelection.length >= MAX_FILTER_USERNAMES;
			const previous = oldSelection || [];
			if ( newSelection.length > previous.length ) {
				// A value was added. Record the current input so a menu pick
				// from a prefix is not also committed as free text. Chip
				// removal must not do this, or a name being typed would be skipped.
				searchTermAtSelection = currentSearchTerm.value;
			}
			emit( 'update:usernames', newSelection.slice() );
		} );

		onMounted( () => {
			document.addEventListener( 'mousedown', onDocumentMouseDown, true );
		} );
		onBeforeUnmount( () => {
			document.removeEventListener( 'mousedown', onDocumentMouseDown, true );
		} );

		const menuConfig = {
			visibleItemLimit: 6
		};

		return {
			root,
			chips,
			selection,
			limitReached,
			maxUsernames: MAX_FILTER_USERNAMES,
			menuConfig,
			menuItems,
			currentSearchTerm,
			onInput,
			addTypedUsername,
			addSearchTerm
		};
	}
};

</script>

<style lang="less">
@import 'mediawiki.skin.variables.less';

.mwe-vue-pt-username-lookup {
	display: inline-block;

	.cdx-menu-item {
		overflow: hidden;
		margin-right: 0.5em;
	}

	&__limit {
		display: block;
		color: @color-subtle;
		font-size: @font-size-small;
	}
}

.mwe-vue-pt-username-lookup .cdx-multiselect-lookup {
	min-width: @size-1600;
}

.mwe-vue-pt-username-lookup .cdx-text-input__input {
	min-height: @size-75;
	line-height: @size-75;
}
</style>

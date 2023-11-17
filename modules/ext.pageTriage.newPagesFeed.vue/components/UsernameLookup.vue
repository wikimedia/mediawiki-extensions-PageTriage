<template>
	<cdx-lookup
		v-model:selected="usernameVal"
		:initial-input-value="username"
		:menu-items="menuItems"
		class="mwe-vue-pt-username-lookup"
		:menu-config="menuConfig"
		inline
		@input="onInput"
		@focus="$emit( 'focus' )"
	>
		<template #no-results>
			{{ $i18n( 'pagetriage-filter-username-lookup-nousernamefound' ).text() }}
		</template>
	</cdx-lookup>
</template>

<script>
/**
 * @author Sohom Datta
 */
const { CdxLookup } = require( '@wikimedia/codex' );
const { ref } = require( 'vue' );
// @vue/component
module.exports = {
	compatConfig: {
		MODE: 3
	},
	compilerOptions: {
		whitespace: 'condense'
	},
	name: 'UsernameLookup',
	components: {
		CdxLookup
	},
	props: {
		username: { type: String, default: '' }
	},
	emits: [ 'update:username', 'focus' ],
	setup( props, { emit } ) {
		const menuItems = ref( [] );
		const currentSearchTerm = ref( '' );

		function fetchUsernames( searchTerm ) {
			const api = new mw.Api();
			return api.get( {
				action: 'query',
				list: 'allusers',
				aufrom: searchTerm,
				auexcludegroup: 'bot',
				aulimit: 3,
				auwitheditsonly: '1'
			} ).then( ( resp ) => { return resp.query.allusers; } );
		}

		function onInput( value ) {
			currentSearchTerm.value = value;

			if ( !value ) {
				menuItems.value = [];
				return;
			}

			// Update the parent username variable so that
			// even if the user does not end up selecting anything,
			// the username change is registered.
			emit( 'update:username', value );

			fetchUsernames( value ).then( ( data ) => {
				if ( currentSearchTerm.value !== value ) {
					return;
				}

				// Reset the menu items if there are no results.
				if ( data.length === 0 ) {
					menuItems.value = [];
					return;
				}

				const results = data.map( ( result ) => {
					return {
						label: result.name,
						value: result.name
					};
				} );

				// Update menuItems.
				menuItems.value = results;

			} ).catch( () => {
				// On error, set results to empty.
				menuItems.value = [];
			} );
		}

		const menuConfig = {
			visibleItemLimit: 6
		};

		return {
			usernameVal: ref( props.username ),
			menuConfig,
			menuItems,
			onInput
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
}

.mwe-vue-pt-username-lookup .cdx-text-input {
	min-width: @size-1600;
}

.mwe-vue-pt-username-lookup .cdx-text-input__input {
	min-height: @size-75;
	line-height: @size-75;
}
</style>

<template>
	<cdx-select
		id="mwe-vue-pt-sort-afc"
		v-model:selected="settings.immediate.afcSort"
		:menu-items="afcMenuItems"
		@update:selected="( newVal ) => settings.updateImmediate( 'afcSort', newVal )"
	>
	</cdx-select>
</template>

<script>
/**
 * @author DannyS712
 * 'newestreview' and 'oldestreview' are used for both newest/oldest submitted and
 * newest/oldest declined, PageTriage adds one or the other, we just change the label -
 * only shown when filtering for submitted, under review, or declined
 */
const { CdxSelect } = require( '@wikimedia/codex' );
const { storeToRefs } = require( 'pinia' );
const { computed } = require( 'vue' );
const { useSettingsStore } = require( '../stores/settings.js' );
// @vue/component
module.exports = {
	name: 'AfcSortSelect',
	components: {
		CdxSelect
	},
	setup() {
		return {
			// if the declined/submitted sort options should be included, the end of
			// the message key to use (pagetriage-afc-(old|new)est-(declined|submitted))
			afcSortUpdated: computed( function () {
				const { applied } = storeToRefs( useSettingsStore() );
				if ( applied.value.afcSubmissionState === 'declined' ) {
					return 'declined';
				} else if (
					applied.value.afcSubmissionState === 'pending' ||
					applied.value.afcSubmissionState === 'reviewing'
				) {
					return 'submitted';
				}
				return null;
			} )
		};
	},
	data: function () {
		return {
			settings: useSettingsStore()
		};
	},
	computed: {
		newestReviewText: function () {
			// Possible keys
			// pagetriage-afc-newest-declined
			// pagetriage-afc-newest-submitted
			// eslint-disable-next-line mediawiki/msg-doc
			return this.$i18n( `pagetriage-afc-newest-${ this.afcSortUpdated }` ).text();
		},
		oldestReviewText: function () {
			// Possible keys
			// pagetriage-afc-oldest-declined
			// pagetriage-afc-oldest-submitted
			// eslint-disable-next-line mediawiki/msg-doc
			return this.$i18n( `pagetriage-afc-oldest-${ this.afcSortUpdated }` ).text();
		},
		afcMenuItems: function () {
			const afcMenuItems = [
				{ label: this.$i18n( 'pagetriage-afc-newest' ).text(), value: 'newestfirst' },
				{ label: this.$i18n( 'pagetriage-afc-oldest' ).text(), value: 'oldestfirst' }
			];

			if ( this.afcSortUpdated ) {
				afcMenuItems.push( { label: this.newestReviewText, value: 'newestreview' } );
				afcMenuItems.push( { label: this.oldestReviewText, value: 'oldestreview' } );
			}

			return afcMenuItems;

		}
	}
};
</script>

<style lang="less">
#mwe-vue-pt-sort-afc {
	min-width: unset;
}
</style>

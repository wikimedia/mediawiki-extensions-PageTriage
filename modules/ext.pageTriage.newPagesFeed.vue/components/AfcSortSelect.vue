<template>
	<label for="mwe-vue-pt-sort-afc">
		<b>
			{{ $i18n( 'pagetriage-sort-by' ).text() }}
		</b>
	</label>
	<select
		id="mwe-vue-pt-sort-afc"
		v-model="settings.immediate.afcSort"
		@change="settings.updateImmediate( 'afcSort', $event.target.value )"
	>
		<option value="newestfirst">
			{{ $i18n( 'pagetriage-afc-newest' ).text() }}
		</option>
		<option value="oldestfirst">
			{{ $i18n( 'pagetriage-afc-oldest' ).text() }}
		</option>
		<template v-if="afcSortUpdated">
			<option value="newestreview">
				{{ newestReviewText }}
			</option>
			<option value="oldestreview">
				{{ oldestReviewText }}
			</option>
		</template>
	</select>
</template>

<script>
/**
 * @author DannyS712
 * 'newestreview' and 'oldestreview' are used for both newest/oldest submitted and newest/oldest declined,
 * PageTriage adds one or the other, we just change the label - only shown when filtering for submitted, under review, or declined
 */
const { storeToRefs } = require( 'pinia' );
const { computed } = require( 'vue' );
const { useSettingsStore } = require( '../stores/settings.js' );
// @vue/component
module.exports = {
	compatConfig: {
		MODE: 3
	},
	compilerOptions: {
		whitespace: 'condense'
	},
	name: 'AfcSortSelect',
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
			return this.$i18n( `pagetriage-afc-newest-${this.afcSortUpdated}` ).text();
		},
		oldestReviewText: function () {
			// Possible keys
			// pagetriage-afc-oldest-declined
			// pagetriage-afc-oldest-submitted
			// eslint-disable-next-line mediawiki/msg-doc
			return this.$i18n( `pagetriage-afc-oldest-${this.afcSortUpdated}` ).text();
		}
	}
};
</script>

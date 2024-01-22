<template>
	<cdx-radio
		v-for="radio in afcStateRadios"
		:key="radio.value"
		v-model="selected"
		name="afc-state-radio-group"
		:input-value="radio.value"
		@click="$emit( 'update:state', radio.value )"
	>
		<span>
			{{ radio.label }}
		</span>
	</cdx-radio>
</template>

<script>
/**
 * @author DannyS712
 * Radio button group for filtering afc queue based on state
 */
const { CdxRadio } = require( '@wikimedia/codex' );
const { ref, watch } = require( 'vue' );
const { useSettingsStore } = require( '../stores/settings.js' );
// @vue/component
module.exports = {
	name: 'AfcStateRadio',
	components: { CdxRadio },
	props: {
		state: { type: String, default: 'pending' }
	},
	emits: [
		'update:state'
	],
	setup( props ) {
		const settings = useSettingsStore();
		const selected = ref( props.state );
		watch(
			selected,
			( newState ) => {
				// Make sure that afcSort isn't invalid
				if ( newState !== 'unsubmitted' && newState !== 'all' ) {
					// oldest/newest submitted/declined are valid
					return;
				}
				if ( settings.immediate.afcSort === 'newestreview' ) {
					settings.updateImmediate( 'afcSort', 'newestfirst' );
				} else if ( settings.immediate.afcSort === 'oldestreview' ) {
					settings.updateImmediate( 'afcSort', 'oldestfirst' );
				}
			}
		);
		return {
			selected
		};
	},
	data: function () {
		return {
			afcStateRadios: [
				{
					value: 'unsubmitted',
					label: this.$i18n( 'pagetriage-afc-state-unsubmitted' ).text()
				},
				{
					value: 'pending',
					label: this.$i18n( 'pagetriage-afc-state-pending' ).text()
				},
				{
					value: 'reviewing',
					label: this.$i18n( 'pagetriage-afc-state-reviewing' ).text()
				},
				{
					value: 'declined',
					label: this.$i18n( 'pagetriage-afc-state-declined' ).text()
				},
				{
					value: 'all',
					label: this.$i18n( 'pagetriage-afc-state-all' ).text()
				}
			]
		};
	}
};

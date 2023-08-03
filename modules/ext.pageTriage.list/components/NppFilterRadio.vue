<template>
	<cdx-radio
		v-for="radio in thatRadios"
		:key="radio.value"
		v-model="selected"
		name="npp-filter-radio-group"
		:input-value="radio.value"
		@click="$emit( 'update:filter', radio.value )"
	>
		<span>{{ radio.label }}</span>
	</cdx-radio>
	<fieldset class="no-border">
		<legend class="no-indent">
			{{ filterUserHeadingLabel }}
		</legend>
		<template v-for="radio in byRadios" :key="radio.value">
			<cdx-radio
				:id="`mwe-vue-pt-filter-radio-${radio.value}`"
				v-model="selected"
				name="npp-filter-radio-group"
				:input-value="radio.value"
				:inline="radio.inline"
				@click="$emit( 'update:filter', radio.value )"
			>
				<span>{{ radio.label }}</span>
			</cdx-radio>
			<span v-if="radio.value === 'username'">
				<input
					id="mwe-vue-pt-filter-input-username"
					v-model="byUser"
					type="text"
					:placeholder="$i18n( 'pagetriage-filter-username' ).text()"
					@input="$emit( 'update:user', $event.target.value )"
				>
			</span>
		</template>
	</fieldset>
	<cdx-radio
		v-model="selected"
		name="npp-filter-radio-group"
		input-value="all"
		@click="$emit( 'update:filter', 'all' )"
	>
		<span>{{ filterAllLabel }}</span>
	</cdx-radio>
</template>

<script>
/**
 * Radio button group for controlling new page patrol feed
 */

const { CdxRadio } = require( '@wikimedia/codex' );
const { ref } = require( 'vue' );
// @vue/component
module.exports = {
	configureCompat: {
		MODE: 3
	},
	compilerOptions: {
		whitespace: 'condense'
	},
	name: 'NppFilterRadio',
	components: { CdxRadio },
	props: {
		filter: { type: String, default: '' },
		user: { type: String, default: '' }
	},
	emits: [
		'update:filter',
		'update:user'
	],
	setup( props ) {
		return {
			selected: ref( props.filter ),
			byUser: ref( props.user )
		};
	},
	data: function () {
		return {
			filterUserHeadingLabel: this.$i18n( 'pagetriage-filter-user-heading' ).text(),
			filterAllLabel: this.$i18n( 'pagetriage-filter-all' ).text(),
			thatRadios: [
				{
					value: 'no-categories',
					label: this.$i18n( 'pagetriage-filter-no-categories' ).text()
				},
				{
					value: 'unreferenced',
					label: this.$i18n( 'pagetriage-filter-unreferenced' ).text()
				},
				{
					value: 'orphan',
					label: this.$i18n( 'pagetriage-filter-orphan' ).text()
				},
				{
					value: 'recreated',
					label: this.$i18n( 'pagetriage-filter-recreated' ).text()
				}
			],
			byRadios: [
				{
					value: 'non-autoconfirmed',
					label: this.$i18n( 'pagetriage-filter-non-autoconfirmed' ).text()
				},
				{
					value: 'learners',
					label: this.$i18n( 'pagetriage-filter-learners' ).text()
				},
				{
					value: 'blocked',
					label: this.$i18n( 'pagetriage-filter-blocked' ).text()
				},
				{
					value: 'bot-edits',
					label: this.$i18n( 'pagetriage-filter-bot-edits' ).text()
				},
				{
					value: 'autopatrolled-edits',
					label: this.$i18n( 'pagetriage-filter-autopatrolled-edits' ).text()
				},
				{
					value: 'username',
					label: this.$i18n( 'pagetriage-filter-username' ).text()
				}
			]
		};
	}
};
</script>

<style lang="less">
@import 'mediawiki.mixins.less';
.no-border {
	border: 0;
}
legend.no-indent {
	padding-left: 0;
	margin-left: -12px;
}
#mwe-vue-pt-filter-radio-username {
	display: inline;
}
#mwe-vue-pt-filter-radio-username label {
	.mixin-screen-reader-text()
}
#mwe-vue-pt-filter-input-username {
    margin-left: calc(1.4285714em + 8px);
}
</style>

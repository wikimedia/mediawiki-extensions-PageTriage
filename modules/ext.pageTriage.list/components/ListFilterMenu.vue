<template>
	<div id="mwe-vue-pt-menu-heading" class="mwe-vue-pt-control-gradient">
		<p v-if="haveDraftNamespace">
			<queue-mode-radio></queue-mode-radio>
		</p>
		<showing-text></showing-text>
		<span v-show="settings.currentFilteredCount !== -1" class="mwe-vue-pt-control-label-right">
			{{ $i18n( 'pagetriage-stats-filter-page-count', settings.currentFilteredCount ).text() }}
		</span>
		<br>
		<span v-show="settings.immediate.queueMode === 'npp'" class="mwe-vue-pt-control-label-right">
			<npp-sort-dir-radio></npp-sort-dir-radio>
		</span>
		<span v-show="settings.immediate.queueMode === 'afc'" class="mwe-vue-pt-control-label-right">
			<afc-sort-select></afc-sort-select>
		</span>
		<div id="mwe-vue-pt-control-menu-toggle">
			<b @click="toggleControlMenu">{{ $i18n( 'pagetriage-filter-set-button' ).text() }} {{ settings.controlMenuOpen ? '▾' : '▸' }}</b>
			<!-- Dropdown goes within the toggle with absolute position to overlay the feed -->
			<div
				v-if="settings.controlMenuOpen"
				id="mwe-vue-pt-control-dropdown"
				class="mwe-vue-pt-control-gradient"
			>
				<div v-show="settings.immediate.queueMode === 'npp'" class="mwe-vue-pt-control-section__row1">
					<div class="mwe-vue-pt-control-section__col1">
						<control-section label-msg="pagetriage-filter-namespace-heading">
							<select v-model="settings.unsaved.nppNamespace">
								<option
									v-for="( namespace, i ) in namespaceOptions"
									:key="`pagetriage-filter-namespace-${i}`"
									:value="i"
								>
									{{ namespace }}
								</option>
							</select>
						</control-section>
						<control-section label-msg="pagetriage-filter-show-heading">
							<labeled-checkbox
								v-model:checked="settings.unsaved.nppIncludeUnreviewed"
								label-msg="pagetriage-filter-unreviewed-edits"
							></labeled-checkbox>
							<labeled-checkbox
								v-model:checked="settings.unsaved.nppIncludeReviewed"
								label-msg="pagetriage-filter-reviewed-edits"
							></labeled-checkbox>
						</control-section>
						<control-section label-msg="pagetriage-filter-type-show-heading">
							<labeled-checkbox
								v-model:checked="settings.unsaved.nppIncludeNominated"
								label-msg="pagetriage-filter-nominated-for-deletion"
							></labeled-checkbox>
							<labeled-checkbox
								v-model:checked="settings.unsaved.nppIncludeRedirects"
								label-msg="pagetriage-filter-redirects"
							></labeled-checkbox>
							<labeled-checkbox
								v-model:checked="settings.unsaved.nppIncludeOthers"
								label-msg="pagetriage-filter-others"
							></labeled-checkbox>
						</control-section>
						<date-control-section
							v-model:from="settings.unsaved.nppDateFrom"
							v-model:to="settings.unsaved.nppDateTo"
							type="npp"
						></date-control-section>
					</div>
					<control-section label-msg="pagetriage-filter-second-show-heading">
						<npp-filter-radio
							v-model:filter="settings.unsaved.nppFilter"
							v-model:user="settings.unsaved.nppFilterUser"
						>
						</npp-filter-radio>
					</control-section>
					<div v-if="showOresFilters" class="mwe-vue-pt-control-section__col2">
						<control-section label-msg="pagetriage-filter-predicted-class-heading">
							<labeled-checkbox
								v-for="( _, rating ) in settings.unsaved.nppPredictedRating"
								:key="`${rating}-${settings.unsaved.nppPredictedRating[ rating ]}`"
								v-model:checked="settings.unsaved.nppPredictedRating[ rating ]"
								:label-msg="'pagetriage-filter-predicted-class-' + rating"
							>
							</labeled-checkbox>
						</control-section>
						<control-section label-msg="pagetriage-filter-predicted-issues-heading">
							<labeled-checkbox
								v-for="( _, issue ) in settings.unsaved.nppPossibleIssues"
								:key="`${issue}-${settings.unsaved.nppPossibleIssues[ issue ]}`"
								v-model:checked="settings.unsaved.nppPossibleIssues[ issue ]"
								:label-msg="'pagetriage-filter-predicted-issues-' + issue"
							>
							</labeled-checkbox>
						</control-section>
					</div>
				</div>
				<div v-show="settings.immediate.queueMode === 'afc'" class="mwe-vue-pt-control-section__row1">
					<div class="mwe-vue-pt-control-section__col1">
						<control-section label-msg="pagetriage-filter-show-heading">
							<afc-state-radio v-model:state="settings.unsaved.afcSubmissionState"
							></afc-state-radio>
						</control-section>
						<date-control-section
							v-if="showOresFilters"
							v-model:from="settings.unsaved.afcDateFrom"
							v-model:to="settings.unsaved.afcDateTo"
							type="afc"
						></date-control-section>
					</div>
					<template v-if="showOresFilters">
						<div class="mwe-vue-pt-control-section__col2">
							<control-section label-msg="pagetriage-filter-predicted-class-heading">
								<labeled-checkbox
									v-for="( _, rating ) in settings.unsaved.afcPredictedRating"
									:key="`${rating}-${settings.unsaved.afcPredictedRating[ rating ]}`"
									v-model:checked="settings.unsaved.afcPredictedRating[ rating ]"
									:label-msg="'pagetriage-filter-predicted-class-' + rating"
								>
								</labeled-checkbox>
							</control-section>
						</div>
						<div class="mwe-vue-pt-control-section__col3">
							<control-section label-msg="pagetriage-filter-predicted-issues-heading">
								<labeled-checkbox
									v-for="( _, issue ) in settings.unsaved.afcPossibleIssues"
									:key="`${issue}-${settings.unsaved.afcPossibleIssues[ issue ]}`"
									v-model:checked="settings.unsaved.afcPossibleIssues[ issue ]"
									:label-msg="'pagetriage-filter-predicted-issues-' + issue"
								>
								</labeled-checkbox>
							</control-section>
						</div>
					</template>
					<div v-else class="mwe-vue-pt-control-section__col2">
						<date-control-section
							v-model:from="settings.unsaved.afcDateFrom"
							v-model:to="settings.unsaved.afcDateTo"
							type="afc"
						></date-control-section>
					</div>
				</div>

				<div class="mwe-vue-pt-control-buttons">
					<cdx-button
						action="progressive"
						type="primary"
						:disabled="!canSaveSettings"
						@click="doSaveSettings"
					>
						{{ $i18n( 'pagetriage-filter-set-button' ).text() }}
					</cdx-button>
					<cdx-button
						action="destructive"
						type="primary"
						@click="settings.reset"
					>
						{{ $i18n( 'pagetriage-filter-reset-button' ).text() }}
					</cdx-button>
				</div>
			</div>
		</div>
	</div>
</template>

<script>
/**
 * @author DannyS712
 * Controls for filtering feed content
 */

const { computed } = require( 'vue' );
const ControlSection = require( './ControlSection.vue' );
const DateControlSection = require( './DateControlSection.vue' );
const LabeledCheckbox = require( './LabeledCheckbox.vue' );
const QueueModeRadio = require( './QueueModeRadio.vue' );
const AfcSortSelect = require( './AfcSortSelect.vue' );
const AfcStateRadio = require( './AfcStateRadio.vue' );
const NppSortDirRadio = require( './NppSortDirRadio.vue' );
const NppFilterRadio = require( './NppFilterRadio.vue' );
const ShowingText = require( './ShowingText.vue' );
const { CdxButton } = require( '@wikimedia/codex' );
const { useSettingsStore } = require( 'ext.pageTriage.util' );
const { getNamespaceOptions } = require( '../namespaces.js' );
// @vue/component
module.exports = {
	configureCompat: {
		MODE: 3
	},
	compilerOptions: {
		whitespace: 'condense'
	},
	name: 'ListFilterMenu',
	components: {
		ControlSection,
		DateControlSection,
		LabeledCheckbox,
		QueueModeRadio,
		NppSortDirRadio,
		NppFilterRadio,
		AfcSortSelect,
		AfcStateRadio,
		ShowingText,
		CdxButton
	},
	setup() {
		const settings = useSettingsStore();
		settings.$subscribe( ( _mutation, state ) => {
			// persist most state to local storage whenever it changes
			const filter = ( key, value ) => {
				// Don't store the control menu open/closed state
				if ( key === 'controlMenuOpen' ) {
					return undefined;
				} else {
					return value;
				}
			};
			localStorage.setItem( 'ext.pageTriage.settings', JSON.stringify( state, filter ) );
		} );
		// Need to include at least one of reviewed/unreviewed, and at least
		// one of nominated for deletion/redirects/normal articles
		const canSaveSettings = computed( () => {
			return (
				( settings.unsaved.nppIncludeUnreviewed || settings.unsaved.nppIncludeReviewed ) &&
				(
					settings.unsaved.nppIncludeNominated ||
					settings.unsaved.nppIncludeRedirects ||
					settings.unsaved.nppIncludeOthers
				)
			);
		} );
		const doSaveSettings = function () {
			settings.update( settings.unsaved );
			settings.controlMenuOpen = false;
		};
		const toggleControlMenu = () => {
			settings.controlMenuOpen = !settings.controlMenuOpen;
		};
		return {
			// settings
			settings,
			// housekeeping
			toggleControlMenu,
			canSaveSettings,
			doSaveSettings
		};
	},
	data: function () {
		return {
			// pure data, not needed in setup()
			haveDraftNamespace: !!mw.config.get( 'wgPageTriageDraftNamespaceId', true ),
			showOresFilters: mw.config.get( 'wgShowOresFilters', true ),
			namespaceOptions: getNamespaceOptions()
		};
	}
};
</script>

<style lang="less">
@import 'mediawiki.skin.variables.less';
#mwe-vue-pt-menu-heading {
	padding: 0.5em 1em 1em 1em;
	top: 0;
	z-index: 10;
}
.mwe-vue-pt-control-options {
	margin-left: 0.5em;
}
.mwe-vue-pt-control-label {
	white-space: nowrap;
	padding: 3px;
}
.mwe-vue-pt-control-label-right {
	float: right;
}
.mwe-vue-pt-control-section {
	min-width: 0;
	padding-bottom: 3px;
}
.mwe-vue-pt-control-buttons {
	margin: 0.2em 0 0 -0.4em;
}
#mwe-vue-pt-control-dropdown {
	position: absolute;
	z-index: 50;
	border: 1px solid #aaa;
	padding: 0.5em 1em;
	margin-left: 48px;
	color: #000;
	cursor: default;
	box-shadow: 0 7px 10px rgba( 0, 0, 0, 0.4 );
	width: min-content;
}
#mwe-vue-pt-control-dropdown fieldset {
	padding-top: 0;
	padding-bottom: 0;
	padding-left: 12px;
	margin-top: 3px;
	margin-bottom: 3px;
}
#mwe-vue-pt-control-menu-toggle {
	color: #0645ad;
	cursor: pointer;
}
.mwe-vue-pt-control-section__row1 {
	display: flex;
	flex-direction: row;
}
.mwe-vue-pt-control-options .cdx-radio {
	margin-bottom: 3px;
}
.mwe-vue-pt-control-options .cdx-radio input.cdx-radio__input {
	margin: 0;
	min-width: min-content;
	min-height: unset;
	width: 1em;
	height: 1em;
}
.mwe-vue-pt-control-options .cdx-radio span.cdx-radio__icon {
	min-width: unset;
	min-height: unset;
	width: 16px;
	height: 16px;
	margin: 1px;
	padding: 1px;
	border-width: 1px;
}
.mwe-vue-pt-control-options .cdx-radio input.cdx-radio__input:enabled:checked+.cdx-radio__icon {
	width: 16px;
	height: 16px;
	margin: 1px;
	padding: 1px;
	border-width: 1px;
	background-color: @color-progressive;
	border-color: @color-base--subtle;
	background-clip: content-box;
}
</style>

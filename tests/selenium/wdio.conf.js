import { config as wdioDefaults } from 'wdio-mediawiki/wdio-defaults.conf.js';
import LocalSettingsSetup from './LocalSettingsSetup.cjs';

export const config = { ...wdioDefaults,
	// Override, or add to, the setting from wdio-mediawiki.
	// Learn more at https://webdriver.io/docs/configurationfile/
	// To enable video recording, enable video and disable browser headless
	// recordVideo: true,
	// useBrowserHeadless: false,
	//
	// To enable screenshots on all tests, disable screenshotsOnFailureOnly
	// screenshotsOnFailureOnly: false,
	onPrepare: async function () {
		await LocalSettingsSetup.overrideLocalSettings();
		await LocalSettingsSetup.restartPhpFpmService();
	},
	onComplete: async function () {
		await LocalSettingsSetup.restoreLocalSettings();
		await LocalSettingsSetup.restartPhpFpmService();
	}
};

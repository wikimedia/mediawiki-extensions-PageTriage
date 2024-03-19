module.exports = {
	moduleNameMapper: {
		// backbone needs this defined here because of the way it checks for jquery & underscore
		underscore: '<rootDir>/modules/external/underscore.js',
		'ext.pageTriage.defaultTagsOptions$': '<rootDir>/modules/ext.pageTriage.defaultTagsOptions/main.js',
		'ext.pageTriage.util': '<rootDir>/modules/ext.pageTriage.util/main.js',
		// @TODO: map virtual files with full path
		'./icons.json': '<rootDir>/tests/mocks/icons.json',
		'./config.json': '<rootDir>/tests/mocks/config.json',
		'./contentLanguageMessages.json': '<rootDir>/tests/mocks/contentLanguageMessages.json',
		'../../../external/jquery.badge.js': '<rootDir>/modules/external/jquery.badge.js',
		// backbone needs this defined here because of the way it checks for jquery & underscore
		'^./modules/(.+)/ext.pageTriage.(.+).underscore': '<rootDir>/modules/$1/$2.underscore'
	},
	clearMocks: true,
	collectCoverageFrom: [
		'modules/**/*.(js|vue)'
	],
	coveragePathIgnorePatterns: [
		'/modules/external/',
		'/node_modules/'
	],
	coverageThreshold: {
		global: {
			branches: 4,
			functions: 6,
			lines: 6,
			statements: 6
		}
	},
	testEnvironment: 'jsdom',
	transform: {
		'^.+\\.vue$': '@vue/vue3-jest',
		'^.+\\js$': 'babel-jest'
	},
	testRegex: '(/__tests__/.*|(\\.|/)(test|spec))\\.(js|ts)$',
	moduleFileExtensions: [ 'js', 'vue' ],
	coverageReporters: [ 'text', 'json-summary', 'lcov', 'clover' ],
	testEnvironmentOptions: {
		url: 'http://localhost:8080',
		customExportConditions: [
			'node',
			'node-addons'
		]
	},
	setupFilesAfterEnv: [
		'<rootDir>/jest.setup.js'
	]
};

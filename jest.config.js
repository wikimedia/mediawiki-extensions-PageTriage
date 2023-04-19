module.exports = {
	moduleNameMapper: {
		'^./modules/(.+)/ext.pageTriage.(.+).underscore': '<rootDir>/modules/$1/$2.underscore',
		// backbone needs this defined here because of the way it checks for jquery & underscore
		underscore: '<rootDir>/modules/external/underscore.js'
	},
	testEnvironment: 'jsdom',
	setupFilesAfterEnv: [
		'<rootDir>/jest.setup.js'
	]
};

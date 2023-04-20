module.exports = {
	methods: {
		$i18n: jest.fn( ( msg ) => {
			return { text: () => msg };
		} )
	}
};

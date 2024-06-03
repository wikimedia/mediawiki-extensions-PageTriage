module.exports = {
	methods: {
		$i18n: jest.fn( ( msg ) => ( { text: () => msg } ) )
	}
};

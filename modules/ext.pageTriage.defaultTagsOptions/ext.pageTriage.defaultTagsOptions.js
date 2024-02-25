// See https://www.mediawiki.org/wiki/Extension:PageTriage#On-wiki_configuration for basic
// documentation on configuration.
( function () {
	$.pageTriageTagsRedirectCategoryShell = 'Redirect category shell';

	const today = new Date();

	const month = [ 'January', 'February', 'March', 'April', 'May', 'June', 'July',
		'August', 'September', 'October', 'November', 'December' ];

	const param = {
		date: {
			label: mw.msg( 'pagetriage-tags-param-date-label' ),
			input: 'automated',
			type: 'hidden',
			value: mw.msg(
				'pagetriage-tags-param-date-format',
				month[ today.getUTCMonth() ],
				today.getUTCFullYear()
			)
		},

		1: {
			label: '1:',
			input: 'automated',
			type: 'hidden',
			value: ''
		},

		for: {
			label: 'This article may require copy editing for:',
			input: 'optional',
			type: 'textarea',
			value: ''
		},

		blp: {
			label: 'This is a biography of a living person',
			input: 'optional',
			type: 'checkbox',
			value: ''
		},

		reason: {
			label: 'Describe issues to be addressed:',
			input: 'required',
			type: 'textarea',
			value: ''
		},

		details: {
			label: 'Describe issues to be addressed:',
			input: 'optional',
			type: 'textarea',
			value: ''
		},

		source: {
			label: 'Please add a URL for that source:',
			input: 'required',
			type: 'text',
			value: ''
		},

		free: {
			label: 'This content is freely licensed.',
			input: 'optional',
			type: 'checkbox',
			value: ''
		},

		url: {
			label: 'Please add a URL for that source.',
			input: 'required',
			type: 'text',
			value: ''
		},

		start: {
			label: 'The oldid number of the diff wherein the copyright violation was added.',
			input: 'optional',
			type: 'text',
			value: ''
		},

		end: {
			label: 'The oldid number of the diff wherein the copyright violation was last visble.',
			input: 'optional',
			type: 'text',
			value: ''
		},

		start2: {
			label: 'Same as second parameter; usable for a second range of diffs.',
			input: 'optional',
			type: 'text',
			value: ''
		},

		end2: {
			label: 'Same as third parameter; usable for a second range of diffs.',
			input: 'optional',
			type: 'text',
			value: ''
		},

		CopyPatrol: {
			label: 'Copypatrol URL of the flagging.',
			input: 'optional',
			type: 'text',
			value: ''
		},

		originalpage: {
			label: 'The page from where the content was copied.',
			input: 'required',
			type: 'text',
			value: ''
		}
	};

	$.pageTriageTagsMultiple = 'Multiple issues';

	/*
	 * "Tags" are items that appear in the Page Curation toolbar "Add tags" flyout menu.
	 *
	 * @param {string} label - The name of the tag shown in the Page Curation toolbar "Add tags"
	 * flyout menu.
	 * @param {string} tag - The name of the template to be written to the page.
	 * @param {string} desc - The detailed description of the tag shown in the Page Curation
	 * toolbar "Add tags" flyout menu.
	 * @param {Object} params - Data to include as template parameters. May also be related to
	 * collecting data from the user via the "Add details" link.
	 * @param {string} position - top, bottom, categories, redirectTag
	 * @param {string} dest - If this is a duplicate tag, such as a tag in the "all" or "common"
	 * menus, in what menu is the "main" tag located? This is used to automatically tick the
	 * boxes for both tags, prevents placing two of the same tag, and causes the tag count to be
	 * applied to the other menu instead of the "all" or "common" menus.
	 * @param {boolean} multiple - If there are multiple tags being placed, include this tag in
	 * the {{Multiple issues}} tag?
	 */
	$.pageTriageTagsOptions = {
		all: {},

		common: {
			label: 'Common',
			alias: true,
			tags: {
				// The tag MUST also exist in a subsection, or the tag will not work.
				// The tag MUST have a "dest" set. This should be equal to the name of the menu that
				// the other tag is located in.
				// TODO: these are all duplicates of other entries in this list. this should be refactored to
				// avoid duplication.
				// TODO: refactoring strategy: this should just be an array of tags, e.g. common: ['underreview',
				// 'linkrot', '...']. Then JS code somewhere else handles the duplicating.
				underreview: {
					label: 'Under review',
					tag: 'Under review',
					desc: 'You intend to assess the article but it will take some time. Tag to avoid patrolling redundancy.',
					params: {
						date: param.date
					},
					position: 'top',
					dest: 'moretags',
					multiple: true
				},

				linkrot: {
					label: 'Bare URLs',
					tag: 'linkrot',
					desc: 'This page uses bare URLs for references, which are prone to link rot.',
					params: {
						date: param.date
					},
					position: 'top',
					dest: 'sources',
					multiple: true
				},

				copyedit: {
					label: 'Copy edit',
					tag: 'copy edit',
					desc: 'This page needs copy editing for grammar, style, cohesion, tone, and/or spelling.',
					params: {
						date: param.date,
						for: $.extend( true, {}, param.for )
					},
					position: 'top',
					dest: 'cleanup',
					multiple: true
				},

				morefootnotes: {
					label: 'More footnotes',
					tag: 'more footnotes',
					desc: 'This page has some references, but insufficient in-text citations.',
					params: {
						date: param.date,
						blp: $.extend( true, {}, param.blp )
					},
					position: 'top',
					dest: 'sources',
					multiple: true
				},

				refimprove: {
					label: 'More references',
					tag: 'refimprove',
					desc: 'This page needs additional references or sources for verification.',
					params: {
						date: param.date
					},
					position: 'top',
					dest: 'sources',
					multiple: true
				},

				unreferenced: {
					label: 'No references',
					tag: 'unreferenced',
					desc: 'This page has no references at all.',
					params: {
						date: param.date
					},
					position: 'top',
					dest: 'sources',
					multiple: true
				},

				stub: {
					label: 'Stub',
					tag: 'stub',
					desc: 'This page is very short.',
					params: { },
					position: 'bottom',
					dest: 'structure',
					multiple: false
				},

				uncategorised: {
					label: 'Uncategorised',
					tag: 'uncategorised',
					desc: 'This page doesn\'t belong to any categories.',
					params: {
						date: param.date
					},
					position: 'categories',
					dest: 'metadata',
					multiple: false
				},

				orphan: {
					label: 'Orphan',
					tag: 'orphan',
					desc: 'No other pages link to this page.',
					params: {
						date: param.date
					},
					position: 'top',
					dest: 'metadata',
					multiple: true
				}
			}
		},

		cleanup: {
			label: 'Cleanup',
			tags: {
				cleanup: {
					label: 'Cleanup',
					tag: 'cleanup',
					desc: 'This page requires cleanup.',
					params: {
						date: param.date,
						reason: $.extend( true, {}, param.reason )
					},
					position: 'top',
					multiple: true
				},

				copyedit: {
					label: 'Copy edit',
					tag: 'copy edit',
					desc: 'This page needs copy editing for grammar, style, cohesion, tone, and/or spelling.',
					params: {
						date: param.date,
						for: $.extend( true, {}, param.for )
					},
					position: 'top',
					multiple: true
				},

				translation: {
					label: 'Needs translation',
					tag: 'not English',
					desc: 'This page is written in a language other than English and needs translation.',
					params: {
						date: param.date
					},
					position: 'top',
					multiple: true
				},

				prose: {
					label: 'Prose',
					tag: 'prose',
					desc: 'This page is in a list format that is better presented using prose.',
					params: {
						date: param.date
					},
					position: 'top',
					multiple: true
				},

				roughtranslation: {
					label: 'Rough translation',
					tag: 'rough translation',
					desc: 'This page is poorly translated and must be updated.',
					params: { },
					position: 'top',
					multiple: false
				}
			}
		},

		metadata: {
			label: 'Metadata',
			tags: {
				deadend: {
					label: 'Dead end',
					tag: 'dead end',
					desc: 'This page has no links to other pages.',
					params: {
						date: param.date
					},
					position: 'top',
					multiple: true
				},

				externallinks: {
					label: 'External links',
					tag: 'external links',
					desc: 'This page\'s external links do not follow content policies or guidelines.',
					params: {
						date: param.date
					},
					position: 'top',
					multiple: true
				},

				orphan: {
					label: 'Orphan',
					tag: 'orphan',
					desc: 'No other pages link to this page.',
					params: {
						date: param.date
					},
					position: 'top',
					multiple: true
				},

				overlinked: {
					label: 'Too many links',
					tag: 'overlinked',
					desc: 'This page has too many duplicate and/or irrelevant links.',
					params: {
						date: param.date
					},
					position: 'top',
					multiple: true
				},

				morecategories: {
					label: 'Improve categories',
					tag: 'improve categories',
					desc: 'This page may require additional categories.',
					params: {
						date: param.date
					},
					position: 'categories',
					multiple: false
				},

				uncategorised: {
					label: 'Uncategorised',
					tag: 'uncategorised',
					desc: 'This page doesn\'t belong to any categories.',
					params: {
						date: param.date
					},
					position: 'categories',
					multiple: false
				},

				historymerge: {
					label: 'History Merge',
					tag: 'Histmerge',
					desc: 'A cut-and-paste move has occured resulting in loss of attribution.',
					params: {
						date: param.date,
						originalpage: $.extend( true, {}, param.originalpage )
					},
					position: 'top',
					multiple: false
				},

				underlinked: {
					label: 'Underlinked',
					tag: 'underlinked',
					desc: 'This page may require additional wikilinks',
					params: {
						date: param.date
					},
					position: 'top',
					multiple: true
				}
			}
		},

		neutrality: {
			label: 'Neutrality',
			tags: {
				autobiography: {
					label: 'Autobiography',
					tag: 'autobiography',
					desc: 'This page is an autobiography and is not written neutrally.',
					params: {
						date: param.date
					},
					position: 'top',
					multiple: true
				},

				coi: {
					label: 'Conflict of interest',
					tag: 'coi',
					desc: 'This page creator or major contributor may have a conflict of interest.',
					params: {
						date: param.date
					},
					position: 'top',
					multiple: true
				},

				globalize: {
					label: 'Globalize',
					tag: 'globalize',
					desc: 'This page may not represent a worldwide view of the subject.',
					params: {
						date: param.date
					},
					position: 'top',
					multiple: true
				},

				overcoverage: {
					label: 'Overcoverage',
					tag: 'overcoverage',
					desc: 'This page has extensive bias and/or disproportional coverage towards one or more specific regions.',
					params: {
						date: param.date
					},
					position: 'top',
					multiple: true
				},

				peacock: {
					label: 'Peacock',
					tag: 'peacock',
					desc: 'This page contains peacock terms that promote the subject without adding information.',
					params: {
						date: param.date
					},
					position: 'top',
					multiple: true
				},

				pov: {
					label: 'POV',
					tag: 'pov',
					desc: 'This page does not maintain a neutral point of view.',
					params: {
						date: param.date
					},
					position: 'top',
					multiple: true
				},

				recentism: {
					label: 'Recent event slant',
					tag: 'recentism',
					desc: 'This page is slanted towards recent events.',
					params: {
						date: param.date
					},
					position: 'top',
					multiple: true
				},

				toofewopinions: {
					label: 'Too few viewpoints',
					tag: 'too few opinions',
					desc: 'This page does not include all significant viewpoints.',
					params: {
						date: param.date
					},
					position: 'top',
					multiple: false
				},

				undue: {
					label: 'Undue weight',
					tag: 'undue weight',
					desc: 'This page lends undue weight to certain aspects of the subject but not others.',
					params: {
						date: param.date
					},
					position: 'top',
					multiple: true
				},

				weasel: {
					label: 'Weasel',
					tag: 'weasel',
					desc: 'This page\'s neutrality is compromised by the use of weasel words.',
					params: {
						date: param.date
					},
					position: 'top',
					multiple: true
				}
			}
		},

		notability: {
			label: 'Notability',
			tags: {
				notability: {
					label: 'General',
					tag: 'notability',
					desc: 'The page\'s subject may not meet the general notability guideline.',
					params: {
						date: param.date
					},
					position: 'top',
					multiple: true
				},

				notabilityAcademics: {
					label: 'Academic',
					tag: 'notability',
					desc: 'The page\'s subject may not meet the notability guideline for academics.',
					params: {
						1: $.extend( {}, param[ '1' ], { value: 'Academics' } ),
						date: param.date
					},
					position: 'top',
					multiple: true
				},

				notabilityBiographies: {
					label: 'Biographies',
					tag: 'notability',
					desc: 'The page\'s subject may not meet the notability guideline for biographies.',
					params: {
						1: $.extend( {}, param[ '1' ], { value: 'Biographies' } ),
						date: param.date
					},
					position: 'top',
					multiple: true
				},

				notabilityBooks: {
					label: 'Books',
					tag: 'notability',
					desc: 'The page\'s subject may not meet the notability guideline for books.',
					params: {
						1: $.extend( {}, param[ '1' ], { value: 'Books' } ),
						date: param.date
					},
					position: 'top',
					multiple: true
				},

				notabilityCompanies: {
					label: 'Companies',
					tag: 'notability',
					desc: 'The page\'s subject may not meet the notability guideline for companies.',
					params: {
						1: $.extend( {}, param[ '1' ], { value: 'Companies' } ),
						date: param.date
					},
					position: 'top',
					multiple: true
				},

				notabilityEvents: {
					label: 'Events',
					tag: 'notability',
					desc: 'The page\'s subject may not meet the notability guideline for events.',
					params: {
						1: $.extend( {}, param[ '1' ], { value: 'Events' } ),
						date: param.date
					},
					position: 'top',
					multiple: true
				},

				notabilityFilms: {
					label: 'Films',
					tag: 'notability',
					desc: 'The page\'s subject may not meet the notability guideline for films.',
					params: {
						1: $.extend( {}, param[ '1' ], { value: 'Films' } ),
						date: param.date
					},
					position: 'top',
					multiple: true
				},

				notabilityMusic: {
					label: 'Music',
					tag: 'notability',
					desc: 'The page\'s subject may not meet the notability guideline for music.',
					params: {
						1: $.extend( {}, param[ '1' ], { value: 'Music' } ),
						date: param.date
					},
					position: 'top',
					multiple: true
				},

				notabilityPlaces: {
					label: 'Places',
					tag: 'notability',
					desc: 'The page\'s subject may not meet the notability guideline for places.',
					params: {
						1: $.extend( {}, param[ '1' ], { value: 'Places' } ),
						date: param.date
					},
					position: 'top',
					multiple: true
				},

				notabilityNeologisms: {
					label: 'Neologisms',
					tag: 'notability',
					desc: 'The page\'s subject may not meet the notability guideline for neologisms.',
					params: {
						1: $.extend( {}, param[ '1' ], { value: 'Neologisms' } ),
						date: param.date
					},
					position: 'top',
					multiple: true
				},

				notabilityNumbers: {
					label: 'Numbers',
					tag: 'notability',
					desc: 'The page\'s subject may not meet the notability guideline for numbers.',
					params: {
						1: $.extend( {}, param[ '1' ], { value: 'Numbers' } ),
						date: param.date
					},
					position: 'top',
					multiple: true
				},

				notabilityProducts: {
					label: 'Products',
					tag: 'notability',
					desc: 'The page\'s subject may not meet the notability guideline for products and services.',
					params: {
						1: $.extend( {}, param[ '1' ], { value: 'Products' } ),
						date: param.date
					},
					position: 'top',
					multiple: true
				},

				notabilitySport: {
					label: 'Sports',
					tag: 'notability',
					desc: 'The page\'s subject may not meet the notability guideline for sports and athletics.',
					params: {
						1: $.extend( {}, param[ '1' ], { value: 'Sport' } ),
						date: param.date
					},
					position: 'top',
					multiple: true
				},

				notabilityWeb: {
					label: 'Web content',
					tag: 'notability',
					desc: 'The page\'s subject may not meet the notability guideline for web content.',
					params: {
						1: $.extend( {}, param[ '1' ], { value: 'Web' } ),
						date: param.date
					},
					position: 'top',
					multiple: true
				}
			}
		},

		sources: {
			label: 'Sources',
			tags: {
				sourcesexist: {
					label: 'Sources Exist',
					tag: 'Sources exist',
					desc: 'The subject has enough sources to demonstrate notability but they are not present in the page.',
					params: {
						date: param.date
					},
					position: 'top',
					multiple: true
				},

				disputed: {
					label: 'Accuracy issues',
					tag: 'disputed',
					desc: 'This page has questionable factual accuracy.',
					params: {
						date: param.date
					},
					position: 'top',
					multiple: true
				},

				linkrot: {
					label: 'Bare URLs',
					tag: 'linkrot',
					desc: 'This page uses bare URLs for references, which are prone to link rot.',
					params: {
						date: param.date
					},
					position: 'top',
					multiple: true
				},

				citationstyle: {
					label: 'Citation style',
					tag: 'citation style',
					desc: 'This page has unclear or inconsistent inline citations.',
					params: {
						date: param.date,
						details: $.extend( true, {}, param.details )
					},
					position: 'top',
					multiple: true
				},

				hoax: {
					label: 'Hoax',
					tag: 'hoax',
					desc: 'This page may be a complete hoax.',
					params: {
						date: param.date
					},
					position: 'top',
					multiple: true
				},

				morefootnotes: {
					label: 'More footnotes',
					tag: 'more footnotes',
					desc: 'This page has some references, but insufficient in-text citations.',
					params: {
						date: param.date,
						blp: $.extend( true, {}, param.blp )
					},
					position: 'top',
					multiple: true
				},

				refimprove: {
					label: 'More references',
					tag: 'refimprove',
					desc: 'This page needs additional references or sources for verification.',
					params: {
						date: param.date
					},
					position: 'top',
					multiple: true
				},

				blpsources: {
					label: 'More sources (BLP)',
					tag: 'BLP sources',
					desc: 'This page about a living person (BLP) needs additional sources citations for verification.',
					params: {
						date: param.date
					},
					position: 'top',
					multiple: true
				},

				nofootnotes: {
					label: 'No footnotes',
					tag: 'no footnotes',
					desc: 'This page has references, but no in-text citations.',
					params: {
						date: param.date
					},
					position: 'top',
					multiple: true
				},

				unreferenced: {
					label: 'No references',
					tag: 'unreferenced',
					desc: 'This page has no references at all.',
					params: {
						date: param.date
					},
					position: 'top',
					multiple: true
				},

				originalresearch: {
					label: 'Original research',
					tag: 'original research',
					desc: 'This page has original research or unverified claims.',
					params: {
						date: param.date
					},
					position: 'top',
					multiple: true
				},

				primarysources: {
					label: 'Primary sources',
					tag: 'primary sources',
					desc: 'This page relies too heavily on primary sources, and needs secondary sources.',
					params: {
						date: param.date,
						blp: $.extend( true, {}, param.blp )
					},
					position: 'top',
					multiple: true
				},

				selfpublished: {
					label: 'Self-published sources',
					tag: 'Self-published',
					desc: 'This page may contain improper references to self-published sources.',
					params: {
						date: param.date
					},
					position: 'top',
					multiple: true
				},

				onesource: {
					label: 'Single source',
					tag: 'one source',
					desc: 'This page relies largely or entirely upon a single source.',
					params: {
						date: param.date
					},
					position: 'top',
					multiple: true
				},

				thirdparty: {
					label: 'Third-party sources',
					tag: 'Third-party',
					desc: 'This page relies too heavily on affiliated sources, and needs third-party sources.',
					params: {
						date: param.date
					},
					position: 'top',
					multiple: true
				},

				unreliable: {
					label: 'Unreliable sources',
					tag: 'unreliable sources',
					desc: 'This page\'s references may not be reliable sources.',
					params: {
						date: param.date
					},
					position: 'top',
					multiple: true
				}
			}
		},

		structure: {
			label: 'Structure',
			tags: {
				condense: {
					label: 'Condense',
					tag: 'condense',
					desc: 'This page has too many section headers dividing up its content.',
					params: { },
					position: 'top',
					multiple: true
				},

				leadmissing: {
					label: 'Lead missing',
					tag: 'lead missing',
					desc: 'This page has no lead section and one should be written.',
					params: {
						date: param.date
					},
					position: 'top',
					multiple: true
				},

				leadrewrite: {
					label: 'Lead rewrite',
					tag: 'lead rewrite',
					desc: 'This page\'s lead section needs to be rewritten to comply with guidelines.',
					params: {
						date: param.date,
						reason: $.extend( true, {}, param.reason )
					},
					position: 'top',
					multiple: true
				},

				leadtoolong: {
					label: 'Lead too long',
					tag: 'lead too long',
					desc: 'This page\'s lead section is too long and should be shortened.',
					params: {
						date: param.date
					},
					position: 'top',
					multiple: true
				},

				leadtooshort: {
					label: 'Lead too short',
					tag: 'lead too short',
					desc: 'This page\'s lead section is too short and should be expanded.',
					params: {
						date: param.date
					},
					position: 'top',
					multiple: true
				},

				cleanupreorganise: {
					label: 'Reorganise',
					tag: 'cleanup-reorganize',
					desc: 'This page is in need of reorganization to comply with layout guidelines.',
					params: {
						date: param.date
					},
					position: 'top',
					multiple: true
				},

				sections: {
					label: 'Sections',
					tag: 'sections',
					desc: 'This page needs to be broken into sections.',
					params: {
						date: param.date
					},
					position: 'top',
					multiple: true
				},

				stub: {
					label: 'Stub',
					tag: 'stub',
					desc: 'This page is very short.',
					params: { },
					position: 'bottom',
					multiple: false
				},

				verylong: {
					label: 'Very long',
					tag: 'very long',
					desc: 'This page is too long.',
					params: {
						date: param.date
					},
					position: 'top',
					multiple: true
				}
			}
		},

		copyrightviolation: {
			label: 'Copyright violation',
			tags: {
				closeparaphrasing: {
					label: 'Close paraphrasing',
					tag: 'close paraphrasing',
					desc: 'This page contains close paraphrasing of a copyrighted source.',
					params: {
						date: param.date,
						source: $.extend( true, {}, param.source ),
						free: $.extend( true, {}, param.free )
					},
					position: 'top',
					multiple: false
				},

				copypaste: {
					label: 'Copy and paste',
					tag: 'copypaste',
					desc: 'This page appears to have been copied and pasted from a source.',
					params: {
						date: param.date,
						url: $.extend( true, {}, param.url )
					},
					position: 'top',
					multiple: false
				},

				nonfree: {
					label: 'Copyrighted content',
					tag: 'non-free',
					desc: 'This page contains excessive or improper use of copyrighted materials.',
					params: { },
					position: 'top',
					multiple: false
				},

				Copyviorevdel: {
					label: 'Revision Deletion',
					tag: 'Copyvio-revdel',
					desc: 'Certain revisions of the page violates copyright policies.',
					params: {
						date: param.date,
						url: $.extend( true, {}, param.url ),
						start: $.extend( true, {}, param.start ),
						end: $.extend( true, {}, param.end ),
						start2: $.extend( true, {}, param.start2 ),
						end2: $.extend( true, {}, param.end2 ),
						CopyPatrol: $.extend( true, {}, param.CopyPatrol )
					},
					position: 'top',
					multiple: true
				}
			}
		},

		writingstyle: {
			label: 'Writing style',
			tags: {
				advert: {
					label: 'Advertisement',
					tag: 'advert',
					desc: 'This page is written like an advertisement.',
					params: {
						date: param.date
					},
					position: 'top',
					multiple: true
				},

				confusing: {
					label: 'Confusing',
					tag: 'confusing',
					desc: 'This page is confusing or unclear.',
					params: {
						date: param.date,
						reason: $.extend( true, {}, param.reason )
					},
					position: 'top',
					multiple: true
				},

				essaylike: {
					label: 'Essay',
					tag: 'essay-like',
					desc: 'This page is written like an essay and needs cleanup.',
					params: {
						date: param.date
					},
					position: 'top',
					multiple: true
				},

				fansite: {
					label: 'Fan site',
					tag: 'fansite',
					desc: 'This page resembles a fansite.',
					params: {
						date: param.date
					},
					position: 'top',
					multiple: true
				},

				incomprehensible: {
					label: 'Incomprehensible',
					tag: 'incomprehensible',
					desc: 'This page is very hard to understand or incomprehensible',
					params: {
						date: param.date
					},
					position: 'top',
					multiple: true
				},

				context: {
					label: 'Insufficient context',
					tag: 'context',
					desc: 'This page provides insufficient context.',
					params: {
						date: param.date
					},
					position: 'top',
					multiple: true
				},

				notenglish: {
					label: 'Incorrect language',
					tag: 'not english',
					desc: 'This page is written in an incorrect language and needs translation.',
					params: { },
					position: 'top',
					multiple: false
				},

				manual: {
					label: 'Manual',
					tag: 'manual',
					desc: 'This page is written like a manual or guidebook.',
					params: {
						date: param.date
					},
					position: 'top',
					multiple: true
				},

				newsrelease: {
					label: 'News release',
					tag: 'news release',
					desc: 'This page reads like a news release.',
					params: {
						date: param.date
					},
					position: 'top',
					multiple: true
				},

				overlydetailed: {
					label: 'Overly detailed',
					tag: 'overly detailed',
					desc: 'This page contains an excessive amount of intricate detail.',
					params: {
						date: param.date
					},
					position: 'top',
					multiple: true
				},

				technical: {
					label: 'Technical',
					tag: 'technical',
					desc: 'This page is too technical for the uninitiated reader.',
					params: {
						date: param.date
					},
					position: 'top',
					multiple: true
				},

				tense: {
					label: 'Tense',
					tag: 'tense',
					desc: 'This page is written in an incorrect tense.',
					params: {
						date: param.date
					},
					position: 'top',
					multiple: true
				},

				tone: {
					label: 'Tone',
					tag: 'tone',
					desc: 'Tone of this page is not appropriate.',
					params: {
						date: param.date
					},
					position: 'top',
					multiple: true
				},

				overquotation: {
					label: 'Too many quotations',
					tag: 'over-quotation',
					desc: 'This page contains too many or too-lengthy quotations for an encyclopedic entry.',
					params: {
						date: param.date
					},
					position: 'top',
					multiple: true
				},

				unfocused: {
					label: 'Unfocused',
					tag: 'unfocused',
					desc: 'This page lacks focus or is about more than one topic.',
					params: {
						date: param.date
					},
					position: 'top',
					multiple: true
				}
			}
		},

		moretags: {
			label: 'More tags',
			tags: {
				allplot: {
					label: 'All plot',
					tag: 'all plot',
					desc: 'This page is almost entirely a plot summary.',
					params: {
						date: param.date
					},
					position: 'top',
					multiple: true
				},

				expandlanguage: {
					label: 'Expand language',
					tag: 'expand language',
					desc: 'This page can be expanded with material from a foreign-language Wikipedia',
					params: {
						date: param.date,
						langcode: {
							label: 'Language of the foreign-language wikipedia (two/three-letter code):',
							input: 'required',
							type: 'text',
							value: ''
						},
						otherarticle: {
							label: 'Name of the article in the original language:',
							input: 'required',
							type: 'text',
							value: ''
						}
					},
					position: 'top',
					multiple: true
				},

				expert: {
					label: 'Expert needed',
					tag: 'expert needed',
					desc: 'This page needs attention from an expert on the subject.',
					params: {
						date: param.date,
						reason: $.extend( true, {}, param.reason )
					},
					position: 'top',
					multiple: true
				},

				fiction: {
					label: 'Fiction',
					tag: 'fiction',
					desc: 'This page fails to distinguish between fact and fiction.',
					params: {
						date: param.date
					},
					position: 'top',
					multiple: true
				},

				inuniverse: {
					label: 'Fictional perspective',
					tag: 'in-universe',
					desc: 'This page subject is fictional and needs rewriting from a non-fictional perspective.',
					params: {
						date: param.date
					},
					position: 'top',
					multiple: true
				},

				outofdate: {
					label: 'Out of date',
					tag: 'out of date',
					desc: 'This page needs out-of-date information removed or updated.',
					params: {
						date: param.date
					},
					position: 'top',
					multiple: true
				},

				plot: {
					label: 'Plot',
					tag: 'plot',
					desc: 'This page\'s plot summary is too long or excessively detailed.',
					params: {
						date: param.date
					},
					position: 'top',
					multiple: true
				},

				underreview: {
					label: 'Under review',
					tag: 'Under review',
					desc: 'You intend to assess the article but it will take some time. Tag to avoid patrolling redundancy.',
					params: {
						date: param.date
					},
					position: 'top',
					multiple: true
				},

				update: {
					label: 'Update',
					tag: 'update',
					desc: 'This page needs additional up-to-date information added.',
					params: {
						date: param.date
					},
					position: 'top',
					multiple: true
				}
			}
		},
		redirects: {
			label: 'Redirect tags',
			tags: {
				'R-from-alternative-language': {
					label: '{{R from alternative language}}',
					tag: 'R from alternative language',
					desc: 'redirect from or to a title in another language',
					position: 'redirectTag',
					params: {
						1: {
							label: 'From language (two-letter code):',
							input: 'optional',
							type: 'text',
							value: ''
						},
						2: {
							label: 'To language (two-letter code):',
							input: 'optional',
							type: 'text',
							value: ''
						}
					},
					multiple: true
				},
				'R-from-acronym': {
					label: '{{R from acronym}}',
					tag: 'R from acronym',
					desc: 'redirect from an acronym (e.g. POTUS) to its expanded form',
					position: 'redirectTag',
					multiple: true
				},
				'R-from-initialism': {
					label: '{{R from initialism}}',
					tag: 'R from initialism',
					desc: 'redirect from an initialism (e.g. AGF) to its expanded form',
					position: 'redirectTag',
					multiple: true
				},
				'R-from-MathSciNet-abbreviation': {
					label: '{{R from MathSciNet abbreviation}}',
					tag: 'R from MathSciNet abbreviation',
					desc: 'redirect from MathSciNet publication title abbreviation to the unabbreviated title',
					position: 'redirectTag',
					multiple: true
				},
				'R-from-NLM-abbreviation': {
					label: '{{R from NLM abbreviation}}',
					tag: 'R from NLM abbreviation',
					desc: 'redirect from a NLM publication title abbreviation to the unabbreviated title',
					position: 'redirectTag',
					multiple: true
				},
				'R-from-CamelCase': {
					label: '{{R from CamelCase}}',
					tag: 'R from CamelCase',
					desc: 'redirect from a CamelCase title',
					position: 'redirectTag',
					multiple: true
				},
				'R-from-other-capitalisation': {
					label: '{{R from other capitalisation}}',
					tag: 'R from other capitalisation',
					desc: 'redirect from a title with another method of capitalisation',
					position: 'redirectTag',
					multiple: true
				},
				'R-from-miscapitalisation': {
					label: '{{R from miscapitalisation}}',
					tag: 'R from miscapitalisation',
					desc: 'redirect from a capitalisation error',
					position: 'redirectTag',
					multiple: true
				},
				'R-from-modification': {
					label: '{{R from modification}}',
					tag: 'R from modification',
					desc: 'redirect from a modification of the target\'s title, such as with words rearranged',
					position: 'redirectTag',
					multiple: true
				},
				'R-from-plural': {
					label: '{{R from plural}}',
					tag: 'R from plural',
					desc: 'redirect from a plural word to the singular equivalent',
					position: 'redirectTag',
					multiple: true
				},
				'R-to-plural': {
					label: '{{R to plural}}',
					tag: 'R to plural',
					desc: 'redirect from a singular noun to its plural form',
					position: 'redirectTag',
					multiple: true
				},
				'R-from-verb': {
					label: '{{R from verb}}',
					tag: 'R from verb',
					desc: 'redirect from an English-language verb or verb phrase',
					position: 'redirectTag',
					multiple: true
				},
				'R-from-adjective': {
					label: '{{R from adjective}}',
					tag: 'R from adjective',
					desc: 'redirect from an adjective (word or phrase that describes a noun)',
					position: 'redirectTag',
					multiple: true
				},
				'R-from-alternative-spelling': {
					label: '{{R from alternative spelling}}',
					tag: 'R from alternative spelling',
					desc: 'redirect from a title with a different spelling',
					position: 'redirectTag',
					multiple: true
				},
				'R-from-ASCII-only': {
					label: '{{R from ASCII-only}}',
					tag: 'R from ASCII-only',
					desc: 'redirect from a title in only basic ASCII to the formal title, with differences that are not diacritical marks or ligatures',
					position: 'redirectTag',
					multiple: true
				},
				'R-to-ASCII-only': {
					label: '{{R to ASCII-only}}',
					tag: 'R to ASCII-only',
					desc: 'redirect to a title in only basic ASCII from the formal title, with differences that are not diacritical marks or ligatures',
					position: 'redirectTag',
					multiple: true
				},
				'R-from-diacritic': {
					label: '{{R from diacritic}}',
					tag: 'R from diacritic',
					desc: 'redirect from a page name that has diacritical marks (accents, umlauts, etc.)',
					position: 'redirectTag',
					multiple: true
				},
				'R-to-diacritic': {
					label: '{{R to diacritic}}',
					tag: 'R to diacritic',
					desc: 'redirect to the article title with diacritical marks (accents, umlauts, etc.)',
					position: 'redirectTag',
					multiple: true
				},
				'R-from-misspelling': {
					label: '{{R from misspelling}}',
					tag: 'R from misspelling',
					desc: 'redirect from a misspelling or typographical error',
					position: 'redirectTag',
					multiple: true
				},
				'R-from-alternative-name': {
					label: '{{R from alternative name}}',
					tag: 'R from alternative name',
					desc: 'redirect from a title that is another name, a pseudonym, a nickname, or a synonym',
					position: 'redirectTag',
					multiple: true
				},
				'R-from-ambiguous-sort-name': {
					label: '{{R from ambiguous sort name}}',
					tag: 'R from ambiguous sort name',
					desc: 'redirect from an ambiguous sort name to a page or list that disambiguates it',
					position: 'redirectTag',
					multiple: true
				},
				'R-from-former-name': {
					label: '{{R from former name}}',
					tag: 'R from former name',
					desc: 'redirect from a former or historic name or a working title',
					position: 'redirectTag',
					multiple: true
				},
				'R-from-incomplete-name': {
					label: '{{R from incomplete name}}',
					tag: 'R from incomplete name',
					desc: 'R from incomplete name',
					position: 'redirectTag',
					multiple: true
				},
				'R-from-incorrect-name': {
					label: '{{R from incorrect name}}',
					tag: 'R from incorrect name',
					desc: 'redirect from an erroneus name that is unsuitable as a title',
					position: 'redirectTag',
					multiple: true
				},
				'R-from-less-specific-name': {
					label: '{{R from less specific name}}',
					tag: 'R from less specific name',
					desc: 'redirect from a less specific title to a more specific, less general one',
					position: 'redirectTag',
					multiple: true
				},
				'R-from-long-name': {
					label: '{{R from long name}}',
					tag: 'R from long name',
					desc: 'redirect from a more complete title',
					position: 'redirectTag',
					multiple: true
				},
				'R-from-more-specific-name': {
					label: '{{R from more specific name}}',
					tag: 'R from more specific name',
					desc: 'redirect from a more specific title to a less specific, more general one',
					position: 'redirectTag',
					multiple: true
				},
				'R-from-non-neutral-name': {
					label: '{{R from non-neutral name}}',
					tag: 'R from non-neutral name',
					desc: 'redirect from a title that contains a non-neutral, pejorative, controversial, or offensive word, phrase, or name',
					position: 'redirectTag',
					multiple: true
				},
				'R-from-short-name': {
					label: '{{R from short name}}',
					tag: 'R from short name',
					desc: 'redirect from a title that is a shortened form of a person\'s full name, a book title, or other more complete title',
					position: 'redirectTag',
					multiple: true
				},
				'R-from-sort-name': {
					label: '{{R from sort name}}',
					tag: 'R from sort name',
					desc: 'redirect from the target\'s sort name, such as beginning with their surname rather than given name',
					position: 'redirectTag',
					multiple: true
				},
				'R-from-synonym': {
					label: '{{R from synonym}}',
					tag: 'R from synonym',
					desc: 'redirect from a semantic synonym of the target page title',
					position: 'redirectTag',
					multiple: true
				},
				'R-from-birth-name': {
					label: '{{R from birth name}}',
					tag: 'R from birth name',
					desc: 'redirect from a person\'s birth name to a more common name',
					position: 'redirectTag',
					multiple: true
				},
				'R-from-given-name': {
					label: '{{R from given name}}',
					tag: 'R from given name',
					desc: 'redirect from a person\'s given name',
					position: 'redirectTag',
					multiple: true
				},
				'R-from-married-name': {
					label: '{{R from married name}}',
					tag: 'R from married name',
					desc: 'redirect from a person\'s married name to a more common name',
					position: 'redirectTag',
					multiple: true
				},
				'R-from-name-with-title': {
					label: '{{R from name with title}}',
					tag: 'R from name with title',
					desc: 'redirect from a person\'s name preceded or followed by a title to the name with no title or with the title in parentheses',
					position: 'redirectTag',
					multiple: true
				},
				'R-from-person': {
					label: '{{R from person}}',
					tag: 'R from person',
					desc: 'redirect from a person or persons to a related article',
					position: 'redirectTag',
					multiple: true
				},
				'R-from-personal-name': {
					label: '{{R from personal name}}',
					tag: 'R from personal name',
					desc: 'redirect from an individual\'s personal name to an article titled with their professional or other better known moniker',
					position: 'redirectTag',
					multiple: true
				},
				'R-from-pseudonym': {
					label: '{{R from pseudonym}}',
					tag: 'R from pseudonym',
					desc: 'redirect from a pseudonym',
					position: 'redirectTag',
					multiple: true
				},
				'R-from-surname': {
					label: '{{R from surname}}',
					tag: 'R from surname',
					desc: 'redirect from a title that is a surname',
					position: 'redirectTag',
					multiple: true
				},
				'R-from-drug-trade-name': {
					label: '{{R from drug trade name}}',
					tag: 'R from drug trade name',
					desc: 'redirect from (or to) the trade name of a drug to (or from) the international nonproprietary name (INN)',
					position: 'redirectTag',
					multiple: true
				},
				'R-from-filename': {
					label: '{{R from filename}}',
					tag: 'R from filename',
					desc: 'redirect from a title that is a filename of the target',
					position: 'redirectTag',
					multiple: true
				},
				'R-from-molecular-formula': {
					label: '{{R from molecular formula}}',
					tag: 'R from molecular formula',
					desc: 'redirect from a molecular/chemical formula to its technical or trivial name',
					position: 'redirectTag',
					multiple: true
				},
				'R-from-gene-symbol': {
					label: '{{R from gene symbol}}',
					tag: 'R from gene symbol',
					desc: 'redirect from a Human Genome Organisation (HUGO) symbol for a gene to an article about the gene',
					position: 'redirectTag',
					multiple: true
				},
				'R-to-scientific-name': {
					label: '{{R to scientific name}}',
					tag: 'R to scientific name',
					desc: 'redirect from the common name to the scientific name',
					position: 'redirectTag',
					multiple: true
				},
				'R-from-scientific-name': {
					label: '{{R from scientific name}}',
					tag: 'R from scientific name',
					desc: 'redirect from the scientific name to the common name',
					position: 'redirectTag',
					multiple: true
				},
				'R-from-alternative-scientific-name': {
					label: '{{R from alternative scientific name}}',
					tag: 'R from alternative scientific name',
					desc: 'redirect from an alternative scientific name to the accepted scientific name',
					position: 'redirectTag',
					multiple: true
				},
				'R-from-scientific-abbreviation': {
					label: '{{R from scientific abbreviation}}',
					tag: 'R from scientific abbreviation',
					desc: 'redirect from a scientific abbreviation',
					position: 'redirectTag',
					multiple: true
				},
				'R-to-monotypic-taxon': {
					label: '{{R to monotypic taxon}}',
					tag: 'R to monotypic taxon',
					desc: 'redirect from the only lower-ranking member of a monotypic taxon to its monotypic taxon',
					position: 'redirectTag',
					multiple: true
				},
				'R-from-monotypic-taxon': {
					label: '{{R from monotypic taxon}}',
					tag: 'R from monotypic taxon',
					desc: 'redirect from a monotypic taxon to its only lower-ranking member',
					position: 'redirectTag',
					multiple: true
				},
				'R-taxon-with-possibilities': {
					label: '{{R taxon with possibilities}}',
					tag: 'R taxon with possibilities',
					desc: 'redirect from a title related to a living organism that potentially could be expanded into an article',
					position: 'redirectTag',
					multiple: true
				},
				'R-from-name-and-country': {
					label: '{{R from name and country}}',
					tag: 'R from name and country',
					desc: 'redirect from the specific name to the briefer name',
					position: 'redirectTag',
					multiple: true
				},
				'R-from-more-specific-geographic-name': {
					label: '{{R from more specific geographic name}}',
					tag: 'R from more specific geographic name',
					desc: 'redirect from a geographic location that includes extraneous identifiers such as the county or region of a city',
					position: 'redirectTag',
					multiple: true
				},
				'R-to-anchor': {
					label: '{{R to anchor}}',
					tag: 'R to anchor',
					desc: 'redirect from a topic that does not have its own page to an anchored part of a page on the subject',
					position: 'redirectTag',
					multiple: true
				},
				'R-from-file-metadata-link': {
					label: '{{R from file metadata link}}',
					tag: 'R from file metadata link',
					desc: 'redirect of a wikilink created from EXIF, XMP, or other information (i.e. the "metadata" section on some image description pages)',
					position: 'redirectTag',
					multiple: true
				},
				'R-to-list-entry': {
					label: '{{R to list entry}}',
					tag: 'R to list entry',
					desc: 'redirect to a list which contains brief descriptions of subjects not notable enough to have separate articles',
					position: 'redirectTag',
					multiple: true
				},
				'R-mentioned-in-hatnote': {
					label: '{{R mentioned in hatnote}}',
					tag: 'R mentioned in hatnote',
					desc: 'redirect from a title that is mentioned in a hatnote at the redirect target',
					position: 'redirectTag',
					multiple: true
				},
				'R-to-section': {
					label: '{{R to section}}',
					tag: 'R to section',
					desc: 'similar to {{R to list entry}}, but when list is organized in sections, such as list of characters in a fictional universe',
					position: 'redirectTag',
					multiple: true
				},
				'R-from-shortcut': {
					label: '{{R from shortcut}}',
					tag: 'R from shortcut',
					desc: 'redirect from a Wikipedia shortcut',
					position: 'redirectTag',
					multiple: true
				},
				'R-to-subpage': {
					label: '{{R to subpage}}',
					tag: 'R to subpage',
					desc: 'redirect to a subpage',
					position: 'redirectTag',
					multiple: true
				},
				'R-from-ambiguous-term': {
					label: '{{R from ambiguous term}}',
					tag: 'R from ambiguous term',
					desc: 'redirect from an ambiguous page name to a page that disambiguates it. This template should never appear on a page that has "(disambiguation)" in its title, use R to disambiguation page instead',
					position: 'redirectTag',
					multiple: true
				},
				'R-to-disambiguation-page': {
					label: '{{R to disambiguation page}}',
					tag: 'R to disambiguation page',
					desc: 'redirect to a disambiguation page',
					position: 'redirectTag',
					multiple: true
				},
				'R-from-incomplete-disambiguation': {
					label: '{{R from incomplete disambiguation}}',
					tag: 'R from incomplete disambiguation',
					desc: 'redirect from a page name that is too ambiguous to be the title of an article and should redirect to an appropriate disambiguation page',
					position: 'redirectTag',
					multiple: true
				},
				'R-from-incorrect-disambiguation': {
					label: '{{R from incorrect disambiguation}}',
					tag: 'R from incorrect disambiguation',
					desc: 'redirect from a page name with incorrect disambiguation due to an error or previous editorial misconception',
					position: 'redirectTag',
					multiple: true
				},
				'R-from-other-disambiguation': {
					label: '{{R from other disambiguation}}',
					tag: 'R from other disambiguation',
					desc: 'redirect from a page name with an alternative disambiguation qualifier',
					position: 'redirectTag',
					multiple: true
				},
				'R-from-unnecessary-disambiguation': {
					label: '{{R from unnecessary disambiguation}}',
					tag: 'R from unnecessary disambiguation',
					desc: 'redirect from a page name that has an unneeded disambiguation qualifier',
					position: 'redirectTag',
					multiple: true
				},
				'R-from-duplicated-article': {
					label: '{{R from duplicated article}}',
					tag: 'R from duplicated article',
					desc: 'redirect to a similar article in order to preserve its edit history',
					position: 'redirectTag',
					multiple: true
				},
				'R-with-history': {
					label: '{{R with history}}',
					tag: 'R with history',
					desc: 'redirect from a page containing substantive page history, kept to preserve content and attributions',
					position: 'redirectTag',
					multiple: true
				},
				'R-from-move': {
					label: '{{R from move}}',
					tag: 'R from move',
					desc: 'redirect from a page that has been moved/renamed',
					position: 'redirectTag',
					multiple: true
				},
				'R-from-merge': {
					label: '{{R from merge}}',
					tag: 'R from merge',
					desc: 'redirect from a merged page in order to preserve its edit history',
					position: 'redirectTag',
					multiple: true
				},
				'R-from-remote-talk-page': {
					label: '{{R from remote talk page}}',
					tag: 'R from remote talk page',
					desc: 'redirect from a talk page in any talk namespace to a corresponding page that is more heavily watched',
					position: 'redirectTag',
					multiple: true
				},
				'R-to-category-namespace': {
					label: '{{R to category namespace}}',
					tag: 'R to category namespace',
					desc: 'redirect from a page outside the category namespace to a category page',
					position: 'redirectTag',
					multiple: true
				},
				'R-to-help-namespace': {
					label: '{{R to help namespace}}',
					tag: 'R to help namespace',
					desc: 'redirect from any page inside or outside of help namespace to a page in that namespace',
					position: 'redirectTag',
					multiple: true
				},
				'R-to-main-namespace': {
					label: '{{R to main namespace}}',
					tag: 'R to main namespace',
					desc: 'redirect from a page outside the main-article namespace to an article in mainspace',
					position: 'redirectTag',
					multiple: true
				},
				'R-to-portal-namespace': {
					label: '{{R to portal namespace}}',
					tag: 'R to portal namespace',
					desc: 'redirect from any page inside or outside of portal space to a page in that namespace',
					position: 'redirectTag',
					multiple: true
				},
				'R-to-project-namespace': {
					label: '{{R to project namespace}}',
					tag: 'R to project namespace',
					desc: 'redirect from any page inside or outside of project (Wikipedia: or WP:) space to any page in the project namespace',
					position: 'redirectTag',
					multiple: true
				},
				'R-to-user-namespace': {
					label: '{{R to user namespace}}',
					tag: 'R to user namespace',
					desc: 'redirect from a page outside the user namespace to a user page (not to a user talk page)',
					position: 'redirectTag',
					multiple: true
				},
				'R-from-book': {
					label: '{{R from book}}',
					tag: 'R from book',
					desc: 'redirect from a book title to a more general, relevant article',
					position: 'redirectTag',
					multiple: true
				},
				'R-from-album': {
					label: '{{R from album}}',
					tag: 'R from album',
					desc: 'redirect from an album to a related topic such as the recording artist or a list of albums',
					position: 'redirectTag',
					multiple: true
				},
				'R-from-song': {
					label: '{{R from song}}',
					tag: 'R from song',
					desc: 'redirect from a song title to a more general, relevant article',
					position: 'redirectTag',
					multiple: true
				},
				'R-from-television-episode': {
					label: '{{R from television episode}}',
					tag: 'R from television episode',
					desc: 'redirect from a television episode title to a related work or lists of episodes',
					position: 'redirectTag',
					multiple: true
				},
				'R-from-fictional-character': {
					label: '{{R from fictional character}}',
					tag: 'R from fictional character',
					desc: 'redirect from a fictional character to a related fictional work or list of characters',
					position: 'redirectTag',
					multiple: true
				},
				'R-from-fictional-element': {
					label: '{{R from fictional element}}',
					tag: 'R from fictional element',
					desc: 'redirect from a fictional element (such as an object or concept) to a related fictional work or list of similar elements',
					position: 'redirectTag',
					multiple: true
				},
				'R-from-fictional-location': {
					label: '{{R from fictional location}}',
					tag: 'R from fictional location',
					desc: 'redirect from a fictional location or setting to a related fictional work or list of places',
					position: 'redirectTag',
					multiple: true
				},
				'R-to-article-without-mention': {
					label: '{{R to article without mention}}',
					tag: 'R to article without mention',
					desc: 'redirect to an article without any mention of the redirected word or phrase',
					position: 'redirectTag',
					multiple: true
				},
				'R-to-decade': {
					label: '{{R to decade}}',
					tag: 'R to decade',
					desc: 'redirect from a year to the decade article',
					position: 'redirectTag',
					multiple: true
				},
				'R-from-domain-name': {
					label: '{{R from domain name}}',
					tag: 'R from domain name',
					desc: 'redirect from a domain name to an article about a website',
					position: 'redirectTag',
					multiple: true
				},
				'R-from-phrase': {
					label: '{{R from phrase}}',
					tag: 'R from phrase',
					desc: 'redirect from a phrase to a more general relevant article covering the topic',
					position: 'redirectTag',
					multiple: true
				},
				'R-from-list-topic': {
					label: '{{R from list topic}}',
					tag: 'R from list topic',
					desc: 'redirect from the topic of a list to the equivalent list',
					position: 'redirectTag',
					multiple: true
				},
				'R-from-member': {
					label: '{{R from member}}',
					tag: 'R from member',
					desc: 'redirect from a member of a group to a related topic such as the group or organization',
					position: 'redirectTag',
					multiple: true
				},
				'R-to-related-topic': {
					label: '{{R to related topic}}',
					tag: 'R to related topic',
					desc: 'redirect to an article about a similar topic',
					position: 'redirectTag',
					multiple: true
				},
				'R-from-related-word': {
					label: '{{R from related word}}',
					tag: 'R from related word',
					desc: 'redirect from a related word',
					position: 'redirectTag',
					multiple: true
				},
				'R-from-school': {
					label: '{{R from school}}',
					tag: 'R from school',
					desc: 'redirect from a school article that had very little information',
					position: 'redirectTag',
					multiple: true
				},
				'R-from-subtopic': {
					label: '{{R from subtopic}}',
					tag: 'R from subtopic',
					desc: 'redirect from a title that is a subtopic of the target article',
					position: 'redirectTag',
					multiple: true
				},
				'R-to-subtopic': {
					label: '{{R to subtopic}}',
					tag: 'R to subtopic',
					desc: 'redirect to a subtopic of the redirect\'s title',
					position: 'redirectTag',
					multiple: true
				},
				'R-from-Unicode-character': {
					label: '{{R from Unicode character}}',
					tag: 'R from Unicode character',
					desc: 'redirect from a single Unicode character to an article or Wikipedia project page that infers meaning for the symbol',
					position: 'redirectTag',
					multiple: true
				},
				'R-from-Unicode-code': {
					label: '{{R from Unicode code}}',
					tag: 'R from Unicode code',
					desc: 'redirect from a Unicode code point to an article about the character it represents',
					position: 'redirectTag',
					multiple: true
				},
				'R-with-possibilities': {
					label: '{{R with possibilities}}',
					tag: 'R with possibilities',
					desc: 'redirect from a specific title to a more general, less detailed article (something which can and should be expanded)',
					position: 'redirectTag',
					multiple: true
				},
				'R-from-ISO-4-abbreviation': {
					label: '{{R from ISO 4 abbreviation}}',
					tag: 'R from ISO 4 abbreviation',
					desc: 'redirect from an ISO 4 publication title abbreviation to the unabbreviated title',
					position: 'redirectTag',
					multiple: true
				},
				'R-from-ISO-639-code': {
					label: '{{R from ISO 639 code}}',
					tag: 'R from ISO 639 code',
					desc: 'redirect from a title that is an ISO 639 language code to an article about the language',
					position: 'redirectTag',
					multiple: true
				},
				'R-printworthy': {
					label: '{{R printworthy}}',
					tag: 'R printworthy',
					desc: 'redirect from a title that would be helpful in a printed or CD/DVD version of Wikipedia',
					position: 'redirectTag',
					multiple: true
				},
				'R-unprintworthy': {
					label: '{{R unprintworthy}}',
					tag: 'R unprintworthy',
					desc: 'redirect from a title that would NOT be helpful in a printed or CD/DVD version of Wikipedia',
					position: 'redirectTag',
					multiple: true
				}
			}
		}
	};
}() );

module.exports = { $ };

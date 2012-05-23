//See http://www.mediawiki.org/wiki/Extension:PageTriage for basic documentation on configuration.
//<nowiki>
( function( $ ) {
$.pageTriageTagsOptions = {

	'common': {
		label: mw.msg( 'pagetriage-tags-cat-common-label' ),
		tags: {
			'linkrot': {
				label: mw.msg( 'pagetriage-tags-linkrot-label' ),
				tag: 'linkrot',
				desc: mw.msg( 'pagetriage-tags-linkrot-desc' ),
				params: {
					'date': {
						required: true,
						label: mw.msg( 'pagetriage-tags-param-date-label' )
					}
				},
				position: 'top'
			},

			'copyedit': {
				label: mw.msg( 'pagetriage-tags-copyedit-label' ),
				tag: 'copy edit',
				desc: mw.msg( 'pagetriage-tags-copyedit-desc' ),
				params: {
					'date': {
						required: true,
						label: mw.msg( 'pagetriage-tags-param-date-label' )
					},
					'for': {
						required: true,
						label: mw.msg( 'pagetriage-tags-param-for-label' )
					},
					'categories': {
						required: false,
						label: mw.msg( 'pagetriage-tags-param-categories-label' )
					}
				},
				position: 'top'
			},

			'morefootnotes': {
				label: mw.msg( 'pagetriage-tags-morefootnotes-label' ),
				tag: 'more footnotes',
				desc: mw.msg( 'pagetriage-tags-morefootnotes-desc' ),
				params: {
					'date': {
						required: true,
						label: mw.msg( 'pagetriage-tags-param-date-label' )
					},
					'blp': {
						required: true,
						label: mw.msg( 'pagetriage-tags-param-blp-label' )
					}
				},
				position: 'top'
			},

			'refimprove': {
				label: mw.msg( 'pagetriage-tags-refimprove-label' ),
				tag: 'ref improve',
				desc: mw.msg( 'pagetriage-tags-refimprove-desc' ),
				params: {
					'date': {
						required: true,
						label: mw.msg( 'pagetriage-tags-param-date-label' )
					},
					'talk': {
						required: false,
						label: mw.msg( 'pagetriage-tags-param-talk-label' )
					}
				},
				position: 'top'
			},

			'uncategorised': {
				label: mw.msg( 'pagetriage-tags-uncategorised-label' ),
				tag: 'uncategorised',
				desc: mw.msg( 'pagetriage-tags-uncategorised-desc' ),
				params: {
					'date': {
						required: true,
						label: mw.msg( 'pagetriage-tags-param-date-label' )
					}
				},
				position: 'bottom'
			},

			'unreferenced': {
				label: mw.msg( 'pagetriage-tags-unreferenced-label' ),
				tag: 'unreferenced',
				desc: mw.msg( 'pagetriage-tags-unreferenced-desc' ),
				params: {
					'date': {
						required: true,
						label: mw.msg( 'pagetriage-tags-param-date-label' )
					}
				},
				position: 'bottom'
			}
		}
	},

	'metadata': {
		label: mw.msg( 'pagetriage-tags-cat-metadata-label' ),
		tags: {
			'deadend': {
				label: mw.msg( 'pagetriage-tags-deadend-label' ),
				tag: 'dead end',
				desc: mw.msg( 'pagetriage-tags-deadend-desc' ),
				params: {
					'date': {
						required: true,
						label: mw.msg( 'pagetriage-tags-param-date-label' )
					}
				},
				position: 'top'
			},

			'externallinks': {
				label: mw.msg( 'pagetriage-tags-externallinks-label' ),
				tag: 'external links',
				desc: mw.msg( 'pagetriage-tags-externallinks-desc' ),
				params: {
					'date': {
						required: true,
						label: mw.msg( 'pagetriage-tags-param-date-label' )
					}
				},
				position: 'top'
			},

			'catimprove': {
				label: mw.msg( 'pagetriage-tags-catimprove-label' ),
				tag: 'cat improve',
				desc: mw.msg( 'pagetriage-tags-catimprove-desc' ),
				params: {
					'date': {
						required: true,
						label: mw.msg( 'pagetriage-tags-param-date-label' )
					},
					'talk': {
						required: false,
						label: mw.msg( 'pagetriage-tags-param-talk-label' )
					}
				},
				position: 'top'
			},

			'orphan': {
				label: mw.msg( 'pagetriage-tags-orphan-label' ),
				tag: 'orphan',
				desc: mw.msg( 'pagetriage-tags-orphan-desc' ),
				params: {
					'date': {
						required: true,
						label: mw.msg( 'pagetriage-tags-param-date-label' )
					},
					'att': {
						required: false,
						label: mw.msg( 'pagetriage-tags-param-att-label' )
					}
				},
				position: 'top'
			},

			'overlinked': {
				label: mw.msg( 'pagetriage-tags-overlinked-label' ),
				tag: 'overlinked',
				desc: mw.msg( 'pagetriage-tags-overlinked-desc' ),
				params: {
					'date': {
						required: true,
						label: mw.msg( 'pagetriage-tags-param-date-label' )
					}
				},
				position: 'top'
			}
		}
	},

	'cleanup': {
		label: mw.msg( 'pagetriage-tags-cat-cleanup-label' ),
		tags: {
			'cleanup': {
				label: mw.msg( 'pagetriage-tags-cleanup-label' ),
				tag: 'cleanup',
				desc: mw.msg( 'pagetriage-tags-cleanup-desc' ),
				params: {
					'date': {
						required: true,
						label: mw.msg( 'pagetriage-tags-param-date-label' )
					},
					'reason': {
						required: true,
						label: mw.msg( 'pagetriage-tags-param-reason-label' )
					},
					'talksection': {
						required: false,
						label: mw.msg( 'pagetriage-tags-param-talksection-label' )
					}
				},
				position: 'top'
			},

			'copyedit': {
				label: mw.msg( 'pagetriage-tags-copyedit-label' ),
				tag: 'copy edit',
				desc: mw.msg( 'pagetriage-tags-copyedit-desc' ),
				params: {
					'date': {
						required: true,
						label: mw.msg( 'pagetriage-tags-param-date-label' )
					},
					'for': {
						required: true,
						label: mw.msg( 'pagetriage-tags-param-for-label' )
					},
					'categories': {
						required: false,
						label: mw.msg( 'pagetriage-tags-param-categories-label' )
					}
				},
				position: 'top'
			},

			'expertsubject': {
				label: mw.msg( 'pagetriage-tags-expertsubject-label' ),
				tag: 'expert-subject',
				desc: mw.msg( 'pagetriage-tags-expertsubject-desc' ),
				params: {
					'date': {
						required: true,
						label: mw.msg( 'pagetriage-tags-param-date-label' )
					},
					'reason': {
						required: true,
						label: mw.msg( 'pagetriage-tags-param-reason-label' )
					},
					'talk': {
						required: false,
						label: mw.msg( 'pagetriage-tags-param-talk-label' )
					}
				},
				position: 'top'
			},

			'prose': {
				label: mw.msg( 'pagetriage-tags-prose-label' ),
				tag: 'prose',
				desc: mw.msg( 'pagetriage-tags-prose-desc' ),
				params: {
					'date': {
						required: true,
						label: mw.msg( 'pagetriage-tags-param-date-label' )
					}
				},
				position: 'top'
			},

			'roughtranslation': {
				label: mw.msg( 'pagetriage-tags-roughtranslation-label' ),
				tag: 'rough translation',
				desc: mw.msg( 'pagetriage-tags-roughtranslation-desc' ),
				params: { },
				position: 'top'
			},

			'wikify': {
				label: mw.msg( 'pagetriage-tags-wikify-label' ),
				tag: 'wikify',
				desc: mw.msg( 'pagetriage-tags-wikify-desc' ),
				params: {
					'date': {
						required: true,
						label: mw.msg( 'pagetriage-tags-param-date-label' )
					},
					'reason': {
						required: true,
						label: mw.msg( 'pagetriage-tags-param-reason-label' )
					}
				},
				position: 'top'
			}
		}
	},

	'neutrality': {
		label: mw.msg( 'pagetriage-tags-cat-neutrality-label' ),
		tags: {
			'advert': {
				label: mw.msg( 'pagetriage-tags-advert-label' ),
				tag: 'advert',
				desc: mw.msg( 'pagetriage-tags-advert-desc' ),
				params: {
					'date': {
						required: true,
						label: mw.msg( 'pagetriage-tags-param-date-label' )
					}
				},
				position: 'top'
			},

			'autobiography': {
				label: mw.msg( 'pagetriage-tags-autobiography-label' ),
				tag: 'autobiography',
				desc: mw.msg( 'pagetriage-tags-autobiography-desc' ),
				params: {
					'date': {
						required: true,
						label: mw.msg( 'pagetriage-tags-param-date-label' )
					}
				},
				position: 'top'
			},

			'coi': {
				label: mw.msg( 'pagetriage-tags-coi-label' ),
				tag: 'coi',
				desc: mw.msg( 'pagetriage-tags-coi-desc' ),
				params: {
					'date': {
						required: true,
						label: mw.msg( 'pagetriage-tags-param-date-label' )
					}
				},
				position: 'top'
			},

			'peacock': {
				label: mw.msg( 'pagetriage-tags-peacock-label' ),
				tag: 'peacock',
				desc: mw.msg( 'pagetriage-tags-peacock-desc' ),
				params: {
					'date': {
						required: true,
						label: mw.msg( 'pagetriage-tags-param-date-label' )
					}
				},
				position: 'top'
			},

			'pov': {
				label: mw.msg( 'pagetriage-tags-pov-label' ),
				tag: 'pov',
				desc: mw.msg( 'pagetriage-tags-pov-desc' ),
				params: {
					'date': {
						required: true,
						label: mw.msg( 'pagetriage-tags-param-date-label' )
					}
				},
				position: 'top'
			},

			'weasel': {
				label: mw.msg( 'pagetriage-tags-weasel-label' ),
				tag: 'weasel',
				desc: mw.msg( 'pagetriage-tags-weasel-desc' ),
				params: {
					'date': {
						required: true,
						label: mw.msg( 'pagetriage-tags-param-date-label' )
					}
				},
				position: 'top'
			}
		}
	},

	'sources': {
		label: mw.msg( 'pagetriage-tags-cat-sources-label' ),
		tags: {
			'refimprove': {
				label: mw.msg( 'pagetriage-tags-refimprove-label' ),
				tag: 'ref improve',
				desc: mw.msg( 'pagetriage-tags-refimprove-desc' ),
				params: {
					'date': {
						required: true,
						label: mw.msg( 'pagetriage-tags-param-date-label' )
					},
					'talk': {
						required: false,
						label: mw.msg( 'pagetriage-tags-param-talk-label' )
					}
				},
				position: 'top'
			},

			'blpsources': {
				label: mw.msg( 'pagetriage-tags-blpsources-label' ),
				tag: 'blp sources',
				desc: mw.msg( 'pagetriage-tags-blpsources-desc' ),
				params: {
					'date': {
						required: true,
						label: mw.msg( 'pagetriage-tags-param-date-label' )
					}
				},
				position: 'top'
			},

			'originalresearch': {
				label: mw.msg( 'pagetriage-tags-originalresearch-label' ),
				tag: 'original research',
				desc: mw.msg( 'pagetriage-tags-originalresearch-desc' ),
				params: {
					'date': {
						required: true,
						label: mw.msg( 'pagetriage-tags-param-date-label' )
					},
					'discuss': {
						required: false,
						label: mw.msg( 'pagetriage-tags-param-discuss-label' )
					}
				},
				position: 'top'
			},

			'primarysources': {
				label: mw.msg( 'pagetriage-tags-primarysources-label' ),
				tag: 'primary sources',
				desc: mw.msg( 'pagetriage-tags-primarysources-desc' ),
				params: {
					'date': {
						required: true,
						label: mw.msg( 'pagetriage-tags-param-date-label' )
					},
					'blp': {
						required: true,
						label: mw.msg( 'pagetriage-tags-param-blp-label' )
					}
				},
				position: 'top'
			},

			'onesource': {
				label: mw.msg( 'pagetriage-tags-onesource-label' ),
				tag: 'one source',
				desc: mw.msg( 'pagetriage-tags-onesource-desc' ),
				params: {
					'date': {
						required: true,
						label: mw.msg( 'pagetriage-tags-param-date-label' )
					},
					'text': {
						required: false,
						label: mw.msg( 'pagetriage-tags-param-text-label' )
					}
				},
				position: 'top'
			},

			'unreferenced': {
				label: mw.msg( 'pagetriage-tags-unreferenced-label' ),
				tag: 'unreferenced',
				desc: mw.msg( 'pagetriage-tags-unreferenced-desc' ),
				params: {
					'date': {
						required: true,
						label: mw.msg( 'pagetriage-tags-param-date-label' )
					}
				},
				position: 'top'
			}
		}
	},

	'structure': {
		label: mw.msg( 'pagetriage-tags-cat-structure-label' ),
		tags: {
			'condense': {
				label: mw.msg( 'pagetriage-tags-condense-label' ),
				tag: 'condense',
				desc: mw.msg( 'pagetriage-tags-condense-desc' ),
				params: {
					'date': {
						required: true,
						label: mw.msg( 'pagetriage-tags-param-date-label' )
					}
				},
				position: 'top'
			},

			'leadmissing': {
				label: mw.msg( 'pagetriage-tags-leadmissing-label' ),
				tag: 'lead missing',
				desc: mw.msg( 'pagetriage-tags-leadmissing-desc' ),
				params: {
					'date': {
						required: true,
						label: mw.msg( 'pagetriage-tags-param-date-label' )
					}
				},
				position: 'top'
			},

			'leadrewrite': {
				label: mw.msg( 'pagetriage-tags-leadrewrite-label' ),
				tag: 'lead rewrite',
				desc: mw.msg( 'pagetriage-tags-leadrewrite-desc' ),
				params: {
					'date': {
						required: true,
						label: mw.msg( 'pagetriage-tags-param-date-label' )
					},
					'reason': {
						required: true,
						label: mw.msg( 'pagetriage-tags-param-reason-label' )
					}
				},
				position: 'top'
			},

			'leadtoolong': {
				label: mw.msg( 'pagetriage-tags-leadtoolong-label' ),
				tag: 'lead too long',
				desc: mw.msg( 'pagetriage-tags-leadtoolong-desc' ),
				params: {
					'date': {
						required: true,
						label: mw.msg( 'pagetriage-tags-param-date-label' )
					}
				},
				position: 'top'
			},

			'leadtooshort': {
				label: mw.msg( 'pagetriage-tags-leadtooshort-label' ),
				tag: 'lead too short',
				desc: mw.msg( 'pagetriage-tags-leadtooshort-desc' ),
				params: {
					'date': {
						required: true,
						label: mw.msg( 'pagetriage-tags-param-date-label' )
					}
				},
				position: 'top'
			},

			'cleanupreorganise': {
				label: mw.msg( 'pagetriage-tags-cleanupreorganise-label' ),
				tag: 'cleanup-reorganise',
				desc: mw.msg( 'pagetriage-tags-cleanupreorganise-desc' ),
				params: {
					'date': {
						required: true,
						label: mw.msg( 'pagetriage-tags-param-date-label' )
					}
				},
				position: 'top'
			},

			'sections': {
				label: mw.msg( 'pagetriage-tags-sections-label' ),
				tag: 'sections',
				desc: mw.msg( 'pagetriage-tags-sections-desc' ),
				params: { },
				position: 'top'
			},

			'stub': {
				label: mw.msg( 'pagetriage-tags-stub-label' ),
				tag: 'stub',
				desc: mw.msg( 'pagetriage-tags-stub-desc' ),
				params: { },
				position: 'top'
			},

			'verylong': {
				label: mw.msg( 'pagetriage-tags-verylong-label' ),
				tag: 'very long',
				desc: mw.msg( 'pagetriage-tags-verylong-desc' ),
				params: {
					'date': {
						required: true,
						label: mw.msg( 'pagetriage-tags-param-date-label' )
					},
					'small': {
						required: false,
						label: mw.msg( 'pagetriage-tags-param-small-label' )
					}
				},
				position: 'top'
			}
		}
	},

	'unwantedcontent': {
		label: mw.msg( 'pagetriage-tags-cat-unwantedcontent-label' ),
		tags: {
			'closeparaphrasing': {
				label: mw.msg( 'pagetriage-tags-closeparaphrasing-label' ),
				tag: 'close paraphrasing',
				desc: mw.msg( 'pagetriage-tags-closeparaphrasing-desc' ),
				params: {
					'date': {
						required: true,
						label: mw.msg( 'pagetriage-tags-param-date-label' )
					},
					'source': {
						required: true,
						label: mw.msg( 'pagetriage-tags-param-source-label' )
					},
					'free': {
						required: true,
						label: mw.msg( 'pagetriage-tags-param-free-label' )
					}
				},
				position: 'top'
			},

			'copypaste': {
				label: mw.msg( 'pagetriage-tags-copypaste-label' ),
				tag: 'copypaste',
				desc: mw.msg( 'pagetriage-tags-copypaste-desc' ),
				params: {
					'date': {
						required: true,
						label: mw.msg( 'pagetriage-tags-param-date-label' )
					},
					'url': {
						required: true,
						label: mw.msg( 'pagetriage-tags-param-url-label' )
					}
				},
				position: 'top'
			},

			'nonfree': {
				label: mw.msg( 'pagetriage-tags-nonfree-label' ),
				tag: 'non-free',
				desc: mw.msg( 'pagetriage-tags-nonfree-desc' ),
				params: { },
				position: 'top'
			},

			'notability': {
				label: mw.msg( 'pagetriage-tags-notability-label' ),
				tag: 'notability',
				desc: mw.msg( 'pagetriage-tags-notability-desc' ),
				params: {
					'date': {
						required: true,
						label: mw.msg( 'pagetriage-tags-param-date-label' )
					}
				},
				position: 'top'
			}
		}
	},

	'verifiability': {
		label: mw.msg( 'pagetriage-tags-cat-verifiability-label' ),
		tags: {
			'disputed': {
				label: mw.msg( 'pagetriage-tags-disputed-label' ),
				tag: 'disputed',
				desc: mw.msg( 'pagetriage-tags-disputed-desc' ),
				params: {
					'date': {
						required: true,
						label: mw.msg( 'pagetriage-tags-param-date-label' )
					}
				},
				position: 'top'
			},

			'cleanuplinkrot': {
				label: mw.msg( 'pagetriage-tags-cleanuplinkrot-label' ),
				tag: 'cleanup-link rot',
				desc: mw.msg( 'pagetriage-tags-cleanuplinkrot-desc' ),
				params: {
					'date': {
						required: true,
						label: mw.msg( 'pagetriage-tags-param-date-label' )
					}
				},
				position: 'top'
			},

			'citationstyle': {
				label: mw.msg( 'pagetriage-tags-citationstyle-label' ),
				tag: 'citation style',
				desc: mw.msg( 'pagetriage-tags-citationstyle-desc' ),
				params: {
					'date': {
						required: true,
						label: mw.msg( 'pagetriage-tags-param-date-label' )
					},
					'details': {
						required: true,
						label: mw.msg( 'pagetriage-tags-param-details-label' )
					}
				},
				position: 'top'
			},

			'hoax': {
				label: mw.msg( 'pagetriage-tags-hoax-label' ),
				tag: 'hoax',
				desc: mw.msg( 'pagetriage-tags-hoax-desc' ),
				params: {
					'date': {
						required: true,
						label: mw.msg( 'pagetriage-tags-param-date-label' )
					}
				},
				position: 'top'
			},

			'nofootnotes': {
				label: mw.msg( 'pagetriage-tags-nofootnotes-label' ),
				tag: 'no footnotes',
				desc: mw.msg( 'pagetriage-tags-nofootnotes-desc' ),
				params: {
					'date': {
						required: true,
						label: mw.msg( 'pagetriage-tags-param-date-label' )
					},
					'blp': {
						required: true,
						label: mw.msg( 'pagetriage-tags-param-blp-label' )
					}
				},
				position: 'top'
			}
		}
	},

	'writingstyle': {
		label: mw.msg( 'pagetriage-tags-cat-writingstyle-label' ),
		tags: {
			'confusing': {
				label: mw.msg( 'pagetriage-tags-confusing-label' ),
				tag: 'confusing',
				desc: mw.msg( 'pagetriage-tags-confusing-desc' ),
				params: {
					'date': {
						required: true,
						label: mw.msg( 'pagetriage-tags-param-date-label' )
					},
					'reason': {
						required: true,
						label: mw.msg( 'pagetriage-tags-param-reason-label' )
					}
				},
				position: 'top'
			},

			'essaylike': {
				label: mw.msg( 'pagetriage-tags-essaylike-label' ),
				tag: 'essay-like',
				desc: mw.msg( 'pagetriage-tags-essaylike-desc' ),
				params: {
					'date': {
						required: true,
						label: mw.msg( 'pagetriage-tags-param-date-label' )
					}
				},
				position: 'top'
			},

			'fansite': {
				label: mw.msg( 'pagetriage-tags-fansite-label' ),
				tag: 'fansite',
				desc: mw.msg( 'pagetriage-tags-fansite-desc' ),
				params: {
					'date': {
						required: true,
						label: mw.msg( 'pagetriage-tags-param-date-label' )
					}
				},
				position: 'top'
			},

			'notenglish': {
				label: mw.msg( 'pagetriage-tags-notenglish-label' ),
				tag: 'not english',
				desc: mw.msg( 'pagetriage-tags-notenglish-desc' ),
				params: { },
				position: 'top'
			},

			'technical': {
				label: mw.msg( 'pagetriage-tags-technical-label' ),
				tag: 'technical',
				desc: mw.msg( 'pagetriage-tags-technical-desc' ),
				params: {
					'date': {
						required: true,
						label: mw.msg( 'pagetriage-tags-param-date-label' )
					}
				},
				position: 'top'
			},

			'tense': {
				label: mw.msg( 'pagetriage-tags-tense-label' ),
				tag: 'tense',
				desc: mw.msg( 'pagetriage-tags-tense-desc' ),
				params: {
					'date': {
						required: true,
						label: mw.msg( 'pagetriage-tags-param-date-label' )
					},
					'tense': {
						required: false,
						label: mw.msg( 'pagetriage-tags-param-tense-label' )
					}
				},
				position: 'top'
			},

			'tone': {
				label: mw.msg( 'pagetriage-tags-tone-label' ),
				tag: 'tone',
				desc: mw.msg( 'pagetriage-tags-tone-desc' ),
				params: {
					'date': {
						required: true,
						label: mw.msg( 'pagetriage-tags-param-date-label' )
					}
				},
				position: 'top'
			}
		}
	},

	'moretags': {
		label: mw.msg( 'pagetriage-tags-cat-moretags-label' ),
		tags: {
			'allplot': {
				label: mw.msg( 'pagetriage-tags-allplot-label' ),
				tag: 'allplot',
				desc: mw.msg( 'pagetriage-tags-allplot-desc' ),
				params: {
					'date': {
						required: true,
						label: mw.msg( 'pagetriage-tags-param-date-label' )
					}
				},
				position: 'top'
			},

			'fiction': {
				label: mw.msg( 'pagetriage-tags-fiction-label' ),
				tag: 'fiction',
				desc: mw.msg( 'pagetriage-tags-fiction-desc' ),
				params: {
					'date': {
						required: true,
						label: mw.msg( 'pagetriage-tags-param-date-label' )
					}
				},
				position: 'top'
			},

			'inuniverse': {
				label: mw.msg( 'pagetriage-tags-inuniverse-label' ),
				tag: 'in-universe',
				desc: mw.msg( 'pagetriage-tags-inuniverse-desc' ),
				params: {
					'date': {
						required: true,
						label: mw.msg( 'pagetriage-tags-param-date-label' )
					},
					'subject': {
						required: false,
						label: mw.msg( 'pagetriage-tags-param-subject-label' )
					},
					'category': {
						required: false,
						label: mw.msg( 'pagetriage-tags-param-category-label' )
					}
				},
				position: 'top'
			},

			'outofdate': {
				label: mw.msg( 'pagetriage-tags-outofdate-label' ),
				tag: 'out of date',
				desc: mw.msg( 'pagetriage-tags-outofdate-desc' ),
				params: {
					'date': {
						required: true,
						label: mw.msg( 'pagetriage-tags-param-date-label' )
					}
				},
				position: 'top'
			},

			'overlydetailed': {
				label: mw.msg( 'pagetriage-tags-overlydetailed-label' ),
				tag: 'overly detailed',
				desc: mw.msg( 'pagetriage-tags-overlydetailed-desc' ),
				params: {
					'date': {
						required: true,
						label: mw.msg( 'pagetriage-tags-param-date-label' )
					}
				},
				position: 'top'
			},

			'plot': {
				label: mw.msg( 'pagetriage-tags-plot-label' ),
				tag: 'plot',
				desc: mw.msg( 'pagetriage-tags-plot-desc' ),
				params: {
					'date': {
						required: true,
						label: mw.msg( 'pagetriage-tags-param-date-label' )
					}
				},
				position: 'top'
			},

			'recentism': {
				label: mw.msg( 'pagetriage-tags-recentism-label' ),
				tag: 'recentism',
				desc: mw.msg( 'pagetriage-tags-recentism-desc' ),
				params: {
					'date': {
						required: true,
						label: mw.msg( 'pagetriage-tags-param-date-label' )
					}
				},
				position: 'top'
			},

			'toofewopinions': {
				label: mw.msg( 'pagetriage-tags-toofewopinions-label' ),
				tag: 'too few opinions',
				desc: mw.msg( 'pagetriage-tags-toofewopinions-desc' ),
				params: {
					'date': {
						required: true,
						label: mw.msg( 'pagetriage-tags-param-date-label' )
					},
					'discuss': {
						required: false,
						label: mw.msg( 'pagetriage-tags-param-discuss-label' )
					}
				},
				position: 'top'
			},

			'undue': {
				label: mw.msg( 'pagetriage-tags-undue-label' ),
				tag: 'undue',
				desc: mw.msg( 'pagetriage-tags-undue-desc' ),
				params: {
					'date': {
						required: true,
						label: mw.msg( 'pagetriage-tags-param-date-label' )
					}
				},
				position: 'top'
			},

			'update': {
				label: mw.msg( 'pagetriage-tags-update-label' ),
				tag: 'update',
				desc: mw.msg( 'pagetriage-tags-update-desc' ),
				params: {
					'date': {
						required: true,
						label: mw.msg( 'pagetriage-tags-param-date-label' )
					},
					'type': {
						required: false,
						label: mw.msg( 'pagetriage-tags-param-type-label' )
					}
				},
				position: 'top'
			}
		}
	}

};

} ) ( jQuery );
//</nowiki>
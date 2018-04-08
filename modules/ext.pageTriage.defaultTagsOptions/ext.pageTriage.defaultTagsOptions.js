// See https://www.mediawiki.org/wiki/Extension:PageTriage for basic documentation on configuration.
// <nowiki>
( function ( $, mw ) {
	var today = new Date(),
		month = [ 'January', 'February', 'March', 'April', 'May', 'June', 'July',
			'August', 'September', 'October', 'November', 'December' ],

		param = {
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

			'for': {
				label: mw.msg( 'pagetriage-tags-param-for-label' ),
				input: 'optional',
				type: 'textarea',
				value: ''
			},

			blp: {
				label: mw.msg( 'pagetriage-tags-param-blp-label' ),
				input: 'optional',
				type: 'checkbox',
				value: ''
			},

			reason: {
				label: mw.msg( 'pagetriage-tags-param-issues-label' ),
				input: 'required',
				type: 'textarea',
				value: ''
			},

			details: {
				label: mw.msg( 'pagetriage-tags-param-issues-label' ),
				input: 'optional',
				type: 'textarea',
				value: ''
			},

			source: {
				label: mw.msg( 'pagetriage-tags-param-source-label' ),
				input: 'required',
				type: 'text',
				value: ''
			},

			free: {
				label: mw.msg( 'pagetriage-tags-param-free-label' ),
				input: 'optional',
				type: 'checkbox',
				value: ''
			},

			url: {
				label: mw.msg( 'pagetriage-tags-param-url-label' ),
				input: 'required',
				type: 'text',
				value: ''
			}
		};

	$.pageTriageTagsMultiple = 'Multiple issues';

	$.pageTriageTagsOptions = {

		common: {
			label: mw.msg( 'pagetriage-tags-cat-common-label' ),
			alias: true,
			tags: {
				linkrot: {
					label: mw.msg( 'pagetriage-tags-linkrot-label' ),
					tag: 'linkrot',
					desc: mw.msg( 'pagetriage-tags-linkrot-desc' ),
					params: {
						date: param.date
					},
					position: 'top',
					dest: 'sources',
					multiple: true
				},

				copyedit: {
					label: mw.msg( 'pagetriage-tags-copyedit-label' ),
					tag: 'copy edit',
					desc: mw.msg( 'pagetriage-tags-copyedit-desc' ),
					params: {
						date: param.date,
						'for': $.extend( true, {}, param.for )
					},
					position: 'top',
					dest: 'cleanup',
					multiple: true
				},

				morefootnotes: {
					label: mw.msg( 'pagetriage-tags-morefootnotes-label' ),
					tag: 'more footnotes',
					desc: mw.msg( 'pagetriage-tags-morefootnotes-desc' ),
					params: {
						date: param.date,
						blp: $.extend( true, {}, param.blp )
					},
					position: 'top',
					dest: 'sources',
					multiple: true
				},

				refimprove: {
					label: mw.msg( 'pagetriage-tags-refimprove-label' ),
					tag: 'refimprove',
					desc: mw.msg( 'pagetriage-tags-refimprove-desc' ),
					params: {
						date: param.date
					},
					position: 'top',
					dest: 'sources',
					multiple: true
				},

				unreferenced: {
					label: mw.msg( 'pagetriage-tags-unreferenced-label' ),
					tag: 'unreferenced',
					desc: mw.msg( 'pagetriage-tags-unreferenced-desc' ),
					params: {
						date: param.date
					},
					position: 'top',
					dest: 'sources',
					multiple: true
				},

				stub: {
					label: mw.msg( 'pagetriage-tags-stub-label' ),
					tag: 'stub',
					desc: mw.msg( 'pagetriage-tags-stub-desc' ),
					params: { },
					position: 'bottom',
					dest: 'structure',
					multiple: false
				},

				uncategorised: {
					label: mw.msg( 'pagetriage-tags-uncategorised-label' ),
					tag: 'uncategorised',
					desc: mw.msg( 'pagetriage-tags-uncategorised-desc' ),
					params: {
						date: param.date
					},
					position: 'categories',
					dest: 'metadata',
					multiple: false
				}
			}
		},

		cleanup: {
			label: mw.msg( 'pagetriage-tags-cat-cleanup-label' ),
			tags: {
				cleanup: {
					label: mw.msg( 'pagetriage-tags-cleanup-label' ),
					tag: 'cleanup',
					desc: mw.msg( 'pagetriage-tags-cleanup-desc' ),
					params: {
						date: param.date,
						reason: $.extend( true, {}, param.reason )
					},
					position: 'top',
					multiple: true
				},

				copyedit: {
					label: mw.msg( 'pagetriage-tags-copyedit-label' ),
					tag: 'copy edit',
					desc: mw.msg( 'pagetriage-tags-copyedit-desc' ),
					params: {
						date: param.date,
						'for': $.extend( true, {}, param.for )
					},
					position: 'top',
					multiple: true
				},

				expertsubject: {
					label: mw.msg( 'pagetriage-tags-expertsubject-label' ),
					tag: 'expert',
					desc: mw.msg( 'pagetriage-tags-expertsubject-desc' ),
					params: {
						date: param.date,
						reason: $.extend( true, {}, param.reason )
					},
					position: 'top',
					multiple: true
				},

				prose: {
					label: mw.msg( 'pagetriage-tags-prose-label' ),
					tag: 'prose',
					desc: mw.msg( 'pagetriage-tags-prose-desc' ),
					params: {
						date: param.date
					},
					position: 'top',
					multiple: true
				},

				roughtranslation: {
					label: mw.msg( 'pagetriage-tags-roughtranslation-label' ),
					tag: 'rough translation',
					desc: mw.msg( 'pagetriage-tags-roughtranslation-desc' ),
					params: { },
					position: 'top',
					multiple: false
				}
			}
		},

		metadata: {
			label: mw.msg( 'pagetriage-tags-cat-metadata-label' ),
			tags: {
				deadend: {
					label: mw.msg( 'pagetriage-tags-deadend-label' ),
					tag: 'dead end',
					desc: mw.msg( 'pagetriage-tags-deadend-desc' ),
					params: {
						date: param.date
					},
					position: 'top',
					multiple: true
				},

				externallinks: {
					label: mw.msg( 'pagetriage-tags-externallinks-label' ),
					tag: 'external links',
					desc: mw.msg( 'pagetriage-tags-externallinks-desc' ),
					params: {
						date: param.date
					},
					position: 'top',
					multiple: true
				},

				catimprove: {
					label: mw.msg( 'pagetriage-tags-catimprove-label' ),
					tag: 'cat improve',
					desc: mw.msg( 'pagetriage-tags-catimprove-desc' ),
					params: {
						date: param.date
					},
					position: 'bottom',
					multiple: false
				},

				orphan: {
					label: mw.msg( 'pagetriage-tags-orphan-label' ),
					tag: 'orphan',
					desc: mw.msg( 'pagetriage-tags-orphan-desc' ),
					params: {
						date: param.date
					},
					position: 'top',
					multiple: true
				},

				overlinked: {
					label: mw.msg( 'pagetriage-tags-overlinked-label' ),
					tag: 'overlinked',
					desc: mw.msg( 'pagetriage-tags-overlinked-desc' ),
					params: {
						date: param.date
					},
					position: 'top',
					multiple: true
				},

				uncategorised: {
					label: mw.msg( 'pagetriage-tags-uncategorised-label' ),
					tag: 'uncategorised',
					desc: mw.msg( 'pagetriage-tags-uncategorised-desc' ),
					params: {
						date: param.date
					},
					position: 'categories',
					multiple: false
				}
			}
		},

		neutrality: {
			label: mw.msg( 'pagetriage-tags-cat-neutrality-label' ),
			tags: {
				advert: {
					label: mw.msg( 'pagetriage-tags-advert-label' ),
					tag: 'advert',
					desc: mw.msg( 'pagetriage-tags-advert-desc' ),
					params: {
						date: param.date
					},
					position: 'top',
					multiple: true
				},

				autobiography: {
					label: mw.msg( 'pagetriage-tags-autobiography-label' ),
					tag: 'autobiography',
					desc: mw.msg( 'pagetriage-tags-autobiography-desc' ),
					params: {
						date: param.date
					},
					position: 'top',
					multiple: true
				},

				coi: {
					label: mw.msg( 'pagetriage-tags-coi-label' ),
					tag: 'coi',
					desc: mw.msg( 'pagetriage-tags-coi-desc' ),
					params: {
						date: param.date
					},
					position: 'top',
					multiple: true
				},

				peacock: {
					label: mw.msg( 'pagetriage-tags-peacock-label' ),
					tag: 'peacock',
					desc: mw.msg( 'pagetriage-tags-peacock-desc' ),
					params: {
						date: param.date
					},
					position: 'top',
					multiple: true
				},

				pov: {
					label: mw.msg( 'pagetriage-tags-pov-label' ),
					tag: 'pov',
					desc: mw.msg( 'pagetriage-tags-pov-desc' ),
					params: {
						date: param.date
					},
					position: 'top',
					multiple: true
				},

				weasel: {
					label: mw.msg( 'pagetriage-tags-weasel-label' ),
					tag: 'weasel',
					desc: mw.msg( 'pagetriage-tags-weasel-desc' ),
					params: {
						date: param.date
					},
					position: 'top',
					multiple: true
				}
			}
		},

		sources: {
			label: mw.msg( 'pagetriage-tags-cat-sources-label' ),
			tags: {
				disputed: {
					label: mw.msg( 'pagetriage-tags-disputed-label' ),
					tag: 'disputed',
					desc: mw.msg( 'pagetriage-tags-disputed-desc' ),
					params: {
						date: param.date
					},
					position: 'top',
					multiple: true
				},

				linkrot: {
					label: mw.msg( 'pagetriage-tags-linkrot-label' ),
					tag: 'linkrot',
					desc: mw.msg( 'pagetriage-tags-linkrot-desc' ),
					params: {
						date: param.date
					},
					position: 'top',
					multiple: true
				},

				citationstyle: {
					label: mw.msg( 'pagetriage-tags-citationstyle-label' ),
					tag: 'citation style',
					desc: mw.msg( 'pagetriage-tags-citationstyle-desc' ),
					params: {
						date: param.date,
						details: $.extend( true, {}, param.details )
					},
					position: 'top',
					multiple: true
				},

				hoax: {
					label: mw.msg( 'pagetriage-tags-hoax-label' ),
					tag: 'hoax',
					desc: mw.msg( 'pagetriage-tags-hoax-desc' ),
					params: {
						date: param.date
					},
					position: 'top',
					multiple: true
				},

				morefootnotes: {
					label: mw.msg( 'pagetriage-tags-morefootnotes-label' ),
					tag: 'more footnotes',
					desc: mw.msg( 'pagetriage-tags-morefootnotes-desc' ),
					params: {
						date: param.date,
						blp: $.extend( true, {}, param.blp )
					},
					position: 'top',
					multiple: true
				},

				refimprove: {
					label: mw.msg( 'pagetriage-tags-refimprove-label' ),
					tag: 'refimprove',
					desc: mw.msg( 'pagetriage-tags-refimprove-desc' ),
					params: {
						date: param.date
					},
					position: 'top',
					multiple: true
				},

				blpsources: {
					label: mw.msg( 'pagetriage-tags-blpsources-label' ),
					tag: 'BLP sources',
					desc: mw.msg( 'pagetriage-tags-blpsources-desc' ),
					params: {
						date: param.date
					},
					position: 'top',
					multiple: true
				},

				nofootnotes: {
					label: mw.msg( 'pagetriage-tags-nofootnotes-label' ),
					tag: 'no footnotes',
					desc: mw.msg( 'pagetriage-tags-nofootnotes-desc' ),
					params: {
						date: param.date
					},
					position: 'top',
					multiple: true
				},

				unreferenced: {
					label: mw.msg( 'pagetriage-tags-unreferenced-label' ),
					tag: 'unreferenced',
					desc: mw.msg( 'pagetriage-tags-unreferenced-desc' ),
					params: {
						date: param.date
					},
					position: 'top',
					multiple: true
				},

				originalresearch: {
					label: mw.msg( 'pagetriage-tags-originalresearch-label' ),
					tag: 'original research',
					desc: mw.msg( 'pagetriage-tags-originalresearch-desc' ),
					params: {
						date: param.date
					},
					position: 'top',
					multiple: true
				},

				primarysources: {
					label: mw.msg( 'pagetriage-tags-primarysources-label' ),
					tag: 'primary sources',
					desc: mw.msg( 'pagetriage-tags-primarysources-desc' ),
					params: {
						date: param.date,
						blp: $.extend( true, {}, param.blp )
					},
					position: 'top',
					multiple: true
				},

				onesource: {
					label: mw.msg( 'pagetriage-tags-onesource-label' ),
					tag: 'one source',
					desc: mw.msg( 'pagetriage-tags-onesource-desc' ),
					params: {
						date: param.date
					},
					position: 'top',
					multiple: true
				}
			}
		},

		structure: {
			label: mw.msg( 'pagetriage-tags-cat-structure-label' ),
			tags: {
				condense: {
					label: mw.msg( 'pagetriage-tags-condense-label' ),
					tag: 'condense',
					desc: mw.msg( 'pagetriage-tags-condense-desc' ),
					params: { },
					position: 'top',
					multiple: true
				},

				leadmissing: {
					label: mw.msg( 'pagetriage-tags-leadmissing-label' ),
					tag: 'lead missing',
					desc: mw.msg( 'pagetriage-tags-leadmissing-desc' ),
					params: {
						date: param.date
					},
					position: 'top',
					multiple: true
				},

				leadrewrite: {
					label: mw.msg( 'pagetriage-tags-leadrewrite-label' ),
					tag: 'lead rewrite',
					desc: mw.msg( 'pagetriage-tags-leadrewrite-desc' ),
					params: {
						date: param.date,
						reason: $.extend( true, {}, param.reason )
					},
					position: 'top',
					multiple: true
				},

				leadtoolong: {
					label: mw.msg( 'pagetriage-tags-leadtoolong-label' ),
					tag: 'lead too long',
					desc: mw.msg( 'pagetriage-tags-leadtoolong-desc' ),
					params: {
						date: param.date
					},
					position: 'top',
					multiple: true
				},

				leadtooshort: {
					label: mw.msg( 'pagetriage-tags-leadtooshort-label' ),
					tag: 'lead too short',
					desc: mw.msg( 'pagetriage-tags-leadtooshort-desc' ),
					params: {
						date: param.date
					},
					position: 'top',
					multiple: true
				},

				cleanupreorganise: {
					label: mw.msg( 'pagetriage-tags-cleanupreorganise-label' ),
					tag: 'cleanup-reorganise',
					desc: mw.msg( 'pagetriage-tags-cleanupreorganise-desc' ),
					params: {
						date: param.date
					},
					position: 'top',
					multiple: true
				},

				sections: {
					label: mw.msg( 'pagetriage-tags-sections-label' ),
					tag: 'sections',
					desc: mw.msg( 'pagetriage-tags-sections-desc' ),
					params: {
						date: param.date
					},
					position: 'top',
					multiple: true
				},

				stub: {
					label: mw.msg( 'pagetriage-tags-stub-label' ),
					tag: 'stub',
					desc: mw.msg( 'pagetriage-tags-stub-desc' ),
					params: { },
					position: 'bottom',
					multiple: false
				},

				verylong: {
					label: mw.msg( 'pagetriage-tags-verylong-label' ),
					tag: 'very long',
					desc: mw.msg( 'pagetriage-tags-verylong-desc' ),
					params: {
						date: param.date
					},
					position: 'top',
					multiple: true
				}
			}
		},

		unwantedcontent: {
			label: mw.msg( 'pagetriage-tags-cat-unwantedcontent-label' ),
			tags: {
				closeparaphrasing: {
					label: mw.msg( 'pagetriage-tags-closeparaphrasing-label' ),
					tag: 'close paraphrasing',
					desc: mw.msg( 'pagetriage-tags-closeparaphrasing-desc' ),
					params: {
						date: param.date,
						source: $.extend( true, {}, param.source ),
						free: $.extend( true, {}, param.free )
					},
					position: 'top',
					multiple: false
				},

				copypaste: {
					label: mw.msg( 'pagetriage-tags-copypaste-label' ),
					tag: 'copypaste',
					desc: mw.msg( 'pagetriage-tags-copypaste-desc' ),
					params: {
						date: param.date,
						url: $.extend( true, {}, param.url )
					},
					position: 'top',
					multiple: false
				},

				nonfree: {
					label: mw.msg( 'pagetriage-tags-nonfree-label' ),
					tag: 'non-free',
					desc: mw.msg( 'pagetriage-tags-nonfree-desc' ),
					params: { },
					position: 'top',
					multiple: false
				},

				notability: {
					label: mw.msg( 'pagetriage-tags-notability-label' ),
					tag: 'notability',
					desc: mw.msg( 'pagetriage-tags-notability-desc' ),
					params: {
						date: param.date
					},
					position: 'top',
					multiple: true
				}
			}
		},

		writingstyle: {
			label: mw.msg( 'pagetriage-tags-cat-writingstyle-label' ),
			tags: {
				confusing: {
					label: mw.msg( 'pagetriage-tags-confusing-label' ),
					tag: 'confusing',
					desc: mw.msg( 'pagetriage-tags-confusing-desc' ),
					params: {
						date: param.date,
						reason: $.extend( true, {}, param.reason )
					},
					position: 'top',
					multiple: true
				},

				essaylike: {
					label: mw.msg( 'pagetriage-tags-essaylike-label' ),
					tag: 'essay-like',
					desc: mw.msg( 'pagetriage-tags-essaylike-desc' ),
					params: {
						date: param.date
					},
					position: 'top',
					multiple: true
				},

				fansite: {
					label: mw.msg( 'pagetriage-tags-fansite-label' ),
					tag: 'fansite',
					desc: mw.msg( 'pagetriage-tags-fansite-desc' ),
					params: {
						date: param.date
					},
					position: 'top',
					multiple: true
				},

				notenglish: {
					label: mw.msg( 'pagetriage-tags-notenglish-label' ),
					tag: 'not english',
					desc: mw.msg( 'pagetriage-tags-notenglish-desc' ),
					params: { },
					position: 'top',
					multiple: false
				},

				technical: {
					label: mw.msg( 'pagetriage-tags-technical-label' ),
					tag: 'technical',
					desc: mw.msg( 'pagetriage-tags-technical-desc' ),
					params: {
						date: param.date
					},
					position: 'top',
					multiple: true
				},

				tense: {
					label: mw.msg( 'pagetriage-tags-tense-label' ),
					tag: 'tense',
					desc: mw.msg( 'pagetriage-tags-tense-desc' ),
					params: {
						date: param.date
					},
					position: 'top',
					multiple: true
				},

				tone: {
					label: mw.msg( 'pagetriage-tags-tone-label' ),
					tag: 'tone',
					desc: mw.msg( 'pagetriage-tags-tone-desc' ),
					params: {
						date: param.date
					},
					position: 'top',
					multiple: true
				}
			}
		},

		moretags: {
			label: mw.msg( 'pagetriage-tags-cat-moretags-label' ),
			tags: {
				allplot: {
					label: mw.msg( 'pagetriage-tags-allplot-label' ),
					tag: 'allplot',
					desc: mw.msg( 'pagetriage-tags-allplot-desc' ),
					params: {
						date: param.date
					},
					position: 'top',
					multiple: true
				},

				fiction: {
					label: mw.msg( 'pagetriage-tags-fiction-label' ),
					tag: 'fiction',
					desc: mw.msg( 'pagetriage-tags-fiction-desc' ),
					params: {
						date: param.date
					},
					position: 'top',
					multiple: true
				},

				inuniverse: {
					label: mw.msg( 'pagetriage-tags-inuniverse-label' ),
					tag: 'in-universe',
					desc: mw.msg( 'pagetriage-tags-inuniverse-desc' ),
					params: {
						date: param.date
					},
					position: 'top',
					multiple: true
				},

				outofdate: {
					label: mw.msg( 'pagetriage-tags-outofdate-label' ),
					tag: 'out of date',
					desc: mw.msg( 'pagetriage-tags-outofdate-desc' ),
					params: {
						date: param.date
					},
					position: 'top',
					multiple: true
				},

				overlydetailed: {
					label: mw.msg( 'pagetriage-tags-overlydetailed-label' ),
					tag: 'overly detailed',
					desc: mw.msg( 'pagetriage-tags-overlydetailed-desc' ),
					params: {
						date: param.date
					},
					position: 'top',
					multiple: true
				},

				plot: {
					label: mw.msg( 'pagetriage-tags-plot-label' ),
					tag: 'plot',
					desc: mw.msg( 'pagetriage-tags-plot-desc' ),
					params: {
						date: param.date
					},
					position: 'top',
					multiple: true
				},

				recentism: {
					label: mw.msg( 'pagetriage-tags-recentism-label' ),
					tag: 'recentism',
					desc: mw.msg( 'pagetriage-tags-recentism-desc' ),
					params: {
						date: param.date
					},
					position: 'top',
					multiple: true
				},

				toofewopinions: {
					label: mw.msg( 'pagetriage-tags-toofewopinions-label' ),
					tag: 'too few opinions',
					desc: mw.msg( 'pagetriage-tags-toofewopinions-desc' ),
					params: {
						date: param.date
					},
					position: 'top',
					multiple: false
				},

				unbalanced: {
					label: mw.msg( 'pagetriage-tags-unbalanced-label' ),
					tag: 'unbalanced',
					desc: mw.msg( 'pagetriage-tags-unbalanced-desc' ),
					params: {
						date: param.date
					},
					position: 'top',
					multiple: true
				},

				update: {
					label: mw.msg( 'pagetriage-tags-update-label' ),
					tag: 'update',
					desc: mw.msg( 'pagetriage-tags-update-desc' ),
					params: {
						date: param.date
					},
					position: 'top',
					multiple: true
				}
			}
		}

	};

}( jQuery, mediaWiki ) );
// </nowiki>

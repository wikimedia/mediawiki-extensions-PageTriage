// view for the article list


// TODO: find a way to insert these templates raw with RL instead of appending them to
// the DOM with javascript later (which is lame)

// This template is repeated many times for each element in list view
// TODO there's some words in here which need to become wfMsg() calls
$( "#backboneTemplates" ).append('<script type="text/template" id="listItemTemplate"> \
		<div class="mwe-pt-article-row"> \
			<div class="mwe-pt-status-icon"> \
			<% if ( afd_status == "1" || blp_prod_status == "1" || csd_status == "1" || prod_status == "1" ) { %> \
				[DEL] <!-- deleted --> \
			<% } else if ( patrol_status == "1" ) { %> \
				[PTR] <!-- patrolled --> \
			<% } else { %> \
				[NEW] <!-- not patrolled --> \
			<% } %> \
			</div> \
			<div class="mwe-pt-info-pane"> \
			<div> \
			<span class="mwe-pt-page-title"><%= title %></span> \
			<span class="mwe-pt-histlink">(hist)</span> \
			<span class="mwe-pt-metadata"> \
			&#xb7; \
			bytes \
			&#xb7; \
			edits \
			&#xb7; \
			images \
			&#xb7; \
			categories \
			</span> \
			</div> \
			<div class="mwe-pt-snippet"> \
			<%= snippet %> \
			</div> \
			</div> \
		</div> \
		<br/> \
	</script>'
);

// instantiate the collection of articles
var articles = new ArticleList;

// single list item
// TODO: move this into its own file?
ListItem = Backbone.View.extend( {
	tagName: "div",
	template: _.template( $( "#listItemTemplate" ).html() ),

	// listen for changes to the model and re-render.
	initialize: function() {
		this.model.bind('change', this.render, this);
		this.model.bind('destroy', this.remove, this);
	},

	render: function() {
		// insert the template into the document.  fill with the current model.
		this.$el.html( this.template( this.model.toJSON() ) );
		return this;
	}		

} );

// overall list view
// currently, this is the main application view.
ListView = Backbone.View.extend( {

	initialize: function() {

		// these events are triggered when items are added to the articles collection
		articles.bind( 'add', this.addOne, this );
		articles.bind( 'reset', this.addAll, this );
		
		// this event is triggered when the collection finishes loading.
		articles.bind( 'all', this.render, this );

		// on init, make sure to load the contents of the collection.
		articles.fetch();
	},

	render: function() {
		// TODO: refresh the view (show/hide the parts that aren't attached to the ListItem view)
	},

	// add a single article to the list
	addOne: function( article ) {
		// pass in the specific article instance
		var view = new ListItem( { model: article } );
		this.$( "#listView" ).append( view.render().el );
	},

	// add all the items in the articles collection
	addAll: function() {
		$("#listView").empty(); // remove the spinner before displaying.
		articles.each( this.addOne );
    }

} );

var list = new ListView();

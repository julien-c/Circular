Circular.Collections.Posts = Backbone.Collection.extend({
	url: "api/posts",
	model: Circular.Models.Post,
	initialize: function(){
		Circular.events.on('ui:posts:sort', this.refreshPostsOrder, this);
	},
	refreshPostsOrder: function(order){
		// Refresh sorting order after jQuery UI sorting:
		// (Too bad Underscore doesn't have a function to order an array of objects by an array of one of their attributes)
		// @see  https://github.com/documentcloud/underscore/issues/692
		this.models = this.sortBy(function(post){
			return _.indexOf(order, post.id);
		});
		Circular.events.trigger('posts:sort', order);
	},
	groupByUser: function(){
		var users = {};
		_.each(Circular.users, function(value, key){
			users[key] = [];
		});
		var out = this.groupBy(function(post){
			return post.get('user');
		});
		out = _.extend(users, out);
		return out;
	}
});


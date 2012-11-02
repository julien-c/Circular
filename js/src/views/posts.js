Circular.Views.Posts = Backbone.View.extend({
	el: ".posts",
	template:         $("#tpl-post").html(),
	templateTab:      $("#tpl-tab").html(),
	templateTimeline: $("#tpl-timeline").html(),
	events: {
		"mousedown .options .btn":  "hidetooltip",
		"click .deletepost":        "deletepost",
		"click .postnow":           "postnow",
		"click .suggestpost":       "suggestpost",
		"click .tab":               "selectTab"
	},
	initialize: function(){
		this.renderTabs();
		
		// Initial fetch:
		this.collection.on('reset', this.render, this);
		
		this.collection.on('add', this.renderPost, this);
		this.collection.on('poststimes:refresh', this.renderPostsTimesAndHeadings, this);
		
		this.collection.on('reset',  this.checkEmptyViewAndTabCount, this);
		this.collection.on('add',    this.checkEmptyViewAndTabCount, this);
		this.collection.on('remove', this.checkEmptyViewAndTabCount, this);
	},
	render: function(posts){
		posts.each(this.renderPost, this);
		this.renderDateHeadings();
	},
	renderPost: function(post){
		if (post.has('time')) {
			this.formatTime(post);
		}
		var output = Mustache.render(this.template, post.toJSON());
		this.timeline(post.get('user')).find(".timeline").append(output);
	},
	formatTime: function(post){
		// This function is called on each post, after the initial fetch, or after each times refresh.
		// (Maybe it should be in the model instead.)
		post.local12HourTimeAndDay = Circular.Utils.Time.local12HourTimeAndDayFromTimeStamp(post.get('time'));
		// Is there any way to add a local-only attribute? (that won't ever be sent to server)
		post.set({formattedTime: Circular.Utils.Time.format12HourTime(post.local12HourTimeAndDay)});
	},
	renderPostsTimesAndHeadings: function(){
		this.renderPostsTimes();
		this.renderDateHeadings();
	},
	renderPostsTimes: function(){
		this.collection.each(this.renderPostTime, this);
	},
	renderPostTime: function(post){
		this.formatTime(post);
		// As the View contains all posts, we have to query the whole document's DOM here:
		$(".post-time", "#post-"+post.id).text(post.get('formattedTime'));
	},
	renderDateHeadings: function(){
		// Let's first clear the Date headers (except Today which should always be here):
		this.$(".timeline li.heading").not(".today").remove();
		
		_.each(this.collection.groupByUser(), function(posts, user){
			
			var day = 0;
			
			_.each(posts, function(post){
				// We assume the collection is ordered by time (it always should be)
				if (post.local12HourTimeAndDay.day > day) {
					day = post.local12HourTimeAndDay.day;
					$("#post-"+post.id).before('<li class="heading"><h3>' + Circular.Utils.Time.formatDay(day) + '</h3></li>');
				}
			});
		});
	},
	hidetooltip: function(e){
		$(e.currentTarget).tooltip('hide');
	},
	deletepost: function(e){
		e.preventDefault();
		var post = $(e.currentTarget).closest("li.post");
		var id = post.attr("data-id");
		post.fadeOut('fast', function(){
			$(this).remove();
		});
		new Circular.Views.Alert({type: "", content: "This post has been deleted"});
		// Proceed to delete:
		this.collection.get(id).destroy();
	},
	postnow: function(e){
		e.preventDefault();
		var post = $(e.currentTarget).closest("li.post");
		var id = post.attr("data-id");
		post.fadeOut('fast', function(){
			$(this).remove();
		});
		setTimeout(function(){
			new Circular.Views.Alert({type: "alert-success", content: "This post has been successfully queued to be posted to Twitter"});
		}, 500);
		
		// Update post's time on the server to "now":
		this.collection.get(id).set('time', 'now').save();
		// Finally, remove from collection:
		this.collection.remove(this.collection.get(id));
	},
	suggestpost: function(){
		Circular.events.trigger('posts:suggestpost');
	},
	checkEmptyViewAndTabCount: function(){
		this.checkEmptyView();
		this.tabCount();
	},
	checkEmptyView: function(){
		_.each(this.collection.groupByUser(), function(posts, user){
			if (posts.length) {
				this.timeline(user).find(".empty-timeline").hide();
				this.timeline(user).find(".timeline").show();
			}
			else {
				this.timeline(user).find(".empty-timeline").show();
				this.timeline(user).find(".timeline").hide();
			}
		}, this);
	},
	tabCount: function(){
		_.each(this.collection.groupByUser(), function(posts, user){
			this.tab(user).find(".tab-count").text(posts.length);
			this.tab(user).find(".tab-count-name").text(
				(posts.length == 1) ? "post" : "posts"
			);
		},
		this);
	},
	renderTabs: function(){
		// Tabs:
		_.each(Circular.users, function(user){
			var output = Mustache.render(this.templateTab, user);
			this.$("ul.tabs").append(output);
		}, this);
		// Tab contents (timelines):
		_.each(Circular.users, function(user){
			var html = Mustache.render(this.templateTimeline, user);
			var output = $(html);
			if (!user.selected) {
				output.hide();
			}
			this.$(".tab-inner").append(output);
		}, this);
		// Initialize jQuery UI sortable:
		this.$("ul.timeline").sortable({
			items: "li.post",
			placeholder: "ui-state-highlight",
			handle: ".sort-handle"
		});
		this.$("ul.timeline").bind("sortstop", function(){
			// New order for our ids:
			var order = $(this).sortable('toArray', {attribute: "data-id"});
			Circular.events.trigger('ui:posts:sort', order);
		});
	},
	selectTab: function(e){
		this.$('.tab').removeClass('selected');
		$(e.currentTarget).addClass('selected');
		var id = $(e.currentTarget).attr('data-id');
		this.$('.timeline-wrapper').hide();
		this.timeline(id).show();
		Circular.events.trigger('tab:selected', id);
	},
	timeline: function(user){
		return this.$("#timeline-"+user);
	},
	tab: function(user){
		return this.$("#tab-"+user);
	}
});


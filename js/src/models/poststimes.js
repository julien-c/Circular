Circular.Models.PostsTimes = Backbone.Model.extend({
	urlRoot: "api/times",
	initialize: function(options){
		this.posts    = options.posts;
		this.settings = options.settings;
		
		Circular.events.on('posts:sort', this.computePostsTimes, this);
		Circular.events.on('settings:saved', this.computePostsTimes, this);
		this.posts.on('add', this.computePostsTimes, this);
		this.posts.on('remove', this.computePostsTimes, this);
	},
	computePostsTimes: function(){
		_.each(this.posts.groupByUser(), function(posts, user){
			this.computePostsTimesForUser(posts, user);
		}, this);
		
		this.posts.trigger('poststimes:refresh');
		this.save();
	},
	computePostsTimesForUser: function(posts, user){
		var date = new Date();
		var secondsUpToNowToday = Circular.Utils.Time.secondsFrom24HourTime({
			hour: date.getHours(),
			minute: date.getMinutes()
		});
		
		var times = this.settings.get('times');
		// Let's find which scheduled time is the next one:
		var i = _.sortedIndex(_.map(times, Circular.Utils.Time.secondsFrom12HourTime), secondsUpToNowToday);
		// So times[i] is the next scheduled time.
		// More precisely: times[i % times.length]
		
		var day = 0;
		
		_.each(posts, function(post){
			if ((i % times.length == 0) && (i > 0)){
				day++;
			}
			// This post must be scheduled to be sent in `day` days, at time `times[i % times.length]`:
			
			// Now compute the UNIX timestamp for this time:
			var then = new Date(date.getFullYear(), date.getMonth(), date.getDate() + day, 0, 0, Circular.Utils.Time.secondsFrom12HourTime(times[i % times.length]));
			// We use the fact that this method "expands" parameters.
			// (ie: you can specify 32 days or 5000 seconds, parameters will overflow)
			// @todo: Check that this is documented and standard.
			var timestamp = Circular.Utils.Time.generateUnixTimestamp(then);
			
			post.set({time: timestamp});
			
			i++;
		});
	},
	save: function(){
		// Only keep id and time from the Posts collection:
		var posts = [];
		this.posts.each(function(post){
			posts.push({
				id:    post.id,
				time:  post.get('time')
			});
		});
		
		// Now save to server:
		$.ajax({
			url: this.urlRoot,
			type: 'POST',
			data: JSON.stringify({posts: posts}), 
			contentType: 'application/json',
			dataType: 'json',
			error: function(){
				new Circular.Views.Alert({type: "alert-error", content: "Something went wrong while updating your posts..."});
			}
		});
	}
});


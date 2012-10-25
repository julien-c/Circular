window.Tampon = {
	Models:      {},
	Collections: {},
	Views:       {},
	events:      _.clone(Backbone.Events),
	account:     null,
	users:       null
};


Tampon.Utils = {
	getParameterByName: function(name) {
		name = name.replace(/[\[]/, "\\\[").replace(/[\]]/, "\\\]"); var regexS = "[\\?&]" + name + "=([^&#]*)"; var regex = new RegExp(regexS); var results = regex.exec(window.location.search); if(results == null) return ""; else return decodeURIComponent(results[1].replace(/\+/g, " "));
	}
	// @see http://stackoverflow.com/a/901144/593036
};


Tampon.Utils.Time = {
	generateUnixTimestamp: function(then){
		return Math.floor(then.getTime() / 1000);
	},
	secondsFrom12HourTime: function(time){
		// 12am = Midnight, 1am, ..., 12pm = Noon, 1pm, ...
		// @see http://en.wikipedia.org/wiki/12-hour_clock
		var hour = time.hour % 12;
		var seconds = time.minute*60 + hour*60*60;
		if (time.ampm == "pm") {
			seconds += 12*60*60;
		}
		return seconds;
	},
	secondsFrom24HourTime: function(time){
		return time.minute*60 + time.hour*60*60;
	},
	get12HourTimeFrom24HourTime: function(hour, minute){
		var _12HourTime = {
			hour: hour % 12,
			minute: minute,
			ampm: (hour < 12) ? "am" : "pm" 
		};
		if (_12HourTime.hour == 0) {
			_12HourTime.hour = 12;
		}
		return _12HourTime;
	},
	format12HourTime: function(time){
		var minute = time.minute;
		if (minute < 10) {
			minute = '0' + minute;
		}
		return time.hour + ":" + minute + " " + time.ampm;
	},
	formatDay: function(day){
		// `day` is the offset in days from today (local time)
		var heading;
		if (day == 0){
			heading = "Today";
		}
		else if (day == 1){
			heading = "Tomorrow";
		}
		else {
			var date = new Date(+new Date + 1000*60*60*24*day);
			var DaysOfWeek  = ["Sunday", "Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday"];
			var DaysOfMonth = ["","1st","2nd","3rd","4th","5th","6th","7th","8th","9th","10th","11th","12th","13th","14th","15th","16th","17th","18th","19th","20th","21st","22nd","23rd","24th","25th","26th","27th","28th","29th","30th","31st"];
			var Months      = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];
			heading = DaysOfWeek[date.getDay()] + " " + DaysOfMonth[date.getDate()] + " " + Months[date.getMonth()];
		}
		return heading;
	},
	local12HourTimeAndDayFromTimeStamp: function(timestamp){
		var date = new Date(timestamp * 1000);
		var now  = new Date();
		var local12HourTime = this.get12HourTimeFrom24HourTime(date.getHours(), date.getMinutes());
		
		// Now let's compute the day offset (local time):
		var localMidnightToday = new Date(now.getFullYear(), now.getMonth(), now.getDate());
		var localMidnightTodayTimestamp = this.generateUnixTimestamp(localMidnightToday);
		var day = Math.floor((timestamp - localMidnightTodayTimestamp) / (24*60*60));
		
		local12HourTime.day = day;
		
		return local12HourTime;
	}
};


Tampon.Views.Alert = Backbone.View.extend({
	el: "#alerts",
	template: $("#tpl-alert").html(),
	initialize: function(options){
		this.type = options.type;
		this.content = options.content;
		this.render();
	},
	render: function(){
		var output = Mustache.render(
			this.template, 
			{type: this.type, content: this.content}
		);
		$(output).prependTo(this.$el).delay(2000).fadeOut();
		return this;
	}
});


Tampon.Models.Settings = Backbone.Model.extend({
	initialize: function(){
		this.fetch();
		
		// Defaults:
		if (!this.has('timezone')) {
			// Notice: The timezone is not actually used right now.
			this.set('timezone', this.defaultTimezone());
		}
		if (!this.has('times')) {
			this.set('times', this.defaultTimes());
		}
		
		this.save();
	},
	defaultTimezone: function(){
		// Automatic Timezone Detection
		var timezone = jstz.determine();
		return timezone.name();
	},
	defaultTimes: function(){
		// Like Buffer, we create 4 random times, 2 in the AM, 2 in the PM:
		var times = [
			{hour:9,  minute:Math.floor(Math.random()*60), ampm:"am"},
			{hour:11, minute:Math.floor(Math.random()*60), ampm:"am"},
			{hour:3,  minute:Math.floor(Math.random()*60), ampm:"pm"},
			{hour:5,  minute:Math.floor(Math.random()*60), ampm:"pm"}
		];
		return times;
	},
	// @ see http://stackoverflow.com/questions/11817015/is-it-good-practice-to-override-fetch-and-save-directly-from-the-model
	fetch: function(){
		this.set('timezone', localStorage['timezone']);
		if (localStorage['times']) {
			this.set('times', JSON.parse(localStorage['times']));
		}
	},
	save: function(){
		localStorage['timezone'] = this.get('timezone');
		localStorage['times']    = JSON.stringify(this.get('times'));
	}
});


Tampon.Views.Settings = Backbone.View.extend({
	el: $(".settings"),
	templateSettingsTime: $("#tpl-time").html(),
	events: {
		"click #addtime":                    "addtime",
		"click .settings-time button.close": "removetime",
		"click #savesettings":               "saveSettings"
	},
	initialize: function(){
		this.render();
	},
	addtime: function(e){
		e.preventDefault();
		var output = Mustache.render(this.templateSettingsTime, {});
		this.$(".times").append(output);
	},
	removetime: function(e){
		e.preventDefault();
		$(e.target).closest(".settings-time").fadeOut('fast', function(){
			$(this).remove();
		});
	},
	render: function(){
		// Set frontend to current values from model:
		this.renderTimezone();
		this.renderTimes();
	},
	renderTimezone: function(){
		this.$("select.timezone").val(this.model.get('timezone'));
	},
	renderTimes: function(){
		// First clear displayed times (as we use this function when refreshing times after saving, to render them ordered):
		this.$(".times").empty();
		
		_.each(this.model.get('times'), function(time){
			var output = Mustache.render(this.templateSettingsTime, {});
			var out = $(output);
			$("select.hour", out).val(time.hour);
			$("select.minute", out).val(time.minute);
			$("select.ampm", out).val(time.ampm);
			this.$(".times").append(out);
		}, this);
	},
	saveSettings: function(e){
		var btn = $(e.target);
		Tampon.events.trigger('button:setstate', btn, 'loading');
		setTimeout(function(){
			Tampon.events.trigger('button:setstate', btn, 'reset');
		}, 500);
		
		this.model.set('timezone', $("select.timezone").val());
		
		var times = [];
		this.$("p.settings-time").each(function(){
			times.push({
				hour:   $(this).find("select.hour").val(),
				minute: $(this).find("select.minute").val(),
				ampm:   $(this).find("select.ampm").val(),
			});
		});
		// Sort times by chronological order:
		times = _.sortBy(times, Tampon.Utils.Time.secondsFrom12HourTime);
		this.model.set('times', times);
		
		// Actual saving:
		this.model.save();
		
		// Refresh displayed times:
		this.renderTimes();
		
		// Trigger event so that posting times in the Timeline view can be refreshed:
		Tampon.events.trigger('settings:saved');
	}
});


Tampon.Models.Post = Backbone.Model.extend({
	initialize: function(attributes){
		if (this.get('status') == "") {
			this.set('status', this.randomQuote());
		}
		// We don't set the time now as it will be set after refreshing posting times
	},
	randomQuote: function(){
		var quotes = [
			"All wrong-doing arises because of mind. If mind is transformed can wrong-doing remain?",
			"Ambition is like love, impatient both of delays and rivals.",
			"An idea that is developed and put into action is more important than an idea that exists only as an idea.",
			"Thousands of candles can be lit from a single candle, and the life of the candle will not be shortened.",
			"Do not dwell in the past, do not dream of the future, concentrate the mind on the present moment.",
			"Do not overrate what you have received, nor envy others. He who envies others does not obtain peace of mind.",
			"The mind is everything. What you think you become.",
			"No one saves us but ourselves. No one can and no one may. We ourselves must walk the path.",
			"Peace comes from within. Do not seek it without.",
			"The only real failure in life is not to be true to the best one knows.",
			"The way is not in the sky. The way is in the heart.",
			"There are only two mistakes one can make along the road to truth; not going all the way, and not starting.",
			"There has to be evil so that good can prove its purity above it."
		];
		
		quotes = _.map(quotes, function(quote){
			quote += " ~ via @TamponApp";
			return quote;
		});
		
		return quotes[Math.floor(Math.random()*quotes.length)];
	}
});


Tampon.Collections.Posts = Backbone.Collection.extend({
	url: "api/posts",
	model: Tampon.Models.Post,
	initialize: function(){
		Tampon.events.on('ui:posts:sort', this.refreshPostsOrder, this);
	},
	refreshPostsOrder: function(order){
		// Refresh sorting order after jQuery UI sorting:
		// (Too bad Underscore doesn't have a function to order an array of objects by an array of one of their attributes)
		// @see  https://github.com/documentcloud/underscore/issues/692
		this.models = this.sortBy(function(post){
			return _.indexOf(order, post.id);
		});
		Tampon.events.trigger('posts:sort', order);
	},
	groupByUser: function(){
		var users = {};
		_.each(Tampon.users, function(value, key){
			users[key] = [];
		});
		var out = this.groupBy(function(post){
			return post.get('user');
		});
		out = _.extend(users, out);
		return out;
	}
});


Tampon.Views.Composer = Backbone.View.extend({
	el: ".composer",
	templateAvatar: $("#tpl-profile").html(),
	events: {
		"click #postnow":                     "postnow",
		"click #addtoposts":                  "addtoposts",
		"keyup #textarea":                    "countdown",
		"change #textarea":                   "countdown",
		"click #addpicture":                  "toggleDropzone",
		"click .picturezone button.close":    "resetPicturezone",
		"click .profile":                     "toggleProfile"
	},
	initialize: function(){
		// Handle query string value (generated by the bookmarklet)
		// and update countdown accordingly:
		this.$("#textarea").val(Tampon.Utils.getParameterByName('p'));
		this.countdown();
		
		Tampon.events.on('loggedin', this.renderAvatars, this);
		Tampon.events.on('posts:suggestpost', this.suggestpost, this);
		Tampon.events.on('tab:selected', this.selectProfile, this);
		
		$(".dropzone").filedrop({
			url: "api/upload.php",
			allowedfiletypes: ['image/jpeg','image/png','image/gif'],
			dragOver: function(){ 
				$(this).addClass("over");
			},
			dragLeave: function(){ 
				$(this).removeClass("over");
			},
			drop: function(){
				$(this).removeClass("over");
			},
			uploadFinished: function(i, file, response, time) {
				$(".picturezone img").attr('src', response.thumbnail).data('picture', {url: response.url, thumbnail: response.thumbnail});
				$(".picturezone").show();
				$(".dropzone").hide();
			}
		});
	},
	renderAvatars: function(){
		this.$("#profiles").html('');
		_.each(Tampon.users, function(user){
			var output = Mustache.render(this.templateAvatar, user);
			if (user.selected) {
				output = $(output).attr('title', $(output).attr('data-title-selected'));
			}
			else {
				output = $(output).attr('title', $(output).attr('data-title-select'));
			}
			this.$("#profiles").append(output);
		}, this);
	},
	postdata: function(){
		var post = {status: this.$("#textarea").val()};
		if (this.$(".picturezone img").data('picture')) {
			post.picture = this.$(".picturezone img").data('picture');
		}
		return post;
	},
	getPostsToSave: function(){
		var post = this.postdata();
		return _.map(this.getSelectedProfiles(), function(id){
			var p = _.clone(post);
			p.user = id;
			return p;
		});
	},
	postnow: function(e){
		var btn = $(e.target);
		Tampon.events.trigger('button:setstate', btn, 'loading');
		setTimeout(function(){
			Tampon.events.trigger('button:setstate', btn, 'reset');
			new Tampon.Views.Alert({type: "alert-success", content: "This post has been successfully queued to be posted to Twitter"});
		}, 500);
		
		_.each(this.getPostsToSave(), function(post){
			post.time = "now";
			var postnow = new Tampon.Models.Post(post);
			// As this model is outside of the collection, we have to specify a urlRoot to save it to 
			// (it's actually the same endpoint as the collection itself):
			postnow.urlRoot = "api/posts";
			postnow.save();
		});
		this.resetComposer();
	},
	addtoposts: function(){
		_.each(this.getPostsToSave(), function(post){
			this.collection.create(post, {wait: true, error: this.errorSave});
			// Wait for the server to respond with a Mongo id.
		}, this);
		this.resetComposer();
	},
	errorSave: function(){
		new Tampon.Views.Alert({type: "alert-error", content: "Something went wrong while saving your post..."});
	},
	countdown: function(e){
		if (e) {
			var len = $(e.target).val().length;
		}
		else {
			var len = this.$("#textarea").val().length;
		}
		if (len == 0) {
			this.$(".countdown").html("");
		}
		else {
			this.$(".countdown").html(140 - len);
			if (len > 130) {
				this.$(".countdown").addClass("warning");
			}
			else {
				this.$(".countdown").removeClass("warning");
			}
			if (len > 140) {
				this.$("#postnow, #addtoposts").prop("disabled", true);
			}
			else {
				this.$("#postnow, #addtoposts").prop("disabled", false);
			}
		}
	},
	resetComposer: function(){
		this.$("#textarea").val("");
		this.countdown();
		this.resetPicturezone();
	},
	resetPicturezone: function(){
		this.$(".picturezone img").attr('src', "").data('picture', null);
		this.$(".picturezone").hide();
	},
	suggestpost: function(){
		var post = new Tampon.Models.Post();
		this.$("#textarea").val(post.randomQuote());
		this.countdown();
	},
	toggleDropzone: function(e){
		$(e.currentTarget).tooltip('hide');
		this.$(".dropzone").slideToggle('fast');
	},
	toggleProfile: function(e){
		var profile = $(e.currentTarget);
		profile.toggleClass('selected');
		if (profile.hasClass('selected')) {
			profile.attr('title', profile.attr('data-title-selected'));
		}
		else {
			profile.attr('title', profile.attr('data-title-select'));
		}
		// Refresh Bootstrap tooltip:
		// Should this be fixed in Bootstrap?
		profile.tooltip('hide');
		profile.data('tooltip', false);
		profile.tooltip('show');
		// Update Tampon.users itself:
		var id = profile.attr('data-id');
		Tampon.users[id].selected = (Tampon.users[id].selected) ? undefined : 'selected';
	},
	selectProfile: function(id){
		// Select this and only this profile:
		_.each(Tampon.users, function(user){
			user.selected = undefined;
		});
		Tampon.users[id].selected = 'selected';
		this.renderAvatars();
	},
	getSelectedProfiles: function(){
		return _.pluck(
			_.filter(Tampon.users, function(user){
				return user.selected == 'selected';
			}),
			'id'
		);
	}
});


Tampon.Views.Posts = Backbone.View.extend({
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
		Tampon.events.on('loggedin', this.renderTabs, this);
		
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
		post.local12HourTimeAndDay = Tampon.Utils.Time.local12HourTimeAndDayFromTimeStamp(post.get('time'));
		// Is there any way to add a local-only attribute? (that won't ever be sent to server)
		post.set({formattedTime: Tampon.Utils.Time.format12HourTime(post.local12HourTimeAndDay)});
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
					$("#post-"+post.id).before('<li class="heading"><h3>' + Tampon.Utils.Time.formatDay(day) + '</h3></li>');
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
		new Tampon.Views.Alert({type: "", content: "This post has been deleted"});
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
			new Tampon.Views.Alert({type: "alert-success", content: "This post has been successfully queued to be posted to Twitter"});
		}, 500);
		
		// Update post's time on the server to "now":
		this.collection.get(id).set('time', 'now').save();
		// Finally, remove from collection:
		this.collection.remove(this.collection.get(id));
	},
	suggestpost: function(){
		Tampon.events.trigger('posts:suggestpost');
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
		_.each(Tampon.users, function(user){
			var output = Mustache.render(this.templateTab, user);
			this.$("ul.tabs").append(output);
		}, this);
		// Tab contents (timelines):
		_.each(Tampon.users, function(user){
			var html = Mustache.render(this.templateTimeline, user);
			var output = $(html);
			if (!user.selected) {
				output.hide();
			}
			this.$(".tab-inner").append(output);
		}, this);
	},
	selectTab: function(e){
		this.$('.tab').removeClass('selected');
		$(e.currentTarget).addClass('selected');
		var id = $(e.currentTarget).attr('data-id');
		this.$('.timeline-wrapper').hide();
		this.timeline(id).show();
		Tampon.events.trigger('tab:selected', id);
	},
	timeline: function(user){
		return this.$("#timeline-"+user);
	},
	tab: function(user){
		return this.$("#tab-"+user);
	}
});


Tampon.Models.PostsTimes = Backbone.Model.extend({
	urlRoot: "api/times",
	initialize: function(options){
		this.posts    = options.posts;
		this.settings = options.settings;
		
		Tampon.events.on('posts:sort', this.computePostsTimes, this);
		Tampon.events.on('settings:saved', this.computePostsTimes, this);
		this.posts.on('add', this.computePostsTimes, this);
		this.posts.on('remove', this.computePostsTimes, this);
	},
	computePostsTimes: function(){
		
		var date = new Date();
		var secondsUpToNowToday = Tampon.Utils.Time.secondsFrom24HourTime({
			hour: date.getHours(),
			minute: date.getMinutes()
		});
		
		var times = this.settings.get('times');
		// Let's find which scheduled time is the next one:
		var i = _.sortedIndex(_.map(times, Tampon.Utils.Time.secondsFrom12HourTime), secondsUpToNowToday);
		// So times[i] is the next scheduled time.
		// More precisely: times[i % times.length]
		
		var day = 0;
		
		this.posts.each(function(post){
			if ((i % times.length == 0) && (i > 0)){
				day++;
			}
			// This post must be scheduled to be sent in `day` days, at time `times[i % times.length]`:
			
			// Now compute the UNIX timestamp for this time:
			var then = new Date(date.getFullYear(), date.getMonth(), date.getDate() + day, 0, 0, Tampon.Utils.Time.secondsFrom12HourTime(times[i % times.length]));
			// We use the fact that this method "expands" parameters.
			// (ie: you can specify 32 days or 5000 seconds, parameters will overflow)
			// @todo: Check that this is documented and standard.
			var timestamp = Tampon.Utils.Time.generateUnixTimestamp(then);
			
			post.set({time: timestamp});
			
			i++;
		});
		
		this.posts.trigger('poststimes:refresh');
		this.save();
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
				new Tampon.Views.Alert({type: "alert-error", content: "Something went wrong while updating your posts..."});
			}
		});
	}
});


Tampon.App = {
	/* This is simple app-level jQuery stuff for which Backbone seems overkill */
	initialize: function() {
		
		var spinner = new Spinner({width: 3, color: '#222', speed: 1, trail: 60, hwaccel: true}).spin($('#spinner').get(0));
		
		/* Sign in with Twitter */
		
		$.get("api/oauth.php", null, function(data){
			spinner.stop();
			if (data && data.id) {
				// We have a signed-in account
				$(".not-logged-in").hide();
				$(".logged-in").show();
				// Store logged in data:
				Tampon.account = data.id;
				Tampon.users   = data.users;
				// Select the first user by default:
				// Too bad there's no _.first() function that works on an object-type collection.
				var i = 1;
				_.each(Tampon.users, function(user){
					if (i == 1) {
						user.selected = 'selected';
					}
					i++;
				});
				// Trigger "logged in" event:
				Tampon.events.trigger('loggedin');
			}
		});
		
		$(".signin").click(function(){
			$.ajax({
				url: "api/oauth.php?start=1", 
				success: function(data){
					if (data && data.authurl) {
						// Start the OAuth dance:
						window.location = data.authurl;
					}
				},
				error: function(data){
					new Tampon.Views.Alert({type: "alert-error", content: "Unknown Twitter API error"});
				}
			});
		});
		
		$(".logout").click(function(e){
			e.preventDefault();
			$.ajax({
				url: "api/oauth.php?wipe=1",
				success: function(){
					window.location.reload();
				}
			}); 
		});
		
		/* Main Navigation */
		
		$(".link-to-settings").live('click', function(){
			$(".composer, .posts").hide();
			$(".settings").show();
		});
		
		$(".link-to-dashboard").live('click', function(){
			$(".composer, .posts").show();
			$(".settings").hide();
		});
		
		/* Twitter Bootstrap JS */
		
		$("body").tooltip({
			selector: '[rel=tooltip]',
			animation: false
		});
		
		Tampon.events.on('button:setstate', function(btn, state){
			btn.button(state);
		});
		
		/* jQuery UI sortable */
		
		$("ul.timeline").sortable({
			items: "li.post",
			placeholder: "ui-state-highlight",
			handle: ".sort-handle"
		});
		
		$("ul.timeline").bind("sortstop", function(){
			// New order for our ids:
			var order = $(this).sortable('toArray', {attribute: "data-id"});
			Tampon.events.trigger('ui:posts:sort', order);
		});
		
		/* jQuery Hotkeys */
		
		$("#textarea").bind('keydown', 'meta+return', function(){
			$("#addtoposts").click();
		});
	}
}







$(document).ready(function(){
	
	/* Initialize App */
	
	Tampon.App.initialize();
	
	
	
	/* Initialize Settings */
	
	var settings = new Tampon.Models.Settings();
	
	new Tampon.Views.Settings({model: settings});
	
	
	/* Initialize Composer and Posts */
	
	var posts = new Tampon.Collections.Posts();
	Tampon.events.on('loggedin', function(){
		posts.fetch();
	});
	
	new Tampon.Views.Composer({collection: posts});
	
	new Tampon.Views.Posts({collection: posts});
	
	
	/* Initialize PostsTimes */
	
	var poststimes = new Tampon.Models.PostsTimes({posts: posts, settings: settings});
	
});



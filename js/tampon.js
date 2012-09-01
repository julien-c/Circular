window.Tampon = {
	Models:      {},
	Collections: {},
	Views:       {},
	events:      _.clone(Backbone.Events),
	user:        null
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
	
	format12HourTime: function(time){
		var minute = time.minute;
		if (minute < 10) {
			minute = '0' + minute;
		}
		return time.hour + ":" + minute + " " + time.ampm;
	},
	
	formatDay: function(day){
		var heading;
		if (day == 1){
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
		this.$el.prepend(output);
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
		
		// Refresh posting times in the Timeline view:
		// refreshPostingTimes();
		// @fixme
	}
});


Tampon.Models.Post = Backbone.Model.extend({
	initialize: function(attributes){
		if (this.get('content') == "") {
			this.set('content', this.randomQuote());
		}
		this.set('user_id',          Tampon.user.id);
		this.set('user_screen_name', Tampon.user.screen_name);
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
	url: "api/posts.php",
	model: Tampon.Models.Post,
	initialize: function(){
		Tampon.events.on('ui:posts:sort', this.refreshPostsOrder, this);
		
		this.fetch();
	},
	refreshPostsOrder: function(order){
		// Refresh sorting order after jQuery UI sorting:
		// (Too bad underscore doesn't have a function to order an array of objects by an array of one of their attributes)
		// @see  https://github.com/documentcloud/underscore/issues/692
		this.models = this.sortBy(function(post){
			return _.indexOf(order, post.id);
		});
		Tampon.events.trigger('posts:sort', order);
	}
});


Tampon.Views.Composer = Backbone.View.extend({
	el: ".composer",
	events: {
		"click #postnow":      "postnow",
		"click #addtoposts":   "addtoposts",
		"keydown #textarea":   "countdown"
	},
	initialize: function(){
		// Handling of query string value (generated by the bookmarklet):
		// We trigger a keydown so that the countdown is updated.
		this.$("#textarea").val(Tampon.Utils.getParameterByName('p')).keydown();
		
		Tampon.events.on('loggedin', this.renderAvatar, this);
	},
	renderAvatar: function(){
		this.$(".avatar img").attr('src', Tampon.user.profile_image_url).attr('title', Tampon.user.name);
	},
	postnow: function(e){
		var btn = $(e.target);
		Tampon.events.trigger('button:setstate', btn, 'loading');
		setTimeout(function(){
			Tampon.events.trigger('button:setstate', btn, 'reset');
			new Tampon.Views.Alert({type: "alert-success", content: "This post has been successfully queued to be posted to Twitter"});
		}, 500);
		var postnow = new Tampon.Models.Post({content: this.$("#textarea").val(), time:"now"});
		postnow.save();
		this.$("#textarea").val("").keydown();
	},
	addtoposts: function(){
		this.collection.create({content: this.$("#textarea").val()}, {wait: true, error: this.errorSave});
		// Wait for the server to respond with a Mongo id.
		this.$("#textarea").val("").keydown();
	},
	errorSave: function(){
		new Tampon.Views.Alert({type: "alert-error", content: "Something went wrong while saving your post..."});
	},
	countdown: function(e){
		var len = $(e.target).val().length;
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
	}
});


Tampon.Views.Posts = Backbone.View.extend({
	el: ".posts",
	template: $("#tpl-post").html(),
	events: {
		"click .deletepost": "deletepost"
	},
	initialize: function(){
		this.collection.on('add', this.renderPost, this);
		this.collection.on('poststimes:refresh', this.renderPostsTimes, this);
	},
	renderPost: function(post){
		var output = Mustache.render(this.template, post.toJSON());
		this.$(".timeline").append(output);
	},
	deletepost: function(e){
		e.preventDefault();
		$(e.currentTarget).tooltip('hide');
		$(e.currentTarget).closest("li.post").fadeOut('fast', function(){
			$(this).remove();
		});
		new Tampon.Views.Alert({type: "", content: "This post has been deleted"});
	},
	renderPostsTimes: function(){
		
		// Let's first clear the Date headers (except Today which should always be here):
		this.$(".timeline li.heading").not(".today").remove();
		
		var day = 0;
		
		console.log(this.collection);
		
		this.collection.each(function(post){
			if (post.local12HourTime.day > day) {
				this.$("#post-"+post.id).before('<li class="heading"><h3>' + Tampon.Utils.Time.formatDay(day) + '</h3></li>');
				day = post.local12HourTime.day;
			}
			
			$(".post-time", "#post-"+post.id).text(Tampon.Utils.Time.format12HourTime(post.local12HourTime));
		});
	}
});


Tampon.Models.PostsTimes = Backbone.Model.extend({
	url: "api/times.php",
	initialize: function(options){
		this.posts    = options.posts;
		this.settings = options.settings;
		
		Tampon.events.on('posts:sort', this.computePostsTimes, this);
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
			post.local12HourTime     = times[i % times.length];
			post.local12HourTime.day = day;
			
			// Now compute the UNIX timestamp for this time:
			var then = new Date(date.getFullYear(), date.getMonth(), date.getDate() + day, 0, 0, Tampon.Utils.Time.secondsFrom12HourTime(times[i % times.length]));
			// We use the fact that this method "expands" parameters.
			// @todo: Check that this is documented and standard.
			var timestamp = Tampon.Utils.Time.generateUnixTimestamp(then);
			
			post.time = timestamp;
			
			i++;
		});
		
		this.posts.trigger('poststimes:refresh');
	}
});



/*
function refreshPostingTimes(){

	var date = new Date();
	var secondsUpToNowToday = Tampon.Utils.Time.secondsFrom24HourTime({
		hour: date.getHours(),
		minute: date.getMinutes()
	});


	var times = JSON.parse(localStorage['times']);
	// Let's find which scheduled time is the next one:
	var i = _.sortedIndex(_.map(times, Tampon.Utils.Time.secondsFrom12HourTime), secondsUpToNowToday);
	// So times[i] is the next scheduled time.
	// More precisely: times[i % times.length]

	// Let's also clear the Date headers (except Today which should always be here):
	$(".timeline li.heading").not(".today").remove();
	var day = 0;

	$(".timeline li.post").each(function(){
		if ((i % times.length == 0) && (i > 0)){
			day++;
			$(this).before('<li class="heading"><h3>' + Tampon.Utils.Time.formatDay(day) + '</h3></li>');
		}
		$(".post-time", this).text(Tampon.Utils.Time.format12HourTime(times[i % times.length]));
		
		// Now compute the UNIX timestamp for this time:
		var then = new Date(date.getFullYear(), date.getMonth(), date.getDate() + day, 0, 0, Tampon.Utils.Time.secondsFrom12HourTime(times[i % times.length]));
		// We use the fact that this method "expands" parameters.
		// @todo: Check that this is documented and standard.
		var timestamp = Tampon.Utils.Time.generateUnixTimestamp(then);
		$(this).attr("data-timestamp", timestamp);
		
		i++;
	});


	// Temporary DOM adapter (should go when moving to Backbone):
	var posts = [];
	$(".timeline li.post").each(function(){
		posts.push({
			id:          $(this).attr('data-id'),
			timestamp:   $(this).attr('data-timestamp')
		});
	});


	$.post("api/times.php", {posts: posts}, null, "json").error(function(){
		new Tampon.Views.Alert({type: "alert-error", content: "Something went wrong while updating your posts..."});
	});
}
*/




Tampon.App = {
	/* This is simple app-level jQuery stuff for which Backbone seems overkill */
	initialize: function() {
		
		var spinner = new Spinner({width: 3, color: '#222', speed: 1, trail: 60, hwaccel: true}).spin($('#spinner').get(0));
		
		/* Sign in with Twitter */
		
		$.get("api/oauth.php", null, function(data){
			spinner.stop();
			if (data && data.id_str) {
				// We have a signed-in Twitter user
				$(".not-logged-in").hide();
				$(".logged-in").show();
				// Store logged in user's data into Tampon.user:
				Tampon.user = data;
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
	
	new Tampon.Views.Composer({collection: posts});
	
	new Tampon.Views.Posts({collection: posts});
	
	
	/* Initialize PostsTimes */
	
	var poststimes = new Tampon.Models.PostsTimes({posts: posts, settings: settings});
	
});



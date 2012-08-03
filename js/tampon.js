window.Tampon = {
	Models:      {},
	Collections: {},
	Views:       {}
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
			var DaysOfWeek = ["Sunday", "Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday"];
			var DaysOfMonth = ["","1st","2nd","3rd","4th","5th","6th","7th","8th","9th","10th","11th","12th","13th","14th","15th","16th","17th","18th","19th","20th","21st","22nd","23rd","24th","25th","26th","27th","28th","29th","30th","31st"];
			var Months = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];
			heading = DaysOfWeek[date.getDay()] + " " + DaysOfMonth[date.getDate()] + " " + Months[date.getMonth()];
		}
		return heading;
	}
};


Tampon.Views.Alert = Backbone.View.extend({
	template: $("#tpl-alert").html(),
	initialize: function(options){
		this.setElement("#main");
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


$(document).ready(function(){
	
	var spinner = new Spinner({width: 3, color: '#222', speed: 1, trail: 60, hwaccel: true}).spin($('#spinner').get(0));
	var user;
	
	/* Sign in with Twitter */
	
	$.get("api/oauth.php", null, function(data){
		spinner.stop();
		if (data && data.id_str) {
			// We have a signed-in Twitter user
			$(".not-logged-in").hide();
			$(".logged-in").show();
			user = data;
			$(".avatar img", ".composer").attr('src', user.profile_image_url).attr('title', user.name);
		}
	});
	
	
	$(".signin").click(function(){
		$.ajax({
			url: "api/oauth.php?start=1", 
			success: function(data){
				if (data && data.authurl) {
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
	
	
	/* Handling of query string value (Bookmarklet) */
	
	
	$("#textarea").val(Tampon.Utils.getParameterByName('p'));
	
	
	/* Navigation */
	
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
	
	
	
	$("#postnow").click(function(){
		var btn = $(this);
		btn.button('loading');
		setTimeout(function(){
			btn.button('reset');
			new Tampon.Views.Alert({type: "alert-success", content: "This post has been successfully queued to be posted to Twitter"});
		}, 500);
	});
	
	
	
	
	
	/* jQuery UI sortable */
	
	$("ul.timeline").sortable({
		items: "li.post",
		placeholder: "ui-state-highlight",
		handle: ".sort-handle"
	});
	
	$("ul.timeline").bind("sortstop", function(){
		refreshPostingTimes();
	});
	
	
	/* Posts and Timeline */
	
	$("#textarea").bind('keydown', 'meta+return', function(){
		$("#addtoposts").click();
	});
	
	$("#addtoposts").click(function(){
		
		var content = $("#textarea").val();
		if (content == "") {
			content = randomQuote();
		}
		
		
		var post = {
			content:          content,
			user_id:          user.id,
			user_screen_name: user.screen_name
			// We don't set the time now as it will be set after refreshing posting times
		};
		
		$.post("api/post.php", post, function(data){
			post.id = data.id;
			var output = Mustache.render($("#tpl-post").html(), post);
			$(".timeline").append(output);
			refreshPostingTimes();
			// Finally, clear textarea:
			$("#textarea").val("");
		}, "json").error(function(){
			new Tampon.Views.Alert({type: "alert-error", content: "Something went wrong while saving your post..."});
		});
		
	});
	
	
	var randomQuote = function(){
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
	};
	
	
	$(".deletepost").live('click', function(e){
		e.preventDefault();
		$(this).tooltip('hide');
		$(this).closest("li.post").fadeOut('fast', function(){
			$(this).remove();
		});
		new Tampon.Views.Alert({type: "", content: "This post has been deleted"});
	});
	
	
	
	
	/* Settings */
	
	$("#addtime").click(function(e){
		e.preventDefault();
		var output = Mustache.render($("#tpl-time").html(), {});
		$(".times").append(output);
	});
	
	$(".settings-time button.close").live('click', function(e){
		e.preventDefault();
		$(this).closest(".settings-time").fadeOut('fast', function(){
			$(this).remove();
		});
	});
	
	var initSettings = function(){
		// Defaults:
		if (localStorage['timezone'] == null) {
			// Automatic Timezone Detection
			var timezone = jstz.determine();
			localStorage['timezone'] = timezone.name();
		}
		
		if (localStorage['times'] == null) {
			// Like Buffer, we create 4 random times, 2 in the AM, 2 in the PM
			var times = [
				{hour:9,  minute:Math.floor(Math.random()*60), ampm:"am"},
				{hour:11, minute:Math.floor(Math.random()*60), ampm:"am"},
				{hour:3,  minute:Math.floor(Math.random()*60), ampm:"pm"},
				{hour:5,  minute:Math.floor(Math.random()*60), ampm:"pm"}
			];
			
			localStorage['times'] = JSON.stringify(times);
		}
		
		// Finally, set frontend to current values:
		$("select.timezone").val(localStorage['timezone']);
		displayTimes();
	};
	
	initSettings();
	
	
	function displayTimes(){
		// First clear displayed times (as we use this function when refreshing times after saving):
		$(".times").empty();
		
		var times = JSON.parse(localStorage['times']);
		_.each(times, function(time){
			var output = Mustache.render($("#tpl-time").html(), {});
			var out = $(output);
			$("select.hour", out).val(time.hour);
			$("select.minute", out).val(time.minute);
			$("select.ampm", out).val(time.ampm);
			$(".times").append(out);
		});
	}
	
	$("#savesettings").click(function(){
		var btn = $(this);
		btn.button('loading');
		setTimeout(function(){
			btn.button('reset');
		}, 500);
		
		// Actual saving:
		localStorage['timezone'] = $("select.timezone").val();
		var times = [];
		$("p.settings-time").each(function(){
			times.push({
				hour:   $(this).find("select.hour").val(),
				minute: $(this).find("select.minute").val(),
				ampm:   $(this).find("select.ampm").val(),
			});
		});
		// Store times sorted by chronological order:
		times = _.sortBy(times, Tampon.Utils.Time.secondsFrom12HourTime);
		localStorage['times'] = JSON.stringify(times);
		// Refresh displayed times in Settings:
		displayTimes();
		// Refresh posting times in the Timeline view:
		refreshPostingTimes();
	});
	
	
	
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
	
	
	
	
});



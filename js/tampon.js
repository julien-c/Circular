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
				new DisplayAlert({type: "alert-error", content: "Unknown Twitter API error"});
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
	
	var DisplayAlert = function(options){
		var output = Mustache.render(
			$("#tpl-alert").html(), 
			options
		);
		$("#main").prepend(output);
	};
	
	$("#postnow").click(function(){
		var btn = $(this);
		btn.button('loading');
		setTimeout(function(){
			btn.button('reset');
			new DisplayAlert({type: "alert-success", content: "This post has been successfully queued to be posted to Twitter"});
		}, 500);
	});
	
	
	
	
	
	/* jQuery UI sortable */
	
	$("ul.timeline").sortable({
		items: "li.post",
		placeholder: "ui-state-highlight",
		handle: ".sort-handle"
	});
	
	
	
	/* Posts and Timeline */
	
	$("#addtoposts").click(function(){
		
		var post = {
			time: "5:30 PM",
			content: randomQuote()
		};
		
		$.post("api/post.php", post);
		
		post.id = "jhkkkjhkhkjhk";
		
		var output = Mustache.render($("#tpl-post").html(), post);
		$(".timeline").append(output);
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
		new DisplayAlert({type: "", content: "This post has been deleted"});
	});
	
	
	
	
	/* Settings */
	
	$("#addtime").click(function(e){
		e.preventDefault();
		var output = Mustache.render($("#tpl-time").html(), {});
		$(".times").append(output);
	});
	
	$(".send-time button.close").live('click', function(e){
		e.preventDefault();
		$(this).closest(".send-time").fadeOut('fast', function(){
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
		// First clear times (as we use this function when refreshing times after saving):
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
		$("p.send-time").each(function(){
			times.push({
				hour:   $(this).find("select.hour").val(),
				minute: $(this).find("select.minute").val(),
				ampm:   $(this).find("select.ampm").val(),
			});
		});
		// Store times sorted by chronological order:
		times = _.sortBy(times, SecondsFromTime);
		localStorage['times'] = JSON.stringify(times);
		displayTimes();
	});
	
	function SecondsFromTime(time){
		var seconds = time.minute*60 + time.hour*60*60;
		if (time.ampm == "pm") {
			seconds += 12*60*60;
		}
		return seconds;
	}
});



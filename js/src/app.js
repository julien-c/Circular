Circular.App = {
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
				Circular.account = data.id;
				Circular.users   = data.users;
				// Select the first user by default:
				// Too bad there's no _.first() function that works on an object-type collection.
				var i = 1;
				_.each(Circular.users, function(user){
					if (i == 1) {
						user.selected = 'selected';
					}
					i++;
				});
				// Trigger "logged in" event:
				Circular.events.trigger('loggedin');
			}
			else {
				// Else, we'll just update the posts counter:
				Circular.App.updateCounter();
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
					new Circular.Views.Alert({type: "alert-error", content: "Unknown Twitter API error"});
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
		
		Circular.events.on('button:setstate', function(btn, state){
			btn.button(state);
		});
		
		
		/* jQuery Hotkeys */
		
		$("#textarea").bind('keydown', 'meta+return', function(){
			$("#addtoposts").click();
		});
		
		/* Event tracking */
		Circular.events.on('track:post', function(){
			Circular.App.trackEvent('Posts', 'add');
		});
	},
	trackEvent: function(category, action, label, value, noninteraction){
		// Wrapper to Google Analytics' event tracking, if present on the page:
		if (typeof _gaq !== 'undefined') {
			_gaq.push(['_trackEvent', category, action]);
		}
	},
	updateCounter: function(){
		$.getJSON("api/counter", function(data){
			$('.counter').text(data.count);
		});
	}
}


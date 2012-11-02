$(document).ready(function(){
	
	// Initialize App
	Circular.App.initialize();
	
	
	Circular.events.on('loggedin', function(){
		
		// Initialize Settings
		var settings = new Circular.Models.Settings();
		new Circular.Views.Settings({model: settings});
		
		// Initialize Composer and Posts
		var posts = new Circular.Collections.Posts();
		posts.fetch();
		new Circular.Views.Composer({collection: posts});
		new Circular.Views.Posts({collection: posts});
		
		// Initialize PostsTimes
		var poststimes = new Circular.Models.PostsTimes({posts: posts, settings: settings});
	});
	
});


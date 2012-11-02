Circular.Models.Settings = Backbone.Model.extend({
	urlRoot: "api/settings",
	initialize: function(){
		this.fetch();
		
		// Defaults:
		if (!this.has('timezone')) {
			// Notice: The timezone is not actually used right now.
			this.set('timezone', this.defaultTimezone()).saveLocalSettings();
		}
		if (!this.has('times')) {
			this.set('times', this.defaultTimes()).saveLocalSettings();
		}
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
		$.ajax({
			url: this.urlRoot, 
			type: 'GET',
			context: this,
			success: function(data){
				this.set('email', data.email);
				Circular.events.trigger('settings:fetched');
			}
		});
	},
	save: function(){
		this.saveLocalSettings();
		this.saveServerSettings();
	},
	saveLocalSettings: function(){
		localStorage['timezone'] = this.get('timezone');
		localStorage['times']    = JSON.stringify(this.get('times'));
	},
	saveServerSettings: function(){
		Circular.Utils.postJSON(this.urlRoot, {email: this.get('email')});
	}
});


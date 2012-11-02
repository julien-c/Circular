Circular.Views.Settings = Backbone.View.extend({
	el: $(".settings"),
	templateSettingsTime: $("#tpl-time").html(),
	events: {
		"click #addtime":                    "addtime",
		"click .settings-time button.close": "removetime",
		"click #savesettings":               "saveSettings"
	},
	initialize: function(){
		Circular.events.on('settings:fetched', this.render, this);
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
		this.renderEmail();
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
	renderEmail: function(){
		this.$('input.email').val(this.model.get('email'));
	},
	saveSettings: function(e){
		var btn = $(e.target);
		Circular.events.trigger('button:setstate', btn, 'loading');
		setTimeout(function(){
			Circular.events.trigger('button:setstate', btn, 'reset');
		}, 500);
		
		this.model.set('timezone', this.$("select.timezone").val());
		
		var times = [];
		this.$("p.settings-time").each(function(){
			times.push({
				hour:   $(this).find("select.hour").val(),
				minute: $(this).find("select.minute").val(),
				ampm:   $(this).find("select.ampm").val(),
			});
		});
		// Sort times by chronological order:
		times = _.sortBy(times, Circular.Utils.Time.secondsFrom12HourTime);
		this.model.set('times', times);
		
		this.model.set('email', this.$('input.email').val());
		
		// Actual saving:
		this.model.save();
		// Refresh displayed times in Settings:
		this.renderTimes();
		// Trigger event so that posting times in the Timeline view can be refreshed:
		Circular.events.trigger('settings:saved');
	}
});


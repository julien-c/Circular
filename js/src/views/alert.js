Circular.Views.Alert = Backbone.View.extend({
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


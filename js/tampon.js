$(document).ready(function(){
	
	
	$("body").tooltip({
		selector: '[rel=tooltip]',
		animation: false
	});
	
	
	$("ul.timeline").sortable({
		items: "li.update",
		placeholder: "ui-state-highlight",
		handle: ".sort-handle"
	});
	
	
	$("#addtoposts").click(function(){
		
		var post = {
			id: "jhkkkjhkhkjhk",
			time: "5:30 PM",
			content: "Test test test"
		};
		
		var output = Mustache.render($("#tpl-post").html(), post);
		$(".timeline").append(output);
	});
	
});

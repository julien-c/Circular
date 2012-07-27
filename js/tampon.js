$(document).ready(function(){
	
	
	$('.tip').tooltip({animation: false});
	
	$('ul.timeline').sortable({
		items: "li.update",
		placeholder: "ui-state-highlight",
		handle: ".sort-handle"
	});
	
});

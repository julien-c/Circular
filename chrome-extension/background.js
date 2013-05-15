chrome.browserAction.onClicked.addListener(function(tab) {
  var action_url = "javascript:(function(){var d=document,w=window,e=w.getSelection,k=d.getSelection,x=d.selection,s=(e?e():(k)?k():(x?x.createRange().text:0)),l=d.location,e=encodeURIComponent,p=((e(s))?e(s):e(document.title))+' '+e(l.href);window.open('http://circular.io/?p='+p);})();";
  chrome.tabs.update(tab.id, {url: action_url});
});

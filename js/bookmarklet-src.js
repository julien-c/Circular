var d = document,
	w = window,
	e = w.getSelection,
	k = d.getSelection,
	x = d.selection,
	s = (e ? e() : (k) ? k() : (x ? x.createRange().text : 0)),
	l = d.location,
	e = encodeURIComponent,
	p = ((e(s)) ? e(s) : e(document.title)) + ' ' + e(l.href);

window.open('http://tamponapp.com/?p=' + p);


/* 

Output from http://ted.mielczarek.org/code/mozilla/bookmarklet.html :

<a href="javascript:(function(){var d=document,w=window,e=w.getSelection,k=d.getSelection,x=d.selection,s=(e?e():(k)?k():(x?x.createRange().text:0)),l=d.location,e=encodeURIComponent,p=((e(s))?e(s):e(document.title))+' '+e(l.href);window.open('http://tamponapp.com/?p='+p);})();">Add to Tampon</a>

*/
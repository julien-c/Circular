Circular.Utils = {
	getParameterByName: function(name) {
		// @see http://stackoverflow.com/a/901144/593036
		name = name.replace(/[\[]/, "\\\[").replace(/[\]]/, "\\\]"); var regexS = "[\\?&]" + name + "=([^&#]*)"; var regex = new RegExp(regexS); var results = regex.exec(window.location.search); if(results == null) return ""; else return decodeURIComponent(results[1].replace(/\+/g, " "));
	},
	postJSON: function(url, data){
		return $.ajax({
			url: url,
			type: 'POST',
			data: JSON.stringify(data), 
			contentType: 'application/json',
			dataType: 'json'
		});
	}
};

Circular.Utils.Time = {
	generateUnixTimestamp: function(then){
		return Math.floor(then.getTime() / 1000);
	},
	secondsFrom12HourTime: function(time){
		// 12am = Midnight, 1am, ..., 12pm = Noon, 1pm, ...
		// @see http://en.wikipedia.org/wiki/12-hour_clock
		var hour = time.hour % 12;
		var seconds = time.minute*60 + hour*60*60;
		if (time.ampm == "pm") {
			seconds += 12*60*60;
		}
		return seconds;
	},
	secondsFrom24HourTime: function(time){
		return time.minute*60 + time.hour*60*60;
	},
	get12HourTimeFrom24HourTime: function(hour, minute){
		var _12HourTime = {
			hour: hour % 12,
			minute: minute,
			ampm: (hour < 12) ? "am" : "pm" 
		};
		if (_12HourTime.hour == 0) {
			_12HourTime.hour = 12;
		}
		return _12HourTime;
	},
	format12HourTime: function(time){
		var minute = time.minute;
		if (minute < 10) {
			minute = '0' + minute;
		}
		return time.hour + ":" + minute + " " + time.ampm;
	},
	formatDay: function(day){
		// `day` is the offset in days from today (local time)
		var heading;
		if (day == 0){
			heading = "Today";
		}
		else if (day == 1){
			heading = "Tomorrow";
		}
		else {
			var date = new Date(+new Date + 1000*60*60*24*day);
			var DaysOfWeek  = ["Sunday", "Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday"];
			var DaysOfMonth = ["","1st","2nd","3rd","4th","5th","6th","7th","8th","9th","10th","11th","12th","13th","14th","15th","16th","17th","18th","19th","20th","21st","22nd","23rd","24th","25th","26th","27th","28th","29th","30th","31st"];
			var Months      = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];
			heading = DaysOfWeek[date.getDay()] + " " + DaysOfMonth[date.getDate()] + " " + Months[date.getMonth()];
		}
		return heading;
	},
	local12HourTimeAndDayFromTimeStamp: function(timestamp){
		var date = new Date(timestamp * 1000);
		var now  = new Date();
		var local12HourTime = this.get12HourTimeFrom24HourTime(date.getHours(), date.getMinutes());
		
		// Now let's compute the day offset (local time):
		var localMidnightToday = new Date(now.getFullYear(), now.getMonth(), now.getDate());
		var localMidnightTodayTimestamp = this.generateUnixTimestamp(localMidnightToday);
		var day = Math.floor((timestamp - localMidnightTodayTimestamp) / (24*60*60));
		
		local12HourTime.day = day;
		
		return local12HourTime;
	}
};


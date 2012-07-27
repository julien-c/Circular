<!DOCTYPE html>
<html lang="en">
	<head>
		<link href="bootstrap/css/bootstrap.css" rel="stylesheet">
		<link href="css/bootstrap.my.css" rel="stylesheet">
		<link href="css/tampon.css" rel="stylesheet">
	</head>
	<body>
		
		<div class="navbar navbar-fixed-top">
			<div class="navbar-inner">
				<div class="container">
					<a class="brand" href="#">
						Tampon
					</a>
				</div>
			</div>
		</div>
		
		<div class="container">
			<div class="hero-unit">
				<h1>Tampon, an open source Buffer app.</h1>
				<p>Stock up some great tweets and have them automatically shared throughout the day.</p>
				<p>
					<a class="btn btn-primary btn-large">
						Sign in with Twitter
					</a>
				</p>
			</div>
			
			
			
			<div class="composer">
				
				<div class="profile avatar twitter">
					<img src="http://a0.twimg.com/profile_images/2428060144/j9s79sbvn1teypf89qzq_normal.jpeg">
					<span></span>
				</div>
				
				<textarea class="input-xlarge" id="textarea" rows="3" placeholder="Write your post here"></textarea>
				
				<div class="form-actions">
					<button class="btn">Post now</button>
					<button type="submit" class="btn btn-primary">Add to Tampon</button>
				</div>
			</div>
			
			
			<div class="posts">
				<h2>Pending posts</h2>
				
				<ul class="timeline">
					<li class="heading"><h3>Today</h3></li>
					
					<li class="update">
						<span class="time-due">
							5:03 PM
						</span>
						<div class="update-body">
							Just as a mother would protect her only child with her life even so let one cultivate a boundless love towards all beings.
						</div>
						<div class="options">
							<div class="btn-group">
								<a class="btn tip" href="#" title="Drag to re-order"><i class="icon-move"></i></a>
								<a class="btn tip" href="#" title="Post this now"><i class="icon-play-circle"></i></a>
								<a class="btn tip" href="#" title="Delete this post"><i class="icon-remove"></i></a>
							</div>
						</div>
					</li>
					
					<li class="update">
						<span class="time-due">
							7:58 PM
						</span>
						<div class="update-body">
							Such is consciousness, such its origination, such its disappearance.
						</div>
						<div class="options">
							<div class="btn-group">
								<a class="btn" href="#"><i class="icon-move"></i></a>
								<a class="btn" href="#"><i class="icon-play-circle"></i></a>
								<a class="btn" href="#"><i class="icon-remove"></i></a>
							</div>
						</div>
					</li>
					
					<li class="heading"><h3>Tomorrow</h3></li>
					
					<li class="update">
						<span class="time-due">
							9:06 PM
						</span>
						<div class="update-body">
							Vision arose, insight arose, discernment arose, knowledge arose, illumination arose within me with regard to things never heard before
						</div>
						<div class="options">
							<div class="btn-group">
								<a class="btn" href="#"><i class="icon-move"></i></a>
								<a class="btn" href="#"><i class="icon-play-circle"></i></a>
								<a class="btn" href="#"><i class="icon-remove"></i></a>
							</div>
						</div>
					</li>
					
					<li class="update">
						<span class="time-due">
							11:13 PM
						</span>
						<div class="update-body">
							It is impossible to retain a past thought, to a seize future thought and even to hold onto a present thought.
						</div>
						<div class="options">
							<div class="btn-group">
								<a class="btn" href="#"><i class="icon-move"></i></a>
								<a class="btn" href="#"><i class="icon-play-circle"></i></a>
								<a class="btn" href="#"><i class="icon-remove"></i></a>
							</div>
						</div>
					</li>
				</ul>
			</div>
			
			
			<hr>
			
			<footer>
				<p>Powered by <a href="http://tamponapp.com">Tampon</a></p>
			</footer>
			
		</div>
		
		
		
		<script src="js/jquery-1.7.2.min.js"></script>
		<script src="bootstrap/js/bootstrap.js"></script>
		<script src="js/tampon.js"></script>
	</body>
</html>
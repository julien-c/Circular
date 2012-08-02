<?php

namespace Daemon;

require BASE_PATH . '/../extlib/tmhOAuth/tmhOAuth.php';
require BASE_PATH . '/../extlib/tmhOAuth/tmhUtilities.php';
require BASE_PATH . '/../api/config.php';


class ParallelTasks extends \Core_Daemon
{
	protected  $loop_interval = 5;

	/**
	 * The only plugin we're using is a simple file-based lock to prevent 2 instances from running
	 */
	protected function setup_plugins()
	{
		$this->plugin('Lock_File');
	}
	
	/**
	 * This is where you implement any once-per-execution setup code. 
	 * @return void
	 * @throws \Exception
	 */
	protected function setup()
	{

	}
	
	/**
	 * This is where you implement the tasks you want your daemon to perform. 
	 * This method is called at the frequency defined by loop_interval. 
	 *
	 * @return void
	 */
	protected function execute()
	{
		$currentUnixTime = self::getCurrentUnixTimestamp();
		
		
		
		$m = new \Mongo();
		
		
		// Step 1: move to the sending queue (`queue`) all posts (`posts`) that were scheduled to be sent before now.
		$posts = $m->tampon->posts->find(array('time' => array('$lte' => new \MongoDate($currentUnixTime))));
		
		foreach ($posts as $post) {
			$m->tampon->queue->insert($post);
			$m->tampon->posts->remove(array('_id' => $post['_id']));
			$this->log(sprintf(
				"Moved scheduled post %s to sending queue", 
				$post['_id']
			));
		}
		
		
		// Step 2: send queued posts (`queue`) to Twitter and move them to `archive`.
		$posts = $m->tampon->queue->find();
		
		foreach ($posts as $post) {
			
			// We use a "processing" flag to not try to send the same post multiple times (as tasks are asynchronous)
			// (or should be, anyways)
			
			if (!isset($post['processing'])) {
				
				$post['processing'] = true;
				$post['processing_since'] = self::getCurrentUnixTimestamp();
				$m->tampon->queue->update(array('_id' => $post['_id']), $post);
				
				// We can't use Task process forking for now because of a MongoDB PHP driver bug...
				// @see  https://jira.mongodb.org/browse/PHP-446
				// $this->task(new SendPostTask($post));
				
				
				
				// Send to Twitter:
				$tmhOAuth = new \tmhOAuth(array(
					'consumer_key'    => CONSUMER_KEY,
					'consumer_secret' => CONSUMER_SECRET,
					'user_token'      => $post['user_token'],
					'user_secret'     => $post['user_secret']
				));
				
				
				$code = $tmhOAuth->request('POST', $tmhOAuth->url('1/statuses/update'), array(
					'status' => $post['content']
				));
				
				// There is no special handling of API errors.
				// Right now we just dump the response to MongoDB
				
				$post['code'] = $code;
				$post['response'] = json_decode($tmhOAuth->response['response'], true);
				
				// Move this post to another collection named archive:
				unset($post['processing']);
				unset($post['processing_time']);
				$m = new \Mongo();
				$m->tampon->archive->insert($post);
				$m->tampon->queue->remove(array('_id' => $post['_id']));
				
				if ($code == 200) {
					$this->log(sprintf(
						"Sent post %s to Twitter, Twitter id: %s by user %s", 
						$post['_id'], 
						(string) $post['response']['id'],
						$post['response']['user']['screen_name']
					));
				}
				else {
					$this->log(sprintf(
						"Failed sending post %s to Twitter, error code %s: %s", 
						$post['_id'], 
						(string) $code,
						$post['response']['error']
					), "warning");
				}
				
			}
		}
		
		
	}

	

	/**
	 * Dynamically build the file name for the log file. This simple algorithm 
	 * will rotate the logs once per day and try to keep them in a central /var/log location. 
	 * @return string
	 */
	protected function log_file()
	{	
		$dir = '/var/log/daemons/tampon';
		if (@file_exists($dir) == false)
			@mkdir($dir, 0777, true);
		
		if (@is_writable($dir) == false)
			$dir = BASE_PATH . '/logs';
		
		return $dir . '/daemon.log';
	}
	
	
	static function getCurrentUnixTimestamp()
	{
		$now = new \DateTime('now', new \DateTimeZone('UTC'));
		return $now->getTimestamp();
	}
}

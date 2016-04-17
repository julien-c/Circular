<?php

namespace Daemon;

require BASE_PATH . '/../extlib/tmhOAuth/tmhOAuth.php';
require BASE_PATH . '/../extlib/tmhOAuth/tmhUtilities.php';
require BASE_PATH . '/../api/config.php';


class ParallelTasks extends \Core_Daemon
{
	protected  $loop_interval = 10;

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
		
		
		
		$m = new \MongoClient();
		
		
		// Step 1: move to the sending queue (`queue`) all requests -- posts (`posts`) and follows (`follows`) -- that were scheduled to be sent before now.
		$types = array(
			'post'   => 'posts',
			'follow' => 'follows'
		);
		foreach ($types as $type => $collection) {
			$items = $m->circular->$collection->find(array('time' => array('$lte' => new \MongoDate($currentUnixTime))));
			
			foreach ($items as $item) {
				$m->circular->queue->insert($item);
				$m->circular->$collection->remove(array('_id' => $item['_id']));
				$this->log(sprintf(
					"Moved scheduled `%s` %s to request queue", 
					$item['type'],
					$item['_id']
				));
			}
		}
		
		
		
		
		// Step 2: send queued requests (`queue`) to Twitter and move them to `archive` along with their result.
		$items = $m->circular->queue->find();
		
		foreach ($items as $item) {
			
			// We use a "processing" flag to not try to send the same request multiple times (as tasks are asynchronous)
			// (or should be, anyways)
			
			if (!isset($item['processing'])) {
				
				$item['processing'] = true;
				$item['processing_since'] = self::getCurrentUnixTimestamp();
				$m->circular->queue->update(array('_id' => $item['_id']), $item);
				
				// We can't use Task process forking for now because of a MongoDB PHP driver bug...
				// @see  https://jira.mongodb.org/browse/PHP-446
				// $this->task(new SendPostTask($post));
				
				
				
				// Send to Twitter:
				$tmhOAuth = new \tmhOAuth(array(
					'consumer_key'    => CONSUMER_KEY,
					'consumer_secret' => CONSUMER_SECRET,
					'user_token'      => $item['user']['user_token'],
					'user_secret'     => $item['user']['user_secret'],
					'v' => '1.1'
				));
				
				
				// If we're sending a picture, we need to make a multipart request:
				if (isset($item['picture']['url'])) {
					$multipart = true;
					
					// Add inline media[] parameter if we're sending a picture:
					// @see https://dev.twitter.com/docs/api/1.1/post/statuses/update_with_media
                                        
					// Convert URL to local file path
					$filepath = realpath(BASE_PATH.'/../'.str_replace(APP_URL, '', $item['picture']['url']));
					
					$item['params']['media[]'] = file_get_contents($filepath);  // or use CurlFile()
					
					$url = 'statuses/update_with_media';
				}
				else {
					$multipart = false;
					
					$url = 'statuses/update';
				}
				
				$code = $tmhOAuth->request('POST', $tmhOAuth->url($url), $item['params'], true, $multipart);
				
				if ($item['type'] == 'post_with_media') {
					// Don't store raw binary images:
					unset($item['params']['media[]']);
				}
				
				// There is no special handling of API errors.
				// Right now we just dump the response to MongoDB
				
				$item['code'] = $code;
				
				$item['response'] = json_decode($tmhOAuth->response['response'], true);
				
				// Move this item to another collection named archive:
				unset($item['processing']);
				unset($item['processing_since']);
				$m = new \MongoClient();
				
				$archives = array('post' => 'archive', 'follow' => 'archive-follows', 'post_with_media' => 'archive');
				$archive = $archives[$item['type']];
				
				$m->circular->$archive->insert($item);
				$m->circular->queue->remove(array('_id' => $item['_id']));
				
				if ($code == 200) {
					$this->log(sprintf(
						"Sent `%s` %s to Twitter, by user %s", 
						$item['type'],
						$item['_id'], 
						$item['user']['user_screen_name']
					));
				}
				else {
					$this->log(sprintf(
						"Failed sending `%s` %s to Twitter, error code %s",
						$item['type'], 
						$item['_id'], 
						(string) $code
					), "warning");
					
					$this->log($tmhOAuth->response['response'], 'warning');
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
		$dir = '/var/log/daemons/circular';
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

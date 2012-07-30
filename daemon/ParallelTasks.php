<?php

namespace Daemon;

require BASE_PATH . '/../extlib/tmhOAuth/tmhOAuth.php';
require BASE_PATH . '/../extlib/tmhOAuth/tmhUtilities.php';

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
		$m = new \Mongo();
		$posts = $m->tampon->posts->find();
		
		
		// We can't use Task process forking for now because of a MongoDB PHP driver bug...
		// @see  https://jira.mongodb.org/browse/PHP-377
		
		
		
		foreach ($posts as $post) {
			
			// We use a "processing" flag to not try to send the same post multiple times (as tasks are asynchronous)
			
			if (!isset($post['processing'])) {
				
				$post['processing'] = true;
				$post['processing_since'] = time();
				$m->tampon->posts->update(array('_id' => $post['_id']), $post);
				
				// We can't use this right now (MongoDB PHP driver bug):
			    // $this->task(new SendPostTask($post));
			    
			    // --start temp code
			    
			    
			    // Send to Twitter:
		        $tmhOAuth = new \tmhOAuth(array(
		            'consumer_key'    => 'ezBlkR7hAZ031y6CPA2jw',
		            'consumer_secret' => 'L1TFML6NE6F0ZwA1HrewBl3OybmsGCizB1G2kt5M',
		            'user_token'      => '713964546-abjJygwQcFMzwk6yyqt7AFcJJiZRNFHlcihuBRY0',
		            'user_secret'     => 'IMZ6zN1MltAZo5FS2qa9g6DtJ4m7skkmg2dXLMAjEc',
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
		        $m->tampon->posts->remove(array('_id' => $post['_id']));
		        
		        if ($code == 200) {
		            $this->log(sprintf(
		                "Sent post %s to Twitter, Twitter id: %s by user %s", 
		                $post['_id'], 
		                (string) $post['response']['id'],
		                $post['response']['screen_name']
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
			    
			    // --end temp code
			    
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
}

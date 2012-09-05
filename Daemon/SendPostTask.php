<?php

namespace Daemon;

// 
// 
// WARNING:
// 
// Not used right now because of a MongoDB PHP driver bug...
// @see  https://jira.mongodb.org/browse/PHP-446
// 
// 
// 

require BASE_PATH . '/../extlib/tmhOAuth/tmhOAuth.php';
require BASE_PATH . '/../extlib/tmhOAuth/tmhUtilities.php';



/**
 * Demonstrate using a Core_ITask object to create a more complex task
 * This won't actually do anything but you get the idea
 *
 * @author Shane Harter
 * @todo Create a plausible demo of a complex task that implements \Core_ITask
 */
class SendPostTask implements \Core_ITask
{
    /**
     * A handle to the Daemon object
     * @var \Core_Daemon
     */
    private $daemon = null;

    private $post;

    
    public function __construct($post) {
        $this->post = $post;
    }

    /**
     * Called on Construct or Init
     * @return void
     */
    public function setup()
    {
        $this->daemon = ParallelTasks::getInstance();
    }

    /**
     * Called on Destruct
     * @return void
     */
    public function teardown()
    {
        // Satisfy Interface
    }

    /**
     * This is called after setup() returns
     * @return void
     */
    public function start()
    {
        $post = $this->post;
        
        // Send to Twitter:
        $tmhOAuth = new \tmhOAuth(array(
            /* Credentials */
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
            $this->daemon->log(sprintf(
                "Sent post %s to Twitter, Twitter id: %s by user %s", 
                $post['_id'], 
                (string) $post['response']['id'],
                $post['response']['screen_name']
            ));
        }
        else {
            $this->daemon->log(sprintf(
                "Failed sending post %s to Twitter, error code %s: %s", 
                $post['_id'], 
                (string) $code,
                $post['response']['error']
            ), "warning");
        }
        
    }
}



<?php

namespace Examples\Tasks;

class ParallelTasks extends \Core_Daemon
{
    protected  $loop_interval = 1;

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
        // Randomly Create Background Tasks
        if (mt_rand(1, 20) == 1) {
            $this->log("Creating Sleepy Task");
            $this->task(array($this, 'task_sleep'));
        }

        if (mt_rand(1, 40) == 1) {
            $sleepfor = mt_rand(60, 180);
            $this->task(new BigTask($sleepfor, "I just woke up from my {$sleepfor} second sleep"));
        }

        // Randomly Shut Down -- Demonstrate daemon shutdown behavior while background tasks are running
        if (mt_rand(1, 1000) == 1) {
            $this->log("Shutting Down..");
            $this->shutdown(true);
        }
	}

	protected function task_sleep()
	{
		$this->log("Sleeping For 20 Seconds");
        sleep(20);
	}

	/**
	 * Dynamically build the file name for the log file. This simple algorithm 
	 * will rotate the logs once per day and try to keep them in a central /var/log location. 
	 * @return string
	 */
	protected function log_file()
	{	
		$dir = '/var/log/daemons/paralleltasks';
		if (@file_exists($dir) == false)
			@mkdir($dir, 0777, true);
		
		if (@is_writable($dir) == false)
			$dir = BASE_PATH . '/example_logs';
		
		return $dir . '/log_' . date('Ymd');
	}
}

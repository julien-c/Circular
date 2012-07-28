<?php

/**
 * Use a lock file. The PID will be set as the file contents, and the filemtime will be used to determine
 * expiration.
 * 
 * @author Shane Harter
 * @since 2011-07-29
 */
class Core_Lock_File extends Core_Lock_Lock implements Core_IPlugin
{
	/**
	 * The directory where the lockfile will be written. The filename will be whatever you set the $daemon_name to be. 
	 * To use the current directory, define and use a BASE_PATH constant: Using ./ will fail when the script is 
	 * run from crontab.   
	 * 
	 * @var string	A filesystem path 
	 */
	public $path = '';

    protected $filename;

    public function __construct(Core_Daemon $daemon, Array $args = array()) {
        parent::__construct($daemon, $args);
        if (isset($args['path']))
            $this->path = $args['path'];
        else
            $this->path = dirname($daemon->filename());
    }

	public function setup()
	{
        if (substr($this->path, -1, 1) != '/')
            $this->path .= '/';

        $this->filename = $this->path . $this->daemon_name . '.' . Core_Lock_Lock::$LOCK_UNIQUE_ID;
	}

	public function teardown()
	{
		// If the lockfile was set by this process, remove it. If filename is empty, this is being called before setup()
		if (!empty($this->filename) && $this->pid == @file_get_contents($this->filename))
			@unlink($this->filename);
	}
	
	public function check_environment()
	{
		$errors = array();
		
		if (is_writable($this->path) == false)
			$errors[] = 'Lock File Path ' . $this->path . ' Not Writable.';
			
		return $errors;
	}
	
	public function set()
	{
		$lock = $this->check();

        if ($lock)
			throw new Exception('Core_Lock_File::set Failed. Additional Lock Detected. PID: ' . $lock);

		// The lock value will contain the process PID
		file_put_contents($this->filename, $this->pid);
		
		touch($this->filename);
	}
	
	protected function get()
	{
		if (file_exists($this->filename) == false)
			return false;

        // If the lock isn't expired yet, read its contents -- which will be the PID that wrote it.
        $lock = file_get_contents($this->filename);

        // If we're seeing our own lock..
        if ($lock == $this->pid)
            return false;

		// Determine lock expiry time by adding it's modified-time, ttl, and padding. If that's in the future,
        // the lock is valid
        clearstatcache();
		if ((filemtime($this->filename) + $this->ttl + Core_Lock_Lock::$LOCK_TTL_PADDING_SECONDS) >= time())
			return $lock;

		return false;
	}
}
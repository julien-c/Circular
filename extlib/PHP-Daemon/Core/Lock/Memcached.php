<?php

/**
 * Use a Memcached key. The value will be the PID and Memcached ttl will be used to implement lock expiration.
 * 
 * @author Shane Harter
 * @since 2011-07-28
 */
class Core_Lock_Memcached extends Core_Lock_Lock implements Core_IPlugin
{
    /**
     * @var Core_Memcache
     */
	private $memcache = false;

    /**
     * @var array
     */
	public $memcache_servers = array();
	
	public function __construct()
	{
		$this->pid = getmypid();
	}
	
	public function setup()
	{
		// Connect to memcache
		$this->memcache = new Core_Lib_Memcache();
		$this->memcache->ns($this->daemon_name);
		
		// We want to use the auto-retry feature built into our memcache wrapper. This will ensure that the occasional blocking operation on
		// the memcache server doesn't crash the daemon. It'll retry every 1/10 of a second until it hits its limit. We're giving it a 1 second limit.
		$this->memcache->auto_retry(1);
		
		if ($this->memcache->connect_all($this->memcache_servers) === false)
			throw new Exception('Core_Lock_Memcached::setup failed: Memcached Connection Failed');
	}
	
	public function teardown()
	{
		// If this PID set this lock, release it
		$lock = $this->memcache->get(Core_Lock_Lock::$LOCK_UNIQUE_ID);
		if ($lock == $this->pid)
			$this->memcache->delete(Core_Lock_Lock::$LOCK_UNIQUE_ID);
	}
	
	public function check_environment()
	{
		$errors = array();
		
		if (false == (is_array($this->memcache_servers) && count($this->memcache_servers)))
			$errors[] = 'Memcache Plugin: Memcache Servers Are Not Set';
			
		if (false == class_exists('Core_Memcache'))
			$errors[] = 'Memcache Plugin: Dependant Class "Core_Memcache" Is Not Loaded';
			
		if (false == class_exists('Memcached'))
			$errors[] = 'Memcache Plugin: PHP Memcached Extension Is Not Loaded';

		return $errors;
	}
	
	public function set()
	{
		$lock = $this->check();
		if ($lock)
			throw new Exception('Core_Lock_Memcached::set Failed. Existing Lock Detected from PID ' . $lock);

		$timeout = Core_Lock_Lock::$LOCK_TTL_PADDING_SECONDS + $this->ttl;
		$this->memcache->set(Core_Lock_Lock::$LOCK_UNIQUE_ID, $this->pid, false, $timeout);
	}
	
	protected function get()
	{
		$lock = $this->memcache->get(Core_Lock_Lock::$LOCK_UNIQUE_ID);

		// Ensure we're not seeing our own lock
		if ($lock == $this->pid)
			return false;
		
		// If We're here, there's another lock... return the pid..
		return $lock;
	}
}
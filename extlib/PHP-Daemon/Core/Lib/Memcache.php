<?php

/**
 * Wrapper class for Memcached supplying Auto-Retry functionality.
 * @author Shane Harter
 * @final
 */
final class Core_Lib_Memcache extends Memcached
{
	/**
	 * How long can we usleep within the function before doing a retry. Longer durations will give
	 * us more time for network/server issues to clear-up but there are many problems that won't be helped. 
	 * @var float
	 */
	private $auto_retry_timeout = 0.25;
	
	/**
	 * Is auto-retry feature enabled? 
	 * @var boolean
	 */
	private $auto_retry = false;
	
	/**
	 * A namespace value that gets prepended to every key
	 * @var string
	 */
	private $namespace = '';
	
	/**
	 * Use if you want memcache to auto-retry if a set() call fails. 
	 * The timeout will dicatate how long it will attempt to retry.  
	 * @param float $auto_retry_timeout	The duration in seconds where it'll retry, must be at least 0.10 seconds.
     * @return boolean
	 */
	public function auto_retry($auto_retry_timeout)
	{
		if (is_numeric($auto_retry_timeout)) {
			$this->auto_retry_timeout = max(0.10, $auto_retry_timeout);
			$this->auto_retry = true;
			return true;
		}
			
		return false;
	}
	
	/**
	 * Set a namespace that will be used on every set/get. Had to use abbreviation because 'namespace' is a reserved keyword. 
	 * @param string $namespace		Optional. If provided, it will set the namespace. 
	 * @return boolean Returns true on Success
	 */
	public function ns($namespace = null)
	{
		if ($namespace !== null)
			if (is_scalar($namespace))
				$this->namespace = $namespace;
		
		return $this->namespace;
	}
	
	/**
	 * Return a value from Memcache with Retry functionality if a value is not returned. 
	 * @param string|array $key
	 * @param string $flags
	 * @param integer $timeout_override		The retry timeout in seconds
	 * @return mixed 	The value from Memcache or False  
	 */
	public function getWithRetry($key, $flags = false, $timeout_override = false)
	{
		if ($timeout_override)
			$max_tries = intval($timeout_override / 0.10);
		else
			$max_tries = intval($this->auto_retry_timeout / 0.10);
		
		if ($max_tries < 1)
			$max_tries = 1;
			
		for ($i=0; $i<$max_tries; $i++)
		{
			$value = $this->get($key, $flags);
			if(false == empty($value))
				return $value;
				
			usleep(100000);
		}
		
		return $value;		
	}
	
	/**
	 * Set a value in Memcache with optional built-in Auto-Retry functionity 
	 * @param string $key
	 * @param string $var
	 * @param string $flags
	 * @param integer $expire
	 * @return boolean
	 */
	public function set($key, $var, $flags = null, $expire = null)
	{
		if ($this->auto_retry)
			$max_tries = intval($this->auto_retry_timeout / 0.10);
		else
			$max_tries = 1;
		
		if ($max_tries < 1)
			$max_tries = 1;			
			
		for ($i=0; $i<$max_tries; $i++)
		{
			if(parent::set($this->key($key), $var, $flags, $expire))
				return true;
				
			usleep(100000);
		}
		
		return false;
	}
	
	/**
	 * Return a key or keys from Memcache
	 * @param string|array $key
	 * @param string $flags
     * @return mixed
	 */
	public function get($key, $flags = null)
	{
		return parent::get($this->key($key), $flags);
	}
	
	public function decrement($key, $value = 1)
	{
		return parent::decrement($this->key($key), $value);
	}
	
	public function increment($key, $value = 1)
	{
		return parent::increment($this->key($key), $value);
	}	
	
	public function delete($key)
	{
		return parent::delete($this->key($key));
	}	
	
	public function replace($key, $var, $flags = null, $expire = null)
	{
		return parent::replace($this->key($key), $var, $flags, $expire);
	}	
			
	/**
	 * Return the fully-qualified $key including namespace
	 * @param string|Array $key
     * @return mixed
	 */
	public function key($key)
	{
		if (is_array($key))
			foreach($key as &$row)
				$row = $this->key($key);
		else
			if (empty($this->namespace) == false)
				$key = "{$this->namespace}_{$key}";
					
		return $key;
	}
	
	/**
	 * Connect to an array of Memcache servers
	 * @param array $connections
	 * @return Boolean	Returns true only if all connections were made. 
	 */
	public function connect_all(array $connections)
	{
		$connection_count = 0;
		foreach ($connections as $connection)
			if (is_array($connection) && isset($connection['host']) && isset($connection['port']))
				if ($this->addServer($connection['host'], $connection['port']) == true)
					$connection_count++;
	
		return (count($connections) == $connection_count);
	}	
}
<?php

/**
 * Lock provider base class
 *
 * @todo Create Redis lock provider
 * @todo Create APC lock provider
 */
abstract class Core_Lock_Lock implements Core_IPlugin
{
	public static $LOCK_TTL_PADDING_SECONDS = 2.0;
	public static $LOCK_UNIQUE_ID = 'daemon_lock';
	
	/**
	 * The pid of the current daemon -- Set automatically by the constructor. 
	 * Also set manually in Core_Daemon::getopt() after the daemon process is forked when run in daemon mode
	 * @var integer
	 */
	public $pid;

	/**
	 * The name of the current domain -- set when the lock provider is instantiated.
	 * @var string
	 */
	public $daemon_name;

	/**
	 * This is added to the const LOCK_TTL_SECONDS to determine how long the lock should last -- any lock provider should be
	 * self-expiring using these TTL's. This is done to minimize likelihood of errant locks being left behind after a kill or crash that
     * would have to be manually removed.
     *
	 * @var float 	Number of seconds the lock should be active -- padded with Core_Lock_Lock::LOCK_TTL_PADDING_SECONDS
	 */
	public $ttl = 0;

    /**
     * The array of args passed-in at instantiation
     * @var Array
     */
    protected $args = array();

	public function __construct(Core_Daemon $daemon, Array $args = array())
	{
		$this->pid = getmypid();
        $this->daemon_name = get_class($daemon);
        $this->ttl = $daemon->loop_interval();
        $this->args = $args;

        $daemon->on(Core_Daemon::ON_INIT, array($this, 'set'));
        $daemon->on(Core_Daemon::ON_PREEXECUTE,  array($this, 'set'));

        $that = $this;
        $daemon->on(Core_Daemon::ON_PIDCHANGE, function($args) use($that) {
            if (!empty($args[0]))
                $that->pid = $args[0];
        });
	}

    /**
     * Write the lock to the shared medium.
     * @abstract
     * @return void
     */
	abstract public function set();

    /**
     * Read the lock from whatever shared medium it's written to.
     * Should return false if the lock was set by the current process (use $this->pid).
     * Should return false if the lock has exceeded it's TTL+LOCK_TTL_PADDING_SECONDS
     * If a lock is valid, it should return the PID that set it.
     * @abstract
     * @return int|falsey
     */
	abstract protected function get();

	/**
	 * Check for the existence of a lock.
	 * Cache results of get() check for 1/10 a second.
	 *
	 * @return bool|int Either false or the PID of the process that has set the lock
	 */
	public function check()
	{
		static $get = false;
		static $get_time = false;

        //if ($get_time && (microtime(true) - $get_time) < 0.10)
        //	return $get;

		$get = $this->get();
		$get_time = microtime(true);
		
		return $get;
	}
}
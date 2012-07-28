<?php

declare(ticks = 5);

/**
 * Daemon Base Class - Extend this to build daemons.
 * @uses PHP 5.3 or Higher
 * @author Shane Harter
 * @link https://github.com/shaneharter/PHP-Daemon
 * @see https://github.com/shaneharter/PHP-Daemon/wiki/Daemon-Startup-Order-Explained
 * @singleton
 * @abstract
 */
abstract class Core_Daemon
{
    /**
     * The application will attempt to restart itself it encounters a recoverable fatal error after it's been running
     * for at least this many seconds. Prevents killing the server with process forking if the error occurs at startup.
     * @var integer
     */
    const MIN_RESTART_SECONDS = 10;

    /**
     * Events can be attached to each state using the on() method
     * @var integer
     */
    const ON_ERROR          = 0;    // error() or fatal_error() is called
    const ON_SIGNAL         = 1;    // the daemon has received a signal
    const ON_INIT           = 2;    // the library has completed initialization, your setup() method is about to be called
    const ON_PREEXECUTE     = 3;    // inside the event loop, right before your execute() method
    const ON_POSTEXECUTE    = 4;    // and right after
    const ON_FORK           = 5;    // in a background process right after it has been forked from the daemon
    const ON_PIDCHANGE      = 6;    // whenever the pid changes -- in a background process for example
    const ON_IDLE           = 7;    // called when there is idle time at the end of a loop_interval, or at the idle_probability when loop_interval isn't used
    const ON_SHUTDOWN       = 10;   // called at the top of the destructor

    /**
     * An array of instructions that's displayed when the -i param is passed to the application.
     * Helps sysadmins and users of your daemons get installation correct. Guide them to set
     * correct permissions, supervisor/monit setup, crontab entries, init.d scripts, etc
     * @var Array
     */
    protected $install_instructions = array();

    /**
     * The frequency of the event loop. In seconds.
     *
     * In timer-based applications your execute() method will be called every $loop_interval seconds. Any remaining time
     * at the end of an event loop iteration will dispatch an ON_IDLE event and your application will sleep(). If the
     * event loop takes longer than the $loop_interval an error will be written to your application log.
     *
     * @example $this->loop_interval = 300;     // execute() will be called once every 5 minutes
     * @example $this->loop_interval = 0.5;     // execute() will be called 2 times every second
     * @example $this->loop_interval = 0;       // execute() will be called immediately -- There will be no sleep.
     *
     * @var float The interval in Seconds
     */
    protected $loop_interval = null;

    /**
     * Control how often the ON_IDLE event fires in applications that do not use a $loop_interval timer.
     *
     * The ON_IDLE event gives your application (and the PHP Simple Daemon library) a way to defer work to be run
     * when your application has idle time and would normally just sleep(). In timer-based applications that is very
     * deterministic. In applications that don't use the $loop_interval timer, this probability factor applied in each
     * iteration of the event loop to periodically dispatch ON_IDLE.
     *
     * Note: This value is completely ignored when using $loop_interval. In those cases, ON_IDLE is fired when there is
     *       remaining time at the end of your loop.
     *
     * Note: If you want to take responsibility for dispatching the ON_IDLE event in your application, just set
     *       this to 0 and dispatch the event periodically, eg:
     *       $this->dispatch(array(self::ON_IDLE));
     *
     *
     *
     * @var float The probability, from 0.0 to 1.0.
     */
    protected $idle_probability = 0.1;

    /**
     * The frequency of your application restarting itself. In seconds.
     *
     * @example $this->auto_restart_interval = 3600;    // Daemon will be restarted once an hour
     * @example $this->auto_restart_interval = 43200;   // Daemon will be restarted twice per day
     * @example $this->auto_restart_interval = 86400;   // Daemon will be restarted once per day
     *
     * @var integer The interval in Seconds
     */
    protected $auto_restart_interval = 43200;

    /**
     * Will be 'false' when running in a Task or Worker background process
     * @var boolean
     */
    private $is_parent = true;

    /**
     * Timestamp when was the application started
     * @var integer
     */
    private $start_time;

    /**
     * Process ID
     * @var integer
     */
    private $pid;

    /**
     * Process ID of the parent (aka application) process
     * When in parent, it'll be the same as $pid
     * @var integer
     */
    private $parent_pid;

    /**
     * An optional filename the PID was written to at startup
     * @see Core_Daemon::pid()
     * @example Pass CLI argument: -p pidfile
     * @var string
     */
    private $pid_file = false;

    /**
     * Is this process running as a Daemon?
     * @see Core_Daemon::is_daemon()
     * @example Pass CLI argument: -d
     * @var boolean
     */
    private $daemon = false;

    /**
     * Is your application shutting down at the end of the current event loop iteration?
     * @see Core_Daemon::shutdown()
     * @var boolean
     */
    private $shutdown = false;

    /**
     * In verbose mode, every log entry is also dumped to stdout, as long as were not in daemon mode.
     * Note: This was originally attached to a commandline option (-v) but it's not implicit based on whether the
     *       application is being run inside your shell (verbose=true) or as a daemon (verbose=false)
     *
     * @see Core_Daemon::verbose()
     * @var boolean
     */
    private $verbose = false;

    /**
     * Array of worker aliases
     * @var Array
     */
    private $workers = array();

    /**
     * Map of PID's to Worker aliases
     * @var array
     */
    private $worker_pids = array();

    /**
     * Array of plugin aliases
     * @var Array
     */
    private $plugins = array();

    /**
     * Map of callbacks that have been registered using on()
     * @var Array
     */
    private $callbacks = array();

    /**
     * Runtime statistics for a recent window of execution
     * @var Array
     */
    private $stats = array();

    /**
     * Set this in your daemon to run a debug console to interact with your worker processes.
     * @example Pass CLI argument: --debugworkers
     * @var bool
     */
    private $debug_workers = false;

    /**
     * By default, any buffered calls and acks between a daemon and its worker processes do not persist after a restart.
     * You can retain these calls -- and attempt to pick-up where you left off -- by using the the --recoverworkers
     * option.
     * Note: When a daemon auto-restarts itself it will use this function to retain worker operation.
     * @example Pass CLI argument: --recoverworkers
     * @var bool
     */
    private $recover_workers = false;


    /**
     * This has to be set using the Core_Daemon::setFilename() method before you call getInstance() the first time.
     * It's used as part of the auto-restart mechanism.
     * @todo Is there a way to get the currently executed filename from within an include?
     * @var string
     */
    private static $filename = false;




    /**
     * Implement this method to define plugins
     * @return void
     */
    protected function setup_plugins()
    {

    }

    /**
     * Implement this method to define workers
     * @return void
     */
    protected function setup_workers()
    {

    }

    /**
     * The setup method will contain the one-time setup needs of the daemon.
     * It will be called as part of the built-in init() method.
     * Any exceptions thrown from setup() will be logged as Fatal Errors and result in the daemon shutting down.
     * @return void
     * @throws Exception
     */
    abstract protected function setup();

    /**
     * The execute method will contain the actual function of the daemon.
     * It can be called directly if needed but its intention is to be called every iteration by the ->run() method.
     * Any exceptions thrown from execute() will be logged as Fatal Errors and result in the daemon attempting to restart or shut down.
     *
     * @return void
     * @throws Exception
     */
    abstract protected function execute();


    /**
     * Return a log file name that will be used by the log() method.
     *
     * You could hard-code a string like '/var/log/myapplog', read an option from an ini file, create a simple log
     * rotator using the date() method, etc
     *
     * Note: This method will be called during startup and periodically afterwards, on even 5-minute intervals: If you
     *       start your application at 13:01:00, the next check will be at 13:05:00, then 13:10:00, etc. This periodic
     *       polling enables you to build simple log rotation behavior into your app.
     *
     * @return string
     */
    abstract protected function log_file();




    /**
     * Return an instance of the Core_Daemon singleton
     * @return Core_Daemon
     */
    public static function getInstance()
    {
        static $o = null;
        if ($o) return $o;

        try
        {
            $o = new static;
            $o->setup_plugins();
            $o->setup_workers();
            $o->check_environment();
            $o->init();
        }
        catch (Exception $e)
        {
            $o->fatal_error($e->getMessage());
        }

        return $o;
    }

    /**
     * Set the current Filename wherein this object is being instantiated and run.
     * @param string $filename the actual filename, pass in __file__
     * @return void
     */
    public static function setFilename($filename)
    {
        self::$filename = realpath($filename);
    }



    protected function __construct()
    {
        // We have to set any installation instructions before we call getopt()
        $this->install_instructions[] = "Add to Supervisor or Monit, or add a Crontab Entry:\n   * * * * * " . $this->getFilename();

        $this->start_time = time();
        $this->pid(getmypid());
        $this->getopt();
    }

    /**
     * Ensure that essential runtime conditions are met.
     * To easily add rules to this, overload this method, build yourself an array of error messages,
     * and then call parent::check_environment($my_errors)
     * @param Array $errors
     * @return void
     * @throws Exception
     */
    protected function check_environment(Array $errors = array())
    {
        if (empty(self::$filename))
            $errors[] = 'Filename is Missing: setFilename must be called before an instance can be initialized';

        if (is_numeric($this->loop_interval) == false)
            $errors[] = "Invalid Loop Interval: $this->loop_interval";

        if (empty($this->auto_restart_interval) || is_numeric($this->auto_restart_interval) == false)
            $errors[] = "Invalid auto-restart interval: $this->auto_restart_interval";

        if (is_numeric($this->auto_restart_interval) && $this->auto_restart_interval < self::MIN_RESTART_SECONDS)
            $errors[] = 'Auto-restart inteval is too low. Minimum value: ' . self::MIN_RESTART_SECONDS;

        if (function_exists('pcntl_fork') == false)
            $errors[] = "The PCNTL Extension is not installed";

        if (version_compare(PHP_VERSION, '5.3.0') < 0)
            $errors[] = "PHP 5.3 or higher is required";

        foreach ($this->plugins as $plugin)
            foreach ($this->{$plugin}->check_environment() as $error)
                $errors[] = "[$plugin] $error";

        foreach ($this->workers as $worker)
            foreach ($this->{$worker}->check_environment() as $error)
                $errors[] = "[$worker] $error";

        if (count($errors)) {
            $errors = implode("\n  ", $errors);
            throw new Exception("Checking Dependencies... Failed:\n  $errors");
        }
    }

    /**
     * Run the setup() methods of installed plugins, installed workers, and the subclass, in that order. And dispatch the ON_INIT event.
     * @return void
     */
    private function init()
    {
        $this->register_signal_handlers();

        foreach ($this->plugins as $plugin)
            $this->{$plugin}->setup();

        foreach ($this->workers as $worker)
            $this->{$worker}->setup();

        $this->loop_interval($this->loop_interval);

        // Our current use of the ON_INIT event is in the Lock provider plugins -- so we can prevent a duplicate daemon
        // process from starting-up. In that case, we want to do that check as early as possible. To accomplish that,
        // the plugin setup has to happen first -- to ensure the Lock provider plugins have a chance to load.
        $this->dispatch(array(self::ON_INIT));

        // Queue any housekeeping tasks we want performed periodically
        $this->on(self::ON_IDLE, array($this, 'stats_trim'), (empty($this->loop_interval)) ? null : ($this->loop_interval * 50)); // Throttle to about once every 50 iterations

        $this->setup();
        if (!$this->daemon)
            $this->log('Note: The daemonize (-d) option was not set: This process is running inside your shell. Auto-Restart feature is disabled.');

        $this->log('Process Initialization Complete. Starting Event Loop.');
    }

    /**
     * Teardown all plugins and workers and reap any zombie processes before exiting
     * @return void
     */
    public function __destruct()
    {
        try
        {
            $this->dispatch(array(self::ON_SHUTDOWN));
            foreach($this->plugins as $plugin)
                $this->{$plugin}->teardown();

            while($this->is_parent && count($this->worker_pids) > 0) {
                foreach(array_unique($this->worker_pids) as $worker)
                    $this->{$worker}->teardown();

                $this->reap(false);
                usleep(50000);
            }
        }
        catch (Exception $e)
        {
            $this->fatal_error(sprintf('Exception Thrown in Shutdown: %s [file] %s [line] %s%s%s',
                $e->getMessage(), $e->getFile(), $e->getLine(), PHP_EOL, $e->getTraceAsString()));
        }

        $this->reap(false);
        if ($this->is_parent && !empty($this->pid_file) && file_exists($this->pid_file) && file_get_contents($this->pid_file) == $this->pid)
            unlink($this->pid_file);

        if ($this->is_parent && $this->verbose)
            echo PHP_EOL;
    }

    /**
     * Some accessors are available as setters only within their defined scope, but can be
     * used as getters universally. Filter out setter arguments and proxy the call to the accessor.
     * Note: A warning will be raised if the accessor is used as a setter out of scope.
     * @example $someDaemon->loop_interval()
     *
     * @param $method
     * @param $args
     * @return mixed
     */
    public function __call($method, $args)
    {
        $accessors = array('loop_interval', 'is_parent', 'verbose', 'pid', 'shutdown');
        if (in_array($method, $accessors)) {
            if ($args)
                trigger_error("The '$method' accessor can not be used as a setter in this context. Supplied arguments ignored.", E_USER_WARNING);

            return call_user_func_array(array($this, $method), array());
        }

        if (in_array($method, $this->workers)) {
            return call_user_func_array($this->$method, $args);
        }

        throw new Exception("Invalid Method Call '$method'");
    }

    /**
     * This is the main program loop for the daemon
     * @return void
     */
    public function run()
    {
        try
        {
            while ($this->shutdown == false && $this->is_parent)
            {
                $this->timer(true);
                $this->auto_restart();
                $this->reap();
                $this->dispatch(array(self::ON_PREEXECUTE));
                $this->execute();
                $this->dispatch(array(self::ON_POSTEXECUTE));
                $this->timer();
            }
        }
        catch (Exception $e)
        {
            $this->fatal_error(sprintf('Uncaught Exception in Event Loop: %s [file] %s [line] %s%s%s',
                $e->getMessage(), $e->getFile(), $e->getLine(), PHP_EOL, $e->getTraceAsString()));
        }
    }

    /**
     * Register a callback for the given $event. Use the event class constants for built-in events. Add and dispatch
     * your own events however you want.
     * @param $event mixed scalar  When creating custom events, keep ints < 100 reserved for the daemon
     * @param $callback closure|callback
     * @param $throttle Optional time in seconds to throttle calls to the given $callback. For example, if
     *        $throttle = 10, the provided $callback will not be called more than once every 10 seconds, even if the
     *        given $event is dispatched more frequently than that.
     * @return array    The return value can be passed to off() to unbind the event
     * @throws Exception
     */
    public function on($event, $callback, $throttle = null)
    {
        if (!is_scalar($event))
            throw new Exception(__METHOD__ . ' Failed. Event type must be Scalar. Given: ' . gettype($event));

        if (!is_callable($callback))
            throw new Exception(__METHOD__ . ' Failed. Second Argument Must be Callable.');

        if (!isset($this->callbacks[$event]))
            $this->callbacks[$event] = array();

        $this->callbacks[$event][] = array('callback' => $callback, 'throttle' => $throttle, 'call_at' => 0);
        end($this->callbacks[$event]);
        return array($event, key($this->callbacks[$event]));
    }

    /**
     * Remove a callback previously registered with on(). Returns the callback.
     * @param array $event  Should be the array returned when you called on()
     * @return callback|closure|null returns the registered event handler assuming $event is valid
     */
    public function off(Array $event)
    {
        if (isset($event[0]) && isset($event[1])) {
            $cb = $this->callbacks[$event[0]][$event[1]];
            unset($this->callbacks[$event[0]][$event[1]]);
            return $cb;
        }
        return null;
    }

    /**
     * Dispatch callbacks. Can either pass an array referencing a specific callback (eg the return value from an on() call)
     * or you can pass it an array with the event type and all registered callbacks will be called.
     * @param array $event  Either an array with a single item (an event type) or 2
     *                      items (an event type, and a callback ID for that event type)
     * @param array $args   Array of arguments passed to the event listener
     */
    protected function dispatch(Array $event, Array $args = array())
    {
        if (!isset($event[0]) || !isset($this->callbacks[$event[0]]))
            return;

        // A specific callback is being dispatched...
        if (isset($event[1]) && isset($this->callbacks[$event[0]][$event[1]])) {
            $callback =& $this->callbacks[$event[0]][$event[1]];
            if (empty($callback['throttle']) || time() > $callback['call_at']) {
                $callback['call_at'] = time() + (int)$callback['throttle'];
                call_user_func_array($callback['callback'], $args);
            }
            return;
        }

        // All callbacks attached to a given event are being dispatched...
        if (!isset($event[1]))
            foreach($this->callbacks[$event[0]] as $callback_id => $callback) {
                if (empty($callback['throttle']) || time() > $callback['call_at']) {
                    $this->callbacks[$event[0]][$callback_id]['call_at'] = time() + (int)$callback['throttle'];
                    call_user_func_array($callback['callback'], $args);
                }
            }
    }

    /**
     * Run any task asynchronously by passing it to this method. Will fork into a child process, execute the supplied
     * code, and exit.
     *
     * The $callable provided can be a standard PHP Callback, a Closure, or any object that implements Core_ITask
     *
     * Note: If the task uses MySQL or certain other outside resources, the connection will have to be
     * re-established in the child process. There are three options:
     *
     * 1. Put any mysql setup or connection code in your task:
     *    This is not suggested because it's bad design but it's certainly possible.
     *
     * 2. Run the same setup code in every background task:
     *    Any event handlers you set using $this->on(ON_FORK) will be run in every forked Task and Worker process
     *    before the callable is called.
     *
     * 3. Run setup code specific to the current background task:
     *    If you need to run specific setup code for a task or worker you have to use an object. You can't use the shortened
     *    form of passing a callback or closure. For tasks that means an object that implements Core_ITask. For workers,
     *    it's Core_IWorker. The setup() and teardown() methods defined in the interfaces are natural places to handle
     *    database connections, etc.
     *
     * @link https://github.com/shaneharter/PHP-Daemon/wiki/Tasks
     *
     * @param callable|Core_ITask $callable     A valid PHP callback or closure.
     * @param Mixed                             All additional params are passed to the $callable
     * @return int|boolean                      Return the PID of the newly-forked task or false on failure
     */
    public function task($task)
    {
        if ($this->shutdown) {
            $this->log("Daemon is shutting down: Cannot run task()");
            return false;
        }

        // Standardize the $task into a $callable
        // If a Core_ITask was passed in, wrap it in a closure
        if ($task instanceof Core_ITask) {
            $callable = function() use($task) {
                // By convention an is_parent variable is used when we need to keep track of process state.
                if (isset($task->is_parent))
                    $task->is_parent = false;

                $task->setup();
                call_user_func(array($task, 'start'));
                $task->teardown();
            };
        } else {
            $callable = $task;
        }


        $pid = pcntl_fork();
        switch ($pid)
        {
            case -1:
                // Parent Process - Fork Failed
                $e = new Exception();
                $this->error('Task failed: Could not fork.');
                $this->error($e->getTraceAsString());
                return false;
                break;

            case 0:
                // Child Process
                $this->start_time = time();
                $this->is_parent  = false;
                $this->parent_pid = $this->pid;
                $this->pid(getmypid());
                pcntl_setpriority(1);

                // Remove unused worker objects. They can be memory hogs.
                foreach(array_merge($this->workers, $this->plugins) as $object)
                    if (!(is_array($callable) && $callable[0] == $this->{$object}))
                        unset($this->{$object});

                $this->workers = $this->worker_pids = $this->plugins = $this->stats = array();
                $this->dispatch(array(self::ON_FORK));

                try
                {
                    call_user_func_array($callable, array_slice(func_get_args(), 1));
                }
                catch (Exception $e)
                {
                    $this->error('Exception Caught in Fork: ' . $e->getMessage());
                }

                exit;
                break;

            default:
                // Parent Process - Return the pid of the newly created Task
                return $pid;
                break;
        }
    }

    /**
     * Log the $message to the filename returned by Core_Daemon::log_file() and/or optionally print to stdout.
     * Multi-Line messages will be handled nicely.
     *
     * Note: Your log_file() method will be called every 5 minutes (at even increments, eg 00:05, 00:10, 00:15, etc) to
     * allow you to rotate the filename based on time (one log file per month, day, hour, whatever) if you wish.
     *
     * Note: You may find value in overloading this method in your app in favor of a more fully-featured logging tool
     * like log4php or Zend_Log. There are fantastic logging libraries available, and this simplistic home-grown option
     * was chosen specifically to avoid forcing another dependency on you.
     *
     * @param string $message
     * @param string $label Truncated at 12 chars
     */
    public function log($message, $label = '', $indent = 0)
    {
        static $handle = false;
        static $log_file = '';
        static $log_file_check_at = 0;
        static $log_file_error = false;

        $header = "\nDate                  PID   Label         Message\n";
        $date   = date("Y-m-d H:i:s");
        $pid    = str_pad($this->pid, 5, " ", STR_PAD_LEFT);
        $label  = str_pad(substr($label, 0, 12), 13, " ", STR_PAD_RIGHT);
        $prefix = "[$date] $pid $label" . str_repeat("\t", $indent);

        if (time() >= $log_file_check_at && $this->log_file() != $log_file) {
            $log_file = $this->log_file();
            $log_file_check_at = mktime(date('H'), (date('i') - (date('i') % 5)) + 5, null);
            @fclose($handle);
            $handle = $log_file_error = false;
        }

        if ($handle === false) {
            if (strlen($log_file) > 0 && $handle = @fopen($log_file, 'a+')) {
                if ($this->is_parent) {
                    fwrite($handle, $header);
                    if ($this->verbose)
                        echo $header;
                }
            } elseif (!$log_file_error) {
                $log_file_error = true;
                trigger_error(__CLASS__ . "Error: Could not write to logfile " . $log_file, E_USER_WARNING);
            }
        }

        $message = $prefix . ' ' . str_replace("\n", "\n$prefix ", trim($message)) . "\n";

        if ($handle)
            fwrite($handle, $message);

        if ($this->verbose)
            echo $message;
    }

    /**
     * Log the provided $message and dispatch an ON_ERROR event.
     *
     * The library has no concept of a runtime error. If your application doesn't attach any ON_ERROR listeners, there
     * is literally no difference between using this and just passing the message to Core_Daemon::log().
     *
     * @param $message
     * @param string $label
     */
    public function error($message, $label = '')
    {
        $this->log($message, $label);
        $this->dispatch(array(self::ON_ERROR), array($message));
    }

    /**
     * Raise a fatal error and kill-off the process. If it's been running for a while, it'll try to restart itself.
     * @param string $message
     * @param string $label
     */
    public function fatal_error($message, $label = '')
    {
        $this->error($message, $label);

        if ($this->is_parent) {
            $this->log(get_class($this) . ' is Shutting Down...');

            $delay = 2;
            if ($this->is_daemon() && ($this->runtime() + $delay) > self::MIN_RESTART_SECONDS) {
                sleep($delay);
                $this->restart();
            }
        }

        // If we get here, it means we couldn't try a re-start or we tried and it just didn't work.
        echo PHP_EOL;
        exit(1);
    }

    /**
     * When a signal is sent to the process it'll be handled here
     * @param integer $signal
     * @return void
     */
    public function signal($signal)
    {
        $this->dispatch(array(self::ON_SIGNAL), array($signal));
        switch ($signal)
        {
            case SIGUSR1:
                // kill -10 [pid]
                $this->dump();
                break;
            case SIGHUP:
                // kill -1 [pid]
                $this->restart();
                break;
            case SIGINT:
            case SIGTERM:
                if ($this->is_parent)
                    $this->log("Shutdown Signal Received\n");

                $this->shutdown = true;
                break;
        }
    }

    /**
     * Register Signal Handlers
     * Note: SIGKILL is missing -- afaik this is uncapturable in a PHP script, which makes sense.
     * Note: Some of these signals have special meaning and use in POSIX systems like Linux. Use with care.
     * Note: If the daemon is run with a loop_interval timer, some signals will be suppressed during sleep periods
     * @return void
     */
    private function register_signal_handlers()
    {
        $signals = array(
            // Handled by Core_Daemon:
            SIGTERM, SIGINT, SIGUSR1, SIGHUP,

            // Ignored by Core_Daemon -- register callback ON_SIGNAL to listen for them.
            // Some of these are duplicated/aliased, listed here for completeness
            SIGUSR2, SIGCONT, SIGQUIT, SIGILL, SIGTRAP, SIGABRT, SIGIOT, SIGBUS, SIGFPE, SIGSEGV, SIGPIPE, SIGALRM,
            SIGCONT, SIGTSTP, SIGTTIN, SIGTTOU, SIGURG, SIGXCPU, SIGXFSZ, SIGVTALRM, SIGPROF,
            SIGWINCH, SIGIO, SIGSYS, SIGBABY, SIGCHLD
        );

        if (defined('SIGPOLL'))     $signals[] = SIGPOLL;
        if (defined('SIGPWR'))      $signals[] = SIGPWR;
        if (defined('SIGSTKFLT'))   $signals[] = SIGSTKFLT;

        foreach(array_unique($signals) as $signal) {
            pcntl_signal($signal, array($this, 'signal'));
        }
    }

    /**
     * Get the fully qualified command used to start (and restart) the daemon
     * @param string $options    An options string to use in place of whatever options were present when the daemon was started.
     * @return string
     */
    private function getFilename($options = false)
    {
        $command = 'php ' . self::$filename;

        if ($options === false) {
            $command .= ' -d --recoverworkers';
            if ($this->pid_file)
                $command .= ' -p ' . $this->pid_file;

            if ($this->debug_workers)
                $command .= ' --debugworkers';
        }
        else {
            $command .= ' ' . trim($options);
        }

        // We have to explicitly redirect output to /dev/null to prevent the exec() call from hanging
        $command .= ' > /dev/null';

        return $command;
    }

    /**
     * This will dump various runtime details to the log.
     * @example $ kill -10 [pid]
     * @return void
     */
    private function dump()
    {
        $workers = '';
        foreach($this->workers as $worker)
            $workers .= sprintf('%s %s [%s], ', $worker, $this->{$worker}->id(), $this->{$worker}->is_idle() ? 'AVAILABLE' : 'BUFFERING');

        $pretty_memory = function($bytes) {
            $kb = 1024; $mb = $kb * 1024; $gb = $mb * 1024;
            switch(true) {
                case $bytes > $gb: return sprintf('%sG', number_format($bytes / $gb, 2));
                case $bytes > $mb: return sprintf('%sM', number_format($bytes / $mb, 2));
                case $bytes > $kb: return sprintf('%sK', number_format($bytes / $kb, 2));
                default: return $bytes;
            }
        };

        $pretty_duration = function($seconds) {
            $m = 60; $h = $m * 60; $d = $h * 24;
            $out = '';
            switch(true) {
                case $seconds > $d:
                    $out .= intval($seconds / $d) . 'd ';
                    $seconds %= $d;
                case $seconds > $h:
                    $out .= intval($seconds / $h) . 'h ';
                    $seconds %= $h;
                case $seconds > $m:
                    $out .= intval($seconds / $m) . 'm ';
                    $seconds %= $m;
                default:
                    $out .= "{$seconds}s";
            }
            return $out;
        };

        $pretty_bool = function($bool) {
            return ($bool ? 'Yes' : 'No');
        };

        $out = array();
        $out[] = "---------------------------------------------------------------------------------------------------";
        $out[] = "Application Runtime Statistics";
        $out[] = "---------------------------------------------------------------------------------------------------";
        $out[] = "Command:              " . ($this->is_parent ? $this->getFilename() : 'Forked Process from pid ' . $this->parent_pid);
        $out[] = "Loop Interval:        " . $this->loop_interval;
        $out[] = "Idle Probability      " . $this->idle_probability;
        $out[] = "Restart Interval:     " . $this->auto_restart_interval;
        $out[] = sprintf("Start Time:           %s (%s)", $this->start_time, date('Y-m-d H:i:s', $this->start_time));
        $out[] = sprintf("Duration:             %s (%s)", $this->runtime(), $pretty_duration($this->runtime()));
        $out[] = "Log File:             " . $this->log_file();
        $out[] = "Daemon Mode:          " . $pretty_bool($this->daemon);
        $out[] = "Shutdown Signal:      " . $pretty_bool($this->shutdown);
        $out[] = "Process Type:         " . ($this->is_parent ? 'Application Process' : 'Background Process');
        $out[] = "Plugins:              " . implode(', ', $this->plugins);
        $out[] = "Workers:              " . $workers;
        $out[] = sprintf("Memory:               %s (%s)", memory_get_usage(true), $pretty_memory(memory_get_usage(true)));
        $out[] = sprintf("Peak Memory:          %s (%s)", memory_get_peak_usage(true), $pretty_memory(memory_get_peak_usage(true)));
        $out[] = "Current User:         " . get_current_user();
        $out[] = "Priority:             " . pcntl_getpriority();
        $out[] = "Loop: duration, idle: " . implode(', ', $this->stats_mean()) . ' (Mean Seconds)';
        $out[] = "Stats sample size:    " . count($this->stats);
        $this->log(implode("\n", $out));
    }

    /**
     * Time the execution loop and sleep an appropriate amount of time.
     * @param boolean $start
     * @return mixed
     */
    private function timer($start = false)
    {
        static $start_time = null;

        // Start the Stop Watch and Return
        if ($start)
            return $start_time = microtime(true);

        // End the Stop Watch

        // Determine if we should run the ON_IDLE tasks.
        // In timer based applications, determine if we have remaining time.
        // Otherwise apply the $idle_probability factor

        $end_time = $probability = null;

        if ($this->loop_interval)    $end_time = ($start_time + $this->loop_interval() - 0.01);
        if ($this->idle_probability) $probability = (1 / $this->idle_probability);

        $is_idle = function() use($end_time, $probability) {
            if ($end_time)
                return microtime(true) < $end_time;

            if ($probability)
                return mt_rand(1, $probability) == 1;

            return false;
        };

        // If we have idle time, do any housekeeping tasks
        if ($is_idle()) {
            $this->dispatch(array(Core_Daemon::ON_IDLE), array($is_idle));
        }

        $stats = array();
        $stats['duration']  = microtime(true) - $start_time;
        $stats['idle']      = $this->loop_interval - $stats['duration'];

        // Suppress child signals during sleep to stop exiting forks/workers from interrupting the timer.
        // Note: SIGCONT (-18) signals are not suppressed and can be used to "wake up" the daemon.
        if ($stats['idle'] > 0) {
            pcntl_sigprocmask(SIG_BLOCK, array(SIGCHLD));
            usleep($stats['idle'] * 1000000);
            pcntl_sigprocmask(SIG_UNBLOCK, array(SIGCHLD));
        } else {
            // There is no time to sleep between intervals -- but we still need to give the CPU a break
            // Sleep for 1/100 a second.
            usleep(10000);
            if ($this->loop_interval > 0)
                $this->error('Run Loop Taking Too Long. Duration: ' . number_format($stats['duration'], 3) . ' Interval: ' . $this->loop_interval);
        }

        $this->stats[] = $stats;
        return $stats;
    }

    /**
     * If this is in daemon mode, provide an auto-restart feature.
     * This is designed to allow us to get a fresh stack, fresh memory allocation, etc.
     * @return boolean|void
     */
    private function auto_restart()
    {
        if ($this->daemon == false)
            return false;

        if ($this->runtime() < $this->auto_restart_interval || $this->auto_restart_interval < self::MIN_RESTART_SECONDS)
            return false;

        $this->restart();
    }

    /**
     * Maintain the worker process map and notify the worker of an exited process.
     * @param bool $block   When true, method will block waiting for an exit signal
     * @return void
     */
    private function reap($block = false)
    {
        do {
            $pid = pcntl_wait($status, ($block && $this->is_parent) ? NULL : WNOHANG);
            if (isset($this->worker_pids[$pid])) {
                $alias = $this->worker_pids[$pid];
                $this->{$alias}->reap($pid, $status);
                unset($this->worker_pids[$pid]);
            }
        } while($pid > 0);
    }

    /**
     * There are 2 paths to the daemon calling restart: The Auto Restart feature, and, also, if a fatal error
     * is encountered after it's been running for a while, it will attempt to re-start.
     * @return void;
     */
    public function restart()
    {
        if ($this->is_parent == false)
            return;

        $this->shutdown = true;
        $this->log('Restart Happening Now...');
        foreach($this->plugins as $plugin)
            $this->{$plugin}->teardown();

        $this->callbacks = array();

        // Close the resource handles to prevent this process from hanging on the exec() output.
        if (is_resource(STDOUT)) fclose(STDOUT);
        if (is_resource(STDERR)) fclose(STDERR);
        if (is_resource(STDIN))  fclose(STDIN);
        exec($this->getFilename());

        // A new daemon process has been created. This one will stick around just long enough to clean up the worker processes.
        exit();
    }

    /**
     * Load any plugin that implements the Core_IPlugin.
     *
     * A single instance of any plugin in the /Core/Plugin directory can be created just by passing-in the significant
     * part of the class name as the alias. See the first ini example below.
     *
     * If you want to load custom plugins (or multiple instances of built-in plugins with different aliases) you can
     * provide any valid alias with an instance of the plugin as the 2nd argument.
     *
     * It's important to understand that plugins and workers are both created as public instance variables (properties)
     * so aliases must be unique among plugins and workers and cannot overwrite any existing instance variables in either
     * Core_Daemon or your superclass.
     *
     * Both of the following examples are equivalent. In both cases, an Ini plugin will be instantiated at $this->ini:
     * @example $this->plugin('ini');
     * @example $this->plugin('ini', new Core_Plugins_Ini());
     *
     * To use two ini files just give them unique aliases:
     * @example $this->plugin('credentials', new Core_Plugins_Ini());
     *          $this->plugin('settings', new Core_Plugins_Ini());
     *          $this->credentials->filename = '~/prod/credentials.ini';
     *          $this->settings->filename = BASE_PATH . '/MyDaemon/settings.ini';
     *          echo $this->credentials['mysql']['user']; // Echo the 'user' key in the 'mysql' section
     *
     * You can implicitly load Lock plugins if your alias includes "Lock_" (since they are not located in /Core/Plugin):
     * @example $this->plugin('Lock_File'); // Instantiated at $this->Lock_File
     *
     * @param string $alias
     * @param Core_IPlugin|null $instance
     * @return Core_IPlugin Returns an instance of the plugin
     * @throws Exception
     */
    protected function plugin($alias, Core_IPlugin $instance = null)
    {
        $this->check_alias($alias);

        if ($instance === null) {
            // This if wouldn't be necessary if /Lock lived inside /Plugin.
            // Now that Locks are plugins in every other way, maybe it should be moved. OTOH, do we really need 4
            // levels of directory depth in a project with like 10 files...?
            if (substr(strtolower($alias), 0, 5) == 'lock_')
                $class = 'Core_' . ucfirst($alias);
            else
                $class = 'Core_Plugin_' . ucfirst($alias);

            if (class_exists($class, true)) {
                $interfaces = class_implements($class, true);
                if (is_array($interfaces) && isset($interfaces['Core_IPlugin'])) {
                    $instance = new $class($this->getInstance());
                }
            }
        }

        if (!is_object($instance)) {
            throw new Exception(__METHOD__ . " Failed. Could Not Load Plugin '{$alias}'");
        }

        $this->{$alias} = $instance;
        $this->plugins[] = $alias;
        return $instance;
    }

    /**
     * Create a persistent Worker process.
     * @param String $alias  The name of the worker -- Will be instantiated at $this->{$alias}
     * @param callable|Core_IWorker $worker An object of type Core_Worker OR a callable (function, callback, closure)
     * @return Core_Worker_ObjectMediator Returns a Core_Worker class that can be used to interact with the Worker
     * @todo Use 'callable' type hinting if/when we move to a php 5.4 requirement.
     */
    protected function worker($alias, $worker)
    {
        if (!$this->is_parent)
            // While in theory there is nothing preventing you from creating workers in child processes, supporting it
            // would require changing a lot of error handling and process management code and I don't really see the value in it.
            throw new Exception(__METHOD__ . ' Failed. You cannot create workers in a background processes.');

        $this->check_alias($alias);

        switch (true) {
            case is_object($worker) && !is_a($worker, 'Closure'):
                if ($this->debug_workers)
                    $mediator = new Core_Worker_Debug_ObjectMediator($alias, $this);
                else
                    $mediator = new Core_Worker_ObjectMediator($alias, $this);

                // Ensure that there are no reserved method names in the worker object -- Determine if there will
                // be a collision between worker methods and public methods on the Mediator class
                // Exclude any methods required by the Core_IWorker interface from the check.
                $intersection = array_intersect(get_class_methods($worker), get_class_methods($mediator));
                $intersection = array_diff($intersection, get_class_methods('Core_IWorker'));
                if (!empty($intersection))
                    throw new Exception(sprintf('%s Failed. Your worker class "%s" contains restricted method names: %s.',
                        __METHOD__, get_class($worker), implode(', ', $intersection)));

                $mediator->setObject($worker);
                break;

            case is_callable($worker):
                if ($this->debug_workers)
                    $mediator = new Core_Worker_Debug_FunctionMediator($alias, $this);
                else
                    $mediator = new Core_Worker_FunctionMediator($alias, $this);

                $mediator->setFunction($worker);
                break;

            default:
                throw new Exception(__METHOD__ . ' Failed. Could Not Load Worker: ' . $alias);
        }

        $this->workers[] = $alias;
        $this->{$alias} = $mediator;
        return $this->{$alias};
    }

    /**
     * Used by the worker mediator to make the daemon aware of a process running for a given worker
     * They're used, among other things, to notify the correct worker when a process exits
     * @param $alias
     * @param $pid
     */
    public function worker_pid($alias, $pid) {
        $this->worker_pids[$pid] = $alias;
    }

    /**
     * Simple function to validate that alises for Plugins or Workers won't interfere with each other or with existing daemon properties.
     * @param $alias
     * @throws Exception
     */
    private function check_alias($alias) {
        if (empty($alias) || !is_scalar($alias))
            throw new Exception("Invalid Alias. Identifiers must be scalar.");

        if (isset($this->{$alias}))
            throw new Exception("Invalid Alias. The identifier `{$alias}` is already in use or is reserved");
    }

    /**
     * Handle command line arguments. To easily extend, just add parent::getopt at the TOP of your overloading method.
     * @return void
     */
    protected function getopt()
    {
        $opts = getopt('hHiI:o:dp:', array('install', 'recoverworkers', 'debugworkers'));

        if (isset($opts['H']) || isset($opts['h']))
            $this->show_help();

        if (isset($opts['i']))
            $this->show_install_instructions();

        if (isset($opts['I']))
            $this->create_init_script($opts['I'], isset($opts['install']));

        if (isset($opts['d'])) {
            $pid = pcntl_fork();
            if ($pid > 0)
                exit();

            $this->daemon = true;
            $this->pid(getmypid()); // We have a new pid now
        }

        $this->recover_workers = isset($opts['recoverworkers']);
        $this->debug_workers = isset($opts['debugworkers']);
        $this->verbose = $this->daemon == false && $this->debug_workers == false;

        if (isset($opts['p'])) {
            $handle = @fopen($opts['p'], 'w');
            if (!$handle)
                $this->show_help('Unable to write PID to ' . $opts['p']);

            fwrite($handle, $this->pid);
            fclose($handle);

            $this->pid_file = $opts['p'];
        }
    }

    /**
     * Print a Help Block and Exit. Optionally Print $msg along with it.
     * @param string $msg
     * @return void
     */
    protected function show_help($msg = '')
    {
        $out = array('');

        if ($msg) {
            $out[] =  '';
            $out[] = 'ERROR:';
            $out[] = ' ' . wordwrap($msg, 72, "\n ");
        }

        echo get_class($this);
        $out[] =  'USAGE:';
        $out[] =  ' # ' . basename(self::$filename) . ' -H | -i | -I TEMPLATE_NAME [--install] | [-d] [-p PID_FILE] [--recoverworkers] [--debugworkers]';
        $out[] =  '';
        $out[] =  'OPTIONS:';
        $out[] =  ' -H Shows this help';
        $out[] =  ' -i Print any daemon install instructions to the screen';
        $out[] =  ' -I Create init/config script';
        $out[] =  '    You must pass in a name of a template from the /Templates directory';
        $out[] =  '    OPTIONS:';
        $out[] =  '     --install';
        $out[] =  '       Install the script to /etc/init.d. Otherwise just output the script to stdout.';
        $out[] =  '';
        $out[] =  ' -d Daemon, detach and run in the background';
        $out[] =  ' -p PID_FILE File to write process ID out to';
        $out[] =  '';
        $out[] =  ' --recoverworkers';
        $out[] =  '   Attempt to recover pending and incomplete calls from a previous instance of the daemon. Should be run under supervision after a daemon crash. Experimental.';
        $out[] =  '';
        $out[] =  ' --debugworkers';
        $out[] =  '   Run workers under a debug console. Provides tools to debug the inter-process communication between workers.';
        $out[] =  '   Console will only be displayed if Workers are used in your daemon';
        $out[] =  '';
        $out[] =  '';

        echo implode("\n", $out);
        exit();
    }

    /**
     * Print any install instructions and Exit.
     * Could be anything from copying init.d scripts, setting crontab entries, creating executable or writable directories, etc.
     * Add instructions from your daemon by adding them one by one: $this->install_instructions[] = 'Do foo'
     * @return void
     */
    protected function show_install_instructions()
    {
        echo get_class($this) . " Installation Instructions:\n\n - ";
        echo implode("\n - ", $this->install_instructions);
        echo "\n";
        exit();
    }

    /**
     * Create and output an init script for this daemon to provide start/stop/restart functionality.
     * Uses templates in the /Templates directory to produce scripts for different process managers and linux distros.
     * When you create an init script, you should chmod it to 0755.
     *
     * @param string $template The name of a template from the /Templates directory
     * @param bool $install When true, the script will be created in the init.d directory and final setup instructions will be printed to stdout
     * @return void
     */
    protected function create_init_script($template_name, $install = false)
    {
        $template = dirname($this->filename()) . '/Core/Templates/' . $template_name;

        if (!file_exists($template))
            $this->show_help("Invalid Template Name '{$template_name}'");

        $daemon = get_class($this);
        $script = sprintf(
            file_get_contents($template),
            $daemon,
            $this->getFilename("-d -p /var/run/{$daemon}.pid")
        );

        if (!$install) {
            echo $script;
            echo "\n\n";
            exit;
        }

        // Print out template-specific setup instructions
        switch($template_name) {
            case 'init_ubuntu':
                $filename = '/etc/init.d/' . $daemon;
                $instructions = "\n - To run on startup on RedHat/CentOS:  sudo chkconfig --add {$filename}";
                break;

            default:
                $instructions = '';
        }

        @file_put_contents($filename, $script);
        @chmod($filename, 0755);
        if (file_exists($filename) == false || is_executable($filename) == false)
            $this->show_help("* Must Be Run as Sudo\n * Could Not Write Config File");

        echo "Init Scripts Created Successfully!";
        echo $instructions;
        echo "\n\n";
        exit();
    }

    /**
     * Return the running time in Seconds
     * @return integer
     */
    public function runtime()
    {
        return time() - $this->start_time;
    }

    /**
     * Return the pid of the parent daemon process
     * @return integer
     */
    public function parent_pid()
    {
        return $this->parent_pid;
    }

    /**
     * Return the daemon's filename
     * @return string
     */
    public static function filename()
    {
        return self::$filename;
    }

    /**
     * Is this run as a daemon or within a shell?
     * @return boolean
     */
    public function is_daemon()
    {
        return $this->daemon;
    }

    /**
     * Is the --recoverworkers flag set?
     * @return boolean
     */
    public function recover_workers()
    {
        return $this->recover_workers;
    }

    /**
     * Return a list containing the mean duration and idle time of the daemons event loop, ignoring the longest and shortest 5%
     * Note: Stats data is trimmed periodically and is not likely to have more than 200 rows.
     * @param int $last  Limit the working set to the last n iteration
     * @return Array A list as array(duration, idle) averages.
     */
    public function stats_mean($last = 100)
    {
        if (count($this->stats) < $last) {
            $data = $this->stats;
        } else {
            $data = array_slice($this->stats, -$last);
        }

        $count = count($data);
        $n = ceil($count * 0.05);

        // Sort the $data by duration and remove the top and bottom $n rows
        $duration = array();
        for($i=0; $i<$count; $i++) {
            $duration[$i] = $data[$i]['duration'];
        }
        array_multisort($duration, SORT_ASC, $data);
        $count -= ($n * 2);
        $data = array_slice($data, $n, $count);

        // Now compute the corrected mean
        $list = array(0, 0);
        if ($count) {
            for($i=0; $i<$count; $i++) {
                $list[0] += $data[$i]['duration'];
                $list[1] += $data[$i]['idle'];
            }

            $list[0] /= $count;
            $list[1] /= $count;
        }

        return $list;
    }

    /**
     * A method to periodically trim older items from the stats array
     * @return void
     */
    public function stats_trim() {
        $this->stats = array_slice($this->stats, -100, 100);
    }

    /**
     * Combination getter/setter for the $is_parent property. Can be called manually inside a background process.
     * @param boolean $set_value
     * @return boolean
     */
    protected function is_parent($set_value = null)
    {
        if (is_bool($set_value))
            $this->is_parent = $set_value;

        return $this->is_parent;
    }

    /**
     * Combination getter/setter for the $shutdown property.
     * @param boolean $set_value
     * @return boolean
     */
    protected function shutdown($set_value = null)
    {
        if (is_bool($set_value))
            $this->shutdown = $set_value;

        return $this->shutdown;
    }

    /**
     * Combination getter/setter for the $verbose property.
     * @param boolean $set_value
     * @return boolean
     */
    protected function verbose($set_value = null)
    {
        if (is_bool($set_value))
            $this->verbose = $set_value;

        return $this->verbose;
    }

    /**
     * Combination getter/setter for the $loop_interval property.
     * @param boolean $set_value
     * @return int
     */
    protected function loop_interval($set_value = null)
    {
        if ($set_value !== null) {
            if (is_numeric($set_value)) {
                $this->loop_interval = $set_value;
                switch(true) {
                    case $set_value >= 5.0 || $set_value <= 0.0:
                        $priority = 0; break;
                    case $set_value > 2.0:
                        $priority = -1; break;
                    case $set_value > 1.0:
                        $priority = -2; break;
                    case $set_value > 0.5:
                        $priority = -3; break;
                    case $set_value > 0.1:
                        $priority = -4; break;
                    default:
                        $priority = -5;
                }

                if ($priority <> pcntl_getpriority()) {
                    @pcntl_setpriority($priority);
                    if (pcntl_getpriority() == $priority) {
                        $this->log('Adjusting Process Priority to ' . $priority);
                    } else {
                        $this->log(
                            "Warning: At configured loop_interval a process priorty of `{$priority}` is suggested but this process does not have setpriority privileges." . PHP_EOL .
                                "         Consider running the daemon with `CAP_SYS_RESOURCE` privileges or set it manually using `sudo renice -n {$priority} -p {$this->pid}`"
                        );
                    }
                }

            } else {
                throw new Exception(__METHOD__ . ' Failed. Could not set loop interval. Number Expected. Given: ' . $set_value);
            }
        }

        return $this->loop_interval;
    }

    /**
     * Combination getter/setter for the $pid property.
     * @param boolean $set_value
     * @return int
     */
    protected function pid($set_value = null)
    {
        if ($set_value !== null) {
            if (is_integer($set_value))
                $this->pid = $set_value;
            else
                throw new Exception(__METHOD__ . ' Failed. Could not set pid. Integer Expected. Given: ' . $set_value);

            if ($this->is_parent)
                $this->parent_pid = $set_value;

            $this->dispatch(array(self::ON_PIDCHANGE), array($set_value));
        }

        return $this->pid;
    }
}
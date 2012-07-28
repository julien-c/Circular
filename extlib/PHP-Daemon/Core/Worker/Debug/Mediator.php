<?php
/**
 * Starts a debug console so you can introspect and direct the inter-process communication between workers.
 * Essentially sets "break points" each time a process is forked or messages are passed to/from it.
 * You can use the debug console to step forward, throw an exception, continue and turn off debugging, kill the
 * running daemon, and use several introspection and information commands. You can easily set breakpoints in your
 * workers by calling `$this->mediator->prompt("label")` from your worker class (For function-based workers, you
 * can pass the worker into the function as an argument and call prompt() on it)
 *
 * When we move to PHP 5.4 later in 2012, this functionality will be re-implemented as a mix-in. It really shouldn't
 * be in the inheritance chain and it does cause problems. I don't want this code to be executed at all when the
 * daemon is in production: for reasons of performance and simplicity so right now we've resorted to having
 * duplicate instances of FunctionMediator and ObjectMediator.
 *
 * @see https://github.com/shaneharter/PHP-Daemon/wiki/Debugging-Workers
 * @author Shane Harter
 */
abstract class Core_Worker_Debug_Mediator extends Core_Worker_Mediator
{
    protected $debug = true;
    const INDENT_DEPTH = 6;

    /**
     * Used to determine which process has access to issue prompts to the debug console.
     * @var Resource
     */
    private $mutex;

    public $consoleshm;

    /**
     * Does this process currently own the semaphore?
     * @var bool
     */
    private $mutex_acquired = false;

    public function setup() {
        ini_set('display_errors', 0); // Displayed errors won't break the debug console but it will make it more difficult to use. Tail a log file in another shell instead.
        $ftok = ftok(Core_Daemon::filename(), 'D');
        $this->mutex = sem_get($ftok, 1, 0666, 1);
        $this->consoleshm = shm_attach($ftok, 64 * 1024, 0666);
        parent::setup();
    }

    public function __destruct() {
        @shm_remove($this->consoleshm);
        @shm_detach($this->consoleshm);
        parent::__destruct();
    }

    /**
     * Destroy any data in shared resources.
     * @return void
     */
    protected function ipc_destroy() {
        $prompt = "Destroy IPC Resources";
        if ($this->prompt($prompt))
            parent::ipc_destroy();
    }

    /**
     * Fork an appropriate number of daemon processes. Looks at the daemon loop_interval to determine the optimal
     * forking strategy: If the loop is very tight, we will do all the forking up-front. For longer intervals, we will
     * fork as-needed. In the middle we will avoid forking until the first call, then do all the forks in one go.
     * @return mixed
     */
    protected function fork() {
        $processes = count($this->processes);
        if ($this->workers <= $processes)
            return;

        switch ($this->forking_strategy) {
            case self::LAZY:
                $stat = $this->ipc_status();
                if ($processes > count($this->running_calls) || count($this->calls) == 0 && $stat['messages'] == 0)
                    $forks = 0;
                else
                    $forks = 1;
                break;
            case self::MIXED:
            case self::AGGRESSIVE:
            default:
                $forks = $this->workers - $processes;
                break;
        }

        if ($forks > 1)
            $prompt = "Forking {$forks} New Worker Processes";
        elseif ($forks > 0)
            $prompt = "Forking 1 New Worker Process";

        if (empty($prompt) || $this->prompt($prompt))
            return parent::fork();

        return false;
    }

    /**
     * Send messages for the given $call_id to the right queue based on that call's state. Writes call data
     * to shared memory at the address specified in the message.
     * @param $call_id
     * @return bool
     */
    protected function message_encode($call_id) {
        $call_status = $this->calls[$call_id]->status;
        $statuses = array(
            self::UNCALLED   =>  'Daemon sending Call message to Worker',
            self::RUNNING    =>  'Worker sending "running" ack message to Daemon',
            self::RETURNED   =>  'Worker sending "return" ack message to Daemon',
        );

        if (isset($statuses[$call_status]))
            $status = $statuses[$call_status];
        else {
            $calltype = gettype($call_id);
            $type = gettype($call_status);
            $status = "Unknown Status. (Status: {$call_status}) (Type: $type) (CallId Type: $calltype)";
        }

        $indent = ($call_id - 1) % self::INDENT_DEPTH;
        $indent = str_repeat("\t", $indent);

        $prompt = "{$indent}[{$call_id}] {$status}";
        if ($this->prompt($prompt, $call_id))
            return parent::message_encode($call_id);

        return false;
    }

    /**
     * Decode the supplied-message. Pulls in data from the shared memory address referenced in the message.
     * @param array $message
     * @return mixed
     * @throws Exception
     */
    protected function message_decode(Array $message) {
        $call_id = parent::message_decode($message);
        $call_status = $message['status'];
        $statuses = array(
            self::UNCALLED    =>  "This worker will run {$this->calls[$call_id]->method}()..",
            self::RUNNING   =>  "Worker is now running {$this->calls[$call_id]->method}()..",
            self::RETURNED  =>  "Worker has returned from {$this->calls[$call_id]->method}()..",
        );

        if (isset($statuses[$call_status]))
            $status = $statuses[$call_status];
        else {
            $calltype = gettype($call_id);
            $type = gettype($call_status);
            $status = "Unknown Status! (Status: {$call_status}) (Type: $type) (CallId Type: $calltype)";
        }

        $indent = ($call_id - 1) % self::INDENT_DEPTH;
        $indent = str_repeat("\t", $indent);
        $prompt = "{$indent}[{$call_id}] {$status}";
        $this->prompt($prompt, $message, function() {
            throw new Exception('User Interrupt! Message Discarded');
        });

        return $call_id;
    }

    /**
     * Mediate all calls to methods on the contained $object and pass them to instances of $object running in the background.
     * @param string $method
     * @param array $args
     * @param int $retries
     * @return bool
     * @throws Exception
     */
    protected function call(stdClass $call) {
        $status = ($this->is_idle()) ? 'Realtime' : 'Queued';
        $prompt = ($call->method == 'execute') ? '' : "->{$call->method}";
        $indent = ($call->id - 1) % self::INDENT_DEPTH;
        $indent = str_repeat("\t", $indent);
        if ($this->prompt("{$indent}[{$call->id}] Call to {$this->alias}{$prompt}()", $call->args))
            return parent::call($call);

        return false;
    }

    /**
     * Run the console with the supplied $prompt
     * @param string $prompt
     * @return string   Returns the raw input
     * @throws Exception
     */
    public function prompt($prompt, $args = null, Closure $on_interrupt = null) {

        $that = $this;
        $daemon = $this->daemon;

        $breakpoint = new Exception();
        $breakpoint = $breakpoint->getTrace();
        $breakpoint = sprintf('%s:%s', $breakpoint[0]['file'], $breakpoint[0]['line']);

        static $state = false;

        if(!is_resource($this->consoleshm)) {
            return true;
        }

        // Each running process will display its own debug console. Use a mutex to serialize the execution
        // and control access to STDIN. We use shared memory -- abstracted using the $state closure -- to share settings among them
        if (!$state) {
            $state = function($key, $value = null) use ($that, $daemon) {
                static $state = false;
                $defaults = array(
                    'parent'  => $daemon->parent_pid(),
                    'enabled' => true,
                    'indent'  => true,
                    'last'    => '',
                    'banner'  => true,
                    'warned'  => false,
                );

                if (shm_has_var($that->consoleshm, 1))
                    $state = shm_get_var($that->consoleshm, 1);
                else
                    $state = $defaults;

                // If the process was kill -9'd we might have settings from last debug session hanging around.. wipe em
                if ($state['parent'] != $daemon->parent_pid()) {
                    $state = $defaults;
                    shm_put_var($that->consoleshm, 1, $state);
                }

                if ($value === null)
                    if (isset($state[$key]))
                        return $state[$key];
                    else
                        return null;

                $state[$key] = $value;
                return shm_put_var($that->consoleshm, 1, $state);
            };
        }

        if ((!$state('enabled') || $state("skip_$breakpoint") || ($state('skip_until') !== null && $state('skip_until') > time())))
            return true;

        if (!$this->mutex_acquired) {
            $this->mutex_acquired = sem_acquire($this->mutex);
            // Just in case another process changed settings while we were waiting for the mutex...
            if ((!$state('enabled') || $state("skip_$breakpoint") || ($state('skip_until') !== null && $state('skip_until') > time())))
                return true;
        }

        if ($state('banner')) {
            echo PHP_EOL, get_class($this->daemon), ' Debug Console';
            echo PHP_EOL, 'Use `help` for list of commands', PHP_EOL, PHP_EOL;
            $state('banner', false);
        }

        try {

            if (!$state('indent'))
                $prompt = str_replace("\t", '', $prompt);

            $pid    = $this->daemon->pid();
            $dw     = ($this->daemon->is_parent()) ? 'D' : 'W';
            $prompt = "[$this->alias $pid $dw] $prompt > ";
            $break  = false;

            // We have to clear the buffer of any input that occurred in the terminal in the space after they submitted their last
            // command and before this new prompt. Otherwise it'll be read from fgets below and probably ruin everything.
            stream_set_blocking(STDIN, 0);
            while(fgets(STDIN)) continue;
            stream_set_blocking(STDIN, 1);

            // Commands that set $break=true will continue forward from the command prompt.
            // Otherwise it will just do the action (or display an error) and then repeat the prompt

            while(!$break) {

                echo $prompt;
                $input = trim(fgets(STDIN));
                $input = preg_replace('/\s+/', ' ', $input);

                $matches = false;
                $message = '';

                if (substr($input, -2) == '[A') {
                    $input = $state('last');
                } elseif(!empty($input)) {
                    $state('last', $input);
                }

                // Validate the input as an expression

                if (!$matches && preg_match('/^show local (\d+)/i', $input, $matches) == 1) {
                    if (!is_array($this->calls)) {
                        echo "No Calls In Memory", PHP_EOL;
                        continue;
                    }

                    if (isset($this->calls[$matches[1]]))
                        $message = print_r(@$this->calls[$matches[1]], true);
                    else
                        $message = "Item Does Not Exist";
                }

                if (!$matches && preg_match('/^show[\s]*(\d+)?$/i', $input, $matches) == 1) {
                    if (empty($this->shm)) {
                        echo "Shared Memory Not Connected Yet", PHP_EOL;
                        continue;
                    }

                    if (count($matches) == 1) {
                        $id = 1; // show the header
                    } else {
                        $id = $matches[1];
                    }

                    $message = print_r(@shm_get_var($this->shm, $id), true);
                }

                if (!$matches && preg_match('/^signal (\d+)/i', $input, $matches) == 1) {
                    posix_kill($this->daemon->parent_pid(), $matches[1]);
                    $message = "Signal Sent";
                }

                if (!$matches && preg_match('/^skipfor (\d+)/i', $input, $matches) == 1) {
                    $time = time() + $matches[1];
                    $state("skip_until", $time);
                    $message = "Skipping Breakpoints for $matches[1] seconds. Will resume at " . date('H:i:s', $time);
                }

                if (!$matches && preg_match('/^call ([A-Z_0-9]+) (.*)?/i', $input, $matches) == 1) {
                    if (count($matches) == 3) {
                        $args = str_replace(',', ' ', $matches[2]);
                        $args = explode(' ', $args);
                    }

                    $context = ($this instanceof Core_Worker_Debug_ObjectMediator) ? $this->object : $this;
                    $function = array($context, $matches[1]);

                    if (is_callable($function))
                        if (call_user_func_array($function, $args) === true)
                            $message = $break = true;
                    else
                        $message = "Function Not Callable!";
                }

                if (!$matches && preg_match('/^eval (.*)/i', $input, $matches) == 1) {
                    $return = @eval($matches[1]);
                    if ($return === false)
                        $message = "eval returned false -- possibly a parse error. Check semi-colons, parens, braces, etc.";
                    elseif ($return !== null)
                        $message = "eval() returned:" . PHP_EOL . print_r($return, true);
                    echo PHP_EOL;
                }

                if ($matches) {
                    if ($message)
                        echo $message, PHP_EOL;

                    continue;
                }

                // Wasn't an expression.
                // Validate input as a command.

                switch(strtolower($input)) {
                    case 'help':
                        $out = array();
                        $out[] = 'For the PHP Simple Daemon debugging guide, see: ';
                        $out[] = 'https://github.com/shaneharter/PHP-Daemon/wiki/Debugging-Workers';
                        $out[] = '';
                        $out[] = 'Available Commands:';
                        $out[] = 'y                 Step to the next break point';
                        $out[] = 'n                 Interrupt';
                        $out[] = '';
                        $out[] = 'call [f] [a,b..]  Call a worker\'s function in the local process, passing remaining values as args. Return true: a "continue" will be implied. Non-true: keep you at the prompt';
                        $out[] = 'cleanipc          Clean all systemv resources including shared memory and message queues. Does not remove semaphores. REQUIRES CONFIRMATION.  ';
                        $out[] = 'end               End the debugging session, continue the daemon as normal.';
                        $out[] = 'eval [php]        Eval the supplied code. Passed to eval() as-is. Any return values will be printed. Run context is the Core_Worker_Mediator class.';
                        $out[] = 'help              Print This Help';
                        $out[] = 'indent [y|n]      When turned-on, indentation will be used to group messages from the same call in a column so you can easily match them together.';
                        $out[] = 'kill              Kill the daemon and all of its worker processes.';
                        $out[] = 'skip              Skip this breakpoint from now on.';
                        $out[] = 'skipfor [n]       Run the daemon (and skip ALL breakpoints) for N seconds, then return to normal break point operation.';
                        $out[] = 'show [n]          Display the Nth item in shared memory. If no ID is passed, `show` will show the shared memory header.';
                        $out[] = 'show args         Display any arguments that may have been passed at the breakpoint.';
                        $out[] = 'show local [n]    Display the Nth item in local memory - from the $this->calls array';
                        $out[] = 'signal [n]        Send the n signal to the parent daemon.';
                        $out[] = 'shutdown          End Debugging and Gracefully shutdown the daemon after the current loop_interval.';
                        $out[] = 'status            Display current process stats';
                        $out[] = 'trace             Print A Stack Trace';
                        $out[] = 'types             Display a table of message types and statuses so you can figure out what they mean.';
                        $out[] = '';
                        $message = implode(PHP_EOL, $out);
                        break;

                    case 'types':
                        $out = array();
                        $out[] = 'Message Types:';
                        $out[] = '1     Worker Sending "onReturn" message to the Daemon';
                        $out[] = '2     Worker Notifying Daemon that it received the Call message and will now begin work.';
                        $out[] = '3     Daemon sending a Call message to the Worker';
                        $out[] = '';
                        $out[] = 'Statuses:';
                        $out[] = '0     Uncalled';
                        $out[] = '1     Called';
                        $out[] = '2     Running';
                        $out[] = '3     Returned';
                        $out[] = '4     Cancelled';
                        $out[] = '10    Timeout';
                        $out[] = '';
                        $message = implode(PHP_EOL, $out);
                        break;

                    case 'indent y':
                        $state('indent', true);
                        $message = 'Indent enabled';
                        break;

                    case 'indent n':
                        $state('indent', false);
                        $message = 'Indent disabled';
                        break;

                    case 'show args':
                        $message = print_r($args, true);
                        break;

                    case 'shutdown':
                        $this->daemon->shutdown();
                        $message = "Shutdown In Progress... Use `end` command to cease debugging until shutdown is complete.";
                        $break = true;
                        break;

                    case 'trace':
                        $e = new exception();
                        $message = $e->getTraceAsString();
                        break;

                    case 'end':
                        $state('enabled', false);
                        $break = true;
                        $message = 'Debugging Ended..';
                        $input = true;
                        break;

                    case 'skip':
                        $state("skip_$breakpoint", true);
                        $break = true;
                        $message = 'Breakpoint Turned Off..';
                        $input = true;
                        break;

                    case 'status':
                        if ($this->is_parent) {
                            $out = array();
                            $out[] = '';
                            $out[] = 'Daemon Process';
                            $out[] = 'Alias: ' . $this->alias;
                            $out[] = 'IPC ID: ' . $this->id;
                            $out[] = 'Workers: ' . count($this->processes);
                            $out[] = 'Max Workers: ' . $this->workers;
                            $out[] = 'Running Jobs: ' . count($this->running_calls);
                            $out[] = '';
                            $out[] = 'Processes:';
                            if ($this->processes)
                                $out[] = $this->processes;
                            else
                                $out[] = 'None';

                            $out[] = '';
                            $message = implode(PHP_EOL, $out);
                        } else {
                            $out = array();
                            $out[] = '';
                            $out[] = 'Worker Process';
                            $out[] = 'Alias: ' . $this->alias;
                            $out[] = 'IPC ID: ' . $this->id;
                            $out[] = '';
                            $message = implode(PHP_EOL, $out);
                        }
                        break;

                    case 'kill':
                        @fclose(STDOUT);
                        @fclose(STDERR);
                        @exec('ps -C "php ' . $this->daemon->filename() . '" -o pid= | xargs kill -9 ');
                        break;

                    case 'cleanipc':
                        if (!$state('warned')) {
                            $message = "WARNING: This will release all Shared Memory and Message Queue IPC resources. Only run this if you want ALL resources released.";
                            $message .= "If this is a production server, you should probably not do this. Does NOT release semaphores. To clean all types, including semaphores, use the scripts/clean_ipc.php tool";
                            $message .= PHP_EOL . PHP_EOL . "Repeat command to proceed with the IPC cleaning.";
                            $state('warned', true);
                            break;
                        }
                        $script = dirname(dirname(dirname(dirname(__FILE__)))) . '/scripts/clean_ipc.php';
                        @passthru("php $script -s --confirm");
                        echo PHP_EOL;
                        break;

                    case 'y':
                        $break = true;
                        $input = true;
                        break;

                    case 'n':
                        if (is_callable($on_interrupt))
                            $on_interrupt();

                        $input = false;
                        $break = true;
                        break;

                    default:

                        if ($input)
                            $message = "Unknown Command! See `help` for list of commands.";
                }

                if ($message)
                    echo $message, PHP_EOL;
            }
        } catch (Exception $e) {
            @sem_release($this->mutex);
            $this->mutex_acquired = false;
            throw $e;
        }

        @sem_release($this->mutex);
        $this->mutex_acquired = false;
        return $input;
    }
}
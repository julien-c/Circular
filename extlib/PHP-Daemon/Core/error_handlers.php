<?php

/*
 * A good error handling strategy is important.
 * 1. We want a daemon to be very resilient and hard to fail fatally, but when it does fail, we need it to fail loudly. Silent
 * failures are my biggest fear.
 *
 * 2. Error handlers are implemented as close to line 1 of your app as possible.
 *
 * 3. We use all the tools PHP gives us: an error handler, an exception handler, and a global shutdown handler.
 *
 */

/**
 * Override the PHP error handler while still respecting the error_reporting, display_errors and log_errors ini settings
 *
 * @param $errno
 * @param $errstr
 * @param $errfile
 * @param $errline
 * @param $e Used when this is a user-generated error from an uncaught exception
 * @return boolean
 */
function daemon_error($errno, $errstr, $errfile, $errline, $errcontext = null, Exception $e = null)
{
    static $runonce = true;
    static $is_writable = true;

	// Respect the error_reporting Level
	if(($errno & error_reporting()) == 0)
		return true;

    if ($runonce) {
        if (ini_get('log_errors')) {
            $error_log = ini_get('error_log');
            if ($error_log != 'syslog' && !is_writable($error_log)) {
                $is_writable = false;
                error_log("\nNote: The PHP error_log at {$error_log} is not writable! Errors will be written to STDERR. Fix the permissions problem or correct the error_log path.");
            }
        }
        $runonce = false;
    }

	$is_fatal = false;

    switch ($errno) {
        case -1:
            // Custom - Works with the daemon_exception exception handler
            $is_fatal = true;
            $errors = 'Exception';
            break;
        case E_NOTICE:
        case E_USER_NOTICE:
            $errors = 'Notice';
            break;
        case E_WARNING:
        case E_USER_WARNING:
            $errors = 'Warning';
            break;
        case E_ERROR:
        case E_USER_ERROR:
        	$is_fatal = true;
            $errors = 'Fatal Error';
            break;
        default:
            $errors = 'Unknown';
            break;
	}

	$message = sprintf('PHP %s: %s in %s on line %d pid %s', $errors, $errstr, $errfile, $errline, getmypid());

    if (ini_get('log_errors')) {
        error_log($message);
        if ($is_fatal) {
            if (!$e)
                $e = new Exception;
            error_log(str_replace(PHP_EOL, PHP_EOL . str_repeat(' ', 23), print_r($e->getTraceAsString(), true)));
        }
    }

    if (ini_get('display_errors') && (!ini_get('log_errors') || $is_writable)) {
    	echo PHP_EOL, $message, PHP_EOL;
        if ($is_fatal) {
            if (!$e)
                $e = new Exception;
            echo $e->getTraceAsString(), PHP_EOL;
        }
    }

    if ($is_fatal) {
    	exit(1);
    }

    return true;
}

/**
 * Capture any uncaught exceptions and pass them as input to the error handler
 * @param Exception $e
 *
 */
function daemon_exception(Exception $e) {
    daemon_error(-1, $e->getMessage(), $e->getFile(), $e->getLine(), null, $e);
}

/**
 * When the process exits, check to make sure it wasn't caused by an un-handled error.
 * This will help us catch nearly all types of php errors.
 * @return void
 */
function daemon_shutdown_function()
{
    $error = error_get_last();

    if (is_array($error) && isset($error['type']) == false)
    	return;

    switch($error['type'])
    {
    	case E_ERROR:
    	case E_PARSE:
    	case E_CORE_ERROR:
    	case E_CORE_WARNING:
    	case E_COMPILE_ERROR:

			//daemon_error($error['type'], $error['message'], $error['file'], $error['line']);
    }
}
error_reporting(E_ALL);
//error_reporting(E_WARNING | E_USER_ERROR);
set_error_handler('daemon_error');
set_exception_handler('daemon_exception');
register_shutdown_function('daemon_shutdown_function');

#!/usr/bin/php
<?php
require_once 'config.php';
require_once 'error_handlers.php';

use Examples\Tasks;

// The daemon needs to know from which file it was executed.
Tasks\ParallelTasks::setFilename(__FILE__);

// The run() method will start the daemon loop. 
Tasks\ParallelTasks::getInstance()->run();
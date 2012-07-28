<?php

/**
 * Objects that implement the Core_IChildInterface can be passed directly to the Core_Daemon::fork() method. This gives
 * you a way to run setup() and teardown() code specific to the code you want to run in that fork().
 *
 * You can use the Core_Daemon::on(ON_FORK) method to provide universal setup code that is run after every fork and
 * in every worker. The setup() method defined here can be used if you want specific setup code run in this forked process.
 *
 */
interface Core_ITask
{
    /**
     * Called on Construct or Init
     * @return void
     */
    public function setup();

    /**
     * Called on Destruct
     * @return void
     */
    public function teardown();

    /**
     * This is called after setup() returns
     * @return void
     */
    public function start();
}
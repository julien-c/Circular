<?php

/**
 * Objects that implement Core_IWorker can be passed to Core_Daemon::worker() to create persistent background
 * workers. Your object's public methods (aside from the ones specified by the interface) will be intercepted when
 * you call them, serialized, and run in the background process.
 *
 * You can use the Core_Daemon::on(ON_FORK) method to provide universal setup code that is run after every fork and
 * in every worker. The setup() method defined here can be used if you want specific setup code run in this forked process.
 */

interface Core_IWorker
{
    /**
     * Interfaces cannot specific properties, but note that a reference to the Mediator object will be set as $this->mediator in your Worker
     * Note: While this would in theory enable you to do crazy things like grab an instance of the mediator object from within a worker and use that
     * to assign work to yourself or other workers, in an inception-worthy system of parents and children, doing anything of the sort is
     * likely to fail spectacularly.
     *
     * @example Write to the daemon's log: $this->mediator->log('message', $is_error=false)
     * @example Access objects set in your Daemon:  $this->mediator->daemon('dbconn')
     * @example Access plugins: $ini=$this->mediator->daemon('ini'); $password = $ini['database']['password'];
     */
    // public $mediator;

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
     * This is called during object construction to validate any dependencies
     * @return Array    Return array of error messages (Think stuff like "GD Library Extension Required" or
     *                  "Cannot open /tmp for Writing") or an empty array
     */
    public function check_environment();
}
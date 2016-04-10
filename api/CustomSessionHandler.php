<?php

use Altmetric\MongoSessionHandler;


class CustomSessionHandler extends MongoSessionHandler {
	
	public function __construct() {
		$m = new MongoClient;
		$logger = new Psr\Log\NullLogger;
		parent::__construct($m->circular->sessions, $logger);
	}
	
	public function gc($maxlifetime) {
		// NOOP
		return;
	}

}

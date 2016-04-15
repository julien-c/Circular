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
	
	
	
	public function setup() {
		session_set_save_handler($this);
		session_set_cookie_params(60*60*24*365*20);
		session_start();
	}
}

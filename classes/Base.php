<?php

/**
 * Abstract class for feeds..
 *
 */

class Base {

	public function __construct() {

	}

	public function log ($message) {
		if (WP_DEBUG_LOG === true) {
			if (is_array($message) || is_object($message)) {
				error_log(print_r($message, true));
			}
			else {
				error_log($message);
			}
		}
	}

}

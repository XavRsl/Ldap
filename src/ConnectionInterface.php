<?php namespace Xavrsl\Ldap;

interface ConnectionInterface {

	/**
	 * Establish the connection to the LDAP.
	 *
	 */
	public function connect();

	/**
	 * Bind to the LDAP.
	 *
	 */
	public function bind();

	/**
	 * Get the resource returned from Ldap.
	 *
	 * @return resource
	 */
	public function getResource();
}
<?php namespace Xavrsl\Ldap\Facades;

use Illuminate\Support\Facades\Facade;

class Ldap extends Facade {

	/**
	 * Get the registered name of the component.
	 *
	 * @return string
	 */
	protected static function getFacadeAccessor() { return 'ldap'; }

}
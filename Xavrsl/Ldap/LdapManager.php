<?php namespace Xavrsl\Ldap;

use Illuminate\Support\Manager;

class LdapManager {

	/**
	 * The application instance.
	 *
	 * @var Illuminate\Foundation\Application
	 */
	protected $app;

	/**
	 * The active connection instances.
	 *
	 * @var array
	 */
	protected $connections = array();

	/**
	 * Create a new Ldap manager instance.
	 *
	 * @param  Illuminate\Foundation\Application  $app
	 * @return void
	 */
	public function __construct($app)
	{
		$this->app = $app;
	}

	/**
	 * Get a Ldap connection instance.
	 *
	 * @param  string  $name
	 * @return Xavrsl\Ldap\Directory
	 */
	public function connection($name = null)
	{
		if ( ! isset($this->connections[$name]))
		{
			$this->connections[$name] = $this->createConnection($name);
		}

		return $this->connections[$name];
	}

	/**
	 * Create the given connection by name.
	 *
	 * @param  string  $name
	 * @return Xavrsl\Ldap\Directory
	 */
	protected function createConnection($name)
	{
		$config = $this->getConfig($name);

		// Ldap may optionally have a password. So, we will attempt to extract out
		// the password from the configuration. But one is not required so we'll
		// just use array_get to return null if one hasn't been set in config.
		$password = array_get($config, 'bindpassword');

		$connection = new Directory($config['server'], $config['port'], $config['binddn'], $password);

		$connection->connect();
		$connection->bind();

		return $connection;
	}

	/**
	 * Get the configuration for a connection.
	 *
	 * @param  string  $name
	 * @return array
	 */
	protected function getConfig($name)
	{
		$name = $name ?: $this->getDefaultConnection();

		// To get the database connection configuration, we will just pull each of the
		// connection configurations and get the configurations for the given name.
		// If the configuration doesn't exist, we'll throw an exception and bail.
		$connections = $this->app['config']['database.ldap'];

		if (is_null($config = array_get($connections, $name)))
		{
			throw new \InvalidArgumentException("Ldap [$name] not configured.");
		}

		return $config;
	}

	/**
	 * Get the default connection name.
	 *
	 * @return string
	 */
	protected function getDefaultConnection()
	{
		return 'default';
	}

	/**
	 * Dynamically pass methods to the default connection.
	 *
	 * @param  string  $method
	 * @param  array   $parameters
	 * @return mixed
	 */
	public function __call($method, $parameters)
	{
		return call_user_func_array(array($this->connection(), $method), $parameters);
	}

}
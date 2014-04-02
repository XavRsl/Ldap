<?php namespace Xavrsl\Ldap;

class Directory {

	/**
	 * The configuration of the package.
	 *
	 * @var string
	 */
	protected $config;

	/**
	 * The bind password for ldap
	 *
	 * @var int
	 */
	protected $bindpwd;

	/**
	 * The connection to the Ldap.
	 *
	 * @var resource
	 */
	protected $connection;

	/**
	 * Binded to the Ldap.
	 *
	 * @var resource
	 */
	protected $binded;

	/**
	 * Search results.
	 *
	 * @var array
	 */
	protected $results;

	/**
	 * Requested entries
	 *
	 * @var array
	 */
	protected $requestedEntries;

	/**
	 * Current Attributes
	 *
	 * @var array
	 */
	protected $attributes;

	/**
	 * Create a new Ldap connection instance.
	 *
	 * @param  string  $server
	 * @param  string  $port
	 * @return void
	 */
	public function __construct($config, $bindpwd)
	{
		$this->config = $config;
		$this->bindpwd = $bindpwd;
	}

	/**
	 * Establish the connection to the LDAP.
	 *
	 * @return resource
	 */
	public function connect()
	{
		if ( ! is_null($this->connection)) return $this->connection;

		$server = $this->getConfig('server');
		$this->connection = ldap_connect($server, $this->getConfig('port'));

		if ($this->connection === false)
		{
			throw new \Exception("Connection to Ldap server {$server} impossible.");
		}

		ldap_set_option($this->connection, LDAP_OPT_PROTOCOL_VERSION, 3);
		ldap_set_option($this->connection, LDAP_OPT_REFERRALS, 0);
	}

	/**
	 * Bind to the LDAP.
	 *
	 * @return resource
	 */
	public function bind()
	{
		if ( ! is_null($this->binded)) return $this->binded;

		$this->binded = ldap_bind($this->connection, $this->getConfig('binddn'), $this->bindpwd);

		if ($this->binded === false)
		{
			throw new \Exception("Can't bind to the Ldap server with these credentials.");
		}
	}

	public function query($method, $parameters)
	{
		if ($method == 'people') {
			if (count($parameters) !== 1) {
				throw new \Exception('People method expecting only one parameter (can be an array)');
			}
			$this->setRequestedEntries($parameters[0]);
			return $this;
		}
		elseif ($method == 'auth') {
			if (count($parameters) !== 2) {
				throw new \Exception('Auth takes Userid and Password as parameters');
			}
			return $this->auth($parameters[0], $parameters[1]);
		}
		else {
			throw new \Exception("This function is not implemented (Yet ?).");
		}
	}

	/**
	 * Set the requested entries list
	 *
	 * @param array  $searchArray
	 **/
	protected function setRequestedEntries($searchArray)
	{
		$searchArray = $this->getParameters($searchArray);

		if ($this->checkWildcards($searchArray))
		{
			throw new \Exception('Wildcards (*) are not usable right now...');
		}

		// Fill an array containing the list of the entries we are looking for
		$this->requestedEntries = $searchArray;
	}

	/**
	 * Get requested parameters as an array.
	 *
	 * @param string|array  $parameters
	 * @return array
	 **/
	protected function getParameters($parameters)
	{
		// People identifiers can be given as string ('xavrsl'), as string containing
		// multiple identifiers ('xavrsl, jeanmich') or as an array (['xavrsl', 'jeanmich'])
		// Let's turn everything into a array
		if (is_string($parameters)) {
			return preg_split('/, ?/', $parameters);
		}

		return $parameters;
	}

	/**
	 * Look for wildcards in parameters
	 *
	 * @param array  $parameters
	 * @return boolean
	 **/
	protected function checkWildcards($parameters)
	{
		foreach($parameters as $parameter)
		{
			if(strpos($parameter, '*')) return true;
		}
		return false;
	}

	public function auth($userid, $password)
	{
		// Prevent null binding
		if ($userid === null || $password === null) {
			return false;
		}
		if (empty($userid) || empty($password)) {
			return false;
		}
		//get user details (and cache it) using peoplequery. This uses admin credentials
		$this->peopleQuery($userid);
		//try to bind user dn with user credentials
		try {
			$user = $this->getstore($userid);
			return ldap_bind($this->connection, $user[$this->config['userdn']], $password);
		} catch (\Exception $e) {
			return false;
		}
	}

	public function __get($attribute)
	{
		return $this->get($attribute);
	}

	/**
	 * Get Ldap attributes and make the search
	 *
	 * @param string|array  $usernames
	 * @return mixed
	 */
	public function get($attributes = null)
	{
		$cachedEntries = $this->getEntriesFromCache();

		if(!empty($cachedEntries))
			$directoryEntries = $this->getEntriesFromDirectory($cachedEntries);

		// if no attributes are supplied, use all from config 'attributes' setting
		if ($attributes === null) {
			$this->attributes = $this->getConfigAttributes();
		}
		else {
			$this->attributes = $this->getParameters($attributes);
		}
		return $this->output();
	}

	/**
	 * Get Attributes entry in config and check it's value
	 *
	 * @return array
	 */
	protected function getConfigAttributes()
	{
		$attributes = $this->getConfig('attributes');

		if (!is_array($attributes))
		{
			throw new \Exception('Attributes entry in config must be an array.');
		}

		if ($this->is_assoc($attributes))
		{
			throw new \Exception('Attributes entry in config cannot be an associative array.');
		}

		// You often find Ldap attributes in a CamelCase form. This is irrelevant
		// when querying Ldap with PHP.
		return array_map('strtolower', $attributes);
	}

	/**
	 * Get config setting
	 *
	 * @param string  $setting
	 * @return mixed
	 */
	protected function getConfig($setting)
	{
		if(!array_key_exists($setting, $this->config))
		{
			throw new \Exception('Setting doesn\'t exist in config.');
		}
		return $this->config[$setting];
	}

	/**
	 * Get the entries from Cache
	 *
	 * @return array
	 **/
	private function getEntriesFromCache()
	{
		// Write to results, pop entry from requestedEntries
		$striped = array();
		// get rid of the users we already know
		foreach($this->requestedEntries as $k => $v) {
			if (!$this->instore($v)) {
				$striped[$k] = $v;
			}
		}
		return $striped;
	}

	/**
	 * Get the entries from Directory
	 *
	 * @param array  $usernames
	 * @return void
	 **/
	protected function getEntriesFromDirectory($usernames)
	{
		// Check if people DN exists in config
		if (is_null($peopledn = $this->config['peopledn']))
		{
			throw new \Exception('No People DN in config');
		}
		$baseFilter = $this->config['basefilter'];

		// $usernames is an array
		$filter = '(|';
		foreach($usernames as $username) {
			$filter .= str_replace('%uid', "{$username}", $baseFilter);
		}
		$filter .= ')';

		$attributes = $this->config['attributes'];
		$key = $this->config['key'];

		$sr = ldap_search($this->connection, $peopledn, $filter, $attributes);
		// return an array of CNs
		$results = ldap_get_entries($this->connection, $sr);
		for($i = 0; $i < $results['count']; $i++) {
			$this->store($results[$i][$key][0], $results[$i]);
		}
	}

	private function store($key, $value = '') {
		\Cache::put($key, $value, $this->config['cachettl']);
		$this->results[$key] = $value;
	}

	private function getstore($key) {
		return (isset($this->results[$key])) ? $this->results[$key] : \Cache::get($key);
	}

	private function instore($key) {
		return (isset($this->results[$key])) ? true : \Cache::has($key);
	}

	/**
	 * Output the finilized result
	 *
	 * @var array $data
	 */
	private function output() {
		if(count($this->requestedEntries) == 1 && count($this->attributes) == 1) {
			$attr = $this->attributes[0];
			$un = $this->requestedEntries[0];
			$user =  $this->getstore($un);
			return $user[$attr][0];
		}
		else {
			$output = array();
			foreach($this->requestedEntries as $n => $u) {
				if($this->instore($u)) {
					$user = $this->getstore($u);
					foreach($this->attributes as $a){
						$output[$u][$a] = array_key_exists($a, $user) ? $user[$a][0] : null;
					}
				}
			}
			return $output;
		}
	}

	/**
	 * Close the connection to the LDAP.
	 *
	 * @return void
	 */
	public function __destruct()
	{
		if ($this->connection)
		{
			ldap_close($this->connection);
		}
	}

	/**
	 * Checks if an array is associative
	 *
	 * @param array $array
	 * @return boolean
	 */
	protected function is_assoc(array $array) {
		return (bool)count(array_filter(array_keys($array), 'is_string'));
	}

}

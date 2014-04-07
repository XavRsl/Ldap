<?php namespace Xavrsl\Ldap;

class Directory {

	/**
	 * The configuration of the package.
	 *
	 * @var string
	 */
	protected $config;

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
	 * @param  array  $config
	 */
	public function __construct($config, $connection)
	{
		$this->config = $config;
		$this->connection = $connection;
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

		$user = static::query('people', [$userid])->get($this->getConfig('userdn'));

		if(ldap_bind($this->connection->getResource(), $user, $password)) {
			return true;
		}
		return false;
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
		$this->results = array();
		$cachedEntries = $this->setEntriesFromCache();

		// Make a directory query only if the info is not already in cache.
		if(!empty($this->requestedEntries))
		{
			$directoryEntries = $this->setEntriesFromDirectory();
		}

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

		if ($this->isAssoc($attributes))
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
	 * Set the entries from Cache
	 *
	 * @return array
	 **/
	private function setEntriesFromCache()
	{
		// Delete entries we already know from requestedEntries
		foreach($this->requestedEntries as $entry) {
			if ($this->inStore($entry)) {
				// Fill in the instance's results
				$this->results[$entry] = $this->getStore($entry);

				// We have the entry in cache already. No need to look for it.
				$a = array_merge(array_diff($this->requestedEntries, array($entry)));

				$this->requestedEntries = $a;
			}
		}
	}

	/**
	 * Set the entries from Directory
	 *
	 * @return void
	 **/
	protected function setEntriesFromDirectory()
	{
		// Check if people DN exists in config
		if (is_null($peopledn = $this->getConfig('peopledn')))
		{
			throw new \Exception('No People DN in config');
		}
		$baseFilter = $this->getConfig('basefilter');

		$filter = '(|';
		foreach($this->requestedEntries as $requestedEntry) {
			$filter .= str_replace('%uid', "{$requestedEntry}", $baseFilter);
		}
		$filter .= ')';

		$attributes = $this->getConfig('attributes');
		$key = $this->getConfig('key');

		$sr = ldap_search($this->connection->getResource(), $peopledn, $filter, $attributes);
		// return an array of CNs
		$entries = ldap_get_entries($this->connection->getResource(), $sr);
		for($i = 0; $i < $entries['count']; $i++) {
			// Store in cache
			$this->store($entries[$i][$key][0], $entries[$i]);
			// Store in instance
			$this->results[$entries[$i][$key][0]] = $entries[$i];
		}
	}

	private function store($key, $value = '') {
		\Cache::put($key, $value, $this->getConfig('cachettl'));
	}

	private function getStore($key) {
		return \Cache::get($key);
	}

	private function inStore($key) {
		return \Cache::has($key);
	}

	/**
	 * Output the finilized result
	 *
	 * @var array $data
	 */
	private function output() {
		if(count($this->results) == 1 && count($this->attributes) == 1) {
			$attr = $this->attributes[0];
			$result = array_shift($this->results);
			return $this->format($result[$attr]);
		}
		else {
			$output = array();
			foreach($this->results as $n => $u) {
				foreach($this->attributes as $a){
					$output[$n][$a] = array_key_exists($a, $u) ? $this->format($u[$a]) : null;
				}
			}
			return $output;
		}
	}

	/**
	 * Format attributes
	 *
	 * Ldap attributes may be multi-valued.
	 *
	 * @var array|string  $attributes
	 * @return mixed
	 **/
	protected function format($attributes)
	{
		if(is_array($attributes))
		{
			// Typical Ldap attribute returned array is :
			// ['count' => 1, 0 => 'value']
			if(isset($attributes['count']) AND $attributes['count'] === 1)
			{
				return $attributes[0];
			}
		}
		return $attributes;
	}

	/**
	 * Close the connection to the LDAP.
	 *
	 * @return void
	 */
	public function __destruct()
	{
		if ($this->connection->getResource())
		{
			ldap_close($this->connection->getResource());
		}
	}

	/**
	 * Checks if an array is associative
	 *
	 * @param array $array
	 * @return boolean
	 */
	protected function isAssoc(array $array) {
		return (bool)count(array_filter(array_keys($array), 'is_string'));
	}

}

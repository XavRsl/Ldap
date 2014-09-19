<?php namespace Xavrsl\Ldap;

use Illuminate\Support\Facades\Cache;

class Directory {

	/**
	 * The configuration of the package.
	 *
	 * @var string
	 */
	protected $config;

	/**
	 * Requested Organisation Unit
	 *
	 * @var string
	 */
	protected $organisationUnit;

	/**
	 * Default Organisation Unit
	 *
	 * @var string
	 */
	protected $defaultOrganisationUnit = 'people';

	/**
	 * Attribute to use in filter
	 *
	 * @var string
	 */
	protected $filterAttribute;

	/**
	 * Boolean Operator
	 *
	 * @var string
	 */
	protected $booleanOperator = '|';

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
		$this->filterAttribute = $this->getConfig('filter');
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

	/************************************************/
	/*************** Public methods *****************/
	/************************************************/

	public function query($method, $parameters)
	{
		if ($this->setOrganisationUnit($method)) {
			if (count($parameters) !== 1) {
				throw new \Exception('OU method expecting only one parameter (can be an array)');
			}
			$this->filterAttribute = $this->getConfig('filter');
			$this->setRequestedEntries($parameters[0]);
			return $this;
		}
		elseif($method == 'find')
		{
			$this->find($parameters[0]);
			return $this;
		}
		elseif ($method == 'auth') {
			if (count($parameters) !== 2) {
				throw new \Exception('Auth takes Login and Password as parameters');
			}
			return $this->auth($parameters[0], $parameters[1]);
		}
		else {
			throw new \Exception("This function is not implemented (Yet ?).");
		}
	}

	/**
	 * Authenticate a user
	 *
	 * @param string  $login
	 * @param string  $password
	 * @return boolean
	 */
	public function auth($login, $password)
	{
		// Prevent null binding
		if ($login === null || $password === null) {
			return false;
		}
		if (empty($login) || empty($password)) {
			return false;
		}

		$user = static::query('people', [$login])->get($this->getConfig('userdn'));

		if(ldap_bind($this->connection->getResource(), $user, $password)) {
			return true;
		}
		return false;
	}

	/**
	 * Get Directory single attributes and make the search
	 *
	 * @param string  $usernames
	 * @return mixed
	 */
	public function __get($attribute)
	{
		return $this->get($attribute);
	}

	/**
	 * Get Directory attributes and make the search
	 *
	 * @param string|array  $usernames
	 * @return mixed
	 */
	public function get($attributes = null)
	{
		$this->results = array();
		$this->setEntriesFromCache();

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
			$this->attributes = array_map('strtolower', $this->getParameters($attributes));
		}
		return $this->output();
	}

	/**
	 * Search through the directory's selected organisation unit
	 * exemple OUs : people, group, mail, ...
	 *
	 * @var string  $organisationUnit
	 **/
	public function find($organisationUnit)
	{
		$this->setOrganisationUnit($organisationUnit);
	}

	/**
	 * Create Directory filter
	 *
	 * @var string  $attribute
	 * @var string  $search
	 **/
	public function where($attribute, $search)
	{
		$this->filterAttribute = $attribute;
		$this->setRequestedEntries($search);
		return $this;
	}

	/**
	 * Create Directory filter
	 *
	 * @var string  $attribute
	 * @var string  $search
	 **/
	public function orWhere($attribute, $search)
	{
		$this->booleanOperator = '|';
		$this->where($attribute, $search);
	}

	/************************************************/
	/************** Protected methods ***************/
	/************************************************/

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
			$this->booleanOperator = '|';
		}

		// Fill an array containing the list of the entries we are looking for
		$this->requestedEntries = $searchArray;
	}

	/**
	 * Set Organisation Unit to look for
	 *
	 * @var string  $organisationUnit
	 * @return string
	 **/
	protected function setOrganisationUnit($organisationUnit)
	{
		$searchOU = strtolower($organisationUnit);
		$confOU = array_map('strtolower', $this->getConfig('organisationUnits'));
		if (!in_array($searchOU, $confOU))
		{
			return false;
		}
		return $this->organisationUnit = $searchOU;
	}

	/**
	 * Get requested parameters as an array.
	 *
	 * @param string|array  $parameters
	 * @return array
	 **/
	protected function getParameters($parameters)
	{
		// In case user would look for numeric value
		$parameters = is_numeric($parameters) ? (string) $parameters : $parameters;

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

		// The key config parameter needs to be retrieved from the Directory
		// even if it's not in the attributes array
		if(!in_array($this->getConfig('key'), $attributes))
		{
			$attributes[] = $this->getConfig('key');
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
	 * Get Selected Organisation Unit
	 *
	 * @return string
	 */
	protected function getOrganisationUnit()
	{
		return isset($this->organisationUnit) ? $this->organisationUnit :
			$this->defaultOrganisationUnit;
	}

	/**
	 * Set the entries from Cache
	 *
	 **/
	protected function setEntriesFromCache()
	{
		// Delete entries we already know from requestedEntries
		foreach($this->requestedEntries as $entry) {
			Cache::forget($entry);
			if ($this->inStore($entry)) {
				// Fill in the instance's results
				$this->results[$entry] = $this->getStore($entry);

				// We have the entry in cache already. No need to look for it.
				$a = array_merge(
					array_diff(
						$this->requestedEntries, array($entry)
					)
				);

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
		// Check if base DN exists in config
		if (is_null($this->getConfig('basedn')))
		{
			throw new \Exception('No Base DN in config');
		}
		$dn = 'ou=' . $this->getOrganisationUnit() .','. $this->getConfig('basedn');

		$filter = '(' . $this->booleanOperator;
		foreach($this->requestedEntries as $requestedEntry) {
			$filter .= '(' . $this->filterAttribute . '=' . $requestedEntry . ')';
		}
		$filter .= ')';

		$attributes = $this->getConfigAttributes();
		$key = $this->getConfig('key');

		$sr = ldap_search($this->connection->getResource(), $dn, $filter, $attributes);
		// return an array of CNs
		$entries = ldap_get_entries($this->connection->getResource(), $sr);

		for($i = 0; $i < $entries['count']; $i++) {
			// Store in cache
			$this->store($this->format($entries[$i][$key]), $entries[$i]);
			// Store in instance
			$this->results[$this->format($entries[$i][$key])] = $entries[$i];
		}
	}

	/**
	 * Store the key in cache
	 *
	 * @var mixed  $key
	 * @var array  $value
	 */
	protected function store($key, $value) {
		// Following the key variable with an 'l' is the only way I could make cache
		// work with numeric keys. It's ugly but force typing to string didn't work...
		// It has no consequence on the 'results' array.
		Cache::put($key.'l', $value, $this->getConfig('cachettl'));
	}

	/**
	 * Get the key from cache
	 *
	 * @var mixed  $key
	 * @return array
	 */
	protected function getStore($key) {
		return Cache::get($key.'l');
	}

	/**
	 * Check if key lives in cache
	 *
	 * @var mixed  $key
	 * @return array
	 */
	protected function inStore($key) {
		return Cache::has($key.'l');
	}

	/**
	 * Output the finilized result
	 *
	 * @return array
	 */
	protected function output() {
		if(count($this->results) == 1 && count($this->attributes) == 1) {
			$attr = $this->attributes[0];
			$result = array_shift($this->results);
			
			if (!array_key_exists($attr, $result)) return false;

			return $this->format($result[$attr]);
		}
		elseif(count($this->results) == 1 && count($this->attributes) > 1) {
			$output = array();
			$u = reset($this->results);
			foreach($this->attributes as $a){
				$output[$a] = array_key_exists($a, $u) ? $this->format($u[$a]) : null;
			}
			return $output;
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
	 * Checks if an array is associative
	 *
	 * @param array $array
	 * @return boolean
	 */
	protected function isAssoc(array $array) {
		return (bool)count(array_filter(array_keys($array), 'is_string'));
	}

}

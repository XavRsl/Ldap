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
	 * Current Usernames
	 *
	 * @var array
	 */
	protected $usernames;

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

		$this->connection = ldap_connect($this->config['server'], $this->config['port']);		

		if ($this->connection === false)
		{
			throw new \Exception("Connection to Ldap server {$this->server} impossible.");
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

		$this->binded = ldap_bind($this->connection, $this->config['binddn'], $this->bindpwd);		

		if ($this->binded === false)
		{ 
			throw new \Exception("Can't bind to the Ldap server with these credentials.");
		}
	}

	public function query($method, $arguments) 
	{
		if ($method == 'people') {
			return $this->peopleQuery($arguments[0]);
		}
		else {
			throw new \Exception("This function is not implemented (Yet ?).");
		}
	}

	/**
	 * Get users from LDAP
	 * 
	 * @param string|array $usernames
	 */
	protected function peopleQuery($usernames = '*') 
	{
		//Who are we looking for ??
		if (is_string($usernames)) {
			// $usernames is * => don't want that
			if ($usernames == '*') {
				throw new \Exception("Can't walk through the entire LDAP at once...");
			}
			// $usernames is a string, convert it to an array
			else {
				$usernames = explode(',', $usernames);
			}
		} 

		$this->usernames = $usernames;
		$this->attributes = array();

		$this->strip();

		return $this;
	}

	public function __get($attribute) 
	{
		// What are we looking for ?
		$this->attributes[] = $attribute;
		return $this->output();
	}

	public function get($attributes) 
	{
		// What are we looking for ?
		if (is_string($attributes)) {
			if (strpos($attributes, ',')) {
				$attributes = explode(',', $attributes);
				array_walk($attributes, create_function('&$value', '$value = trim($value);'));
				return $this->get($attributes);
			}
			return $this->$attributes;
		}
		elseif (is_array($attributes)) {
			$this->attributes = $attributes;
		}
		return $this->output();
	}

	public function find($term)
	{
		
	}

	private function strip() {
		$striped = array();
		// get rid of the users we already know
		foreach($this->usernames as $k => $v) {
			if (!$this->instore($v)) {
				$striped[$k] = $v;
			}
		}
		if(!empty($striped))
			$this->request($striped);
	}

	private function request($usernames) {
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

		$sr = ldap_search($this->connection, $peopledn, $filter, $attributes);
		// return an array of CNs
		$results = ldap_get_entries($this->connection, $sr); 
		for($i = 0; $i < $results['count']; $i++) {
			$this->store($results[0]['login'][0], $results[0]);
		}
	}

	private function store($key, $value = '') {
		\Cache::put($key, $value, 20);
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
		if(count($this->usernames) == 1 && count($this->attributes) == 1) {
			$attr = $this->attributes[0];
			$un = $this->usernames[0];
			$user =  $this->getstore($un);
			return $user[$attr][0];
		}
		else {
			$output = array();
			foreach($this->usernames as $n => $u) {
				if($this->instore($u)) {
					$user = $this->getstore($u);
					foreach($this->attributes as $a){
						$output[$u][$a] = $user[$a];
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

}
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
			if (is_array($arguments)) {
				$arguments = implode(',', $arguments);
			}
			return $this->peopleQuery($arguments);
		}
		elseif ($method == 'auth') {
			if (count($arguments) !== 2) {
				throw new \Exception ('Auth takes Userid and Password as parameters');
			}
			return $this->auth($arguments[0], $arguments[1]);
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
		// What are we looking for ?
		$this->attributes[] = $attribute;
		return $this->output();
	}

	public function get($attributes=null)
	{
		//if no attributes are supplied, use all in config attributes setting
		if ($attributes === null) {
			$this->attributes = $this->config['attributes'];
		}
		// What are we looking for ?
		elseif (is_string($attributes)) {
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

}

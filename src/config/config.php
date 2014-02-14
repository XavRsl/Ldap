<?php

return array(
	'default' => array(

		/*
		|--------------------------------------------------------------------------
		| LDAP Server
		|--------------------------------------------------------------------------
		|
		| Address of the LDAP Server
		|
		| Example: 'cas.myuniv.edu'.
		|
		*/

		'server' => 'ldap.domain.fr',

		/*
		|--------------------------------------------------------------------------
		| LDAP Port (389 is default)
		|--------------------------------------------------------------------------
		*/

		'port' => '389',

		/*
		|--------------------------------------------------------------------------
		| LDAP Base DN
		|--------------------------------------------------------------------------
		*/

		'peopledn' => 'ou=People,dc=domain,dc=fr',

		/*
		|--------------------------------------------------------------------------
		| LDAP ADMIN bind DN
		|--------------------------------------------------------------------------
		*/

		'binddn' => 'cn=Manager,dc=domain,dc=fr',

		/*
		|--------------------------------------------------------------------------
		| LDAP ADMIN bind password
		|--------------------------------------------------------------------------
		|
		*/
		'bindpwd' => 'password',

		/*
		|--------------------------------------------------------------------------
		| cache time-to-live value in minutes. How long should we cache result if found
		|--------------------------------------------------------------------------
		*/

		'cachettl'   => 20,

		/*
		|--------------------------------------------------------------------------
		| caching key. This is typically a unique userid allotted to resources in ldap
		|--------------------------------------------------------------------------
		*/

		'key'        => 'uid',

		/*
		|--------------------------------------------------------------------------
		| userdn used for user authentication. This is the distinguished name
		| of a user that will authenticate to the directory using a BIND. Typically named 'dn'
		|--------------------------------------------------------------------------
		*/

		'userdn'     => 'dn',

		'basefilter' => '(login=%uid)',
		'searchscope' => 'SUBTREE_SCOPE',
		'attributes' => array("displayname", "sn", "givenname", "mail", 'edupersonaffiliation', 'supannaffectation', 'login'),
	
	),
);

LDAP plugin for Laravel 4.x
===========================

I know that a bundle already exists to manage LDAP auth in Laravel, but, it only works for Laravel 3.X, and it's made for authentication.  
The LDAP Zend Class in Laravel is another option... but it was a bit too much for what I needed.  
Considering this plugin from a CRUD point of view, this is only a R - it allows you to lookup the ldap details of a resource.  

Considering this plugin from a Geek point of view, this is my first time writing a plugin for Laravel 4.X as a Composer Package.  

Installing
----------
Declare a dependency on this package in your composer.json file:

```
"require": {		
		"xavrsl/ldap": "1.1"
	},
```	

Next, run composer update to pull in the code.

Add the service provider to config/app :

```
'Xavrsl\Ldap\LdapServiceProvider',
```

Add the facade to the alias array (also in config/app):

```
'Ldap' => 'Xavrsl\Ldap\Facades\Ldap',
```

You then need to customize the config file to indicate the location of your ldap server and also set your dn, attributes etc. 

```
php artisan config:publish Xavrsl/ldap
```

Now, you are ready to use all that this package offers.

Usage
-----
First remember to set ALL your config parameters. All sections have been well commented. Any attribute that you want to retrieve MUST be specified in the 'attributes' array.

1. To get the CN's of user1 and user2:
```
 $names = Ldap::people('user1', 'user2')->cn;
 dd($names);
```
displays (assuming ofcourse that both user1 and user2 exist in the directory. No data will be returned if they dont)

```
array(2) {
  ["user1"]=>
  array(1) {
    ["cn"]=>
    string(8) "Common Name 1"
  }
  ["user2"]=>
  array(1) {
    ["cn"]=>
    string(8) "Common Name 2"
  }
}
```

2. Get a single attribute for a single user

```
 $name = Ldap::people('user1')->cn;
 dd($name);
```

3. Get all attributes (as declared in config file) for users1, 2 and 3

```
$data = Ldap::people('user1','user2', 'user3')->get();
```

4. Authenticate user1 against the LDAP

```
 $boolValue = Ldap::auth('user1','user1password');
 dd($boolValue);
```    

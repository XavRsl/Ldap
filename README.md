LDAP package for Laravel 5.x
============================

This package is an attempt to provide a way to search through an Ldap Directory like you would query a database with Eloquent.

It now also comes with a AuthUserProvider so that you can hook it in as a AuthProvider for your Laravel app. In order to accomplish that, I have stolen very large pieces of this great package :
[Adldap2/Adldap2-Laravel](https://github.com/Adldap2/Adldap2-Laravel/)

The only reason why I'm not using **Adldap2-Laravel** for my projects is because it doesn't support OpenLdap yet, so if you use ActiveDirectory, go check it out !



Installing
----------
Require it from your command line :

```bash
composer require xavrsl/ldap
```

Add the service provider to config/app :

```php
Xavrsl\Ldap\LdapServiceProvider::class,
```

Add the facade to the alias array (also in config/app):

```php
'Ldap' => Xavrsl\Ldap\Facades\Ldap::class,
```

You then need to publish and customize the config file to indicate the location of your ldap server and also set your dn, attributes etc. :

```
php artisan vendor:publish
```

You're now ready to use this package !

Usage
-----
First remember to set ALL your config parameters. All sections have been well documented in the comments.
Any attribute that you want to retrieve MUST be specified in the 'attributes' array. This may seem strange but the reason I built this package was because I needed a way to query an LDAP Directory every two seconds on a big list of users. So I needed Caching. The only way I was able to cache all the fields was by providing a retrieved attributes array.

- Return an attribute from one member of your organisation :
```php
// First possibility, with find/where methods and get
Ldap::find('people')->where('uid', 8162)->get('displayname');

// Second possibility, using an alias for the get method
Ldap::find('people')->where('uid', 8162)->displayname;

// Third possibility, attribute in camelCase format
Ldap::find('people')->where('uid', 8162)->displayName;

// If default attribute is set to 'uid' in conf, you can use the short method
Ldap::people(8162)->displayname;
```
All those possibilities should return the same string (our user's displayname) :
```
Bobby Blake
```

- Return multiple attributes for a single member of organisation :
```php
// Let's directly use the short method
Ldap::people(8162)->get('displayname, mail');

// May as well use an array instead of a string
Ldap::people(8162)->get(['displayName', 'mail']);
```
This should return :
```php
array(1) [
    '8162' => array(2) [
        'displayname' => string (11) "Bobby Blake"
        'mail' => string (22) "bobby.blake@domain.org"
    ]
]
```
If you change the key in your config to some attribute like 'login' for exemple, you get :
```php
array(1) [
    'bobblake' => array(2) [
        'displayname' => string (11) "Bobby Blake"
        'mail' => string (22) "bobby.blake@domain.org"
    ]
]
```
**NOTE :** You don't need to add the 'key' attribute's value in the 'attributes' array in the config. The package does that for you.

- Return multiple attributes from multiple members of the organisation :
```php
// Let's use the short method again
Ldap::people('8162, 128')->get('displayname, mail');

// Same thing using arrays
Ldap::people(['8162', '128'])->get(['displayName', 'mail']);

// Longer syntax
Ldap::find('people')->where('uid', ['8162', '128'])->get(['displayName', 'mail']);

// Base your search on another attribute
Ldap::find('people')->where('login', ['bobblake', 'johndoe'])->get(['displayName', 'mail']);
```
This should return :
```php
array(2) [
    '108' => array(2) [
        'displayname' => string (8) "John Doe"
        'mail' => string (20) "john.doe@domain.org"
    ]
    '8162' => array(2) [
        'displayname' => string (11) "Bobby Blake"
        'mail' => string (22) "bobby.blake@domain.org"
    ]
]
```

You can also return all the attributes you've set in the 'attributes' config property :
```php
// The long way
Ldap::find('people')->where('login', ['bobblake', 'johndoe'])->get();

// The short way
Ldap::people('108, 8162')->get();
```

- Query the Ldap Directory based on a wildcard :
```php
// The long way
Ldap::find('people')->where('login', 'bob*')->get(['displayName', 'mail']);

// The short way (assuming you have set the 'filter' attribute to 'login' in config)
Ldap::people('bob*')->get(['displayName', 'mail']);

// Also works with multiple wildcards
Ldap::people('bob*, john*')->get(['displayName', 'mail']);
```
You get a result looking something like this :
```php
array(2) [
    '108' => array(2) [
        'displayname' => string (8) "John Doe"
        'mail' => string (20) "john.doe@domain.org"
    ]
    '4021' => array(2) [
        'displayname' => string (10) "John Smith"
        'mail' => string (22) "john.smith@domain.org"
    ]
    '8162' => array(2) [
        'displayname' => string (11) "Bobby Blake"
        'mail' => string (22) "bobby.blake@domain.org"
    ]
    '9520' => array(2) [
        'displayname' => string (12) "Bob McCormac"
        'mail' => string (24) "bobby.mccormac@domain.org"
    ]
]
```
You get the idea !!

- Authenticate against the Ldap Directory :
```php
// Depending on the filter attribute you've set in the config
Ldap::auth('bobblake', 'm7V3ryStr0ngP@ssw0rd!')
```
Will simply return **TRUE** or **FALSE**.

**NOTE :** Don't forget to set the dn attribute in config for user authentication.
___
TODOs :
-------

There is still a lot of work ahead to make this package complete. Here's a list of what you could expect in the future :

- Create / update attributes from the Ldap. For now, the package can only read the Ldap.
- Query multiple Organisation Units (Ldap branches, or OU. ex. : People, Groups, Mail, ...). This should work pretty soon...
- Use Active Directory and Open Ldap. For now, only Open Ldap directories work.

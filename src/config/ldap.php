<?php

return array(

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

    'server' => env('LDAP_HOST'),

    /*
    |--------------------------------------------------------------------------
    | LDAP Port (389 is default)
    |--------------------------------------------------------------------------
    */

    'port' => env('LDAP_PORT', '389'),

    /*
    |--------------------------------------------------------------------------
    | LDAP Base DN
    |--------------------------------------------------------------------------
    */

    'basedn' => env('LDAP_BASE_DN'),

    /*
    |--------------------------------------------------------------------------
    | Managed Organisation Units (OU)
    | Only people works for now
    |--------------------------------------------------------------------------
    */

    'organisationUnits' => ['people', 'groups'],

    /*
    |--------------------------------------------------------------------------
    | LDAP ADMIN bind DN
    |--------------------------------------------------------------------------
    */

    'binddn' => env('LDAP_BIND_DN', 'cn=Manager,dc=domain,dc=fr'),

    /*
    |--------------------------------------------------------------------------
    | LDAP ADMIN bind password
    |--------------------------------------------------------------------------
    |
    */
    'bindpwd' => env('LDAP_BIND_PASSWORD'),

    /*
    |--------------------------------------------------------------------------
    | Cache time-to-live value in minutes.
    | How long should we cache result if found
    |--------------------------------------------------------------------------
    */

    'cachettl'   => 20,

    /*
    |--------------------------------------------------------------------------
    | Caching & Results array key.
    | This is typically a unique attribute from the directory OU
    |--------------------------------------------------------------------------
    */

    'key'        => 'dn',

    /*
    |--------------------------------------------------------------------------
    | Default filter attribute
    | Will be used when calling short method like :
    | Ldap::people('xavrsl')->displayname;
    |--------------------------------------------------------------------------
    */

    'filter'        => 'login',

    /*
    |--------------------------------------------------------------------------
    | User dn used for user authentication.
    | This is the distinguished name of a user that will authenticate to
    | the directory using a BIND. Typically named 'dn'
    |--------------------------------------------------------------------------
    */

    'userdn'     => 'dn',

    'searchscope' => 'SUBTREE_SCOPE',

    'attributes' => [
        'uid',
        'displayName',
        'sn',
        'givenName',
        'mail',
        'edupersonAffiliation',
        'supannAffectation',
        'login',
    ],

    'username_attribute' => ['login' => 'login'],

    'password_key' => 'password',

    'login_fallback' => false,

    'sync_attributes' => [
        'name' => 'displayname'
    ],

    'bind_user_to_model' => false,

);

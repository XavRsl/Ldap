<?php


namespace Xavrsl\Ldap\Providers;


use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;
use Xavrsl\Ldap\Facades\Ldap;

class LdapAuthUserProvider extends EloquentUserProvider
{
    public function retrieveById($identifier)
    {
        $model = parent::retrieveById($identifier);

        return $this->discoverAdldapFromModel($model);
    }

    public function retrieveByToken($identifier, $token)
    {
        $model = parent::retrieveByToken($identifier, $token);

        return $this->discoverAdldapFromModel($model);
    }

    public function retrieveByCredentials(array $credentials)
    {
        // Get the username input attributes
        $attributes = $this->getUsernameAttribute();

        // Get the input key
        $key = key($attributes);

        $ldapUser = Ldap::find('people')->where($attributes[$key], $credentials[$key])->get();

        // Retrieve the users login attribute.
        $username = $ldapUser[$attributes[$key]];

        if (is_array($username)) {
            // We'll make sure we retrieve the users first username
            // attribute if it's contained in an array.
            $username = Arr::get($username, 0);
        }

        // Get the password input array key.
        $key = $this->getPasswordKey();

        // Try to log the user in.
        if (Ldap::auth($username, $credentials[$key])) {
            // Login was successful, we'll create a new
            // Laravel model with the Adldap user.
            return $this->getModelFromLdap($ldapUser, $credentials[$key]);
        }

        if ($this->getLoginFallback()) {
            // Login failed. If login fallback is enabled
            // we'll call the eloquent driver.
            return parent::retrieveByCredentials($credentials);
        }

        return;
    }

    /**
     * Creates a local User from Active Directory.
     *
     * @param array $user
     * @param string $password
     * @return Model
     */
    protected function getModelFromLdap(array $user, $password)
    {
        // Get the username attributes.
        $attributes = $this->getUsernameAttribute();

        // Get the model key.
        $key = key($attributes);

        // Get the username from the AD model.
        $username = $user[$attributes[$key]];

        // Make sure we retrieve the first username
        // result if it's an array.
        if (is_array($username)) {
            $username = Arr::get($username, 0);
        }

        // Try to retrieve the model from the model key and AD username.
        $model = $this->createModel()->newQuery()->where([$key => $username])->first();

        // Create the model instance of it isn't found.
        if (!$model instanceof Model) {
            $model = $this->createModel();
        }

        // Set the username and password in case
        // of changes in active directory.
        $model->{$key} = $username;

        // Sync the users password.
        $model = $this->syncModelPassword($model, $password);

        // Synchronize other active directory
        // attributes on the model.
        $model = $this->syncModelFromLdap($user, $model);

        if ($this->getBindUserToModel()) {
            $model = $this->bindAdldapToModel($user, $model);
        }

        return $model;
    }

    /**
     * Fills a models attributes by the specified Users attributes.
     *
     * @param array            $user
     * @param Authenticatable $model
     *
     * @return Authenticatable
     */
    protected function syncModelFromLdap(array $user, Authenticatable $model)
    {
        $attributes = $this->getSyncAttributes();

        foreach ($attributes as $modelField => $adField) {
            if ($this->isAttributeCallback($adField)) {
                $value = $this->handleAttributeCallback($user, $adField);
            } else {
                $value = $this->handleAttributeRetrieval($user, $adField);
            }

            $model->{$modelField} = $value;
        }

        if ($model instanceof Model) {
            $model->save();
        }

        return $model;
    }

    /**
     * Syncs the models password with the specified password.
     *
     * @param Authenticatable $model
     * @param string          $password
     *
     * @return Authenticatable
     */
    protected function syncModelPassword(Authenticatable $model, $password)
    {
        if ($model instanceof Model && $model->hasSetMutator('password')) {
            // If the model has a set mutator for the password then
            // we'll assume that the dev is using their
            // own encryption method for passwords.
            $model->password = $password;

            return $model;
        }

        // Always encrypt the model password by default.
        $model->password = bcrypt($password);

        return $model;
    }

    /**
     * Retrieves the Adldap User model from the
     * specified Laravel model.
     *
     * @param mixed $model
     *
     * @return null|Authenticatable
     */
    protected function discoverAdldapFromModel($model)
    {
        if ($model instanceof Authenticatable && $this->getBindUserToModel()) {
            $attributes = $this->getUsernameAttribute();

            $key = key($attributes);

            $query = $this->newLdapUserQuery();

            $query->whereEquals($attributes[$key], $model->{$key});

            $user = $query->first();

            if ($user instanceof User) {
                $model = $this->bindAdldapToModel($user, $model);
            }
        }

        return $model;
    }

    /**
     * Binds the Adldap User instance to the Eloquent model instance
     * by setting its `adldapUser` public property.
     *
     * @param User            $user
     * @param Authenticatable $model
     *
     * @return Authenticatable
     */
    protected function bindAdldapToModel(User $user, Authenticatable $model)
    {
        $model->adldapUser = $user;

        return $model;
    }

    /**
     * Returns a new Adldap user query.
     *
     * @return \Adldap\Query\Builder
     */
    protected function newLdapUserQuery()
    {
        return Adldap::search()->select($this->getSelectAttributes());
    }

    /**
     * Authenticates a user against Active Directory.
     *
     * @param string $username
     * @param string $password
     *
     * @return bool
     */
    protected function authenticate($username, $password)
    {
        return Ldap::auth($username, $password);
    }

    /**
     * Returns true / false if the specified string
     * is a callback for an attribute handler.
     *
     * @param string $string
     *
     * @return bool
     */
    protected function isAttributeCallback($string)
    {
        $matches = preg_grep("/(\w)@(\w)/", explode("\n", $string));

        return count($matches) > 0;
    }

    /**
     * Handles retrieving the value from an attribute callback.
     *
     * @param array   $user
     * @param string $callback
     *
     * @return mixed
     */
    protected function handleAttributeCallback(array $user, $callback)
    {
        // Explode the callback into its class and method.
        list($class, $method) = explode('@', $callback);

        // Create the handler.
        $handler = app($class);

        // Call the attribute handler method and return the result.
        return call_user_func_array([$handler, $method], $user);
    }

    /**
     * Handles retrieving the specified field from the User model.
     *
     * @param array $user
     * @param string $field
     * @return null|string
     */
    protected function handleAttributeRetrieval(array $user, $field)
    {
//        if ($field === ActiveDirectory::THUMBNAIL) {
//            // If the field we're retrieving is the users thumbnail photo, we need
//            // to retrieve it encoded so we're able to save it to the database.
//            $value = $user->getThumbnailEncoded();
//        } else {
            $value = $user[$field];

            if (is_array($value)) {
                // If the AD Value is an array, we'll
                // retrieve the first value.
                $value = Arr::get($value, 0);
            }
//        }

        return $value;
    }

    /**
     * Returns the username attribute for discovering LDAP users.
     *
     * @return array
     */
    protected function getUsernameAttribute()
    {
        return Config::get('ldap.username_attribute', ['username' => 'login']);
    }

    /**
     * Returns the password key to retrieve the
     * password from the user input array.
     *
     * @return mixed
     */
    protected function getPasswordKey()
    {
        return Config::get('ldap.password_key', 'password');
    }

    /**
     * Retrieves the Adldap login attribute for authenticating users.
     *
     * @return string
     */
    protected function getLoginAttribute()
    {
        return Config::get('ldap.userdn', 'login');
    }

    /**
     * Retrieves the Adldap bind user to model config option for binding
     * the Adldap user model instance to the laravel model.
     *
     * @return bool
     */
    protected function getBindUserToModel()
    {
        return Config::get('ldap.bind_user_to_model', false);
    }

    /**
     * Retrieves the Adldap sync attributes for filling the
     * Laravel user model with active directory fields.
     *
     * @return array
     */
    protected function getSyncAttributes()
    {
        return Config::get('ldap.sync_attributes', ['name' => 'displayname']);
    }

    /**
     * Retrieves the Aldldap select attributes when performing
     * queries for authentication and binding for users.
     *
     * @return array
     */
    protected function getSelectAttributes()
    {
        return Config::get('ldap.attributes', []);
    }

    /**
     * Retrieves the Adldap login fallback option for falling back
     * to the local database if AD authentication fails.
     *
     * @return bool
     */
    protected function getLoginFallback()
    {
        return Config::get('ldap.login_fallback', false);
    }
}
<?php

namespace AnthonyEdmonds\LaravelLdapEmulator\Providers;

use Illuminate\Auth\Events\Attempting;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use LdapRecord\Container;
use LdapRecord\Laravel\Testing\DirectoryEmulator;
use LdapRecord\Models\Model as LdapModel;
use Throwable;

class LdapEmulatorServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../../config/ldap-emulator.php' => config_path('ldap-emulator.php'),
        ], 'ldap-emulator');

        if (config('ldap-emulator.enabled') === true) {
            self::start();
        }
    }

    public static function setActingUser(string $username)
    {
        $ldapUsernameKey = config('ldap-emulator.ldap-username-key');
        $model = config('ldap-emulator.ldap-user-model');

        Container::getDefaultConnection()->actingAs(
            $model::findBy($ldapUsernameKey, strtolower($username)),
        );
    }

    public static function start(): void
    {
        DirectoryEmulator::setup(
            config('ldap.default')
        );

        if (file_exists(config_path('ldap-emulator.php')) === false) {
            return;
        }

        $laravelModel = config('ldap-emulator.laravel-user-model');
        $ldapModel = config('ldap-emulator.ldap-user-model');
        $password = config('ldap-emulator.password');
        $laravelUsernameKey = config('ldap-emulator.laravel-username-key');
        $ldapUsernameKey = config('ldap-emulator.ldap-username-key');
        $users = config('ldap-emulator.users');

        $ldapUsers = self::buildDirectory($users, $ldapModel, $ldapUsernameKey);
        self::setupLocalUsers($users, $laravelModel, $laravelUsernameKey, $password, $ldapUsers);
        self::setupEvents();
    }
    
    protected static function setupEvents(): void
    {
        Event::listen(
            function (Attempting $event) {
                $ldapUsernameKey = config('ldap-emulator.ldap-username-key');
                static::setActingUser($event->credentials[$ldapUsernameKey]);
            }
        );
    }

    protected static function setupLocalUsers(
        array $users,
        string $model,
        string $usernameKey,
        string $password,
        Collection $ldapUsers
    ): void {
        try {
            DB::connection()->getPdo();
        } catch (Throwable $exception) {
            return;
        }

        if (Schema::hasTable('users') !== true) {
            return;
        }

        if (Schema::hasTable('roles') !== true) {
            return;
        }

        $importable = array_filter($users, function ($user) {
            return $user['import'] === true;
        });

        $existing = $model::withTrashed()
            ->whereIn($usernameKey, array_column($importable, $usernameKey))
            ->pluck($usernameKey);

        $toImport = array_filter($importable, function ($user) use ($existing) {
            return $existing->contains($user['username']) === false;
        });

        foreach ($toImport as $user) {
            self::assignRoles(
                self::makeLocalUser($user, $model, $usernameKey, $password, $ldapUsers),
                $user['roles'] ?? []
            );
        }
    }

    protected static function makeLocalUser(
        array $details,
        string $model,
        string $usernameKey,
        string $password,
        Collection $ldapUsers
    ): Model {
        $user = new $model();

        foreach ($details['laravel-attributes'] as $key => $value) {
            $user->$key = $value;
        }

        $user->$usernameKey = $details['username'];
        $user->password = $password;
        $user->guid = $ldapUsers->get($details['username'])->fresh()->getObjectGuid();
        $user->save();

        return $user;
    }

    protected static function assignRoles(Model $user, array $roles = []): void
    {
        foreach ($roles as $role) {
            try {
                $user->assignRole($role);
            } catch (Throwable $exception) {
                continue;
            }
        }
    }

    protected static function buildDirectory(array $users, string $model, string $usernameKey): Collection
    {
        $ldapUsers = new Collection();

        foreach ($users as $user) {
            $ldapUsers->put($user['username'], self::makeLdapUser($user, $model, $usernameKey));
        }

        return $ldapUsers;
    }

    protected static function makeLdapUser(array $details, string $model, string $usernameKey): LdapModel
    {
        return $model::create(
            array_merge(
                [
                    $usernameKey => $details['username'],
                ],
                $details['ldap-attributes']
            )
        );
    }
}

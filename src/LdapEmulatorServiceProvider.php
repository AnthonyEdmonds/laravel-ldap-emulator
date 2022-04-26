<?php

namespace AnthonyEdmonds\LdapEmulator\Providers;

use Illuminate\Auth\Events\Attempting;
use Illuminate\Database\Eloquent\Model;
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
        $this->mergeConfigFrom(
            __DIR__.'/../../config/ldap-emulator.php',
            'ldap-emulator'
        );
    }

    public function boot(): void
    {
        $this->publishes([
            'ldap-emulator.php' => config_path('ldap-emulator.php'),
        ], 'ldap-emulator');

        if ($this->app->isLocal() === true) {
            self::start();
        }

        if ($this->app->isLocal() === true || $this->app->runningUnitTests() === true) {
            Event::listen(
                function (Attempting $event) {
                    $ldapUsernameKey = config('ldap-emulator.ldap-username-key');
                    $model = config('ldap-emulator.ldap-user-model');

                    Container::getDefaultConnection()->actingAs(
                        $model::findBy($ldapUsernameKey, strtolower($event->credentials[$ldapUsernameKey])),
                    );
                }
            );
        }
    }

    public static function start(): void
    {
        DirectoryEmulator::setup('default', [
            'database' => database_path('ldap.sqlite')
        ]);

        $laravelModel = config('ldap-emulator.laravel-user-model');
        $ldapModel = config('ldap-emulator.ldap-user-model');
        $password = config('ldap-emulator.password');
        $laravelUsernameKey = config('ldap-emulator.laravel-username-key');
        $ldapUsernameKey = config('ldap-emulator.ldap-username-key');
        $users = config('ldap-emulator.users');

        self::setupLocalUsers($users, $laravelModel, $laravelUsernameKey, $password);
        self::buildDirectory($users, $ldapModel, $ldapUsernameKey);
    }

    protected static function setupLocalUsers(array $users, string $model, string $usernameKey, string $password): void
    {
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

        foreach ($users as $user) {
            self::setupLocalUser($user, $model, $usernameKey, $password);
        }
    }

    protected static function setupLocalUser(array $details, string $model, string $usernameKey, string $password): void
    {
        if ($details['import'] ?? false !== true) {
            return;
        }

        self::assignRoles(
            self::makeLocalUser($details, $model, $usernameKey, $password),
            $details['roles'] ?? []
        );
    }

    protected static function makeLocalUser(array $details, string $model, string $usernameKey, string $password): Model
    {
        return $model::withTrashed()
            ->updateOrCreate(
                [
                    $usernameKey => $details['username'],
                ],
                array_merge(
                    $details['laravel-attributes'],
                    [
                        'password' => $password,
                    ]
                )
            );
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

    protected static function buildDirectory(array $users, string $model, string $usernameKey): void
    {
        foreach ($users as $user) {
            self::makeLdapUser($user, $model, $usernameKey);
        }
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

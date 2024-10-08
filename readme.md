# Laravel LDAP Emulator

Automatically boot an LDAP emulator while working in a local environment.

Built for [Laravel](https://laravel.com/).

Based on the LDAP emulation provided by [LDAP Record](https://ldaprecord.com/).

Supports roles provided by [Spatie's Laravel Permission](https://spatie.be/docs/laravel-permission).

## Installation

Add the library via Composer: `composer require anthonyedmonds/laravel-ldap-emulator --dev`

Add the `LdapEmulatorServiceProvider` to `bootstrap/providers.php`:

```php
use AnthonyEdmonds\LaravelLdapEmulator\Providers\LdapEmulatorServiceProvider;

return [
    ...
    LdapEmulatorServiceProvider::class,
    ...
];
```

If you are manually loading service providers, `LdapEmulatorServiceProvider` must be loaded after `LdapRecord\Laravel\LdapServiceProvider`.

Once installed, export the config: `php artisan vendor:publish --provider="AnthonyEdmonds\LaravelLdapEmulator\Providers\LdapEmulatorServiceProvider"`

## Configuration

All configuration can be performed in the published `config/ldap-emulator.php` file.

| Config Key           | .env key              | Expected | Description |
| -------------------- | --------------------- | -------- | ----------- |
| enabled              | LDAP_EMULATOR_ENABLED | bool     | Whether the emulator is enabled |
| laravel-user-model   |                       | string   | The fully qualified name of the Laravel User model |
| laravel-username-key |                       | string   | Which attribute is used to identify the local User |
| password             |                       | string   | What to set the default local password to |
| ldap-user-model      |                       | string   | The fully qualified name of the LdapRecord User model |
| ldap-username-key    |                       | string   | Which attribute is used to identify the LDAP User |
| users                |                       | array    | The users to add to LDAP and the local system |

Further instructions on setting up users are provided in the comments.

* Keep the total number of users to a minimum for efficiency
* You may create a mixture of users which are automatically imported or left in active directory
* You may only assign roles to automatically imported users

## Usage

When the `APP_ENV` key of your system is set to `local` an LDAP emulator instance is started.

Beyond configuring the pool of users to add, the system will operate as if it had an LDAP server connected.

Imported users will not be updated once they have been created, however they can be synced when they sign in if LdapRecord is set up to do so.

Note that there some limitations to the functionality of the emulator, which are [described here](https://ldaprecord.com/docs/laravel/v2/testing/#directory-emulator).

## Authentication

When a call to `Auth::attempt()` is made, the `Attempting` event is fired and the LdapUser attempting to log in will be allowed to sign-in.

If you use a library that first calls `Auth::validate()`, such as Laravel Fortify, you will need to call the `setActingUser()` method first:

```php
Fortify::authenticateUsing(function ($request) {
    if (config('ldap-emulator.enabled') === true) {
        LdapEmulatorServiceProvider::setActingUser($request->username);
    }

    $validated = Auth::validate([
        'samaccountname' => $request->username,
        'password' => $request->password,
    ]);

    return $validated ? Auth::getLastAttempted() : null;
});
```

## Roadmap

Raise a ticket with your ideas and suggestions, or raise a pull request with your contributions.

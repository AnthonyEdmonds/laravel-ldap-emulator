<?php

return [
    /*
     * Which User model to use locally
     * Which attribute identifies the user in Laravel
     * What the default local password is
     */
    'laravel-user-model' => \App\Models\User::class,
    'laravel-username-key' => 'username',
    'password' => 'secret',

    /*
     * Which LdapRecord User model to use
     * Which attribute identifies the user in LDAP
     */
    'ldap-user-model' => \LdapRecord\Models\ActiveDirectory\User::class,
    'ldap-username-key' => 'samaccountname',

    /*
     * Define your users as follows:
     * username => The username to set in Laravel and LDAP
     * laravel-attributes => The attributes for the User model
     * ldap-attributes => The attributes for the LDAP User model
     * roles => Roles to be added via ->assignRole()
     * import => Whether the user should be imported automatically
     */
    'users' => [
        [
            'username' => 'gandalf',
            'laravel-attributes' => [
                'first_name' => 'Gandalf',
                'last_name' => 'Stormcrow',
            ],
            'ldap-attributes' => [
                'cn' => 'Gandalf Stormcrow',
                'givenname' => 'Gandalf',
                'sn' => 'Stormcrow',
                'mail' => 'gandalf.stormcrow@example.com',
            ],
            'roles' => [
                'Admin',
            ],
            'import' => true,
        ],
    ],
];

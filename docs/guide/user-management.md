User Management
===============

For `basic application template` that want to have user stored in database.
To use this feature, create required table by execute migration.
```
./yii migrate --migrationPath=@mdm/admin/migrations
```
Then, change config of user component
```php
    'components' => [
        ...
        'user' => [
            'identityClass' => 'mdm\admin\models\User',
            'loginUrl' => ['admin/user/login'],
        ]
    ]
```
Then you can access this menu at `index.php?r=admin/user`.

Signup User
-----------
```
http://localhost/myapp/index.php?r=admin/user/signup
```
Default registered user has status `ACTIVE`, mean user can login without activation needed.
To change that, you can change at config/params.php
```php
// config/params.php

return [
    ...
    'mdm.admin.configs' => [
        'defaultUserStatus' => 0, // 0 = inactive, 10 = active
    ]
];
```

Login Page
----------
Login page can access at `index.php?r=admin/user/login`

Security notes (password login & reset)
---------------------------------------

- **Uniform login timing.** When the submitted username does not match any
  account, the login still runs one full bcrypt verification against a fixed
  dummy hash (`Login::DUMMY_PASSWORD_HASH`, cost 13) so a failed login for an
  unknown user takes about as long as a wrong password for an existing one —
  the response time does not reveal whether an account exists.

- **Password-reset request throttling.** `admin/user/request-password-reset`
  never tells the caller whether an address is registered, active or unknown
  (same generic success message, no `exist` validation rule). Because sending
  a reset email is inherently slower than the not-found path, that residual
  SMTP timing channel could still be used to enumerate active accounts; it is
  mitigated with a per-IP **and** per-email rate limit backed by the cache
  component: every request consumes one slot on both counters, and once the
  limit is exceeded no further email is sent (the request is additionally
  delayed) while the uniform message is kept. Emails are never sent to
  inactive accounts (`status != ACTIVE`).

  The limits are configurable in `config/params.php`:

  ```php
  'user.passwordResetRequest.maxAttempts' => 5,    // attempts per window
  'user.passwordResetRequest.windowSeconds' => 3600, // window length
  'user.passwordResetRequest.throttleDelay' => 2,   // extra sleep in seconds when throttled
  ```

  The counters live in the application `cache` component; without a configured
  cache the throttle degrades to no-op (single requests are still answered
  with the uniform message).

More...
---------------

- [**Basic Usage**](basic-usage.md)
- [**Using Menu**](using-menu.md)
- [**Basic Configuration**](configuration.md)

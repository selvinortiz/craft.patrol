![Patrol](resources/img/patrol.png)

## Patrol
> Patrol simplifies **SSL** and **maintenance** routing for sites built with [Craft CMS](http://craftcms.com)

### Features
- Allows you to _force SSL_ on specific areas of your site or globally
- Allows you to put your site on _maintenance mode_ and route traffic to your _offline page_
- Allows you to define who can access your website during maintenance
- Allows you to enforce a primary domain (`primaryDomain environment config`)
- Allows you to limit control panel access (`limitCpAccessTo environment config`)

> You can let users access your website during maintenance by:
- Making them **admins**
- Authorizing their **IP address**
- Giving them this permission: `Patrol > Access the site when maintenance is on`

> If you want to block all users, (including admins) during maintenance:
- Add your email or username to `limitCpAccessTo` in your _config file_ and login with that account

### Installation
1. Download the [latest release](https://github.com/selvinortiz/craft.releases)
2. Extract the archive and place `patrol` inside your `craft/plugins` directory
3. Adjust file permissions as necessary

### Environment Configs
> You can configure Patrol from any environment definition. Here is an example of how you could go about setting that up...

```php
// config/general.php
return [
    '*'    => [
        'environmentVariables' => [],
    ],
    '.dev' => [
        'patrol' => [
            'forceSsl' => false,
        ],
    ],
    '.com' => [
        'patrol' => [
            'primaryDomain'   => '*',
            'forceSsl'        => true,
            'restrictedAreas' => [
                '/{cpTrigger}',
                '/members',
            ],
            'maintenanceMode' => false,
            'maintenanceUrl'  => '/offline.html',
            'authorizedIps'   => [
                '127.0.0.1',
            ],
            'limitCpAccessTo' => ['you@domain.com'],
            'enableCpTab'     => true,
            'pluginAlias'     => 'Patrol',
        ],
    ],
];
```

### Notes
> Patrol will throw an `HttpException(403)` for unauthorized users during maintenance if you do not have an _offline page_ set up.

> To force SSL everywhere (recommended practice), you can set `/` as the restricted area. If you only want to force SSL on the control panel, you could use `/admin` or `/{cpTrigger}`, the latter is recommended.

### Help & Feedback
If you have questions, comments, or suggestions, feel free to reach out to me on twitter [@selvinortiz](https://twitter.com/selvinortiz)

### License
**Patrol** for [Craft CMS](http://craftcms.com) is open source software, licensed under the [MIT License](http://opensource.org/licenses/MIT)

![Open Source Initiative](resources/img/osilogo.png)

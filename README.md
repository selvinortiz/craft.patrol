![Patrol](resources/img/patrol.png)

## Patrol 1.0.1
Lovingly crafted *by* [Selvin Ortiz](http://twitter.com/selvinortiz) for [Craft CMS](http://buildwithcraft.com)

Patrol simplifies **maintenance** and **SSL** for sites built with [Craft](http://buildwithcraft.com)

<a href='https://pledgie.com/campaigns/27296'>
<img alt='Support Craft Plugin Development By Selvin Ortiz' src='https://pledgie.com/campaigns/27296.png?skin_name=chrome' border='0'></a>

----
### Features
- Put your site on maintenance mode quickly and easily
- Force SSL globally or on specific pages with fine grain control
- Uses IP based authentication to bypass maintenance mode
- Allows IP based authentication even if behind *CloudFlare*
- Allows logged in admins to bypass maintenance mode
- Fully configurable via _environment configs_

### Installation
1. Download the [latest release](https://github.com/selvinortiz/craft.patrol/releases)
2. Extract the archive and place `patrol` inside your `craft/plugins` directory
3. Adjust file permissions as necessary

### Setup
Patrol offers a pretty straight forward but fully featured UI to adjust settings as needed.  
Additionally, you can fully configure Patrol via environment configs which will take priority.

### Environment Driven Configuration
You can configure Patrol and all of its settings from any environment definition. Here is an example of how you could go about setting that up...


```php
// config/general.php
return array(
	'*'	=> array(
		'environmentVariables' => array()
	),
	'.dev'	=> array(
		'patrol'				=> array(
			'forceSsl'			=> false,
		)
	),
	'.com' => array(
		'patrol'				=> array(
			'forceSsl'			=> true,
			'restrictedAreas'	=> array(
				'/{cpTrigger}',
				'/members'
			),
			'maintenanceMode'	=> false,
			'maintenanceUrl'	=> '/offline.html',
			'authorizedIps'		=> array(
				'127.0.0.1',
			),
			'enableCpTab'		=> false
			'pluginAlias'		=> '',
		)
    )
);
```

### Notes
- If no **maintenance URL** is set, Patrol will default to throwing a **403** server error
- The **Control Panel** is accessible to logged in admins even if **maintenance mode** is **ON**
- The yellow border used as a visual cue when maintenance mode was on has been removed

### FAQ

##### 1. How do I give users access when maintenance mode is ON?
- You can add their **IP** to the list of _Authorized IPs_
- Logged in users with **admin** privileges have full access by default

##### 2. Will IP authentication work if my site is behind [CloudFlare](http://cloudflare.com)?
- **Yes**, CloudFlare provides the **requesting IP** via a header that Patrol understands

##### 3. Doesn't Craft have a maintenance mode setting?
- **Yes**, but it's meant for internal use only to handle updates

### SSL Rules FAQ
Please note that these questions/answers only apply if you don't want to enable **SSL** everywhere for whatever reason.
However, forcing SSL everywhere is now standard practice and it is what I would recommended you do.

##### 1. How do I force secure connections on the Control Panel?
- You can simply add the URL for it (/admin) or use `/{cpTrigger}` so that it works even if you change that setting later

##### 2. How do I force secure connections on a specific URL, like my login page?
- You can add something like `/members/login` to the _Restricted Areas_

##### 3. How do I force secure connections on a specific section, like the members area?
- You can add the section URL `/members` and URLs on that scope will be protected as well

### Help & Feedback
If you have questions, comments, or concerns feel free to reach out to me on twitter [@selvinortiz](http://twitter.com/selvinortiz)

### License
**Patrol** for _craft_ is open source software licensed under the [MIT License](http://opensource.org/licenses/MIT)

![Open Source Initiative](resources/img/osilogo.png)

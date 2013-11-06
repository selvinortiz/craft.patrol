![Patrol](etc/patrol.png)

## Patrol 0.9
Patrol aims to improve deployment workflow and security for sites built with [Craft](http://buildwithcraft.com)

## Features

### Maintenance
Allows you to display a splash page to let users know that your site is **down for maintenance** while giving authorized users full access to the front-end and CP.

![Maintenance Settings](etc/maintenance.png)

### Security
Allows you to force **SSL** on specific areas of your site, giving you fine grain control without messing with _htaccess_ rules.

![Security Settings](etc/security.png)

### Designer & OCD Friendly
Patrol solves two problems often faced by developers but it attempts to do so in a designer friendly way by simplifying the terminology used and providing helpful hits through out the interface.
Additionally, _admins_ are able to export and import settings to make it easy to set sensible defaults on multiple sites or to share with others.
Lastly, Patrol allows you to enable/disable its **CP tab** and give it an **alias** which will change the name displayed on the CP tab if enabled and on the `settings/plugins` page.

![Plugin Settings](etc/settings.png)

## Tips
1. To authorize services such as beanstalk you can do the following...
	- Find beanstalk/service IP range: `50.31.189.108 – 50.31.189.122`
	- Add a partial IP to the authorized list: `50.31.189.1 or 50.31.189.1**`
2. To force SSL on the CP, just click on the button provided or at `/{cpTrigger}` to the secure section list.

## Notes
1. The CP is accessible even if **maintenance mode** is turned on to avoid admin lockouts.
2. Users are authorized via their **IP** address which can be added to the authorized list from the CP.
3. Patrol will go **off duty** if **devMode** is turned on regardless of its settings.
4. Settings are exported in `JSON` format.

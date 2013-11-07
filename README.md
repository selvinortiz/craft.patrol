![Patrol](resources/etc/patrol.png)

## Patrol 0.9.1
Patrol aims to improve deployment workflow and security for sites built with [Craft](http://buildwithcraft.com)

### Maintenance Mode

---
Maintenance mode allows you to redirect traffic to a specific **maintenance URL** while giving full access to all authorized users.

![Patrol](resources/etc/maintenance.png)

#### Enable Maintenance Mode `[+]`
Redirects all traffic to the _maintenance URL_ except logged in users with **admin** permissions and **IPs** in the _authorized_ list.

#### Maintenance URL
The URL to redirect traffic to if _maintenance mode_ is enabled.

#### Authorized IPs
The list of IPs that should be granted access when _maintenance mode_ is enabled.

### Paranoid Mode

---
Paranoid mode allows you to **force SSL** sitewide or on specific sections, giving you fine grain control without messing with _htaccess_ rules.

![Patrol](resources/etc/security.png)

#### Enable Paranoid Mode `[+]`
Forces connections via **SSL/HTTPS** on _restricted sections_ or the whole site if no sections are defined.

#### Restricted Sections
The sections where _paranoid mode_ should be enabled.

### Plugin Settings

---
These features/bugs are dedicated to [Lindsey D.](http://twitter.com/LindseyDiLoreto), the talented _control freaks_ at [CTRL+CLICK CAST](http://ctrlclickcast.com/) and everyone out there with **OCD**.

![Patrol](resources/etc/settings.png)

#### Enable CP Tab `[+]`
Displays a menu item atop with the label of **Patrol** or the _alias_ if one has been set.

#### Plugin Alias
Allows you to rename (give an _alias_ to) **Patrol** which will change the label displayed on the CP tab if enabled and on the `settings/plugins` page.

#### Export/Import
Patrol let's you export and import settings to make it easy to set sensible defaults on multiple sites or to share with other team members.

### Tips
1. To authorize services such as beanstalk you can do the following...
	- Find the IP range for beanstalk or any other service: `50.31.189.108 – 50.31.189.122`
	- Add a partial IP to the authorized list: `50.31.189.1 or 50.31.189.1**`
2. To force SSL on the CP, just click on the **Secure The CP** button and save your settings.

### Notes
1. The CP is accessible even if **maintenance mode** is turned on to avoid admin lockouts.
2. Users are authorized via their **IP** address which can be added to the authorized list from the CP.
3. Patrol will go **off duty** if **devMode** is turned on regardless of its settings.
4. Settings are exported in `JSON` format.
5. Craft has a _maintenanceMode_ but it is used internally for handling updates.

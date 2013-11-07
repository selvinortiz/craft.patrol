![Patrol](resources/etc/patrol.png)

## Patrol 0.9.2
Patrol simplifies **maintenance mode** and **SSL** management on sites built with [Craft](http://buildwithcraft.com)

### TL;DR
![Patrol](resources/etc/features.png)

### FAQ

---
#### Maintenance Mode
1. How do I give users access when maintenance mode is enabled?
	- You can add their **IP** to the _Authorized IPs_ list
	- Logged in users with **admin** permissions have full access by default
2. How do I authorize [BeanStalk](http://beanstalk.com) so I can deploy if maintenance mode is enabled?
	- Find the IP range for **BeanStalk** or any other service: `50.31.189.108 – 50.31.189.122`
	- Add a partial IP to the _Authorized List`: `50.31.189.1 or 50.31.189.1**`
3. Will IP authentication work if my site is behind [CloudFlare](http://cloudflare.com)?
	- Yes, **CloudFlare** provides the user's IP via a header that Patrol understands
4. Doesn't Craft have a maintenance mode setting?
	- Yes, but it's meant for internal use only to handle updates
#### Security
1. How do I force SSL on the CP?
	- Click on the **Secure The CP** button and save your settings
	- Add `/{cpTrigger}` to the _Restricted Areas_
2. How do I for SSL on my whole site?
	- Leave the _restricted areas_ blank
3. How do I force SSL on a specific URL, like my login page?
	- You can add something like `/members/login` to the _restricted areas_
4. How do I force SSL on a specific section of the my site, like my members section?
	- You can add the section URL `/members` and URLs on that scope will be restricted

### Notes
1. The CP is accessible even if **maintenance mode** is turned on to avoid lockouts
3. Patrol will go **off duty** if **devMode** is turned on regardless of its settings
4. Settings are exported in `JSON` format

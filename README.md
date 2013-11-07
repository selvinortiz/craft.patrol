![Patrol](resources/etc/patrol.png)

## Patrol 0.9.2
Patrol aims to improve deployment workflow and security for sites built with [Craft](http://buildwithcraft.com)

### TL;DR
![Patrol](resources/etc/features.png)

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

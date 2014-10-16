![Patrol](resources/img/patrol.png)

## Patrol 0.9.8
Lovingly crafted *by* [Selvin Ortiz](http://twitter.com/selvinortiz) for [Craft CMS](http://buildwithcraft.com)

Patrol simplifies **maintenance mode** and **secure connections** for sites built with [Craft](http://buildwithcraft.com)

----
### Features
- Put your site on maintenance mode quickly and easily
- Force SSL on specific pages, sections, or globally with fine grain control
- Uses IP based authentication to bypass maintenance mode
- Allows IP based authentication even if behind *CloudFlare*
- Allows logged in admins to bypass maintenance mode
- Fully configurable via _environment configs_

### Installation
1. Download the [latest release](https://github.com/selvinortiz/craft.patrol/releases)
2. Extract the archive and place `patrol` inside your `craft/plugins` directory
3. Adjust file permissions as necessary

### Setup
Patrol offers a pretty straight forward but fully featured UI to adjust settings as needed  
Additionally, you can fully configure Patrol via environment configs which will take priority.

### Notes
- If no **maintenance URL** is set, Patrol will default to throwing a **403** server error
- The **Control Panel** is accessible to logged in admins even if **maintenance mode** is **ON** to avoid **admin** lockouts
- When maintenance mode is on, you will see a yellow border at the top of the page so that you don't forget to turn it off after testing

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

<?php
namespace Craft;

class PatrolService extends BaseApplicationComponent
{
	protected $warnings			= null;
	protected $dynamicParams	= null;
	protected $exportFileName	= 'patrol.json';
	protected $importFieldName	= 'patrolFile';

	/**
	 * Runs Patrol if devMode is turned off
	 *
	 * @param Model $settings
	 */
	public function watch(Model $settings)
	{
		if (!$this->getDevMode())
		{
			$this->protect($settings);
			$this->restrict($settings);
		}
	}

	/**
	 * Forces SSL based on restrictedAreas
	 *
	 * @param	Model	$settings
	 * @return	bool
	 */
	public function protect(Model $settings)
	{
		if ($settings->getAttribute('forceSsl'))
		{
			$requestedUrl		= craft()->request->getUrl();
			$restrictedAreas	= $settings->getAttribute('restrictedAreas');
			$securedConnection	= craft()->request->isSecureConnection();

			if (empty($restrictedAreas))
			{
				if (!$securedConnection)
				{
					$this->forceSsl();
				}

				return true;
			}

			// Force SSL
			if (!$securedConnection)
			{
				foreach ($restrictedAreas as $restrictedArea)
				{
					// Parse dynamic variables like /{cpTrigger}
					if (stripos($restrictedArea, '{') !== false)
					{
						$restrictedArea = $this->parseTags($restrictedArea);
					}

					if (stripos($requestedUrl, $restrictedArea) === 0)
					{
						$this->forceSsl();
					}
				}

				return true;
			}

			// Revert SSL
			if ($securedConnection)
			{
				foreach ($restrictedAreas as $restrictedArea)
				{
					if (stripos($restrictedArea, '{') !== false)
					{
						$restrictedArea = $this->parseTags($restrictedArea);
					}

					if (stripos($requestedUrl, $restrictedArea) !== false)
					{
						return true;
					}
				}

				$this->revertSsl();
			}
		}
	}

	/**
	 * Restricts accessed based on authorizedIps
	 *
	 * @param	Model	$settings
	 * @return	bool
	 */
	public function restrict(Model $settings)
	{
		// Authorize logged in admins on the fly
		if (craft()->userSession->isAdmin()) { return true; }

		if (!craft()->request->isCpRequest() && $settings->getAttribute('maintenanceMode'))
		{
			$requestingIp	= $this->getRequestingIp();
			$authorizedIps	= $settings->getAttribute('authorizedIps');
			$maintenanceUrl	= $settings->getAttribute('maintenanceUrl');

			if ($maintenanceUrl == craft()->request->getUrl())
			{
				return true;
			}

			if (empty($authorizedIps))
			{
				$this->forceRedirect();
			}

			if (is_array($authorizedIps) && count($authorizedIps))
			{
				if (in_array($requestingIp, $authorizedIps))
				{
					return true;
				}

				foreach ($authorizedIps as $authorizedIp)
				{
					$authorizedIp = str_replace('*', '', $authorizedIp);

					if (stripos($requestingIp, $authorizedIp) === 0)
					{
						return true;
					}
				}

				$this->forceRedirect($maintenanceUrl);
			}
		}
	}

	/**
	 * Ensures that we get the right IP address even if behind CloudFlare
	 *
	 * @todo	Add support for IPV6 and Proxy servers (Overkill?)
	 * @return	string
	 */
	public function getRequestingIp()
	{
		return isset($_SERVER['HTTP_CF_CONNECTING_IP']) ? $_SERVER['HTTP_CF_CONNECTING_IP'] : $_SERVER['REMOTE_ADDR'];
	}

	public function hasCpRule(Model $settings)
	{
		$restrictedAreas = $settings->getAttribute('restrictedAreas');

		if (is_array($restrictedAreas) && count($restrictedAreas))
		{
			foreach ($restrictedAreas as $restrictedArea)
			{
				if (stripos($restrictedArea, '/{cpTrigger}') !== false)
				{
					return true;
				}
			}

			return false;
		}

		return (bool) (stripos((string) $restrictedAreas, '/{cpTrigger}') !== false);
	}

	public function hasIpRule(Model $settings)
	{
		$authorizedIps = $settings->getAttribute('authorizedIps');

		if (is_array($authorizedIps) && count($authorizedIps))
		{
			foreach ($authorizedIps as $authorizedIp)
			{
				if (stripos($authorizedIp, $this->getRequestingIp()) !== false)
				{
					return true;
				}
			}

			return false;
		}

		return (bool) (stripos((string) $authorizedIps, $this->getRequestingIp()) !== false);
	}

	public function getDevMode($default=false)
	{
		return craft()->config->get('devMode') ? true : $default;
	}

	protected function forceRedirect($redirectTo='')
	{
		if (empty($redirectTo))
		{
			$this->renderDefaultSplash();
		}

		craft()->request->redirect($redirectTo);
	}

	protected function renderDefaultSplash()
	{
		echo craft()->templates->renderString(IOHelper::getFileContents(craft()->path->getPluginsPath().'patrol/templates/_down.html'));
		craft()->end();
		// throw new HttpException(403);
	}

	protected function forceSsl()
	{
		$siteUrl	= UrlHelper::getSiteUrl();
		$requestUri	= craft()->request->getUrl();
		$redirectTo	= str_replace('http://', 'https://', rtrim($siteUrl, '/')).'/'.ltrim($requestUri, '/');

		craft()->request->redirect($redirectTo);
	}

	protected function revertSsl()
	{
		$siteUrl	= UrlHelper::getSiteUrl();
		$requestUri	= craft()->request->getUrl();
		$redirectTo	= str_replace('https://', 'http://', rtrim($siteUrl, '/')).'/'.ltrim($requestUri, '/');

		craft()->request->redirect($redirectTo);
	}

	protected function parseTags($str='')
	{
		$params = $this->getDynamicParams();

		foreach ($params as $key => $val)
		{
			$str = str_replace('{'.$key.'}', $val, $str);
		}

		return $str;
	}

	protected function getDynamicParams()
	{
		if (is_null($this->dynamicParams))
		{
			$env	= craft()->config->get('environmentVariables');
			$vars	= array(
				'cpTrigger'	=> craft()->config->get('cpTrigger')
			);

			if (count($env))
			{
				$vars = array_merge($vars, $env);
			}

			$this->dynamicParams = $vars;
		}

		return $this->dynamicParams;
	}
}

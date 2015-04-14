<?php
namespace Craft;

/**
 * The core service for security and maintenance
 *
 * Class PatrolService
 *
 * @author  Selvin Ortiz <selvin@selv.in>
 * @package Craft
 */

class PatrolService extends BaseApplicationComponent
{
	/**
	 * An array of key/value pairs used when parsing restricted areas like {cpTrigger}
	 *
	 * @var array
	 */
	protected $dynamicParams;

	/**
	 * Begin watching...
	 *
	 * @param array $settings
	 */
	public function watch(array $settings)
	{
		$this->protect($settings);
		$this->restrict($settings);
	}

	/**
	 * Forces SSL based on restrictedAreas
	 * The environment settings take priority over those defined in the control panel
	 *
	 * @param array $settings
	 *
	 * @return bool
	 */
	public function protect(array $settings)
	{
		if ($settings['forceSsl'])
		{
			$requestedUrl      = craft()->request->getUrl();
			$restrictedAreas   = $settings['restrictedAreas'];
			$securedConnection = craft()->request->isSecureConnection();

			// Forcing SSL if no restricted areas are defined, equivalent to strict mode.
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
						$restrictedArea = craft()->templates->renderObjectTemplate($restrictedArea, $this->getDynamicParams());
					}

					$restrictedArea = '/'.ltrim($restrictedArea, '/');

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
						$restrictedArea = craft()->templates->renderObjectTemplate($restrictedArea, $this->getDynamicParams());
					}

					if (stripos($requestedUrl, $restrictedArea) !== false)
					{
						return true;
					}
				}

				$this->revertSsl();
			}
		}

		return false;
	}

	/**
	 * Restricts accessed based on authorizedIps
	 *
	 * @param array $settings
	 *
	 * @return void
	 */
	public function restrict(array $settings)
	{
		// Authorize logged in admins on the fly
		if ($this->doesCurrentUserHaveAccess())
		{
			return;
		}

		if (craft()->request->isSiteRequest() && $settings['maintenanceMode'])
		{
			$requestingIp   = $this->getRequestingIp();
			$authorizedIps  = $settings['authorizedIps'];
			$maintenanceUrl = $settings['maintenanceUrl'];

			if ($maintenanceUrl == craft()->request->getUrl())
			{
				return;
			}

			if (empty($authorizedIps))
			{
				$this->forceRedirect($maintenanceUrl);
			}

			if (is_array($authorizedIps) && count($authorizedIps))
			{
				if (in_array($requestingIp, $authorizedIps))
				{
					return;
				}

				foreach ($authorizedIps as $authorizedIp)
				{
					$authorizedIp = str_replace('*', '', $authorizedIp);

					if (stripos($requestingIp, $authorizedIp) === 0)
					{
						return;
					}
				}

				$this->forceRedirect($maintenanceUrl);
			}
		}
	}

	/**
	 * Redirects to the HTTPS version of the requested URL
	 */
	protected function forceSsl()
	{
		craft()->request->redirect('https://'.craft()->request->getServerName().craft()->request->getUrl());
	}

	/**
	 * Redirects to the HTTP version of the requested URL
	 */
	protected function revertSsl()
	{
		craft()->request->redirect('http://'.craft()->request->getServerName().craft()->request->getUrl());
	}

	/**
	 * Returns a list of dynamic parameters and their values that can be used in restricted area settings
	 *
	 * @return array
	 */
	protected function getDynamicParams()
	{
		if (is_null($this->dynamicParams))
		{
			$variables           = craft()->config->get('environmentVariables');
			$this->dynamicParams = array(
				'cpTrigger'     => craft()->config->get('cpTrigger'),
				'actionTrigger' => craft()->config->get('actionTrigger')
			);

			if (is_array($variables) && count($variables))
			{
				$this->dynamicParams = array_merge($this->dynamicParams, $variables);
			}
		}

		return $this->dynamicParams;
	}

	/**
	 * Parses authorizedIps to ensure they are valid even when created from a string
	 *
	 * @param array|string $ips
	 *
	 * @return array
	 */
	public function parseAuthorizedIps($ips)
	{
		$ips = trim($ips);

		if (is_string($ips) && !empty($ips))
		{
			$ips = explode(PHP_EOL, $ips);
		}

		return $this->filterOutArrayValues(
			$ips, function ($val)
			{
				return preg_match('/^[0-9\.\*]{5,15}$/i', $val);
			}
		);
	}

	/**
	 * Parser restricted areas to ensure they are valid even when created from a string
	 *
	 * @param array|string $areas
	 *
	 * @return array
	 */
	public function parseRestrictedAreas($areas)
	{
		if (is_string($areas) && !empty($areas))
		{
			$areas = trim($areas);
			$areas = explode(PHP_EOL, $areas);
		}

		return $this->filterOutArrayValues(
			$areas, function ($val)
			{
				$valid = preg_match('/^[\/\{\}a-z\_\-\?\=]{1,255}$/i', $val);

				if (!$valid)
				{
					return false;
				}

				return true;
			}
		);
	}

	/**
	 * Filters out array values by using a custom filter
	 *
	 * @param array|string|null $values
	 * @param callable          $filter
	 * @param bool              $preserveKeys
	 *
	 * @return array
	 */
	protected function filterOutArrayValues($values = null, \Closure $filter = null, $preserveKeys = false)
	{
		$data = array();

		if (is_array($values) && count($values))
		{
			foreach ($values as $key => $value)
			{
				$value = trim($value);

				if (!empty($value))
				{
					if (is_callable($filter) && $filter($value))
					{
						$data[$key] = $value;
					}
				}
			}

			if (!$preserveKeys)
			{
				$data = array_values($data);
			}
		}

		return $data;
	}

	/**
	 * @param string $redirectTo
	 *
	 * @throws HttpException
	 */
	protected function forceRedirect($redirectTo = '')
	{
		if (empty($redirectTo))
		{
			$this->runDefaultBehavior();
		}

		craft()->request->redirect($redirectTo);
	}

	/**
	 * Ensures that we get the right IP address even if behind CloudFlare
	 *
	 * @return string
	 */
	public function getRequestingIp()
	{
		return isset($_SERVER['HTTP_CF_CONNECTING_IP']) ? $_SERVER['HTTP_CF_CONNECTING_IP'] : $_SERVER['REMOTE_ADDR'];
	}

	/**
	 * Returns whether or not the current user has access during maintenance mode
	 */
	protected function doesCurrentUserHaveAccess()
	{
		// Admins have access by default
		if (craft()->userSession->isAdmin())
		{
			return true;
		}

		// User has the right permission
		if (craft()->userSession->checkPermission('patrolMaintenanceModeBypass'))
		{
			return true;
		}

		return false;
	}

	/**
	 * @throws HttpException
	 */
	protected function runDefaultBehavior()
	{
		throw new HttpException(403);
	}
}

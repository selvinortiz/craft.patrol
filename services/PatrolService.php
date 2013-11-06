<?php
namespace Craft;

class PatrolService extends BaseApplicationComponent
{
	protected $helper			= null;
	protected $warnings			= array();
	protected $dynamicParams	= array();
	protected $exportFileName	= 'patrol.json';
	protected $importFieldName	= 'patrolFile';

	/**
	 * Kickstart Patrol if devMode is turned off
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
	 * Prepares plugin settings before saving to db
	 *
	 * @param array $settings
	 * @return array
	 */
	public function prepare(array $settings=array())
	{
		$authorizedIps		= $this->get('authorizedIps', $settings);
		$restrictedAreas	= $this->get('restrictedAreas', $settings);

		if ($authorizedIps)
		{
			$authorizedIps = $this->parseIps($authorizedIps);

			$settings['authorizedIps'] = empty($authorizedIps) ? '' : $authorizedIps;
		}

		if ($restrictedAreas)
		{
			$restrictedAreas = $this->parseAreas($restrictedAreas);

			$settings['restrictedAreas'] = empty($restrictedAreas) ? '' : $restrictedAreas;
		}

		if (empty($this->warnings))
		{
			return $settings;
		}
	}

	/**
	 * Handle SSL enforcement based on plugin settings
	 *
	 * @param	Model	$settings
	 * @return	bool
	 */
	protected function protect(Model $settings)
	{
		if ($settings->getAttribute('forceSsl'))
		{
			$requestedUrl		= craft()->request->getUrl();
			$restrictedAreas	= $settings->getAttribute('restrictedAreas');
			$securedConnection	= craft()->request->isSecureConnection();

			// Protect everything
			if (empty($restrictedAreas))
			{
				// Only if connection is not secure to avoid redirect loop
				if (!$securedConnection)
				{
					$this->forceSsl();
				}

				return true;
			}

			// Run checks if connection is not secure
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

			// Revert SSL: Inspect the request if the connection is being made over HTTPS
			if ($securedConnection)
			{
				foreach ($restrictedAreas as $restrictedArea)
				{
					// Parse dynamic variables: /{cpTrigger} > /admin
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

	protected function restrict(Model $settings)
	{
		// Ignore CP requests even on maintenance mode
		if ($settings->getAttribute('maintenanceMode') && !craft()->request->isCpRequest())
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

	public function importSettings(\CUploadedFile $file)
	{
		$fileContent = IOHelper::getFileContents($file->getTempName());
		$fileContent = json_decode($fileContent, true);

		if (json_last_error() == JSON_ERROR_NONE && isset($fileContent['settings']))
		{
			return craft()->plugins->savePluginSettings(craft()->plugins->getPlugin('patrol'), $fileContent['settings']);
		}

		return false;
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

	public function getImportFieldName()
	{
		return $this->importFieldName;
	}

	public function getExportFileName()
	{
		return $this->exportFileName;
	}

	public function getHelper()
	{
		if (is_null($this->helper))
		{
			$this->helper = new PatrolHelper();
		}

		return $this->helper;
	}

	protected function forceRedirect($redirectTo='')
	{
		if (empty($redirectTo))
		{
			echo craft()->templates->renderString(IOHelper::getFileContents(craft()->path->getPluginsPath().'patrol/templates/_down.html'));

			craft()->end(); // throw new HttpException(403);
		}

		craft()->request->redirect($redirectTo);
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
		$this->loadDynamicParams();

		foreach ($this->dynamicParams as $key => $val)
		{
			$str = str_replace('{'.$key.'}', $val, $str);
		}

		return $str;
	}

	protected function parseIps($ips)
	{
		if (is_string($ips))
		{
			$ips = explode(PHP_EOL, $ips);
		}

		return $this->ignoreEmptyValues($ips, function($val)
		{
			return preg_match('/^[0-9\.\*]{5,15}$/i', $val);
		});
	}

	protected function parseAreas($areas)
	{
		if (is_string($areas))
		{
			$areas	= explode(PHP_EOL, $areas);
		}

		$patrol	= $this;

		return $this->ignoreEmptyValues($areas, function($val) use($patrol)
		{
			$valid = preg_match('/^[\/\{\}a-z\_\-\?\=]{1,255}$/i', $val);

			if (!$valid)
			{
				$patrol->warnings['restrictedAreas'] = 'Please use valid URL with optional dynamic parameters like: /{cpTrigger}';

				return false;
			}

			return true;
		});
	}

	protected function ignoreEmptyValues(array $values=array(), \Closure $filter=null, $preserveKeys=false)
	{
		$data = array();

		if (count($values))
		{
			foreach ($values as $key => $value)
			{
				$value = trim($value);

				if (!empty($value) && $filter($value))
				{
					$data[$key] = $value;
				}
			}

			if (!$preserveKeys)
			{
				$data = array_values($data);
			}
		}

		return $data;
	}

	protected function loadDynamicParams()
	{
		if (is_null($this->dynamicParams))
		{
			$this->dynamicParams = array(
				'cpTrigger'	=> craft()->config->get('cpTrigger')
			);
		}
	}

	protected function get($key, array $data, $default=false)
	{
		return array_key_exists($key, $data) ? $data[$key] : $default;
	}
}

<?php
namespace Craft;

class PatrolService extends BaseApplicationComponent
{
	protected $helper;
	protected $warnings;
	protected $dynamicParams;
	protected $exportFileName	= 'patrol.json';
	protected $importFieldName	= 'patrolFile';

	public function watch($settings)
	{
		$this->protect($settings);
		$this->restrict($settings);
	}

	/**
	 * Handle SSL enforcement based on plugin settings
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

	public function restrict(Model $settings)
	{
		// Ignore CP requests even in maintenance mode
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

	public function getEnvSettings()
	{
		/**
		 * -------------------------------------------------------------------------------
		 * CONSTANT			ACCESS		LEGEND
		 * -------------------------------------------------------------------------------
		 * PHP_INI_USER		1			Entry can be set in user scripts like in ini_set()
		 * PHP_INI_PERDIR	2			Entry can be set in php.ini/.htaccess/httpd.conf
		 * PHP_INI_SYSTEM	4			Entry can be set in php.ini/httpd.conf
		 * PHP_INI_ALL		7			Entry can be set anywhere
		 */

		$options	= array();
		$settings	= @ini_get_all();

		if (is_array($settings) && count($settings))
		{
			foreach ($settings as $option => $properties)
			{
				$options[$option] = array(
					'defaultVal'	=> $properties['global_value'],
					'runtimeVal'	=> $properties['local_value'],
					'accessLevel'	=> $properties['access'],
					'canChangeOtf'	=> (bool) ($properties['access'] == 1 || $properties['access'] == 7)
				);
			}
		}

		// return craft()->templates->render('patrol/_env', compact('options'));
	}

	/**
	 * Format a flat JSON string to make it more human-readable
	 *
	 * @param string $json
	 *		The original JSON string to process
	 *		When the input is not a string it is assumed the input is RAW
	 *		and should be converted to JSON first of all.
	 *
	 * @return string Indented version of the original JSON string
	 */
	public function jsonPrettify($json)
	{
		if (!is_string($json))
		{
			if (phpversion() && phpversion() >= 5.4)
			{
				return json_encode($json, JSON_PRETTY_PRINT);
			}

			$json = json_encode($json);
		}

		$pos			= 0;
		$result			= '';
		$strLen			= strlen($json);
		$indentStr		= "\t";
		$newLine		= "\n";
		$prevChar		= '';
		$outOfQuotes	= true;

		for ($i=0; $i<$strLen; $i++)
		{
			// Grab the next character in the string
			$char = substr($json, $i, 1);

			// Are we inside a quoted string?
			if ($char == '"' && $prevChar != '\\') {
				$outOfQuotes = !$outOfQuotes;
			}
			// If this character is the end of an element,
			// output a new line and indent the next line
			else if (($char == '}' || $char == ']') && $outOfQuotes)
			{
				$result .= $newLine;
				$pos--;
				for ($j = 0; $j < $pos; $j++) {
					$result .= $indentStr;
				}
			}
			// eat all non-essential whitespace in the input as we do our own here and it would only mess up our process
			else if ($outOfQuotes && false !== strpos(" \t\r\n", $char))
			{
				continue;
			}

			// Add the character to the result string
			$result .= $char;
			// always add a space after a field colon:
			if ($char == ':' && $outOfQuotes)
			{
				$result .= ' ';
			}

			// If the last character was the beginning of an element,
			// output a new line and indent the next line
			if (($char == ',' || $char == '{' || $char == '[') && $outOfQuotes)
			{
				$result .= $newLine;
				if ($char == '{' || $char == '[') {
					$pos++;
				}
				for ($j = 0; $j < $pos; $j++) {
					$result .= $indentStr;
				}
			}

			$prevChar = $char;
		}

		return $result;
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
		if (!craft()->config->get('devMode'))
		{
			$siteUrl	= UrlHelper::getSiteUrl();
			$requestUri	= craft()->request->getUrl();
			$redirectTo	= str_replace('http://', 'https://', rtrim($siteUrl, '/')).'/'.ltrim($requestUri, '/');

			craft()->request->redirect($redirectTo);
		}
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

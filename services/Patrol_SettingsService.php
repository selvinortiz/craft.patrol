<?php
namespace Craft;

class Patrol_SettingsService extends BaseApplicationComponent
{
	protected $exportFileName	= 'Patrol.json';
	protected $importFieldName	= 'patrolFile';

	/**
	 * Prepares plugin settings prior to saving them to the db
	 *	- authorizedIps are converted from string to array
	 *	- restrictedAreas are converted from string to array
	 *
	 * @param	array	$settings	The settings array from $_POST provided by Craft
	 * @return	array
	 */
	public function prepare(array $settings=array())
	{
		$settings['maintenanceMode']	= (bool) $settings['maintenanceMode'];
		$settings['forceSsl']			= (bool) $settings['forceSsl'];
		$settings['enableCpTab']		= (bool) $settings['enableCpTab'];
		$settings['authorizedIps']		= $settings['authorizedIps'] ? $this->parseIps($settings['authorizedIps']) : array();
		$settings['restrictedAreas']	= $settings['restrictedAreas'] ? $this->parseAreas($settings['restrictedAreas']) : array();

		if (!craft()->patrol->hasWarnings())
		{
			return $settings;
		}
	}

	public function export()
	{
		$settings	= craft()->plugins->getPlugin('patrol')->getSettings();
		$settings	= $settings->getAttributes();
		$json		= json_encode($settings);

		if (json_last_error() == JSON_ERROR_NONE)
		{
			header('Content-disposition: attachment; filename='.$this->exportFileName);
			header('Content-type: application/json');
			header('Pragma: no-cache');

			craft()->config->set('devMode', false);
			echo $this->prettify($json);
			craft()->end();
		}

		return false;
	}

	public function import()
	{
		$file = $this->arrayFlatten(array_shift($_FILES), $this->importFieldName);

		if ($file && isset($file['name']) && $file['error'] == 0)
		{
			if (empty($file['tmp_name']))
			{
				return false;
			}

			return $this->save($file['tmp_name']);
		}

		return false;
	}

	public function save($path=null)
	{
		if (is_null($path)) { $path = $this->getSettingsFile(); }

		$jsonString	= $this->getFileContent($path, 'text/plain');
		$jsonObject	= json_decode($jsonString);

		// @todo	Beef up settings validation from file
		if (json_last_error() == JSON_ERROR_NONE && is_object($jsonObject))
		{
			return craft()->plugins->savePluginSettings(craft()->plugins->getPlugin('patrol'), get_object_vars($jsonObject));
		}

		craft()->patrol->addWarning($this->getJsonMessage(), 'jsonDecoding');

		return false;
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
	public function prettify($json)
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

	/**
	 * Gets the last JSON decoding message if any
	 *
	 * @param	null	$errorCode
	 * @return	bool|string
	 */
	public function getJsonMessage($errorCode=null)
	{
		if (is_null($errorCode)) { $errorCode = json_last_error(); }

		switch ($errorCode)
		{
			case JSON_ERROR_NONE:
				return false;
			break;
			case JSON_ERROR_DEPTH:
				return 'Maximum stack depth exceeded';
			break;
			case JSON_ERROR_STATE_MISMATCH:
				return 'Underflow or the modes mismatch';
			break;
			case JSON_ERROR_CTRL_CHAR:
				return 'Unexpected control character found';
			break;
			case JSON_ERROR_SYNTAX:
				return 'Syntax error, malformed JSON';
			break;
			case JSON_ERROR_UTF8:
				return 'Malformed UTF-8 characters, possibly incorrectly encoded';
			break;
			default:
				return 'Unknown error';
			break;
		}
	}

	public function parseIps($ips)
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

	public function parseAreas($areas)
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
				craft()->patrol->warnings['restrictedAreas'] = 'Please use valid URL with optional dynamic parameters like: /{cpTrigger}';

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

	protected function getSettingsFile()
	{
		return craft()->path->getConfigPath().'patrol/Settings.json';
	}

	protected function getFileContent($path='', $restrictTo='text/plain')
	{
		$file = IOHelper::getFile($path);

		if ($file)
		{
			if (!empty($restrictTo) && strtolower($file->getMimeType()) != strtolower($restrictTo))
			{
				return false;
			}

			return $file->getContents();
		}

		return false;
	}

	protected function get($key, array $data, $default=false)
	{
		return array_key_exists($key, $data) ? $data[$key] : $default;
	}

	/**
	 * Flattens an associative array with a known second level subKey
	 *
	 * @example
	 *	$file = array('name'=>array('patrolFile'=>'Patrol.json', 'type'=>array('patrolFile'=>'application/octet-stream'));
	 *	$this->arrayFlatten($file, 'patrolFile');
	 *	// array('name'=>'Patrol.json', 'type'=>'application/octet-stream');
	 *
	 * @param	array	$subject
	 * @param	string	$subKey
	 * @return	array
	 */
	protected function arrayFlatten(array $subject=array(), $subKey='')
	{
		$flat = array();

		if (count($subject))
		{
			foreach ($subject as $key => $val)
			{
				if (is_array($val))
				{
					$val = $this->get($subKey, $val);
				}

				$flat[$key] = $val;
			}
		}

		return $flat;
	}
}

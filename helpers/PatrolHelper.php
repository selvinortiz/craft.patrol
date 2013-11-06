<?php
namespace Craft;

class PatrolHelper
{
	public function __toString()
	{
		return get_class($this);
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

	public function getEnvSettings()
	{
		/**
		 * -------------------------------------------------------------------------------
		 * CONSTANT				ACCESS		LEGEND
		 * -------------------------------------------------------------------------------
		 * PHP_INI_USER			1			Entry can be set in user scripts like in ini_set()
		 * PHP_INI_PERDIR		2			Entry can be set in php.ini/.htaccess/httpd.conf
		 * PHP_INI_SYSTEM		4			Entry can be set in php.ini/httpd.conf
		 * PHP_INI_ALL			7			Entry can be set anywhere
		 */
		$options = array();
		$settings = @ini_get_all();

		if (is_array($settings) && count($settings)) {
			foreach ($settings as $option => $properties) {
				$options[$option]	= array(
					'defaultVal'	=> $properties['global_value'],
					'runtimeVal'	=> $properties['local_value'],
					'accessLevel'	=> $properties['access'],
					'canChangeOtf'	=> (bool)($properties['access'] == 1 || $properties['access'] == 7)
				);
			}
		}

		return $options;
	}
}

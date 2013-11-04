<?php
namespace Craft;

/**
 * @=Patrol
 *
 * Patrol aims to improve deployment workflow and security for sites built with Craft
 *
 * @author		Selvin Ortiz <selvin@selv.in>
 * @package		Patrol
 * @category	Security
 */

class PatrolPlugin extends BasePlugin
{
	protected $metadata	= array(
		'plugin'		=> 'Patrol',
		'version'		=> '0.7.0',
		'description'	=> 'Patrol aims to improve deployment workflow and security for sites built with Craft',
		'developer'		=> array(
			'name'		=> 'Selvin Ortiz',
			'website'	=> 'http://selv.in'
		)
	);

	public function init()
	{
		// PatrolHelper
		Craft::import('plugins.patrol.helpers.PatrolHelper');

		// Patrol.watch()
		craft()->patrol->watch($this->getSettings());
	}

	public function getName()			{ return $this->getPluginAlias($this->metadata['plugin']); }

	public function getVersion()		{ return $this->metadata['version']; }

	public function getDescription()	{ return $this->metadata['description']; }

	public function getDeveloper()		{ return $this->metadata['developer']['name']; }

	public function getDeveloperUrl()	{ return $this->metadata['developer']['website']; }

	public function hasCpSection()		{ return (bool) $this->getSettings()->enableCpTab; }

	public function registerCpRoutes()	{ return array(); }

	public function defineSettings()
	{
		return array(
			'maintenanceMode'	=> array( AttributeType::Bool ),
			'maintenanceUrl'	=> array( AttributeType::String ),
			'authorizedIps'		=> array( AttributeType::Mixed ),
			'forceSsl'			=> array( AttributeType::Bool ),
			'restrictedAreas'	=> array( AttributeType::Mixed ),
			'enableCpTab'		=> array( AttributeType::Bool ),
			'pluginAlias'		=> array( AttributeType::String )
		);
	}

	/**
	 * Extended version of getSettings() to handle two use cases for settings
	 *
	 * @param	bool		$templateReady	Whether or not settings should be prepared for template use
	 * @return	BaseModel
	 */
	public function getSettings($templateReady=false)
	{
		$settingsModel = parent::getSettings();

		// Prepare settings for template use?
		if ($templateReady)
		{
			$authorizedIps		= $settingsModel->getAttribute('authorizedIps');
			$restrictedAreas	= $settingsModel->getAttribute('restrictedAreas');

			if (is_array($authorizedIps) && count($authorizedIps))
			{
				$settingsModel->setAttribute('authorizedIps', implode(PHP_EOL, $authorizedIps));
			}

			if (is_array($restrictedAreas) && count($restrictedAreas))
			{
				$settingsModel->setAttribute('restrictedAreas', implode(PHP_EOL, $restrictedAreas));
			}
		}

		return $settingsModel;
	}

	public function getSettingsHtml()
	{
		craft()->templates->includeCssResource('patrol/css/patrol.css');
		craft()->templates->includeJsResource('patrol/js/jquery.backstretch.min.js');
		craft()->templates->includeJsResource('patrol/js/mousetrap.min.js');
		craft()->templates->includeJsResource('patrol/js/patrol.js');

		$settings = $this->getSettings();

		return craft()->templates->render('patrol/_settings',
			array(
				'name'			=> $this->getName(),
				'status'		=> $settings->maintenanceMode || $settings->forceSsl ? 'Watching' : 'Off Duty',
				'version'		=> $this->getVersion(),
				'description'	=> $this->getDescription(),
				'settings'		=> $this->getSettings(true),
				'importUrl'		=> sprintf('/%s/%s/patrol/importSettings', craft()->config->get('cpTrigger'), craft()->config->get('actionTrigger')),
				'exportUrl'		=> sprintf('/%s/%s/patrol/exportSettings', craft()->config->get('cpTrigger'), craft()->config->get('actionTrigger')),
				'hasCpRule'		=> craft()->patrol->hasCpRule($settings),
				'hasIpRule'		=> craft()->patrol->hasIpRule($settings),
				'requestingIp'	=> craft()->patrol->getRequestingIp()
			)
		);
	}

	public function prepSettings($settings=array())
	{
		return craft()->patrol->prepare($settings);
	}

	protected function getPluginAlias($default='')
	{
		$alias = $this->getSettings()->pluginAlias;

		return empty($alias) ? Craft::t($default) : $alias;
	}
}

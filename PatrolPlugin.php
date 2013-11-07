<?php
namespace Craft;

/**
 * @=Patrol
 *
 * Patrol simplifies maintenance mode and SSL management on sites built with Craft
 *
 * @author		Selvin Ortiz <selvin@selv.in>
 * @version		0.9.2
 * @package		Patrol
 * @category	Security
 * @since		Craft 1.3
 */

class PatrolPlugin extends BasePlugin
{
	protected $metadata	= array(
		'plugin'		=> 'Patrol',
		'version'		=> '0.9.2',
		'description'	=> 'Patrol simplifies maintenance mode and SSL management on sites built with Craft',
		'developer'		=> array(
			'name'		=> 'Selvin Ortiz',
			'website'	=> 'http://selv.in'
		)
	);

	public function init()
	{
		craft()->patrol->watch($this->getSettings());
	}

	public function getName($realName=false)
	{
		return $realName ? Craft::t($this->metadata['plugin']) : $this->getPluginAlias($this->metadata['plugin']);
	}

	public function getVersion()		{ return $this->metadata['version']; }

	public function getDescription()	{ return $this->metadata['description']; }

	public function getDeveloper()		{ return $this->metadata['developer']['name']; }

	public function getDeveloperUrl()	{ return $this->metadata['developer']['website']; }

	public function hasCpSection()		{ return (bool) $this->getSettings()->enableCpTab; }

	public function defineSettings()
	{
		return array(
			'maintenanceMode'	=> array( AttributeType::Bool ),
			'maintenanceUrl'	=> array( AttributeType::String ),
			'authorizedIps'		=> array( AttributeType::Mixed ),
			'forceSsl'			=> array( AttributeType::Bool ),
			'restrictedAreas'	=> array( AttributeType::Mixed ),
			'enableCpTab'		=> array( AttributeType::Bool ),
			'pluginAlias'		=> array( AttributeType::String ),
		);
	}

	/**
	 * Extends getSettings() to handle formatting for template use
	 *
	 * @param	bool	$templateReady	Whether or not settings should be formatted for template use
	 * @return	Model
	 */
	public function getSettings($templateReady=false)
	{
		$settingsModel = parent::getSettings();

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
		$this->includeResources();

		return craft()->templates->render('patrol/_settings', $this->getTemplateVars());
	}

	/**
	 * Prepare setting for save to db
	 *
	 * @todo	Remove isset() logic when onAfterInstall() support for services is fixed
	 * @param	array	$settings
	 * @return	array
	 */
	public function prepSettings($settings=array())
	{
		if (isset(craft()->patrol_settings))
		{
			return craft()->patrol_settings->prepare($settings);
		}
		else
		{
			return Patrol_SettingsService::doPrepare($settings);
		}
	}

	/**
	 * Saves default settings from /Patrol.json after installation
	 *
	 * @todo	Remove static call when craft()->patrol_settings->save() becomes available
	 */
	public function onAfterInstall()
	{
		if (isset(craft()->patrol_settings))
		{
			craft()->patrol_settings->save();
		} else
		{
			if (class_exists(__NAMESPACE__.'\\Patrol_SettingsService'))
			{
				Patrol_SettingsService::doSave();
			}
		}
	}

	protected function includeResources()
	{
		// @todo	Migrate to production mode only on next major update
		if (craft()->config->get('devMode'))
		{
			craft()->templates->includeCssResource('patrol/css/patrol.css');
			craft()->templates->includeJsResource('patrol/js/mousetrap.js');
			craft()->templates->includeJsResource('patrol/js/patrol.js');
		}
		else
		{
			craft()->templates->includeCssResource('patrol/min/patrol.css');
			craft()->templates->includeJsResource('patrol/min/patrol.js');
		}
	}

	protected function getTemplateVars()
	{
		$settings		= $this->getSettings();
		$baseCpUrl		= sprintf('/%s/%s/', craft()->config->get('cpTrigger'), craft()->config->get('actionTrigger'));
		$patrolStatus	= !craft()->config->get('devMode') && ($settings->maintenanceMode || $settings->forceSsl) ? 'On Duty' : 'Off Duty';

		return array(
			'name'			=> $this->getName(),
			'status'		=> $patrolStatus,
			'version'		=> $this->getVersion(),
			'description'	=> $this->getDescription(),
			'settings'		=> $this->getSettings(true),
			'importUrl'		=> $baseCpUrl.'/patrol/importSettings',
			'exportUrl'		=> $baseCpUrl.'/patrol/exportSettings',
			'hasCpRule'		=> craft()->patrol->hasCpRule($settings),
			'hasIpRule'		=> craft()->patrol->hasIpRule($settings),
			'devMode'		=> craft()->config->get('devMode'),
			'requestingIp'	=> craft()->patrol->getRequestingIp()
		);
	}

	protected function getPluginAlias($default='')
	{
		$alias = $this->getSettings()->pluginAlias;

		return empty($alias) ? Craft::t($default) : $alias;
	}
}

<?php
namespace Craft;

/**
 * @=Patrol
 *
 * Patrol simplifies maintenance and SSL for sites built with Craft
 *
 * @author   Selvin Ortiz <selvin@selvin.co>
 * @version  1.1.0
 * @package  Patrol
 * @category Security
 * @since    Craft 1.3
 * --
 * @property bool   $maintenanceMode Whether maintenance mode is on (offline)
 * @property string $maintenanceUrl  The URL/template to redirect to when maintenance mode is on
 * @property array  $authorizedIps   The list of IP addresses that bypass maintenance mode
 * @property bool   $forceSsl        Whether force SSL mode is on (https)
 * @property array  $restrictedAreas The list or sections that should be restricted when force SSL mode is on
 * @property bool   $enableCpTab     Whether the Control Panel tab for Patrol is display
 * @property string $pluginAlias     The name that Patrol was renamed to by the user after installation
 */
class PatrolPlugin extends BasePlugin
{
	/**
	 * @var array The raw settings model attributes
	 */
	protected $settings;

	/**
	 * @var array The settings configured via the general environment config which take priority
	 */
	protected $configs;

	/**
	 * Loads the general environment configs and merges them with the raw settings model attributes
	 * This allows us to fully configure Patrol via the multiple environment configuration
	 */
	public function init()
	{
		$this->configs  = craft()->config->get('patrol');
		$this->settings = array_merge($this->getSettings()->getAttributes(), $this->configs ? $this->configs : array());

		patrol()->watch($this->settings);
	}

	/**
	 * Returns the real name of the plugin or the plugin alias given by the user after installation
	 *
	 * @param bool $real
	 *
	 * @return string
	 */
	public function getName($real = false)
	{
		$alias = $this->settings['pluginAlias'];

		return ($real || empty($alias)) ? 'Patrol' : $alias;
	}

	/**
	 * @return string
	 */
	public function getVersion()
	{
		return '1.1.0';
	}

	/**
	 * @return string
	 */
	public function getDeveloper()
	{
		return 'Selvin Ortiz';
	}

	/**
	 * @return string
	 */
	public function getDeveloperUrl()
	{
		return 'https://selv.in';
	}

	/**
	 * @return bool
	 */
	public function hasCpSection()
	{
		return (bool) $this->settings['enableCpTab'];
	}

	/**
	 * @return array
	 */
	public function defineSettings()
	{
		return array(
			'maintenanceMode'      => AttributeType::Bool,
			'maintenanceUrl'       => AttributeType::String,
			'authorizedIps'        => AttributeType::Mixed,
			'forceSsl'             => AttributeType::Bool,
			'restrictedAreas'      => AttributeType::Mixed,
			'enableCpTab'          => AttributeType::Bool,
			'pluginAlias'          => AttributeType::String,
		);
	}

	/**
	 * @return bool Whether rendering was successful
	 */
	public function getSettingsHtml()
	{
		$settings = $this->settings;

		craft()->templates->includeCssResource('patrol/css/patrol.css');

		if (is_array($settings['authorizedIps']))
		{
			$settings['authorizedIps'] = implode(PHP_EOL, $settings['authorizedIps']);
		}

		if (is_array($settings['restrictedAreas']))
		{
			$settings['restrictedAreas'] = implode(PHP_EOL, $settings['restrictedAreas']);
		}

		$variables = array(
			'name'     => $this->getName(true),
			'alias'    => $this->getName(),
			'status'   => $this->settings['maintenanceMode'] || $this->settings['forceSsl'] ? 'On Duty' : 'Off Duty',
			'version'  => $this->getVersion(),
			'settings' => $settings,
			'configs'  => $this->configs,
		);

		return craft()->templates->render('patrol/_settings', $variables);
	}

	/**
	 * Prepares plugin settings prior to saving them to the db
	 *    - authorizedIps are converted from string to array
	 *    - restrictedAreas are converted from string to array
	 *
	 * @param array $settings
	 *
	 * @return array
	 */
	public function prepSettings($settings = array())
	{
		$ips   = craft()->request->getPost('settings.authorizedIps', $this->settings['authorizedIps']);
		$areas = craft()->request->getPost('settings.restrictedAreas', $this->settings['restrictedAreas']);

		$settings['authorizedIps']   = patrol()->parseAuthorizedIps($ips);
		$settings['restrictedAreas'] = patrol()->parseRestrictedAreas($areas);

		return $settings;
	}

	/**
	 * @return array
	 */
	public function registerUserPermissions()
	{
		return array(
			'patrolMaintenanceModeBypass' => array(
				'label' => Craft::t('Access the site when maintenance is on')
			),
		);
	}

}

/**
 * Returns an instance of the Patrol service and enables proper hinting and service layer encapsulation
 *
 * @return PatrolService
 */
function patrol()
{
	return Craft::app()->getComponent('patrol');
}

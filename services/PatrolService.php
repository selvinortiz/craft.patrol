<?php
namespace Craft;

/**
 * Class PatrolService
 *
 * @author  Selvin Ortiz <selvin@selvin.co>
 * @package Craft
 */
class PatrolService extends BaseApplicationComponent
{
    /**
     * An array of settings from the plugin and general config
     *
     * @var array
     */
    protected $settings;

    /**
     * An array of key/value pairs used when parsing restricted areas like {cpTrigger}
     *
     * @var array
     */
    protected $dynamicParams;

    /**
     * @param array $settings
     */
    public function watch(array $settings)
    {
        $this->settings = $settings;

        $this->enforceDomainRules();
        $this->enforceConnectionRules();
        $this->enforceMaintenanceRules();
    }

    /**
     * Enforces domain rules
     *
     * @return void
     */
    protected function enforceDomainRules()
    {
        if (! empty($this->settings['primaryDomain']) && mb_strpos($this->settings['primaryDomain'], '*') === false)
        {
            $vars          = $this->getDynamicParams();
            $primaryDomain = craft()->templates->renderObjectTemplate($this->settings['primaryDomain'], $vars);

            if (StringHelper::toLowerCase($primaryDomain) != StringHelper::toLowerCase($_SERVER['SERVER_NAME']))
            {
                // Checking for http or https at the beginning
                if (mb_stripos($primaryDomain, 'http') !== 0)
                {
                    // Using http by default and let the next request sort out SSL
                    $primaryDomain = 'http://'.$primaryDomain;
                }

                craft()->request->redirect($primaryDomain);
            }
        }
    }

    /**
     * Enforces connection rules
     *
     * @return void
     */
    protected function enforceConnectionRules()
    {
        if ($this->settings['forceSsl'])
        {
            $vars              = $this->getDynamicParams();
            $requestedUrl      = craft()->request->getUrl();
            $restrictedAreas   = $this->settings['restrictedAreas'];
            $securedConnection = $this->isSecureConnection();

            // Forcing SSL if no restricted areas are defined, equivalent to strict mode.
            if (empty($restrictedAreas))
            {
                if (! $securedConnection)
                {
                    $this->forceSsl();
                }

                return;
            }

            // Force SSL
            if (! $securedConnection)
            {
                foreach ($restrictedAreas as $restrictedArea)
                {
                    // Parse dynamic variables like /{cpTrigger}
                    if (stripos($restrictedArea, '{') !== false)
                    {
                        $restrictedArea = craft()->templates->renderObjectTemplate($restrictedArea, $vars);
                    }

                    $restrictedArea = '/'.ltrim($restrictedArea, '/');

                    if (stripos($requestedUrl, $restrictedArea) === 0)
                    {
                        $this->forceSsl();
                    }
                }

                return;
            }

            // Revert SSL
            if ($securedConnection)
            {
                foreach ($restrictedAreas as $restrictedArea)
                {
                    if (stripos($restrictedArea, '{') !== false)
                    {
                        $restrictedArea = craft()->templates->renderObjectTemplate($restrictedArea, $vars);
                    }

                    if (stripos($requestedUrl, $restrictedArea) !== false)
                    {
                        return;
                    }
                }

                $this->revertSsl();
            }
        }
    }

    /**
     * Enforce maintenance rules
     *
     * @return void
     */
    protected function enforceMaintenanceRules()
    {
        $maintenance     = $this->settings['maintenanceMode'];
        $isCpRequest     = craft()->request->isCpRequest();
        $isSiteRequest   = craft()->request->isSiteRequest();
        $isCpLoginPage   = mb_stripos(craft()->request->getRequestUri(), '/login');
        $maintenanceUrl  = $this->settings['maintenanceUrl'];
        $limitCpAccessTo = $this->settings['limitCpAccessTo'];

        if ($isCpRequest && is_array($limitCpAccessTo) && count($limitCpAccessTo) && ! $isCpLoginPage)
        {
            // Redirect to login page
            if (! craft()->userSession->isLoggedIn())
            {
                craft()->request->redirect(sprintf('/%s/login', craft()->config->get('cpTrigger')));
            }
            else
            {
                // Verify
                $user = craft()->userSession->getUser();

                if (in_array($user->email, $limitCpAccessTo) || in_array($user->username, $limitCpAccessTo))
                {
                    return;
                }
            }

            $this->forceRedirect($maintenanceUrl);
        }

        if ($maintenance && $isSiteRequest)
        {
            // Authorize logged in admins on the fly
            if ($this->doesCurrentUserHaveAccess())
            {
                return;
            }

            $requestingIp  = $this->getRequestingIp();
            $authorizedIps = $this->settings['authorizedIps'];

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
            $this->dynamicParams = [
                'cpTrigger'     => craft()->config->get('cpTrigger'),
                'actionTrigger' => craft()->config->get('actionTrigger'),
            ];

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
        if (is_string($ips))
        {
            $ips = explode(PHP_EOL, trim($ips));
        }

        return $this->filterOutArrayValues(
            $ips,
            function ($val)
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
        if (is_string($areas) && ! empty($areas))
        {
            $areas = trim($areas);
            $areas = explode(PHP_EOL, $areas);
        }

        return $this->filterOutArrayValues(
            $areas,
            function ($val)
            {
                $valid = preg_match('/^[\/\{\}a-z\_\-\?\=]{1,255}$/i', $val);

                if (! $valid)
                {
                    return false;
                }

                return true;
            }
        );
    }

    /**
     * Determines whether we're already secured, even if on CloudFlare Flexible SSL
     *
     * @return bool
     */
    protected function isSecureConnection()
    {
        if (craft()->request->isSecureConnection())
        {
            return true;
        }

        if (isset($_SERVER['HTTP_CF_VISITOR']) && stripos($_SERVER['HTTP_CF_VISITOR'], 'https') !== false)
        {
            return true;
        }

        return false;
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
    protected function filterOutArrayValues($values = null, callable $filter = null, $preserveKeys = false)
    {
        $data = [];

        if (is_array($values) && count($values))
        {
            foreach ($values as $key => $value)
            {
                $value = trim($value);

                if (! empty($value))
                {
                    if (is_callable($filter) && $filter($value))
                    {
                        $data[$key] = $value;
                    }
                }
            }

            if (! $preserveKeys)
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
     *
     * @return bool
     */
    protected function doesCurrentUserHaveAccess()
    {
        $admin      = craft()->userSession->isAdmin();
        $authorized = craft()->userSession->checkPermission('patrolMaintenanceModeBypass');

        return ($admin || $authorized);
    }

    /**
     * @throws HttpException
     */
    protected function runDefaultBehavior()
    {
        throw new HttpException(403);
    }
}

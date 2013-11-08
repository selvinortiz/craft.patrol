<?php
namespace Craft;

class PatrolController extends BaseController
{
	protected $allowAnonymous = array('actionExportSettings');

	public function actionExportSettings()
	{
		$this->requireAdmin();

		if (!craft()->patrol_settings->export())
		{
			craft()->userSession->setError('Patrol: '.Craft::t('Unable to export settings to JSON'));
			$this->redirectToPatrol();
		}
	}

	public function actionImportSettings()
	{
		$this->requireAdmin();
		$this->requirePostRequest();

		if (craft()->patrol_settings->import())
		{
			craft()->userSession->setNotice('Patrol: '.Craft::t('Settings saved successfully'));
		}
		else
		{
			if (craft()->patrol->hasWarnings('jsonDecoding'))
			{
				craft()->userSession->setError('Patrol: '.Craft::t(craft()->patrol->getWarning('jsonDecoding')));
			}
			else
			{
				craft()->userSession->setError('Patrol: '.Craft::t('Unable to import settings'));
			}

		}

		$this->redirectToPatrol();
	}

	public function redirectToPatrol()
	{
		$this->redirect('settings/plugins/patrol');
	}
}

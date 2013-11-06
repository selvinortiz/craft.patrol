<?php
namespace Craft;

class PatrolController extends BaseController
{
	protected $allowAnonymous = array('actionExportSettings');

	/**
	 * @Todo:	Put this controller on a diet, too fat right now.
	 */
	public function actionExportSettings()
	{
		$this->requireAdmin();

		if (!craft()->patrol_settings->export())
		{
			craft()->userSession->setError('Unable to export settings to JSON');
			$this->redirectToPatrol();
		}
	}

	public function actionImportSettings()
	{
		$this->requireAdmin();
		$this->requirePostRequest();

		if (craft()->patrol_settings->save())
		{
			craft()->userSession->setNotice('Patrol settings saved successfully');
		}
		else
		{
			craft()->userSession->setError('Unable to save settings');
		}

		$this->redirectToPatrol();
	}

	public function redirectToPatrol()
	{
		$this->redirect('settings/plugins/patrol');
	}
}

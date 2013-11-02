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

		// Disable devMode or the output will be jumbled with JS
		craft()->config->set('devMode', false);

		$exportInfo = array(
			'metadata'		=> array(
				'exportedFrom'	=> Craft::getSiteName(),
				'exportedAt'	=> DateTimeHelper::currentUTCDateTime(),
				'exportedBy'	=> craft()->getUser()->getName()
			)
		);

		$settings	= craft()->plugins->getPlugin('patrol')->getSettings();
		$settings 	= $settings->getAttributes();
		$json		= json_encode(array_merge($exportInfo, array('settings'=>$settings)));

		if (json_last_error() == JSON_ERROR_NONE)
		{
			header('Content-disposition: attachment; filename='.craft()->patrol->getExportFileName());
			header('Content-type: application/json');
			header('Pragma: no-cache');

			echo craft()->patrol->jsonPrettify($json);
		}

		craft()->end();
	}

	public function actionImportSettings()
	{
		$this->requireAdmin();
		$this->requirePostRequest();

		$file	= array_shift($_FILES);
		$field	= craft()->patrol->getImportFieldName();

		if ($file && isset($file['name'][$field]) && $file['error'][$field] == 0)
		{
			$file = new \CUploadedFile($file['name'][$field], $file['tmp_name'][$field], $file['type'][$field], $file['size'][$field], $file['error'][$field]);

			if (strtoupper($file->getExtensionName()) == 'JSON' && craft()->patrol->importSettings($file))
			{
				craft()->userSession->setNotice('Settings for Patrol imported successfully:)');
				$this->redirectToPatrol();
			}
		}

		craft()->userSession->setError('Unable to save imported settings');
		$this->redirectToPatrol();
	}

	public function redirectToPatrol()
	{
		// $url = sprintf('/%s/settings/plugins/patrol', craft()->config->get('cpTrigger'));

		$this->redirect('settings/plugins/patrol');
	}
}

<?php
/*
 *  package: Joomla new article notification plugin
 *  copyright: Copyright (c) 2022. Jeroen Moolenschot | Joomill
 *  license: GNU General Public License version 2 or later
 *  link: https://www.joomill-extensions.com
 */

defined('_JEXEC') or die;

use Joomla\CMS\Access\Access;
use Joomla\CMS\Factory;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Mail\Exception\MailDisabledException;
use Joomla\CMS\Mail\MailTemplate;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Table\Table;
use Joomla\Database\ParameterType;
use PHPMailer\PHPMailer\Exception as phpMailerException;

class PlgSystemArticlenotification extends CMSPlugin
{
	/**
	 * Application object
	 *
	 * @var    \Joomla\CMS\Application\CMSApplication
	 * @since  4.0.0
	 */
	protected $app;

	/**
	 * Database driver
	 *
	 * @var    \Joomla\Database\DatabaseInterface
	 * @since  4.0.0
	 */
	protected $db;

	/**
	 * Load plugin language files automatically
	 *
	 * @var    boolean
	 * @since  4.0.0
	 */
	protected $autoloadLanguage = true;

	/**
	 * The notification email code is triggered after article is saved.
	 *
	 * @return  void
	 *
	 * @since  4.0.0
	 */

    function onContentAfterSave($context, $article, $isNew )
    {

		$doc = $this->app->getDocument();

        // Don't send when article is created in the backend
        $app = Factory::getApplication();
        if($app->isClient('administrator')){
            return true;
        }

        // Don't send when exsisting article is edited 
        if (!$isNew) {
        	return false;
        }

		// Get Category name
		$db = Factory::getDbo(); 
		$db->setQuery("SELECT cat.title FROM #__categories cat WHERE cat.id='$article->catid'"); 
		$article->category = $db->loadResult();

		// Check if Category needs to send a Notification
		if ($this->params->get('categories')) {
	        if (!in_array($article->catid , $this->params->get('categories'))) {
	    		return false;
			}
		}

		// Get Author name
		$user = Factory::getUser($article->created_by);
		$article->author = $user->get('name');

		// Tags
		$substitutions = [
			'sitename' => $this->app->get('sitename'),
			'title'    => $article->title,
			'category' => $article->category,
			'author'   => $article->author
		];

		$language = $this->getLanguage();


		// Let's find out the email addresses to notify
		$emails = explode(',', $this->params->get('email', ''));

		// Send the emails
		foreach ($emails as $email)
		{
			try
			{
				$mailer = new MailTemplate('plg_system_articlenotification.mail', $language->getTag());
				$mailer->addRecipient($email);
				$mailer->addTemplateData($substitutions);
				$mailer->send();
			}

			catch (MailDisabledException | phpMailerException $exception)
			{
				try
				{
					Log::add(Text::_($exception->getMessage()), Log::WARNING, 'jerror');
				}

				catch (\RuntimeException $exception)
				{
					$this->app->enqueueMessage(Text::_($exception->articleMessage()), 'warning');
				}
			}
		}
	}

	private function getLanguage(): \Joomla\CMS\Language\Language
	{
		$language = $this->app->getLanguage();
		$language->load('plg_system_articlenotification', JPATH_ADMINISTRATOR, 'en-GB', true, true);
		$language->load('plg_system_articlenotification', JPATH_ADMINISTRATOR, null, true, false);

		// Then try loading the preferred (forced) language
		$forcedLanguage = $this->params->get('language_override', '');

		if (!empty($forcedLanguage))
		{
			$language->load('plg_system_articlenotification', JPATH_ADMINISTRATOR, $forcedLanguage, true, false);
		}

		return $language;
	}
}

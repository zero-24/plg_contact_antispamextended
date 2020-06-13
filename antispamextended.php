<?php
/**
 * AntiSpamExtended Plugin
 *
 * @copyright  Copyright (C) 2020 Tobias Zulauf All rights reserved.
 * @license    http://www.gnu.org/licenses/gpl-2.0.txt GNU General Public License Version 2 or Later
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\CMSPlugin;

/**
 * Plugin class for Http Header
 *
 * @since  1.0
 */
class PlgContactAntiSpamExtended extends CMSPlugin
{
	/**
	 * Affects constructor behavior. If true, language files will be loaded automatically.
	 *
	 * @var    boolean
	 * @since  1.0
	 */
	protected $autoloadLanguage = true;

	/**
	 * Blacklisted characters
	 *
	 * @var     array
	 * @since   1.0.0
	 */
	private $customBlacklist = [];

	/**
	 * Whitelisted chars that should be excluded from the checks
	 *
	 * @var     array
	 * @since   1.0.0
	 */
	private $whitelistedChars = [];

	/**
	 * Globally whitelisted chars that should be excluded from the checks
	 *
	 * @var     array
	 * @since   1.0.0
	 */
	private $globalWitelistedChars = [
		'ß',
		'ä',
		'ü',
		'ö',
		'´',
		'€',
		'°',
		'“',
		'„',
		'–',
	];

	/**
	 * Constructor.
	 *
	 * @param   object  &$subject  The object to observe
	 * @param   array   $config    An optional associative array of configuration settings.
	 *
	 * @since   1.0.0
	 */
	public function __construct(&$subject, $config)
	{
		parent::__construct($subject, $config);

		$this->customBlacklist  = explode(',', $this->params->get('character_blacklist', ''));
		$this->whitelistedChars = explode(',', $this->params->get('character_whitelist', ''));
		$this->whitelistedChars = array_merge($this->whitelistedChars, $this->globalWitelistedChars);
	}

	/**
	 * This event is triggered after a contact form has been submitted.
	 * An example use case would be validating a captcha. If you return an Exception object form submission will be terminated.
	 *
	 * @param   object   $contact   A reference to the person who will receive the form.
	 * @param   array    &$data     A reference to the data in the $_POST variable.
	 *
	 * @return  mixed    void on success or Exception on failure.
	 *
	 * @since   1.0.0
	 */
	public function onValidateContact(&$contact, &$data)
	{
		foreach ($data as $dataKey => $dataValue)
		{
			if ($dataKey === 'com_fields' && is_array($dataValue))
			{
				foreach ($dataValue as $fieldsKey => $fieldsValue)
				{
					$shouldBeBlocked = $this->checkContent($fieldsValue);

					if ($shouldBeBlocked)
					{
						throw new Exception(Text::_('PLG_CONTACT_ANTISPAMEXTENDED_ERROR_BLOCKED_BLACKLISTED'), 403);
					}
				}

				return;
			}

			$shouldBeBlocked = $this->checkContent($dataValue);

			if ($shouldBeBlocked)
			{
				throw new Exception(Text::_('PLG_CONTACT_ANTISPAMEXTENDED_ERROR_BLOCKED_BLACKLISTED'), 403);
			}
		}
	}

	/**
	 * Check the passed text and return true whether the message should be blocked
	 *
	 * @param   string   $text  The text to be checked
	 *
	 * @return  boolean  True whether the message should be blocked
	 *
	 * @since   1.0.0
	 */
	private function checkContent($text): bool
	{
		// Init the clearstring var with the original text
		$clearstring = $text;

		// Remove the blacklisted words / chars so that it triggers the checker
		foreach ($this->customBlacklist as $blacklisted)
		{
			$clearstring = str_replace($blacklisted, '', $clearstring);
		}

		// Check whether this triggerd already the checks
		if ($clearstring !== $text)
		{
			return true;
		}

		// Restart now with for the non-ascii checks
		$clearstring = $text;

		if ((int) $this->params->get('block_non_ascii_chars', 1) === 1)
		{
			// Make sure the whitelisted chars does not trigger the checker
			foreach ($this->whitelistedChars as $whitelistedChar)
			{
				$text = str_replace($whitelistedChar, '', $text);
				$clearstring = str_replace($whitelistedChar, '', $clearstring);

				$whitelistedChar = mb_strtoupper($whitelistedChar, 'UTF-8');
				$text = str_replace($whitelistedChar, '', $text);
				$clearstring = str_replace($whitelistedChar, '', $clearstring);
			}

			$clearstring = filter_var($text, FILTER_UNSAFE_RAW, FILTER_FLAG_STRIP_HIGH);
		}

		if ($clearstring !== $text)
		{
			return true;
		}

		return false;
	}
}

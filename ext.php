<?php
/**
 * Admin Activation Details extension for phpBB.
 *
 * @license GNU General Public License, version 2 (GPL-2.0)
 */

namespace phpbbmodders\adminactivationdetails;

/** Require the organization's supported phpBB and PHP security floors. */
class ext extends \phpbb\extension\base
{
	/**
	 * @return bool|string True on supported runtimes, otherwise a localized error
	 */
	public function is_enableable()
	{
		if (PHP_VERSION_ID >= 80336 &&
			phpbb_version_compare(PHPBB_VERSION, '3.3.19', '>=') &&
			phpbb_version_compare(PHPBB_VERSION, '4.1.0-dev', '<'))
		{
			return true;
		}

		$language = $this->container->get('language');
		$language->add_lang('install', 'phpbbmodders/adminactivationdetails');

		return $language->lang('ADMINACTIVATIONDETAILS_NOT_ENABLEABLE');
	}
}

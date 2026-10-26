<?php
/**
 * Admin Activation Details extension for phpBB.
 *
 * @license GNU General Public License, version 2 (GPL-2.0)
 */

if (!defined('IN_PHPBB'))
{
	exit;
}

if (empty($lang) || !is_array($lang))
{
	$lang = array();
}

$lang = array_merge($lang, array(
	'ADMINACTIVATIONDETAILS_NOT_ENABLEABLE' => 'Admin Activation Details requires phpBB 3.3.19 or phpBB 4.0, and PHP 8.3.36 or later.',
));

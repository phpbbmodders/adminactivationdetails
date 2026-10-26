<?php
/**
 * Add account details to administrator activation emails on phpBB 3.3 and 4.0.
 *
 * @license GNU General Public License, version 2 (GPL-2.0)
 */

namespace phpbbmodders\adminactivationdetails\event;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class listener implements EventSubscriberInterface
{
	/** @var \phpbb\user_loader */
	protected $user_loader;

	/** @var \phpbb\user */
	protected $user;

	/**
	 * @param \phpbb\user_loader $user_loader Account data cache
	 * @param \phpbb\user $user Email-rendering user's date preferences
	 */
	public function __construct(\phpbb\user_loader $user_loader, \phpbb\user $user)
	{
		$this->user_loader = $user_loader;
		$this->user = $user;
	}

	/**
	 * @return array Events shared by both supported phpBB branches
	 */
	public static function getSubscribedEvents()
	{
		return array('core.modify_notification_template' => 'activation_email');
	}

	/**
	 * Enrich only admin_activate emails, including direct activation resends.
	 * Read the account ID from core's activation URL, never from request data.
	 *
	 * @param \phpbb\event\data $event Email template before rendering
	 * @return void
	 */
	public function activation_email($event)
	{
		$template = $event['template'];
		if (basename($template->get_source_file_for_handle('body')) !== 'admin_activate.txt')
		{
			return;
		}

		$query = parse_url(html_entity_decode($template->retrieve_var('U_ACTIVATE'), ENT_COMPAT, 'UTF-8'), PHP_URL_QUERY);
		parse_str(is_string($query) ? $query : '', $params);
		$user_id = filter_var($params['u'] ?? null, FILTER_VALIDATE_INT, array('options' => array('min_range' => 2)));
		if ($user_id === false)
		{
			return;
		}

		$row = $this->user_loader->get_user($user_id, true);
		// user_loader falls back to Anonymous for a missing account.
		if (!$row || (int) $row['user_id'] !== $user_id)
		{
			$row = array();
		}

		$ip = $row['user_ip'] ?? '';
		$ip = filter_var($ip, FILTER_VALIDATE_IP) !== false ? $ip : '';
		$encoded_ip = rawurlencode($ip);
		$regdate = (int) ($row['user_regdate'] ?? 0);

		$template->assign_vars(array(
			'USER_IP'		=> $ip,
			'USER_EMAIL'		=> html_entity_decode($row['user_email'] ?? '', ENT_COMPAT, 'UTF-8'),
			'REG_DATE'		=> $regdate > 0 ? $this->user->format_date($regdate, '', true) : '',
			'REG_TIMEZONE'	=> $this->user->timezone ? $this->user->timezone->getName() : 'UTC',
			'U_WHOIS'		=> $ip !== '' ? 'https://search.arin.net/rdap/?query=' . $encoded_ip : '',
			'U_STOPFORUMSPAM'	=> $ip !== '' ? 'https://www.stopforumspam.com/search/?q=' . $encoded_ip : '',
		));
		$template->set_filenames(array('body' => '@phpbbmodders_adminactivationdetails/admin_activate.txt'));
	}
}

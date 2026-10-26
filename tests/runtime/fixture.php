<?php
/**
 * Configure and exercise a marked disposable board; never use on a live board.
 * Inputs: board root, operation, and optional username/IP or queue size.
 * Outputs: JSON account data or status; nonzero on invalid input or failure.
 */
if (PHP_SAPI !== 'cli' || count($argv) < 3)
{
	fwrite(STDERR, "Usage: php fixture.php BOARD_ROOT setup|account|notify|expire|queue|flush|unsubscribe|mail-off [VALUE] [IP]\n");
	exit(2);
}
$root = realpath($argv[1]);
if (!$root || !is_file($root . '/.adminactivationdetails-test-board'))
{
	throw new RuntimeException('A marked disposable test board is required.');
}
define('IN_PHPBB', true);
$phpbb_root_path = $root . '/';
$phpEx = 'php';
chdir($root);
require $phpbb_root_path . 'common.php';
$user->session_begin();
$auth->acl($user->data);
$user->setup();
// CLI has no signed-in email address; expose email-method availability for fixtures.
$user->data['user_email'] = 'fixture@example.org';
$manager = $phpbb_container->get('notification_manager');
$operation = $argv[2];
if ($operation === 'setup')
{
	foreach (array('require_activation' => USER_ACTIVATION_ADMIN, 'enable_confirm' => 0,
		'coppa_enable' => 0, 'email_enable' => 1, 'smtp_delivery' => 1,
		'smtp_host' => '127.0.0.1', 'smtp_port' => 18250, 'smtp_username' => '',
		'smtp_password' => '', 'smtp_auth_method' => 'PLAIN', 'email_package_size' => 0,
		'board_contact' => 'support@example.org', 'board_email' => 'support@example.org',
		'min_time_reg' => 0, 'min_time_terms' => 0, 'max_reg_attempts' => 0,
		'email_check_mx' => 0, 'check_dnsbl' => 0, 'cookie_secure' => 0) as $key => $value)
	{
		$config->set($key, $value);
	}
	$manager->add_subscription('notification.type.admin_activate_user', 0, 'notification.method.email', 2);
	echo json_encode(array('version' => PHPBB_VERSION, 'configured' => true));
}
else if ($operation === 'mail-off')
{
	$config->set('email_enable', 0);
}
else if ($operation === 'queue')
{
	$config->set('email_package_size', (int) ($argv[3] ?? 0));
}
else if ($operation === 'flush')
{
	if (phpbb_version_compare(PHPBB_VERSION, '4.0.0-dev', '>='))
	{
		$phpbb_container->get('messenger.queue')->process();
	}
	else
	{
		require_once $phpbb_root_path . 'includes/functions_messenger.php';
		$queue = new queue();
		$queue->process();
	}
}
else if ($operation === 'unsubscribe')
{
	$manager->delete_subscription('notification.type.admin_activate_user', 0, 'notification.method.email', 2);
}
else if (in_array($operation, array('account', 'notify', 'expire'), true))
{
	$name = $argv[3] ?? '';
	$result = $db->sql_query('SELECT * FROM ' . USERS_TABLE . " WHERE username = '" . $db->sql_escape($name) . "'");
	$row = $db->sql_fetchrow($result);
	$db->sql_freeresult($result);
	if (!$row)
	{
		throw new RuntimeException('Test account not found.');
	}
	if ($operation === 'expire')
	{
		$db->sql_query('UPDATE ' . USERS_TABLE . ' SET user_actkey_expiration = 0 WHERE user_id = ' . (int) $row['user_id']);
	}
	else if ($operation === 'notify')
	{
		$ip = $argv[4] ?? '';
		$db->sql_query('UPDATE ' . USERS_TABLE . " SET user_ip = '" . $db->sql_escape($ip) . "' WHERE user_id = " . (int) $row['user_id']);
		$manager->delete_notifications('notification.type.admin_activate_user', (int) $row['user_id']);
		// Email deduplication records are cleared through mark_notifications().
		$phpbb_container->get('notification.method.email')->mark_notifications(
			$manager->get_notification_type_id('notification.type.admin_activate_user'), (int) $row['user_id'], false);
		$manager->add_notifications('notification.type.admin_activate_user', array(
			'user_id' => (int) $row['user_id'], 'user_actkey' => $row['user_actkey'],
			'user_regdate' => (int) $row['user_regdate'],
		));
	}
	else
	{
		echo json_encode(array_intersect_key($row, array_flip(array('user_id', 'user_ip', 'user_email', 'user_type', 'user_actkey', 'user_regdate'))));
	}
}
else
{
	throw new InvalidArgumentException('Unknown test operation.');
}

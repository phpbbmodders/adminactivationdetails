<?php
/** Test account identity, template isolation, address validation and core links. */
namespace phpbbmodders\adminactivationdetails\tests;

use phpbbmodders\adminactivationdetails\event\listener;

class notification_test extends \phpbb_test_case
{
	private function render($row, $filename = 'admin_activate.txt', $url = 'https://board.example/ucp.php?mode=activate&u=42&k=keep-key')
	{
		$user = $this->getMockBuilder(\phpbb\user::class)->disableOriginalConstructor()
			->onlyMethods(array('format_date'))->getMock();
		$user->timezone = new \DateTimeZone('America/Chicago');
		$user->ip = '198.51.100.200';
		$user->method('format_date')->willReturn('10/26/2026 9:00 AM');
		$loader = $this->getMockBuilder(\phpbb\user_loader::class)->disableOriginalConstructor()
			->onlyMethods(array('get_user'))->getMock();
		$should_load = $filename === 'admin_activate.txt' && strpos($url, 'u=42') !== false;
		$loader->expects($should_load ? $this->once() : $this->never())->method('get_user')
			->with(42, true)->willReturn($row);
		$template = $this->createMock(\phpbb\template\template::class);
		$template->method('get_source_file_for_handle')->with('body')->willReturn('/language/en/email/' . $filename);
		$template->method('retrieve_var')->with('U_ACTIVATE')->willReturn($url);
		$vars = array();
		$template->method('assign_vars')->willReturnCallback(function ($added) use (&$vars) { $vars = $added; });
		$template->expects($should_load ? $this->once() : $this->never())->method('set_filenames')
			->with(array('body' => '@phpbbmodders_adminactivationdetails/admin_activate.txt'));
		$listener = new listener($loader, $user);
		$listener->activation_email(new \phpbb\event\data(array('template' => $template)));
		return $vars;
	}

	public function test_uses_applicant_details_and_keeps_core_links_untouched()
	{
		$vars = $this->render(array('user_id' => 42, 'user_ip' => '192.0.2.12',
			'user_email' => 'applicant@example.org', 'user_regdate' => 1793023200));
		$this->assertSame('192.0.2.12', $vars['USER_IP']);
		$this->assertSame('applicant@example.org', $vars['USER_EMAIL']);
		$this->assertSame('10/26/2026 9:00 AM', $vars['REG_DATE']);
		$this->assertSame('America/Chicago', $vars['REG_TIMEZONE']);
		$this->assertStringEndsWith('q=192.0.2.12', $vars['U_STOPFORUMSPAM']);
		foreach (array('U_ACTIVATE', 'U_USER_DETAILS', 'USERNAME', 'PASSWORD') as $core_var)
		{
			$this->assertArrayNotHasKey($core_var, $vars);
		}
	}

	public function test_ipv6_arguments_are_encoded()
	{
		$vars = $this->render(array('user_id' => 42, 'user_ip' => '2001:db8::12'));
		$this->assertSame('2001:db8::12', $vars['USER_IP']);
		$this->assertSame('https://search.arin.net/rdap/?query=2001%3Adb8%3A%3A12', $vars['U_WHOIS']);
		$this->assertStringEndsWith('q=2001%3Adb8%3A%3A12', $vars['U_STOPFORUMSPAM']);
	}

	public function test_invalid_ip_cannot_create_lookup_links()
	{
		$vars = $this->render(array('user_id' => 42, 'user_ip' => "192.0.2.1\r\nInjected: value"));
		$this->assertSame('', $vars['USER_IP']);
		$this->assertSame('', $vars['U_WHOIS']);
		$this->assertSame('', $vars['U_STOPFORUMSPAM']);
	}

	public function test_missing_fields_are_safe()
	{
		$vars = $this->render(array('user_id' => 42));
		$this->assertSame('', $vars['USER_IP']);
		$this->assertSame('', $vars['USER_EMAIL']);
		$this->assertSame('', $vars['REG_DATE']);
	}

	public function test_missing_account_does_not_expose_anonymous_details()
	{
		$vars = $this->render(array('user_id' => ANONYMOUS, 'user_ip' => '203.0.113.8',
			'user_email' => 'anonymous@example.org'));
		$this->assertSame('', $vars['USER_IP']);
		$this->assertSame('', $vars['USER_EMAIL']);
	}

	public function test_false_loader_result_is_safe()
	{
		$this->assertSame('', $this->render(false)['USER_IP']);
	}

	public function test_other_emails_are_not_modified()
	{
		$this->assertSame(array(), $this->render(false, 'user_welcome.txt'));
	}

	public function test_missing_or_invalid_account_id_does_not_query_users()
	{
		$this->assertSame(array(), $this->render(false, 'admin_activate.txt', 'https://board.example/ucp.php?u[]=42'));
		$this->assertSame(array(), $this->render(false, 'admin_activate.txt', 'https://board.example/ucp.php'));
	}
}

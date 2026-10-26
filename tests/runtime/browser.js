// Exercise registration, activation resends and local SMTP delivery on a marked test board.
// Inputs: TEST_URL, TEST_BOARD_ROOT, TEST_ARTIFACT_DIR; outputs JSON test results.
const fs = require('node:fs');
const path = require('node:path');
const { execFileSync } = require('node:child_process');
const assert = require('node:assert/strict');
const base = process.env.TEST_URL;
const root = process.env.TEST_BOARD_ROOT;
const work = process.env.TEST_ARTIFACT_DIR;
if (!base || !root || !work) {
 console.log('Usage: TEST_URL=http://localhost:PORT TEST_BOARD_ROOT=/path/to/marked/board TEST_ARTIFACT_DIR=/path/to/artifacts node browser.js');
 process.exit(0);
}
const fixture = path.join(__dirname, 'fixture.php');
assert.equal(new URL(base).hostname, 'localhost');
const { chromium } = require('playwright');
const username = 'iptest' + Date.now().toString().slice(-10);
function cli(...args) { return execFileSync('sudo', ['-n', '-u', 'www-data', 'php', fixture, root, ...args], {encoding:'utf8'}).trim(); }
function mails() { return JSON.parse(execFileSync('python3',[path.join(__dirname,'read_mail.py'),path.join(work,'mail')],{encoding:'utf8'})).filter(m=>m.body.includes(username)); }
function adminMails() { return mails().filter(m=>m.subject === 'Activate user account'); }
(async()=>{
 const browser = await chromium.launch({headless: !process.env.DISPLAY});
 const checks = [];
 try {
  const page = await browser.newPage();
  await page.goto(base+'/ucp.php?mode=register');
  await page.waitForTimeout(2200);
  await page.getByRole('button',{name:'I agree to these terms',exact:true}).click();
  await page.locator('#username').fill(username);
  await page.locator('#new_password').fill('SyntheticTest123!');
  await page.locator('#password_confirm').fill('SyntheticTest123!');
  await page.locator('#email').fill(username+'@example.org');
  await page.waitForTimeout(2200);
  await page.locator('#submit').click();
  const body = await page.locator('body').innerText();
  if (!/account has been created|account has been registered/i.test(body)) throw new Error('Registration did not succeed: '+body.slice(-4000));
  const account = JSON.parse(cli('account',username));
  let mail = adminMails().at(-1);
  assert.ok(mail, 'Administrator activation email delivered');
  assert.ok(mail.to.includes('admin@example.org'));
  assert.ok(mail.body.includes('Registration IP: '+account.user_ip));
  assert.ok(mail.body.includes('Email address: '+username+'@example.org'));
  assert.ok(mail.body.includes('Registration date:') && !mail.body.includes('Registration date: Not available'));
  assert.ok(mail.body.includes('u='+account.user_id+'&k='+account.user_actkey));
  assert.ok(!mail.body.includes('SyntheticTest123!'));
  assert.ok(!mail.body.includes('<!--') && !mail.body.includes('{USER_IP}'));
  assert.ok(mails().some(m=>m.to.includes(username+'@example.org') && !m.body.includes('WHOIS')));
  checks.push('browser registration, correct administrator and applicant mail, account IP/email/date, original activation key, no password or unparsed template');
  cli('notify',username,'2001:db8::12');
  mail=adminMails().at(-1);
  assert.ok(mail.body.includes('Registration IP: 2001:db8::12'));
  assert.ok(mail.body.includes('query=2001%3Adb8%3A%3A12'));
  assert.ok(mail.body.includes('q=2001%3Adb8%3A%3A12'));
  checks.push('IPv6 display and encoded lookup URLs');
  cli('expire',username);
  await page.goto(base+'/ucp.php?mode=resend_act');
  if (await page.locator('input[name=username]').count()) await page.locator('input[name=username]').fill(username);
  await page.locator('input[name=email]').fill(username+'@example.org');
  await page.waitForTimeout(2200);
  const beforeResend=adminMails().length;
  await page.locator('input[name=submit]').click();
  await page.waitForLoadState('domcontentloaded');
  mail=adminMails().at(-1);
  assert.ok(adminMails().length > beforeResend, 'Resend delivered administrator mail: '+(await page.locator('body').innerText()).slice(-1800));
  assert.ok(mail.body.includes('Registration IP: 2001:db8::12'));
  assert.ok(!mail.body.includes('Registration IP: 127.0.0.1'));
  checks.push('real browser resend uses stored account IP, not requesting visitor IP');
  cli('notify',username,'not-an-ip');
  mail=adminMails().at(-1);
  assert.ok(mail.body.includes('Registration IP: Not available'));
  assert.ok(!mail.body.includes('https://search.arin.net') && !mail.body.includes('https://www.stopforumspam.com'));
  checks.push('invalid IP hides lookup links');
  cli('queue','20');
  const beforeQueue=adminMails().length;
  cli('notify',username,'192.0.2.19');
  assert.equal(adminMails().length,beforeQueue,'Mail remains queued');
  cli('flush');
  assert.equal(adminMails().length,beforeQueue+1,'Queued mail delivered');
  assert.ok(adminMails().at(-1).body.includes('Registration IP: 192.0.2.19'));
  cli('queue','0');
  checks.push('queued delivery preserves rendered account details');
  cli('unsubscribe');
  const beforeUnsubscribe=adminMails().length;
  cli('notify',username,'192.0.2.20');
  assert.equal(adminMails().length,beforeUnsubscribe,'Notification preference respected');
  checks.push('notification-manager email opt-out respected');
  cli('setup');
  console.log(JSON.stringify({url:base,username,checks},null,2));
  fs.writeFileSync(path.join(work,'browser-'+new URL(base).port+'.json'),JSON.stringify({url:base,username,checks},null,2));
 } finally { await browser.close(); }
})().catch(e=>{console.error(e);process.exitCode=1;});

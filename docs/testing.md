# Implementation and testing

## Implementation

The extension listens to `core.modify_notification_template` on phpBB 3.3 and
4.0. Only emails using `admin_activate.txt` are affected. Core's activation URL
identifies the account; the extension loads its stored registration details.
Missing accounts cannot expose Anonymous account details.

This event covers notification-manager mail from new registrations and direct
mail from activation resends. Core still controls recipients, preferences,
activation keys, and queuing. The extension does not replace a core service.

phpBB's resend path sends directly to administrators. It does not use the
registration notification-manager subscription preferences; the extension
preserves that existing behavior rather than changing who receives mail.

## Results — 10/26/2026

Both live boards used PHP 8.4.27 with SQLite and a localhost-only SMTP capture.

| Board | Source revision | Unit tests | Browser and delivery checks |
| --- | --- | --- | --- |
| phpBB 3.3.19 (3.3.x source) | `1b1ffdf89c033f584a1daf0c6995c13e59dcb81b` | 8 tests, 42 assertions | Passed |
| phpBB 4.0.0-a3-dev | `2a2dad29a4d47d26d8f01f4bf4851c98f809c481` | 8 tests, 42 assertions | Passed |

Source inspection also compared the current 3.3.x (`d8e558ca55`) and master
(`a4cea6406f`) revisions: the exercised UCP, notification and messenger paths
are unchanged from the runtime snapshots above. The phpBB 4.0 claim is for the
measured development snapshot, not a future final release.

Verified on each board:

- Real registration through Playwright, with separate applicant and admin mail.
- Correct account email, stored registration IP, registration date/timezone,
  and existing profile/activation URLs; no password or unexpanded variables.
- IPv6 output and encoded lookup URLs; invalid addresses omit lookup links.
- Real activation resend from localhost retains the stored synthetic IPv6 IP.
- Mail remains queued until processing and retains its enriched contents.
- Registration notification email opt-out is respected.
- Disable plus cache purge restores the original email; re-enable plus cache
  purge restores the enriched email.
- Administrator login and the email's activation key activate the correct account.

The installable package passes EPV with **0 fatal, 0 error, 0 warning, 0 notice**.
The production PHP passes both branches' phpBB extension coding standards.
Composer metadata validates; its explicit extension version produces Composer's
usual Packagist advisory. GitHub CI is configured but has not been run remotely.

## Repeat unit tests

Use the committed `phpbb-release-tools/phpbb-test-board/run-unit-tests.sh`
runner against this checkout, once with `-b 3.3.x` and once with `-b master`.
It uses phpBB's own test bootstrap and supported PHPUnit dependencies.

CI repeats the regression suite and code standards on both phpBB branches,
using PHP 8.3 and 8.4. The organization EPV workflow validates both branches.
The local runtime tests above used PHP 8.4; PHP 8.3 CI has not run yet.

## Repeat browser/delivery tests

Use `seed-forum` with a copy of the appropriate `tests/runtime/board-*.yml.example`.
Change the source and disposable board paths, choose free ports, and install
with `reset-board.sh --config YOUR_CONFIG --reset`. These fixtures are strictly
for disposable local boards. Mark the new board root by creating an empty
`.adminactivationdetails-test-board` file; the PHP fixture refuses unmarked boards.

Start `python3 tests/runtime/smtp_capture.py /path/to/artifacts/mail` in a
separate terminal. It listens only on `127.0.0.1:18250` and never relays mail.
Configure the board with `sudo -u www-data php tests/runtime/fixture.php BOARD_ROOT setup`.
Keep test-board CLI cache operations under the web-server user so Twig cache
permissions remain consistent. Run `cache:purge` after CLI extension state changes.

Run `tests/runtime/browser.js` through the Playwright skill runner, or with a
locally installed Playwright and Chromium, setting `TEST_URL`, `TEST_BOARD_ROOT`
and `TEST_ARTIFACT_DIR`. The script uses the local SMTP capture and invokes the
PHP fixture through noninteractive `sudo -u www-data`.

The browser test creates only synthetic `@example.org` accounts. It tests
registration, resend, IPv6, invalid IPs, queueing and opt-out; results are written
as JSON to the artifact directory. The test restores the admin subscription.
After testing, use the fixture's `mail-off` operation and stop the capture server.
Restore or remove only the disposable boards and their dedicated nginx sites.

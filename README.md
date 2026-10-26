# Admin Activation Details

Adds the registering account's email address, stored registration IP and
registration date to phpBB's administrator activation emails. Valid IPv4
and IPv6 addresses include WHOIS/RDAP and Stop Forum Spam lookup links.

Requires phpBB 3.3.19 or phpBB 4.0 and PHP 8.3.36 or later. phpBB 4.0 is
still a development branch; see [testing](docs/testing.md) for tested revisions.

## Installation

Copy this extension into `ext/phpbbmodders/adminactivationdetails/`, then
open **ACP → Customise → Manage extensions** and enable **Admin Activation Details**.
Set **Account activation** to **By admin** in User registration settings.
Administrators must enable email for the existing **User requiring activation**
notification in their UCP notification settings.

There are no additional settings or database migrations. Disable the extension
to restore phpBB's original activation notification and email template.

## Email behavior

- Covers administrator activation notifications from registration and resends.
- Uses the account's stored registration IP, not the current visitor's address.
- Keeps phpBB's existing recipients, notification preferences, profile link,
  activation link and mail queue behavior.
- Uses phpBB's current email-rendering user's date format and timezone, shown
  explicitly beside the date. A queued email keeps that rendered date.
- Missing or invalid IPs show **Not available** and omit lookup links.
- Sends no passwords and makes no requests to lookup services. Clicking a lookup
  link submits the IP to that service. A registration IP may identify a proxy or VPN.

The extension supplies a plain-text English email template; phpBB's normal
language fallback applies. Translations can provide
`language/<locale>/email/admin_activate.txt` inside this extension. Existing
core-template customizations and HTML-email modifications do not automatically
carry over to the extension's template.

## Development

See [implementation and testing](docs/testing.md). The extension listens to
`core.modify_notification_template` and changes only the `admin_activate` email.
Another extension replacing that template may conflict.
No phpBB core files need editing. If using phpBB's CLI to enable or disable
the extension, run `cache:purge` afterward to rebuild its cached services.

The feature was requested in this [phpBB discussion](https://www.phpbb.com/community/viewtopic.php?t=2667817).

Synthetic [IPv4](samples/admin-activation-ipv4.txt) and
[IPv6](samples/admin-activation-ipv6.txt) email examples are included. Their example activation keys do not work.

## Acknowledgments

- Implementation, testing and documentation assisted by [Codex](https://openai.com/codex/).

## License

This extension is licensed under the **GNU General Public License v2.0**.
See [license.txt](license.txt).

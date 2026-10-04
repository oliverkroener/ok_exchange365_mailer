# Exchange 365 Mailer (ok_exchange365_mailer)

[![TYPO3 9](https://img.shields.io/badge/TYPO3-9-orange?logo=typo3)](https://get.typo3.org/version/9)
[![PHP 7.2+](https://img.shields.io/badge/PHP-7.2%2B-777BB4?logo=php&logoColor=white)](https://www.php.net/)
[![License: GPL v2+](https://img.shields.io/badge/License-GPL%20v2%2B-blue)](https://www.gnu.org/licenses/old-licenses/gpl-2.0.html)
[![Version](https://img.shields.io/badge/version-1.1.0-green)](https://github.com/oliverkroener/ok_exchange365_mailer)

A TYPO3 mail transport that sends emails through **Microsoft Exchange 365 / Microsoft 365** using the **Microsoft Graph API** with **OAuth 2.0** — no SMTP required.

## Features

- **SMTP-free email delivery** — mails are posted to the Microsoft Graph `/users/{id}/sendMail` endpoint instead of SMTP.
- **OAuth 2.0 (client credentials)** — app-only access token from the Microsoft identity platform v2.0 endpoint; no mailbox password is stored in TYPO3. The token is cached per credential set, so a request that sends several mails authenticates only once.
- **SwiftMailer transport** — implements `Swift_Transport`; existing `MailMessage` code keeps working unchanged.
- **Direct HTTPS calls with Guzzle** — no Microsoft Graph SDK. 10 s connect timeout, 30 s total timeout.
- **One careful retry** — a request is repeated once on HTTP 429/503/504 (honouring `Retry-After`) and when no connection could be made, but not after a connection stalled, so a mail is not delivered twice.
- **Every context** — the `TYPO3_CONF_VARS` settings apply to backend, frontend, CLI and scheduler. Frontend TypoScript overlays them per setting; empty values fall back.
- **Send As / Send On Behalf** — an optional `graphSenderUserId` targets a different Graph mailbox than the visible `From` address.
- **Save to Sent Items** — `saveToSentItems` controls whether Graph keeps a copy in the sender mailbox.
- **Credential masking** — tenant ID, client ID and client secret from `TYPO3_CONF_VARS` are masked in *System > Configuration* (lowlevel module hook).
- **Clear errors** — a failed send is logged and thrown as a `RuntimeException` that names the cause.

## Requirements

| Component | Supported |
| --- | --- |
| TYPO3 | 9.5 LTS (this is the 1.x line) |
| PHP | 7.2 – 7.4 |
| Composer packages | `oliverkroener/ok-typo3-helper` `^1`, `guzzlehttp/guzzle` `^6.3 \|\| ^7.0` |
| Microsoft 365 | A Microsoft Entra ID app registration with the `Mail.Send` application permission and admin consent |

## Installation

Install via Composer:

```bash
composer require oliverkroener/ok-exchange365-mailer:^1.1
```

Then activate the extension:

```bash
vendor/bin/typo3 extension:activate ok_exchange365_mailer
```

Before sending mail, register an application in Microsoft Entra ID and grant it the `Mail.Send` application permission. See [Documentation/Azure/Index.rst](Documentation/Azure/Index.rst).

## Configuration

The transport is selected **only** in `$GLOBALS['TYPO3_CONF_VARS']['MAIL']['transport']`
— there is no TypoScript option for it. The settings below live in
`$GLOBALS['TYPO3_CONF_VARS']['MAIL']` and are used in every context (frontend, backend,
CLI, scheduler).

| Setting | Type | Default | Description |
| --- | --- | --- | --- |
| `transport` | string | — | Set to `OliverKroener\OkExchange365\Mail\Transport\Exchange365Transport` |
| `transport_exchange365_tenantId` | string | — | Microsoft Entra ID Tenant ID |
| `transport_exchange365_clientId` | string | — | Azure Application (Client) ID |
| `transport_exchange365_clientSecret` | string | — | Azure Application Secret Value |
| `transport_exchange365_fromEmail` | string | `MAIL.defaultMailFromAddress` | Sender email address (must exist in Exchange 365) |
| `transport_exchange365_graphSenderUserId` | string | — | *(optional)* Graph mailbox used for `/users/{id}/sendMail` (*Send As* / *Send On Behalf*). Falls back to the message `From`, then `fromEmail`, then `MAIL.defaultMailFromAddress` |
| `transport_exchange365_saveToSentItems` | bool | `0` | `1` to save to Sent Items, `0` to skip (the static template defaults to `1` in the frontend) |

Example using environment variables (`TYPO3_CONF_VARS__MAIL__<setting>`):

```bash
TYPO3_CONF_VARS__MAIL__transport=OliverKroener\\OkExchange365\\Mail\\Transport\\Exchange365Transport
TYPO3_CONF_VARS__MAIL__transport_exchange365_tenantId='your-tenant-id'
TYPO3_CONF_VARS__MAIL__transport_exchange365_clientId='your-client-id'
TYPO3_CONF_VARS__MAIL__transport_exchange365_clientSecret='your-client-secret'
TYPO3_CONF_VARS__MAIL__transport_exchange365_fromEmail='service@your-domain.com'
# Optional: Send As / Send On Behalf
#TYPO3_CONF_VARS__MAIL__transport_exchange365_graphSenderUserId='shared-mailbox@your-domain.com'
TYPO3_CONF_VARS__MAIL__transport_exchange365_saveToSentItems=1
```

> **TYPO3 does not map `TYPO3_CONF_VARS__…` variables by itself.** Your project needs a
> small loop in `public/typo3conf/AdditionalConfiguration.php` that copies them into
> `$GLOBALS['TYPO3_CONF_VARS']`, or set the values there with `getenv()`. Both are shown
> in [the documentation](Documentation/Configuration/Index.rst). Never write the client
> secret as a literal into `LocalConfiguration.php` or `AdditionalConfiguration.php`.

A failed send throws a `RuntimeException`, e.g.
`… Error: Exchange 365 configuration missing required field: clientSecret`. Test the
setup in *Admin Tools > Environment > Test Mail Setup*. Tenant ID, client ID and client
secret from `TYPO3_CONF_VARS` are masked in *System > Configuration*.

### Via TypoScript (per-site overrides in the frontend)

Optional. Include the static template *[kroener.DIGITAL] Exchange 365 Mailer*, then read
the credentials from the environment with `getEnv()` — never write the secret into
TypoScript:

```typoscript
plugin.tx_okexchange365mailer.settings.exchange365 {
    tenantId := getEnv(EXCHANGE365_TENANT_ID)
    clientId := getEnv(EXCHANGE365_CLIENT_ID)
    clientSecret := getEnv(EXCHANGE365_CLIENT_SECRET)
    fromEmail = service@your-domain.com
    # Optional: route via a different mailbox using Send As / Send On Behalf
    # graphSenderUserId = service@your-domain.com
    saveToSentItems = 1
}
```

Non-empty TypoScript values override `TYPO3_CONF_VARS` **per setting**; empty values fall
back (except `saveToSentItems`, where empty means `0`). This works in setup and constants.

`getEnv()` reads PHP's real process environment (`getenv()`), not `$_ENV`: variables
loaded only into `$_ENV` (symfony/dotenv, helhum/dotenv-connector default) are invisible,
so set them where the web server starts PHP or use `putenv()`. A variable that is not set
leaves the previous value in place, and the result is cached until the next cache flush.
TypoScript is not sent to the browser, but it is readable in the backend and part of every
database dump. Details: [Frontend Configuration](Documentation/Configuration/Frontend.rst).

## Testing

This branch is covered by the cross-version test matrix on the extension's main branch
(`make test-matrix` / `make test-matrix-live` there). For TYPO3 9.5 it runs unit,
functional, PHPStan and coding-standard checks, sends real mail through Microsoft Graph
from the CLI and from the frontend (credentials only via `:= getEnv()`), and checks in a
headless browser that the credentials are masked in the backend.

The unit tests of this branch run with `composer test:unit`.

## Architecture

Request flow for one mail:

```
TYPO3 MailMessage (SwiftMailer)
  → Exchange365Transport::send()
      → configuration: TYPO3_CONF_VARS['MAIL'] baseline + non-empty frontend TypoScript
      → POST https://login.microsoftonline.com/{tenantId}/oauth2/v2.0/token   (cached per credential set)
      → POST https://graph.microsoft.com/v1.0/users/{sender}/sendMail
```

| Component | File | Role |
| --- | --- | --- |
| Mail transport | `Classes/Mail/Transport/Exchange365Transport.php` | `Swift_Transport` implementation; obtains the OAuth token and posts the message to the Graph `sendMail` endpoint with Guzzle. Selected via `MAIL.transport`; handles timeouts, the single retry and the sender resolution |
| Credential masking | `Classes/Hook/BlindedConfigurationOptionsHook.php`, registered in `ext_localconf.php` | Masks tenant ID, client ID and client secret from `TYPO3_CONF_VARS` in *System > Configuration* (lowlevel `ConfigurationController` hook) |
| TypoScript settings | `Configuration/TypoScript/` | Constants (Constant Editor category `exchange365mailer`) mapped to `plugin.tx_okexchange365mailer.settings.exchange365` |
| Static template | `Configuration/TCA/Overrides/sys_template.php` | Registers the static template *[kroener.DIGITAL] Exchange 365 Mailer* |
| Message conversion | `oliverkroener/ok-typo3-helper` (`MSGraphMailApiService`) | Converts the SwiftMailer message into the Graph message format |

```
ok_exchange365_mailer/
├── Build/                      PHPUnit, PHPStan and php-cs-fixer configuration
├── Classes/
│   ├── Hook/BlindedConfigurationOptionsHook.php
│   └── Mail/Transport/Exchange365Transport.php
├── Configuration/
│   ├── Services.yaml
│   ├── TCA/Overrides/sys_template.php
│   └── TypoScript/{constants,setup}.typoscript
├── Documentation/
├── Resources/Public/Icons/Extension.svg
├── Tests/{Functional,Unit}/
├── composer.json
├── ext_emconf.php
└── ext_localconf.php
```

## Documentation

Full documentation lives in the `Documentation/` directory:

- `Documentation/Introduction/Index.rst` — features, how it works, requirements
- `Documentation/Installation/Index.rst` — installation
- `Documentation/Azure/Index.rst` — Microsoft Entra ID app registration
- `Documentation/Configuration/Index.rst` — settings and environment variables
- `Documentation/Configuration/Frontend.rst` — optional frontend (TypoScript) overrides
- `Documentation/Faq/Index.rst` — FAQ and troubleshooting

Render it locally with `make docs`.

## License

This extension is licensed under the [GPL-2.0-or-later](https://www.gnu.org/licenses/old-licenses/gpl-2.0.html).

## Author — Oliver Kroener

### Automated. Scaled. Done.

Web3 · Cloud · Automation

Technology is only valuable when it solves a real problem. For over 30 years I've been translating between business and tech — so your investment in digitalisation doesn't stall at proof-of-concept but delivers measurable results.

- Website: [oliver-kroener.de](https://www.oliver-kroener.de)
- Web3: [web3.oliver-kroener.de](https://web3.oliver-kroener.de/)
- Email: [ok@oliver-kroener.de](mailto:ok@oliver-kroener.de)
- Web3 Email: [oliverkroener@ethermail.io](mailto:oliverkroener@ethermail.io)

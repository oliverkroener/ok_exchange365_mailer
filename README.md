# Exchange 365 Mailer (ok_exchange365_mailer)

[![TYPO3 10](https://img.shields.io/badge/TYPO3-10-orange?logo=typo3)](https://get.typo3.org/version/10)
[![PHP 7.2+](https://img.shields.io/badge/PHP-7.2%2B-777BB4?logo=php&logoColor=white)](https://www.php.net/)
[![License: GPL v2+](https://img.shields.io/badge/License-GPL%20v2%2B-blue)](https://www.gnu.org/licenses/old-licenses/gpl-2.0.html)
[![Version](https://img.shields.io/badge/version-2.2.0-green)](https://github.com/oliverkroener/ok_exchange365_mailer)

A TYPO3 mail transport that sends emails through **Microsoft Exchange 365 / Microsoft 365** using the **Microsoft Graph API** with **OAuth 2.0** — no SMTP required.

## Features

- **SMTP-free email delivery** — sends mail via the Microsoft Graph `sendMail` endpoint instead of SMTP.
- **OAuth 2.0 (client credentials)** — token-based authentication against Microsoft Entra ID; no mailbox passwords stored in TYPO3.
- **Backend and frontend support** — works for TYPO3 system mail (`$GLOBALS['TYPO3_CONF_VARS']['MAIL']`) and for frontend forms (Powermail, Form Framework); TypoScript can override the settings per site.
- **Credential blinding** — client ID, tenant ID and client secret from `TYPO3_CONF_VARS` are masked in *System > Configuration* (lowlevel module hook).
- **Send As / Send On Behalf** — an optional `graphSenderUserId` targets a different Graph mailbox than the visible `From` address.
- **Drop-in transport** — registers as a Symfony Mailer transport; existing `MailMessage` code keeps working unchanged.

## Requirements

| Component | Supported |
| --- | --- |
| TYPO3 | 10.4 LTS |
| PHP | 7.2 or higher |
| Other | `guzzlehttp/guzzle` `^6.3 \|\| ^7.0`, a Microsoft Entra ID app registration with `Mail.Send` application permission |

## Installation

Install via Composer:

```bash
composer require oliverkroener/ok-exchange365-mailer
```

Then activate the extension:

```bash
vendor/bin/typo3 extension:activate ok_exchange365_mailer
```

Before sending mail you must register an application in Microsoft Entra ID (Azure AD) and grant it the `Mail.Send` application permission. See `Documentation/Azure.rst` for the full Azure setup walkthrough.

## Configuration

Set the transport and credentials in `$GLOBALS['TYPO3_CONF_VARS']['MAIL']`. The transport is selected **only** here — TypoScript cannot switch it.

| Setting | Type | Default | Description |
| --- | --- | --- | --- |
| `transport` | string | — | Set to `OliverKroener\OkExchange365\Mail\Transport\Exchange365Transport` |
| `transport_exchange365_tenantId` | string | — | Microsoft Entra ID tenant ID |
| `transport_exchange365_clientId` | string | — | Azure application (client) ID |
| `transport_exchange365_clientSecret` | string | — | Azure application client secret **value** |
| `transport_exchange365_fromEmail` | string | `MAIL.defaultMailFromAddress` | Sender email address (must exist as a user or shared mailbox) |
| `transport_exchange365_graphSenderUserId` | string | — | *Optional.* Graph mailbox/user ID used for the API call (Send As / Send On Behalf). Falls back to the message `From`, then `fromEmail`, then `MAIL.defaultMailFromAddress` |

`saveToSentItems` has **no effect** on the 2.x line: the transport posts the raw MIME
message to Graph `sendMail`, which always saves a copy in *Sent Items*.

Example using environment variables (`.env`):

```bash
TYPO3_CONF_VARS__MAIL__transport=OliverKroener\\OkExchange365\\Mail\\Transport\\Exchange365Transport
TYPO3_CONF_VARS__MAIL__transport_exchange365_tenantId='your-tenant-id'
TYPO3_CONF_VARS__MAIL__transport_exchange365_clientId='your-client-id'
TYPO3_CONF_VARS__MAIL__transport_exchange365_clientSecret='your-client-secret'
TYPO3_CONF_VARS__MAIL__transport_exchange365_fromEmail='service@your-domain.com'
```

> **TYPO3 does not map `TYPO3_CONF_VARS__…` variables by itself.** Your project needs a
> small loop in `public/typo3conf/AdditionalConfiguration.php` that copies them into
> `$GLOBALS['TYPO3_CONF_VARS']`, or set the values there with `getenv()`. Both are shown
> in [Essential Configuration](Documentation/Configuration/Essential.rst).

### Frontend mail (Powermail, Form Framework)

Frontend mail uses the same `TYPO3_CONF_VARS` settings — no TypoScript is needed. To
override them per site, include the static template **`[kroener.DIGITAL] Exchange 365 Mailer`**
and read the credentials from the environment with `getEnv()` — never write the secret
into TypoScript:

```typoscript
plugin.tx_okexchange365mailer.settings.exchange365 {
    tenantId := getEnv(EXCHANGE365_TENANT_ID)
    clientId := getEnv(EXCHANGE365_CLIENT_ID)
    clientSecret := getEnv(EXCHANGE365_CLIENT_SECRET)
    fromEmail = service@your-domain.com
}
```

Non-empty TypoScript values override `TYPO3_CONF_VARS` **per setting**; empty values
fall back. This works in setup and in constants.

`getEnv()` reads PHP's real process environment (`getenv()`), not `$_ENV`: variables
loaded only into `$_ENV` (symfony/dotenv, helhum/dotenv-connector default) are invisible,
so set them where the web server starts PHP or use `putenv()`. A variable that is not set
leaves the previous value in place, and the result is cached until the next cache flush.
TypoScript is not sent to the browser, but it is readable in the backend and part of every
database dump. Details: [Frontend Configuration](Documentation/Configuration/Frontend.rst).

A failed send throws Symfony's `TransportException` (a `RuntimeException`), e.g.
`… Error: Exchange 365 configuration missing required field: clientSecret`. Test the
setup in *Admin Tools > Environment > Test Mail Setup*.

### Send As / Send On Behalf

An optional `graphSenderUserId` setting lets the Microsoft Graph `sendMail` call run against a **different mailbox** than the visible `From` address. This supports *Send As* and *Send On Behalf* scenarios — for example, sending through a shared mailbox while keeping a personal or no-reply address as the visible sender.

The mailbox used for the Graph API call is resolved **separately** from the message `From` address, in this order:

```
graphSenderUserId  →  message From  →  fromEmail  →  MAIL.defaultMailFromAddress
```

Configure it like the other transport settings, e.g. as an environment variable:

```bash
TYPO3_CONF_VARS__MAIL__transport_exchange365_graphSenderUserId='shared-mailbox@your-domain.com'
```

or via TypoScript for frontend email:

```typoscript
plugin.tx_okexchange365mailer.settings.exchange365.graphSenderUserId = shared-mailbox@your-domain.com
```

> The Azure application (or the sending mailbox) must be permitted to send as / on behalf of the target mailbox in Exchange 365.

## Testing

This branch is covered by the cross-version test matrix on the extension's main branch
(`make test-matrix` / `make test-matrix-live` there). For TYPO3 10 it runs unit,
functional, PHPStan and coding-standard checks, sends real mail through Microsoft Graph
from the CLI and from the frontend (credentials only via `:= getEnv()`), and checks in a
headless browser that the credentials are masked in the backend.

## Architecture

| Component | File | Role |
| --- | --- | --- |
| Mail transport | `Classes/Mail/Transport/Exchange365Transport.php` | Symfony Mailer transport; obtains an OAuth token and posts the MIME message to the Graph `sendMail` endpoint. Selected via `MAIL.transport`; `TYPO3_CONF_VARS` is the baseline, non-empty frontend TypoScript overlays it per setting |
| Credential blinding | `Classes/Hook/BlindedConfigurationOptionsHook.php`, registered in `ext_localconf.php` | Masks tenant ID, client ID and client secret from `TYPO3_CONF_VARS` in *System > Configuration* (lowlevel `ConfigurationController` hook) |
| TypoScript settings | `Configuration/TypoScript/` | Maps frontend configuration constants to plugin settings |

```
ok_exchange365_mailer/
├── Classes/
│   ├── Hook/BlindedConfigurationOptionsHook.php
│   └── Mail/Transport/Exchange365Transport.php
├── Configuration/
│   ├── Services.yaml
│   ├── TCA/Overrides/sys_template.php
│   └── TypoScript/{constants,setup}.typoscript
├── Documentation/
├── ext_emconf.php
├── ext_localconf.php
└── composer.json
```

## Documentation

Full documentation lives in the `Documentation/` directory and on the TYPO3 documentation server. Highlights:

- `Documentation/Installation.rst` — installation
- `Documentation/Azure.rst` — Microsoft Entra ID / Azure app setup
- `Documentation/Configuration/Essential.rst` — backend configuration
- `Documentation/Configuration/Frontend.rst` — optional frontend (TypoScript) overrides

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

# Exchange 365 Mailer (ok_exchange365_mailer)

[![TYPO3 10](https://img.shields.io/badge/TYPO3-10-orange?logo=typo3)](https://get.typo3.org/version/10)
[![PHP 7.2+](https://img.shields.io/badge/PHP-7.2%2B-777BB4?logo=php&logoColor=white)](https://www.php.net/)
[![License: GPL v2+](https://img.shields.io/badge/License-GPL%20v2%2B-blue)](https://www.gnu.org/licenses/old-licenses/gpl-2.0.html)
[![Version](https://img.shields.io/badge/version-2.1.1-green)](https://github.com/oliverkroener/ok_exchange365_mailer)

A TYPO3 mail transport that sends emails through **Microsoft Exchange 365 / Microsoft 365** using the **Microsoft Graph API** with **OAuth 2.0** — no SMTP required.

## Features

- **SMTP-free email delivery** — sends mail via the Microsoft Graph `sendMail` endpoint instead of SMTP.
- **OAuth 2.0 (client credentials)** — token-based authentication against Microsoft Entra ID; no mailbox passwords stored in TYPO3.
- **Backend and frontend support** — works for TYPO3 system mail (`$GLOBALS['TYPO3_CONF_VARS']['MAIL']`) and for frontend forms (Powermail, Form Framework) via TypoScript.
- **Send As / Send On Behalf** — an optional `graphSenderUserId` targets a different Graph mailbox than the visible `From` address.
- **Drop-in transport** — registers as a Symfony Mailer transport; existing `MailMessage` code keeps working unchanged.

## Requirements

| Component | Supported |
| --- | --- |
| TYPO3 | 10.4 LTS |
| PHP | 7.2 or higher |
| Other | `microsoft/microsoft-graph` `^1`, a Microsoft Entra ID app registration with `Mail.Send` application permission |

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

Set the transport and credentials in TYPO3's `MAIL` configuration (e.g. via environment variables, `config/system/settings.php`, or `LocalConfiguration.php`):

| Setting | Type | Default | Description |
| --- | --- | --- | --- |
| `transport` | string | — | Set to `OliverKroener\OkExchange365\Mail\Transport\Exchange365Transport` |
| `transport_exchange365_tenantId` | string | — | Microsoft Entra ID tenant ID |
| `transport_exchange365_clientId` | string | — | Azure application (client) ID |
| `transport_exchange365_clientSecret` | string | — | Azure application client secret **value** |
| `transport_exchange365_fromEmail` | string | `MAIL.defaultMailFromAddress` | Sender email address (must exist as a user or shared mailbox) |
| `transport_exchange365_graphSenderUserId` | string | `fromEmail` | *Optional.* Graph mailbox/user ID used for the API call (Send As / Send On Behalf) |

Example using environment variables (`.env`):

```bash
TYPO3_CONF_VARS__MAIL__transport=OliverKroener\\OkExchange365\\Mail\\Transport\\Exchange365Transport
TYPO3_CONF_VARS__MAIL__transport_exchange365_tenantId='your-tenant-id'
TYPO3_CONF_VARS__MAIL__transport_exchange365_clientId='your-client-id'
TYPO3_CONF_VARS__MAIL__transport_exchange365_clientSecret='your-client-secret'
TYPO3_CONF_VARS__MAIL__transport_exchange365_fromEmail='service@your-domain.com'
```

For **frontend** email (Powermail, Form Framework) configure the same values via TypoScript under `plugin.tx_okexchange365mailer.settings.exchange365`. See `Documentation/Configuration/Frontend.rst`.

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

## Architecture

| Component | File | Role |
| --- | --- | --- |
| Mail transport | `Classes/Mail/Transport/Exchange365Transport.php` | Symfony Mailer transport; obtains an OAuth token and posts the MIME message to the Graph `sendMail` endpoint |
| Transport registration | `ext_localconf.php` | Registers the transport as the default TYPO3 mailer |
| TypoScript settings | `Configuration/TypoScript/` | Maps frontend configuration constants to plugin settings |

```
ok_exchange365_mailer/
├── Classes/Mail/Transport/Exchange365Transport.php
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
- `Documentation/Configuration/Frontend.rst` — frontend (TypoScript) configuration

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

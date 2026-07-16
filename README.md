# Exchange 365 Mailer (`ok_exchange365_mailer`)

[![TYPO3 11](https://img.shields.io/badge/TYPO3-11-orange?logo=typo3)](https://get.typo3.org/version/11)
[![PHP 7.4+](https://img.shields.io/badge/PHP-7.4%2B-777BB4?logo=php&logoColor=white)](https://www.php.net/)
[![License: GPL v2+](https://img.shields.io/badge/License-GPL%20v2%2B-blue)](https://www.gnu.org/licenses/old-licenses/gpl-2.0.html)
[![Version](https://img.shields.io/badge/version-3.1.2-green)](https://github.com/oliverkroener/ok_exchange365_mailer)
[![Microsoft Graph](https://img.shields.io/badge/Microsoft%20Graph-API%20v2-0078D4?logo=microsoft)](https://learn.microsoft.com/en-us/graph/overview)

Send TYPO3 emails through Microsoft Exchange 365 using the Microsoft Graph API and OAuth2 — **without enabling SMTP**.

This extension registers a custom Symfony Mailer transport that delivers mail via the Microsoft Graph `sendMail` endpoint. Credentials are obtained through the OAuth2 client-credentials flow, so no SMTP username/password ever needs to be stored or transmitted.

## Features

- **SMTP-free delivery** — emails are sent directly through the Microsoft Graph API (`/users/{id}/sendMail`).
- **OAuth2 client-credentials authentication** — uses tenant ID, client ID and client secret from a Microsoft Entra ID (Azure AD) app registration; no mailbox passwords.
- **Backend and frontend support** — works for TYPO3 system mail (`$GLOBALS['TYPO3_CONF_VARS']['MAIL']`) and for frontend forms (Powermail, Form Framework) via TypoScript.
- **Send As / Send On Behalf** — an optional `graphSenderUserId` targets a different Graph mailbox than the visible `From` address.
- **Save to Sent Items** — optionally store sent messages in the sender mailbox's "Sent Items" folder.
- **Credential blinding** — client ID, tenant ID and client secret are masked in the backend *Configuration* (lowlevel) module so they are not shown in plain text (TYPO3 < 12).

## Requirements

- TYPO3 **11.5 LTS** (this branch is TYPO3 v11 only)
- PHP **7.4+** (TYPO3 11.5 LTS supports PHP 7.4.1–8.3; the `microsoft/microsoft-graph` ^2 SDK supports 7.4/8.0)
- `oliverkroener/ok-typo3-helper` ^2 (installed automatically via Composer; provides the Graph message conversion)
- A Microsoft Entra ID (Azure AD) app registration with the `Mail.Send` application permission and admin consent granted

## Installation

Install via Composer:

```bash
composer require oliverkroener/ok-exchange365-mailer
```

Then activate the extension (or rely on Composer auto-activation):

```bash
vendor/bin/typo3 extension:activate ok_exchange365_mailer
```

Alternatively, download it from the [TYPO3 Extension Repository](https://extensions.typo3.org/extension/ok_exchange365_mailer) and install it via the Extension Manager (Classic mode may require manual installation of dependencies).

## Configuration

First complete the Azure side: register an application in Microsoft Entra ID, grant it the `Mail.Send` application permission, create a client secret, and note the tenant ID, client ID and secret value. See `Documentation/Azure.rst` for the full walkthrough.

### Backend / system mail (recommended for production)

Set the transport and credentials in `$GLOBALS['TYPO3_CONF_VARS']['MAIL']`, either via environment variables or in `config/system/settings.php`.

| Setting | Type | Default | Description |
| --- | --- | --- | --- |
| `transport` | string | — | Set to `OliverKroener\OkExchange365\Mail\Transport\Exchange365Transport` |
| `transport_exchange365_tenantId` | string | — | Microsoft Entra ID tenant ID |
| `transport_exchange365_clientId` | string | — | Azure application (client) ID |
| `transport_exchange365_clientSecret` | string | — | Azure client secret **value** |
| `transport_exchange365_fromEmail` | string | `MAIL.defaultMailFromAddress` | Sender email address (must exist as a user or shared mailbox) |
| `transport_exchange365_graphSenderUserId` | string | `fromEmail` | *Optional.* Graph mailbox/user ID used for the API call (Send As / Send On Behalf). Falls back to the message `From`, then `fromEmail`, then `MAIL.defaultMailFromAddress` |
| `transport_exchange365_saveToSentItems` | bool | `0` | Save sent mail to the sender's "Sent Items" folder |

Example using environment variables (`.env`):

```bash
TYPO3_CONF_VARS__MAIL__transport=OliverKroener\\OkExchange365\\Mail\\Transport\\Exchange365Transport
TYPO3_CONF_VARS__MAIL__transport_exchange365_tenantId='your-tenant-id'
TYPO3_CONF_VARS__MAIL__transport_exchange365_clientId='your-client-id'
TYPO3_CONF_VARS__MAIL__transport_exchange365_clientSecret='your-client-secret'
TYPO3_CONF_VARS__MAIL__transport_exchange365_fromEmail='service@your-domain.com'
TYPO3_CONF_VARS__MAIL__transport_exchange365_saveToSentItems=1
```

### Frontend mail (Powermail, Form Framework)

For frontend-generated emails, include the static template **`[kroener.DIGITAL] Exchange 365 Mailer`** and configure TypoScript. The same values are also exposed as constants in the Constant Editor (category *exchange365mailer*).

```typoscript
config.mail.transport = OliverKroener\OkExchange365\Mail\Transport\Exchange365Transport

plugin.tx_okexchange365mailer.settings.exchange365 {
    tenantId = your-tenant-id
    clientId = your-client-id
    clientSecret = your-client-secret
    fromEmail = service@your-domain.com
    graphSenderUserId =
    saveToSentItems = 1
}
```

> **Security note:** TypoScript can be exposed in the frontend. Prefer the backend/environment-variable configuration for production and keep the client secret out of TypoScript wherever possible.

## Architecture

The extension is intentionally small. Configuration is resolved at send time, first from frontend TypoScript and then from the `MAIL` transport settings.

| Component | Responsibility |
| --- | --- |
| `Classes/Mail/Transport/Exchange365Transport.php` | Symfony `AbstractTransport`. Resolves credentials and the Graph sender, converts the message via `MSGraphMailApiService`, and POSTs through `GraphServiceClient`. Excluded from autowiring (TYPO3 instantiates it with the mail settings array). |
| `Classes/Hook/BlindedConfigurationOptionsHook.php` | Masks credentials in the backend Configuration module. Registered only on TYPO3 < 12. |
| `Configuration/TypoScript/` | Exposes the six settings as constants and maps them into `plugin.tx_okexchange365mailer.settings.exchange365.*` for the frontend. |
| `Configuration/TCA/Overrides/sys_template.php` | Registers the static TypoScript template. |

The sender mailbox for the Graph call (`graphSenderUserId`) is resolved **separately** from the message `From` address to support Send As / Send On Behalf scenarios. Resolution order: `graphSenderUserId` → message `From` → `fromEmail` → `MAIL.defaultMailFromAddress`.

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
├── ext_localconf.php
├── ext_emconf.php
└── composer.json
```

## Documentation

Full documentation lives in the `Documentation/` directory and on the TYPO3 documentation server. Render it locally with `make docs` (requires Docker).

## License

This extension is licensed under [GPL-2.0-or-later](https://www.gnu.org/licenses/old-licenses/gpl-2.0.html).

## Author — Oliver Kroener

### Automated. Scaled. Done.

Web3 · Cloud · Automation

Technology is only valuable when it solves a real problem. For over 30 years I've been translating between business and tech — so your investment in digitalisation doesn't stall at proof-of-concept but delivers measurable results.

- Website: [oliver-kroener.de](https://www.oliver-kroener.de)
- Web3: [web3.oliver-kroener.de](https://web3.oliver-kroener.de/)
- Email: [ok@oliver-kroener.de](mailto:ok@oliver-kroener.de)
- Web3 Email: [oliverkroener@ethermail.io](mailto:oliverkroener@ethermail.io)

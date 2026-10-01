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
- **Backend and frontend support** — works for TYPO3 system mail (`$GLOBALS['TYPO3_CONF_VARS']['MAIL']`) and for frontend forms (Powermail, Form Framework); TypoScript can override the settings per site.
- **Send As / Send On Behalf** — an optional `graphSenderUserId` targets a different Graph mailbox than the visible `From` address.
- **Save to Sent Items** — optionally store sent messages in the sender mailbox's "Sent Items" folder.
- **Credential blinding** — client ID, tenant ID and client secret from `TYPO3_CONF_VARS` are masked in *System > Configuration* (lowlevel module hook).

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

Set the transport and credentials in `$GLOBALS['TYPO3_CONF_VARS']['MAIL']`. The transport is selected **only** here — TypoScript cannot switch it.

| Setting | Type | Default | Description |
| --- | --- | --- | --- |
| `transport` | string | — | Set to `OliverKroener\OkExchange365\Mail\Transport\Exchange365Transport` |
| `transport_exchange365_tenantId` | string | — | Microsoft Entra ID tenant ID |
| `transport_exchange365_clientId` | string | — | Azure application (client) ID |
| `transport_exchange365_clientSecret` | string | — | Azure client secret **value** |
| `transport_exchange365_fromEmail` | string | `MAIL.defaultMailFromAddress` | Sender email address (must exist as a user or shared mailbox) |
| `transport_exchange365_graphSenderUserId` | string | `fromEmail` | *Optional.* Graph mailbox/user ID used for the API call (Send As / Send On Behalf). Falls back to the message `From`, then `fromEmail`, then `MAIL.defaultMailFromAddress` |
| `transport_exchange365_saveToSentItems` | bool | `0` | Save sent mail to the sender's "Sent Items" folder (the static template defaults to `1` in the frontend) |

Example using environment variables (`.env`):

```bash
TYPO3_CONF_VARS__MAIL__transport=OliverKroener\\OkExchange365\\Mail\\Transport\\Exchange365Transport
TYPO3_CONF_VARS__MAIL__transport_exchange365_tenantId='your-tenant-id'
TYPO3_CONF_VARS__MAIL__transport_exchange365_clientId='your-client-id'
TYPO3_CONF_VARS__MAIL__transport_exchange365_clientSecret='your-client-secret'
TYPO3_CONF_VARS__MAIL__transport_exchange365_fromEmail='service@your-domain.com'
TYPO3_CONF_VARS__MAIL__transport_exchange365_saveToSentItems=1
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
    # Optional: route via a different mailbox using Send As / Send On Behalf
    # graphSenderUserId = service@your-domain.com
    saveToSentItems = 1
}
```

Non-empty TypoScript values override `TYPO3_CONF_VARS` **per setting**; empty values
fall back (except `saveToSentItems`, where empty means `0`). This works in setup and
in constants.

`getEnv()` reads PHP's real process environment (`getenv()`), not `$_ENV`: variables
loaded only into `$_ENV` (symfony/dotenv, helhum/dotenv-connector default) are invisible,
so set them where the web server starts PHP or use `putenv()`. A variable that is not set
leaves the previous value in place, and the result is cached until the next cache flush.
TypoScript is not sent to the browser, but it is readable in the backend and part of every
database dump. Details: [Frontend Configuration](Documentation/Configuration/Frontend.rst).

A failed send throws Symfony's `TransportException` (a `RuntimeException`), e.g.
`… Error: Exchange 365 configuration missing required field: clientSecret`. Test the
setup in *Admin Tools > Environment > Test Mail Setup*.

## Testing

This branch is covered by the cross-version test matrix on the extension's main branch
(`make test-matrix` / `make test-matrix-live` there). For TYPO3 11 it runs unit,
functional, PHPStan and coding-standard checks, sends real mail through Microsoft Graph
from the CLI and from the frontend (credentials only via `:= getEnv()`), and checks in a
headless browser that the credentials are masked in the backend.

## Architecture

The extension is intentionally small. Configuration is resolved at send time: the `MAIL` transport settings are the baseline, and non-empty frontend TypoScript values overlay them per setting.

| Component | Responsibility |
| --- | --- |
| `Classes/Mail/Transport/Exchange365Transport.php` | Symfony `AbstractTransport`. Resolves credentials and the Graph sender, converts the message via `MSGraphMailApiService`, and POSTs through `GraphServiceClient`. Excluded from autowiring (TYPO3 instantiates it with the mail settings array). |
| `Classes/Hook/BlindedConfigurationOptionsHook.php` | Masks tenant ID, client ID and client secret from `TYPO3_CONF_VARS` in *System > Configuration* (lowlevel `ConfigurationController` hook). |
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

# Microsoft Exchange 365 Mailer (ok_exchange365_mailer)

[![TYPO3 12](https://img.shields.io/badge/TYPO3-12-orange?logo=typo3)](https://get.typo3.org/version/12)
[![TYPO3 13](https://img.shields.io/badge/TYPO3-13-orange?logo=typo3)](https://get.typo3.org/version/13)
[![TYPO3 14](https://img.shields.io/badge/TYPO3-14-orange?logo=typo3)](https://get.typo3.org/version/14)
[![PHP 8.3+](https://img.shields.io/badge/PHP-8.3%2B-777BB4?logo=php&logoColor=white)](https://www.php.net/)
[![License: GPL v2+](https://img.shields.io/badge/License-GPL%20v2%2B-blue)](https://www.gnu.org/licenses/old-licenses/gpl-2.0.html)
[![Version](https://img.shields.io/badge/version-4.1.2-green)](https://github.com/oliverkroener/ok_exchange365_mailer)

A TYPO3 extension for sending emails via Microsoft Exchange 365 using the MS Graph API instead of SMTP. Uses OAuth 2.0 client credentials flow for secure, token-based authentication.

## Features

- Send emails through Microsoft Graph API — no SMTP required
- OAuth 2.0 client credentials flow for server-to-server authentication
- Supports both backend (environment variables / TYPO3 settings) and frontend (TypoScript) configuration
- Compatible with Powermail, TYPO3 Form Framework, and other form extensions
- Optional saving of sent emails to the sender's "Sent Items" folder
- Automatic credential blinding in TYPO3's configuration module
- Works with shared mailboxes and Application Access Policies

## Requirements

- **TYPO3**: 12.4 LTS, 13.4 LTS, or 14.x
- **PHP**: 8.3+
- **Dependencies**:
  - `microsoft/microsoft-graph` ^2
  - `oliverkroener/ok-typo3-helper` ^3

## Installation

Install via Composer (recommended):

```bash
composer require oliverkroener/ok-exchange365-mailer
```

### Local path repository

If using a local path (e.g., in a monorepo), add the repository to your root `composer.json`:

```json
{
    "repositories": [
        {
            "type": "path",
            "url": "packages/ok_exchange365_mailer"
        }
    ]
}
```

Then install:

```bash
composer require oliverkroener/ok-exchange365-mailer:@dev
```

## Configuration

### 1. Register an Azure App

Register an application in Microsoft Entra ID (formerly Azure AD) with `Mail.Send` and `User.ReadBasic.All` application permissions. Grant admin consent. See the [full Azure setup guide](Documentation/Azure.rst).

### 2. Configure TYPO3

Set the mail transport to `Exchange365Transport` and provide your Azure credentials.

**Via environment variables (.env):**

| Variable | Description |
|----------|-------------|
| `TYPO3_CONF_VARS__MAIL__transport` | `OliverKroener\OkExchange365\Mail\Transport\Exchange365Transport` |
| `TYPO3_CONF_VARS__MAIL__transport_exchange365_tenantId` | Microsoft Entra ID Tenant ID |
| `TYPO3_CONF_VARS__MAIL__transport_exchange365_clientId` | Azure Application (Client) ID |
| `TYPO3_CONF_VARS__MAIL__transport_exchange365_clientSecret` | Azure Application Secret Value |
| `TYPO3_CONF_VARS__MAIL__transport_exchange365_fromEmail` | Sender email address (must exist in Exchange 365) |
| `TYPO3_CONF_VARS__MAIL__transport_exchange365_saveToSentItems` | `1` to save to Sent Items, `0` to skip (default: `0`) |

**Via TypoScript (for frontend forms):**

```typoscript
plugin.tx_okexchange365mailer.settings.exchange365 {
    tenantId = your-tenant-id
    clientId = your-client-id
    clientSecret = your-client-secret
    fromEmail = service@your-domain.com
    saveToSentItems = 1
}
```

### Sender Display Name

The Graph API uses the **Display name** configured on the mailbox in Exchange Online. TYPO3's `defaultMailFromName` has no effect. Configure the display name in the Microsoft 365 Admin Center or Exchange Admin Center.

## Architecture

| Component | Description |
|-----------|-------------|
| `Exchange365Transport` | Custom Symfony mailer transport; handles OAuth2 auth and sends via Graph API |
| `ModifyBlindedConfigurationOptionsEventListener` | PSR-14 event listener; blinds credentials in TYPO3 configuration module |
| `MSGraphMailApiService` (from ok-typo3-helper) | Converts Symfony email messages to Microsoft Graph format |

```
Classes/
├── Mail/Transport/
│   └── Exchange365Transport.php
└── Lowlevel/EventListener/
    └── ModifyBlindedConfigurationOptionsEventListener.php
Configuration/
├── Services.yaml
├── TCA/Overrides/sys_template.php
└── TypoScript/
    ├── constants.typoscript
    └── setup.typoscript
```

## License

GPL-2.0-or-later

## Author — Oliver Kroener

### Automated. Scaled. Done.

Web3 · Cloud · Automation

Technology is only valuable when it solves a real problem. For over 30 years I've been translating between business and tech — so your investment in digitalisation doesn't stall at proof-of-concept but delivers measurable results.

- Website: [oliver-kroener.de](https://www.oliver-kroener.de)
- Web3: [web3.oliver-kroener.de](https://web3.oliver-kroener.de/)
- Email: [ok@oliver-kroener.de](mailto:ok@oliver-kroener.de)
- Web3 Email: [oliverkroener@ethermail.io](mailto:oliverkroener@ethermail.io)

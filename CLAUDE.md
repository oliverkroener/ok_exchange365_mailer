# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Extension Overview

TYPO3 extension (`ok_exchange365_mailer`) that enables sending emails via Microsoft Exchange 365 using the MS Graph API instead of SMTP. Uses OAuth 2.0 client credentials flow for authentication.

**Compatibility:** TYPO3 12.4 - 14.x | PHP 8.3

## Architecture

### Core Components

- `Classes/Mail/Transport/Exchange365Transport.php` - Custom Symfony mailer transport implementing `AbstractTransport`. Handles OAuth2 authentication and sends emails via MS Graph API. Note: Excluded from autowiring in Services.yaml as TYPO3 instantiates it directly.

- `Classes/Lowlevel/EventListener/ModifyBlindedConfigurationOptionsEventListener.php` - Event listener that blinds sensitive credentials (tenantId, clientId, clientSecret) in TYPO3's configuration module.

### Configuration Sources (Priority Order)

1. **TypoScript** (frontend context only): `plugin.tx_okexchange365mailer.settings.exchange365.*`
2. **TYPO3 Mail Settings**: `$GLOBALS['TYPO3_CONF_VARS']['MAIL']['transport_exchange365_*']`

### Required Settings

| Setting | TypoScript Key | Mail Setting Key |
|---------|---------------|------------------|
| Tenant ID | `tenantId` | `transport_exchange365_tenantId` |
| Client ID | `clientId` | `transport_exchange365_clientId` |
| Client Secret | `clientSecret` | `transport_exchange365_clientSecret` |
| From Email | `fromEmail` | `transport_exchange365_fromEmail` |
| Save to Sent | `saveToSentItems` | `transport_exchange365_saveToSentItems` |

## Dependencies

- `microsoft/microsoft-graph` ^2 - MS Graph API SDK
- `oliverkroener/ok-typo3-helper` ^4 - Provides `MSGraphMailApiService` for message conversion

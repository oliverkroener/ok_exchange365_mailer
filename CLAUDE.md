# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Extension Overview

TYPO3 extension (`ok_exchange365_mailer`) that enables sending emails via Microsoft Exchange 365 using the MS Graph API instead of SMTP. Uses OAuth 2.0 client credentials flow for authentication.

**Compatibility:** TYPO3 12.4 LTS – 14.x | PHP 8.1 – 8.5

## Architecture

### Core Components

- `Classes/Mail/Transport/Exchange365Transport.php` - Custom Symfony mailer transport implementing `AbstractTransport`. Handles OAuth2 authentication and sends emails via MS Graph API. Note: Excluded from autowiring in Services.yaml as TYPO3 instantiates it directly.

### How the transport is selected

There is no DSN factory. TYPO3 activates this transport when `$GLOBALS['TYPO3_CONF_VARS']['MAIL']['transport']` is set to the **fully-qualified class name** `OliverKroener\OkExchange365\Mail\Transport\Exchange365Transport` (via env var `TYPO3_CONF_VARS__MAIL__transport`, settings.php, or `config.mail.transport` in frontend TypoScript). TYPO3's mailer instantiates the class directly, passing `$GLOBALS['TYPO3_CONF_VARS']['MAIL']` as the `$mailSettings` constructor argument — which is exactly why the class is excluded from the Services.yaml autoloading resource. The `__toString()` return value `exchange365api` is only the transport's display name.

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
| Graph Sender User ID (optional) | `graphSenderUserId` | `transport_exchange365_graphSenderUserId` |
| Save to Sent | `saveToSentItems` | `transport_exchange365_saveToSentItems` |

## Important Behavior

- **Sender display name**: The Graph API uses the **Display name** configured on the mailbox in Exchange Online. TYPO3's `$GLOBALS['TYPO3_CONF_VARS']['MAIL']['defaultMailFromName']` and `defaultMailFromAddress` have **no effect** on the sender name shown to recipients. The display name must be configured in the Microsoft 365 Admin Center or Exchange Admin Center.

- **Send As / Send On Behalf**: The optional `graphSenderUserId` decouples the Graph mailbox (path parameter of `/users/{id}/sendMail`) from the visible message `From` address. When set, that mailbox is used for the Graph call; the message `From` header (and the `fromEmail` / `defaultMailFromAddress` fallback) is unaffected. Use this when the configured mailbox has *Send As* or *Send On Behalf* permission on another mailbox in Exchange. When unset, the resolution chain is unchanged: `$graphMessage['from']` → `fromEmail` → `defaultMailFromAddress`. See <https://learn.microsoft.com/en-us/graph/outlook-send-mail-from-other-user>.

## Dependencies

- `microsoft/microsoft-graph` ^2 - MS Graph API SDK
- `oliverkroener/ok-typo3-helper` ^3 - Provides `MSGraphMailApiService` for message conversion (`convertToGraphMessage()`, used in `doSend()`)

## Development Commands

All PHP tooling runs **through DDEV from the parent project root** (`/home/oliver/typo3-14`), where `phpstan/phpstan`, `saschaegerer/phpstan-typo3`, and `typo3/coding-standards` are the shared dev dependencies — they are not installed inside this package.

```bash
# Static analysis — config lives at packages/ok_exchange365_mailer/phpstan.neon (level 8, TYPO3 extension auto-registered)
ddev exec vendor/bin/phpstan analyse -c packages/ok_exchange365_mailer/phpstan.neon

# Code style — TYPO3 CGL via the root .php-cs-fixer.dist.php; scope to this package with a path argument
ddev exec vendor/bin/php-cs-fixer fix packages/ok_exchange365_mailer
ddev exec vendor/bin/php-cs-fixer fix packages/ok_exchange365_mailer --dry-run --diff   # check only
```

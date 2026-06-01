# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

`ok_exchange365_mailer` is a TYPO3 v11 extension (extension key `ok_exchange365_mailer`,
composer package `oliverkroener/ok-exchange365-mailer`). It registers a custom Symfony
Mailer transport that sends mail through the **Microsoft Graph API** (`/users/{id}/sendMail`)
using OAuth2 client-credentials, instead of SMTP. This branch (`feature-typo3-11`) targets
TYPO3 11.5 and PHP via `microsoft/microsoft-graph: ^2`.

## Commands

There is no test suite, linter, or composer build step configured. The only tooling is
documentation rendering (requires Docker):

```bash
make docs        # render Documentation/ to Documentation-GENERATED-temp via the TYPO3 render-guides Docker image
make docs-fast   # same, without pulling a fresh image
make help        # list make targets
```

Version lives in **both** `composer.json` and `ext_emconf.php` — keep them in sync when bumping
(the `/typo3-bump-version` skill does both).

## Architecture

The extension is intentionally tiny — three moving parts:

1. **`Classes/Mail/Transport/Exchange365Transport.php`** — the core. Extends Symfony's
   `AbstractTransport`. `doSend()` resolves credentials, converts the Symfony message to Graph
   format via `MSGraphMailApiService::convertToGraphMessage()` (from the
   `oliverkroener/ok-typo3-helper` dependency), and POSTs through `GraphServiceClient`.
   - **Config resolution order in `doSend()`:** it first tries frontend TypoScript
     (`$GLOBALS['TSFE']->tmpl->setup['plugin.']['tx_okexchange365mailer.']['settings.']['exchange365.']`),
     and falls back to the `MAIL` transport settings array (keys `transport_exchange365_*`).
   - **`graphSenderUserId` vs `fromEmail`:** the Graph mailbox the API call targets is resolved
     *separately* from the message From address, to support Send As / Send On Behalf. Resolution:
     `graphSenderUserId` → message From → `fromEmail` → `MAIL.defaultMailFromAddress`. Empty
     strings count as unset (uses `!empty()`, not `??`).
   - It is **excluded from autowiring** in `Configuration/Services.yaml` because TYPO3
     instantiates transports itself (passing the `$mailSettings` array), not the DI container.
   - Contains a TYPO3-11-only shim: it wraps the event dispatcher in
     `EventDispatcherAdapter` (marked `TODO remove if support for TYPO3 11 dropped`).

2. **`Classes/Hook/BlindedConfigurationOptionsHook.php`** — masks the client ID / tenant ID /
   secret in the backend *Configuration* lowlevel module so credentials aren't shown in plain
   text. Registered in `ext_localconf.php` **only when TYPO3 major version < 12** (the hook API
   changed in v12).

3. **TypoScript config** (`Configuration/TypoScript/`) — `constants.typoscript` exposes the
   six settings (tenantId, clientId, clientSecret, fromEmail, graphSenderUserId,
   saveToSentItems) in the constant editor; `setup.typoscript` maps them into
   `plugin.tx_okexchange365mailer.settings.exchange365.*`. This is the *frontend* config path
   read by `doSend()`. Registered as a static template via `Configuration/TCA/Overrides/sys_template.php`.

### Two ways the extension is configured (important)

- **Global / backend mail** (most setups): `$GLOBALS['TYPO3_CONF_VARS']['MAIL']` keys —
  `transport` set to the transport FQCN plus `transport_exchange365_tenantId`, `_clientId`,
  `_clientSecret`, `_fromEmail`, `_graphSenderUserId`, `_saveToSentItems`. Can be set via
  `TYPO3_CONF_VARS__MAIL__...` env vars or in `config/system/settings.php`.
- **Frontend** (e.g. Powermail): `config.mail.transport = ...\Exchange365Transport` plus the
  TypoScript `plugin.tx_okexchange365mailer.settings.exchange365.*` constants.

When changing config keys, update **all** of: `constants.typoscript`, `setup.typoscript`, the
fallback reads in `Exchange365Transport::doSend()`, and `Documentation/Configuration/*.rst`.

## Conventions

- PHP namespace is `OliverKroener\OkExchange365\` → `Classes/` (PSR-4). Note the namespace omits
  the `Mailer` suffix that's in the package/extension name.
- The `oliverkroener/ok-typo3-helper` dependency (namespace `OliverKroener\Helpers\`) holds the
  Graph message-conversion logic; behaviour changes to message formatting may live there, not here.

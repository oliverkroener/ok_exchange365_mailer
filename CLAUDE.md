# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

`ok_exchange365_mailer` is a TYPO3 v11 extension (extension key `ok_exchange365_mailer`,
composer package `oliverkroener/ok-exchange365-mailer`). It registers a custom Symfony
Mailer transport that sends mail through the **Microsoft Graph API** (`/users/{id}/sendMail`)
using OAuth2 client-credentials, instead of SMTP. This branch (`feature-typo3-11`) targets
TYPO3 11.5 and PHP via `microsoft/microsoft-graph: ^2`.

## Commands

Tests live in `Tests/Unit` and `Tests/Functional` (configs in `Build/phpunit/`, PHPStan and
php-cs-fixer configs in `Build/`). They are run by the cross-version **test matrix on the
`main` branch** (`make test-matrix` / `make test-matrix-live` there), which mounts this branch
as a git worktree into a TYPO3 11.5 DDEV lab and also performs real Microsoft Graph sends.
Test methods use plain `testFoo()` names — no attributes or data providers.

Documentation rendering (requires Docker):

```bash
make docs        # render Documentation/ to Documentation-GENERATED-temp via the TYPO3 render-guides Docker image
make docs-fast   # same, without pulling a fresh image
make help        # list make targets
```

Version lives in `composer.json`, `ext_emconf.php`, the README badge and
`Documentation/guides.xml` — keep them in sync when bumping. Tags are pushed through the release
gate on `main` (`make install-hooks`), which refuses a tag unless the matrix is green.

## Architecture

The extension is intentionally tiny — three moving parts:

1. **`Classes/Mail/Transport/Exchange365Transport.php`** — the core. Extends Symfony's
   `AbstractTransport`. `doSend()` resolves credentials, converts the Symfony message to Graph
   format via `MSGraphMailApiService::convertToGraphMessage()` (from the
   `oliverkroener/ok-typo3-helper` dependency), and POSTs through `GraphServiceClient`.
   - **Config resolution (`getConfiguration()`):** the `MAIL` transport settings (keys
     `transport_exchange365_*`) are the baseline in every context. Frontend TypoScript
     (`$GLOBALS['TSFE']->tmpl->setup['plugin.']['tx_okexchange365mailer.']['settings.']['exchange365.']`)
     overlays them **per key**; an empty TypoScript value falls back (except `saveToSentItems`).
     `validateConfiguration()` requires tenantId, clientId and clientSecret.
   - **`graphSenderUserId` vs `fromEmail`:** the Graph mailbox the API call targets is resolved
     *separately* from the message From address, to support Send As / Send On Behalf. Resolution:
     `graphSenderUserId` → message From → `fromEmail` → `MAIL.defaultMailFromAddress`, in
     `resolveGraphSenderUserId()`. Empty strings count as unset at every step.
   - It is **excluded from autowiring** in `Configuration/Services.yaml` because TYPO3
     instantiates transports itself (passing the `$mailSettings` array), not the DI container.
   - **Graph client:** `getGraphServiceClient()` keeps one client (and its OAuth token) per
     credential set. `createGraphServiceClient()` (protected — the test seam) sets 10 s connect /
     30 s total timeouts on the Graph call *and* the OAuth token request; the latter needs an
     injected `httpClient`, because the OAuth library otherwise uses no timeout at all. A request
     that stalled after connecting is deliberately not retried (duplicate mails).
   - **Errors:** `doSend()` catches `\Throwable`, logs at `error`, rethrows Symfony
     `TransportException` (a `\RuntimeException`) with the original as `previous`.
   - TYPO3-11 specific: Symfony Mailer 5 needs a Symfony dispatcher, so a passed PSR-14
     dispatcher is wrapped in `EventDispatcherAdapter`; without one the adapter comes from
     `GeneralUtility::makeInstance()`.

2. **`Classes/Hook/BlindedConfigurationOptionsHook.php`** — masks the client ID / tenant ID /
   secret in the backend *Configuration* lowlevel module so credentials aren't shown in plain
   text. Registered in `ext_localconf.php` **only when TYPO3 major version < 12** (the hook API
   changed in v12). Covers `TYPO3_CONF_VARS` only.

3. **TypoScript config** (`Configuration/TypoScript/`) — `constants.typoscript` exposes the
   six settings (tenantId, clientId, clientSecret, fromEmail, graphSenderUserId,
   saveToSentItems) in the constant editor; `setup.typoscript` maps them into
   `plugin.tx_okexchange365mailer.settings.exchange365.*`. This is the *frontend* overlay. The
   credential constants default to **empty** on purpose (only `saveToSentItems` defaults to 1): a non-empty default would override
   `TYPO3_CONF_VARS` in the frontend. Registered as a static template via `Configuration/TCA/Overrides/sys_template.php`.

### Two ways the extension is configured (important)

- **Global / backend mail** (most setups): `$GLOBALS['TYPO3_CONF_VARS']['MAIL']` keys —
  `transport` set to the transport FQCN plus `transport_exchange365_tenantId`, `_clientId`,
  `_clientSecret`, `_fromEmail`, `_graphSenderUserId`, `_saveToSentItems`. Can be set via
  `TYPO3_CONF_VARS__MAIL__...` env vars (the project must map those itself — TYPO3 does not)
  or in `public/typo3conf/AdditionalConfiguration.php`.
- **Frontend overrides** (optional, e.g. per site): the TypoScript
  `plugin.tx_okexchange365mailer.settings.exchange365.*` values, ideally `:= getEnv(VAR)`.
  The transport itself can only be selected in `TYPO3_CONF_VARS` — there is no
  `config.mail.transport` in TYPO3.

When changing config keys, update **all** of: `constants.typoscript`, `setup.typoscript`,
`Exchange365Transport::getMailSettingsConfiguration()`, and `Documentation/Configuration/*.rst`.

## Conventions

- PHP namespace is `OliverKroener\OkExchange365\` → `Classes/` (PSR-4). Note the namespace omits
  the `Mailer` suffix that's in the package/extension name.
- The `oliverkroener/ok-typo3-helper` dependency (namespace `OliverKroener\Helpers\`) holds the
  Graph message-conversion logic; behaviour changes to message formatting may live there, not here.

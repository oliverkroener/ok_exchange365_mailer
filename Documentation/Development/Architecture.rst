:navigation-title: Architecture

..  include:: /Includes.rst.txt

..  _architecture:

==========================
Architecture
==========================

The extension is small — two PHP classes — but the way configuration reaches the
transport is subtle, and there are two parallel frontend configuration paths. This
page documents both.

..  _architecture-components:

Components
==========

..  list-table::
    :header-rows: 1
    :widths: 36 64

    *   -   Class
        -   Responsibility

    *   -   :php:`Mail\Transport\Exchange365Transport`
        -   Symfony :php:`AbstractTransport` implementation. Resolves configuration,
            authenticates, converts the message and calls Microsoft Graph. Keeps one
            Graph client per credential set — see :ref:`architecture-graph-client`.
    *   -   :php:`Lowlevel\EventListener\ModifyBlindedConfigurationOptionsEventListener`
        -   Blinds the tenant ID, client ID and client secret in the backend
            :guilabel:`Configuration` module, so the values render as ``ab******yz``.
            Covers both ``$GLOBALS['TYPO3_CONF_VARS']['MAIL']`` (provider
            ``confVars``) and the same settings in a site's configuration (provider
            ``sitesYamlConfiguration``, nested or dotted keys).

Message conversion itself lives in
:composer:`oliverkroener/ok-typo3-helper`, whose
:php:`MSGraphMailApiService::convertToGraphMessage()` turns the Symfony
:php:`SentMessage` into a Graph message. Bugs in attachment, inline-image or
recipient handling usually belong in that package rather than here.

..  _architecture-transport-selection:

How the transport is selected
=============================

There is **no DSN factory**. TYPO3 activates this transport when
:php:`$GLOBALS['TYPO3_CONF_VARS']['MAIL']['transport']` is the
**fully-qualified class name**:

..  code-block:: text

    OliverKroener\OkExchange365\Mail\Transport\Exchange365Transport

TYPO3's mailer then instantiates the class directly, passing the whole
:php:`$GLOBALS['TYPO3_CONF_VARS']['MAIL']` array as the ``$mailSettings``
constructor argument.

..  important::
    Because TYPO3 constructs the class itself, it must **not** be handled by the
    Symfony DI container. It is therefore excluded from the autoloading resource in
    :file:`Configuration/Services.yaml`. Do not add it back to autowiring.

The :php:`__toString()` return value ``exchange365api`` is only a display name; it
plays no part in transport selection.

..  _architecture-configuration-resolution:

Configuration resolution
========================

:php:`getConfiguration()` builds the effective configuration from two sources:

#.  **Mail settings are the baseline.** The
    ``$GLOBALS['TYPO3_CONF_VARS']['MAIL']['transport_exchange365_*']`` values,
    normalised into short keys (``tenantId``, ``clientId``, …) by
    :php:`getMailSettingsConfiguration()`. This is the only source available in
    backend, CLI and scheduler contexts.

#.  **Frontend TypoScript overlays it per key.** The values below
    ``plugin.tx_okexchange365mailer.settings.exchange365``, read only when
    :php:`$GLOBALS['TYPO3_REQUEST']` reports ``applicationType === 1``. Site-set
    settings arrive through this same path, because site settings are flattened
    into TypoScript constants.

Two rules govern the overlay, and both are deliberate:

..  code-block:: php
    :caption: Classes/Mail/Transport/Exchange365Transport.php

    if ($key === 'saveToSentItems' || ($value !== '' && $value !== null)) {
        $conf[$key] = $value;
    }

*   An **empty** TypoScript value means *"not configured here"* and must not shadow
    a value that is set in the mail settings. This is why the code uses an explicit
    guard rather than a plain :php:`??` or an array merge.
*   :php:`saveToSentItems` is **exempt** from that guard. A site setting of
    ``false`` flattens to an empty constant, and there it genuinely means *false*.

:php:`getTypoScriptConfiguration()` reads the ``frontend.typoscript`` request
attribute unconditionally. On TYPO3 12.4.0 — the one supported version that predates
that attribute — it is absent, the method returns :php:`null`, and the mail-settings
baseline stands.

:php:`validateConfiguration()` hard-requires ``tenantId``, ``clientId`` and
``clientSecret``. Every other setting has a fallback.

..  _architecture-sender-resolution:

Sender resolution
=================

``graphSenderUserId`` — the mailbox in the ``/users/{id}/sendMail`` path — is
deliberately decoupled from the message ``From`` header, so that *Send As* and
*Send On Behalf* work. It resolves in this order:

..  code-block:: text

    conf.graphSenderUserId
      → $graphMessage['from']
        → conf.fromEmail
          → MAIL.defaultMailFromAddress
            → RuntimeException

:php:`resolveGraphSenderUserId()` walks these candidates and returns the first one
that is a non-empty scalar. An empty string counts as "unset" at **every** step —
including an empty message ``From`` — because
:php:`getMailSettingsConfiguration()` returns empty strings, not nulls, for unset
values.

..  note::
    The sender **display name** comes from the mailbox in Exchange Online, not from
    TYPO3. :typoscript:`defaultMailFromName` has no effect on what recipients see.
    See :ref:`Configuring the Sender Display Name <sender-display-name>`.

..  _architecture-frontend-paths:

Two mutually exclusive frontend paths
=====================================

..  list-table::
    :header-rows: 1
    :widths: 40 14 46

    *   -   Path
        -   TYPO3
        -   Files

    *   -   Site set ``oliverkroener/ok-exchange365-mailer`` (preferred, since 4.3.0)
        -   13 / 14
        -   :file:`Configuration/Sets/Exchange365Mailer/config.yaml`,
            :file:`settings.definitions.yaml`, :file:`setup.typoscript`
    *   -   Static template *[kroener.DIGITAL] Exchange 365 Mailer*
        -   12
        -   :file:`Configuration/TypoScript/constants.typoscript`,
            :file:`setup.typoscript`, registered in
            :file:`Configuration/TCA/Overrides/sys_template.php`

The set's :file:`setup.typoscript` is a one-line ``@import`` of the static template's
:file:`setup.typoscript`. The site-setting keys are named identically to the
TypoScript constants, so the mapping file is reused verbatim.

..  warning::
    Use the set **or** the static template, never both. Site settings are applied to
    constants *before* template records, so a lingering static template overwrites the
    set's values with its own empty defaults.

When adding or renaming a setting, change all four places:
:file:`constants.typoscript`, :file:`setup.typoscript`,
:file:`settings.definitions.yaml`, and :php:`getMailSettingsConfiguration()`.

..  _architecture-graph-client:

Graph client, timeouts and retries
==================================

:php:`getGraphServiceClient()` keeps **one** :php:`GraphServiceClient` per
credential set (keyed by a hash of tenant ID, client ID and client secret). The
client holds its OAuth token in memory, so a request that sends several mails — a
form with a receiver and a confirmation mail, a scheduler run — authenticates once.
Different credentials, for example a frontend site with its own app registration,
get their own client.

:php:`createGraphServiceClient()` builds the client the same way the SDK does by
default, with two deliberate differences:

*   **The Graph call** uses a 10 s connect and 30 s total timeout instead of the
    SDK's 30 s / 100 s, so a stalled connection cannot hold a frontend request for
    minutes.
*   **The OAuth token request** gets the same timeouts through an injected HTTP
    client. The OAuth library the SDK uses otherwise sends it with **no timeout at
    all** — a stalled token request would block a CLI or scheduler run forever.

Throttling (429) and 503/504 responses are retried by the SDK's own middleware,
honouring ``Retry-After``. A request that **stalled after connecting is not
retried**: Graph may already have accepted the message, and a retry could send it
twice.

:php:`createGraphServiceClient()` is :php:`protected` and is the one seam the unit
tests use to count or replace client creation.

..  _architecture-errors:

Error handling
==============

:php:`doSend()` catches every :php:`\Throwable`, logs at ``error`` level, and
rethrows a Symfony :php:`TransportException` with the original as ``previous``.
:php:`TransportException` extends :php:`\RuntimeException`, so existing
``catch (\RuntimeException)`` blocks keep working. Graph's original message is
appended to the exception text — it is the only diagnostic an integrator gets, so
keep it when editing. The client secret and the token never appear in it.

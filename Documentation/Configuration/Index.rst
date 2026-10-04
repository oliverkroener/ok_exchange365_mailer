..  include:: /Includes.rst.txt

..  _configuration:

=============
Configuration
=============

After completing the :ref:`Microsoft Entra ID setup <azure>`, configure TYPO3
with the values obtained there.

..  toctree::
    :titlesonly:

    Frontend

..  _configuration-variables:

Settings
========

The extension reads its settings from ``$GLOBALS['TYPO3_CONF_VARS']['MAIL']``.
These settings are used everywhere — backend, CLI, scheduler and, unless
TypoScript overrides them, the :ref:`frontend <frontend>`. The transport is
selected **only** in ``$GLOBALS['TYPO3_CONF_VARS']['MAIL']['transport']``;
there is no TypoScript option for it.

..  list-table::
    :header-rows: 1
    :widths: 40 60

    *   -   ``$GLOBALS['TYPO3_CONF_VARS']['MAIL'][…]``
        -   Meaning
    *   -   ``transport``
        -   ``OliverKroener\OkExchange365\Mail\Transport\Exchange365Transport``
    *   -   ``transport_exchange365_tenantId``
        -   Microsoft Entra ID tenant ID. Required.
    *   -   ``transport_exchange365_clientId``
        -   Application (client) ID of the app registration. Required.
    *   -   ``transport_exchange365_clientSecret``
        -   The client secret **Value**. Required.
    *   -   ``transport_exchange365_fromEmail``
        -   Sender mailbox (user or shared mailbox). Falls back to
            ``MAIL.defaultMailFromAddress``.
    *   -   ``transport_exchange365_graphSenderUserId``
        -   Optional. The mailbox the Graph call ``/users/{id}/sendMail`` is
            made through, when it differs from the visible From address
            (*Send As* / *Send On Behalf*). Falls back to the message From
            address, then ``fromEmail``, then ``MAIL.defaultMailFromAddress``.
            See `Send mail from another user
            <https://learn.microsoft.com/en-us/graph/outlook-send-mail-from-other-user>`__.
    *   -   ``transport_exchange365_saveToSentItems``
        -   ``1`` saves a copy in *Sent Items*, ``0`` does not. Default: ``0``.

..  warning::

    Never write the client secret as a literal value into
    :file:`LocalConfiguration.php` or :file:`AdditionalConfiguration.php` —
    TYPO3 rewrites :file:`LocalConfiguration.php` when settings change in the
    backend, and both files typically end up in version control and backups.
    Read the values from the environment instead, with one of the two options
    below.

..  _env-mapping:

Option A: environment variables named ``TYPO3_CONF_VARS__…``
============================================================

..  code-block:: bash
    :caption: .env

    TYPO3_CONF_VARS__MAIL__transport=OliverKroener\\OkExchange365\\Mail\\Transport\\Exchange365Transport
    TYPO3_CONF_VARS__MAIL__transport_exchange365_tenantId='your-tenant-id'
    TYPO3_CONF_VARS__MAIL__transport_exchange365_clientId='your-client-id'
    TYPO3_CONF_VARS__MAIL__transport_exchange365_clientSecret='your-client-secret'
    TYPO3_CONF_VARS__MAIL__transport_exchange365_fromEmail='service@your-domain.com'
    # Optional: Send As / Send On Behalf
    #TYPO3_CONF_VARS__MAIL__transport_exchange365_graphSenderUserId='shared-mailbox@your-domain.com'
    TYPO3_CONF_VARS__MAIL__transport_exchange365_saveToSentItems=1

..  important::

    **TYPO3 does not read** ``TYPO3_CONF_VARS__…`` **variables by itself.**
    The double-underscore naming is a widespread convention, but the mapping
    onto ``$GLOBALS['TYPO3_CONF_VARS']`` has to be done by your project. If
    your project has no such mapping, add the loop below.

Double underscores become array levels, so
``TYPO3_CONF_VARS__MAIL__transport_exchange365_clientSecret`` ends up in
``$GLOBALS['TYPO3_CONF_VARS']['MAIL']['transport_exchange365_clientSecret']``.

..  code-block:: php
    :caption: public/typo3conf/AdditionalConfiguration.php

    <?php

    // Variables loaded by a dotenv library live in $_ENV, variables set by the
    // web server or container in getenv(). Read both.
    foreach (array_merge(getenv(), $_ENV) as $name => $value) {
        if (!is_string($name) || strpos($name, 'TYPO3_CONF_VARS__') !== 0) {
            continue;
        }
        $target = &$GLOBALS['TYPO3_CONF_VARS'];
        foreach (explode('__', substr($name, strlen('TYPO3_CONF_VARS__'))) as $segment) {
            $target = &$target[$segment];
        }
        $target = $value;
        unset($target);
    }

..  _config-files-from-env:

Option B: read the environment in AdditionalConfiguration.php
=============================================================

Without the mapping, set the values in
:file:`public/typo3conf/AdditionalConfiguration.php` and read the secrets
from the environment there. Use your own variable names:

..  code-block:: php
    :caption: public/typo3conf/AdditionalConfiguration.php

    <?php

    $env = static function (string $name): string {
        return (string)(getenv($name) ?: ($_ENV[$name] ?? ''));
    };

    $GLOBALS['TYPO3_CONF_VARS']['MAIL']['transport'] = \OliverKroener\OkExchange365\Mail\Transport\Exchange365Transport::class;
    $GLOBALS['TYPO3_CONF_VARS']['MAIL']['transport_exchange365_tenantId'] = $env('EXCHANGE365_TENANT_ID');
    $GLOBALS['TYPO3_CONF_VARS']['MAIL']['transport_exchange365_clientId'] = $env('EXCHANGE365_CLIENT_ID');
    $GLOBALS['TYPO3_CONF_VARS']['MAIL']['transport_exchange365_clientSecret'] = $env('EXCHANGE365_CLIENT_SECRET');
    $GLOBALS['TYPO3_CONF_VARS']['MAIL']['transport_exchange365_fromEmail'] = 'service@your-domain.com';
    // Optional: distinct Graph sender mailbox (Send As / Send On Behalf).
    // $GLOBALS['TYPO3_CONF_VARS']['MAIL']['transport_exchange365_graphSenderUserId'] = 'account1@your-domain.com';
    $GLOBALS['TYPO3_CONF_VARS']['MAIL']['transport_exchange365_saveToSentItems'] = 1;

..  note::

    In the frontend, the same values can also come from TypoScript, where
    ``:= getEnv(...)`` reads them from the environment. See
    :ref:`frontend-getenv`.

..  _configuration-usage:

Usage
=====

Once configured, the extension handles email sending via Exchange 365
automatically. All emails sent by TYPO3 (system emails, form notifications,
etc.) use the Microsoft Graph API.

..  _testing-the-configuration:

Testing the configuration
=========================

To verify that emails are being sent correctly, open
:guilabel:`Admin Tools > Environment > Test Mail Setup` and send a test mail.
Check that it arrives, and — with ``saveToSentItems`` enabled — that a copy
appears in the sender mailbox's *Sent Items*.

A failed send throws a ``RuntimeException`` whose message names the cause,
for example
``… Error: Exchange 365 configuration missing required field: clientSecret``.
The same message is written to the TYPO3 log. See :ref:`faq` for the common
causes.

..  _security-considerations:

Security
========

In :guilabel:`System > Configuration`, the extension masks the tenant ID,
client ID and client secret held in ``TYPO3_CONF_VARS`` (via a hook of the
lowlevel configuration module): only the first two and the last two
characters stay visible. Values set in TypoScript are not masked.

-   Keep the client secret in the environment, never in files under version
    control.
-   Renew the client secret before it expires.
-   Use separate app registrations for development, staging and production.

..  _testing:

Automated tests
===============

This branch is covered by the cross-version test matrix that lives on the
extension's main branch (``make test-matrix`` / ``make test-matrix-live``
there). For TYPO3 9.5 it runs the unit and functional tests, PHPStan and
the coding standard check, sends real mail through Microsoft Graph from the
CLI and from the frontend (credentials via ``:= getEnv()``), and checks in
a headless browser that the credentials are masked in the backend.

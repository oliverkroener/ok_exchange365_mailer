:navigation-title: Frontend

..  _frontend:

======================
Frontend Configuration
======================

Mails sent from the frontend — by the **Form Framework**, **Powermail** or any other
extension that uses TYPO3's mailer — go through the same transport as backend and CLI
mails. In the frontend, the transport additionally reads TypoScript, so a site can use
its own credentials or sender.

..  note::
    **On TYPO3 v13 and v14, use the** :ref:`site set <sitesets>` **instead.** It provides
    the same settings under the same names, but activated per site and editable in the
    backend. This page describes the static TypoScript template, which remains the only
    option on TYPO3 v12. Do not use both at the same time — see :ref:`sitesets` for
    details.

How frontend configuration works
================================

The transport itself is always selected in :php:`$GLOBALS['TYPO3_CONF_VARS']['MAIL']['transport']`
(see :ref:`essential`). TypoScript cannot switch the transport.

For the credentials and the sender, TypoScript **overlays** the
``$GLOBALS['TYPO3_CONF_VARS']['MAIL']['transport_exchange365_*']`` settings **per
parameter**:

*   A parameter with a value in TypoScript wins.
*   A parameter that is empty in TypoScript falls back to ``TYPO3_CONF_VARS``.

So if the credentials are already configured for the backend, the frontend needs no
TypoScript at all. Add TypoScript only where a site should differ.

..  figure:: /_Images/image-frontend.png
    :alt: TYPO3 TypoScript configuration showing Exchange365 frontend parameters
    :class: with-shadow
    :scale: 100

..  _frontend-getenv:

Recommended: read the credentials from the environment
======================================================

Never write the tenant ID, client ID or client secret into TypoScript. TypoScript is
stored in the database or in a site package, both of which end up in backups,
exports and version control. Read them from environment variables with the
TypoScript function ``getEnv()`` instead:

..  code-block:: typoscript
    :caption: setup.typoscript

    plugin.tx_okexchange365mailer.settings.exchange365 {
        tenantId := getEnv(EXCHANGE365_TENANT_ID)
        clientId := getEnv(EXCHANGE365_CLIENT_ID)
        clientSecret := getEnv(EXCHANGE365_CLIENT_SECRET)
        fromEmail := getEnv(EXCHANGE365_FROM_EMAIL)
    }

The same works for constants, if you prefer to set them in
:file:`constants.typoscript` and keep the static template's mapping:

..  code-block:: typoscript
    :caption: constants.typoscript

    plugin.tx_okexchange365mailer.settings.exchange365.clientSecret := getEnv(EXCHANGE365_CLIENT_SECRET)

Then provide the variables to the PHP process, for example:

..  code-block:: bash
    :caption: .env (DDEV: .ddev/.env.web)

    EXCHANGE365_TENANT_ID=00000000-0000-0000-0000-000000000000
    EXCHANGE365_CLIENT_ID=00000000-0000-0000-0000-000000000000
    EXCHANGE365_CLIENT_SECRET=your-client-secret-value
    EXCHANGE365_FROM_EMAIL=service@your-domain.com

``getEnv()`` is available on every TYPO3 version this extension supports.

..  important::
    **Three things to know about** ``getEnv()``. All three are verified by the
    extension's :ref:`test matrix <testing>` on every supported TYPO3 version.

    *   **It reads the real process environment.** ``getEnv()`` calls PHP's
        :php:`getenv()`. A variable that is only loaded into :php:`$_ENV` — which
        is what ``symfony/dotenv`` and ``helhum/dotenv-connector`` do by default —
        is **invisible** to it. Set the variable where the web server starts PHP
        (DDEV ``web_environment`` or :file:`.ddev/.env.web`, an ``env[...]`` line in
        the PHP-FPM pool, ``SetEnv`` in Apache, the container environment), or
        configure the dotenv loader to use ``putenv()``.
    *   **An unset variable keeps the previous value.** If the variable does not
        exist, ``getEnv()`` leaves the property unchanged — it does **not** empty
        it. Clear the property first if a missing variable must not fall back to an
        earlier value:

        ..  code-block:: typoscript

            plugin.tx_okexchange365mailer.settings.exchange365.clientSecret =
            plugin.tx_okexchange365mailer.settings.exchange365.clientSecret := getEnv(EXCHANGE365_CLIENT_SECRET)

        The empty value then falls back to ``TYPO3_CONF_VARS``. If that is empty
        too, sending fails with
        ``Exchange 365 configuration missing required field: clientSecret``.
    *   **The value is cached.** TypoScript is parsed once and cached. After
        changing an environment variable, flush the caches.

TypoScript parameters
=====================

All parameters live below ``plugin.tx_okexchange365mailer.settings.exchange365``.
Include the static template **[kroener.DIGITAL] Exchange 365 Mailer** to get the
constants editor entries; its defaults are all empty, so including it changes
nothing until you set a value.

..  list-table::
    :header-rows: 1
    :widths: 22 78

    *   -   Parameter
        -   Meaning
    *   -   ``tenantId``
        -   Microsoft Entra ID tenant ID (:ref:`Azure Configuration <azure>`, step 4).
            Use ``:= getEnv(...)``.
    *   -   ``clientId``
        -   Application (client) ID of the app registration (step 4).
            Use ``:= getEnv(...)``.
    *   -   ``clientSecret``
        -   The secret **Value** of the app registration (step 7).
            Always use ``:= getEnv(...)``.
    *   -   ``fromEmail``
        -   Sender address used when a mail has no From address. Must be a user
            or shared mailbox in your tenant.
    *   -   ``graphSenderUserId``
        -   Optional. The mailbox the Graph call ``/users/{id}/sendMail`` is made
            through, when it differs from the visible From address (*Send As* /
            *Send On Behalf*). Falls back to the message From address, then
            ``fromEmail``, then ``MAIL.defaultMailFromAddress``. See
            `Send mail from another user <https://learn.microsoft.com/en-us/graph/outlook-send-mail-from-other-user>`__.
    *   -   ``saveToSentItems``
        -   ``1`` saves a copy in the mailbox's *Sent Items*, ``0`` does not. The
            static template sets ``1``. Unlike the other parameters, an empty value
            here means ``0`` and does **not** fall back to ``TYPO3_CONF_VARS``.

Per-environment credentials
===========================

Use different environment variables per environment rather than TypoScript
conditions with literal IDs — the TypoScript stays identical everywhere and only
the server configuration differs:

..  code-block:: bash

    # staging server
    EXCHANGE365_CLIENT_ID=staging-app-id
    EXCHANGE365_CLIENT_SECRET=staging-secret

    # production server
    EXCHANGE365_CLIENT_ID=production-app-id
    EXCHANGE365_CLIENT_SECRET=production-secret

A different sender per site is a plain value, not a secret, so it can live in
TypoScript directly:

..  code-block:: typoscript

    [site("identifier") == "shop"]
        plugin.tx_okexchange365mailer.settings.exchange365.fromEmail = shop@your-domain.com
    [END]

Integration with form extensions
================================

**Form Framework**, **Powermail** and other extensions use TYPO3's mailer and
therefore this transport automatically. They need no extra configuration; their
own sender settings become the message From address.

..  code-block:: yaml

    finishers:
      -
        identifier: EmailToReceiver
        options:
          recipients:
            recipient@example.com: 'Recipient Name'

Security considerations
=======================

..  danger::
    *   **Never write the client secret into TypoScript** — not in a template
        record, a site package or :file:`settings.yaml`. Use ``:= getEnv(...)``
        or ``TYPO3_CONF_VARS`` filled from the environment.
    *   TypoScript is not sent to the browser, but it **is** readable in the
        backend (*Site Management > TypoScript*) by every user with access to
        that module, and it is part of every database dump.
    *   In *System > Configuration*, the extension masks the tenant ID, client ID
        and client secret held in ``TYPO3_CONF_VARS`` — and, from TYPO3 v12 on,
        the same settings in a site's configuration (*Sites YAML configuration*).

Troubleshooting
===============

**"Exchange 365 configuration missing required field: …"**
    The parameter is empty in TypoScript **and** in ``TYPO3_CONF_VARS``. With
    ``getEnv()``, the variable is most likely not in the PHP process environment —
    see the note on :php:`getenv()` above. Run
    ``php -r 'var_dump(getenv("EXCHANGE365_CLIENT_SECRET"));'`` in the same
    environment as the web server to check.

**Old credentials still used after a change**
    Flush the caches — TypoScript, including ``getEnv()`` results, is cached.

**Authentication errors (AADSTS…)**
    Tenant ID or client ID is wrong, or the client secret has expired.

**Permission errors (403)**
    The app registration lacks the ``Mail.Send`` application permission or admin
    consent, or the sender mailbox is outside an application access policy.

..  seealso::
    For backend configuration details, see :ref:`Essential Configuration <essential>`.
    For Azure setup instructions, see :ref:`Azure Configuration <azure>`.

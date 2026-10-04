..  include:: /Includes.rst.txt

..  _frontend:

======================
Frontend Configuration
======================

Mails sent from the frontend — by the **Form Framework**, **Powermail** or any
other extension that uses TYPO3's mailer — go through the same transport and
use the same ``TYPO3_CONF_VARS`` settings as backend and CLI mails, so **no
TypoScript is needed**. In the frontend, the transport additionally reads
TypoScript, so a site can use its own credentials or sender.

..  _frontend-overlay:

How frontend configuration works
================================

The transport itself is always selected in
``$GLOBALS['TYPO3_CONF_VARS']['MAIL']['transport']`` (see
:ref:`configuration`). TypoScript cannot switch the transport.

To use other credentials or another sender on a site, include the static
template **[kroener.DIGITAL] Exchange 365 Mailer** and set values below
``plugin.tx_okexchange365mailer.settings.exchange365``. TypoScript
**overlays** ``TYPO3_CONF_VARS`` **per parameter**:

-   A parameter with a non-empty value in TypoScript wins.
-   A parameter that is empty in TypoScript falls back to ``TYPO3_CONF_VARS``.
-   ``saveToSentItems`` is the exception — the static template sets it to
    ``1``, and an empty value means ``0``.

..  _frontend-getenv:

Read the credentials from the environment
=========================================

Never write the tenant ID, client ID or client secret into TypoScript. Read
them from the environment with ``getEnv()`` (works in setup and constants):

..  code-block:: typoscript
    :caption: setup.typoscript

    plugin.tx_okexchange365mailer.settings.exchange365 {
        tenantId := getEnv(EXCHANGE365_TENANT_ID)
        clientId := getEnv(EXCHANGE365_CLIENT_ID)
        clientSecret := getEnv(EXCHANGE365_CLIENT_SECRET)
        fromEmail = service@your-domain.com
    }

..  important::

    **Three things to know about** ``getEnv()``. All three are verified by
    the extension's :ref:`test matrix <testing>` on TYPO3 9.5.

    -   **It reads the real process environment.** ``getEnv()`` calls PHP's
        ``getenv()``. A variable that is only loaded into ``$_ENV`` — which is
        what ``symfony/dotenv`` and ``helhum/dotenv-connector`` do by default —
        is **invisible** to it. Set the variable where the web server starts
        PHP (DDEV ``web_environment`` or :file:`.ddev/.env.web`, an
        ``env[...]`` line in the PHP-FPM pool, ``SetEnv`` in Apache, the
        container environment), or configure the dotenv loader to use
        ``putenv()``.
    -   **An unset variable keeps the previous value.** If the variable does
        not exist, ``getEnv()`` leaves the property unchanged — it does
        **not** empty it. Clear the property first if a missing variable must
        not fall back to an earlier value:

        ..  code-block:: typoscript

            plugin.tx_okexchange365mailer.settings.exchange365.clientSecret =
            plugin.tx_okexchange365mailer.settings.exchange365.clientSecret := getEnv(EXCHANGE365_CLIENT_SECRET)

        The empty value then falls back to ``TYPO3_CONF_VARS``.
    -   **The value is cached.** TypoScript is parsed once and cached. After
        changing an environment variable, flush the caches.

TypoScript is not sent to the browser, but it **is** readable in the
backend (:guilabel:`Web > Template`) by every user with access to that module,
and it is part of every database dump. Values set in TypoScript are not masked
in :guilabel:`System > Configuration`.

..  _frontend-parameters:

TypoScript parameters
=====================

All parameters live below
``plugin.tx_okexchange365mailer.settings.exchange365``. The static template
maps constants of the same name to these settings and adds them to the
Constant Editor (category *exchange365mailer*).

..  list-table::
    :header-rows: 1
    :widths: 25 15 60

    *   -   Parameter
        -   Default
        -   Meaning
    *   -   ``tenantId``
        -   empty
        -   Microsoft Entra ID tenant ID. Use ``:= getEnv(...)``.
    *   -   ``clientId``
        -   empty
        -   Application (client) ID of the app registration. Use
            ``:= getEnv(...)``.
    *   -   ``clientSecret``
        -   empty
        -   The client secret **Value**. Always use ``:= getEnv(...)``.
    *   -   ``fromEmail``
        -   empty
        -   Sender mailbox (user or shared mailbox).
    *   -   ``graphSenderUserId``
        -   empty
        -   Optional. The mailbox the Graph call is made through (*Send As* /
            *Send On Behalf*). Falls back to the message From address, then
            ``fromEmail``, then ``MAIL.defaultMailFromAddress``.
    *   -   ``saveToSentItems``
        -   ``1``
        -   ``1`` saves a copy in *Sent Items*, ``0`` or empty does not.

..  seealso::

    :ref:`faq` lists the error messages and their causes.

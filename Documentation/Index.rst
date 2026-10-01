.. include:: Includes.rst.txt

Documentation
=============

.. contents:: Table of Contents
   :depth: 2
   :local:

Introduction
------------

The **ok_exchange365_mailer** is a TYPO3 extension that enables your
TYPO3 installation to send emails using Microsoft Exchange 365 via the
Microsoft Graph API. This ensures secure and reliable email delivery by
leveraging Microsoft’s cloud services directly from your TYPO3 site.

Features
--------

-  **Microsoft Exchange 365 Integration**: Seamlessly integrate Exchange
   365 as your email sending service.
-  **Microsoft Graph API**: Utilize the powerful Microsoft Graph API for
   email transmission.
-  **Secure OAuth2 Authentication**: Secure communication with Exchange
   365 using OAuth2 (client credentials).
-  **Send As / Send On Behalf**: An optional ``graphSenderUserId`` sends
   through a different Graph mailbox than the visible From address.
-  **Credential masking**: Tenant ID, client ID and client secret from
   ``TYPO3_CONF_VARS`` are masked in **System > Configuration**.

Requirements
------------

-  **TYPO3 CMS**: 9.5 LTS (this is the 1.x line; other TYPO3 versions
   are served by other major versions of the extension).
-  **PHP**: 7.2 – 7.4.
-  **Composer**: For installation via Composer.
-  **Dependencies**:

   -  oliverkroener/ok-typo3-helper https://packagist.org/packages/oliverkroener/ok-typo3-helper
      Version ^1.
   -  guzzlehttp/guzzle Version ^6.3 || ^7.0.

Installation
------------

Install via Composer
~~~~~~~~~~~~~~~~~~~~

Run the following command in your TYPO3 project root directory:

.. code-block:: bash

   composer require oliverkroener/ok-exchange365-mailer

This will install the extension along with its dependencies.

Configuration
-------------

To configure the extension to send emails via Exchange 365, follow these
steps:

Step 1: Register an Application in Azure Portal
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

1. **Log in** to the `Azure Portal <https://portal.azure.com/>`__.
2. Navigate to **Microsoft Entra ID** > **App registrations**.
3. Click **New registration**.
4. **Name** your application (e.g., “TYPO3 Mailer”).
5. Set **Supported account types** as per your requirements.
6. Click **Register**. No redirect URI is needed (client credentials flow).

Step 2: Configure API Permissions
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

1. In your registered app, go to **API Permissions**.
2. Click **Add a permission**.
3. Select **Microsoft Graph**.
4. Choose **Application permissions**.
5. Find and add **Mail.Send**, **User.ReadBasic.All** permission.
6. Click **Grant admin consent** to grant permissions.

Step 3: Create a Client Secret
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

1. Go to **Certificates & secrets**.
2. Click **New client secret**.
3. Provide a description and set an expiration.
4. Click **Add**.
5. Copy the **Value** of the client secret (not the Secret ID). **This is
   shown only once**.

Step 4: Configure TYPO3
~~~~~~~~~~~~~~~~~~~~~~~

The extension reads its settings from ``$GLOBALS['TYPO3_CONF_VARS']['MAIL']``.
These settings are used everywhere — backend, CLI, scheduler and, unless
TypoScript overrides them, the frontend. The transport is selected **only**
in ``$GLOBALS['TYPO3_CONF_VARS']['MAIL']['transport']``; there is no
TypoScript option for it.

.. list-table::
   :header-rows: 1
   :widths: 40 60

   * - ``$GLOBALS['TYPO3_CONF_VARS']['MAIL'][…]``
     - Meaning
   * - ``transport``
     - ``OliverKroener\OkExchange365\Mail\Transport\Exchange365Transport``
   * - ``transport_exchange365_tenantId``
     - Microsoft Entra ID tenant ID
   * - ``transport_exchange365_clientId``
     - Application (client) ID of the app registration
   * - ``transport_exchange365_clientSecret``
     - The client secret **Value**
   * - ``transport_exchange365_fromEmail``
     - Sender mailbox (user or shared mailbox). Falls back to
       ``MAIL.defaultMailFromAddress``.
   * - ``transport_exchange365_graphSenderUserId``
     - Optional. The mailbox the Graph call ``/users/{id}/sendMail`` is
       made through, when it differs from the visible From address
       (*Send As* / *Send On Behalf*). Falls back to the message From
       address, then ``fromEmail``, then ``MAIL.defaultMailFromAddress``.
       See `Send mail from another user
       <https://learn.microsoft.com/en-us/graph/outlook-send-mail-from-other-user>`__.
   * - ``transport_exchange365_saveToSentItems``
     - ``1`` saves a copy in *Sent Items*, ``0`` does not. Default: ``0``.

Never write the client secret as a literal value into
:file:`LocalConfiguration.php` or :file:`AdditionalConfiguration.php` — TYPO3
rewrites :file:`LocalConfiguration.php` when settings change in the backend,
and both files typically end up in version control and backups. Read the
values from the environment instead.

**Option A: environment variables named** ``TYPO3_CONF_VARS__…``

.. code-block:: bash

   TYPO3_CONF_VARS__MAIL__transport=OliverKroener\\OkExchange365\\Mail\\Transport\\Exchange365Transport
   TYPO3_CONF_VARS__MAIL__transport_exchange365_tenantId='your-tenant-id'
   TYPO3_CONF_VARS__MAIL__transport_exchange365_clientId='your-client-id'
   TYPO3_CONF_VARS__MAIL__transport_exchange365_clientSecret='your-client-secret'
   TYPO3_CONF_VARS__MAIL__transport_exchange365_fromEmail='service@your-domain.com'
   # Optional: Send As / Send On Behalf
   #TYPO3_CONF_VARS__MAIL__transport_exchange365_graphSenderUserId='shared-mailbox@your-domain.com'
   TYPO3_CONF_VARS__MAIL__transport_exchange365_saveToSentItems=1

.. important::

   **TYPO3 does not read** ``TYPO3_CONF_VARS__…`` **variables by itself.**
   The double-underscore naming is a widespread convention, but the mapping
   onto ``$GLOBALS['TYPO3_CONF_VARS']`` has to be done by your project. If
   your project has no such mapping, add this loop:

   .. code-block:: php
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

**Option B: read the environment in AdditionalConfiguration.php**

Without the mapping, set the values in
:file:`public/typo3conf/AdditionalConfiguration.php` and read the secrets
from the environment there. Use your own variable names:

.. code-block:: php
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

Step 5: Frontend overrides via TypoScript (optional)
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

Frontend mail (Form Framework, Powermail, …) uses the same
``TYPO3_CONF_VARS`` settings, so **no TypoScript is needed**. To use other
credentials or another sender on a site, include the static template
**[kroener.DIGITAL] Exchange 365 Mailer** and set values below
``plugin.tx_okexchange365mailer.settings.exchange365`` (``tenantId``,
``clientId``, ``clientSecret``, ``fromEmail``, ``graphSenderUserId``,
``saveToSentItems``). TypoScript **overlays** ``TYPO3_CONF_VARS`` per
parameter: a non-empty value wins, an empty value falls back.
``saveToSentItems`` is the exception — the static template sets it to ``1``,
and an empty value means ``0``.

Never write the tenant ID, client ID or client secret into TypoScript. Read
them from the environment with ``getEnv()`` (works in setup and constants):

.. code-block:: typoscript

   plugin.tx_okexchange365mailer.settings.exchange365 {
       tenantId := getEnv(EXCHANGE365_TENANT_ID)
       clientId := getEnv(EXCHANGE365_CLIENT_ID)
       clientSecret := getEnv(EXCHANGE365_CLIENT_SECRET)
       fromEmail = service@your-domain.com
   }

.. important::

   **Three things to know about** ``getEnv()``. All three are verified by
   the extension's test matrix on TYPO3 9.5.

   -  **It reads the real process environment.** ``getEnv()`` calls PHP's
      ``getenv()``. A variable that is only loaded into ``$_ENV`` — which is
      what ``symfony/dotenv`` and ``helhum/dotenv-connector`` do by default —
      is **invisible** to it. Set the variable where the web server starts
      PHP (DDEV ``web_environment`` or ``.ddev/.env.web``, an ``env[...]``
      line in the PHP-FPM pool, ``SetEnv`` in Apache, the container
      environment), or configure the dotenv loader to use ``putenv()``.
   -  **An unset variable keeps the previous value.** If the variable does
      not exist, ``getEnv()`` leaves the property unchanged — it does
      **not** empty it. Clear the property first if a missing variable must
      not fall back to an earlier value:

      .. code-block:: typoscript

         plugin.tx_okexchange365mailer.settings.exchange365.clientSecret =
         plugin.tx_okexchange365mailer.settings.exchange365.clientSecret := getEnv(EXCHANGE365_CLIENT_SECRET)

      The empty value then falls back to ``TYPO3_CONF_VARS``.
   -  **The value is cached.** TypoScript is parsed once and cached. After
      changing an environment variable, flush the caches.

TypoScript is not sent to the browser, but it **is** readable in the
backend (**Web > Template**) by every user with access to that module, and
it is part of every database dump.

Usage
-----

Once configured, the extension handles email sending via Exchange 365
automatically. All emails sent by TYPO3 (system emails, form
notifications, etc.) will use the Microsoft Graph API.

Testing Email Sending
~~~~~~~~~~~~~~~~~~~~~

To verify that emails are being sent correctly, open **Admin Tools** >
**Environment** > **Test Mail Setup** and send a test mail. Check that it
arrives, and — with ``saveToSentItems`` enabled — that a copy appears in
the sender mailbox's *Sent Items*.

Automated tests
~~~~~~~~~~~~~~~

This branch is covered by the cross-version test matrix that lives on the
extension's main branch (``make test-matrix`` / ``make test-matrix-live``
there). For TYPO3 9.5 it runs the unit and functional tests, PHPStan and
the coding standard check, sends real mail through Microsoft Graph from the
CLI and from the frontend (credentials via ``:= getEnv()``), and checks in
a headless browser that the credentials are masked in the backend.

Troubleshooting
---------------

Common Issues
~~~~~~~~~~~~~

A failed send throws a ``RuntimeException`` whose message names the cause,
for example
``… Error: Exchange 365 configuration missing required field: clientSecret``.

-  **"Exchange 365 configuration missing required field: …"**: The value is
   empty in TypoScript **and** in ``TYPO3_CONF_VARS``. With ``getEnv()`` or
   ``TYPO3_CONF_VARS__…`` variables, check that the variable reaches PHP and
   that your project maps it (see Step 4).
-  **Old credentials still used after a change**: Flush the caches.
-  **Authentication Errors (AADSTS…)**: Double-check your Tenant ID and
   Client ID, and that the Client Secret has not expired.
-  **Permission Denied (403)**: Ensure that **Mail.Send** application
   permission is granted with admin consent, and that the sender mailbox is
   not excluded by an application access policy.

Checking Logs
~~~~~~~~~~~~~

-  **TYPO3 System Log**: Navigate to **System** > **Log** to view
   system messages.
-  **PHP Error Log**: Check your server’s PHP error logs for any runtime
   errors.
-  **Microsoft Entra ID sign-in logs**: Use the Azure Portal to monitor
   the application's sign-ins and identify issues.

Security
~~~~~~~~

In **System > Configuration**, the extension masks the tenant ID, client ID
and client secret held in ``TYPO3_CONF_VARS`` (via a hook of the lowlevel
configuration module). Values set in TypoScript are not masked.

Support
~~~~~~~

If issues persist:

-  **Contact the Author**: See `Author and
   Support <#author-and-support>`__ section.
-  **Consult Documentation**: Review Microsoft’s documentation on
   `Microsoft Graph
   API <https://docs.microsoft.com/en-us/graph/overview>`__ for
   additional insights.

License
-------

This extension is licensed under the `GNU General Public License v2.0 <https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html>`__.

Author and Support
------------------

-  **Author**: Oliver Kroener
-  **Email**: ok@oliver-kroener.de
-  **Website**: `oliver-kroener.de <https://www.oliver-kroener.de>`__

For support, feature requests, or bug reports, please contact the author
via email.

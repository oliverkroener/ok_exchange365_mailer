..  include:: /Includes.rst.txt

..  _introduction:

============
Introduction
============

..  _introduction-what:

What does it do?
================

**ok_exchange365_mailer** is a mail transport for TYPO3. Once it is selected
in ``$GLOBALS['TYPO3_CONF_VARS']['MAIL']['transport']``, every mail TYPO3
sends — system mails, the Install Tool test mail, Form Framework and Powermail
finishers, scheduler tasks, CLI commands — is delivered through
Microsoft Exchange 365 with the Microsoft Graph API instead of SMTP.

The extension has no backend module and no plugin. It is configured through
``TYPO3_CONF_VARS`` and, optionally, frontend TypoScript.

..  _introduction-features:

Features
========

-  **SMTP-free delivery**: Mails are posted to the Microsoft Graph endpoint
   ``/users/{id}/sendMail``. SMTP does not have to be enabled in the tenant.
-  **OAuth 2.0 client credentials**: The transport requests an app-only access
   token from the Microsoft identity platform (v2.0 endpoint). No mailbox
   password is stored in TYPO3.
-  **SwiftMailer transport**: The transport implements ``Swift_Transport``, so
   existing code that uses TYPO3's ``MailMessage`` keeps working unchanged.
-  **One baseline for every context**: The settings in ``TYPO3_CONF_VARS`` are
   used in the backend, the frontend, on the CLI and in the scheduler.
-  **Per-site overrides in the frontend**: TypoScript overlays
   ``TYPO3_CONF_VARS`` per setting; an empty TypoScript value falls back. See
   :ref:`frontend`.
-  **Send As / Send On Behalf**: An optional ``graphSenderUserId`` sends
   through a different Graph mailbox than the visible From address.
-  **Save to Sent Items**: ``saveToSentItems`` controls whether Graph stores a
   copy in the sender mailbox.
-  **Credential masking**: Tenant ID, client ID and client secret from
   ``TYPO3_CONF_VARS`` are masked in :guilabel:`System > Configuration`.
-  **Errors are not swallowed**: A failed send is logged and thrown as a
   ``RuntimeException`` whose message names the cause.

..  _introduction-how:

How it works
============

For each mail the transport

#.  builds its configuration: the ``transport_exchange365_*`` settings from
    ``TYPO3_CONF_VARS`` are the baseline, non-empty frontend TypoScript values
    replace them per setting,
#.  checks that tenant ID, client ID and client secret are present,
#.  obtains an access token from
    ``https://login.microsoftonline.com/{tenantId}/oauth2/v2.0/token``. The
    token is kept in the transport and reused for the same set of credentials
    until one minute before it expires, so a request that sends several mails
    authenticates only once,
#.  converts the SwiftMailer message into a Graph message and resolves the
    mailbox to send through (``graphSenderUserId``, then the message From
    address, then ``fromEmail``, then ``MAIL.defaultMailFromAddress``),
#.  posts the message to
    ``https://graph.microsoft.com/v1.0/users/{id}/sendMail``.

The extension talks to Microsoft directly over HTTPS with Guzzle; the
Microsoft Graph SDK is not used.

Timeouts and retry
------------------

-  Connecting may take at most **10 seconds**, a whole request at most
   **30 seconds**.
-  A request is repeated **once** when Microsoft answers with HTTP 429, 503 or
   504. The transport waits for the time given in the ``Retry-After`` header
   first, at least 1 and at most 5 seconds.
-  A request is also repeated once when **no connection could be made**.
-  A request that stalled **after** the connection was established is **not**
   repeated: Graph may already have accepted the message, and a retry could
   deliver it twice.

..  _introduction-requirements:

Requirements
============

-  **TYPO3**: 9.5 LTS
-  **PHP**: 7.2 – 7.4
-  **Composer packages**:
   `oliverkroener/ok-typo3-helper <https://packagist.org/packages/oliverkroener/ok-typo3-helper>`__
   ``^1`` and ``guzzlehttp/guzzle`` ``^6.3 || ^7.0``. Composer installs both.
-  **Microsoft 365**: An app registration in Microsoft Entra ID with the
   ``Mail.Send`` application permission and admin consent, and a user or
   shared mailbox to send from. See :ref:`azure`.
-  The masking in :guilabel:`System > Configuration` needs the system extension
   *lowlevel*, which provides that module.

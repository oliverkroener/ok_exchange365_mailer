..  include:: /Includes.rst.txt

..  _faq:

================================
Frequently Asked Questions (FAQ)
================================

..  _faq-general:

General
=======

Which TYPO3 versions does this version support?
    The 1.x line supports TYPO3 9.5 LTS with PHP 7.2 – 7.4. Other TYPO3
    versions are served by other major versions of the extension.

Do I need TypoScript?
    No. Frontend mail uses the same ``TYPO3_CONF_VARS`` settings as backend
    and CLI mail. TypoScript is only needed to override single values per
    site, see :ref:`frontend`.

Can I select the transport with TypoScript?
    No. The transport is selected only in
    ``$GLOBALS['TYPO3_CONF_VARS']['MAIL']['transport']``.

Is there a backend module or a settings form?
    No. The extension is configured through ``TYPO3_CONF_VARS`` and,
    optionally, TypoScript. See :ref:`configuration`.

Can a mail be delivered twice by the retry?
    The transport repeats a request once when Microsoft answers with HTTP
    429, 503 or 504, or when no connection could be made — in both cases the
    message was not accepted. A request that stalled after the connection was
    established is not repeated, to avoid duplicate mails.

..  _faq-troubleshooting:

Troubleshooting
===============

A failed send throws a ``RuntimeException`` whose message names the cause,
for example
``… Error: Exchange 365 configuration missing required field: clientSecret``.

"Exchange 365 configuration missing required field: …"
    The value is empty in TypoScript **and** in ``TYPO3_CONF_VARS``. With
    ``getEnv()`` or ``TYPO3_CONF_VARS__…`` variables, check that the variable
    reaches PHP and that your project maps it (see :ref:`env-mapping` and
    :ref:`frontend-getenv`).

"No Microsoft Graph sender user ID could be resolved"
    Neither ``graphSenderUserId`` nor a message From address, ``fromEmail``
    or ``MAIL.defaultMailFromAddress`` is set. Configure one of them.

Old credentials still used after a change
    Flush the caches — TypoScript, including ``getEnv()`` results, is cached.

Authentication errors (AADSTS…)
    Double-check your tenant ID and client ID, and that the client secret has
    not expired.

Permission denied (403)
    Ensure that the **Mail.Send** application permission is granted with
    admin consent, and that the sender mailbox is not excluded by an
    application access policy.

..  _faq-logs:

Checking logs
=============

-   **TYPO3 log**: The transport logs every failed send as an error, with the
    exception. Navigate to :guilabel:`System > Log` or check the log files in
    :file:`var/log/`.
-   **PHP error log**: Check your server's PHP error logs for any runtime
    errors.
-   **Microsoft Entra ID sign-in logs**: Use the Azure Portal to monitor the
    application's sign-ins and identify issues.

..  _help:

Where to get help
=================

-   Report issues at
    `github.com/oliverkroener/ok_exchange365_mailer/issues <https://github.com/oliverkroener/ok_exchange365_mailer/issues>`__.
-   Contact the author — see :ref:`contact`.
-   Consult Microsoft's documentation on the
    `Microsoft Graph API <https://learn.microsoft.com/en-us/graph/overview>`__.

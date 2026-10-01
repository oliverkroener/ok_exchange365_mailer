:navigation-title: Essential

..  _essential:

=======================
Essential Configuration
=======================

After completing the :ref:`Azure Configuration <azure>`, you need to configure the TYPO3 extension with the values obtained from Microsoft Entra ID.

..  attention::
    The **Client ID** can be found on the overview page in Azure and **should not be confused with the Client Secret ID**. For the secret configuration, only the Secret value itself is required, not the Secret ID.

Quick Navigation
================

This page covers the complete configuration setup for the Exchange 365 TYPO3 extension:

*   :ref:`configuration-variables` - Required environment variables and settings
*   :ref:`configuration-example` - Complete configuration example with screenshot
*   :ref:`alternative-configuration-methods` - Different ways to configure the extension
*   :ref:`testing-the-configuration` - How to verify your setup works
*   :ref:`security-considerations` - Important security guidelines
*   :ref:`configuration-validation` - Steps to validate your configuration

..  _configuration-variables:

Configuration Variables
=======================

The extension reads its settings from ``$GLOBALS['TYPO3_CONF_VARS']['MAIL']``. The
keys are prefixed with ``transport_exchange365_`` to avoid conflicts with other mail
transports. These settings are used everywhere — backend, CLI, scheduler and, unless
TypoScript overrides them, the :ref:`frontend <frontend>`.

The steps below write them as environment variables named
``TYPO3_CONF_VARS__MAIL__…``, which keeps every secret out of files that end up in
version control.

..  important::
    **TYPO3 does not read** ``TYPO3_CONF_VARS__…`` **variables by itself.** The
    double-underscore naming is a widespread convention, but the mapping onto
    ``$GLOBALS['TYPO3_CONF_VARS']`` has to be done by your project — see
    :ref:`env-mapping`. If your project has no such mapping, use
    :ref:`config-files-from-env` instead.

..  rst-class:: bignums-xxl

1.  Set the mail transport to Exchange365Transport.

    Configure TYPO3 to use the Exchange 365 transport instead of the default SMTP transport.

    ..  code-block:: bash

        TYPO3_CONF_VARS__MAIL__transport=OliverKroener\\OkExchange365\\Mail\\Transport\\Exchange365Transport

2.  Configure the Tenant ID.

    Set the **Tenant ID** obtained from step 4 of the :ref:`Azure Configuration <azure>`.

    ..  code-block:: bash

        TYPO3_CONF_VARS__MAIL__transport_exchange365_tenantId='your-tenant-id-here'

    ..  attention::
        Replace `your-tenant-id-here` with the actual Tenant ID from your Azure application overview page.

3.  Configure the Client ID.

    Set the **Client ID** (Application ID) obtained from step 4 of the :ref:`Azure Configuration <azure>`.

    ..  code-block:: bash

        TYPO3_CONF_VARS__MAIL__transport_exchange365_clientId='your-client-id-here'

    ..  attention::
        Replace `your-client-id-here` with the actual Client ID from your Azure application overview page.

4.  Configure the Client Secret.

    Set the **Client Secret** value obtained from step 7 of the :ref:`Azure Configuration <azure>`.

    ..  code-block:: bash

        TYPO3_CONF_VARS__MAIL__transport_exchange365_clientSecret='your-client-secret-here'

    ..  attention::
        - Replace `your-client-secret-here` with the actual Secret **Value** (not the Secret ID)
        - This is sensitive information - keep it secure and never expose it in public repositories

5.  Configure the sender email address.

    Set the email address that will be used as the sender for all emails sent through this transport.

    ..  code-block:: bash

        TYPO3_CONF_VARS__MAIL__transport_exchange365_fromEmail='service@your-domain.com'

    ..  attention::
        The email address will fall back to TYPO3's `$GLOBALS['TYPO3_CONF_VARS']['MAIL']['defaultMailFromAddress']` if not specified here.
        
    ..  note::
        - Replace `service@your-domain.com` with a valid email address from your organization (it must exist in your Exchange 365 environment as **SharedMailbox or User Mailbox**)
        - This email address must exist in your Exchange 365 environment
        - The application needs permission to send emails on behalf of this address

6.  Configure the Graph sender user ID (optional).

    Set the Microsoft Graph mailbox/user ID that is used for the
    ``/users/{id}/sendMail`` API call. This is intentionally separate from the
    message **From** address, so you can send *as* or *on behalf of* a different
    mailbox (Send As / Send On Behalf scenarios).

    ..  code-block:: bash

        TYPO3_CONF_VARS__MAIL__transport_exchange365_graphSenderUserId='shared-mailbox@your-domain.com'

    ..  note::
        - **Optional.** Leave empty to fall back to the message **From** address, then to ``fromEmail``, then to ``$GLOBALS['TYPO3_CONF_VARS']['MAIL']['defaultMailFromAddress']``
        - The Azure application must have ``Mail.Send`` permission for this mailbox, and this mailbox needs *Send As* or *Send On Behalf* on the visible sender mailbox. See `Send mail from another user (Microsoft Graph) <https://learn.microsoft.com/en-us/graph/outlook-send-mail-from-other-user>`_.
        - The value can be a user principal name or the object ID of the mailbox

7.  Configure save to sent items (optional).

    Determine whether sent emails should be saved to the sender's "Sent Items" folder.

    ..  code-block:: bash

        TYPO3_CONF_VARS__MAIL__transport_exchange365_saveToSentItems=1

    ..  note::
        - Set to `1` to save emails to Sent Items folder
        - Set to `0` to skip saving emails to Sent Items folder
        - Default when not set: `0` for backend, CLI and scheduler mails. In the
          frontend, the static template defaults to `1`.

..  _configuration-example:

Configuration Example
=====================

Here's a complete example of all required configuration variables:

..  figure:: /_Images/image16.png
    :alt: TYPO3 configuration showing Exchange365 transport variables setup
    :class: with-shadow
    :scale: 100

Environment Variables (.env file)
---------------------------------

You can configure these settings using a `.env` file in your TYPO3 root directory:

..  code-block:: bash

    # Exchange 365 Mail Transport Configuration
    TYPO3_CONF_VARS__MAIL__transport=OliverKroener\\OkExchange365\\Mail\\Transport\\Exchange365Transport
    TYPO3_CONF_VARS__MAIL__transport_exchange365_tenantId='your-tenant-id-here'
    TYPO3_CONF_VARS__MAIL__transport_exchange365_clientId='your-client-id-here'
    TYPO3_CONF_VARS__MAIL__transport_exchange365_clientSecret='your-client-secret-here'
    TYPO3_CONF_VARS__MAIL__transport_exchange365_fromEmail='service@your-domain.com'
    # Optional: Graph sender mailbox (Send As / Send On Behalf).
    #TYPO3_CONF_VARS__MAIL__transport_exchange365_graphSenderUserId='account1@your-domain.com'
    TYPO3_CONF_VARS__MAIL__transport_exchange365_saveToSentItems=1

..  _alternative-configuration-methods:

Alternative Configuration Methods
=================================

..  _env-mapping:

Mapping ``TYPO3_CONF_VARS__…`` variables
----------------------------------------

If your project does not already map ``TYPO3_CONF_VARS__…`` environment variables,
add this loop. Double underscores become array levels, so
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

In TYPO3 configuration files, reading the environment
-----------------------------------------------------

Without the mapping, set the values in
:file:`public/typo3conf/AdditionalConfiguration.php` and read the secrets from the
environment there. Use your own variable names:

..  code-block:: php
    :caption: public/typo3conf/AdditionalConfiguration.php

    <?php

    $env = static fn (string $name): string => (string)(getenv($name) ?: ($_ENV[$name] ?? ''));

    $GLOBALS['TYPO3_CONF_VARS']['MAIL']['transport'] = \OliverKroener\OkExchange365\Mail\Transport\Exchange365Transport::class;
    $GLOBALS['TYPO3_CONF_VARS']['MAIL']['transport_exchange365_tenantId'] = $env('EXCHANGE365_TENANT_ID');
    $GLOBALS['TYPO3_CONF_VARS']['MAIL']['transport_exchange365_clientId'] = $env('EXCHANGE365_CLIENT_ID');
    $GLOBALS['TYPO3_CONF_VARS']['MAIL']['transport_exchange365_clientSecret'] = $env('EXCHANGE365_CLIENT_SECRET');
    $GLOBALS['TYPO3_CONF_VARS']['MAIL']['transport_exchange365_fromEmail'] = 'service@your-domain.com';
    // Optional: distinct Graph sender mailbox (Send As / Send On Behalf).
    // $GLOBALS['TYPO3_CONF_VARS']['MAIL']['transport_exchange365_graphSenderUserId'] = 'account1@your-domain.com';
    $GLOBALS['TYPO3_CONF_VARS']['MAIL']['transport_exchange365_saveToSentItems'] = 1;

..  warning::
    Do not write the client secret as a literal into :file:`LocalConfiguration.php`
    or :file:`AdditionalConfiguration.php`. TYPO3 rewrites
    :file:`LocalConfiguration.php` when settings change in the backend, and both
    files typically end up in version control and backups.

..  note::
    In the frontend, the same values can also come from TypoScript, where
    ``:= getEnv(...)`` reads them from the environment. See :ref:`frontend-getenv`.

..  _testing-the-configuration:

Testing the Configuration
=========================

After configuring all variables, you can test the email functionality by:

1. Sending a test email through TYPO3's mail functionality
2. Checking the TYPO3 logs for any error messages
3. Verifying that emails are received at the intended recipients
4. Checking the sender's "Sent Items" folder if `saveToSentItems` is enabled

..  tip::
    Enable TYPO3's developer log to see detailed information about the email sending process and any potential issues with the Exchange 365 integration.

..  _security-considerations:

Security Considerations
=======================

..  warning::
    **Azure Credential Security**
    
    - Store the `clientSecret` securely using environment variables or encrypted configuration
    - Never commit secrets to version control systems
    - Rotate client secrets regularly before expiration
    - Use different Azure applications for different environments (dev/staging/prod)
    - Monitor Azure sign-in logs for unauthorized access

..  note::
    In *System > Configuration*, the extension masks the tenant ID, client ID and
    client secret held in ``TYPO3_CONF_VARS`` (via a hook of the lowlevel
    configuration module). Other places, such as TypoScript, are not masked.

..  _configuration-validation:

Configuration Validation
========================

To verify your configuration is correct:

..  rst-class:: bignums-xxl

1.  **Check in backend**:

    - Open **Admin Tools > Environment > Test Mail Setup** and send a test mail
    - Ensure the transport is set to `Exchange365Transport` and all required fields are filled

    ..  figure:: /_Images/image-test-mail-setup.png
        :alt: TYPO3 Environment module showing the Test Mail Setup
        :class: with-shadow
        :scale: 100

2.  **Check TYPO3 configuration**:
   
    ..  code-block:: php
   
        // In TYPO3 backend or debug context
        \TYPO3\CMS\Core\Utility\DebugUtility::debug($GLOBALS['TYPO3_CONF_VARS']['MAIL']);

3. **Test email sending**:
   
    ..  code-block:: php
   
        // Test email functionality
        $mail = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Core\Mail\MailMessage::class);
        $mail->to('test@example.com')
            ->subject('Test Email')
            ->text('This is a test email from TYPO3.')
            ->send();

4. **Check logs**: Monitor TYPO3 logs for authentication or sending errors.
   A failed send throws a Symfony ``TransportException`` (a ``RuntimeException``)
   whose message names the cause, for example
   ``Exchange 365 configuration missing required field: clientSecret``.

..  _testing:

Automated tests
===============

This branch is covered by the cross-version test matrix that lives on the
extension's main branch (``make test-matrix`` / ``make test-matrix-live`` there).
For TYPO3 v11 it runs the unit and functional tests, PHPStan and the coding
standard check, sends real mail through Microsoft Graph from the CLI and from the
frontend (credentials via ``:= getEnv()``), and checks in a headless browser that
the credentials are masked in the backend.

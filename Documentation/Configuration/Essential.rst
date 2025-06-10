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

The extension requires several configuration variables to be set in TYPO3. These can be configured through environment variables or directly in the TYPO3 configuration.

The following steps show the configuration with .env variables, but you can also set them in the `LocalConfiguration.php` file or through the TYPO3 Admin Panel if available.
..  note::
    The configuration variables are prefixed with `TYPO3_CONF_VARS__MAIL__transport_exchange365_` to avoid conflicts with other mail transports.

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

..  _configuration-example:

Configuration Example
=====================

Here's a complete example of all required configuration variables:

..  figure:: /_Images/image16.png
    :alt: TYPO3 configuration showing Exchange365 transport variables setup
    :class: with-shadow
    :scale: 100

Environment Variables (.env file)
----------------------------------

You can configure these settings using a `.env` file in your TYPO3 root directory:

..  code-block:: bash

    # Exchange 365 Mail Transport Configuration
    TYPO3_CONF_VARS__MAIL__transport=OliverKroener\\OkExchange365\\Mail\\Transport\\Exchange365Transport
    TYPO3_CONF_VARS__MAIL__transport_exchange365_tenantId='your-tenant-id-here'
    TYPO3_CONF_VARS__MAIL__transport_exchange365_clientId='your-client-id-here'
    TYPO3_CONF_VARS__MAIL__transport_exchange365_clientSecret='your-client-secret-here'
    TYPO3_CONF_VARS__MAIL__transport_exchange365_fromEmail='service@your-domain.com'

..  _alternative-configuration-methods:

Alternative Configuration Methods
=================================

In Typo3 config files
----------------------

Alternatively, you can add these settings directly to your TYPO3 configuration files, such as `config/system/settings.php or config/system/additional.php` or `typo3conf/LocalConfiguration.php`.

..  code-block:: php

    <?php
    return [
        // ...existing configuration...
        
        'MAIL' => [
            'transport' => 'OliverKroener\\OkExchange365\\Mail\\Transport\\Exchange365Transport',
            'transport_exchange365_tenantId' => 'your-tenant-id-here',
            'transport_exchange365_clientId' => 'your-client-id-here',
            'transport_exchange365_clientSecret' => 'your-client-secret-here',
            'transport_exchange365_fromEmail' => 'service@your-domain.com',
        ],
        
        // ...existing configuration...
    ];

Or using the $GLOBALS syntax:

..  code-block:: php

    <?php
    // In typo3conf/LocalConfiguration.php or ext_localconf.php
    $GLOBALS['TYPO3_CONF_VARS']['MAIL']['transport'] = 'OliverKroener\\OkExchange365\\Mail\\Transport\\Exchange365Transport';
    $GLOBALS['TYPO3_CONF_VARS']['MAIL']['transport_exchange365_tenantId'] = 'your-tenant-id-here';
    $GLOBALS['TYPO3_CONF_VARS']['MAIL']['transport_exchange365_clientId'] = 'your-client-id-here';
    $GLOBALS['TYPO3_CONF_VARS']['MAIL']['transport_exchange365_clientSecret'] = 'your-client-secret-here';
    $GLOBALS['TYPO3_CONF_VARS']['MAIL']['transport_exchange365_fromEmail'] = 'service@your-domain.com';

..  attention::
    - Replace placeholder values with your actual Azure configuration values
    - When using PHP syntax, use single backslashes (`\`) in class names instead of double backslashes
    - Keep the client secret secure and never commit it to version control

..  _testing-the-configuration:

Testing the Configuration
=========================

After configuring all variables, you can test the email functionality by:

1. Sending a test email through TYPO3's mail functionality
2. Checking the TYPO3 logs for any error messages
3. Verifying that emails are received at the intended recipients

..  tip::
    Enable TYPO3's developer log to see detailed information about the email sending process and any potential issues with the Exchange 365 integration.

..  _security-considerations:

Security Considerations
======================

..  warning::
    **Azure Credential Security**
    
    - Store the `clientSecret` securely using environment variables or encrypted configuration
    - Never commit secrets to version control systems
    - Rotate client secrets regularly before expiration
    - Use different Azure applications for different environments (dev/staging/prod)
    - Monitor Azure sign-in logs for unauthorized access

..  _configuration-validation:

Configuration Validation
========================

To verify your configuration is correct:

..  rst-class:: bignums-xxl

1.  **Check in backend**:

    - Navigate to the TYPO3 Admin Panel
    - Check the mail configuration under **Settings > Environment > Test Mail Setup**
    - Ensure the transport is set to `Exchange365Transport` and all required fields are filled

    ..  figure:: /_Images/image-test-mail-setup.png
        :alt: TYPO3 Admin Panel showing mail transport configuration
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

4. **Check logs**: Monitor TYPO3 logs for authentication or sending errors
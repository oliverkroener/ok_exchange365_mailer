..  _start:

Exchange 365 Mailer
===================

:Extension key:
   ok_exchange365_mailer

:Package name:
   oliverkroener/ok-exchange365-mailer

:Version:
   |release|

:Language:
   en

:Author:
   Oliver Kroener & Contributors

:License:
   This document is published under the
   `Open Publication License <https://www.opencontent.org/openpub/>`__.

:Rendered:
   |today|

.. _table-of-contents-1:

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
   365 using OAuth2.
-  **TYPO3 Compatibility**: Compatible with TYPO3 versions 9.5, 10.4,
   11.5, 12.4, and 13.
-  **Easy Setup**: Straightforward installation and configuration
   process.

Requirements
------------

-  **TYPO3 CMS**: Version 9.5, 10.4, 11.5, 12.4, 13, or dev-main.
-  **PHP Version**: Compatible with your TYPO3 version.
-  **Composer**: For installation via Composer.
-  **Dependencies**:

   -  `oliverkroener/ok-typo3-helper <https://packagist.org/packages/oliverkroener/ok-typo3-helper>`__:
      Version ^2.
   -  `microsoft/microsoft-graph <https://github.com/microsoftgraph/msgraph-sdk-php>`__:
      Version ^2.

Installation
------------

Step 1: Install via Composer
~~~~~~~~~~~~~~~~~~~~~~~~~~~~

Run the following command in your TYPO3 project root directory:

::

   composer require oliverkroener/ok-exchange365-mailer

This will install the extension along with its dependencies.

Step 2: Activate the Extension
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

1. Log in to your TYPO3 backend.
2. Navigate to **Extensions** module.
3. Find **ok_exchange365_mailer** in the list.
4. Click the activate icon to enable the extension.

Configuration
-------------

To configure the extension to send emails via Exchange 365, follow these
steps:

Step 1: Register an Application in Azure Portal
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

1. **Log in** to the `Azure Portal <https://portal.azure.com/>`__.
2. Navigate to **Azure Active Directory** > **App registrations**.
3. Click **New registration**.
4. **Name** your application (e.g., “TYPO3 Mailer”).
5. Set **Supported account types** as per your requirements.
6. Under **Redirect URI**, select **Web** and enter a URI (e.g.,
   ``https://yourdomain.com/typo3conf/ext/ok_exchange365_mailer/Authentication/OAuth2Callback``).
7. Click **Register**.

Step 2: Configure API Permissions
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

1. In your registered app, go to **API Permissions**.
2. Click **Add a permission**.
3. Select **Microsoft Graph**.
4. Choose **Application permissions**.
5. Find and add **Mail.Send** permission.
6. Click **Grant admin consent** to grant permissions.

Step 3: Create a Client Secret
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

1. Go to **Certificates & secrets**.
2. Click **New client secret**.
3. Provide a description and set an expiration.
4. Click **Add**.
5. Copy the **Value** of the client secret. **This is shown only once**.

Step 4: Configure the Extension in TYPO3
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

1. In TYPO3 backend, navigate to **Admin Tools** > **Settings** >
   **Extension Configuration**.
2. Find **ok_exchange365_mailer** and click the configuration icon.
3. Enter the following details:

   -  **Tenant ID**: Found under **Azure Active Directory** >
      **Properties**.
   -  **Client ID**: The **Application (client) ID** from your Azure
      app.
   -  **Client Secret**: The client secret value you copied.
   -  **Redirect URI**: The same URI you set in Azure.
   -  **From Email Address**: The email address that will appear as the
      sender.
   -  **From Name**: The sender’s display name.

4. Save the configuration.

Step 5: Authenticate with Microsoft Graph API
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

1. In TYPO3 backend, navigate to **Tools** > **ok_exchange365_mailer**
   module (if available).
2. Click on **Authenticate with Microsoft**.
3. Follow the authentication flow to grant access.
4. After successful authentication, the extension is ready to send
   emails.

Usage
-----

Once configured and authenticated, the extension will handle email
sending via Exchange 365 automatically. All emails sent by TYPO3 (system
emails, form notifications, etc.) will use the Microsoft Graph API.

Testing Email Sending
~~~~~~~~~~~~~~~~~~~~~

To verify that emails are being sent correctly:

1. Go to **System** > **Scheduler**.
2. Create a new task for **Execute console commands**.
3. Select a command that sends test emails (e.g., a custom command or
   extension command).
4. Run the task and check if the email is received.

Troubleshooting
---------------

Common Issues
~~~~~~~~~~~~~

-  **Authentication Errors**: Double-check your Tenant ID, Client ID,
   Client Secret, and Redirect URI.
-  **Permission Denied**: Ensure that **Mail.Send** permission is
   granted and admin consent is provided.
-  **Emails Not Sending**: Check TYPO3 logs for errors. Make sure the
   extension is active and properly configured.
-  **Access Token Expiry**: The extension should handle token refresh.
   If not, re-authenticate via the backend.

Checking Logs
~~~~~~~~~~~~~

-  **TYPO3 System Log**: Navigate to **Admin Tools** > **Log** to view
   system messages.
-  **PHP Error Log**: Check your server’s PHP error logs for any runtime
   errors.
-  **Microsoft Graph API Logs**: Use Azure Portal to monitor API calls
   and identify issues.

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

This extension is licensed under the `GNU General Public License v2.0 or
later <https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html>`__.

Author and Support
------------------

-  **Author**: Oliver Kroener
-  **Email**: ok@oliver-kroener.de
-  **Website**: `oliver-kroener.de <https://www.oliver-kroener.de>`__

For support, feature requests, or bug reports, please contact the author
via email.

*This documentation provides an overview and guidance on installing,
configuring, and using the ok_exchange365_mailer TYPO3 extension. For
advanced configurations and updates, refer to the official extension
repository or contact the author.*

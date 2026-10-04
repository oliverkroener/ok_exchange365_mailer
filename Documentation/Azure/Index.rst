..  include:: /Includes.rst.txt

..  _azure:

=======================================================
Configuration of Microsoft Entra ID (formerly Azure AD)
=======================================================

The extension authenticates as an application (OAuth 2.0 client credentials
flow). For that it needs an app registration in Microsoft Entra ID. This
guide assumes you have administrative access to Microsoft Entra ID and may
register applications and grant admin consent.

..  attention::

    The **Client ID** is shown on the overview page of the app registration
    and must not be confused with the **Secret ID** of a client secret. For
    the secret, only the secret **Value** is needed, not the Secret ID.

..  _azure-register:

Step 1: Register an application
===============================

#.  Log in to the `Azure Portal <https://portal.azure.com/>`__.
#.  Navigate to **Microsoft Entra ID** > **App registrations**.
#.  Click **New registration**.
#.  **Name** your application (for example "TYPO3 Mailer").
#.  Set **Supported account types** as per your requirements, usually
    "Accounts in this organizational directory only (Single tenant)".
#.  Click **Register**. No redirect URI is needed (client credentials flow).
#.  On the overview page, note the **Directory (tenant) ID** and the
    **Application (client) ID**.

..  _azure-permissions:

Step 2: Configure API permissions
=================================

#.  In your registered app, go to **API permissions**.
#.  Click **Add a permission**.
#.  Select **Microsoft Graph**.
#.  Choose **Application permissions**, because the application runs without
    user interaction.
#.  Find and add the **Mail.Send** and **User.ReadBasic.All** permissions.
#.  Click **Grant admin consent** to grant the permissions.

..  _azure-secret:

Step 3: Create a client secret
==============================

#.  Go to **Certificates & secrets**.
#.  Click **New client secret**.
#.  Provide a description and set an expiration.
#.  Click **Add**.
#.  Copy the **Value** of the client secret (not the Secret ID). **This is
    shown only once.**

..  attention::

    The secret value is sensitive. Store it securely, never commit it to
    version control, and renew it before it expires — an expired secret stops
    all mail.

..  _azure-result:

What you need for TYPO3
=======================

..  list-table::
    :header-rows: 1
    :widths: 40 60

    *   -   Value from Microsoft Entra ID
        -   TYPO3 setting
    *   -   Directory (tenant) ID
        -   ``transport_exchange365_tenantId``
    *   -   Application (client) ID
        -   ``transport_exchange365_clientId``
    *   -   Client secret **Value**
        -   ``transport_exchange365_clientSecret``
    *   -   A user or shared mailbox in the tenant
        -   ``transport_exchange365_fromEmail``

Continue with :ref:`configuration`.

..  seealso::

    -   `Register an application in Microsoft Entra ID
        <https://learn.microsoft.com/en-us/entra/identity-platform/quickstart-register-app>`__
    -   `Send mail from another user
        <https://learn.microsoft.com/en-us/graph/outlook-send-mail-from-other-user>`__
        (*Send As* / *Send On Behalf*)

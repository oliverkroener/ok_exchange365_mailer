:navigation-title: Azure Entra setup

..  _azure:

=======================================================
Configuration of Microsoft Entra ID (formerly Azure AD)
=======================================================

..  attention::
    The **Client ID** can be found on the overview page in Azure and **should not be confused with the Client Secret ID**. For the secret configuration, only the Secret value itself is required, not the Secret ID.

Please follow the steps below to configure Microsoft Entra ID (formerly Azure AD) for the TYPO3 extension **ok_exchange365_mailer**. This configuration is necessary to enable secure email sending through Microsoft Exchange 365 using the Graph API.
..  note::
    This guide assumes you have **administrative access to Microsoft Entra ID** and the necessary permissions to register applications.

..  rst-class:: bignums-xxl

1.  Register an application in Microsoft Entra ID (formerly Azure AD).
   
    Please go to https://portal.azure.com

    ..  figure:: /_Images/image1.png
        :alt: Azure portal home page with app registrations navigation
        :class: with-shadow
        :scale: 100

2.  Register the application in Microsoft Entra ID.

    ..  note::
        - **Name**: Choose a descriptive name for your application.
        - **Supported account types**: Select "Accounts in this organizational directory only (Single tenant)".

    ..  figure:: /_Images/image2.png
        :alt: Register application form with name and supported account types
        :class: with-shadow
        :scale: 100

3.  Register an application.

    .. attention::
        **Redirect URI**: No redirect URI is required since this application uses client credentials flow for server-to-server authentication without user delegation.

    ..  figure:: /_Images/image3.png
        :alt: Application registration form with redirect URI section
        :class: with-shadow
        :scale: 100

4.  Collect tenant ID and client ID.

    - **Tenant ID**: This is the unique identifier for your Microsoft Entra ID tenant.
    - **Client ID**: This is the unique identifier for your registered application.

    ..  attention::
        The **Client ID** can be found on the overview page in Azure and **should not be confused with the Secret ID**. For the secret configuration, only the Secret value itself is required, not the Secret ID.

    ..  figure:: /_Images/image4.png
        :alt: Application overview page showing Tenant ID and Client ID values
        :class: with-shadow
        :scale: 100

5.  Create a client secret.
    - Navigate to the "Certificates & secrets" section of your application.
    - Click on "New client secret".

    ..  figure:: /_Images/image5.png
        :alt: Certificates & secrets page with New client secret button
        :class: with-shadow
        :scale: 100

6.  Add client secret.

    ..  attention::
        - **Secret Value**: Copy the secret value immediately after creation. You won't be able to see it again.
  
        - **Expiry**: Ensure you manage the expiration of the secret and renew it before it expires to maintain uninterrupted service.

    ..  figure:: /_Images/image6.png
        :alt: Add client secret dialog with description and expiration settings
        :class: with-shadow
        :scale: 100

    ..  figure:: /_Images/image7.png
        :alt: Client secret creation confirmation with expiration date
        :class: with-shadow
        :scale: 100

7.  Copy secret value.

    - **Secret Value**: This is the value you will use in your TYPO3 configuration to authenticate with Microsoft Entra ID.
    - **Secret ID**: This is not required for the configuration, only the Secret **Value** is needed. This will be later on the **clientSecret** in the TYPO3 configuration.

    ..  attention::
        The **Secret Value** is sensitive information. Store it securely and do not expose it in public repositories or logs.

    ..  figure:: /_Images/image8.png
        :alt: Client secret value display with copy button (value visible only once)
        :class: with-shadow
        :scale: 100

8.  Assign API permissions.

    - Navigate to the "API permissions" section of your application.
    - Click on "Add a permission".

    ..  figure:: /_Images/image9.png
        :alt: API permissions page with Add a permission button
        :class: with-shadow
        :scale: 100

9.  Select Microsoft Graph.

    Choose "Microsoft Graph" as the API you want to access.

    ..  figure:: /_Images/image10.png
        :alt: Request API permissions dialog with Microsoft Graph selection
        :class: with-shadow
        :scale: 100

10. Select application permissions.

    Choose **Application permissions** since this application will run without user interaction.

    ..  figure:: /_Images/image11.png
        :alt: Permission type selection showing Application permissions option
        :class: with-shadow
        :scale: 100
    
11. Add **Mail.Send** permission.

    - This permission allows the application to send emails on behalf of users in your organization.
    - Choose *Mail.Send* (Send mail as any user) and "Add permissions".

    ..  figure:: /_Images/image12.png
        :alt: Microsoft Graph permissions list with Mail.Send permission highlighted
        :class: with-shadow
        :scale: 100

12. Add **User.ReadBasic.All** permission.

    - This permission allows the application to read basic user information, which is often necessary for sending emails on behalf of users.
    - Click on "Add permissions" after selecting the permission.

    ..  figure:: /_Images/image13.png
        :alt: Microsoft Graph permissions list with User.ReadBasic.All permission highlighted
        :class: with-shadow
        :scale: 100

13. Grant admin consent.

    - After adding the permissions, you need to grant admin consent for the permissions to take effect.
    - Click on "Grant admin consent for *[Your Organization Name]*".

    ..  attention::
        This step is crucial as it allows the application to use the permissions granted without requiring individual user consent.

    ..  figure:: /_Images/image14.png
        :alt: API permissions page with Grant admin consent button
        :class: with-shadow
        :scale: 100

    ..  figure:: /_Images/image15.png
        :alt: Admin consent confirmation dialog for granted permissions
        :class: with-shadow
        :scale: 100


:navigation-title: Exchange Online Setup

..  _exchange-setup:

========================
Exchange Online Setup
========================

To restrict app access to specific mailboxes, you need the Exchange Online PowerShell module.

..  note::
    Application Access Policies can currently *only* be configured via **PowerShell**. There is no option in the Microsoft Entra ID or Exchange Admin Center web interfaces at this time.

..  card:: Prerequisites
    :class: mb-4 border-primary

    **PowerShell 7.x** (latest recommended) is required for best compatibility. Windows PowerShell 5.1 works but may have limitations with newer module versions.

    Check your version:

    ..  code-block:: powershell

        $PSVersionTable.PSVersion

    Install or update to PowerShell 7:

    ..  code-block:: powershell

        winget install Microsoft.PowerShell

    Install the **ExchangeOnlineManagement** module. Run PowerShell as **Administrator**:

    ..  code-block:: powershell

        Install-Module -Name ExchangeOnlineManagement -Force -AllowClobber

..  toctree::
    :titlesonly:

    SharedMailboxes
    SenderDisplayName

Import and connect
==================

..  code-block:: powershell

    Import-Module ExchangeOnlineManagement
    Connect-ExchangeOnline -UserPrincipalName your-admin@yourdomain.com

This opens a browser window for authentication. Use an account with Exchange Admin rights.

Create the Application Access Policy
====================================

The ``New-ApplicationAccessPolicy`` cmdlet restricts your application to only access specific mailboxes instead of all mailboxes in the tenant.

**Parameters:**

- ``-AppId``: The Application (client) ID from your Microsoft Entra ID app registration
- ``-PolicyScopeGroupId``: The email address of the mailbox or mail-enabled security group the app is allowed to access
- ``-AccessRight RestrictAccess``: Limits the app to *only* the specified mailbox(es)
- ``-Description``: A human-readable description for the policy

..  code-block:: powershell

    New-ApplicationAccessPolicy -AppId "<your-app-id>" -PolicyScopeGroupId "shared@yourdomain.com" -AccessRight RestrictAccess -Description "Restrict to shared mailbox"

..  tip::
    Replace ``<your-app-id>`` with your actual Application ID and ``shared@yourdomain.com`` with the mailbox address you want to allow.

To verify the policy was created:

..  code-block:: powershell

    Get-ApplicationAccessPolicy | Format-List

To test if the policy works correctly:

..  code-block:: powershell

    Test-ApplicationAccessPolicy -Identity "shared@yourdomain.com" -AppId "<your-app-id>"

Troubleshooting
===============

..  code-block:: powershell

    # Check if connected
    Get-ConnectionInformation

    # Verify cmdlet exists
    Get-Command New-ApplicationAccessPolicy

    # Check PowerShell version (needs 5.1+)
    $PSVersionTable.PSVersion

    # List all existing policies
    Get-ApplicationAccessPolicy | Format-List

    # Remove a policy if needed
    Remove-ApplicationAccessPolicy -Identity "<policy-id>"

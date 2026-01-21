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

Using Shared Mailboxes
======================

..  attention::
    You **cannot** use a shared mailbox directly as the ``PolicyScopeGroupId`` – it requires a **mail-enabled security group**.

If you see an error like this when creating the policy:

..  code-block:: text

    The policy scope "shared@yourdomain.com" is not a valid mail-enabled security group.

You need to create a mail-enabled security group and add the shared mailbox to it.

Create a mail-enabled security group
------------------------------------

**1. Create the security group via PowerShell:**

..  code-block:: powershell

    New-DistributionGroup -Name "Graph API Shared Mailboxes" -Type Security -PrimarySmtpAddress "graph-mailboxes@yourdomain.com"

**2. Add your shared mailbox to the group:**

..  code-block:: powershell

    Add-DistributionGroupMember -Identity "Graph API Shared Mailboxes" -Member "shared@yourdomain.com"

**3. Verify the group was created correctly:**

..  code-block:: powershell

    Get-DistributionGroup -Identity "Graph API Shared Mailboxes" | Format-List
    Get-DistributionGroupMember -Identity "Graph API Shared Mailboxes"

**4. Now create the Application Access Policy using the group:**

..  code-block:: powershell

    New-ApplicationAccessPolicy -AppId "<your-app-id>" -PolicyScopeGroupId "graph-mailboxes@yourdomain.com" -AccessRight RestrictAccess -Description "Allow app to send from shared mailboxes"

**5. Test the policy:**

..  code-block:: powershell

    Test-ApplicationAccessPolicy -Identity "shared@yourdomain.com" -AppId "<your-app-id>"

You should see ``AccessCheckResult: Granted``.

..  warning::
    Policy changes can take **up to 30 minutes** to propagate. If the test shows "Denied" immediately after creating the policy, wait and try again.

Adding multiple mailboxes
-------------------------

Simply add additional mailboxes to the same security group you already created:

..  code-block:: powershell

    Add-DistributionGroupMember -Identity "Graph API Shared Mailboxes" -Member "another-shared@yourdomain.com"

The Application Access Policy applies to **all members** of the group – no need to create a new policy.

**Verify it worked:**

..  code-block:: powershell

    # List all members of the group
    Get-DistributionGroupMember -Identity "Graph API Shared Mailboxes"

    # Test the new mailbox
    Test-ApplicationAccessPolicy -Identity "another-shared@yourdomain.com" -AppId "<your-app-id>"

..  note::
    Changes can take up to 30 minutes to propagate. If the test doesn't show "Granted" immediately, wait and try again.

Alternative via Microsoft 365 Admin Center
------------------------------------------

1. Go to **Admin Center → Teams & Groups → Active teams & groups**
2. Click **Add a group** and select **Mail-enabled security**
3. Name the group (e.g., "Graph API Shared Mailboxes")
4. Add the shared mailbox as a member
5. Then run the ``New-ApplicationAccessPolicy`` command with that group's email address

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

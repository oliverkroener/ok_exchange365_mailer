:navigation-title: Shared Mailboxes

..  _shared-mailboxes:

======================
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

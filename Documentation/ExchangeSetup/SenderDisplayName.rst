:navigation-title: Sender Display Name

..  _sender-display-name:

====================================
Configuring the Sender Display Name
====================================

..  attention::
    By default, emails sent via the Microsoft Graph API from a shared mailbox may display the **mailbox address** or a generic name instead of the intended sender name. Recipients will see whatever **Display name** is configured on the mailbox in Exchange Online — not the name set in TYPO3.

    Hence, setting **$GLOBALS['TYPO3_CONF_VARS']['MAIL']['defaultMailFromName']** or **$GLOBALS['TYPO3_CONF_VARS']['MAIL']['defaultMailFromAddress']** in TYPO3 has **no effect** on the sender name shown to recipients. You must explicitly configure the display name in the Microsoft 365 or Exchange Admin Center for it to appear correctly.


When sending emails through the Graph API, the recipient sees the **display name** configured on the mailbox in Exchange Online. To ensure the correct sender name appears, you need to configure the mailbox properties in the Microsoft 365 Admin Center.

Configure via Microsoft 365 Admin Center
-----------------------------------------

..  rst-class:: bignums-xxl

1.  Open the **Microsoft 365 Admin Center**.

    Go to https://admin.microsoft.com and sign in with an admin account.

2.  Navigate to the mailbox.

    - For **shared mailboxes**: Go to **Teams & Groups → Shared mailboxes** (or **Active teams & groups** if the mailbox is a group).
    - For **regular user mailboxes**: Go to **Users → Active users**.

    Select the mailbox you configured as the ``fromEmail`` in your TYPO3 setup.

3.  Edit the display name.

    Click on the mailbox name to open its properties. Under **General**, update the **Display name** to the name you want recipients to see as the sender (e.g., "Oliver Kroener" or "Contact - Oliver Kroener").

    Click **Save changes**.

4.  Verify the sender display name.

    Send a test email through TYPO3 and verify that the recipient sees the correct sender name in their inbox.

Configure via Exchange Admin Center
------------------------------------

Alternatively, you can use the **Exchange Admin Center** for more granular mailbox settings:

..  rst-class:: bignums-xxl

1.  Open the **Exchange Admin Center**.

    Go to https://admin.exchange.microsoft.com and sign in with an admin account.

2.  Navigate to the mailbox.

    Go to **Recipients → Mailboxes** (for user mailboxes) or **Recipients → Groups → Shared mailboxes** (for shared mailboxes).

    Select the mailbox used as ``fromEmail``.

3.  Edit mailbox properties.

    Click on the mailbox to open its settings. Under **General**, you can configure:

    - **Display name**: The name shown to email recipients (e.g., "Oliver Kroener Website")
    - **Email addresses**: Add or modify email aliases if needed

    Click **Save** to apply the changes.

Configure via PowerShell
-------------------------

You can also update the display name via PowerShell:

..  code-block:: powershell

    # For a shared mailbox
    Set-Mailbox -Identity "shared@yourdomain.com" -DisplayName "Your Desired Sender Name"

    # Verify the change
    Get-Mailbox -Identity "shared@yourdomain.com" | Format-List DisplayName, PrimarySmtpAddress

..  note::
    The display name change takes effect immediately for new emails. Recipients of previously sent emails will continue to see the old display name.

..  tip::
    If you want the sender to show a specific name like "Contact Form - Your Company" instead of just the mailbox name, set the ``DisplayName`` accordingly. This is the name that appears in the **From** field in recipients' email clients.

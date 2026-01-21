:navigation-title: 
    Exchange 365 Mailer
    
..  _start:

=============================
Microsoft Exchange 365 Mailer
=============================

:Extension key: 
    ok_exchange365_mailer

:Package name:
    oliverkroener/ok-exchange365-mailer

:Version:
    |release|

:Language:
    en

:Author:
    Oliver Kroener <https://www.oliver-kroener.de> & Contributors

:License:
   This document is published under the
   `Open Publication License <https://www.opencontent.org/openpub/>`__.

:Rendered:
    |today|

..  toctree::
    :glob:
    :titlesonly:
    :hidden:
    :maxdepth: 2

    */Index
    Installation
    Azure
    ExchangeSetup
    Configuration/Index
    GetHelp
    [!Sitemap]

.. toctree::
   :hidden:

   Sitemap

    ----
    ..  meta::
        :description: TYPO3 extension for Microsoft Exchange 365 email integration using Graph API without SMTP
        :keywords: TYPO3, Exchange 365, Microsoft Graph API, OAuth 2.0, email integration
    ----

..  note::
    * **Purpose**: Enables TYPO3 to send emails through Microsoft Exchange 365 using Graph API instead of SMTP
    * **Authentication**: Uses OAuth 2.0 for secure token-based authentication
    * **API Integration**: Leverages Microsoft Graph API for direct email sending
    * **Scalability**: Handles high email volumes without additional infrastructure

..  attention::
    Since **Q3 2025**, Microsoft has enforced stricter access policies in *some* Exchange 365 tenants. You may need to configure an **Application Access Policy** to restrict app permissions to specific mailboxes. See :ref:`Exchange Online Setup <exchange-setup>`.

    This can also be used to restrict sending to *only* specific sender addresses (e.g., a **shared mailbox**).

..  card-grid::
    :columns: 1
    :columns-md: 2
    :gap: 4
    :class: pb-4
    :card-height: 100

    ..  card:: :ref:`Installation <installation>`

        Explains how to install this extension in Composer-based and Classic
        TYPO3 installations.

    ..  card:: :ref:`Configuration Microsoft Entra ID <azure>`

        Learn how to configure Microsoft Entra ID (formerly Microsoft AD) for this extension.

    ..  card:: :ref:`Exchange Online Setup <exchange-setup>`

        Configure Application Access Policies to restrict app permissions to specific mailboxes.

    ..  card:: :ref:`Configuration <configuration>`

        Learn how to configure this extension.

    ..  card:: :ref:`Frequently Asked Questions (FAQ) <faq>`

        These questions have been frequently asked.

    ..  card:: :ref:`How to get help <help>`

        Learn where to get help and how to report issues you found.

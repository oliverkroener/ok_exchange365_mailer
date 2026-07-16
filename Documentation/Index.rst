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
   `Oliver Kroener <https://www.oliver-kroener.de>`__ <ok@oliver-kroener.de>

:License:
   This document is published under the
   `Open Publication License <https://www.opencontent.org/openpub/>`__.

:Rendered:
    |today|

..  meta::
    :description: TYPO3 extension for Microsoft Exchange 365 email integration using Graph API without SMTP
    :keywords: TYPO3, Exchange 365, Microsoft Graph API, OAuth 2.0, email integration, Send As, Send On Behalf

..  toctree::
    :titlesonly:
    :hidden:
    :maxdepth: 2

    Installation
    Azure
    Configuration/Index
    Faq
    GetHelp
    Contact/Index
    Sitemap

..  note::
    * **Purpose**: Enables TYPO3 to send emails through Microsoft Exchange 365 using Graph API instead of SMTP
    * **Authentication**: Uses OAuth 2.0 for secure token-based authentication
    * **API Integration**: Leverages Microsoft Graph API for direct email sending
    * **Scalability**: Handles high email volumes without additional infrastructure


..  card-grid::
    :columns: 1
    :columns-md: 2
    :gap: 4
    :class: pb-4
    :card-height: 100

    ..  card:: Installation

        Explains how to install this extension in Composer-based and Classic
        TYPO3 installations.

        ..  card-footer:: :ref:`Get started <installation>`
            :button-style: btn btn-primary

    ..  card:: Microsoft Entra ID

        Learn how to configure Microsoft Entra ID (formerly Azure AD) for this
        extension.

        ..  card-footer:: :ref:`Set up Azure <azure>`
            :button-style: btn btn-primary

    ..  card:: Configuration

        Learn how to configure this extension for backend and frontend email.

        ..  card-footer:: :ref:`Configure <configuration>`
            :button-style: btn btn-primary

    ..  card:: FAQ

        Answers to frequently asked questions about this extension.

        ..  card-footer:: :ref:`Read the FAQ <faq>`
            :button-style: btn btn-primary

    ..  card:: Get Help

        Learn where to get help and how to report issues you found.

        ..  card-footer:: :ref:`Get help <help>`
            :button-style: btn btn-primary

    ..  card:: Contact

        Get in touch with the author for support, questions, or contributions.

        ..  card-footer:: :ref:`Get in touch <contact>`
            :button-style: btn btn-primary

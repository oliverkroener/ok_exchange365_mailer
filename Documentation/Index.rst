..  include:: /Includes.rst.txt

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

----

A TYPO3 extension for sending emails via Microsoft Exchange 365 using the
MS Graph API instead of SMTP. Uses OAuth 2.0 client credentials flow for
secure, token-based authentication.

..  attention::
    Since **Q3 2025**, Microsoft has enforced stricter access policies in *some* Exchange 365 tenants. You may need to configure an **Application Access Policy** to restrict app permissions to specific mailboxes. See :ref:`Exchange Online Setup <exchange-setup>`.

    This can also be used to restrict sending to *only* specific sender addresses (e.g., a **shared mailbox**).

..  card-grid::
    :columns: 1
    :columns-md: 2
    :gap: 4
    :class: pb-4
    :card-height: 100

    ..  card:: Introduction

        ..  card-image:: /Images/Icons/introduction.svg
            :alt: Open book icon

        Learn what this extension does, its features, and system requirements.

        ..  card-footer:: :ref:`Learn more <introduction>`
            :button-style: btn btn-primary

    ..  card:: Installation

        ..  card-image:: /Images/Icons/installation.svg
            :alt: Download arrow icon

        Install the extension via Composer and activate it in your TYPO3 project.

        ..  card-footer:: :ref:`Get started <installation>`
            :button-style: btn btn-primary

    ..  card:: Compatibility

        ..  card-image:: /Images/Icons/compatibility.svg
            :alt: Layered versions icon

        Find the right extension version for your TYPO3 major — one release line per major.

        ..  card-footer:: :ref:`View matrix <compatibility>`
            :button-style: btn btn-primary

    ..  card:: Microsoft Entra ID Setup

        ..  card-image:: /Images/Icons/azure.svg
            :alt: Cloud with key icon

        Register an app in Microsoft Entra ID (formerly Azure AD) and configure
        API permissions for Graph API mail sending.

        ..  card-footer:: :ref:`Configure Azure <azure>`
            :button-style: btn btn-primary

    ..  card:: Exchange Online Setup

        ..  card-image:: /Images/Icons/exchange-setup.svg
            :alt: Shield icon

        Configure Application Access Policies to restrict app permissions to
        specific mailboxes using PowerShell.

        ..  card-footer:: :ref:`View guide <exchange-setup>`
            :button-style: btn btn-primary

    ..  card:: Configuration

        ..  card-image:: /Images/Icons/configuration.svg
            :alt: Settings sliders icon

        Set up the extension via environment variables, TYPO3 settings, or
        TypoScript for frontend form integration.

        ..  card-footer:: :ref:`Configure <configuration>`
            :button-style: btn btn-primary

    ..  card:: Development

        ..  card-image:: /Images/Icons/development.svg
            :alt: Code brackets icon

        Architecture internals, the automated cross-version test matrix, and how to
        contribute.

        ..  card-footer:: :ref:`Read more <development>`
            :button-style: btn btn-primary

    ..  card:: FAQ

        ..  card-image:: /Images/Icons/faq.svg
            :alt: Question mark icon

        Answers to frequently asked questions about installation, configuration,
        and usage.

        ..  card-footer:: :ref:`Read FAQ <faq>`
            :button-style: btn btn-primary

    ..  card:: Contact

        ..  card-image:: /Images/Icons/contact.svg
            :alt: Envelope icon

        Get in touch with the author for support, questions, or contributions.

        ..  card-footer:: :ref:`Get in touch <contact>`
            :button-style: btn btn-primary


..  toctree::
    :maxdepth: 2
    :titlesonly:
    :hidden:

    Introduction/Index
    Installation
    Compatibility
    Azure
    ExchangeSetup/Index
    Configuration/Index
    Development/Index
    Faq
    GetHelp
    Contact/Index

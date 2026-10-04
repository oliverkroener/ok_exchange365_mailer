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

**A mail transport for TYPO3 9.5 LTS that sends every TYPO3 mail through
Microsoft Exchange 365 with the Microsoft Graph API and OAuth 2.0 — no SMTP
required.**

This manual covers the 1.x line of the extension (TYPO3 9.5 LTS, PHP 7.2 – 7.4,
SwiftMailer). Other TYPO3 versions are served by other major versions of the
extension.

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

        Install the extension via Composer and activate it in your TYPO3
        project.

        ..  card-footer:: :ref:`Get started <installation>`
            :button-style: btn btn-primary

    ..  card:: Microsoft Entra ID

        ..  card-image:: /Images/Icons/azure.svg
            :alt: Cloud with key icon

        Register the application in Microsoft Entra ID (formerly Azure AD)
        and collect the tenant ID, client ID and client secret.

        ..  card-footer:: :ref:`Set up Azure <azure>`
            :button-style: btn btn-primary

    ..  card:: Configuration

        ..  card-image:: /Images/Icons/configuration.svg
            :alt: Settings sliders icon

        Select the transport, provide the credentials from the environment
        and optionally override them per site with TypoScript.

        ..  card-footer:: :ref:`Configure <configuration>`
            :button-style: btn btn-primary

    ..  card:: FAQ

        ..  card-image:: /Images/Icons/faq.svg
            :alt: Question mark icon

        Answers to frequent questions, error messages and where to look when
        a mail is not sent.

        ..  card-footer:: :ref:`Read the FAQ <faq>`
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
    Installation/Index
    Azure/Index
    Configuration/Index
    Faq/Index
    Contact/Index

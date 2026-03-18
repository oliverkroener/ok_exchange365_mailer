:navigation-title: Introduction

..  _introduction:

============
Introduction
============

What does it do?
================

The **Microsoft Exchange 365 Mailer** extension replaces TYPO3's default SMTP
mail transport with a custom transport that sends emails via the
`Microsoft Graph API <https://learn.microsoft.com/en-us/graph/api/user-sendmail>`__.

Instead of configuring an SMTP server, you register an application in
**Microsoft Entra ID** (formerly Azure AD) and authenticate using the
**OAuth 2.0 client credentials flow** — no user interaction or stored
passwords required.

Features
========

-  Send emails through Microsoft Graph API — no SMTP required
-  OAuth 2.0 client credentials flow for server-to-server authentication
-  Supports both backend (environment variables / TYPO3 settings) and
   frontend (TypoScript) configuration
-  Compatible with Powermail, TYPO3 Form Framework, and other form
   extensions
-  Optional saving of sent emails to the sender's "Sent Items" folder
-  Automatic credential blinding in TYPO3's configuration module
-  Works with shared mailboxes and Application Access Policies

Requirements
============

-  **TYPO3**: 12.4 LTS, 13.4 LTS, or 14.x
-  **PHP**: 8.3+
-  **Dependencies**:

   -  ``microsoft/microsoft-graph`` ^2 — Microsoft Graph SDK
   -  ``oliverkroener/ok-typo3-helper`` — provides ``MSGraphMailApiService``
      for message conversion

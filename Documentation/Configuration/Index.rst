:navigation-title: Configuration

..  _configuration:

=============
Configuration
=============

This section covers all aspects of configuring the Microsoft Exchange 365 Mailer extension for TYPO3.

After completing the :ref:`Azure Configuration <azure>`, you need to configure the TYPO3 extension with the values obtained from Microsoft Entra ID.

..  toctree::
    :titlesonly:

    Essential
    Frontend

Quick Configuration Overview
============================

The extension requires the following key configuration variables:

1. **Mail Transport**: Set to use Exchange365Transport
2. **Tenant ID**: Your Microsoft Entra ID tenant identifier
3. **Client ID**: Your Azure application identifier
4. **Client Secret**: Your Azure application secret
5. **From Email**: The sender email address
6. **Graph Sender User ID** (optional): Graph mailbox for *Send As* / *Send On Behalf*
7. **Save to Sent Items** (optional): Whether to save emails to sent folder

For detailed step-by-step instructions, see:

- :ref:`Essential Configuration <essential>` - For server-side email sending (recommended for production)
- :ref:`Frontend Configuration <frontend>` - Optional per-site TypoScript overrides for frontend mail (Powermail, Form Framework)

Configuration Methods
=====================

The transport is selected only in ``$GLOBALS['TYPO3_CONF_VARS']['MAIL']['transport']``.
The credentials and sender can come from:

**Essential Configuration** (baseline for every context)
    - Environment variables, mapped onto ``TYPO3_CONF_VARS`` by your project
    - :file:`AdditionalConfiguration.php` reading the environment

**Frontend Configuration** (optional per-site overrides)
    - TypoScript, ideally with ``:= getEnv(...)``. Non-empty TypoScript values
      override ``TYPO3_CONF_VARS`` per setting; empty values fall back.

If ``TYPO3_CONF_VARS`` is configured, frontend forms (Powermail, Form Framework)
work without any TypoScript.

..  attention::
    **Security Recommendation**: Never write the client secret as a literal value
    into TypoScript or configuration files. Read it from the environment.

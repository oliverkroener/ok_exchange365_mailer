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

For detailed step-by-step instructions, see:

- :ref:`Essential Configuration <essential>` - For server-side email sending (recommended for production)
- :ref:`Frontend Configuration <frontend>` - For form-based email sending (Powermail, Form Framework)

Configuration Methods
====================

You can configure this extension using:

**Essential Configuration** (Recommended)
    - Environment variables (`.env` file)
    - TYPO3 LocalConfiguration.php
    - TYPO3 Admin Panel (if available)

**Frontend Configuration** (For Forms)
    - TypoScript configuration
    - Required for Powermail, Form Framework, and other frontend forms

Choose the method that best fits your deployment workflow and security requirements.

..  attention::
    **Security Recommendation**: Use backend configuration with environment variables for production environments to avoid exposing sensitive Azure credentials in TypoScript.
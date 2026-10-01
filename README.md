# Exchange365 Mailer extension

Extension for Typo3 to enable mails with Exchange 365 without enabling SMTP.

## **Enabling Microsoft Exchange 365 Mail Integration for TYPO3 Without SMTP**

### **Seamless Email Integration Using Microsoft Graph API**

Integrating Microsoft Exchange 365 mail services with TYPO3, a widely used content management system, can present challenges, particularly for those looking to avoid the traditional SMTP protocol. A specialized TYPO3 extension now offers a solution by enabling seamless email integration with Exchange 365 without the need for SMTP. This extension utilizes the Microsoft Graph API to send emails directly, providing a secure and efficient method for organizations that prefer not to use SMTP and wish to leverage the modern API-driven capabilities of Microsoft 365.

### **Enhanced Security Through OAuth 2.0 and API-Based Communication**

At the core of this extension's functionality is the use of OAuth 2.0 authentication in conjunction with the Microsoft Graph API to send emails. This method, recommended by Microsoft, provides a secure token-based approach, eliminating the need to store SMTP credentials within the TYPO3 environment. By using Microsoft Graph API to send emails, the extension ensures that communication with Exchange 365 servers is conducted securely and in compliance with data protection standards. This approach is especially beneficial in sectors like healthcare, finance, and government, where data security and regulatory compliance are crucial.

### **Improved Performance by Bypassing SMTP Overheads**

By sending emails via the Microsoft Graph API instead of relying on SMTP, this extension helps improve overall performance and reduces latency in email communication. The API-based approach allows for direct communication with Exchange 365 servers, bypassing the traditional SMTP handshaking and authentication processes, which can often lead to delays. This results in faster email delivery and a more responsive TYPO3 environment, which is particularly advantageous for websites or organizations with high volumes of email traffic.

### **Cost-Effective, Scalable, and Future-Proof Solution**

This TYPO3 extension offers a cost-effective and scalable solution for businesses by utilizing the Microsoft Graph API to handle email sending. It removes the need for SMTP server setup and maintenance, reducing infrastructure costs. Additionally, the extension's reliance on Microsoft Graph API makes it inherently scalable, capable of handling significant email traffic without requiring additional resources. This future-proof approach aligns with Microsoft's push towards API-driven services, ensuring that businesses can leverage the latest technologies and remain adaptable to future changes in the Microsoft ecosystem.

## Requirements

- TYPO3 **9.5 LTS** (this is the 1.x line), PHP 7.2 – 7.4
- A Microsoft Entra ID app registration with the `Mail.Send` application permission and admin consent

## Configure TYPO3

The transport is selected **only** in `$GLOBALS['TYPO3_CONF_VARS']['MAIL']['transport']`
— there is no TypoScript option for it. The settings below are used in every context
(frontend, backend, CLI, scheduler).

| Variable | Description |
|----------|-------------|
| `TYPO3_CONF_VARS__MAIL__transport` | `OliverKroener\OkExchange365\Mail\Transport\Exchange365Transport` |
| `TYPO3_CONF_VARS__MAIL__transport_exchange365_tenantId` | Microsoft Entra ID Tenant ID |
| `TYPO3_CONF_VARS__MAIL__transport_exchange365_clientId` | Azure Application (Client) ID |
| `TYPO3_CONF_VARS__MAIL__transport_exchange365_clientSecret` | Azure Application Secret Value |
| `TYPO3_CONF_VARS__MAIL__transport_exchange365_fromEmail` | Sender email address (must exist in Exchange 365) |
| `TYPO3_CONF_VARS__MAIL__transport_exchange365_graphSenderUserId` | *(optional)* Graph mailbox used for `/users/{id}/sendMail` (*Send As* / *Send On Behalf*). Falls back to the message `From`, then `fromEmail`, then `MAIL.defaultMailFromAddress` |
| `TYPO3_CONF_VARS__MAIL__transport_exchange365_saveToSentItems` | `1` to save to Sent Items, `0` to skip (default: `0`; the static template defaults to `1` in the frontend) |

> **TYPO3 does not map `TYPO3_CONF_VARS__…` variables by itself.** Your project needs a
> small loop in `public/typo3conf/AdditionalConfiguration.php` that copies them into
> `$GLOBALS['TYPO3_CONF_VARS']`, or set the values there with `getenv()`. Both are shown
> in [the documentation](Documentation/Index.rst). Never write the client secret as a
> literal into `LocalConfiguration.php` or `AdditionalConfiguration.php`.

A failed send throws a `RuntimeException`, e.g.
`… Error: Exchange 365 configuration missing required field: clientSecret`. Test the
setup in *Admin Tools > Environment > Test Mail Setup*. Tenant ID, client ID and client
secret from `TYPO3_CONF_VARS` are masked in *System > Configuration*.

### Via TypoScript (per-site overrides in the frontend)

Optional. Include the static template *[kroener.DIGITAL] Exchange 365 Mailer*, then read
the credentials from the environment with `getEnv()` — never write the secret into
TypoScript:

```typoscript
plugin.tx_okexchange365mailer.settings.exchange365 {
    tenantId := getEnv(EXCHANGE365_TENANT_ID)
    clientId := getEnv(EXCHANGE365_CLIENT_ID)
    clientSecret := getEnv(EXCHANGE365_CLIENT_SECRET)
    fromEmail = service@your-domain.com
    # Optional: route via a different mailbox using Send As / Send On Behalf
    # graphSenderUserId = service@your-domain.com
    saveToSentItems = 1
}
```

Non-empty TypoScript values override `TYPO3_CONF_VARS` **per setting**; empty values fall
back (except `saveToSentItems`, where empty means `0`). This works in setup and constants.

`getEnv()` reads PHP's real process environment (`getenv()`), not `$_ENV`: variables
loaded only into `$_ENV` (symfony/dotenv, helhum/dotenv-connector default) are invisible,
so set them where the web server starts PHP or use `putenv()`. A variable that is not set
leaves the previous value in place, and the result is cached until the next cache flush.
TypoScript is not sent to the browser, but it is readable in the backend and part of every
database dump.

## Testing

This branch is covered by the cross-version test matrix on the extension's main branch
(`make test-matrix` / `make test-matrix-live` there). For TYPO3 9.5 it runs unit,
functional, PHPStan and coding-standard checks, sends real mail through Microsoft Graph
from the CLI and from the frontend (credentials only via `:= getEnv()`), and checks in a
headless browser that the credentials are masked in the backend.

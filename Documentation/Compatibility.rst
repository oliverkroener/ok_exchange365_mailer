:navigation-title: Compatibility

..  include:: /Includes.rst.txt

..  _compatibility:

==========================
Version Compatibility
==========================

This extension is maintained as **one release line per TYPO3 major**. Composer selects
the matching line automatically when you run
:composer:`oliverkroener/ok-exchange365-mailer` without a version constraint.

..  _compatibility-matrix:

Support matrix
==============

..  list-table::
    :header-rows: 1
    :widths: 14 12 24 16 12 22

    *   -   TYPO3
        -   Extension
        -   Branch
        -   PHP
        -   Graph SDK
        -   Status

    *   -   14.x
        -   4.4.x
        -   ``main``
        -   8.2 – 8.5
        -   ^2
        -   Active
    *   -   13.4 LTS
        -   4.4.x
        -   ``main``
        -   8.2 – 8.5
        -   ^2
        -   Active
    *   -   12.4 LTS
        -   4.4.x
        -   ``main``
        -   8.1 – 8.4
        -   ^2
        -   Active
    *   -   11.5 ELTS
        -   3.2.x
        -   ``feature-typo3-11``
        -   7.4 – 8.3
        -   ^2
        -   Maintenance
    *   -   10.4 ELTS
        -   2.2.x
        -   ``feature-typo3-10``
        -   7.2 – 7.4
        -   none (Guzzle)
        -   Maintenance
    *   -   9.5
        -   1.1.x
        -   ``feature-typo3-9``
        -   7.2 – 7.4
        -   none (Guzzle)
        -   Maintenance

..  note::
    The 1.x line for TYPO3 9.5 predates Symfony Mailer — TYPO3 9.5 still used
    SwiftMailer — so its transport is a ``Swift_Transport`` rather than the
    architecture described in :ref:`Architecture <architecture>`. Like every other
    line, it is covered by the :ref:`test matrix <testing>`. The 1.x and 2.x lines
    call Microsoft Graph directly over HTTP instead of through the Graph SDK.

Pinning a version
=================

Composer resolves the correct line on its own. Pin explicitly only when you need to:

..  code-block:: bash

    # TYPO3 12, 13 or 14
    composer require oliverkroener/ok-exchange365-mailer:^4.4

    # TYPO3 11
    composer require oliverkroener/ok-exchange365-mailer:^3.2

    # TYPO3 10
    composer require oliverkroener/ok-exchange365-mailer:^2.2

If :command:`composer require` reports that the package cannot be resolved, the
cause is almost always a TYPO3 major that the requested version does not cover —
check the table above rather than forcing the constraint.

What differs between the lines
==============================

The 4.x line is the reference implementation. The older lines differ in ways that
matter if you read the source or report a bug:

*   **Configuration precedence.** From 2.2.0 / 3.2.0 onwards all lines overlay
    frontend TypoScript on top of the mail settings **per key**. Earlier 2.x and 3.x
    releases let a frontend TypoScript configuration *replace* the mail settings
    wholesale.
*   **Frontend TypoScript access.** The 4.x line reads the ``frontend.typoscript``
    request attribute. The 2.x and 3.x lines read ``$GLOBALS['TSFE']->tmpl->setup``,
    which is the correct API on TYPO3 10 and 11 but was removed in TYPO3 13.
*   **Credential blinding.** The 4.x line uses a PSR-14 event listener, the 3.x line
    a hook. The 2.x line does not blind credentials at all.
*   **Graph SDK.** The 2.x line uses ``microsoft/microsoft-graph`` v1
    (``Microsoft\Graph\Graph``); 3.x and 4.x use v2 (``GraphServiceClient``).

..  seealso::
    :ref:`Architecture <architecture>` describes the 4.x implementation in detail.

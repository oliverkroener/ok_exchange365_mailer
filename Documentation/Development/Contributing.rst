:navigation-title: Contributing

..  include:: /Includes.rst.txt

..  _contributing:

==========================
Contributing
==========================

Contributions are welcome. The one thing worth knowing before you open a pull
request is that this repository does **not** use a single main line.

..  _contributing-branches:

The branch-per-major model
==========================

Each supported TYPO3 major has its own long-lived branch:

..  list-table::
    :header-rows: 1
    :widths: 32 20 48

    *   -   Branch
        -   TYPO3
        -   Use it for

    *   -   ``main``
        -   12.4, 13.4, 14.x
        -   All new features and fixes.
    *   -   ``feature-typo3-11``
        -   11.5 ELTS
        -   Backports only.
    *   -   ``feature-typo3-10``
        -   10.4 ELTS
        -   Backports only.
    *   -   ``feature-typo3-9``
        -   9.5
        -   Backports only.

**Target ``main`` unless the bug only exists on an older line.** A fix that matters
for the maintenance branches is applied to ``main`` first and then backported, so the
history stays readable.

..  note::
    The branches are not simply older copies of ``main``. They differ in the Graph
    SDK major, in how frontend TypoScript is read, and in how credentials are blinded.
    :ref:`Version Compatibility <compatibility>` lists the differences.

..  _contributing-workflow:

Before opening a pull request
=============================

#.  **Run the test matrix** for the majors your change affects:

    ..  code-block:: bash

        make test-matrix

    See :ref:`Testing <testing>`. A change to ``main`` should be green on v12, v13
    and v14.

#.  **Run static analysis and the coding standards fixer**, in that order — PHPStan
    first, :command:`php-cs-fixer` second:

    ..  code-block:: bash

        Build/Scripts/runTests.sh --layers=phpstan,cgl

#.  **Keep** :file:`declare(strict_types=1)` on new classes. The TYPO3 coding
    standards preset does not enforce it, so a file missing it passes the fixer
    silently.

#.  **Update the documentation** in :file:`Documentation/` when you change behaviour
    or add a setting. Render it locally with :command:`make docs`.

..  _contributing-settings:

Adding or renaming a setting
============================

A setting exists in four places, and all four must change together:

#.  :file:`Configuration/TypoScript/constants.typoscript`
#.  :file:`Configuration/TypoScript/setup.typoscript`
#.  :file:`Configuration/Sets/Exchange365Mailer/settings.definitions.yaml`
#.  :php:`getMailSettingsConfiguration()` in
    :file:`Classes/Mail/Transport/Exchange365Transport.php`

Missing one of them produces a setting that is editable in the backend but never
reaches the transport — or the reverse.

..  _contributing-releasing:

Releasing
=========

The version appears in five places and they drift easily:

*   :file:`composer.json` → ``version``
*   :file:`ext_emconf.php` → ``version``
*   :file:`README.md` → the version badge URL
*   :file:`Documentation/guides.xml` → ``release``
*   :file:`Documentation/**/*.rst` → any ``versionadded`` directive

Find every occurrence before tagging:

..  code-block:: bash

    grep -rn "<version>" . --exclude-dir=Documentation-GENERATED-temp --exclude-dir=.git

..  note::
    :command:`composer validate` warns that the ``version`` field is present. That is
    intentional here, for TER and :file:`ext_emconf.php` parity — it is not something
    to "fix".

..  _contributing-help:

Getting in touch
================

Questions, bug reports and feature requests belong in the
`issue tracker <https://github.com/oliverkroener/ok_exchange365_mailer/issues>`__ or
the `discussions <https://github.com/oliverkroener/ok_exchange365_mailer/discussions>`__.
See also :ref:`Where to get help <help>`.

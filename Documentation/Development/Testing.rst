:navigation-title: Testing

..  include:: /Includes.rst.txt

..  _testing:

==========================
Testing
==========================

The extension ships an automated **cross-version test matrix**. It provisions one
throwaway TYPO3 installation per supported major, wires the working tree in as a
Composer path repository, and runs the test layers against each one.

..  _testing-requirements:

Requirements
============

*   `DDEV <https://ddev.com/>`__ 1.24 or newer, and a running Docker daemon.
*   Roughly 6 GB of free disk — each lab is about 600–900 MB of :file:`vendor/`
    plus a database volume.
*   Git, because three of the labs test a different branch of this repository.
*   For the live checks: Node.js 18 or newer (the browser check installs
    ``playwright-core`` into :file:`Build/testing/browser/` on first use).

..  _testing-running:

Running the matrix
==================

From the extension directory:

..  code-block:: bash

    make test-matrix

A cold run provisions six labs and takes roughly 30–60 minutes. Warm re-runs reuse
the existing labs and take a few minutes per lab.

Useful variants:

..  code-block:: bash

    make test-matrix                        # all majors, offline layers only
    make test-matrix-live                   # additionally the live checks, see below
    make test-matrix-clean                  # delete all labs and worktrees
    make install-hooks                      # gate tag pushes on a green matrix

    Build/Scripts/runTests.sh --versions=13,14   # a subset
    Build/Scripts/runTests.sh --keep-labs        # never delete, even when green
    Build/Scripts/runTests.sh --layers=unit      # one layer only
    Build/Scripts/runTests.sh --status           # what state is each lab in?

The layer names are ``resolve``, ``install``, ``canary``, ``unit``,
``functional``, ``phpstan`` and ``cgl``.

..  _testing-matrix:

What is tested where
====================

The matrix is defined in :file:`Build/matrix.json`. Three of the six labs check out
a **different branch**, because the extension is maintained as one release line per
TYPO3 major — see :ref:`Version Compatibility <compatibility>`.

..  list-table::
    :header-rows: 1
    :widths: 14 30 12 44

    *   -   TYPO3
        -   Branch tested
        -   PHP
        -   Source wired into the lab

    *   -   14.3
        -   ``main``
        -   8.2
        -   the working tree
    *   -   13.4
        -   ``main``
        -   8.2
        -   the working tree
    *   -   12.4
        -   ``main``
        -   8.1
        -   the working tree
    *   -   11.5
        -   ``feature-typo3-11``
        -   8.1
        -   a :command:`git worktree`
    *   -   10.4
        -   ``feature-typo3-10``
        -   7.4
        -   a :command:`git worktree`
    *   -   9.5
        -   ``feature-typo3-9``
        -   7.4
        -   a :command:`git worktree`

Coding standards are checked once per branch, in its newest lab: the older labs
resolve an older ``typo3/coding-standards`` whose rules contradict the newer ones,
and style is a property of the source, not of the TYPO3 version.

..  _testing-layers:

The test layers
===============

Each lab runs these in order — cheapest and most likely to fail first:

..  list-table::
    :header-rows: 1
    :widths: 24 76

    *   -   Layer
        -   What it proves

    *   -   Composer resolve
        -   The declared constraints actually resolve against that TYPO3 major. A
            conflict here is a **finding**, not an error — it means the advertised
            compatibility is wrong.
    *   -   TYPO3 install
        -   A real, installed TYPO3 site comes up on that major.
    *   -   Wiring canary
        -   The lab is testing **your working tree** and not a copy fetched from
            Packagist. See the warning below.
    *   -   PHPUnit unit
        -   Configuration precedence, the empty-value guard, the
            ``saveToSentItems`` exemption, credential validation, the sender
            resolution order, client/token reuse and error wrapping. On the 1.x
            and 2.x lines, the complete send path including retries runs against
            a mocked HTTP client.
    *   -   PHPUnit functional
        -   The extension activates, and TYPO3 really selects this transport when
            ``MAIL.transport`` is set to the fully-qualified class name.
    *   -   PHPStan
        -   Static analysis at level 8 against that major's core API.
    *   -   Coding standards
        -   TYPO3 CGL via :command:`php-cs-fixer`.
    *   -   Live checks
        -   Skipped unless explicitly enabled. See below.

..  warning::
    The **wiring canary** is not a formality. It writes a temporary file into the
    extension source on the host and checks that it appears inside the container. If
    the path repository is not wired correctly, Composer silently installs a release
    copy from Packagist instead, and every green result in that lab is meaningless.
    A failed canary marks the lab ``wiring`` and invalidates its results.

..  note::
    TYPO3 11 and 12 are **ELTS**. Every freely published patch of those majors
    carries security advisories, so Composer's audit would refuse to load any of
    them and the major could not be tested at all. The harness therefore sets
    ``policy.advisories.block=false`` *inside the lab*. That is safe for a
    throwaway installation, but it means a lab is **not** a security-current
    install — the report's environment header says so on every run.

..  _testing-coverage-gaps:

Known coverage gaps
===================

..  important::
    On the 3.x and 4.x lines, the call into the Microsoft Graph SDK itself is only
    executed by the live checks. Everything around it — configuration, sender
    resolution, client reuse and the error wrapping — has offline tests through
    the :php:`createGraphServiceClient()` seam.

..  _testing-live:

The live checks
===============

With ``--live`` (``make test-matrix-live``), each lab additionally runs four checks
against the real Microsoft Graph API. They use a lab-only fixture from
:file:`Build/testing/` that is mounted into the lab and never shipped.

..  list-table::
    :header-rows: 1
    :widths: 24 76

    *   -   Check
        -   What it proves
    *   -   ``live-cli``
        -   A real mail is accepted by Graph in **CLI context**, with the
            credentials in ``TYPO3_CONF_VARS``.
    *   -   ``browser``
        -   A headless Chromium logs into the backend, opens
            *System > Configuration* and finds the client secret **masked** — never
            in plain text. Screenshots are stored with the report.
    *   -   ``live-frontend``
        -   A real mail is accepted by Graph in **frontend context**, while
            ``TYPO3_CONF_VARS`` hold **no** credentials: they come only from
            ``:= getEnv(...)`` in the root template's setup and constants. This
            proves the documented :ref:`getEnv() configuration <frontend-getenv>`
            on that TYPO3 version.
    *   -   ``getenv-missing``
        -   A ``getEnv()`` of a variable that does not exist leaves the send
            failing cleanly with ``missing required field: clientSecret``.

Every message has the subject ``[ex365-matrix] <run> v<major> <context>``, so a run
can be traced in the mailbox. A full run sends two messages per lab.

#.  Create the credentials file **outside** the repository:

    ..  code-block:: bash

        mkdir -p ~/.config/ok-ex365
        cp Build/testing/.env.test.dist ~/.config/ok-ex365/test.env
        chmod 600 ~/.config/ok-ex365/test.env

    Fill in a test tenant. ``EXCHANGE365_TEST_RECIPIENT`` defaults to the sender
    address. Set ``OK_EX365_TEST_ENV`` to use a different file.

#.  Run the matrix with the flag:

    ..  code-block:: bash

        make test-matrix-live

The credentials are passed to the lab's web container through
:file:`.ddev/.env.web`, so they are in the real process environment where
:php:`getenv()` sees them, and that file is deleted again after the checks. No
secret is written into a PHP or TypoScript file.

..  note::
    The live checks surfaced two network failure modes that production servers
    share: dead IPv6 routes to Microsoft (DNS returns IPv6 addresses the host
    cannot reach) and connections that stall after connecting. All transports
    therefore use a 10 s connect and 30 s total timeout — for the Graph call
    instead of the SDK's 100 s, and for the OAuth token request, which the
    SDK's OAuth library otherwise sends with **no timeout at all** (a stalled
    token request would block a scheduler run forever). A request that never connected is retried once; a request that
    stalled after connecting is **not** retried, because Graph may already have
    accepted the mail and a retry could send it twice.

..  _testing-release-gate:

Release gate
============

..  code-block:: bash

    make install-hooks

enables :file:`.githooks/pre-push` for this clone and all its worktrees. Pushing a
**tag** then runs the matrix for every TYPO3 major of the branch the tag is on —
including the live checks when the credentials file exists — and refuses the push
unless everything is green. The checkout being tested must be clean and at the
tagged commit, so a green gate always means "this exact release was tested".
Branch pushes are not affected. ``OK_EX365_SKIP_MATRIX=1 git push --tags`` bypasses
the gate in an emergency.

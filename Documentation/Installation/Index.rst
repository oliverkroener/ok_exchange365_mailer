..  include:: /Includes.rst.txt

..  _installation:

============
Installation
============

..  _installation-composer:

Install with Composer
=====================

Run the following command in your TYPO3 project root directory:

..  code-block:: bash

    composer require oliverkroener/ok-exchange365-mailer:^1.1

The ``^1.1`` constraint selects the 1.x line for TYPO3 9.5 LTS. Composer
installs the dependencies ``oliverkroener/ok-typo3-helper`` and
``guzzlehttp/guzzle`` along with it.

..  _installation-activate:

Activate the extension
======================

Activate the extension in :guilabel:`Admin Tools > Extensions` or on the
command line:

..  code-block:: bash

    vendor/bin/typo3 extension:activate ok_exchange365_mailer

The extension adds no database tables, so no database update is needed.

..  _installation-next:

Next steps
==========

Installing the extension does not change how TYPO3 sends mail. To switch to
Exchange 365:

#.  Register an application in Microsoft Entra ID — see :ref:`azure`.
#.  Select the transport and provide the credentials — see
    :ref:`configuration`.
#.  Send a test mail in
    :guilabel:`Admin Tools > Environment > Test Mail Setup`.

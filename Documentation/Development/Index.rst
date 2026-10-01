:navigation-title: Development

..  include:: /Includes.rst.txt

..  _development:

==========================
Development
==========================

Reference material for contributors and for integrators who need to understand how
the transport resolves its configuration.

..  card-grid::
    :columns: 1
    :columns-md: 2
    :gap: 4
    :class: pb-4
    :card-height: 100

    ..  card:: Architecture

        ..  card-image:: /Images/Icons/architecture.svg
            :alt: Architecture diagram icon

        How TYPO3 selects the transport, how configuration is resolved, and how the
        sender mailbox is determined.

        ..  card-footer:: :ref:`Read more <architecture>`
            :button-style: btn btn-primary

    ..  card:: Testing

        ..  card-image:: /Images/Icons/development.svg
            :alt: Code brackets icon

        Run the automated cross-version test matrix against TYPO3 10 through 14.

        ..  card-footer:: :ref:`Run the tests <testing>`
            :button-style: btn btn-primary

..  toctree::
    :maxdepth: 2
    :titlesonly:
    :hidden:

    Architecture
    Testing
    Contributing

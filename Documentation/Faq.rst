:navigation-title: FAQ

..  _faq:

================================
Frequently Asked Questions (FAQ)
================================

..  accordion::
    :name: faq

    ..  accordion-item:: How can I install this extension?
        :name: installation
        :header-level: 2
        :show:

        See chapter :ref:`installation`.

    ..  accordion-item:: How to can I include the TypoScript?
        :name: configuration
        :header-level: 2

        See chapter :ref:`configuration`.

    ..  accordion-item:: Where to get help?
        :name: help
        :header-level: 2

        See chapter :ref:`help`.

    ..  accordion-item:: Inline images (``cid:``) render broken in received mails
        :name: inline-cid-attachments
        :header-level: 2

        This was a known issue that has been **fixed** in the related
        dependency ``oliverkroener/ok-typo3-helper`` (version 3.1.2 and later).

        Emails with inline images reference the embedded attachment through a
        ``cid:`` URL in the HTML body. Microsoft Graph does **not**
        auto-generate a matching ``Content-ID`` for inline attachments, so the
        reference was left dangling and the image rendered broken in the
        recipient's mail client. ``MSGraphMailApiService`` now carries the
        original ``Content-ID`` over to the Graph ``FileAttachment``, so inline
        images display correctly.

        No change is required in this extension — simply make sure
        ``oliverkroener/ok-typo3-helper`` is on version ``3.1.2`` or newer
        (``composer update oliverkroener/ok-typo3-helper``). The dependency
        constraint (``^3``) already allows it.

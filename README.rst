.. ==================================================
.. FOR YOUR INFORMATION
.. --------------------------------------------------
.. -*- coding: utf-8 -*- with BOM.


.. _start:

=============
Documentation
=============

Job queues for TYPO3 CMS. Implements concrete queue for the redis workqueue. Requires the exension **jobqueue** to be installed.

This extension is a backport of the flow package `Flowpack/jobqueue-redis <https://github.com/Flowpack/jobqueue-redis/>`_.


Installation
------------

This extension requires the `jobqueue <https://typo3.org/extensions/repository/view/jobqueue/>`_ extension.

If you are using composer you can also require the package ``"predis/predis": "^1.0"``.
If not, the provided predis phar archive will be used instead, perhaps this is not the most recent version of the library.


Configuration
-------------

In order to use this queue you should set the *defaultQueue* to ``R3H6\JobqueueRedis\Queue\RedisQueue`` in the *jobqueue* extension settings.

.. tip::

   Check extension configuration options.


Contributing
------------

Bug reports and pull request are welcome through `GitHub <https://github.com/r3h6/TYPO3.EXT.jobqueue_redis/>`_.
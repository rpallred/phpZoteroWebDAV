phpZoteroWebDAV
===============

Store your Zotero attachments on your own site, even on shared hosting.

Features
--------

- Sync library attachment to any webhosting space that supports PHP (including freely available ones).
This means your attachment data is never stored on computers (clients or servers) that you do not control yourself.
- Access your Zotero library on your own webspace through the zotero.org server API, including sorting, detail view, custom number of items per page etc
- Browse your Zotero collections from any web browser
- View your synced attachments (incl. web snapshots) from any web browser without having to use zotero.org's storage server
- Enjoy complete security with support for HTTPS connections

This fork has been updated to run on PHP 8.1+ (the original code, last
updated ~2013-2018, no longer ran at all on modern PHP) and to speak
Zotero's current JSON (v3) API instead of the XML API Zotero retired years
ago.

Installation and Configuration Instructions
-------------------------------------------
See [DEPLOY.md](DEPLOY.md) for setup instructions, including SFTP and
control-panel File Manager upload steps.

License
-------

phpZoteroWebDAV was originally written by Christian Holz and is licensed under the AGPLv3 license.
Significant updates have been made by:
* fishburn (Real name unknown - https://github.com/fishburn)
* David Dean

phpZoteroWebDAV includes the following third party components:
- The WebDAV server PEAR module written by Hartmut Holzgraefe as well as the PEAR base module, both licensed under the PHP license (http://www.php.net/license/3_01.txt)
- The zotero.org css style sheet, apparently released under the AGPLv3 license (http://www.gnu.org/licenses/agpl.html))

Note: the original `libZotero` API client (by https://github.com/fcheslack/libZotero)
has been replaced with a small custom client (`inc/ZoteroClient.php`) that
speaks Zotero's current JSON API — the old client only understood the XML
API Zotero has since discontinued.


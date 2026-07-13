Deployment Instructions
========================

This fork has been updated to run on PHP 8.1+ and to talk to Zotero's current
(JSON, v3) API instead of the old XML API Zotero retired years ago. See
`CHANGELOG` / commit history for what changed.

Requirements
------------

- PHP 8.1 or newer, with the `curl` and `zip` extensions enabled (both are
  standard on virtually all hosts).
- The folder this app lives in must be protected by your host's own HTTP
  Basic Auth (e.g. cPanel/Plesk "Password Protect Directories"). This app
  does **not** implement its own login — it relies entirely on that
  directory-level protection to keep your attachments private. Do not deploy
  this without it.

Step 1: Configure `settings.php`
---------------------------------

Before uploading, open `settings.php` and fill in:

- `$API_key` and `$user_ID` — from https://www.zotero.org/settings/keys
- `$user_name` — your Zotero library/username slug

Leave everything else at its default unless you have a specific reason to
change it (see the comments in the file).

**Do not overwrite this file on future updates.** When you upload a newer
version of this app later, upload every file except `settings.php`, `cache/`,
and `data/` — or re-apply your saved values to `settings.php` immediately
after a full re-upload.

Step 2: Upload the files
-------------------------

Pick whichever of these you're more comfortable with — both put the same
files in the same place.

### Option A: SFTP

1. Connect to your host with an SFTP client (Cyberduck, FileZilla, Transmit,
   etc.) using the credentials from your hosting provider.
2. Navigate to the folder you want this app to live in (this must be the
   same folder your host's directory password protection covers).
3. Upload the entire contents of this project — all files and folders
   (`attachment.php`, `details.php`, `index.php`, `settings.php`,
   `webdav_server.php`, `inc/`, `cache/`, `data/`, etc.) — into that folder.
4. Make sure `cache/` and `data/` are writable by the web server (typically
   permissions `755` on the folders is enough; your host's SFTP client
   usually preserves this automatically on upload).

### Option B: Host's File Manager (cPanel/Plesk)

1. Zip up the contents of this project on your own computer (select all the
   files/folders inside — not the top-level project folder itself — and
   compress them, so the zip's root is `attachment.php`, `inc/`, etc.
   directly).
2. In your host's control panel, open File Manager and navigate to the
   folder this app should live in.
3. Use the "Upload" button to upload your zip file.
4. Select the uploaded zip and use "Extract" (usually right-click or a
   toolbar button) to unzip it in place.
5. Delete the uploaded zip file afterward — no need to leave it on the
   server.
6. Confirm `cache/` and `data/` show as folders with write permission
   (File Manager's "Permissions" column/dialog — `755` is typical).

Step 3: Point Zotero at it
----------------------------

1. Load `https://yoursite.example/path/index.php` in a browser (log in with
   your host's directory password if prompted). Near the top it displays a
   "WebDAV URL" — copy that exact URL.
2. In Zotero desktop: **Settings → Sync → File Syncing → WebDAV**. Paste the
   URL, and enter the **same username/password as your host's directory
   password protection** (not your Zotero.org login — this app has no
   separate login of its own).
3. Click **Verify Server**. It should succeed.
4. Sync a library item with an attachment and confirm it uploads. Try
   removing the local copy and re-syncing to confirm downloads work too.

Step 4: Confirm the browser view works
----------------------------------------

Load `index.php` again — you should see your collections and items listed.
Click into an item to see `details.php`; click a stored attachment to
confirm `attachment.php` serves it correctly.

Troubleshooting
----------------

- **Zotero's "Verify Server" fails immediately / requests never seem to
  reach the app**: double-check the WebDAV URL matches exactly what
  `index.php` displays, and that your directory password protection is
  actually active on that folder.
- **Library page loads but shows no items / a PHP error about the API key**:
  double check `$API_key`, `$user_ID`, and `$user_name` in `settings.php`.
- **A 401/403 shows up when Zotero tries to authenticate**: this is your
  host's directory password protection rejecting the credentials Zotero
  sent — re-check the username/password in Zotero's WebDAV settings against
  what your host's control panel has configured for that folder.

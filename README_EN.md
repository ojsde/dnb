# OJS DNB Export Plugin
**Version: 1.7.0**

**Author: Bozana Bokan, Ronald Steffen**

**Last update: February 27, 2026**

---

About
-----
This plugin provides the export of article metadata and full texts (in PDF and EPUB format) for their transfer to the German National Library (DNB)
using the DNB Hotfolder method. The plugin also offers the option of directly depositing the transfer package into the DNB Hotfolder.
Details on the Hotfolder method are available at: http://nbn-resolving.de/urn%3Anbn%3Ade%3A101-2016111401
Details on the XML format and data requirements are available at: http://nbn-resolving.de/urn%3Anbn%3Ade%3A101-2014071124

License
-------
This plugin is licensed under the GNU General Public License v3. See the file LICENSE for the complete terms of this license.

System Requirements
-------------------
This plugin version is compatible with:
 - OJS 3.5.0-3
 
The `tar` executable is required and has to be configured in config.inc.php.

For depositing articles directly to the DNB Hotfolder via SFTP, your server must support SFTP via the PHP libcurl library. Please ensure the libcurl library installed on your server supports the SFTP protocol.
Alternatively, you can use the WebDAV protocol (port 443). The connection protocol can be selected in the plugin settings.

Installation
------------
Installation via OJS GUI:
 - Download the tar.gz archive (dnb-[version].tar.gz) from https://github.com/ojsde/dnb/releases

  Please always use the latest revision number (.x) of the plugin version corresponding to your OJS version:
   | OJS version | plugin version    |
   | ----------- | ----------------- |
   | 3.2         | 1.4.x             |
   | 3.3         | 1.5.x             |
   | 3.4         | 1.6.x             |
   | 3.5         | 1.7.x             |

 - Install the plugin in your OJS instance (Settings -> Website -> Plugins -> "Upload a New Plugin" -> upload dnb-[version].tar.gz)

Installation via command line without Git:
 - Download the archive in the desired version from https://github.com/ojsde/dnb
 - Extract the plugin to the plugins/importexport directory
 - If necessary, rename the main directory to "dnb"
 - Update the database (it is recommended to back up your database first). Run from your OJS installation directory: php tools/upgrade.php upgrade or php tools/installPluginVersion.php plugins/importexport/dnb/version.xml

Installation via command line with Git:
 - cd [my_ojs_installation]/plugins/importexport
 - git clone https://github.com/ojsde/dnb
 - cd dnb
 - git checkout [branch]
 - cd [my_ojs_installation]
 - php tools/upgrade.php upgrade or php tools/installPluginVersion.php

Adding the DNB SFTP server to SSH known_hosts (only on first installation on a server):

To enable the DNB Plugin to deposit transfer packages on the DNB server via SFTP, an SSH connection must be initiated. To do this, the DNB server must be added to the known_hosts file of your web server account. An easy way to achieve this is to establish a connection to the DNB server via the command line of your OJS server. Use the following command:

`sftp -P 22122 <username>@hotfolder.dnb.de:<folder ID>`

Replace <username> and <folder ID> with the login credentials you received from the DNB.

Advanced settings for curl connections can be configured in the config.inc.php file. Create a section [dnb-plugin] at the end of the file. The following additional parameters are supported:

- CURLOPT_SSH_HOST_PUBLIC_KEY_MD5
- CURLOPT_SSH_HOST_PUBLIC_KEY_SHA256
- CURLOPT_SSH_PUBLIC_KEYFILE
- CURLOPT_HTTPPROXYTUNNEL

Example:
```
[dnb-plugin]
CURLOPT_SSH_HOST_PUBLIC_KEY_SHA256='<put the public key here>'
```

Plugin Settings
--------------
The plugin settings can be found under "Tools > DNB Export Plugin > Settings". A detailed description of the settings is available in the [documentation](docs/manual/en/settings.md).

Deposit and Export
--------------

Starting with OJS 3.5, article deposits are handled via the OJS job queue in the background. You do not need to wait for deposit confirmation in the OJS backend. The status of initiated deposits is displayed in updated form the next time you open the article list.

The plugin export interface can be found at:
Tools > DNB Export Plugin > Articles

Note
--------------
If you want to deposit articles directly from OJS, you must enter your username, password, and subfolder ID in the plugin settings.
You can export DNB packages without entering these credentials, but you cannot deposit them from within OJS.
Please note that the password will be saved as plain text (unencrypted) due to DNB service requirements.

Troubleshooting
---------------

1) Information about the connection setup can be found in the `curl.log` file in the `files/dnb` folder.
2) Try to establish a SFTP connection as described above.
3) Try to deposit a test file via curl from the command line:

    `curl -v -T <path to your test file> sftp://<username>:<password>@hotfolder.dnb.de:22122/<folder ID>/`

Contact/Support
---------------

Please refer to the documentation in the `docs` folder.

Documentation, bug listings, and updates can be found on this plugin's homepage at <http://github.com/ojsde/dnb>.

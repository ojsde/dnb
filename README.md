# OJS DNB Export Plugin
**Version: 1.6**

**Author: Bozana Bokan, Ronald Steffen**

**Last update: February 28, 2024**

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
This plugin version is compatible with...
 - OJS 3.4.0-4
 
The `tar` executable is required and has to be configured in config.inc.php.

For the depositing articles directly to the DNB Hofolder your server has to support SFTP via the PHP libcurl library. Please make sure the libcurl library installed on your server supports the SFTP protocol.

Installation
------------
Installalion via OJS GUI:
 - download dnb-[version].tar.gz from https://github.com/ojsde/dnb/releases

  Please alwasy use the latest revision number (.x) of the plugin version corrsponding to your OJS version
   | OJS version | plugin version    |
   | ----------- | ----------------- |
   | 3.2         | 1.4.x             |
   | 3.3         | 1.5.x             |
   | 3.4         | 1.6.x             |

 - install plugin in OJS (Settings -> Website -> plugins -> „Upload a New Plugin“ -> upload dnb-[version].tar.gz)

Installation via command line without Git:
 - download archive from https://github.com/ojsde/dnb
 - unzip the archive to plugins/importexport and rename the main plugin folder to "dnb" if necessary
 - from your application's installation directory, run the upgrade script (it is recommended to back up your database first):
   php tools/upgrade.php upgrade or php tools/installPluginVersion.php

Installation with Git:
 - cd [my_ojs_installation]/plugins/importexport
 - git clone https://github.com/ojsde/dnb
 - cd dnb
 - git checkout [branch]
 - cd [my_ojs_installation]
 - php tools/upgrade.php upgrade or php tools/installPluginVersion.php plugins/importexport/dnb/version.xml

Adding the DNB SFTP server to SSH known_hosts (only on first installation on a server):

To enable the DNB-Plugin to deposit transfer packages on the DNB server an SSH connection has to be initiated. To this end, the DNB server has to be added to the known_hosts file of your web server account. An easy way to achieve this is to once establish a connection to the DNB server via the command line of your web server. On a Debian system you can use the following command:

`sftp -P 22122 <username>@hotfolder.dnb.de:<folder ID>`

Please replace <username> and <folder ID> by the login credentials you received from the DNB.

Advanced settings for your SSH connection can be configured in the config.inc.php file. Create a section [dnb-plugin] at the end of the file. The following additional settings are supported:

- CURLOPT_SSH_HOST_PUBLIC_KEY_MD5
- CURLOPT_SSH_HOST_PUBLIC_KEY_SHA256
- CURLOPT_SSH_PUBLIC_KEYFILE

Example:
```
[dnb-plugin]
CURLOPT_SSH_HOST_PUBLIC_KEY_SHA256='<put the public key here>'
```


Export
------------
The plugin settings can be found at:
Tools > Import/Export > DNB Export Plugin > Settings

The plugin export interface can be found at:
Tools > Import/Export > DNB Export Plugin > Articles

Note
---------
In order to deposit articles to DNB from within OJS you will have to enter your username, password and subfolder ID in the plugin settings. 
If you do not enter this information you'll still be able to export the DNB packages but you cannot deposit them from within OJS.
Please note, that the password will be saved as plain text, i.e. not encrypted, due to DNB service requirements.

Contact/Support
---------------

Please see the documentation (in German) in the `docs` folder or at https://ojs-de.net/support/anleitungen/dnb-plugin. 

Documentation, bug listings, and updates can be found on this plugin's homepage
at <http://github.com/ojsde/dnb>.

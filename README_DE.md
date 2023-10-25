# OJS DNB-Export-Plug-In
**Version: 1.6**

**Autor: Bozana Bokan, Ronald Steffen**

**Letzte Änderung: 25. Oktober 2023**

---

Über
-----
Dieses Plug-In ermöglicht den Export von Artikel-Metadaten und -Volltexten (im PDF- und EPUB-Format) zwecks ihrer Pflichtablieferung an die Deutsche Nationalbibliothek (DNB)
mittels DNB-Hotfolder-Verfahren. Das Plug-In bietet auch die Option, das Transferpaket direkt in den DNB-Hotfolder abzuliefern.
Details über das Hotfolder-Verfahren sind hier zu finden: <http://nbn-resolving.de/urn:nbn:de:101-2016111401>
Details über das XML-Format und die Datenanforderungen sind hier zu finden: <http://nbn-resolving.de/urn:nbn:de:101-2014071124>

Lizenz
-------
Das Plug-In ist unter GNU General Public License v3 lizenziert. Sehen Sie die Datei LICENSE für mehr Informationen über die Lizenz.

Systemanforderungen
-------------------
Dieses Plug-In Verison ist kompatibel mit...
 - OJS 3.4.0-4

Das Programm `tar` wird benötigt und muss in der Datei config.inc.php konfiguriert werden.

Um Artikel direkt in den Hotfolder der DNB abzuliefern muss der Server SFTP über das PHP-Paket libcurl unterstützen. Bitte achten Sie darauf, dass das installierte libcurl-Paket das SFTP-Protokoll unterstützt. 

Installation
------------
Installation über die OJS-Benutzeroberfläche:
 - Download  des tar.gz-Archivs (dnb-[Version].tar.gz)von https://github.com/ojsde/dnb 

  Bitte immer die neuste Revisionsnummer (.x) des Plugins für die installierte OJS Version benutzen:
   | OJS version | plugin version    |
   | ----------- | ----------------- |
   | 3.2         | 1.4.x             |
   | 3.3         | 1.5.x             |
   | 3.4         | 1.6.x             |

 - Installation des Plugins im Managementbereich von OJS (Einstellungen -> Website -> Plugins -> „Ein neues Plugin hochladen“ -> dnb-[Version].tar.gz hochladen)

Installation über die Kommandozeile ohne Git:
 - Download des Archivs in der gewünschten Version von https://github.com/ojsde/dnb
 - Entpacken des Plugins in das Verzeichnis plugins/importexport
 - ggf. Umbenennen des Hauptverzeichnisses in "dnb"
 - Aktualisierung der Datenbank (es empfiehlt sich, zuerst ein Backup der Datenbank zu erstellen),
   führen Sie dazu aus Ihrem OJS-Verzeichnis aus: php tools/upgrade.php upgrade oder
   php tools/installPluginVersion.php plugins/importexport/dnb/version.xml

Installation über die Kommandozeile mit Git:
 - cd [my_ojs_installation]/plugins/importexport
 - git clone https://github.com/ojsde/dnb
 - cd dnb
 - git checkout [branch]
 - cd [my_ojs_installation]
 - php tools/upgrade.php upgrade or php tools/installPluginVersion.php

Hinzufügen des DNB SFTP-Servers zu den SSH known_hosts (nur bei Erstinstallation auf einem Server):

Damit das DNB-Plugin Transferpakete an die DNB übertragen kann muss eine SSH-Verbindung aufgebaut werden. Dazu muss der DNB-Server zur known_hosts-Datei Ihres Webserver-Accounts hinzugefügt werden. Eine einfache Methode dies zu erreichen ist, einmalig eine Verbindung zum DNB-Server über die Kommandozeile Ihres OJS-Servers herzustellen. Benutzen Sie dazu folgenden Befehl (Debian):

`sftp -P 22122 <username>@hotfolder.dnb.de:<folder ID>`

Ersetzen Sie <username> und <folder ID> durch die Ihnen von der DNB mitgeteilen Login-Daten.

Erweiterte Einstellungen zur SSH-Verbindung können in der config.inc.php definiert werden. Erstellen Sie dazu einen Abschnitt [dnb-plugin] am Ende der Datei. Folgende zusätzliche Parameter werden unterstützt:

CURLOPT_SSH_HOST_PUBLIC_KEY_MD5
CURLOPT_SSH_HOST_PUBLIC_KEY_SHA256
CURLOPT_SSH_PUBLIC_KEYFILE

Beispiel:
```
[dnb-plugin]
CURLOPT_SSH_HOST_PUBLIC_KEY_SHA256='<put the public key here>'
```

Export
------------
Die Plug-In-Einstellungen sind hier zu finden:
Werkzeuge > Import/Export > DNB-Export-Plug-In > Einstellungen

Die Plug-In-Export-Schnittstelle ist hier zu finden:
Werkzeuge > Import/Export > DNB-Export-Plug-In > Artikel

Hinweis
---------
Wenn Sie Artikel direkt aus OJS heraus abliefern möchten, müssen Sie Ihren Benutzernamen, Ihr Passwort und Ihre Unterordner-ID in die Plug-In-Einstellungen eintragen.
Exportieren können Sie die DNB-Pakete aber auch ohne die Zugangsdaten eingetragen zu haben.
Bitte beachten Sie, dass das Passwort wegen Anforderungen des DNB-Dienstes im Klartext, d.h. unverschlüsselt, gespeichert werden wird. 

Kontakt/Support
---------------

Bitte beachten Sie auch die Dokumentation im Ordner `docs`.

Dokumentation, Fehlerauflistung und Updates können auf dieser Plug-Ins-Startseite gefunden werden <http://github.com/ojsde/dnb>.

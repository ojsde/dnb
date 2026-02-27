# OJS DNB-Export-Plug-In
**Version: 1.7.0**

**Autor: Bozana Bokan, Ronald Steffen**

**Letzte Änderung: 27. Februar 2026**

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
 - OJS 3.5.0-3

Das Programm `tar` wird benötigt und muss in der Datei config.inc.php konfiguriert werden.

Um Artikel über SFTP direkt in den Hotfolder der DNB abzuliefern muss ihr OJS-Server SFTP über das PHP-Paket libcurl unterstützen. Bitte achten Sie darauf, dass das installierte libcurl-Paket das SFTP-Protokoll unterstützt.
Alternativ können Sie das WebDav-Protokoll (über Port 443) verwenden. Das Verbindungsprotokoll kann in den Plugin-Einstellungen ausgewählt werden.

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
   | 3.5         | 1.7.x             |

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

Damit das DNB-Plugin Transferpakete über SFTP an die DNB übertragen kann muss eine SSH-Verbindung aufgebaut werden. Dazu muss der DNB-Server zur known_hosts-Datei Ihres Webserver-Accounts hinzugefügt werden. Eine einfache Methode dies zu erreichen ist, einmalig eine Verbindung zum DNB-Server über die Kommandozeile Ihres OJS-Servers herzustellen. Benutzen Sie dazu folgenden Befehl (Debian):

`sftp -P 22122 <username>@hotfolder.dnb.de:<folder ID>`

Ersetzen Sie <username> und <folder ID> durch die Ihnen von der DNB mitgeteilen Login-Daten.

Erweiterte Einstellungen zur curl-Verbindungen können in der config.inc.php definiert werden. Erstellen Sie dazu einen Abschnitt [dnb-plugin] am Ende der Datei. Folgende zusätzliche Parameter werden unterstützt:

- CURLOPT_SSH_HOST_PUBLIC_KEY_MD5
- CURLOPT_SSH_HOST_PUBLIC_KEY_SHA256
- CURLOPT_SSH_PUBLIC_KEYFILE
- CURLOPT_HTTPPROXYTUNNEL

Beispiel:
```
[dnb-plugin]
CURLOPT_SSH_HOST_PUBLIC_KEY_SHA256='<put the public key here>'
```

Plugin-Einstellungen
--------------
Die Plugin-Einstellungen finden Sie unter "Werkzeuge > DNB-Export-Plug-In > Einstellungen". Eine detaillierte Beschreibung der Einstellungen steht in der [Dokumentation](docs/manual/de/settings.md) zur Verfügung.

Ablieferung und Export
--------------

Ab OJS 3.5 wird die Ablieferung der Artikel über die OJS Job Queue im Hintergrund abgewickelt. Sie müssen im OJS-Backend nicht auf die Bestätigung der Ablieferung warten. Der Status der initiierten Ablieferungen wird Ihnen beim erneuten Aufruf der Artikelliste in aktualisierter Form angezeigt.

Die Plug-In-Export-Schnittstelle ist hier zu finden:
Werkzeuge > DNB-Export-Plug-In > Artikel

Hinweis
--------------
Wenn Sie Artikel direkt aus OJS heraus abliefern möchten, müssen Sie Ihren Benutzernamen, Ihr Passwort und Ihre Unterordner-ID in die Plug-In-Einstellungen eintragen.
Exportieren können Sie die DNB-Pakete aber auch ohne die Zugangsdaten eingetragen zu haben.
Bitte beachten Sie, dass das Passwort wegen Anforderungen des DNB-Dienstes im Klartext, d.h. unverschlüsselt, gespeichert werden wird.

Fehleranalyse
---------------

1) Informationen zum Verbingungsaufbau finden Sie in der Datei `curl.log` im Ordner `files/dnb`.
2) Veruschen Sie, wie oben beschrieben, eine Verbindung via SFTP aufzubauen.
3) Versuchen Sie eine Testdatei via curl über die Kommandozeile abzuliefern:

    `curl -v -T <path to your test file> sftp://<username>:<password>@hotfolder.dnb.de:22122/<folder ID>/`


Kontakt/Support
---------------

Bitte beachten Sie auch die Dokumentation im Ordner `docs`.

Dokumentation, Fehlerauflistung und Updates können auf dieser Plug-Ins-Startseite gefunden werden <http://github.com/ojsde/dnb>.

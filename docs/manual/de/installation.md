# Installation des DNB Export Plugins

1. [Welche Plugin-Version muss ich installieren?](installation#version)
2. [Plugin installieren](installation#plugin)

## <a name="version"></a>Welche Plugin-Version muss ich installieren?

Bitte installieren Sie immer die neuste Revisionsnummer (.x) des Plugins für Ihre installierte OJS Version. Die aktuellen Plugin-Versionen finden Sie auf [Github - Releases -DNB Export Plugin](https://github.com/ojsde/dnb/releases).

| OJS version | plugin version   |
|:----------: | :--------------: |
| 3.2         | 1.4.x            |
| 3.3         | 1.5.x            |
| 3.4         | 1.6.x            |
| 3.5         | 1.7.x            |

## <a name="plugin"></a>Plugin installieren

**Voraussetzungen**

- Ein TAR-Programm wird benötigt und muss in der Datei config.inc.php konfiguriert werden. Dies ist auf den meisten Linux-Systemen Standard.
- Um Artikel über SFTP direkt in den Hotfolder der DNB abzuliefern muss der Server SFTP über das PHP-Paket libcurl unterstützen. Bitte achten Sie darauf, dass das installierte libcurl-Paket das SFTP-Protokoll unterstützt. Zusätzlich musee der Port 22122 in Ihrer Firewall freigegeben sein.


**Installation des Plugins im Managementbereich von OJS**

Im Managementbereich von OJS (Einstellungen -> Website -> Plugins -> „Ein neues Plugin hochladen“ können Sie die auf Github verfügbare Datei dnb-[Version].tar.gz hochladen um das Plugin zu installieren.

**Installation über die Kommandozeile ohne Git**

- Download des Archivs in der gewünschten Version von [Github - Releases -DNB Export Plugin](https://github.com/ojsde/dnb/releases)
- Entpacken des Plugins in das Verzeichnis plugins/importexport. Der automatisch erstellte Ordner sollte "dnb" sein.
- Aktualisierung der Datenbank (es empfiehlt sich, zuerst ein Backup der Datenbank zu erstellen). Um die Datenbank zu aktualisieren führen Sie aus Ihrem OJS-Verzeichnis einen der folgenden Befehle aus:
  - `php tools/upgrade.php upgrade` oder
  - `php tools/installPluginVersion.php plugins/importexport/dnb/version.xml`

**Installation über die Kommandozeile mit Git**

- cd [my_ojs_installation]/plugins/importexport
- git clone https://github.com/ojsde/dnb
- cd dnb
- git checkout [branch]
- cd [my_ojs_installation]
- Aktualisierung der Datenbank (es empfiehlt sich, zuerst ein Backup der Datenbank zu erstellen). Um die Datenbank zu aktualisieren führen Sie aus Ihrem OJS-Verzeichnis einen der folgenden Befehle aus:
  - `php tools/upgrade.php upgrade` oder
  - `php tools/installPluginVersion.php plugins/importexport/dnb/version.xml`

**Hinzufügen des DNB SFTP-Servers zu den SSH known_hosts**
  (nur bei Erstinstallation auf einem Server)

Damit das DNB-Plugin Transferpakete an die DNB übertragen kann muss eine SSH-Verbindung aufgebaut werden. Dazu muss der DNB-Server zur known_hosts-Datei Ihres Webserver-Accounts hinzugefügt werden. Eine einfache Methode dies zu erreichen ist, einmalig eine Verbindung zum DNB-Server über die Kommandozeile Ihres OJS-Servers herzustellen. Ja nach System benutzen Sie dazu einen der folgenden Befehle:

 - `sftp -P 22122 <username>@hotfolder.dnb.de:<folder ID>` oder `sftp -oPort=22122 <username>@hotfolder.dnb.de:<folder ID>`

Ersetzen Sie `<username>` und `<folder ID>` durch die Ihnen von der DNB mitgeteilen Login-Daten.

## Automatische Ablieferung einrichten

Ab OJS Version 3.5 ist keine zusätzliche Konfiguration mehr notwendig. Die Ablieferung erfolgt über die bei der Installation von OJS durch den Adminsitrator eingerichtete Job-Queue (siehe https://docs.pkp.sfu.ca/admin-guide/en/deploy-jobs).
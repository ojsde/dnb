# Installation des DNB Export Plugins

1. [Welche Plugin-Version muss ich installieren?](installation#version)
2. [Plugin installieren](installation#plugin)
3. [Cronjob einrichten](installation#cronjob)

##<a name="version"></a>Welche Plugin-Version muss ich installieren?

Bitte installieren Sie immer die neuste Revisionsnummer (.x) des Plugins für Ihre installierte OJS Version. Die aktuellen Plugin-Versionen finden Sie auf [Github - Releases -DNB Export Plugin](https://github.com/ojsde/dnb/releases).

| OJS version | plugin version   |
|:----------: | :--------------: |
| 3.2         | 1.4.x            |
| 3.3         | 1.5.x            |

## <a name="plugin"></a>Plugin installieren

**Voraussetzungen**

- Ein TAR-Programm wird benötigt und muss in der Datei config.inc.php konfiguriert werden. Dies ist auf den meisten Linux-Systemen Standard.
- Um Artikel direkt in den Hotfolder der DNB abzuliefern muss der Server SFTP über das PHP-Paket libcurl unterstützen. Bitte achten Sie darauf, dass das installierte libcurl-Paket das SFTP-Protokoll unterstützt. 

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


## <a name="cronjob"></a>Cronjob für automatische Ablieferung einrichten

Um die automatische Ablieferung von Artikeln an die DNB nutzen zu können muss ein cronjob eingerichtet werden. Der Befehl für das Starten der Aufgabe lautet: 

`php tools/runScheduledTasks.php plugins/importexport/dnb/scheduledTasks.xml`

In der Datei version.xml im Pluginverzeichniss können Sie über das Attribute "frequency" zusätzlich die Häufigkeit der Ablieferungsversuche konfigurieren.
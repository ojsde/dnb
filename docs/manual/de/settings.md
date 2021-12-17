# Einstellungen

Bitte bachten Sie:

1. Falls Ihre Zeitschrift keine Open-Access-Zeitschrift ist, müssen Sie als erstes im Abschnitt *Archivzugriff* der Registerkarte die Zugriffsrechte für die bei der DNB archivierten Exemplare Ihrer Artikel angeben.
2. Die Konfiguration der Zugangsdaten für den *Hotfolder* wird nur benötigt wenn Sie direkt an die DNB abliefern wollen.

***Zugangsdaten DNB-Hotfolder***

Falls eine direkte Ablieferung der Artikel in Ihren DNB-Hotfolder gewünscht wird, tragen Sie Ihre DNB-Kontodaten (Benutzer/innennamen, Passwort, Unterordner-ID des Hotfolders) im Abschintt *Zugangsdaten DNB Hotfolder* in die entsprechenden Felder ein. Für eine direkte Ablieferung muss ausserdem SFTP auf Ihrem Server konfiguriert sein. Sollte dies nicht der Fall sein erhalten Sie eine Fehlermeldung. Bitte kontaktieren Sie dazu Ihren Systemadministrator.

***Automatische Ablieferung***

Wenn Sie die automatische Ablieferung aktivieren werden neue, nicht abgelieferte Artikel mittels eines von Ihrem Systemadminstrator konfigurieten Cronjobs in regelmäßigen Abständen automatisch in den *DNB-Hotfolder* übertragen. Wenn diese Option aktiv ist wird außerdem ein zusätzlicher Reiter mit dem letzten Ablieferungsprotokoll angezeigt.

***Ablieferung von Begleitmaterial***

Auch das Begleitmaterial Ihrer Artikel ist ablieferungspflichtig. Ab Version 3.3 liefert das DNB Export Plugin mit jeder Dokumentfahne automatisch das gesamte Begleitmaterial eines Artikels ab. Falls technische Probleme die Ablieferung des Begleitmaterials verhinden, steht eine Option zur Deaktivierung dieser Funktion zur Verfüfgung. Bitte deaktivieren Sie diese Funktion nur in Rücksprache mit der DNB.

***Fahnen die an einem externen Ort bereitgestellt werden abliefern***

Ab Version 3.3 des DNB Export Plugins können auch Fahnen die an einem externen Ort bereitgestellt werden (Remote Galleys) abliefert werden. Bitte beachten Sie, dass in OJS nur Dokumentfahnen (also nicht das Begleitmaterial) als Remote Galleys behandelt werden. Aus Sicherheitsgründen muss bei Aktivierung dieser Funktion auch eine (oder mehrere) feste IP-Adressen angegeben werden, von denen Remote Galleys akzeptieren werden dürfen. Weiterhin werden nur nicht-ausführbare Remote Galleys an die DNB abgelifert. 

***Archivzugriff***

Es stehen die Optionen 

- Beschränkter Zugriif an speziellen Rechnern der Lesesäle der DNB
- Uneingeschränkter Zugriff für alle
- Zugriff für registrierte Nutzer/innen auch außerhalb der DNB

zur Verfügung. Für Open-Access-Zeitschriften und -Artikel ist der Zugriff auf die archivierte Version automatisch "Uneingeschränkt für alle". Geschlossene Zeitschriften und Zeitschriften mit beschränktem Zugriff müssen eine von den durch die DNB zur Verfügung gestellten Zugriffsoptionen für Archivexemplare auswählen. Bitte beachten Sie, dass auf Ausgaben oder Artikelebene vergebene Zugriffsrechte vorgang von den hier gesetzten Zugriffrechten haben.
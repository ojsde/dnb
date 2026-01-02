# Ablieferung von Artikeln

Über die Registerkarte „Artikel“ können Sie Artikel manuell exportieren oder den Exportstatus Ihrer Artikel verwalten. 

1. [Artikelstatus](export#status)
2. [Abliefern](export#deposit)
3. [Exportieren](export#export)
4. [Zusatzmaterial](export#supplementary)
5. [Ablieferung von URNs](export#urn)

## <a name="status"></a>Artikelstatus

Der Ablieferungsstatus eines Artikels kann einen der folgenden Zustände haben:

***Nicht abgeliefert***: Der Artikel wurde noch nicht bei der DNB abgeliefert (er wurde nicht über OJS in den DNB Hotfolder und auch nicht extern, z.B. über das DNB Webformular abgeliefert).

***Abgeliefert***: Der Artikel wurde über OJS in den DNB-Hotfolder abgeliefert.

***Als registriert markiert***: Der Artikel wurde manuell als registriert markiert. Sie können Artikel als registriert markieren (s. Button *"Als registriert markieren"*), um anzuzeigen, dass dieser Artikel außerhalb von OJS an die DNB abgeliefert wurde, z.B. über das DNB Webformular. *Diese Einstellung wirkt sich nicht auf die Ablieferung mittles Cronjob aus und kann nicht rückgängig gemacht werden* (Originalimplementation von PKP)."

***Ausgeschlossen***: Der Artikel wurde vom Export ausgeschlossen. Er wird werder beim Abliefern über die Benutzeroberflächen noch beim Abliefern durch einen Cronjob berücksichtigt. Diese Einstellung kann rückgängig gemacht werden.

## <a name="deposit"></a>Abliefern

Mit Hilfe der der Funktion ***Abliefern*** werden die erstellten Archivdateien direkt per SFTP an den unter [Einstellungen](settings) konfigurierten Ordner gesendet. Bitte stellen Sie sicher, dass Ihr Server und Ihre locale IT-Umgebung (z.B. Firewalls) ausgehende SFTP-Verbindungen zulassen.

Beachten Sie, dass die Ablieferung der Artikel eine Weile dauern kann, Sie werden benachrichtigt, sobald der Prozess abgeschlossen ist. Bei einer größeren Anzahl von Artikeln ist es ratsam, (anfangs) schrittweise vorzugehen und zu prüfen, ob alle Artikel übertragen wurden. Nach der Ablieferung der Artikel ändert sich deren Status automatisch auf „Abgeliefert“. 

## <a name="export"></a>Exportieren

Mit Hilfe der Funktion ***Exportieren*** werden die erstellten Archivdateien auf Ihr lokales System heruntergeladen. Der Status eines Artikels ändert sich dabei nicht. Sie können die Dateien einsehen und manuell in den DNB Hotfolder hochladen. Nutzen Sie dafür einen SFTP-Client Ihrer Wahl und erstellen Sie eine Verbindung mit den Ihnen von der DNB für den Hotfolder zur Verfügung gestellten Anmeldeinformationen und den folgenden Daten: 

-  Übertragungsprotokoll: SFTP 
-  Serveradresse: hotfolder.dnb.de 
-  Port: 22122 

Falls Sie Artikel selbst an die DNB abliefern, empfehlen wir, von der Möglichkeit Gebrauch zu machen, abgelieferte Artikel als registriert zu markieren.

## <a name="supplementary"></a>Zusatzmaterial

OSJ bietet momentan keine Unterstützung für die Zuordnung von Zusatzmaterial zu Dokumentfahnen. Wenn mehr als eine Dokumentfahne existiert kann Zusatzmaterial nicht mehr eindeutig zugeordnet werden. Diese Artikel markiert das DNB Export Plugin mit einem roten Dreieck mit Ausrufezeichen.

Das DNB Export Plugin exportiert immer alle Zusatzmaterialfahnen mit jeder einzelnen Dokumentfahne. Falls Sie Artikel mit unkorrekt zugeordnetem Zusatzmaterial nicht direkt abliefern wollen, sollten Sie in Erwägung ziehen die entsprechenden Artikel zu exportieren, die überflüssigen Fahnen zu entfernen und die Archivdatei manuell in den DNB Hotfolder hochzuladen.

## <a name="urn"></a>Ablieferung von URNs

Das DNB Export Plugin exportiert momentan keine Artikel die URNs auf Artikelebene verwenden. Wenn Sie Artikel mit URNs auf Artikelebene abliefern möchten kontaktieren Sie bitte die DNB zum weiteren Verfahren.

Falls Sie in OJS URNs verwenden, müssen Sie ausserdem einstellen, dass Prüfziffern für die URNs berechnet werden. Bei automatisch generierten URNs gehen Sie dazu in die Einstellungen Ihres URN-Plugins. Diese finden Sie in der Registerkarte Plugins im Menü Einstellungen -> Website. In der Rubrik „Plugins für öffentliche Kennungen“, klicken auf den blauen Pfeil links von URN und wählen „Einstellungen“. Setzen Sie ein Häkchen beim Kontrollkästchen „Prüfziffer“. Bei individuell vergebenen URNs finden Sie einen Button zum Berechnen der Prüfziffer direkt neben dem Eingabefeld für das URN-Suffix. Ein Tool der DNB zur Berechnung von Prüfziffern finden Sie [hier](http://nbn-resolving.de/nbnpruefziffer.php). 

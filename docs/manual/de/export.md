# Ablieferung von Artikeln

1. [Artikelstatus](export#status)
2. [Abliefern](export#deposit)
3. [Exportieren](export#export)
4. [Zusatzmaterial](export#supplementary)

## <a name="status"></a>Artikelstatus

Der Ablieferungsstatus eines Artikels kann einen der folgenden Zustände haben:

***Nicht abgeliefert***: Der Artikel wurde noch nicht bei der DNB abgeliefert (er wurde nicht über OJS in den DNB Hotfolder und auch nicht extern, z.B. über das DNB Webformular abgeliefert).

***Abgeliefert***: Der Artikel wurde über OJS in den DNB-Hotfolder abgeliefert.

***Als registriert markiert***: Der Artikel wurde manuell als registriert markiert. Sie können Artikel als registriert markieren (s. Button *"Als registriert markieren"*), um anzuzeigen, dass dieser Artikel außerhalb von OJS an die DNB abgeliefert wurde, z.B. über das DNB Webformular."

## <a name="deposit"></a>Abliefern

Mit Hilfe der der Funktion ***Abliefern*** werden die erstellten Archivdateien direkt per SFTP an den unter [Einstellungen](settings) konfigurierten Ordner gesendet. Bitte stellen Sie sicher, dass Ihr Server und Ihre locale IT-Umgebung (z.B. Firewalls) ausgehende SFTP-Verbindungen zulassen.

## <a name="export"></a>Exportieren

Mit Hilfe der Funktion ***Exportieren*** werden die erstellten Archivdateien auf Ihr lokales System heruntergeladen. Sie können die Dateien einsehen und manuell in den DNB Hotfolder hochladen.

## <a name="supplementary"></a>Zusatzmaterial

OSJ bietet momentan keine Unterstützung für die Zuordnung von Zusatzmaterial zu Dokumentfahnen. Wenn mehr als eine Dokumentfahne existiert kann Zusatzmaterial nicht mehr eindeutig zugeordnet werden. Diese Artikel markiert das DNB Export Plugin mit einem roten Dreieck mit Ausrufezeichen.

Das DNB Export Plugin exportiert immer alle Zusatzmaterialfahnen mit jeder einzelnen Dokumentfahne. Falls Sie Artikel mit unkorrekt zugeordnetem Zusatzmaterial nicht direkt abliefern wollen, sollten Sie in Erwägung ziehen die entsprechenden Artikel zu exportieren, die überflüssigen Dokumentfahnen zu entfernen und die Archivdatei manuell in den DNB Hotfolder hochzuladen.

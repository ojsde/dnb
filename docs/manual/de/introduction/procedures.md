# Allgemeine Vorgehensweisen bei der Ablieferung

Je nach Ihren internen Arbeitsabläufen können Sie verschiedene Vorgehensweisen bei der Ablieferung an die DNB nutzen. Diese werden im Folgenden kurz erläutert.

## Vorgehensweise 1

Wenn Sie die ausgewählten Artikel in eine lokale Datei exportieren möchten, klicken Sie auf „Daten exportieren“. Die Artikel verbleiben dabei im Status „Nicht 
abgeliefert“. Sie können die so exportierten Artikel auch außerhalb von OJS in den Hotfolder übertragen. Nutzen Sie dafür einen SFTP-Client Ihrer Wahl und erstellen Sie eine Verbindung mit den Ihnen von der DNB für den Hotfolder zur Verfügung gestellten Anmeldeinformationen und den folgenden Daten:

-  Übertragungsprotokoll: SFTP 
-  Serveradresse: hotfolder.dnb.de 
-  Port: 22122 

Falls Sie Artikel selbst an die DNB abliefern, empfehlen wir, von der Möglichkeit Gebrauch zu machen, abgelieferte Artikel als registriert zu markieren.

## Vorgehensweise 2

Wenn Sie die ausgewählten Artikel direkt in den DNB-Hotfolder ablegen lassen möchten, klicken Sie auf „Abliefern“. Beachten Sie, dass die Ablieferung der 
Artikel eine Weile dauern kann, Sie werden benachrichtigt, sobald der Prozess abgeschlossen ist. Bei einer größeren Anzahl von Artikeln ist es ratsam, (anfangs) schrittweise vorzugehen und zu prüfen, ob alle Artikel übertragen wurden. Nach der Ablieferung der Artikel ändert sich deren Status automatisch auf „Abgeliefert“. Wenn Sie diese Vorgehensweise nutzen möchten müssen Sie Ihre Login-Infoemationen im Reiter *Einstellungen* angeben.

## Vorgehensweise 3
Bei dieser Vorgehensweise muss die Ablieferung nicht manuell ausgelöst werden. Alle Artikel mit 
dem Status „Nicht abgeliefert“ werden automatisch in den Hotfolder übertragen. Nach erfolgreicher Ablieferung der Artikel ändert sich deren Status automatisch auf „Abgeliefert“. Wenn Sie diese Vorgehensweise nutzen möchten muss auf Ihrem Server ein Cronjob eingerichtet sein. Bitte konsultieren Sie dafür Ihren Systemadministrator.


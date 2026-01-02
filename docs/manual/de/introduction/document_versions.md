# Umgang mit Dubletten und Versionen

### Dubletten

Zur Identifizierung von Dubletten nutzt die DNB entweder [Persistent Identifier](https://de.wikipedia.org/wiki/Persistent_Identifier) auf Galleyebene (Persistent Identifier werden in OJS als Public Identifier bezeichnet), oder erstellt Prüfsummen über die einzelnen Transferpakete.

Artikel mit identischer URN auf Galleyebene werden von der DNB grundsätzlich als Dublette interpretiert und abgewiesen. Bei Artikel mit DOI auf Galleyebene wird eine Prüfsumme über 
das gesamte Transferpaket erstellt und die Identität der Prüfsumme als Kriterium für die Dublettenerkennung verwendet. Dies bedeutet, dass nur solche Artikel als Dublette interpretiert werden, die über alle Dateien im Transferpaket (d.h. Volltexte und Metadaten) identisch sind. Prinzipiell können daher verschiedene Versionen eines Artikels mit identischer DOI an die DNB abgeliefert werden. 

Beachten Sie, dass bereits kleinste Änderungen an einer Galley, z.B. die Korrektur von Tippfehlern, dazu führen, dass diese Galley nicht als Dublette zurückgewiesen und erneut in den Katalog aufgenommen wird. Bei größeren und inhaltlich bedeutenden Änderungen kann solch eine Neuaufnahme durchaus gewollt sein. 

Die DNB kann derzeit noch keine Versionen von Galleys verwalten. Veränderte Galleys werden neu in den Katalog aufgenommen ohne einen Verweis auf Vorgängerversionen.

### Umgang mit der Versionierung von Artikeln ab OJS 3.2

Mit OJS Version 3.2 hat [PKP](https://pkp.sfu.ca/ojs/) die Möglichkeit eingeführt verschiedene [Versionen](https://docs.pkp.sfu.ca/learning-ojs/en/production-publication#versioning-of-articles) eines Artikels zu veröffentlichen. Dabei können neue Versionen sowohl Änderungen der Metadaten als auch der Volltexte beinhalten. Alle 
veröffentlichten Versionen eines Artikels sind auf der Artikelseite von OJS verfügbar und können durch Nutzer abgerufen werden.

Das DNB-Export Plugin liefert immer nur die letzte veröffentlichte Version eines Artikels an die DNB ab und markiert den Artikel nach erfolgreicher Ablieferung als „Abgeliefert“. Eine Ablieferung von nachträglich neu erstellten Versionen des Artikels direkt durch das DNB-Export Plugin ist nicht möglich.

Falls die Ablieferung von neu erstellten Artikeln gewünscht wird, muss dazu die manuelle Ablieferung durch Export und selbständiges hochladen des Transferpaketes in den Hotfolder 
(Vorgehensweise 1) genutzt werden. 
# Das Hotfolder-Ablieferungsverfahren

Das DNB Export Plugin verwendet das sogenannte *Hotfolder*-Verfahren. Dabei nutzt der Ablieferer ein Konto bei der DNB, über das per SFTP-Schnittstelle Artikel als sogenannte Transferpakete in den *Hotfolder* übertragen werden können. Das Plugin liefert für jede EPUB- und jede PDF-Fahne (Galley) eines Artikels genau ein Transferpaket an die DNB ab. Liegt ein Artikel in mehreren Sprachen vor, wird er in allen Sprachen abgeliefert, ebenfalls mit einem Transferpaket pro Galley. 
Für die Ablieferung erstellt das DNB Export Plugin gepackte Archivdateien nach den [Spezifikationen für den DNB Horfolder](https://www.dnb.de/SharedDocs/Downloads/DE/Professionell/Netzpublikationen/spezifikationHotfolder.pdf;jsessionid=30EBEB0D8D5F9C7B717E80FCDD5FC8B3.intranet672?__blob=publicationFile&v=2) und zusätzlichen Vereinbarungen mit der DNB.

Bei der Ablieferung gibt es drei verschiedene Vorgehensweisen:

1. **Vorgehensweise 1**: Export in eine lokale Datei, die unabhängig von OJS in einen Hotfolder übertragen 
werden kann. Bei dieser Vorgehensweise kann man die exportierten Daten einsehen und selbst bestimmen, welche Artikel und Galleys abgeliefert werden.

2. **Vorgehensweise 2**: Automatische Übertragung der Transferpakete in den Hotfolder, die manuell im Exportbereich des Plugins ausgelöst wird. Bei dieser 
Vorgehensweise kann man selbst bestimmen, welche Artikel abgeliefert werden.

3. **Vorgehensweise 3**: Automatische Übertragung der Transferpakete in den Hotfolder, die in regelmäßigen Abständen von OJS oder einem Cronjob ausgelöst wird. Bei dieser Vorgehensweise werden alle publizierten Artikel, die noch nicht abgeliefert wurden, in den Hotfolder übertragen.

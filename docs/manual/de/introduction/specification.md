# Spezifikation des Marc 21 Formats zur Ablieferung von Netzpublikationen an die DNB aus OJS

Die hier zusammengefassten Spezifikationen entstehen in enger Absprache mit der DNB.
Stand: 17.2.2022

Header
---
Feld | tag | ind1 | ind2 | code | Inhalt/Beschreibung
-----|-----|------|------|------|------------------
leader | | | | | '00000naa a2200000 u 4500'
controlfield | 001 | | | | Fahnen-ID
controlfield | 007 | | | | 'cr \|\|\|\|\|\|\|\|\|\|\|'
controlfield | 008 | | | | 'yymmddsYYYY\|\|\|\|xx#\|\|\|\| \|\|\|\|\| \|\|\|\|\| lang\|\|' <br/>- yymmdd, YYYY = Publikationsdatum Artikel <br />- lang = 3-Letter Iso Language Code der Fahne

Fahnen-URN
---
Feld | tag | ind1 | ind2 | code | Inhalt/Beschreibung
-----|:---:|:----:|:----:|:----:|------------------
datafield | 024 | 7 | | a | Fahnen-URN
datafield | 024 | 7 | | 2 | 'urn'

DOIs (Artikel und Fahnen)
---
Feld | tag | ind1 | ind2 | code | Inhalt/Beschreibung
-----|:---:|:----:|:----:|:----:|------------------
datafield | 024 | 7 | | a | DOI
datafield | 024 | 7 | | 2 | 'doi'

Pluginversion
---
Feld | tag | ind1 | ind2 | code | Inhalt/Beschreibung
-----|:---:|:----:|:----:|:----:|------------------
datafield | 040 |  |  | a | 'OJS DNB-Export-Plugin Version *<major.minor.revision>*'

Sprache
---
Feld | tag | ind1 | ind2 | code | Inhalt/Beschreibung
-----|:---:|:----:|:----:|:----:|------------------
datafield | 041 |  |  | a | 3-Letter Iso Language Code der Fahne

Zugangsbeschränkung
---
Feld | tag | ind1 | ind2 | code | Inhalt/Beschreibung
-----|:---:|:----:|:----:|:----:|------------------
datafield | 093 |  |  | b | 'a' = nur Lesesäle der DNB<br/>'b' = Open Access <br/> 'd' = Registrierte Nutzer

Erstautor
---
Feld | tag | ind1 | ind2 | code | Inhalt/Beschreibung
-----|:---:|:----:|:----:|:----:|------------------
datafield | 100 | 1 |  | a | Name, Vorname
datafield | 100 | 1 |  | 0 | '(orcid)0000-0000-0000-0000*<verifizierte Orcid-ID>*' <br/> (nur mit Oricd-Profile Plugin)
datafield | 100 | 1 |  | 4 | 'aut'

Titel & Untertitel
---
Feld | tag | ind1 | ind2 | code | Inhalt/Beschreibung
-----|:---:|:----:|:----:|:----:|------------------
datafield | 245 | 0 | 0 | a | Titel in Fahnensprache
datafield | 245 | 0 | 0 | b | Untertitel in Fahnensprache

Publikationsjahr des Artikels
---
Feld | tag | ind1 | ind2 | code | Inhalt/Beschreibung
-----|:---:|:----:|:----:|:----:|------------------
datafield | 264 |  | 1 | c | Publikationsjahr YYYY

Begleitmaterial vorhanden
---
Feld | tag | ind1 | ind2 | code | Inhalt/Beschreibung
-----|:---:|:----:|:----:|:----:|------------------
datafield | 300 |  | 1 | e | 'Begleitmaterial'<br/><br/>Wenn diesem Artikel mindestens eine Begleitmaterialfahne zugeordnet ist.

Artikel-URN
---

**Achtung:** Artikel-URNs werden nicht automatisch abgeliefert. Bitte Hinweis im Abschnitt "Ablieferung von Artikeln" beachten!

Feld | tag | ind1 | ind2 | code | Inhalt/Beschreibung
-----|:---:|:----:|:----:|:----:|------------------
datafield | 500 |  |  | a | 'URN: *<Artikel-URN>*'

Begleitmaterial nicht eindeutig zuzuordnen
---

Begleitmaterial wird in OJS dem Artikel und nicht einzelnen Dokumentfahnen zugeordnet.

Feld | tag | ind1 | ind2 | code | Inhalt/Beschreibung
-----|:---:|:----:|:----:|:----:|------------------
datafield | 500 |  |  | a | 'Artikel in verschiedenen Dokumentversionen mit Begleitmaterial veröffentlicht'<br/><br/>Wenn diesem Artikel mehr als eine Dokumentfahne und mindestens eine Begleitmaterialfahne zugeordnet ist.

Abstract
---
Feld | tag | ind1 | ind2 | code | Inhalt/Beschreibung
-----|:---:|:----:|:----:|:----:|------------------
datafield | 520 | 3 |  | a | Abstract
datafiles | 520 | 3 |  | u | URL zum Abstract

Lizenz-URL oder Copyright-Hinweis
---
Feld | tag | ind1 | ind2 | code | Inhalt/Beschreibung
-----|:---:|:----:|:----:|:----:|------------------
datafield | 540 |  |  | u | Lizenz-URL wenn vorhanden, ansonsten Copyright-Hinweis

Schlagwörter
---
Feld | tag | ind1 | ind2 | code | Inhalt/Beschreibung
-----|:---:|:----:|:----:|:----:|------------------
datafield | 653 |  |  | a | Ein Feld pro Schlagwort

Weitere Autoren
---
Feld | tag | ind1 | ind2 | code | Inhalt/Beschreibung
-----|:---:|:----:|:----:|:----:|------------------
datafield | 700 | 1 |  | a | Name, Vorname
datafield | 700 | 1 |  | 0 | '(orcid)0000-0000-0000-0000*<verifizierte Orcid-ID>*' <br/> (nur mit Oricd-Profile Plugin)
datafield | 700 | 1 |  | 4 | 'aut'

Übersetzer
---
Feld | tag | ind1 | ind2 | code | Inhalt/Beschreibung
-----|:---:|:----:|:----:|:----:|------------------
datafield | 700 | 1 |  | a | Name, Vorname
datafield | 700 | 1 |  | 0 | '(orcid)0000-0000-0000-0000*<verifizierte Orcid-ID>*' <br/> (nur mit Oricd-Profile Plugin)
datafield | 700 | 1 |  | 4 | 'trl'

Daten der Ausgabe
---
Feld | tag | ind1 | ind2 | code | Inhalt/Beschreibung
-----|:---:|:----:|:----:|:----:|------------------
datafield | 773 | 1 |  | g | Band (Volume)
datafield | 773 | 1 |  | g | Nummer
datafield | 773 | 1 |  | g | Wenn vorhanden: Publikationsjahr der Ausgabe aus OJS-Feld "Identifizierung -> Jahr"<br/><br/>Ansonsten: Jahr des (automatisch gespeicherten) Publikationsdatums der Ausgabe
datafield | 773 | 1 |  | 7 | 'nnas'

ISSN der Zeitschrift
---
Feld | tag | ind1 | ind2 | code | Inhalt/Beschreibung
-----|:---:|:----:|:----:|:----:|------------------
datafield | 773 | 1 | 8 | x | Wenn vorhanden: Online ISSN<br/>Ansosnten: Print ISSN

Daten der Fahnendatei
---
Feld | tag | ind1 | ind2 | code | Inhalt/Beschreibung
-----|:---:|:----:|:----:|:----:|------------------
datafield | 856 | 4 |  | u | URL zur Dokumentfahne
datafield | 856 | 4 |  | q | Dateityp
datafield | 856 | 4 |  | s | Dateigröße
datafield | 856 | 4 |  | z | 'Open Access'

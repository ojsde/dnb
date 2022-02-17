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

Erstautor (Corresponding Author)
---
Feld | tag | ind1 | ind2 | code | Inhalt/Beschreibung
-----|:---:|:----:|:----:|:----:|------------------
datafield | 100 | 1 |  | a | Name, Vorname
datafield | 100 | 1 |  | 0 | '(orcid)*<verifizierte Orcid-ID>*' <br/> (nur mit Oricd-Profile Plugin)
datafield | 100 | 1 |  | 4 | 'aut'

Titel & Untertitel
---
Feld | tag | ind1 | ind2 | code | Inhalt/Beschreibung
-----|:---:|:----:|:----:|:----:|------------------
datafield | 245 | 0 | 0 | a | Titel in Fahnensprache
datafield | 245 | 0 | 0 | b | Untertitel in Fahnensprache
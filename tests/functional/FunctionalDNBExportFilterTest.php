<?php

# run with 'sh plugins/importexport/dnb/tests/runTests.sh -d' from ojs folder (note phpunit needs to be installed by running 'composer.phar --working-dir=lib/pkp install' without the -no-dev option)
# This is not an automatic test. A native xml export file with the appropriate name and submission ID in your systems has to be placed in the tests folder.
# You may need to install phpunit in lib/pkp: 'composer.phar require --dev phpunit/phpunit'

    import('lib.pkp.tests.PKPTestCase');
    import('classes.issue.Issue');
    import('classes.submission.Submission');
    import('classes.article.ArticleGalley');
    import('plugins.importexport.dnb.filter.DNBXmlFilter');
    import('plugins.importexport.dnb.DNBExportPlugin');
    import('plugins.importexport.dnb.DNBExportDeployment');
    import('plugins.importexport.dnb.DNBInfoSender');
    import('lib.pkp.classes.filter.FilterGroup');
    import('lib.pkp.classes.plugins.LazyLoadPlugin');

    class FunctionalDNBExportFilterTest extends PKPTestCase {

        # How to use this test:
        # 1) Prepare a submission (including DOIs) in the OJS backend
        # 2) Add the keyword "FunctionalExportFilterTest" to the submission
        # 3) Publish and Export individual submissions as native xml
        # 2) Copy exported xml file into "tests" folder and rename as "FunctionalExportFilterTestSubmission<submission ID in your system>.xml"
        #    
        # Alternatively you can import an existing xml file from the tests folder and replace correct submission ID assigned in your system in the file name of the existing xml file
        public function testXMLExport() {

            // Initialize the request object with a page router
            $application = Application::get();
            $request = $application->getRequest();
            $journalPath = 'dnb33';
    
            // FIXME: Write and use a CLIRouter here (see classdoc)
            import('classes.core.PageRouter');
            $router = new PageRouter();
            $router->setApplication($application);
            $request->setRouter($router);

            Registry::set('request', $request);
            $router->_contextList = $application->getContextList();
            $router->_contextPaths = [$journalPath];            

            self::assertTrue($request->getContext() != null);

            $contextDao = Application::getContextDAO();
		    $journalFactory = $contextDao->getAll(true);
            
            while($journal = $journalFactory->next()) {
                $contextId = $journal->getId();
                    // check required plugin settings
                    // if (!$plugin->getSetting($journalId, 'username') ||
                    //     !$plugin->getSetting($journalId, 'password') ||
                    //     !$plugin->getSetting($journalId, 'folderId') ||
                    //     !$plugin->getSetting($journalId, 'automaticDeposit') ||
                    //     !$plugin->checkPluginSettings($journal)) continue;

                // load pubIds for this journal (they are currently not loaded via ScheduledTasks)
                PluginRegistry::loadCategory('pubIds', true, $contextId); 

                // find publications to test
                $submissions = Services::get('submission')->getMany([ 'contextId' => $contextId, 'status' => STATUS_PUBLISHED]);
                $testPublications = [];
                foreach ($submissions as $submission) {
                    $publication = $submission->getCurrentPublication();
                    $publicationId = $publication->getId();
                    $submissionId = $submission->getData('id');
                    $submissionKeywordDao = DAORegistry::getDAO('SubmissionKeywordDAO');
                    $controlledVocabulary = $submissionKeywordDao->getKeywords($publicationId, [$publication->getData('locale')]);
                    if (!empty($controlledVocabulary) && array_search('FunctionalExportFilterTest', $controlledVocabulary[$publication->getData('locale')]) !== false) {
                        $testPublications[] = $publication;
                    }
                }

                self::assertTrue(!empty($testPublications), "No publications found in context ".$contextId);

                // run test for each publication
                foreach ($testPublications as $publication) {
                    $submissionId = $publication->getData('submissionId');
                    $this->exportXML("plugins/importexport/dnb/tests/FunctionalExportFilterTestSubmission".$submissionId.".xml", $publication, $journal);
                }
                
            }

        }

        # @param string $filename name of native xml export file to load
        # @param string $submissionId current submission ID (if imported might not be the same as is given in the xml file)
        # @param string $galleyId current galley ID (if imported might not be the same as is given in the xml file)
        private function exportXML($filename, $publication, $context) {

            $submissionId = $publication->getData('submissionId');

            print_r("Testing submission ID: ", $submissionId);

            // define the submission metadata you expect in the exported file
            $exportFile = new DOMDocument();
            $exportFile->load($filename, LIBXML_PARSEHUGE);
            $xpathNative = new DOMXPath($exportFile);
            $xpathNative->registerNamespace("d", "http://pkp.sfu.ca");

            // prepare xml export
            $submissionDao = DAORegistry::getDAO('SubmissionDAO');
            $submission = $submissionDao->getById($submissionId);
            self::assertTrue($submission->getId() == $submissionId);

            $xmlFilter = new DNBXmlFilter(new FilterGroup("galley=>dnb-xml"));
            $xmlFilter->setDeployment(new DNBExportDeployment($context, $plugin = new DNBExportPlugin()));

            $plugin->canBeExported($submission, $issue, $documentGalleys, $supplementaryGalleys);

            foreach ($documentGalleys as $galley) { 

                // store submissionId in galley object
                $galley->setData('submissionId', $submission->getId());
                $galleyLocale = $galley->getLocale();

                $version = $xpathNative->query('//d:publication[not(@version < ../d:publication/@version)]')[0]->getAttribute('version');// only the latest version of each document is published and exported by DNB Export plugin
                $publication = "//d:publication[@version = ".$version."]";
    
                $publication_pubId = $this->getTextContent($xpathNative, $publication."/d:id[@type='doi']");
                $galley_pubIds = $xpathNative->query($publication."/d:article_galley/d:id[@type='doi']");
                //$language = $xpathNative->query("//submission_file ???? ")[0]->getAttribute('locale'); // not clear where this information is stored in native xml
                $author = $this->getTextContent($xpathNative, $publication."//d:author[1]/d:familyname").", ".$this->getTextContent($xpathNative, $publication."//d:author[1]/d:givenname");
                $access = $xpathNative->query($publication)[0]->getAttribute('access_status');
                $access = $access == 0 ? 'b' : $access;
                $prefix = $this->getTextContent($xpathNative, $publication."//d:prefix");
                if ($prefix != "") {$prefix = $prefix." ";}
                $title = strip_tags($prefix) . strip_tags($this->getTextContent($xpathNative, $publication."//d:title[@locale='".$galleyLocale."']"));
                $subtitle = strip_tags($this->getTextContent($xpathNative, $publication."//d:subtitle[@locale='".$galleyLocale."']"));
                $fullDatePublished = $xpathNative->query($publication)[0]->getAttribute('date_published');
                $datePublished = date('Y', strtotime($fullDatePublished));
                $day = "day:".date('d', strtotime($fullDatePublished));
                $month = "month:".date('m', strtotime($fullDatePublished));
                $year = "year:".date('Y', strtotime($fullDatePublished));
                $abstract = strip_tags($this->getTextContent($xpathNative, $publication."//d:abstract[@locale='".$galleyLocale."']"));
                $keywords = $xpathNative->query($publication."//d:keywords[@locale='".$galleyLocale."']/d:keyword");
                $licenseURL = $this->getTextContent($xpathNative, $publication."//d:licenseUrl");
                $volume = "volume:".$this->getTextContent($xpathNative, $publication."//d:volume");
                $number = "number:".$this->getTextContent($xpathNative, $publication."//d:number");
                if ($this->getTextContent($xpathNative, $publication."//d:year")) {
                    $issueYear = "year:".$this->getTextContent($xpathNative, $publication."//d:year");
                } else {
                    $issueYear = "NA"; // issue publication date not available in article XML (only in issue xml)
                }
                $publishedGalleys = $xpathNative->query($publication."[@status=3]"); // status 3 = "published"
                $supplementaryGenres = $xpathNative->query("//d:submission_file[@genre != 'Artikeltext' and @genre != 'Multimedia' and @genre != 'Bild' and @genre != 'HTML_Stylesheet']");

                $galleyFile = $galley->getFile();
                // if ($galleyFile && 'application/pdf' === $galleyFile->getData('mimetype')) {
                //     $testGalley = $galley;
                //     continue;
                // }
                $testGalley = $galley;
                self::assertTrue(isset($testGalley),"Test gallay not found.");

                // run xml export
                try {
                    $result = $xmlFilter->process($testGalley);
                } catch (ErrorException $e) {
                    switch($e->getCode()) {
                        case URN_SET_EXCEPTION:
                            return;
                            break;
                        default:
                            throw $e;
                    }
                }
                self::assertTrue($result instanceof DOMDocument);

                // verify xml export
                $xpathDNBFilter = new DOMXPath($result);

                // pubIds: DOI
                $DNBXMLFilterPubIds = $xpathDNBFilter->query("//*[@tag='024']/*[@code='a']");
                $DNBXMLFilterPubIds = array_map(function($i) {return $i->textContent;}, iterator_to_array($DNBXMLFilterPubIds));
                
                // test publication pubId
                // the last pubId should be the publication pubId
                $value = array_pop($DNBXMLFilterPubIds);
                self::assertTrue($value == $publication_pubId, "Publication DOI/URN was: ".print_r($value, true)."\nValue should have been: ".$publication_pubId);

                // test galley pubIds
                $nativeXMLGalleyPubIds = array_map(function($i) {return $i->textContent;}, iterator_to_array($galley_pubIds));
                if (count($DNBXMLFilterPubIds) && count($nativeXMLGalleyPubIds) > 0) {
                    // simple test wehther galley DOI is at all present in native xml export
                    self::assertTrue(!empty(array_intersect($nativeXMLGalleyPubIds, $DNBXMLFilterPubIds)), "DOI/URN: ".print_r($value, true)."\nNot found in native xml export!");
                }

                // test pubIds by galley
                // get native XML pubId for our test galley
                $nodes = $xpathNative->query(
                    "//d:article_galley/d:id[@type='internal'][text() = '".$testGalley->getId().
                    "']/parent::d:article_galley//d:id[@type='doi']"
                    , $publishedGalleys[0]);
                $value = $testGalley->getData('pub-id::doi');
                
                if ($nodes->length > 0) {
                    // native XML has a DOI for our galley
                    $nativeXMLDOI = $nodes[0]->textContent;
                    self::assertTrue($nativeXMLDOI == $value, "DOI was: ".print_r($value, true)."\nValue should have been: ".$nativeXMLDOI);
                } else {
                    // there should be no DOI for our galley
                    self::assertTrue(empty($value), "DOI was: ".print_r($value, true)."\nValue should have been empty");
                }

                // language
                $entries = $xpathDNBFilter->query("//*[@tag='041']/*[@code='a']");
                if ($entries->length > 0) {
                    $value = $entries[0]->textContent;
                    //self::assertTrue($value == $language, "Language was: ".print_r($value, true)."\nValue should have been: ".$language); // conversion of locale "en_US" -> "eng" required
                }

                // access status
                $entries = $xpathDNBFilter->query("//*[@tag='093']/*[@code='b']");
                if ($entries->length > 0) {
                    $value = $entries[0]->textContent;
                    self::assertTrue($value == $access, "Author was: ".print_r($value, true)."\nValue should have been: ".$access);
                }

                // author
                $entries = $xpathDNBFilter->query("//*[@tag='100']/*[@code='a']|//*[@tag='700']/*[@code='a']");
                self::assertTrue($entries->length > 0);
                foreach ($entries as $index => $entry) {
                    $value = $entry->textContent;
                    // author names
                    $author = $xpathNative->query($publication."//d:author[".($index+1)."]/d:familyname")[0]->textContent.", ".$xpathNative->query($publication."//d:author[".($index+1)."]/d:givenname")[0]->textContent;
                    self::assertTrue($value == $author, "Author was: ".print_r($value, true)."\nValue should have been: ".$author);
                }
                
                // orcid
                $entries = $xpathDNBFilter->query("//*[@tag='100']/*[@code='0']|//*[@tag='700']/*[@code='0']");
                foreach ($entries as $index => $entry) {
                    $value = $entry->textContent;
                    // orcid numbers
                    $orcid = '(orcid)'.basename($xpathNative->query($publication."//d:author[".($index+1)."]/d:orcid")[0]->textContent);
                    self::assertTrue($value == $orcid, "Orcid was: ".print_r($value, true)."\nValue should have been: ".$orcid);
                }

                // title and subtitle
                $entries = $xpathDNBFilter->query("//*[@tag='245']/*[@code='a']");
                if ($entries->length > 0) {
                    $value = $entries[0]->textContent;
                    self::assertTrue($value == $title, "Title was: ".print_r($value, true)."\nValue should have been: ".$title);
                }
                $entries = $xpathDNBFilter->query("//*[@tag='245']/*[@code='b']");
                if ($entries->length > 0) {
                    $value = $entries[0]->textContent;
                    self::assertTrue($value == $subtitle, "Subtitle was: ".print_r($value, true)."\nValue should have been: ".$subtitle);
                }

                // date published
                $entries = $xpathDNBFilter->query("//*[@tag='264']/*[@code='c']");
                if ($entries->length > 0) {
                    $value = $entries[0]->textContent;
                    self::assertTrue($value == $datePublished, "Date published was: ".print_r($value, true)."\nValue should have been: ".$datePublished);
                }

                // supplementary material
                $entries = $xpathDNBFilter->query("//*[@tag='300']/*[@code='e']");
                if ($entries->length > 0) {
                    $value = $entries[0]->textContent;
                    self::assertTrue($value == DNB_MSG_SUPPLEMENTARY, "hasSupplementary was: ".print_r($value, true)."\nValue should have been: ".DNB_MSG_SUPPLEMENTARY);
                    self::assertTrue(count($supplementaryGenres) > 0, "hasSupplementary was: ".print_r($value, true)."\nValue should have been empty");
                } else {
                    self::assertTrue(count($supplementaryGenres) == 0, "hasSupplementary was: empty\nValue should have been: ".DNB_MSG_SUPPLEMENTARY);                    
                }

                // additional info field in case supplememtary galleys cannot be unambiguously assigned to the main document galleys
                $entries = $xpathDNBFilter->query("//*[@tag='500']/*[@code='a']");
                if ($entries->length > 0) {
                    $value = $entries[0]->textContent;
                    self::assertTrue($value == DNB_MSG_SUPPLEMENTARY_AMBIGUOUS, "Ambiguous flag value error.");
                    self::assertTrue(count($supplementaryGenres) > 0, "Ambiguos flag set but no supplementary found.");
                } else {
                    // not able to test this here
                    // self::assertTrue(count($supplementaryGenres) == 0, "Ambiguos flag not set but supplementary found.");                    
                }

                // Abstract
                $entries = $xpathDNBFilter->query("//*[@tag='520']/*[@code='a']");
                if ($entries->length > 0) {
                    $value = $entries[0]->textContent;
                    $abstract = preg_replace("#[\s\n\r]+#",' ',$abstract); 
                    if (strlen($abstract) > 999)  {
                        $abstract = mb_substr($abstract, 0, 996,"UTF-8");
                        $abstract .= '...';
                    }
                    self::assertTrue($value == $abstract, "Abstract was: ".print_r($value, true)."\nValue should have been: ".$abstract);
                }

                // license URL
                $entries = $xpathDNBFilter->query("//*[@tag='540']/*[@code='u']");
                if ($entries->length > 0) {
                    $value = $entries[0]->textContent;
                    self::assertTrue($value == $licenseURL, "License URL was: ".print_r($value, true)."\nValue should have been: ".$licenseURL);
                }

                // keywords
                $entries = $xpathDNBFilter->query("//*[@tag='653']/*[@code='a']");
                self::assertTrue($entries->length == $keywords->length, "No of keywords was: ".print_r($entries->length, true)."\nValue should have been: ".$keywords->length);
                foreach ($entries as $entry) {
                    $value = $entry->textContent;
                    $nativeXMLKeywords = array_map(
                        function ($i) {return $i->textContent;},
                        iterator_to_array($keywords)
                    );
                    $found = array_search($value,$nativeXMLKeywords);
                    self::assertTrue($found !== false, "Keywords was: ".print_r($value, true)."\nValue should have been: ".$nativeXMLKeywords[$found]);
                }

                // issue data and article publication date
                $entries = $xpathDNBFilter->query("//*[@tag='773']/*[@code='g']");
                if ($entries->length > 0) {
                    $value = $entries[0]->textContent;
                    self::assertTrue($value == $volume, "Issue Volume was: ".print_r($value, true)."\nValue should have been: ".$volume);
                    $value = $entries[1]->textContent;
                    self::assertTrue($value == $number, "Issue Number was: ".print_r($value, true)."\nValue should have been: ".$number);
                    // issue day and month not available in native article XML
                    // $value = $entries[2]->textContent;
                    // self::assertTrue($value == $day, "Galley publication day was: ".print_r($value, true)."\nValue should have been: ".$day);
                    // $value = $entries[3]->textContent;
                    // self::assertTrue($value == $month, "Galley publication month was: ".print_r($value, true)."\nValue should have been: ".$month);
                    if ($issueYear != "NA") {
                        $value = $entries[2]->textContent;
                        self::assertTrue($value == $issueYear, "Issue publication year was: ".print_r($value, true)."\nValue should have been: ".$issueYear);
                    }
                }

                //  tag 773: journal data not provided in native xml export

                // file
                $entries = $xpathDNBFilter->query("//*[@tag='856']/*[@code='q']");
                if ($entries->length > 0) {
                    // get submission file ref from native XML for our test galley
                    $nodes = $xpathNative->query(
                        "//d:article_galley/d:id[@type='internal'][text() = '".$testGalley->getId().
                        "']/parent::d:article_galley/d:submission_file_ref"
                        , $publishedGalleys[0]);

                    if ($nodes->length == 0) {
                        self::assertTrue(strlen($galley->getData('urlRemote')) > 0, "File type not provided.");
                    } else {
                        self::assertTrue($nodes->length > 0, "File type not provided.");
                    }
                    if ($nodes->length > 0) {
                        $file = $xpathNative->query("//d:submission_file[@id=".$nodes[0]->getAttribute('id')."]/d:file");
                        $filetype = $file[0]->getAttribute('extension');
                        $value = $entries[0]->textContent;
                        self::assertTrue($value == $filetype, "Filetype was: ".print_r($value, true)."\nValue should have been: ".$filetype);
                    }
                }
            }
        }

        function getTextContent($xpathNative, $path) {
            $node = $xpathNative->query($path);
            return $node->length > 0 ? $node[0]->textContent : '';
        }
    }
?>
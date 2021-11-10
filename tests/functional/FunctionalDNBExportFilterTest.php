<?php

# run with 'sh plugins/importexport/dnb/tests/runTests.sh -d' from ojs folder (note phpunit needs to be installed by running 'composer.phar --working-dir=lib/pkp install' without the -no-dev option)
# This is not an automatic test. A native xml export file with the appropriate name and submission ID in your systems has to be placed in the tests folder.
# ATTENTION: Testing the export of pubIds is currently not possible. pubIds plugin category cannot be loaded into mock test environment.

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
        # 3) Publish and Export as native xml
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

            $journals = array();
            while($journal = $journalFactory->next()) {
                $contextId = $journal->getId();
                // check required plugin settings
                // if (!$plugin->getSetting($journalId, 'username') ||
                //     !$plugin->getSetting($journalId, 'password') ||
                //     !$plugin->getSetting($journalId, 'folderId') ||
                //     !$plugin->getSetting($journalId, 'automaticDeposit') ||
                //     !$plugin->checkPluginSettings($journal)) continue;

                $journals[] = $journal;
                unset($journal);
            }

            // load pubIds for this journal (they are currently not loaded via ScheduledTasks)
			PluginRegistry::loadCategory('pubIds', true, $contextId); 
            $test = Services::get('publication')->get(11);

            $test = Services::get('context')->getMany(['urlPath' => $journalPath])->current();

            // find publications to test
            $publications = Services::get('publication')->getMany([ 'contextId' => $contextId ]);
            $testPublications = [];
            foreach ($publications as $publication) {
                $publicationId = $publication->getId();
                $submissionId = $publication->getData('submissionId');
                $submissionKeywordDao = DAORegistry::getDAO('SubmissionKeywordDAO');
		        $controlledVocabulary = $submissionKeywordDao->getKeywords($publicationId, [$publication->getData('locale')]);
                if (!empty($controlledVocabulary) && array_search('FunctionalExportFilterTest', $controlledVocabulary[$publication->getData('locale')])) {
                    $testPublications[] = $publication;
                }
            }

            self::assertTrue(!empty($testPublications));

            // run test for each publication
            foreach ($testPublications as $publication) {
                $submissionId = $publication->getData('submissionId');
                $this->exportXML("plugins/importexport/dnb/tests/FunctionalExportFilterTestSubmission".$submissionId.".xml", $publication);
            }
        }

        # @param string $filename name of native xml export file to load
        # @param string $submissionId current submission ID (if imported might not be the same as is given in the xml file)
        # @param string $galleyId current galley ID (if imported might not be the same as is given in the xml file)
        private function exportXML($filename, $publication) {

            $context = Application::get()->getRequest()->getContext();
            $submissionId = $publication->getData('submissionId');

            // define the submission metadata you expect in the exported file
            $exportFile = new DOMDocument();
            $exportFile->load($filename);
            $xpathNative = new DOMXPath($exportFile);
            $xpathNative->registerNamespace("d", "http://pkp.sfu.ca");

            $version = $xpathNative->query('//d:publication[not(@version < ../d:publication/@version)]')[0]->getAttribute('version');// only the latest version of each document is published and exported by DNB Export plugin
            $publication = "//d:publication[@version = ".$version."]";

            $publication_pubId = $xpathNative->query($publication."/d:id[@type='doi']")[0]->textContent;
            $galley_pubIds = $xpathNative->query($publication."/d:article_galley/d:id[@type='doi']");
            //$language = $xpathNative->query("//submission_file ???? ")[0]->getAttribute('locale'); // not clear where this information is stored in native xml
            $author = $xpathNative->query($publication."//d:author[1]/d:familyname")[0]->textContent.", ".$xpathNative->query($publication."//d:author[1]/d:givenname")[0]->textContent;
            $access = $xpathNative->query($publication)[0]->getAttribute('access_status');
            $access = $access == 0 ? 'b' : $access;
            $title = strip_tags($xpathNative->query($publication."//d:prefix")[0]->textContent) . " " . strip_tags($xpathNative->query($publication."//d:title")[0]->textContent);
            $subtitle = strip_tags($xpathNative->query($publication."//d:subtitle")[0]->textContent);
            $fullDatePublished = $xpathNative->query($publication)[0]->getAttribute('date_published');
            $datePublished = date('Y', strtotime($fullDatePublished));
            $abstract = strip_tags($xpathNative->query($publication."//d:abstract")[0]->textContent);
            $keyword = $xpathNative->query($publication."//d:keyword")[0]->textContent;
            $licenseURL = $xpathNative->query($publication."//d:licenseUrl")[0]->textContent;
            $volume = "volume:".$xpathNative->query($publication."//d:volume")[0]->textContent;
            $number = "number:".$xpathNative->query($publication."//d:number")[0]->textContent;
            $day = "day:".date('d', strtotime($fullDatePublished));;
            $month = "month:".date('m', strtotime($fullDatePublished));
            $year = "year:".$xpathNative->query($publication."//d:year")[0]->textContent;
            $filetype = pathinfo($xpathNative->query("//d:submission_file//d:name")[0]->textContent, PATHINFO_EXTENSION);

            // prepare xml export
            $submissionDao = DAORegistry::getDAO('SubmissionDAO');
            $submission = $submissionDao->getById($submissionId);
            self::assertTrue($submission->getId() == $submissionId);

            $xmlFilter = new DNBXmlFilter(new FilterGroup("galley=>dnb-xml"));
            $xmlFilter->setDeployment(new DNBExportDeployment($context, new DNBExportPlugin()));

            $galleys = $submission->getGalleys();
            self::assertTrue(count($galleys) >= 1);

            foreach ($galleys as $galley) { // use first DPF galley
                if ('application/pdf' === $galley->getFile()->getData('mimetype')) {
                    $testGalley = $galley;
                    continue;
                }
            }

            self::assertTrue(isset($testGalley),"Test gallay not found.");

            // run xml export
            $result = $xmlFilter->process($testGalley); 
            self::assertTrue($result instanceof DOMDocument);

            // verify xml export
            $xpathDNBFilter = new DOMXPath($result);

            // pubIds: DOI
            $entries = $xpathDNBFilter->query("//*[@tag='024']/*[@code='a']");
            // the last pubId should be the publication pubId
            $value = $entries[count($entries)-1]->textContent;
            self::assertTrue($value == $publication_pubId, "DOI/URN was: ".print_r($value, true)."\nValue should have been: ".$publication_pubId);
            // test galley pubIds
            if ($galley_pubIds->length > 0) {
                // simple test wehther galley DOI is present in native xml export
                $galleyPubIds = array_map(function($i) {return $i->textContent;}, iterator_to_array($galley_pubIds));
                $xmlPubIds = array_map(function($i) {return $i->textContent;}, iterator_to_array($entries));
                self::assertTrue(!empty(array_intersect($galleyPubIds, $xmlPubIds)), "DOI/URN: ".print_r($value, true)."\nNot found in native xml export!");
            } else {
                self::assertTrue($entries->length == count($pubIds), "Number of pubIds was: ".$entries->length."\nValue should have been: ".count($pubIds));
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

            // abstract -> test will currently fail if abstract contains line breaks or > 999 characters because xml export flattens the abstract
            $entries = $xpathDNBFilter->query("//*[@tag='520']/*[@code='a']");
            if ($entries->length > 0) {
                $value = $entries[0]->textContent;
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
            if ($entries->length > 0) {
                $value = $entries[0]->textContent;
                self::assertTrue($value == $keyword, "Keywords was: ".print_r($value, true)."\nValue should have been: ".$keyword);
            }

            // issue data
            $entries = $xpathDNBFilter->query("//*[@tag='773']/*[@code='g']");
            if ($entries->length > 0) {
                $value = $entries[0]->textContent;
                self::assertTrue($value == $volume, "Volume was: ".print_r($value, true)."\nValue should have been: ".$volume);
                $value = $entries[1]->textContent;
                self::assertTrue($value == $number, "Number was: ".print_r($value, true)."\nValue should have been: ".$number);
                $value = $entries[2]->textContent;
                self::assertTrue($value == $day, "Day was: ".print_r($value, true)."\nValue should have been: ".$day);
                $value = $entries[3]->textContent;
                self::assertTrue($value == $month, "Month was: ".print_r($value, true)."\nValue should have been: ".$month);
                $value = $entries[4]->textContent;
                self::assertTrue($value == $year, "Year was: ".print_r($value, true)."\nValue should have been: ".$year);
            }

            //  tag 773: journal data not provided in native xml exort

            // file
            $entries = $xpathDNBFilter->query("//*[@tag='856']/*[@code='q']");
            if ($entries->length > 0) {
                $value = $entries[0]->textContent;
                self::assertTrue($value == $filetype, "Filetype was: ".print_r($value, true)."\nValue should have been: ".$filetype);
            }
        }
    }
?>
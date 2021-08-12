<?php

# run with 'sh plugins/importexport/dnb/tests/runTests.sh -d' from ojs folder
# This is not an automatic test. A native xml export fiel with the appropriate name and submission ID in your systems has to be placed in the tests folder.

    import('lib.pkp.tests.PKPTestCase');
    import('classes.issue.Issue');
    import('classes.submission.Submission');
    import('classes.article.ArticleGalley');
    import('plugins.importexport.dnb.filter.DNBXmlFilter');
    import('plugins.importexport.dnb.DNBExportPlugin');
    import('plugins.importexport.dnb.DNBExportDeployment');
    import('lib.pkp.classes.filter.FilterGroup');

    # TODO @RS fix registered and unregistered pubIds not loaded into test environment, unregistered pubIds are also not loaded via CLI-ScheduledTasks

    class FunctionalDNBExportFilterTest extends PKPTestCase {

        # How to use this test:
        # 1) Prepare a submission (including DOIs) in the OJS backend
        # 2) Add the keyword "FunctionalExportFilterTest" to the submission
        # 3) Publish and Export as native xml
        # 2) Copy exported xml file into "tests" folder and rename as "FunctionalExportFilterTestSubmission<submission ID in your system>.xml"
        #    
        # Alternatively you can import an existing xml file from the tests folder and replace correct submission ID assigned in your system in the file name of the existing xml file
        public function testXMLExport() {

            $contextId = "1"; // We use the first journal by default

            $contextDao = Application::getContextDAO();
            $context =$contextDao->getById($contextId);

            $publications = Services::get('publication')->getMany([ 'contextId' => $contextId ]);
            $testPublications = [];
            foreach ($publications as $publication) {
                $publicationId = $publication->getId();
                $submissionId = $publication->getData('submissionId');
                $submissionKeywordDao = DAORegistry::getDAO('SubmissionKeywordDAO');
		        $controlledVocabulary = $submissionKeywordDao->getKeywords($publicationId);
                if (array_search('FunctionalExportFilterTest', $controlledVocabulary['en_US'])) {
                    $testPublications[] = $publication;
                }
            }

            foreach ($testPublications as $publication) {
                $submissionId = $publication->getData('submissionId');
                $this->exportXML("plugins/importexport/dnb/tests/FunctionalExportFilterTestSubmission".$submissionId.".xml", $publication);
            }
        }

        # @param string $filename name of native xml export file to load
        # @param string $submissionId current submission ID (if imported might not be the same as is given in the xml file)
        # @param string $galleyId current galley ID (if imported might not be the same as is given in the xml file)
        private function exportXML($filename, $publication) {

            $submissionId = $publication->getData('submissionId');
            $contextId = "1"; // We use the first journal by default

            $contextDao = Application::getContextDAO();
            $context =$contextDao->getById($contextId);

            // define the submission metadata you expect in the exported file
            $exportFile = new DOMDocument();
            $exportFile->load($filename);
            $xpath = new DOMXPath($exportFile);
            $xpath->registerNamespace("d", "http://pkp.sfu.ca");

            $version = $xpath->query('//d:publication[not(@version < ../d:publication/@version)]')[0]->getAttribute('version');// only the latest version of each document is published and exported by DNB Export plugin
            $publication = "//d:publication[@version = ".$version."]";

            $pubIds = $xpath->query($publication."//d:id[not(@type='internal')]");
            //$language = $xpath->query("//submission_file ???? ")[0]->getAttribute('locale'); // not clear where this information is stored in native xml
            $author = $xpath->query($publication."//d:author[1]/d:familyname")[0]->textContent.", ".$xpath->query($publication."//d:author[1]/d:givenname")[0]->textContent;
            $access = $xpath->query($publication)[0]->getAttribute('access_status');
            $access = $access == 0 ? 'b' : $access;
            $title = strip_tags($xpath->query($publication."//d:title")[0]->textContent);
            $subtitle = strip_tags($xpath->query($publication."//d:subtitle")[0]->textContent);
            $fullDatePublished = $xpath->query($publication)[0]->getAttribute('date_published');
            $datePublished = date('Y', strtotime($fullDatePublished));
            $abstract = strip_tags($xpath->query($publication."//d:abstract")[0]->textContent);
            $keyword = $xpath->query($publication."//d:keyword")[0]->textContent;
            $licenseURL = $xpath->query($publication."//d:licenseUrl")[0]->textContent;
            $volume = "volume:".$xpath->query($publication."//d:volume")[0]->textContent;
            $number = "number:".$xpath->query($publication."//d:number")[0]->textContent;
            $day = "day:".date('d', strtotime($fullDatePublished));;
            $month = "month:".date('m', strtotime($fullDatePublished));
            $year = "year:".$xpath->query($publication."//d:year")[0]->textContent;
            $filetype = pathinfo($xpath->query("//d:submission_file//d:name")[0]->textContent, PATHINFO_EXTENSION);

            // prepare xml export
            $submissionDao = DAORegistry::getDAO('SubmissionDAO');
            $submission = $submissionDao->getById($submissionId);
            self::assertTrue($submission->getId() == $submissionId);

            //TODO @RS FIX pubId not exported because they are not loaded from publication_settings table !!! -> Github Issue
            $test = Services::get('publication')->get(11);

            $xmlFilter = new DNBXmlFilter(new FilterGroup("galley=>dnb-xml"));
            $xmlFilter->setDeployment(new DNBExportDeployment($context, new DNBExportPlugin()));

            $galleys = $submission->getGalleys();
            self::assertTrue(count($galleys) >= 1);

            foreach ($galleys as $galley) { // use first DPF galley
                if ('PDF' === $galley->getData('label')) {
                    $testGalley = $galley;
                    continue;
                }
            }

            // run xml export
            $result = $xmlFilter->process($testGalley); 
            self::assertTrue($result instanceof DOMDocument);

            // verify xml export
            $xpath = new DOMXPath($result);

            // pubIds: DOI or URN
            $entries = $xpath->query("//*[@tag='024']/*[@code='a']");
            if ($entries->length > 0) {
                for ($i = 0; $i < $entries->length; $i++) {
                    $entry = $entries[$i];
                    $pubId = $pubIds[$i];
                    $value = $entry->textContent;
                    self::assertTrue($value == $author, "DOI/URN was: ".print_r($value, true)."\nValue should have been: ".$author);
                }
            } else {
               self::assertTrue($entries->length == count($pubIds), "Number of pubIds was: ".$entries->length."\nValue should have been: ".count($pubIds));
            }

            // language
            $entries = $xpath->query("//*[@tag='041']/*[@code='a']");
            if ($entries->length > 0) {
                $value = $entries[0]->textContent;
                //self::assertTrue($value == $language, "Language was: ".print_r($value, true)."\nValue should have been: ".$language); // conversion of locale "en_US" -> "eng" required
            }

            // access status
            $entries = $xpath->query("//*[@tag='093']/*[@code='b']");
            if ($entries->length > 0) {
                $value = $entries[0]->textContent;
                self::assertTrue($value == $access, "Author was: ".print_r($value, true)."\nValue should have been: ".$access);
            }

            // author
            $entries = $xpath->query("//*[@tag='100']/*[@code='a']");
            if ($entries->length > 0) {
                $value = $entries[0]->textContent;
                self::assertTrue($value == $author, "Author was: ".print_r($value, true)."\nValue should have been: ".$author);
            }
            
            // title and subtitle
            $entries = $xpath->query("//*[@tag='245']/*[@code='a']");
            if ($entries->length > 0) {
                $value = $entries[0]->textContent;
                self::assertTrue($value == $title, "Title was: ".print_r($value, true)."\nValue should have been: ".$title);
            }
            $entries = $xpath->query("//*[@tag='245']/*[@code='b']");
            if ($entries->length > 0) {
                $value = $entries[0]->textContent;
                self::assertTrue($value == $subtitle, "Subtitle was: ".print_r($value, true)."\nValue should have been: ".$subtitle);
            }

            // date published
            $entries = $xpath->query("//*[@tag='264']/*[@code='c']");
            if ($entries->length > 0) {
                $value = $entries[0]->textContent;
                self::assertTrue($value == $datePublished, "Date published was: ".print_r($value, true)."\nValue should have been: ".$datePublished);
            }

            // abstract -> test will currently fail if abstract contains line breaks or > 999 characters because xml export flattens the abstract
            $entries = $xpath->query("//*[@tag='520']/*[@code='a']");
            if ($entries->length > 0) {
                $value = $entries[0]->textContent;
                self::assertTrue($value == $abstract, "Abstract was: ".print_r($value, true)."\nValue should have been: ".$abstract);
            }

            // license URL
            $entries = $xpath->query("//*[@tag='540']/*[@code='u']");
            if ($entries->length > 0) {
                $value = $entries[0]->textContent;
                self::assertTrue($value == $licenseURL, "License URL was: ".print_r($value, true)."\nValue should have been: ".$licenseURL);
            }

            // keywords
            $entries = $xpath->query("//*[@tag='653']/*[@code='a']");
            if ($entries->length > 0) {
                $value = $entries[0]->textContent;
                self::assertTrue($value == $keyword, "Keywords was: ".print_r($value, true)."\nValue should have been: ".$keyword);
            }

            // issue data
            $entries = $xpath->query("//*[@tag='773']/*[@code='g']");
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
            $entries = $xpath->query("//*[@tag='856']/*[@code='q']");
            if ($entries->length > 0) {
                $value = $entries[0]->textContent;
                self::assertTrue($value == $filetype, "Filetype was: ".print_r($value, true)."\nValue should have been: ".$filetype);
            }
        }
    }
?>
<?php

/**
 * @file plugins/importexport/dnb/filter/DNBXmlFilter.inc.php
 *
 * Copyright (c) 2021 Center for Digital Systems (CeDiS), Universitätsbibliothek Freie Universität Berlin
 * Distributed under the GNU GPL v3. For full terms see the plugin file LICENSE.
 * Author: Bozana Bokan, Ronald Steffen
 *
 * @class DNBXmlFilter
 * @ingroup plugins_importexport_dnb
 *
 * @brief Class that converts an Article to a DNB XML document.
 */

namespace APP\plugins\importexport\dnb\filter;

use APP\core\Application;
use APP\core\Services;
use APP\facades\Repo;
use PKP\i18n\LocaleConversion;
use PKP\db\DAORegistry;
use PKP\filter\PersistableFilter;
use PKP\plugins\importexport\native\filter\NativeExportFilter;
use APP\plugins\importexport\dnb\DNBPluginException;
use PKP\core\PKPString;

define('DNB_XML_NON_VALID_CHARCTERS_EXCEPTION', 100);
define('DNB_FIRST_AUTHOR_NOT_REGISTERED_EXCEPTION', 102);
define('DNB_URN_SET_EXCEPTION', 101);

define('DNB_MSG_SUPPLEMENTARY','Begleitmaterial');
define('DNB_MSG_SUPPLEMENTARY_AMBIGUOUS','Artikel in verschiedenen Dokumentversionen mit Begleitmaterial veröffentlicht');

class DNBXmlFilter extends \PKP\plugins\importexport\native\filter\NativeExportFilter {
	/**
	 * Constructor
	 * @param $filterGroup FilterGroup
	 */
	function __construct($filterGroup) {
		$this->setDisplayName('DNB XML export');
		parent::__construct($filterGroup);
	}

	//
	// Implement template methods from PersistableFilter
	//
	/**
	 * @copydoc PersistableFilter::getClassName()
	 */
	function getClassName(): string {
		return 'APP\plugins\importexport\dnb\filter\DNBXmlFilter';
	}

	//
	// Implement template methods from Filter
	//
	/**
	 * @see Filter::process()
	 * @param $pubObjects ArticleGalley
	 * @return DOMDocument
	 */
	function &process(&$pubObject) {
		// Create the XML document
		$doc = new \DOMDocument('1.0', 'utf-8');
		$doc->preserveWhiteSpace = false;
		$doc->formatOutput = true;

		// prepare basic application objects required later
		$deployment = $this->getDeployment();
		$journal = $deployment->getContext();
		$plugin = $deployment->getPlugin();
		$cache = $plugin->getCache();
		$request = Application::get()->getRequest();

		// Get all required data objects
		$issue = $submission = $galleyFile = null;
		$galley = $pubObject;

		$submissionId = $galley->getData('submissionId');
		if ($cache->isCached('articles', $submissionId)) {
			$submission = $cache->get('articles', $submissionId);
		} else {
			$submission = Repo::submission()->get($submissionId);
			if ($submission) $cache->add($submission, null);
		}

		$issue = Repo::issue()->getBySubmissionId($submission->getId());
		$issueId = $issue->getId();

		// abort export in case any URN is set on the submission/article level, this is a special case that has to be discussed with DNB and implemented differently in each case
		$submissionURN = $submission->getStoredPubId('other::urnDNB');
		if (empty($submissionURN)) $submissionURN = $submission->getStoredPubId('other::urn');
		if (!empty($submissionURN)) {
			$msg = __('plugins.importexport.dnb.export.error.urnSet', array('submissionId' => $submissionId, 'urn' => $submissionURN));
		    throw new DNBPluginException($msg, DNB_URN_SET_EXCEPTION);
		};
		
		// Data we will need later
		$language = LocaleConversion::get3LetterIsoFromLocale($galley->getLocale());
		$datePublished = $submission->getDatePublished();
		if (!$datePublished) $datePublished = $issue->getDatePublished();
		assert(!empty($datePublished));
		$yearYYYY = date('Y', strtotime($datePublished));
		$yearYY = date('y', strtotime($datePublished));
		$month = date('m', strtotime($datePublished));
		$day = date('d', strtotime($datePublished));

		// get contributers and split into authors and translators
		$publication = $submission->getCurrentPublication();
		$contributors = $publication->getData('authors');
		$authors = $translators = [];
		foreach ($contributors as $contributor) {
			$nameLocalKey = $contributor->getUserGroup()->getData('nameLocaleKey');
			if ($nameLocalKey == 'default.groups.name.author') {
				$authors[] = $contributor;
			} elseif ($nameLocalKey == 'default.groups.name.translator') {
				$translators[] = $contributor;
			};
		}

		// get primary author
		if (is_array($authors) && !empty($authors)) {
			// get and remove first author from the array
			// so the array can be used later in the field 700 1 _
			$firstAuthor = array_shift($authors);
		}
		if (!$firstAuthor) {
			throw new DNBPluginException("DNBXmlFilter Error: ", DNB_FIRST_AUTHOR_NOT_REGISTERED_EXCEPTION);
		}
		
		// is open access
		$openAccess = false;
		if ($journal->getSetting('publishingMode') == PUBLISHING_MODE_OPEN) {
			$openAccess = true;
		} else if ($journal->getSetting('publishingMode') == PUBLISHING_MODE_SUBSCRIPTION) {
			if ($issue->getAccessStatus() == 0 || $issue->getAccessStatus() == ISSUE_ACCESS_OPEN) {
				$openAccess = true;
			} else if ($issue->getAccessStatus() == ISSUE_ACCESS_SUBSCRIPTION) {
				if ($submission->getCurrentPublication()->getData('accessStatus') == ARTICLE_ACCESS_OPEN) {
					$openAccess = true;
				}
			}
		}
		$archiveAccess = $plugin->getSetting($journal->getId(), 'archiveAccess');
		assert($openAccess || $archiveAccess);

		// Create the root node
		$rootNode = $this->createRootNode($doc);
		$doc->appendChild($rootNode);

		// record node
		$recordNode = $doc->createElementNS($deployment->getNamespace(), 'record');
		$rootNode->appendChild($recordNode);

		// leader
		$recordNode->appendChild($node = $doc->createElementNS($deployment->getNamespace(), 'leader', '00000naa a2200000 u 4500'));

		// now follow all fields ordered by MARC field number

		// control fields: 001, 007 and 008
		$recordNode->appendChild($node = $doc->createElementNS($deployment->getNamespace(), 'controlfield', $galley->getId()));
		$node->setAttribute('tag', '001');
		$recordNode->appendChild($node = $doc->createElementNS($deployment->getNamespace(), 'controlfield', ' cr |||||||||||'));
		$node->setAttribute('tag', '007');
		$recordNode->appendChild($node = $doc->createElementNS($deployment->getNamespace(), 'controlfield', $yearYY.$month.$day.'s'.$yearYYYY.'||||xx#|||| ||||| ||||| '.$language.'||'));
		$node->setAttribute('tag', '008');

		// Marc 024 urn
		$urn = $galley->getStoredPubId('other::urnDNB');
		if (empty($urn)) $urn = $galley->getStoredPubId('other::urn');
		if (!empty($urn)) {
			$urnDatafield024 = $this->createDatafieldNode($doc, $recordNode, '024', '7', ' ');
			$this->createSubfieldNode($doc, $urnDatafield024, 'a', $urn);
			$this->createSubfieldNode($doc, $urnDatafield024, '2', 'urn');
		}

		// Marc 024 DOI
		// according the the latest arrangement with DNB both, article and galley DOIs will be submited to the DNB  
		$galleyDOI = $galley->getStoredPubId('doi');
		if (!empty($galleyDOI)) {
			$doiDatafield024 = $this->createDatafieldNode($doc, $recordNode, '024', '7', ' ');
			$this->createSubfieldNode($doc, $doiDatafield024, 'a', $galleyDOI);
			$this->createSubfieldNode($doc, $doiDatafield024, '2', 'doi');
		}
		$submissionDOI = $submission->getStoredPubId('doi');
		if (!empty($submissionDOI)) {
		    $doiDatafield024 = $this->createDatafieldNode($doc, $recordNode, '024', '7', ' ');
		    $this->createSubfieldNode($doc, $doiDatafield024, 'a', $submissionDOI);
		    $this->createSubfieldNode($doc, $doiDatafield024, '2', 'doi');
		}

		// Marc 040 plugin version
		$datafield040 = $this->createDatafieldNode($doc, $recordNode, '040', ' ', ' ');
		$versionDao = DAORegistry::getDAO('VersionDAO'); /* @var $versionDao VersionDAO */
		$version = $versionDao->getCurrentVersion('plugins.importexport', $plugin->getPluginSettingsPrefix(), true);
		$this->createSubfieldNode($doc, $datafield040, 'a', "OJS DNB-Export-Plugin Version ".$version->getVersionString());

		// Marc 041 language
		$datafield041 = $this->createDatafieldNode($doc, $recordNode, '041', ' ', ' ');
		$this->createSubfieldNode($doc, $datafield041, 'a', $language);

		// Marc 093 access to the archived article
		$datafield093 = $this->createDatafieldNode($doc, $recordNode, '093', ' ', ' ');
		if ($openAccess) {
			$this->createSubfieldNode($doc, $datafield093, 'b', 'b');
		} else {
			$this->createSubfieldNode($doc, $datafield093, 'b', $archiveAccess);
		}

		// Marc 100 first author
		$datafield100 = $this->createDatafieldNode($doc, $recordNode, '100', '1', ' ');
		$this->createSubfieldNode($doc, $datafield100, 'a', $firstAuthor->getFullName(false,true));
		if (!empty($firstAuthor->getData('orcidAccessToken'))) {
            $this->createSubfieldNode($doc, $datafield100, '0', '(orcid)'.basename($firstAuthor->getOrcid()));
    	}
		$this->createSubfieldNode($doc, $datafield100, '4', 'aut');

		// Marc 254 title
		// title
		$title = $submission->getTitle($galley->getLocale());
		if (empty($title)) $title = $submission->getTitle($submission->getLocale());
		assert(!empty($title));
		//remove line breaks in case DNB doesn't like them (they are allowed in XML 1.0 spec)
		$title = preg_replace("#[\s\n\r]+#",' ',$title);
		$datafield245 = $this->createDatafieldNode($doc, $recordNode, '245', '0', '0');
		$this->createSubfieldNode($doc, $datafield245, 'a', $title);

		// subtitle
		$subTitle = $submission->getSubtitle($galley->getLocale());
		if (empty($subTitle)) $subTitle = $submission->getSubtitle($submission->getLocale());
		if (!empty($subTitle)) {
		    //remove line breaks in case DNB doesn't like them (they are allowed in XML 1.0 spec)
		    $subTitle = preg_replace("#[\s\n\r]+#",' ',$subTitle); 
			$this->createSubfieldNode($doc, $datafield245, 'b', $subTitle);
		}

		// Marc 264 date published
		$datafield264 = $this->createDatafieldNode($doc, $recordNode, '264', ' ', '1');
		$this->createSubfieldNode($doc, $datafield264, 'c', $yearYYYY);

		// Marc 300 Supplementary
		// this package will be delivered including supplementary material
		if ($submission->getData('hasSupplementary')) {
			// !!! Do not change this message without consultation of the DNB !!!
			$datafield300 = $this->createDatafieldNode($doc, $recordNode, '300', ' ', ' ');
			$this->createSubfieldNode($doc, $datafield300, 'e', DNB_MSG_SUPPLEMENTARY);
		}

		// Marc 500 article level URN (only if galley level URN does not exist)
		if (empty($urn)) {
			$submissionURN = $submission->getStoredPubId('other::urnDNB');
			if (empty($submissionURN)) $submissionURN = $submission->getStoredPubId('other::urn');
			if (!empty($submissionURN)) {
				$urnDatafield500 = $this->createDatafieldNode($doc, $recordNode, '500', ' ', ' ');
				if (!empty($submissionURN)) $this->createSubfieldNode($doc, $urnDatafield500, 'a', 'URN: ' . $submissionURN);
			}
		}

		// Marc 500 additional information
		// additional info field in case supplememtary galleys cannot be unambiguously assigned to the main document galleys
		if ($submission->getData('supplementaryNotAssignable')) {
			// !!! Do not change this message without consultation of the DNB !!!
			$supplementaryDatafield500 = $this->createDatafieldNode($doc, $recordNode, '500', ' ', ' ');
			$this->createSubfieldNode($doc, $supplementaryDatafield500, 'a', DNB_MSG_SUPPLEMENTARY_AMBIGUOUS);
		}

		// Marc 506 Access Status
		if ($openAccess) {
			$datafield506 = $this->createDatafieldNode($doc, $recordNode, '506', '0', ' ');
			$this->createSubfieldNode($doc, $datafield506, 'a', 'open-access');
		} else {
			$datafield506 = $this->createDatafieldNode($doc, $recordNode, '506', '1', ' ');
			$this->createSubfieldNode($doc, $datafield506, 'a', 'closed-access');
		}

		// Marc 520 abstract
		$abstract = $submission->getAbstract($galley->getLocale());
		if (empty($abstract)) $abstract = $submission->getAbstract($submission->getLocale());
		if (!empty($abstract)) {
			$abstract = trim(PKPString::html2text($abstract));
			//remove line breaks in case DNB doesn't like them (they are allowed in XML 1.0 spec)
			$abstract = preg_replace("#[\s\n\r]+#",' ',$abstract); 
			if (strlen($abstract) > 999)  {
				$abstract = mb_substr($abstract, 0, 996,"UTF-8");
				$abstract .= '...';
			}
			$abstractURL = $request->url($journal->getPath(), 'article', 'view', array($submissionId));
			$datafield520 = $this->createDatafieldNode($doc, $recordNode, '520', '3', ' ');
			$this->createSubfieldNode($doc, $datafield520, 'a', $abstract);
			$this->createSubfieldNode($doc, $datafield520, 'u', $abstractURL);
		}

		// Marc 540 license URL
		$licenseURL = $submission->getLicenseURL();
		if (empty($licenseURL)) {
			// copyright notice
			$copyrightNotice = $journal->getSetting('copyrightNotice', $galley->getLocale());
			if (empty($copyrightNotice)) $copyrightNotice = $journal->getSetting('copyrightNotice', $journal->getPrimaryLocale());
			if (!empty($copyrightNotice)) {
				// link to the article view page where the copyright notice can be found
			    $licenseURL = $request->url($journal->getPath(), 'article', 'view', array($submission->getId()));
			}
		}
		if (!empty($licenseURL)) {
			$datafield540 = $this->createDatafieldNode($doc, $recordNode, '540', ' ', ' ');
			$this->createSubfieldNode($doc, $datafield540, 'u', $licenseURL);
			$ccLicenseBadge = Application::get()->getCCLicenseBadge($publication->getData('licenseUrl'), $galley->getLocale());
			// only if there is a cc-Badge we know a predefined cc license was selected, otherwise its a custom license Url
			if ($ccLicenseBadge) {
				$this->createSubfieldNode($doc, $datafield540, '2', 'cc');
				if (preg_match('#\d+\.\d+#',$licenseURL, $matchesVersion) && preg_match('#by(-\w{2})*#',$licenseURL, $matchesCode)) {
					$ccVersion = $matchesVersion[0];
					$ccCode = $matchesCode[0];
					$this->createSubfieldNode($doc, $datafield540, 'f', 'cc-'.$ccCode.'.'.$ccVersion);
				}
				preg_match('#">(\w.*)<\/a#', $ccLicenseBadge, $matches);
				if ($matches) {
					$this->createSubfieldNode($doc, $datafield540, 'a', $matches[1]);
				}
			}
		}

		// Marc 563 keywords
		$submissionKeywordDao = DAORegistry::getDAO('SubmissionKeywordDAO'); /* @var $submissionKeywordDao SubmissionKeywordDAO */
		$controlledVocabulary = $submissionKeywordDao->getKeywords($submission->getCurrentPublication()->getId(), array($galley->getLocale()));
		if (!empty($controlledVocabulary[$galley->getLocale()])) {
			$datafield653 = $this->createDatafieldNode($doc, $recordNode, '653', ' ', ' ');
			foreach ($controlledVocabulary[$galley->getLocale()] as $controlledVocabularyItem) {
				$this->createSubfieldNode($doc, $datafield653, 'a', $controlledVocabularyItem);
			}
		}

		// Marc 700 contributers
		// other authors
		foreach ((array) $authors as $author) {
			$datafield700 = $this->createDatafieldNode($doc, $recordNode, '700', '1', ' ');
			$this->createSubfieldNode($doc, $datafield700, 'a', $author->getFullName(false,true));
			if (!empty($author->getData('orcidAccessToken'))) {
				$this->createSubfieldNode($doc, $datafield700, '0', '(orcid)'.basename($author->getOrcid()));
			}
			$this->createSubfieldNode($doc, $datafield700, '4', 'aut');
		}

		// translators
		foreach ((array) $translators as $translator) {
		    $datafield700 = $this->createDatafieldNode($doc, $recordNode, '700', '1', ' ');
		    $this->createSubfieldNode($doc, $datafield700, 'a', $translator->getFullName(false,true));
			if (!empty($translator->getData('orcidAccessToken'))) {
				$this->createSubfieldNode($doc, $datafield700, '0', '(orcid)'.basename($translator->getOrcid()));
			}
		    $this->createSubfieldNode($doc, $datafield700, '4', 'trl');
		}
		
		// Marc 773 journal and issue data
		// at least the year has to be provided
		// 17.2.2022
		//   - provide issue year if available, if not year of publication date of the issue
		//   - remove day and month
		$volume = $issue->getShowVolume()?$issue->getVolume():null;
		$number = $issue->getShowNumber()?$issue->getNumber():null;
		$year = $issue->getShowYear()?$issue->getYear():null;
		$issueDatafield773 = $this->createDatafieldNode($doc, $recordNode, '773', '1', ' ');
		if (!empty($volume)) $this->createSubfieldNode($doc, $issueDatafield773, 'g', 'volume:'.$volume);
		if (!empty($number)) $this->createSubfieldNode($doc, $issueDatafield773, 'g', 'number:'.$number);
		if (empty($year)) {
			$year = date('Y', strtotime($issue->getDatePublished()));
		}
		$this->createSubfieldNode($doc, $issueDatafield773, 'g', 'year:'.$year);
		$this->createSubfieldNode($doc, $issueDatafield773, '7', 'nnas');

		// journal data
		// there has to be an ISSN
		$issn = $journal->getData('onlineIssn');
		if (empty($issn)) $issn = $journal->getData('printIssn');
		assert(!empty($issn));
		$journalDatafield773 = $this->createDatafieldNode($doc, $recordNode, '773', '1', '8');
		$this->createSubfieldNode($doc, $journalDatafield773, 'x', $issn);

		// Marc 856 file data
		$galleyURL = $request->url($journal->getPath(), 'article', 'view', array($submissionId, $galley->getId()));
		$datafield856 = $this->createDatafieldNode($doc, $recordNode, '856', '4', ' ');
		$this->createSubfieldNode($doc, $datafield856, 'u', $galleyURL);
		$this->createSubfieldNode($doc, $datafield856, 'q', $this->_getGalleyFileType($galley));
		$galleyFile = $galley->getFile();
		if (isset($galleyFile)) {
		    # galley is a local file
		    $fileSize = Services::get('file')->fs->fileSize($galleyFile->getData('path'));
		} else {
		    # galley is a remote URL and we stored the filesize before
		    $fileSize = $galley->getData('fileSize');
		}
		if ($fileSize > 0) $this->createSubfieldNode($doc, $datafield856, 's', Services::get('file')->getNiceFileSize($fileSize));

		return $doc;
	}
	
	/**
	 * Create and return the root node.
	 * @param $doc DOMDocument
	 * @return DOMElement
	 */
	function createRootNode($doc) {
		$deployment = $this->getDeployment();
		$rootNode = $doc->createElementNS($deployment->getNamespace(), $deployment->getRootElementName());
		$rootNode->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:xsi', $deployment->getXmlSchemaInstance());
		$rootNode->setAttribute('xsi:schemaLocation', $deployment->getNamespace() . ' ' . $deployment->getSchemaFilename());
		return $rootNode;
	}

	/**
	 * Generate the datafield node.
	 * @param $doc DOMElement
	 * @param $recordNode DOMElement
	 * @param $tag string 'tag' attribute
	 * @param $ind1 string 'ind1' attribute
	 * @param $ind2 string 'ind2' attribute
	 * @return DOMElement
	 */
	function createDatafieldNode($doc, $recordNode, $tag, $ind1, $ind2) {
		$deployment = $this->getDeployment();
		$datafieldNode = $doc->createElementNS($deployment->getNamespace(), 'datafield');
		$datafieldNode->setAttribute('tag', $tag);
		$datafieldNode->setAttribute('ind1', $ind1);
		$datafieldNode->setAttribute('ind2', $ind2);
		$recordNode->appendChild($datafieldNode);
		return $datafieldNode;
	}

	/**
	 * Generate the subfield node.
	 * @param $doc DOMElement
	 * @param $datafieldNode DOMElement
	 * @param $code string 'code' attribute
	 * @param $value string Element text value
	 */
	function createSubfieldNode($doc, $datafieldNode, $code, $value) {
		$deployment = $this->getDeployment();
		$node = $doc->createElementNS($deployment->getNamespace(), 'subfield');
		//check for characters not allowed according to XML 1.0 specification (https://www.w3.org/TR/2006/REC-xml-20060816/Overview.html#NT-Char)
		$matches = array();
		//use for debugging:
		//if ($datafieldNode->getAttribute('tag') == '520') {$value = $value . mb_chr(0,'utf-8').chr(11) . $value;}
		$res = preg_match_all('/[^\x09\x0A\x0D\x20-\xFF]/', $value, $matches,PREG_OFFSET_CAPTURE);
    	if ($res != 0) {
		    // libxml will strip input at the first occurance of an non-allowed character, subsequent character will be lost
		    // we don't remove these characters automatically because user has to be aware of the issue
    	    throw new DNBPluginException("Character code ".ord($matches[0][0][0])." found at position ".$matches[0][0][1]." in MARC21 datafield node ".$datafieldNode->getAttribute('tag')." code ".$code, DNB_XML_NON_VALID_CHARCTERS_EXCEPTION);
		}

		$node->appendChild($doc->createTextNode($value));
		$datafieldNode->appendChild($node);
		$node->setAttribute('code', $code);
	}

	/**
	 * Generate the DNB file type.
	 * @param $galley ArticleGalley
	 * @return string pdf or epub (currently supported by DNB)
	 */
	function _getGalleyFileType($galley) {
		if ($galley->isPdfGalley()) {
			return 'pdf';
		}
		
		switch ($galley->getFileType()) {
			case 'application/epub+zip':
				return 'epub';
			case 'text/plain':
				return 'txt';
			case 'text/html':
				return 'html';
			default:
				return $galley->getData('fileType');
		}
	}
}

?>

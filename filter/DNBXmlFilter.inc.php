<?php

/**
 * @file plugins/importexport/dnb/filter/DNBXmlFilter.inc.php
 *
 * Copyright (c) 2017 Center for Digital Systems (CeDiS), Freie Universität Berlin
 * Distributed under the GNU GPL v2. For full terms see the plugin file LICENSE.
 * Author: Bozana Bokan
 * Last update: May 15, 2017
 *
 * @class DNBXmlFilter
 * @ingroup plugins_importexport_dnb
 *
 * @brief Class that converts an Article to a DNB XML document.
 */

import('lib.pkp.plugins.importexport.native.filter.NativeExportFilter');
define('XML_NON_VALID_CHARCTERS', 100);
define('FIRST_AUTHOR_NOT_REGISTERED', 102);
define('URN_SET', 101);
define('MESSAGE_URN_SET','An URN has been set.'); // @RS refine

class DNBXmlFilter extends NativeExportFilter {
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
	function getClassName() {
		return 'plugins.importexport.dnb.filter.DNBXmlFilter';
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
		$doc = new DOMDocument('1.0', 'utf-8');
		$doc->preserveWhiteSpace = false;
		$doc->formatOutput = true;
		$deployment = $this->getDeployment();
		$journal = $deployment->getContext();
		$plugin = $deployment->getPlugin();
		$cache = $plugin->getCache();
		$request = Application::getRequest();

		// Get all objects
		$issue = $submission = $galley = $galleyFile = null;
		$galley = $pubObject;
		$galleyFile = $galley->getFile();
		$submissionId = $galleyFile->getSubmissionId();
		if ($cache->isCached('articles', $submissionId)) {
			$submission = $cache->get('articles', $submissionId);
		} else {
			$submissionDao = DAORegistry::getDAO('SubmissionDAO'); /* @var $submissionDao SubmissionDAO */
			$submission = $submissionDao->getById($submissionId);
			
			if ($submission) $cache->add($submission, null);
		}
		$issueDao = DAORegistry::getDAO('IssueDAO');
		$issueId = $issueDao->getBySubmissionId($submission->getId())->getId();	

		if ($cache->isCached('issues', $issueId)) {
			$issue = $cache->get('issues', $issueId);
		} else {
			$issueDao = DAORegistry::getDAO('IssueDAO'); /* @var $issueDao IssueDAO */
			$issue = $issueDao->getById($issueId, $journal->getId());
			if ($issue) $cache->add($issue, null);
		}

		// abort export in case any URN is set, this is a special case that has to be discussed with DNB and implmented differently in each case
		$submissionURN = $submission->getStoredPubId('other::urnDNB');
		if (empty($submissionURN)) $submissionURN = $submission->getStoredPubId('other::urn');
		if (!empty($submissionURN)) {
		    throw new ErrorException(MESSAGE_URN_SET, URN_SET);
		};
		
		// Data we will need later
		$language = AppLocale::get3LetterIsoFromLocale($galley->getLocale());
		$datePublished = $submission->getDatePublished();
		if (!$datePublished) $datePublished = $issue->getDatePublished();
		assert(!empty($datePublished));
		$yearYYYY = date('Y', strtotime($datePublished));
		$yearYY = date('y', strtotime($datePublished));
		$month = date('m', strtotime($datePublished));
		$day = date('d', strtotime($datePublished));
		$contributors = $submission->getAuthors();

		// extract submission authors
		$authors = array_filter($contributors, array($this, '_filterAuthors'));
		if (is_array($authors) && !empty($authors)) {
			// get and remove first author from the array
			// so the array can be used later in the field 700 1 _
			$firstAuthor = array_shift($authors);
		}
		if (!$firstAuthor) {
			throw new ErrorException("DNBXmlFilter Error: ", FIRST_AUTHOR_NOT_REGISTERED);
		}

		// extract submission translators
		$translators = array_filter($contributors, array($this, '_filterTranslators'));
		
		// is open access
		$openAccess = false;
		if ($journal->getSetting('publishingMode') == PUBLISHING_MODE_OPEN) {
			$openAccess = true;
		} else if ($journal->getSetting('publishingMode') == PUBLISHING_MODE_SUBSCRIPTION) {
			if ($issue->getAccessStatus() == 0 || $issue->getAccessStatus() == ISSUE_ACCESS_OPEN) {
				$openAccess = true;
			} else if ($issue->getAccessStatus() == ISSUE_ACCESS_SUBSCRIPTION) {
				if ($submission->getAccessStatus() == ARTICLE_ACCESS_OPEN) {
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

		// control fields: 001, 007 and 008
		$recordNode->appendChild($node = $doc->createElementNS($deployment->getNamespace(), 'controlfield', $galley->getId()));
		$node->setAttribute('tag', '001');
		$recordNode->appendChild($node = $doc->createElementNS($deployment->getNamespace(), 'controlfield', ' cr |||||||||||'));
		$node->setAttribute('tag', '007');
		$recordNode->appendChild($node = $doc->createElementNS($deployment->getNamespace(), 'controlfield', $yearYY.$month.$day.'s'.$yearYYYY.'||||xx#|||| ||||| ||||| '.$language.'||'));
		$node->setAttribute('tag', '008');

		$urn = $galley->getStoredPubId('other::urnDNB');
		if (empty($urn)) $urn = $galley->getStoredPubId('other::urn');
		if (!empty($urn)) {
			$urnDatafield024 = $this->createDatafieldNode($doc, $recordNode, '024', '7', ' ');
			$this->createSubfieldNode($doc, $urnDatafield024, 'a', $urn);
			$this->createSubfieldNode($doc, $urnDatafield024, '2', 'urn');
		}
		// DOI
		// according the the latest arrangement with DNB both, article and galley DOIs will be submited to the DNB  
		$doi = $galley->getStoredPubId('doi');
		if (!empty($doi)) {
			$doiDatafield024 = $this->createDatafieldNode($doc, $recordNode, '024', '7', ' ');
			$this->createSubfieldNode($doc, $doiDatafield024, 'a', $doi);
			$this->createSubfieldNode($doc, $doiDatafield024, '2', 'doi');
		}
		$submissionDoi = $submission->getStoredPubId('doi');
		if (!empty($submissionDoi)) {
		    $doiDatafield024 = $this->createDatafieldNode($doc, $recordNode, '024', '7', ' ');
		    $this->createSubfieldNode($doc, $doiDatafield024, 'a', $submissionDoi);
		    $this->createSubfieldNode($doc, $doiDatafield024, '2', 'doi');
		}
		// plugin version
		$datafield040 = $this->createDatafieldNode($doc, $recordNode, '040', ' ', ' ');
		$versionDao = DAORegistry::getDAO('VersionDAO'); /* @var $versionDao VersionDAO */
		$version = $versionDao->getCurrentVersion('plugins.importexport', $plugin->getPluginSettingsPrefix(), true);
		$this->createSubfieldNode($doc, $datafield040, 'a', "OJS DNB-Export-Plugin Version ".$version->getVersionString());
		// language
		$datafield041 = $this->createDatafieldNode($doc, $recordNode, '041', ' ', ' ');
		$this->createSubfieldNode($doc, $datafield041, 'a', $language);
		// access to the archived article
		$datafield093 = $this->createDatafieldNode($doc, $recordNode, '093', ' ', ' ');
		if ($openAccess) {
			$this->createSubfieldNode($doc, $datafield093, 'b', 'b');
		} else {
			$this->createSubfieldNode($doc, $datafield093, 'b', $archiveAccess);
		}
		// first author
		$datafield100 = $this->createDatafieldNode($doc, $recordNode, '100', '1', ' ');
		$this->createSubfieldNode($doc, $datafield100, 'a', $firstAuthor->getFullName(false,true));
		$this->createSubfieldNode($doc, $datafield100, '4', 'aut');
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
		// date published
		$datafield264 = $this->createDatafieldNode($doc, $recordNode, '264', ' ', '1');
		$this->createSubfieldNode($doc, $datafield264, 'c', $yearYYYY);
		// article level URN (only if galley level URN does not exist)
		if (empty($urn)) {
			$submissionURN = $submission->getStoredPubId('other::urnDNB');
			if (empty($submissionURN)) $submissionURN = $submission->getStoredPubId('other::urn');
			if (!empty($submissionURN)) {
				$urnDatafield500 = $this->createDatafieldNode($doc, $recordNode, '500', ' ', ' ');
				if (!empty($submissionURN)) $this->createSubfieldNode($doc, $urnDatafield500, 'a', 'URN: ' . $submissionURN);
			}
		}
		// abstract
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
		// license URL
		$licenseURL = $submission->getLicenseURL();
		if (empty($licenseURL)) {
			// copyright notice
			$copyrightNotice = $journal->getSetting('copyrightNotice', $galley->getLocale());
			if (empty($copyrightNotice)) $copyrightNotice = $journal->getSetting('copyrightNotice', $journal->getPrimaryLocale());
			if (!empty($copyrightNotice)) {
				// link to the article view page where the copyright notice can be found
			    $licenseURL = $request->url($journal->getPath(), 'article', 'view', array($article->getId()));
			}
		}
		if (!empty($licenseURL)) {
			$datafield540 = $this->createDatafieldNode($doc, $recordNode, '540', ' ', ' ');
			$this->createSubfieldNode($doc, $datafield540, 'u', $licenseURL);
		}
		// keywords
		$submissionKeywordDao = DAORegistry::getDAO('SubmissionKeywordDAO'); /* @var $submissionKeywordDao SubmissionKeywordDAO */
		$controlledVocabulary = $submissionKeywordDao->getKeywords($submission->getCurrentPublication()->getId(), array($galley->getLocale()));
		if (!empty($controlledVocabulary[$galley->getLocale()])) {
			$datafield653 = $this->createDatafieldNode($doc, $recordNode, '653', ' ', ' ');
			foreach ($controlledVocabulary[$galley->getLocale()] as $controlledVocabularyItem) {
				$this->createSubfieldNode($doc, $datafield653, 'a', $controlledVocabularyItem);
			}
		}
		// other authors
		foreach ((array) $authors as $author) {
			$datafield700 = $this->createDatafieldNode($doc, $recordNode, '700', '1', ' ');
			$this->createSubfieldNode($doc, $datafield700, 'a', $author->getFullName(false,true));
			$this->createSubfieldNode($doc, $datafield700, '4', 'aut');
		}
		// translators
		foreach ((array) $translators as $translator) {
		    $datafield700 = $this->createDatafieldNode($doc, $recordNode, '700', '1', ' ');
		    $this->createSubfieldNode($doc, $datafield700, 'a', $translator->getFullName(false,true));
		    $this->createSubfieldNode($doc, $datafield700, '4', 'trl');
		}
		
		// issue data
		// at least the year has to be provided
		$volume = $issue->getVolume();
		$number = $issue->getNumber();
		$issueDatafield773 = $this->createDatafieldNode($doc, $recordNode, '773', '1', ' ');
		if (!empty($volume)) $this->createSubfieldNode($doc, $issueDatafield773, 'g', 'volume:'.$volume);
		if (!empty($number)) $this->createSubfieldNode($doc, $issueDatafield773, 'g', 'number:'.$number);
		$this->createSubfieldNode($doc, $issueDatafield773, 'g', 'day:'.$day);
		$this->createSubfieldNode($doc, $issueDatafield773, 'g', 'month:'.$month);
		$this->createSubfieldNode($doc, $issueDatafield773, 'g', 'year:'.$yearYYYY);
		$this->createSubfieldNode($doc, $issueDatafield773, '7', 'nnas');
		// journal data
		// there has to be an ISSN
		$issn = $journal->getSetting('onlineIssn');
		if (empty($issn)) $issn = $journal->getSetting('printIssn');
		assert(!empty($issn));
		$journalDatafield773 = $this->createDatafieldNode($doc, $recordNode, '773', '1', '8');
		$this->createSubfieldNode($doc, $journalDatafield773, 'x', $issn);
		// file data
		$galleyURL = $request->url($journal->getPath(), 'article', 'view', array($submissionId, $galley->getId()));
		$datafield856 = $this->createDatafieldNode($doc, $recordNode, '856', '4', ' ');
		$this->createSubfieldNode($doc, $datafield856, 'u', $galleyURL);
		$this->createSubfieldNode($doc, $datafield856, 'q', $this->_getGalleyFileType($galley));
		if (isset($galleyFile)) {
		    # galley is a local file
		    $fileSize = $galleyFile->getFileSize();
		} else {
		    # galley is a remote URL and we stored the filesize before
		    $fileSize = $galley->getData('fileSize');
		}
		if ($fileSize > 0) $this->createSubfieldNode($doc, $datafield856, 's', $this->_getFileSize($fileSize));
		if ($openAccess) $this->createSubfieldNode($doc, $datafield856, 'z', 'Open Access');

		return $doc;
	}

	/**
	 * Check if the contributor is an author resistered with the journal.
	 * @param $contributor Author
	 * @return boolean
	 */
	function _filterAuthors($contributor) {
	    $userGroup = $contributor->getUserGroup();
	    return $userGroup->getData('nameLocaleKey') == 'default.groups.name.author';
	}

	/**
	 * Check if the contributor is a translator resistered with the journal.
	 * @param $contributor Author
	 * @return boolean
	 */
	function _filterTranslators($contributor) {
	    $userGroup = $contributor->getUserGroup();
	    return $userGroup->getData('nameLocaleKey') == 'default.groups.name.translator';
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
    	    throw new ErrorException("Character code ".ord($matches[0][0][0])." found at position ".$matches[0][0][1]." in MARC21 datafield node ".$datafieldNode->getAttribute('tag')." code ".$code, XML_NON_VALID_CHARCTERS);
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
		} elseif ($galley->getFileType() == 'application/epub+zip') {
			return 'epub';
		}
		assert(false);
	}

	/**
	 * Get human friendly file size.
	 * @param $fileSize integer
	 * @return string
	 */
	function _getFileSize($fileSize) {
		$fileSize = round(((int)$fileSize) / 1024);
		if ($fileSize >= 1024) {
			$fileSize = round($fileSize / 1024, 2);
			$fileSize = $fileSize . ' MB';
		} elseif ($fileSize >= 1) {
			$fileSize = $fileSize . ' kB';
		}
		return $fileSize;
	}

}

?>

<?php

/**
 * @file plugins/importexport/dnb/classes/DNBExportDom.inc.php
 *
 * Copyright (c) 2017 Center for Digital Systems (CeDiS), Freie Universität Berlin
 * Distributed under the GNU GPL v2. For full terms see the plugin file LICENSE.
 * Author: Bozana Bokan
 * Last update: March 15, 2017
 *
 * @class DNBExportDom
 * @ingroup plugins_importexport_DNB_classes
 *
 * @brief DNB plugin DOM functions for export
 */

import('lib.pkp.classes.xml.XMLCustomWriter');

// XML attributes
define('DNB_XMLNS' , 'http://www.loc.gov/MARC21/slim');
define('DNB_XMLNS_XSI' , 'http://www.w3.org/2001/XMLSchema-instance');
define('DNB_XSI_SCHEMALOCATION' , 'http://www.loc.gov/standards/marcxml/schema/MARC21slim.xsd');


class DNBExportDom {
	/** @var array */
	var $_errors = array();

	/**
	 * Retrieve export error details.
	 * @return array
	 */
	function getErrors() {
		return $this->_errors;
	}

	/**
	 * Add an error to the errors list.
	 * @param $errorTranslationKey string An i18n key.
	 * @param $param string An additional translation parameter.
	 */
	function _addError($errorTranslationKey, $param = null) {
		$this->_errors[] = array($errorTranslationKey, $param);
	}

	/**
	 * Generate the metadata XML.
	 * @param $request Request
	 * @param $galley ArticleGalley
	 * @param $article PublishedArticle
	 * @param $issue Issue
	 * @param $journal Journal
	 * @param $archiveAccess string (optional) The access option from the plugin settings
	 * @return DOMElement
	 */
	function generateDom($request, $galley, $article, $issue, $journal, $archiveAccess = null) {
		$doc = XMLCustomWriter::createDocument();

		// collection (root) node
		$collectionNode = XMLCustomWriter::createElement($doc, 'collection');
		$collectionNode->setAttribute('xmlns', DNB_XMLNS);
		$collectionNode->setAttribute('xmlns:xsi', DNB_XMLNS_XSI);
		$collectionNode->setAttribute('xsi:schemaLocation', DNB_XMLNS . ' ' . DNB_XSI_SCHEMALOCATION);
		XMLCustomWriter::appendChild($doc, $collectionNode);

		// record node
		$recordNode = XMLCustomWriter::createElement($doc, 'record');
		XMLCustomWriter::appendChild($collectionNode, $recordNode);

		// Data we will need later
		$language = AppLocale::get3LetterIsoFromLocale($galley->getLocale());
		$datePublished = $article->getDatePublished();
		if (!$datePublished) $datePublished = $issue->getDatePublished();
		assert(!empty($datePublished));
		$yearYYYY = date('Y', strtotime($datePublished));
		$yearYY = date('y', strtotime($datePublished));
		$month = date('m', strtotime($datePublished));
		$day = date('d', strtotime($datePublished));
		$authors = $article->getAuthors();
		if (is_array($authors) && !empty($authors)) {
			// get and remove first author from the array
			// so the array can be used later in the field 700 1 _
			$firstAuthor = array_shift($authors);
		}
		assert($firstAuthor);

		// is open access
		$openAccess = false;
		if ($journal->getSetting('publishingMode') == PUBLISHING_MODE_OPEN) {
			$openAccess = true;
		} else if ($journal->getSetting('publishingMode') == PUBLISHING_MODE_SUBSCRIPTION) {
			if ($issue->getAccessStatus() == 0 || $issue->getAccessStatus() == ISSUE_ACCESS_OPEN) {
				$openAccess = true;
			} else if ($issue->getAccessStatus() == ISSUE_ACCESS_SUBSCRIPTION) {
				if ($article->getAccessStatus() == ARTICLE_ACCESS_OPEN) {
					$openAccess = true;
				}
			}
		}
		assert($openAccess || $archiveAccess);

		// leader
		XMLCustomWriter::createChildWithText($doc, $recordNode, 'leader', '00000naa a2200000 u 4500', false);

		// control fields: 001, 007 and 008
		$controlfield001Node = XMLCustomWriter::createChildWithText($doc, $recordNode, 'controlfield', $galley->getId());
		XMLCustomWriter::setAttribute($controlfield001Node, 'tag', '001');
		$controlfield007Node = XMLCustomWriter::createChildWithText($doc, $recordNode, 'controlfield', ' cr |||||||||||');
		XMLCustomWriter::setAttribute($controlfield007Node, 'tag', '007');
		$controlfield008Node = XMLCustomWriter::createChildWithText($doc, $recordNode, 'controlfield', $yearYY.$month.$day.'s'.$yearYYYY.'||||xx#|||| ||||| ||||| '.$language.'||');
		XMLCustomWriter::setAttribute($controlfield008Node, 'tag', '008');

		// data fields:
		// URN
		$urn = $galley->getPubId('other::urnDNB');
		if (empty($urn)) $urn = $galley->getPubId('other::urn');
		if (!empty($urn)) {
			$urnDatafield024 = $this->createDatafieldNode($doc, $recordNode, '024', '7', ' ');
			$this->createSubfieldNode($doc, $urnDatafield024, 'a', $urn);
			$this->createSubfieldNode($doc, $urnDatafield024, '2', 'urn');
		}
		// DOI
		$doi = $galley->getPubId('doi');
		if (!empty($doi)) {
			$doiDatafield024 = $this->createDatafieldNode($doc, $recordNode, '024', '7', ' ');
			$this->createSubfieldNode($doc, $doiDatafield024, 'a', $doi);
			$this->createSubfieldNode($doc, $doiDatafield024, '2', 'doi');
		}
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
		$this->createSubfieldNode($doc, $datafield100, 'a', $firstAuthor->getFullName(true));
		$this->createSubfieldNode($doc, $datafield100, '4', 'aut');
		// title
		$title = $article->getTitle($galley->getLocale());
		if (empty($title)) $title = $article->getTitle($article->getLocale());
		assert(!empty($title));
		$datafield245 = $this->createDatafieldNode($doc, $recordNode, '245', '0', '0');
		$this->createSubfieldNode($doc, $datafield245, 'a', $title);
		// date published
		$datafield264 = $this->createDatafieldNode($doc, $recordNode, '264', ' ', ' ');
		$this->createSubfieldNode($doc, $datafield264, 'c', $yearYYYY);
		// article level URN and DOI (only if galley level URN and DOI do not exist)
		if (empty($urn) && empty($doi)) {
			$articleURN = $article->getPubId('other::urnDNB');
			if (empty($articleURN)) $articleURN = $article->getPubId('other::urn');
			$articleDoi = $article->getPubId('doi');
			if (!empty($articleURN) || !empty($articleDoi)) {
				$doiDatafield500 = $this->createDatafieldNode($doc, $recordNode, '500', ' ', ' ');
				if (!empty($articleURN)) $this->createSubfieldNode($doc, $doiDatafield500, 'a', 'URN: ' . $articleURN);
				if (!empty($articleDoi)) $this->createSubfieldNode($doc, $doiDatafield500, 'a', 'DOI: ' . $articleDoi);
			}
		}
		// abstract
		$abstract = $article->getAbstract($galley->getLocale());
		if (empty($abstract)) $abstract = $article->getAbstract($article->getLocale());
		if (!empty($abstract)) {
			$abstract = String::html2text($abstract);
			if (strlen($abstract) > 999)  {
				$abstract = substr($abstract, 0, 996);
				$abstract .= '...';
			}
			$abstractURL = $request->url(null, 'article', 'view', array($article->getId()));
			$datafield520 = $this->createDatafieldNode($doc, $recordNode, '520', '3', ' ');
			$this->createSubfieldNode($doc, $datafield520, 'a', $abstract);
			$this->createSubfieldNode($doc, $datafield520, 'u', $abstractURL);
		}
		// license URL
		$licenseURL = $article->getLicenseURL();
		if (empty($licenseURL)) {
			$licenseURL = $journal->getSetting('licenseURL');
		}
		if (empty($licenseURL)) {
			// copyright notice
			$copyrightNotice = $journal->getSetting('copyrightNotice', $galley->getLocale());
			if (empty($copyrightNotice)) $copyrightNotice = $journal->getSetting('copyrightNotice', $journal->getPrimaryLocale());
			if (!empty($copyrightNotice)) {
				// link to the journal about page where the copyright notice can be found
				$licenseURL = $request->url(null, 'about');
			}
		}
		if (!empty($licenseURL)) {
			$datafield540 = $this->createDatafieldNode($doc, $recordNode, '540', ' ', ' ');
			$this->createSubfieldNode($doc, $datafield540, 'u', $licenseURL);
		}
		// keywords
		$subjects = array();
		$keywords = $article->getSubject($galley->getLocale());
		if (!empty($keywords)) $subjects = array_map('trim', explode(';', $keywords));
		if (!empty($subjects)) {
			$datafield653 = $this->createDatafieldNode($doc, $recordNode, '653', ' ', ' ');
			foreach ($subjects as $keyword) {
				$this->createSubfieldNode($doc, $datafield653, 'a', $keyword);
			}
		}
		// other authors
		foreach ((array) $authors as $author) {
			$datafield700 = $this->createDatafieldNode($doc, $recordNode, '700', '1', ' ');
			$this->createSubfieldNode($doc, $datafield700, 'a', $author->getFullName(true));
			$this->createSubfieldNode($doc, $datafield700, '4', 'aut');
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
		// there have to be an ISSN
		$issn = $journal->getSetting('onlineIssn');
		if (empty($issn)) $issn = $journal->getSetting('printIssn');
		assert(!empty($issn));
		$journalDatafield773 = $this->createDatafieldNode($doc, $recordNode, '773', '1', '8');
		$this->createSubfieldNode($doc, $journalDatafield773, 'x', $issn);
		// file data
		$galleyURL = $request->url(null, 'article', 'view', array($article->getId(), $galley->getId()));
		$datafield856 = $this->createDatafieldNode($doc, $recordNode, '856', '4', ' ');
		$this->createSubfieldNode($doc, $datafield856, 'u', $galleyURL);
		$this->createSubfieldNode($doc, $datafield856, 'q', $this->_getGalleyFileType($galley));
		$fileSize = $galley->getFileSize();
		if ($fileSize > 0) $this->createSubfieldNode($doc, $datafield856, 's', $this->_getFileSize($fileSize));
		if ($openAccess) $this->createSubfieldNode($doc, $datafield856, 'z', 'Open Access');
		return $doc;
	}

	/**
	 * Generate the datafield node.
	 * @param $doc DOMElement
	 * @param $collectionNode DOMElement
	 * @param $tag string 'tag' attribute
	 * @param $ind1 string 'ind1' attribute
	 * @param $ind2 string 'ind2' attribute
	 * @return DOMElement
	 */
	function createDatafieldNode($doc, $collectionNode, $tag, $ind1, $ind2) {
		$datafieldNode = XMLCustomWriter::createElement($doc, 'datafield');
		XMLCustomWriter::setAttribute($datafieldNode, 'tag', $tag);
		XMLCustomWriter::setAttribute($datafieldNode, 'ind1', $ind1);
		XMLCustomWriter::setAttribute($datafieldNode, 'ind2', $ind2);
		XMLCustomWriter::appendChild($collectionNode, $datafieldNode);
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
		$subfieldNode = XMLCustomWriter::createChildWithText($doc, $datafieldNode, 'subfield', $value, false);
		XMLCustomWriter::setAttribute($subfieldNode, 'code', $code);
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

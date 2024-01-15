<?php

/**
 * @file plugins/importexport/dnb/classes/DNBCatalogInfoProvider.php
 *
 * Copyright (c) 2021 Center for Digital Systems (CeDiS), Universitätsbibliothek Freie Universität Berlin
 * Distributed under the GNU GPL v3. For full terms see the plugin file LICENSE.
 * Author: Bozana Bokan, Ronald Steffen
 *
 * @class DNBCatalogInfoProvider
 * @ingroup plugins_importexport_dnb
 *
 * @brief Get information on the current journal from the DNB catalog.
 */

namespace APP\plugins\importexport\dnb\classes;

use APP\core\Services;
use DOMDocument;
use DOMXPath;

class DNBCatalogInfoProvider {

	private $pluginFilesDir;

	function getCatalogInfo($context, $pluginFilesDir) {
		$this->pluginFilesDir = $pluginFilesDir;

		$dnbCatalogInfo = $this->fetchDNBCatalogData($context);

		// post process results
		// create table heading
		$tmp = array_filter(array_column($dnbCatalogInfo, 'otherColName'));
		$otherColName = array_shift($tmp);
		$tmp = array_filter(array_column($dnbCatalogInfo, 'previousColName'));
		$previousColName = array_shift($tmp);
		$tmp = array_filter(array_column($dnbCatalogInfo, 'followingColName'));
		$followingColName = array_shift($tmp);
		$keys = array_keys($dnbCatalogInfo[0]);
		array_unshift($dnbCatalogInfo, array_combine($keys, array_fill(0, count($keys), "")));
		$otherColName?$dnbCatalogInfo[0]['otherColName'] = $otherColName:NULL;
		$previousColName?$dnbCatalogInfo[0]['previousColName'] = $previousColName:NULL;
		$followingColName?$dnbCatalogInfo[0]['followingColName'] = $followingColName:$dnbCatalogInfo[0]['followingColName'] = NULL;
		$heading = $dnbCatalogInfo[0];
		
		// rearrange columns
		$dnbCatalogInfo = array_map(
			function ($i) use ($heading) {
				// link ISSN
				$i['ISSN'] = '<a href="https://d-nb.info/'.$i['dnb_id'].'" target="_blank">'.$i['ISSN'].'</a>';
				unset($i['dnb_id']);
				//set column headers
				if ($i['otherColName']) {
					$i[$i['otherColName']] = $i['other'];
				} else {
					$i[$heading['otherColName']] = "";
				}
				unset($i['other']);
				unset($i['otherColName']);
				if ($i['previousColName']) {
					$i[$i['previousColName']] = $i['previous'];
				} else {
					$i[$heading['previousColName']] = "";
				}
				unset($i['previous']);
				unset($i['previousColName']);
				if ($i['followingColName']) {
					$i[$i['followingColName']] = $i['following'];
				} else {
					$i[$heading['followingColName']] = "";
				}
				unset($i['following']);
				unset($i['followingColName']);
				return $i;
			},
			$dnbCatalogInfo
		);

		return $dnbCatalogInfo;
	}

    function fetchDNBCatalogData($context, $mode = 'issn', $url = '', $dnbCatalogInfo = []) {

		// prepare query
		switch ($mode) {
			case 'issn':
				if (empty($dnbCatalogInfo)) {
					$dnbCatalogInfo[]['ISSN'] = $context->getData('onlineIssn');
				}
				$dnbCatalogQueryUrl = 'https://services.dnb.de/sru/dnb?version=1.1&operation=searchRetrieve&query=num%3D'.$dnbCatalogInfo[count($dnbCatalogInfo)-1]['ISSN'];
				break;
			case 'dnb_id':
				$dnbCatalogQueryUrl = 'https://services.dnb.de/sru/dnb?version=1.1&operation=searchRetrieve&query=partOfat%3D'.$dnbCatalogInfo[count($dnbCatalogInfo)-1]['dnb_id'];
				break;
			default:
				$dnbCatalogQueryUrl = $url;
				break;
		}

		// execute query
		$xpathFilter = $this->fetchDNBRawData($dnbCatalogQueryUrl, $mode, $dnbCatalogInfo[count($dnbCatalogInfo)-1]['ISSN'].'-'.$mode);
		
		// process response
		switch ($mode) {
			case 'issn':
				$dnbNumberOfRecordsNode = $xpathFilter->query('//xmlns:numberOfRecords');
				if ((int)$dnbNumberOfRecordsNode[0]->textContent > 0) {
					$dnbJournalIDNode = $xpathFilter->query('//*[text()[contains(., "DE-101")]]');
					$dnbCatalogInfo[count($dnbCatalogInfo)-1]['dnb_id'] = substr($dnbJournalIDNode[0]->textContent, strpos($dnbJournalIDNode[0]->textContent, ")") + 1);
					$dnbCatalogInfo = $this->fetchDNBCatalogData($context, 'dnb_id', '', $dnbCatalogInfo);
				} else {
					$dnbCatalogInfo[count($dnbCatalogInfo)-1]['dnb_id'] = '';
					$dnbCatalogInfo[count($dnbCatalogInfo)-1]['Anzahl Artikel'] = __('plugins.importexport.dnb.settings.form.dnbCatalog.catalogInfo.issnNotFound');
				}
				break;
			case 'dnb_id':
				$recordsNode = $xpathFilter->query('/*');

				$dnbCatalogInfo[count($dnbCatalogInfo)-1]['Anzahl Artikel'] = 0;
				// get detail in case articles were found
				if ($recordsNode->length > 0) {
					$isPrimaryTopicOf = [];
					$exportData = [];
					foreach ($xpathFilter->query('//foaf:isPrimaryTopicOf') as $i)  {
						$isPrimaryTopicOf[] = parse_url($i->textContent);
						$exportData[] = $i->textContent;
					}

					// Write the urls to a csv file.
					$fileName =  $this->pluginFilesDir . '/isPrimaryTopicOf.csv';
					$fh = fopen($fileName, 'wt');
					//Add BOM (byte order mark) to fix UTF-8 in Excel
					fprintf($fh, chr(0xEF) . chr(0xBB) . chr(0xBF));
					foreach ($exportData as $row) {
						fputcsv($fh, [$row]);
					}
					fclose($fh);

					// summarize unique urls for better display
					$uniqueUrls = array_unique(array_column($isPrimaryTopicOf,'host'));
					foreach ($uniqueUrls as $i) {
						$dnbCatalogInfo[count($dnbCatalogInfo)-1]['Anzahl Artikel'] = 
							count(array_filter($isPrimaryTopicOf, function ($e) use ($i) {
								return $e['host'] == $i;
							})).' => '.$i;
					}
				}

				$dnbCatalogQueryUrl = "https://d-nb.info/".$dnbCatalogInfo[count($dnbCatalogInfo)-1]['dnb_id']."/about/marcxml";
				$dnbCatalogInfo = $this->fetchDNBCatalogData($context, 'marcxml', $dnbCatalogQueryUrl, $dnbCatalogInfo);
				break;
			case 'marcxml':
				// strategy: 
				// 1) find linking ISSN from OJS ISSN
				// 2) if there is a linking ISSN query Marcxml 776, 780 and 785 for other ISSNs
				$tag022 = $xpathFilter->query('//*[@tag="022"]/*[@code="l"]'); //ISSN-L = (ISSN-Linking) ist die übergeordnete Identifikationsnummer, die für alle Erscheinungsformen des gleichen Titels gilt
				$tag776 = $xpathFilter->query('//*[@tag="776"]/*[@code!="w" and @code!="i" and @code!="x"]');
				$tag776ISSNs = $xpathFilter->query('//*[@tag="776"]/*[@code="x"]');
				$tag776Name = $xpathFilter->query('//*[@tag="776"]/*[@code="i"]')['firstElemetChild'];
				$tag780 = $xpathFilter->query('//*[@tag="780"]/*[@code!="w" and @code!="i" and @code!="x"]');
				$tag780ISSNs = $xpathFilter->query('//*[@tag="780"]/*[@code="x"]');
				$tag780Name = $xpathFilter->query('//*[@tag="780"]/*[@code="i"]')['firstElemetChild'];
				$tag785 = $xpathFilter->query('//*[@tag="785"]/*[@code!="w" and @code!="i" and @code!="x"]');
				$tag785ISSNs = $xpathFilter->query('//*[@tag="785"]/*[@code="x"]');
				$tag785Name = $xpathFilter->query('//*[@tag="785"]/*[@code="i"]')['firstElemetChild'];

				$issns = [];
				foreach ($xpathFilter->query('//*[@tag="776" or @tag="780" or @tag="785"]/*[@code="x"]') as $issn) {
					$issns[] = $issn->textContent;
				};
				$queried_issns = array_column($dnbCatalogInfo, 'ISSN');

				$issn_l = [];
				foreach ($tag022 as $tag) {
					$issn_l[]	= $tag->textContent;
				}

				$unqueried_issns = array_diff(array_unique(array_merge($issns, $issn_l)), $queried_issns);

				$other_issn = '';
				foreach ($tag776 as $tag) {
					$other_issn = $other_issn.$tag->textContent.'<br>';
				}
				foreach ($tag776ISSNs as $tag) {
					$other_issn = $other_issn.'ISSN: <strong>'.$tag->textContent.'</strong><br>';
				}

				$previous_issn = '';
				foreach ($tag780 as $tag) {
					$previous_issn = $previous_issn.$tag->textContent.'<br>';
				}
				foreach ($tag780ISSNs as $tag) {
					$previous_issn = $previous_issn.'ISSN: <strong>'.$tag->textContent.'</strong><br>';
				}

				$following_issn = '';
				foreach ($tag785 as $tag) {
					$following_issn = $following_issn.$tag->textContent.'<br>';
				}
				foreach ($tag785ISSNs as $tag) {
					$following_issn = $following_issn.'ISSN: <strong>'.$tag->textContent.'</strong><br>';
				}

				$dnbCatalogInfo[count($dnbCatalogInfo)-1]['ISSN-L'] = NULL;
				if ($issn_l) {
					$dnbCatalogInfo[count($dnbCatalogInfo)-1]['ISSN-L'] = $issn_l[0];
				}

				$dnbCatalogInfo[count($dnbCatalogInfo)-1]['other'] = NULL;
				$dnbCatalogInfo[count($dnbCatalogInfo)-1]['otherColName'] = NULL;
				if ($tag776Name) {
					$dnbCatalogInfo[count($dnbCatalogInfo)-1]['other'] = $other_issn;
					$dnbCatalogInfo[count($dnbCatalogInfo)-1]['otherColName'] = $tag776Name->textContent;
				}

				$dnbCatalogInfo[count($dnbCatalogInfo)-1]['previous'] = NULL;
				$dnbCatalogInfo[count($dnbCatalogInfo)-1]['previousColName'] = NULL;
				if ($tag780Name) {
					$dnbCatalogInfo[count($dnbCatalogInfo)-1]['previous'] = $previous_issn;
					$dnbCatalogInfo[count($dnbCatalogInfo)-1]['previousColName'] = $tag780Name->textContent;
				}

				$dnbCatalogInfo[count($dnbCatalogInfo)-1]['following'] = NULL;
				$dnbCatalogInfo[count($dnbCatalogInfo)-1]['followingColName'] = NULL;
				if ($tag785Name) {
					$dnbCatalogInfo[count($dnbCatalogInfo)-1]['following'] = $following_issn;
					$dnbCatalogInfo[count($dnbCatalogInfo)-1]['followingColName'] = $tag785Name->textContent;
				}

				// if we found a linking issn, fetch all data from there
				foreach ($unqueried_issns as $issn) {
					$dnbCatalogInfo[]['ISSN'] = $issn;
					$dnbCatalogInfo = $this->fetchDNBCatalogData($context, 'issn', '', $dnbCatalogInfo);
				}
				break;
			default:
		}
		
		return $dnbCatalogInfo;
	}

	private function fetchDNBRawData($baseUrl, $mode = '', $debugFileId = '') {

		$exit = false;
		do {
			// Queries for article details are paged. We have to loop until we got all the data.

			// prepare query
			switch ($mode) {
				case 'dnb_id':
					$maxNumberOfRecords = 300;
					if (!isset($startRecord)) $startRecord = 0;
					$url = $baseUrl.'&startRecord='.$startRecord.'&maximumRecords='.$maxNumberOfRecords;
					break;
				default:
					$url = $baseUrl;
			}
			
			$response = file_get_contents($url);

			// write response to file for debugging purposes
			if (true) {
				file_put_contents(
					'/var/www/html/files/dnb/response_'.$debugFileId.'.xml',$response
				);
			}

			$xmlResponse = new DOMDocument();
			$res = $xmlResponse->loadXML($response, LIBXML_PARSEHUGE);
			$xpathFilter = $this->getDOMXPath($xmlResponse);

			// post process response to detect pagination
			switch ($mode) {
				case 'dnb_id':
					if (!isset($buffer)) {
						$buffer = new DOMDocument();
					}
					$buffer = $this->mergeRDFNodes($buffer, $xmlResponse);
					if ($nextPosition = $xpathFilter->query('//xmlns:nextRecordPosition')->item(0)) {
						$startRecord = $nextPosition->textContent;
					} else {
						$exit = true;
						$xpathFilter = $this->getDOMXPath($buffer);
					}
					break;
				default:
					$exit = true;
			}

		} while (!$exit);

		return $xpathFilter;
	}

	private function mergeRDFNodes(DOMDocument $domA, DOMDocument $domB) {
		$xpath = $this->getDOMXPath($domB);
		
		foreach ($xpath->query('//rdf:RDF') as $node) {
			$domA->append($domA->importNode($node, true));
		}
		return $domA;
	}

	private function getDOMXPath(DOMDocument $domDoc) {
		$domXPath = new DOMXPath($domDoc);

		// set default namespace
		$domXPath->registerNamespace("xmlns", "http://www.loc.gov/zing/srw/");

		// all namespaces subsequently used in the XPath expression must be registered here
		$domXPath->registerNamespace("rdf", "http://www.w3.org/1999/02/22-rdf-syntax-ns#");
		$domXPath->registerNamespace("dc", "http://purl.org/dc/elements/1.1/");
		$domXPath->registerNamespace("foaf", "http://xmlns.com/foaf/0.1/");

		return $domXPath;
	}

}

?>
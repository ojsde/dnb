<?php

/**
 * @file plugins/generic/dnb/classes/filter/GalleyFilter.php
 *
 * Copyright (c) 2021 Center for Digital Systems (CeDiS), Freie Universität Berlin
 * Distributed under the GNU GPL v3. For full terms see the plugin file LICENSE.
 *
 * @class GalleyFilter
 * @brief Filter for galley files
 */

namespace APP\plugins\generic\dnb\classes\filter;

use PKP\db\DAORegistry;

class GalleyFilter {
	
	/**
	 * Filter for PDF and EPUB full text galleys
	 */
	public function filterPDFAndEPUB($galley): bool {
		$genreDao = DAORegistry::getDAO('GenreDAO');
		$galleyFile = $galley->getFile();

		// Remote galleys
		if (!isset($galleyFile)) {
			return $this->isRemoteGalleyPDFOrEPUB($galley);
		}
		
		$genre = $genreDao->getById($galleyFile->getGenreId());
		
		// Must be document genre (full text)
		if (!$genre || $genre->getCategory() != 1) {
			return false;
		}
		
		// Check if PDF or EPUB
		$label = strtolower($galley->getLabel());
		return (strpos($label, 'pdf') !== false || strpos($label, 'epub') !== false);
	}
	
	/**
	 * Filter for supplementary galleys
	 */
	public function filterSupplementary($galley): bool {
		$genreDao = DAORegistry::getDAO('GenreDAO');
		$galleyFile = $galley->getFile();

		// Remote galleys are not currently handled as supplementary
		if (!isset($galleyFile)) {
			return false;
		}
		
		$genre = $genreDao->getById($galleyFile->getGenreId());
		
		// Category 2 = supplementary
		if ($genre && $genre->getCategory() == \PKP\submission\Genre::GENRE_CATEGORY_SUPPLEMENTARY) {
			return true;
		}
		
		return false;
	}
	
	/**
	 * Check if remote galley is PDF or EPUB
	 */
	private function isRemoteGalleyPDFOrEPUB($galley): bool {
		$label = strtolower($galley->getLabel());
		$url = $galley->getData('urlRemote');
		
		// Check label or URL extension
		return (strpos($label, 'pdf') !== false || 
		        strpos($label, 'epub') !== false ||
		        preg_match('/\.(pdf|epub)$/i', $url));
	}
	
	/**
	 * Get file genre category
	 */
	public function getFileGenre($galleyFile): int {
		$genreDao = DAORegistry::getDAO('GenreDAO');
		$genre = $genreDao->getById($galleyFile->getGenreId());
		return $genre ? $genre->getCategory() : 0;
	}
}

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
	 * Determine if a galley represents a full-text PDF or EPUB file.
	 *
	 * @param object $galley Galley object to inspect.
	 * @return bool True if the galley should be treated as PDF/EPUB full text.
	 */
	public function filterPDFAndEPUB($galley): bool {
		$genreDao = DAORegistry::getDAO('GenreDAO');
		$galleyFile = $galley->getFile(); // returns null for Galley::add (empty galley) AND for Galley::edit hook (i.e. a just uploaded galley file)

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
		return in_array($galley->getFile()->getData('mimetype'),['application/pdf','application/epub+zip']);
	}
	
	/**
	 * Determine if a galley should be considered supplementary material.
	 *
	 * @param object $galley Galley object to inspect.
	 * @return bool True if the galley is supplementary.
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
	 * Inspect a remote galley entry (no local file) to guess its type.
	 *
	 * @param object $galley Remote galley object.
	 * @return bool True if its label or URL suggests PDF/EPUB.
	 */
	private function isRemoteGalleyPDFOrEPUB($galley): bool {
		$label = strtolower($galley->getLabel());
		$url = $galley->getData('urlRemote')?$galley->getData('urlRemote'):'';
		
		// Check label or URL extension, apart from downloading the file this is the only information we have
		// downloaded files will be verified at export time
		return (strpos($label, 'pdf') !== false || 
		        strpos($label, 'epub') !== false ||
		        preg_match('/\.(pdf|epub)$/i', $url));
	}
	
	/**
	 * Retrieve the genre category identifier for a galley file.
	 *
	 * @param object $galleyFile File object with genreId property.
	 * @return int Numeric genre category or 0 if unknown.
	 */
	public function getFileGenre($galleyFile): int {
		$genreDao = DAORegistry::getDAO('GenreDAO');
		$genre = $genreDao->getById($galleyFile->getGenreId());
		return $genre ? $genre->getCategory() : 0;
	}
}

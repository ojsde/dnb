<?php

/**
 * @file plugins/generic/dnb/classes/api/DNBHelpApiHandler.php
 *
 * Copyright (c) 2021 Center for Digital Systems (CeDiS), Freie Universität Berlin
 * Distributed under the GNU GPL v3. For full terms see the plugin file LICENSE.
 *
 * @class DNBHelpApiHandler
 * @brief Handler for help documentation API endpoint
 */

namespace APP\plugins\generic\dnb\classes\api;

use Illuminate\Http\JsonResponse;
use PKP\i18n\LocaleConversion;
use PKP\facades\Locale;

class DNBHelpApiHandler {
	
	private string $helpPath;
	
	public function __construct(string $helpPath) {
		$this->helpPath = $helpPath;
	}
	
	/**
	 * Handle help content request
	 */
	public function handle($lang, $topic): JsonResponse {
		try {
			// Sanitize inputs to prevent path traversal
			$lang = preg_replace('/[^a-z]/', '', substr($lang, 0, 2));
			$topic = preg_replace('/[^a-zA-Z0-9\/_-]/', '', $topic ?: 'SUMMARY');
			
			$urlPart = $lang . '/' . $topic;
			$filename = $urlPart . '.md';
			
			$language = LocaleConversion::getIso1FromLocale(Locale::getLocale());
			$summaryFile = $this->helpPath . $language . '/SUMMARY.md';
			
			// Find next/previous links from SUMMARY
			$previousLink = $nextLink = null;
			if (file_exists($summaryFile)) {
				list($previousLink, $nextLink) = $this->findNavigationLinks($summaryFile, $topic);
			}
			
			// Parse markdown
			$fullPath = $this->helpPath . $filename;
			if (!file_exists($fullPath)) {
				return response()->json(['error' => 'Help content not found'], 404);
			}
			
			$content = $this->parseMarkdown(file_get_contents($fullPath), $filename);
			
			return response()->json([
				'content' => $content,
				'previous' => $previousLink,
				'next' => $nextLink,
			], 200);
			
		} catch (\Throwable $e) {
			error_log('DNB Plugin Help Error: ' . $e->getMessage());
			return response()->json(['error' => 'Failed to load help content'], 500);
		}
	}
	
	/**
	 * Parse markdown to HTML with URL filtering
	 */
	private function parseMarkdown(string $markdownContent, string $filename): string {
		$parser = new \Michelf\MarkdownExtra;
		$parser->url_filter_func = function ($url) use ($filename) {
			// Prepend current path to relative URLs (matching old logic)
			return (empty(parse_url($url)['host']) ? dirname($filename) . '/' : '') . $url;
		};
		
		return $parser->transform($markdownContent);
	}
	
	/**
	 * Find previous/next navigation links from SUMMARY.md
	 */
	private function findNavigationLinks(string $summaryFile, string $currentTopic): array {
		$previousLink = $nextLink = null;
		
		if (preg_match_all('/\(([^)]+)\)/sm', file_get_contents($summaryFile), $matches)) {
			$links = $matches[1];
			
			$currentIndex = array_search($currentTopic, $links);
			if ($currentIndex !== false) {
				if ($currentIndex > 0) {
					$previousLink = $links[$currentIndex - 1];
				}
				if ($currentIndex < count($links) - 1) {
					$nextLink = $links[$currentIndex + 1];
				}
			}
		}
		
		return [$previousLink, $nextLink];
	}
}

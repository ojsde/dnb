<?php

/**
 * @file plugins/generic/dnb/classes/api/DNBSubmissionsApiHandler.php
 *
 * Copyright (c) 2021 Center for Digital Systems (CeDiS), Freie Universität Berlin
 * Distributed under the GNU GPL v3. For full terms see the plugin file LICENSE.
 *
 * @class DNBSubmissionsApiHandler
 * @brief Handler for submissions list API endpoint
 */

namespace APP\plugins\generic\dnb\classes\api;

use APP\core\Application;
use APP\facades\Repo;
use APP\submission\Submission;
use Illuminate\Http\JsonResponse;
use PKP\db\DAORegistry;
use PKP\userGroup\UserGroup;
use PKP\facades\Locale;

class DNBSubmissionsApiHandler {
	
	private $plugin;
	
	public function __construct($plugin) {
		$this->plugin = $plugin;
	}
	
	/**
	 * Handle submissions list request
	 */
	public function handle($illuminateRequest, $request): JsonResponse {
		$context = $request->getContext();
		$params = $illuminateRequest->query();
		$args = array_merge($params, $illuminateRequest->input() ?? []);
		
		$statusName = $this->plugin->getDepositStatusSettingName();

		// Get all published submissions
		$issueIds = Repo::issue()
			->getCollector()
			->filterByContextIds([$context->getId()])
			->filterByPublished(true)
			->getIds()
			->toArray();
			
		$collector = Repo::submission()
			->getCollector()
			->filterByContextIds([$context->getId()])
			->filterByIssueIds(isset($args['issueIds']) ? $args['issueIds'] : $issueIds)
			->filterByStatus([Submission::STATUS_PUBLISHED]);
		
		if (isset($args['sectionIds'])) {
			$collector->filterBySectionIds($args['sectionIds']);
		}
		if (isset($args['searchPhrase'])) {
			$collector->filterBySearchPhrase($args['searchPhrase']);
		}
		
		$submissionsIterator = $collector->getMany();
			
		// Get default submission list properties
		$userGroups = UserGroup::withContextIds([$context->getId()])->get();
		$genreDao = DAORegistry::getDAO('GenreDAO'); /** @var \PKP\submission\GenreDAO $genreDao */
		$genres = $genreDao->getByContextId($context->getId())->toArray();
		$userRoles = $request->getUser()->getRoles($context->getId());
		$mappedItems = Repo::submission()->getSchemaMap()
			->mapManyToSubmissionsList($submissionsIterator, $userGroups, $genres, $userRoles)
			->toArray();

		// Add DNB-specific properties
		$items = $this->enrichWithDNBData($submissionsIterator, $mappedItems, $request, $context, $statusName);
		
		// Filter by DNB status
		$items = $this->filterByDNBStatus($items, $args, $statusName);

		// Paginate
		$data = [];
		$data['itemsMax'] = count($items);
		$itemsPerPage = $context->getData('itemsPerPage') ?? 20;
		$data['items'] = array_values(array_slice($items, $args['offset'] ?? 0, $itemsPerPage));
	
		return response()->json($data, 200);
	}
	
	/**
	 * Enrich submissions with DNB-specific data
	 */
	private function enrichWithDNBData($submissionsIterator, $mappedItems, $request, $context, $statusName): array {
		$items = [];
		
		foreach ($submissionsIterator as $submission) {
			$item = $mappedItems[$submission->getId()];
			
			$issue = Repo::issue()->get($submission->getCurrentPublication()->getData('issueId'));
			if (!$issue) continue;
			
			$item['issueTitle'] = $issue->getLocalizedTitle();
			$item['issueUrl'] = $request->getDispatcher()->url(
				$request,
				Application::ROUTE_PAGE,
				$context->getPath(),
				'issue',
				'view',
				[$issue->getId()]
			);

			// Check if can be exported
			$documentGalleys = $supplementaryGalleys = [];
			try {
				$this->plugin->canBeExported($submission, $issue, $documentGalleys, $supplementaryGalleys);
			} catch (\Exception $e) {
				$item['exportError'] = $e->getMessage();
			}

			// Create warning message if issues detected with supplementary files
			$msg = "";
			if ($submission->getData('supplementaryNotAssignable')) {
				$plural = Locale::getLocale() == "de_DE" ? "n" : "s";
				$msg = __('plugins.importexport.dnb.warning.supplementaryNotAssignable',
					array(
						'nDoc' => count($documentGalleys),
						'nSupp' => count($supplementaryGalleys),
						'pSupp' => count($supplementaryGalleys) > 1 ? $plural : ""
					));
			}
			$item['supplementariesNotAssignable'] = $msg;

			$currentPublication = $submission->getCurrentPublication();
			$item['publication']['id'] = $currentPublication->getData('id');
			$item['sectionIds'] = $currentPublication->getData('sectionId');
			
			$authorUserGroups = UserGroup::withContextIds([$context->getId()])
				->withRoleIds([\PKP\security\Role::ROLE_ID_AUTHOR])
				->get();
			$item['publication']['authorsString'] = $currentPublication->getAuthorString($authorUserGroups);
			$item['publication']['fullTitle'] = $currentPublication->getLocalizedFullTitle('html');
			
			$item[$statusName] = $submission->getData($statusName) ?: $this->plugin::EXPORT_STATUS_NOT_DEPOSITED;
			$item['dnbStatus'] = $this->plugin->getStatusNames()[$item[$statusName]];
			$item['dnbStatusConst'] = empty($item[$statusName]) ? $this->plugin::EXPORT_STATUS_NOT_DEPOSITED : $item[$statusName];

			$items[] = $item;
		}
		
		return $items;
	}
	
	/**
	 * Filter items by DNB status
	 */
	private function filterByDNBStatus(array $items, array $args, string $statusName): array {
		return array_filter($items, function ($item) use ($args, $statusName) {
			if (isset($args[$statusName])) {
				$result = false;
				foreach ($args[$statusName] as $status) {
					if ($item[$statusName] == NULL && $status == $this->plugin::EXPORT_STATUS_NOT_DEPOSITED) {
						$result = $result || true;
					}
					$result = $result || $item[$statusName] == $status;
				}
				return $result;
			}
			return true;
		});
	}
}

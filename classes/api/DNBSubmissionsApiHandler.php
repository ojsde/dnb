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
use PKP\context\Context;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request as IlluminateRequest;
use Illuminate\Support\Facades\DB;
use PKP\db\DAORegistry;
use PKP\userGroup\UserGroup;
use PKP\facades\Locale;
use PKP\core\PKPRequest;
use APP\submission\Collector as Collector;

class DNBSubmissionsApiHandler {
	
	private object $plugin;
	
	/**
	 * Constructor for the submissions API handler.
	 *
	 * @param object $plugin The DNB export plugin instance.
	 */
	public function __construct(object $plugin) {
		$this->plugin = $plugin;
	}
	
	/**
	 * Process an API request to list submissions for the DNB export plugin.
	 *
	 * @param IlluminateRequest $illuminateRequest The Laravel request object containing query parameters.
	 * @param PKPRequest $request OJS request providing context and user information.
	 * @return JsonResponse JSON response containing items, totals and optional status counts.
	 */
	public function handle(IlluminateRequest $illuminateRequest, PKPRequest $request): JsonResponse {
		$context = $request->getContext();
		$params = $illuminateRequest->query();
		$args = array_merge($params, $illuminateRequest->input() ?? []);
		
		$statusName = $this->plugin->getDepositStatusSettingName();
		$countsOnly = !empty($args['countsOnly']);
		$defaultItemsPerPage = (int) ($context->getData('itemsPerPage') ?: 20);
		$itemsPerPage = isset($args['count']) ? (int) $args['count'] : $defaultItemsPerPage;
		$itemsPerPage = max(1, min(100, $itemsPerPage));
		$offset = isset($args['offset']) ? max(0, (int) $args['offset']) : 0;

		// Get all published submissions
		$issueIds = Repo::issue()
			->getCollector()
			->filterByContextIds([$context->getId()])
			->filterByPublished(true)
			->getIds()
			->toArray();
			
		$collector = Repo::submission() /** @var $collector Collector **/
			->getCollector()
			->filterByContextIds([$context->getId()])
			->filterByIssueIds(isset($args['issueIds']) ? $args['issueIds'] : $issueIds)
			->filterByStatus([Submission::STATUS_PUBLISHED]);
		
		if (isset($args['sectionIds'])) {
			$collector->filterBySectionIds($args['sectionIds']);
		}
		if (isset($args['searchPhrase'])) {
			$collector->SearchPhrase($args['searchPhrase']); /** @var $collector Collector **/
		}
		
		$itemsMax = $collector->getCount();
		if ($countsOnly) {
			$submissionIds = $collector->getIds()->toArray();
			$statusCounts = $this->getStatusCounts($submissionIds, $statusName);
			return response()->json([
				'itemsMax' => count($submissionIds),
				'items' => [],
				'statusCounts' => $statusCounts,
			], 200);
		}
		$submissionsIterator = $collector
			->limit($itemsPerPage)
			->offset($offset)
			->getMany()
			->remember();
		$submissions = $submissionsIterator->all();
			
		// Get default submission list properties
		$userGroups = UserGroup::withContextIds([$context->getId()])->get();
		$genreDao = DAORegistry::getDAO('GenreDAO'); /** @var \PKP\submission\GenreDAO $genreDao */
		$genres = $genreDao->getByContextId($context->getId())->toArray();
		$userRoles = array_map(
			fn ($role) => $role->getId(),
			$request->getUser()->getRoles($context->getId()) ?? []
		);
		$mappedItems = Repo::submission()->getSchemaMap()
			->mapManyToSubmissionsList($submissionsIterator, $userGroups, $genres, $userRoles)
			->toArray();

		// Add DNB-specific properties
		$items = $this->enrichWithDNBData($submissions, $mappedItems, $request, $context, $statusName);
		
		// Filter by DNB status
		$items = $this->filterByDNBStatus($items, $args, $statusName);
		if (isset($args[$statusName])) {
			$itemsMax = count($items);
		}

		// Paginate
		$data = [];
		$data['itemsMax'] = $itemsMax;
		$data['items'] = array_values($items);
	
		return response()->json($data, 200);
	}

	/**
	 * Count submissions by export status.
	 *
	 * @param int[] $submissionIds Array of submission identifiers to inspect.
	 * @param string $statusName Name of the setting storing the export status.
	 * @return array Associative array with counts for each status category.
	 */
	private function getStatusCounts(array $submissionIds, string $statusName): array
	{
		$counts = [
			'all' => count($submissionIds),
			'notDeposited' => 0,
			'deposited' => 0,
			'queued' => 0,
			'failed' => 0,
			'markedRegistered' => 0,
			'excluded' => 0,
		];

		if (empty($submissionIds)) {
			return $counts;
		}

		$statusCounts = DB::table('submission_settings')
			->select('setting_value', DB::raw('COUNT(*) as count'))
			->where('setting_name', $statusName)
			->whereIn('submission_id', $submissionIds)
			->groupBy('setting_value')
			->pluck('count', 'setting_value');

		$counts['deposited'] = (int) ($statusCounts[DNB_STATUS_DEPOSITED] ?? 0);
		$counts['queued'] = (int) ($statusCounts[DNB_EXPORT_STATUS_QUEUED] ?? 0);
		$counts['failed'] = (int) ($statusCounts[DNB_EXPORT_STATUS_FAILED] ?? 0);
		$counts['markedRegistered'] = (int) ($statusCounts[$this->plugin::EXPORT_STATUS_MARKEDREGISTERED] ?? 0);
		$counts['excluded'] = (int) ($statusCounts[DNB_EXPORT_STATUS_MARKEXCLUDED] ?? 0);

		$known = $counts['deposited'] + $counts['queued'] + $counts['failed'] + $counts['markedRegistered'] + $counts['excluded'];
		$counts['notDeposited'] = max(0, $counts['all'] - $known);

		return $counts;
	}
	
	/**
	 * Add plugin-specific properties (deposit status, export URLs, etc.) to the
	 * mapped submission items.
	 *
	 * @param Submission[] $submissions The raw submission objects.
	 * @param array<int, array> $mappedItems Schema-mapped representations keyed by submission id.
	 * @param PKPRequest $request Current request context.
	 * @param Context $context Journal context.
	 * @param string $statusName Export status setting identifier.
	 * @return array<int, array> The enriched items array.
	 */
	private function enrichWithDNBData(array $submissions, array $mappedItems, PKPRequest $request, Context $context, string $statusName): array {
		$items = [];
		$pluginPrefix = $this->plugin->getPluginSettingsPrefix();
		$contextPath = $context->getPath();
		$authorUserGroups = UserGroup::withContextIds([$context->getId()])
			->withRoleIds([\PKP\security\Role::ROLE_ID_AUTHOR])
			->get();

		// Gather all issue IDs referenced by the current batch of submissions.
		// We'll fetch those issues in a single query to avoid N+1 retrieval
		// later when iterating submissions.
		$issueIds = array_values(array_unique(array_filter(array_map(function ($submission) {
			return $submission->getCurrentPublication()->getData('issueId');
		}, $submissions))));
		$issuesById = [];
		if (!empty($issueIds)) {
			$issues = Repo::issue()
				->getCollector()
				->filterByContextIds([$context->getId()])
				->filterByIssueIds($issueIds)
				->getMany();
			foreach ($issues as $issue) {
				$issuesById[$issue->getId()] = $issue;
			}
		}

		// Some submissions may not yet have had their validation cache populated
		// (e.g. newly imported or edited items).  We detect those cases by
		// looking for a null value in the supplementaryNotAssignable flag and
		// trigger an on-the-fly refresh so that subsequent logic has accurate
		// data.  Refreshed submissions are stored separately to avoid
		// re-querying the original iterator mid-loop.
		$missingCacheIds = [];
		foreach ($submissions as $submission) {
			if ($submission->getData($pluginPrefix . '::supplementaryNotAssignable') === null) {
				$missingCacheIds[] = $submission->getId();
			}
		}

		$refreshedSubmissions = [];
		foreach ($missingCacheIds as $submissionId) {
			$this->plugin->updateExportValidation($submissionId);
			$refreshedSubmissions[$submissionId] = Repo::submission()->get($submissionId);
		}
		
		foreach ($submissions as $submission) {
			$item = $mappedItems[$submission->getId()];
			
			$issueId = $submission->getCurrentPublication()->getData('issueId');
			$issue = $issueId ? ($issuesById[$issueId] ?? null) : null;
			if (!$issue) {
				continue;
			}
			
			$item['issueTitle'] = $issue->getLocalizedTitle();
			$item['issueUrl'] = $request->getDispatcher()->url(
				$request,
				Application::ROUTE_PAGE,
				$contextPath,
				'issue',
				'view',
				[$issue->getBestIssueId()]
			);

			$submissionData = $refreshedSubmissions[$submission->getId()] ?? $submission;
			$supplementaryNotAssignable = $submissionData->getData($pluginPrefix . '::supplementaryNotAssignable') ?? false;
			$galleyCount = (int) ($submissionData->getData($pluginPrefix . '::galleyCount') ?? 0);
			$supplementaryCount = (int) ($submissionData->getData($pluginPrefix . '::supplementaryCount') ?? 0);

			// Create warning message if issues detected with supplementary files
			$msg = "";
			if ($supplementaryNotAssignable) {
				$plural = Locale::getLocale() == "de_DE" ? "n" : "s";
				$msg = __('plugins.importexport.dnb.warning.supplementaryNotAssignable',
					array(
						'nDoc' => $galleyCount,
						'nSupp' => $supplementaryCount,
						'pSupp' => $supplementaryCount > 1 ? $plural : ""
					));
			}
			$item['supplementaryNotAssignable'] = $supplementaryNotAssignable;
			$item['supplementaryNotAssignableMessage'] = $msg !== "" ? $msg : false;

			$currentPublication = $submission->getCurrentPublication();
			$item['publication']['id'] = $currentPublication->getData('id');
			$item['sectionIds'] = $currentPublication->getData('sectionId');
			
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
	 * Filter items by DNB status parameter provided in the request.
	 *
	 * @param array $items Array of item maps produced by schema mapping.
	 * @param array $args Request arguments which may include status filters.
	 * @param string $statusName The name of the status field to match.
	 * @return array Filtered array retaining only matching statuses.
	 */
	private function filterByDNBStatus(array $items, array $args, string $statusName): array {
		return array_filter($items, function ($item) use ($args, $statusName) {
			if (isset($args[$statusName])) {
				$result = false;
				foreach ($args[$statusName] as $status) {
					if ($item[$statusName] == NULL && $status == $this->plugin::EXPORT_STATUS_NOT_DEPOSITED) {
						$result = true;
						break;
					}
					$result = $result || $item[$statusName] == $status;
				}
				return $result;
			}
			return true;
		});
	}
}

<?php

declare(strict_types=1);

namespace OCA\NopStationAnalytics\Controller;

use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;

class ApiController extends OCSController {
	/**
	 * Health check / ping endpoint
	 *
	 * @return DataResponse<Http::STATUS_OK, array{message: string}, array{}> The API is active and running
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/api')]
	public function index(): DataResponse {
		return new DataResponse(
			['message' => 'nopStation Analytics API active']
		);
	}
}

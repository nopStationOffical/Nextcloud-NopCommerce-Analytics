<?php

declare(strict_types=1);

namespace OCA\NopStationAnalytics\Controller;

use OCA\NopStationAnalytics\Service\WebhookService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\FrontpageRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;

class WebhookController extends Controller {
	private const APP_ID = 'nopstation_analytics';

	public function __construct(
		IRequest $request,
		private WebhookService $webhookService
	) {
		parent::__construct(self::APP_ID, $request);
	}

	#[PublicPage]
	#[NoCSRFRequired]
	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'POST', url: '/api/v1/webhook')]
	public function receive(): JSONResponse {
		$signature = $this->request->getHeader('X-NopStation-Signature');
		$timestamp = $this->request->getHeader('X-NopStation-Timestamp');
		$eventType = $this->request->getHeader('X-NopStation-Event');

		$rawBody = file_get_contents('php://input');

		// 1. Verify HMAC signature & timestamp
		if (!$this->webhookService->verifySignature($signature, $timestamp, $rawBody)) {
			return new JSONResponse([
				'status' => 'error',
				'message' => 'Invalid or expired webhook signature.',
			], Http::STATUS_UNAUTHORIZED);
		}

		// 2. Parse JSON payload
		$payload = json_decode($rawBody, true);
		if (!is_array($payload)) {
			return new JSONResponse([
				'status' => 'error',
				'message' => 'Malformed JSON payload.',
			], Http::STATUS_BAD_REQUEST);
		}

		if ($eventType === null || $eventType === '') {
			$eventType = (string)($payload['event'] ?? $payload['Event'] ?? 'order.created');
		}

		// 3. Process event
		$result = $this->webhookService->processEvent($eventType, $payload);

		$httpCode = $result['status'] === 'success' ? Http::STATUS_OK : Http::STATUS_INTERNAL_SERVER_ERROR;
		return new JSONResponse($result, $httpCode);
	}
}

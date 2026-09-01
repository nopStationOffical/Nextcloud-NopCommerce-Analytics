<?php

declare(strict_types=1);

namespace Controller;

use OCA\NopStationAnalytics\AppInfo\Application;
use OCA\NopStationAnalytics\Controller\ApiController;
use OCP\IRequest;
use PHPUnit\Framework\TestCase;

final class ApiTest extends TestCase {
	public function testIndex(): void {
		$request = $this->createMock(IRequest::class);
		$controller = new ApiController(Application::APP_ID, $request);

		$this->assertEquals('nopStation Analytics API active', $controller->index()->getData()['message']);
	}
}

<?php

declare(strict_types=1);

namespace Service;

use OCA\NopStationAnalytics\Service\NopApiClient;
use OCP\Http\Client\IClientService;
use OCP\IConfig;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class NopApiClientTest extends TestCase {
	private NopApiClient $client;

	protected function setUp(): void {
		parent::setUp();
		$config = $this->createMock(IConfig::class);
		$clientService = $this->createMock(IClientService::class);
		$logger = $this->createMock(LoggerInterface::class);

		$this->client = new NopApiClient($config, $clientService, $logger);
	}

	public function testParseMoneyDollarString(): void {
		$this->assertEquals(700.0, $this->client->parseMoney('$700.00'));
		$this->assertEquals(1855.50, $this->client->parseMoney('$1,855.50'));
	}

	public function testParseMoneyWithOtherCurrencies(): void {
		$this->assertEquals(19.0, $this->client->parseMoney('19.00৳'));
		$this->assertEquals(250.0, $this->client->parseMoney('€250.00'));
	}

	public function testParseMoneyNegativeParentheses(): void {
		$this->assertEquals(-10.0, $this->client->parseMoney('($10.00)'));
		$this->assertEquals(-25.5, $this->client->parseMoney('-$25.50'));
	}

	public function testParseMoneyNumericInput(): void {
		$this->assertEquals(50.0, $this->client->parseMoney(50));
		$this->assertEquals(99.99, $this->client->parseMoney(99.99));
		$this->assertEquals(0.0, $this->client->parseMoney(''));
		$this->assertEquals(0.0, $this->client->parseMoney(null));
	}
}

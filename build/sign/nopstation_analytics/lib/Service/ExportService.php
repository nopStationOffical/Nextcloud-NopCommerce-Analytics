<?php

declare(strict_types=1);

namespace OCA\NopStationAnalytics\Service;

use Exception;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use Psr\Log\LoggerInterface;

class ExportService {
	private const APP_ID = 'nopstation_analytics';
	private const EXPORT_FOLDER = 'NopCommerce_Analytics/Reports';

	public function __construct(
		private IRootFolder $rootFolder,
		private AnalyticsCalculatorService $analyticsService,
		private LoggerInterface $logger
	) {
	}

	public function exportSalesSummaryCsv(
		string $userId,
		?string $startDate = null,
		?string $endDate = null,
		int $storeId = 0,
		string $groupBy = 'day'
	): array {
		$data = $this->analyticsService->getSalesSummary($startDate, $endDate, $storeId, $groupBy);

		$userFolder = $this->rootFolder->getUserFolder($userId);
		$targetFolder = $this->getOrCreateFolder($userFolder, self::EXPORT_FOLDER);

		$timestamp = date('Ymd_His');
		$fileName = "sales_summary_{$groupBy}_{$timestamp}.csv";

		$handle = fopen('php://temp', 'r+');
		fputcsv($handle, ['Period', 'Number of Orders', 'Profit', 'Shipping', 'Tax', 'Order Total']);

		foreach ($data as $row) {
			fputcsv($handle, [
				$row['summary'],
				$row['numberOfOrders'],
				number_format($row['profit'], 2, '.', ''),
				number_format($row['shipping'], 2, '.', ''),
				number_format($row['tax'], 2, '.', ''),
				number_format($row['orderTotal'], 2, '.', ''),
			]);
		}

		rewind($handle);
		$csvContent = stream_get_contents($handle);
		fclose($handle);

		$file = $targetFolder->newFile($fileName);
		$file->putContent($csvContent);

		return [
			'fileName' => $fileName,
			'filePath' => '/' . self::EXPORT_FOLDER . '/' . $fileName,
			'fileId' => $file->getId(),
			'size' => $file->getSize(),
			'rowCount' => count($data),
			'createdAt' => date('Y-m-d H:i:s'),
		];
	}

	public function exportBestsellersCsv(string $userId, int $limit = 50): array {
		$data = $this->analyticsService->getBestsellers($limit);

		$userFolder = $this->rootFolder->getUserFolder($userId);
		$targetFolder = $this->getOrCreateFolder($userFolder, self::EXPORT_FOLDER);

		$timestamp = date('Ymd_His');
		$fileName = "bestsellers_{$timestamp}.csv";

		$handle = fopen('php://temp', 'r+');
		fputcsv($handle, ['Product ID', 'Product Name', 'Total Quantity Sold', 'Total Sales Amount']);

		foreach ($data as $row) {
			fputcsv($handle, [
				$row['productId'],
				$row['productName'],
				$row['totalQuantity'],
				number_format($row['totalAmount'], 2, '.', ''),
			]);
		}

		rewind($handle);
		$csvContent = stream_get_contents($handle);
		fclose($handle);

		$file = $targetFolder->newFile($fileName);
		$file->putContent($csvContent);

		return [
			'fileName' => $fileName,
			'filePath' => '/' . self::EXPORT_FOLDER . '/' . $fileName,
			'fileId' => $file->getId(),
			'size' => $file->getSize(),
			'rowCount' => count($data),
			'createdAt' => date('Y-m-d H:i:s'),
		];
	}

	private function getOrCreateFolder(Folder $baseFolder, string $path): Folder {
		$parts = explode('/', trim($path, '/'));
		$current = $baseFolder;

		foreach ($parts as $part) {
			if (!$current->nodeExists($part)) {
				$current = $current->newFolder($part);
			} else {
				$node = $current->get($part);
				if ($node instanceof Folder) {
					$current = $node;
				} else {
					throw new Exception("Path component '{$part}' is not a folder.");
				}
			}
		}

		return $current;
	}
}

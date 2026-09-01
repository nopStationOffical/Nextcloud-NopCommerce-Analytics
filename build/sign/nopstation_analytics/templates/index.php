<?php

declare(strict_types=1);

use OCA\NopStationAnalytics\AppInfo\Application;
use OCP\Util;

Util::addScript(Application::APP_ID, Application::APP_ID . '-main');
Util::addStyle(Application::APP_ID, Application::APP_ID . '-main');

$cssPath = __DIR__ . '/../css/nopstation_analytics-main.css';
$cssMtime = file_exists($cssPath) ? filemtime($cssPath) : time();
?>

<link rel="stylesheet" href="/custom_apps/nopstation_analytics/css/nopstation_analytics-main.css?t=<?= $cssMtime ?>">

<div id="nopstation_analytics"></div>

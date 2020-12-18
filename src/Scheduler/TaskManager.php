<?php
namespace App\Scheduler;
use App\Scheduler\Kernel;
use Carbon\Carbon;

// bootstrap
include dirname(dirname(dirname(__FILE__))) . '/app/bootstrap.php';
include dirname(dirname(dirname(__FILE__))) . '/app/middleware.php';

// unlimited time
ini_set('memory_limit', '-1');
$taskLogger = $c->taskLogger;

echo 'Creating Kernel..' . PHP_EOL;
$taskLogger->info('Creating Kernel..');
$kernel = new Kernel;

echo 'Updating Carbon timezone..' . PHP_EOL;
$taskLogger->info('Updating Carbon timezone..');
$kernel->setDate(Carbon::now()->tz('America/Chicago'));

echo 'Adding tasks for TaskManager..' . PHP_EOL;
$taskLogger->info('Adding tasks for TaskManager..');

/**
 * Database backup and summary
 */
$kernel->add(new HourlyReport($app))->cron('0 */3 * * *');

/**
 * Check enpoint health
 */
$kernel->add(new HeartbeatTask($app))->cron('*/1 * * * *');

/**
 * View new submissions online 10PM CST
 */
$kernel->add(new UserSubmissionsTask($app))->cron('0 22 * * *');

/**
 * User Report sent on the 1st and 15th at 2PM
 */
$kernel->add(new RecurringReportTask($app))->cron('0 14 1 * *');
$kernel->add(new RecurringReportTask($app))->cron('0 14 15 * *');

echo 'Kernel now running..' . PHP_EOL;
$taskLogger->info('Kernel now running..');

$kernel->run();
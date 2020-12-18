<?php 
namespace App\Scheduler;

use Carbon\Carbon;
use Cron\CronExpression;

abstract class Event
{
	use Frequencies;
	
	/**
	 * The cron expression for this event.
	 *
	 * @var string
	 */
	public $expression = '* * * * *';

	/**
	 * Handle the event.
	 *
	 * @return void
	 */
	abstract public function handle();

	/**
	 * Check if the event is due to run.
	 *
	 * @param  Carbon  $date
	 * @return boolean
	 */
	public function isDueToRun(Carbon $date)
	{
		return CronExpression::factory($this->expression)->isDue($date);
	}

	public function log($logger = null, string $type = 'info', string $msg = '')
	{
		if (!is_null($logger))
		{
			$logger->{$type}($msg);
		}
		else throw new \Exception('Logger instance is null for Event', 1);
	}
}
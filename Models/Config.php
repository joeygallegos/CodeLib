<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model as Eloquent;
class Config extends Eloquent {
	protected $table = 'config';
	protected $fillable = [
		'id',
		'configuration_key',
		'configuration_type',
		'configuration_value',
		'configuration_sequence',
		'configuration_readonly',
		'configuration_description'
	];

	protected $guarded = ['id'];
	public $timestamps = false;

	// get a section from the config
	public static function get($key = '', $default = '') {
		$entry = self::where('configuration_key', '=', $key)->first();
		
		// return default value
		if (is_null($entry)) {
			if (is_string($default)) {
				return htmlspecialchars($default);
			}
			return $default;
		}

		// if not null
		if (!is_null($entry->configuration_value)) {

			// if is string special characters
			if (is_string($entry->configuration_value)) {
				return htmlspecialchars($entry->configuration_value);
			}
		}

		return $entry->configuration_value;
	}

	public static function updateSection($key = null, $value = null, $container = null) {
		if (is_null($key)) throw new \Exception('The key provided is null', 1);
		if (is_null($value)) throw new \Exception('The value to update is null', 1);

		// valid logger instance
		$activeLog = false;
		if ((!is_null($container)) && (!is_null($container->logger))) {
			$activeLog = true;
		}

		// if entry exists
		$entry = self::where('configuration_key', '=', $key)->first();
		if (is_null($entry)) {
			if ($activeLog) {
				$message = '{Config->updateSection}: No entry in the database found for an entry, please check the log for more information';
				$container->logger->error($message);
				throw new \Exception($message, 1);
			}
		}

		if ($activeLog) {
			self::__logChange($container, 'updateSection', $entry->configuration_key, $entry->configuration_value, $value);
		}

		$entry->configuration_value = $value;
		return $entry->save();
	}

	public static function updateRaw($key = null, $value = null, $container = null) {
		if (is_null($key)) throw new \Exception('The key provided is null', 1);

		// valid logger instance
		$activeLog = false;
		if ((!is_null($container)) && (!is_null($container->logger))) {
			$activeLog = true;
		}

		// update raw
		$entry = self::where('configuration_key', '=', $key)->first();

		// if entry exists
		if (is_null($entry)) {
			if ($activeLog) {
				$message = '{Config->updateRaw}: No entry in the database found for an entry, please check the log for more information';
				$container->logger->error($message);
				throw new \Exception($message, 1);
			}
		}

		if ($activeLog) {
			self::__logChange($container, 'updateRaw', $entry->configuration_key, $entry->configuration_value, $value);
		}

		$entry->configuration_value = $value;
		return $entry->save();
	}

	private static function __logChange($container, $functionName, $key, $before, $after) {
		if (is_null($after)) $after = 'null';
		$container->logger->info("{Config->$functionName}: Config data was changed for {$key}: {$before} -> {$after}");
	}

	public static function getRaw($section = '', $defaultValue = '') {
		$item = self::where('configuration_key', '=', $section)->first();
		$returnedValue = $item->configuration_value;
		
		// return default value
		if (is_null($returnedValue) || $returnedValue === '') return $defaultValue;
		return $returnedValue;
	}

	public static function getArrayableData() {
		$arr = [];
		foreach (self::all() as $item) {
			$arr[$item->configuration_key] = $item;
		}
		return $arr;
	}
}
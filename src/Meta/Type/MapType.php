<?php declare(strict_types=1);

namespace Sturdy\Activity\Meta\Type;

use Ds\Map;
use stdClass;

final class MapType extends Type
{
	const type = "map";
	private $options = [];

	/**
	 * Constructor
	 *
	 * @param string|null $state  the objects state
	 */
	public function __construct(string $state = null)
	{
		$this->options = new Map;
		if ($state !== null) {
			foreach (explode("\x1E", $state) as $option) {
				[$value, $label, $_tags] = explode("\x1F", $option);
				$_tags = $_tags ? explode("\x1C", $_tags) : [];
				$tags = [];

				foreach ($_tags as $tag) {
					if (!empty($tag)){
						[$key, $val] = explode("\x1D", $tag);
						$tags[$key] = $val;
					}
				}

				$this->options->put($value, [$label, $tags]);
			}
		}
	}

	/**
	 * Get descriptor
	 *
	 * @return string
	 */
	public function getDescriptor(): string
	{
		$descriptor = self::type;
		$i = 0;
		foreach ($this->options->toArray() as $value => [$label, $tags]) {
			$descriptor .= $i++ ? "\x1E" : ":";
			$descriptor .= "$value\x1F$label\x1F";
			$j = 0;
			foreach ($tags as $key => $val) {
				$descriptor .= $j++ ? "\x1C" : "";
				$descriptor .= "$key\x1D$val";
			}
		}
		return $descriptor;
	}

	/**
	 * Get all possible options
	 *
	 * @return Map
	 */
	public function getOptions(): ?Map
	{
		return $this->options;
	}

	/**
	 * Add option
	 *
	 * @param string $value
	 * @param string $label
	 * @param array $tags
	 * @return MapType
	 */
	public function addOption(string $value, string $label, array $tags = []): self
	{
		$this->options->put($value, [$label, $tags]);
		return $this;
	}

	/**
	 * Set meta properties on object
	 *
	 * @param stdClass $meta
	 * @param array $state
	 */
	public function meta(stdClass $meta, array $state): void
	{
		$meta->type = self::type;
		if ($this->options->count()) {
			$meta->options = [];
			foreach ($this->options->toArray() as $value => [$label, $tags]) {
				foreach ($tags as $key => $val) {
					if (!isset($state[$key]) || $state[$key] !== $val) {
						continue;
					}
				}
				$meta->options[] = ["value" => $value, "label" => $label];
			}
		}
	}

	/**
	 * Filter value
	 *
	 * @param  $value string the value to filter
	 * @return bool whether the value is valid
	 */
	public function filter(&$value): bool
	{
		if (!is_string($value)) return false;
		return $this->options->hasKey($value);
	}
}


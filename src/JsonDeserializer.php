<?php declare(strict_types=1);

namespace Sturdy\Activity;

/**
 * The reverse of JsonSerializable
 */
interface JsonDeserializer
{
	/**
	 * Deserialize value
	 *
	 * @param  string $typeHint  a type hint, for instance uuid or datetime
	 * @param  mixed  $value     the value to deserialize
	 * @return mixed             the deserialized version
	 */
	public function jsonDeserialize(string $typeHint, $value);
}

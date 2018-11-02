<?php declare(strict_types=1);

namespace Sturdy\Activity\Meta\Type;

use stdClass;
use Sturdy\Activity\Meta\Field;
use Sturdy\Activity\Meta\FieldFlags;
use Sturdy\Activity\Translator;

/**
 * Reference type
 */
final class ReferenceType extends Type
{
	const type = "reference";

	/**
	 * Constructor
	 *
	 * @param string|null $state  the objects state
	 */
	public function __construct(string $state = null)
	{
	}

	/**
	 * Set meta properties on object
	 *
	 * @param stdClass $meta
	 * @param array $state
	 */
	public function meta(stdClass $meta, array $state): void
	{
		throw new \Exception("reference can't be used in responses");
	}

	/**
	 * Get descriptor
	 *
	 * @return string
	 */
	public function getDescriptor(): string
	{
		return self::type;
	}

	/**
	 * Get fields
	 *
	 * @return iterable  the fields
	 */
	public function getTaggables(): iterable
	{
		foreach ($this->fields??[] as $name => $fields) {
			foreach ($fields as $field) {
				yield $field;
				$type = $field->getType();
				if ($type instanceof self) {
					yield from $type->getTaggables();
				}
			}
		}
	}

	/**
	 * Filter value
	 *
	 * @param  &$value  the value to filter
	 * @return bool  whether the value is valid
	 */
	public function filter(&$value): bool
	{
		return is_object($value) && !$value instanceof stdClass;
	}
}

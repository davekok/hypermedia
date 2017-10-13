<?php declare(strict_types=1);

namespace Sturdy\Activity\Meta\Type;

use stdClass;
use Sturdy\Activity\Meta\Field;

/**
 * String type
 */
final class ObjectType extends Type
{
	const type = "object";

	private $fields;

	/**
	 * Constructor
	 *
	 * @param array|null $state  the objects state
	 */
	public function __construct(array $state = null)
	{
		if ($state !== null) {
		}
	}

	/**
	 * Set meta properties on object
	 *
	 * @param stdClass $meta
	 */
	public function meta(stdClass $meta): void
	{
		$meta->type = self::type;
		$meta->fields = [];
		foreach ($this->fields as $field) {
			$meta->fields[] = $submeta = new stdClass;
			$field->meta($submeta);
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
		foreach ($this->fields as $field) {
			$descriptor .= ",(".$field->getDescriptor().")";
		}
		return $descriptor;
	}

	/**
	 * Add a field
	 *
	 * @param Field $field  the field to add
	 */
	public function addField(Field $field)
	{
		$this->fields[] = $field;
	}

	/**
	 * Get fields
	 *
	 * @return iterable  the fields
	 */
	public function getFields(): iterable
	{
		return $this->fields;
	}

	/**
	 * Filter value
	 *
	 * @param  &$value  the value to filter
	 * @return bool  whether the value is valid
	 */
	public function filter(&$value): bool
	{
		return is_object($value);
	}
}

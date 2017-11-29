<?php declare(strict_types=1);

namespace Sturdy\Activity\Meta\Type;

use stdClass;
use Sturdy\Activity\Meta\Field;
use Sturdy\Activity\Meta\FieldFlags;
use Sturdy\Activity\Translator;

/**
 * String type
 */
final class ObjectType extends Type
{
	const type = "object";

	private $fieldDescriptors;
	private $fields; // only used for during compilation

	/**
	 * Constructor
	 *
	 * @param string|null $state  the objects state
	 */
	public function __construct(string $state = null)
	{
		if ($state !== null) {
			$this->fieldDescriptors = unserialize($state);
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
		$meta->fields = new stdClass;
		foreach ($this->fieldDescriptors as $name => [$type, $defaultValue, $flags, $autocomplete, $label]) {
			$submeta = new stdClass;
			if ($label) {
				$submeta->label = $label;
			}
			Type::createType($type)->meta($submeta);
			(new FieldFlags($flags))->meta($submeta);
			if ($defaultValue !== null) {
				$submeta->defaultValue = $defaultValue;
			}
			if ($autocomplete !== null) {
				$submeta->autocomplete = $autocomplete;
			}
			$meta->fields->$name = $submeta;
		}
	}

	/**
	 * Get descriptor
	 *
	 * @return string
	 */
	public function getDescriptor(): string
	{
		return self::type.":".serialize($this->fieldDescriptors);
	}

	/**
	 * Set field descriptor
	 *
	 * @param string $name          the name of the field
	 * @param string $type          the type descriptor
	 * @param mixed  $defaultValue  the default value
	 * @param int    $flags         field flags
	 * @param string $autocomplete  autocomplete expression
	 * @param string $label         label
	 */
	public function setFieldDescriptor(string $name, string $type, $defaultValue, int $flags, ?string $autocomplete, ?string $label): self
	{
		$this->fieldDescriptors[$name] = [$type, $defaultValue, $flags, $autocomplete, $label];
		return $this;
	}

	/**
	 * Get field descriptors
	 */
	public function getFieldDescriptors(): array
	{
		return $this->fieldDescriptors??[];
	}

	/**
	 * Add a field
	 *
	 * @param Field $field  the field to add
	 */
	public function addField(Field $field): self
	{
		$this->fields[$field->getName()][] = $field;
		return $this;
	}

	/**
	 * Get fields
	 *
	 * @return iterable  the fields
	 */
	public function getFields(): iterable
	{
		return $this->fields??[];
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
		if (!is_object($value)) {
			return false;
		}
		foreach ($this->fieldDescriptors as $name => [$type, $defaultValue, $flags, $autocomplete]) {
			$flags = new FieldFlags($flags);
			if (isset($value->$name)) {
			} elseif ($flags->isRequired()) {
			}
			$type = Type::createType($type);
		}
	}
}

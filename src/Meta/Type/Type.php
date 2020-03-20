<?php declare(strict_types=1);

namespace Sturdy\Activity\Meta\Type;

use stdClass;

/**
 * Type
 */
abstract class Type
{
	const types = [
		BooleanType::type => BooleanType::class,
		ColorType::type => ColorType::class,
		DateTimeType::type => DateTimeType::class,
		DateType::type => DateType::class,
		DayType::type => DayType::class,
		EmailType::type => EmailType::class,
		EnumType::type => EnumType::class,
		FloatType::type => FloatType::class,
		HTMLType::type => HTMLType::class,
		IntegerType::type => IntegerType::class,
		LinkType::type => LinkType::class,
		ListType::type => ListType::class,
		MapType::type => MapType::class,
		MonthType::type => MonthType::class,
		ObjectType::type => ObjectType::class,
		PasswordType::type => PasswordType::class,
		ReferenceType::type => ReferenceType::class,
		SetType::type => SetType::class,
		StringType::type => StringType::class,
		IconType::type => IconType::class,
		TimeType::type => TimeType::class,
		TupleType::type => TupleType::class,
		URLType::type => URLType::class,
		UUIDType::type => UUIDType::class,
		WeekDayType::type => WeekDayType::class,
		WeekType::type => WeekType::class,
		YearType::type => YearType::class,
	];

	protected $slot;

	public static function createType(string $state): Type
	{
		$p = strpos($state, ":");
		if ($p !== false) {
			$type = substr($state, 0, $p);
			$state = substr($state, $p+1);
		} else {
			$type = $state;
			$state = null;
		}
		$class = self::types[$type];
		return new $class($state);
	}

	/**
	 * Get state
	 *
	 * @return string
	 */
	public abstract function getDescriptor(): string;

	/**
	 * Set meta properties on object
	 *
	 * @param stdClass $meta
	 * @param array $state
	 */
	public abstract function meta(stdClass $meta, array $state): void;

	/**
	 * Filter value
	 *
	 * @param  &$value string the value to filter
	 * @return bool  whether the value is valid
	 */
	public abstract function filter(&$value): bool;

	public function setSlot(string $slot): void
	{
		$this->slot = $slot;
	}

	public function getSlot(): ?string
	{
		return $this->slot;
	}
}

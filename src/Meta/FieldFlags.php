<?php declare(strict_types=1);

namespace Sturdy\Activity\Meta;

use stdClass;

/**
 * Field flags
 */
final class FieldFlags
{
	const required =    1;  // whether the field is required
	const readonly =    2;  // whether the field is readonly
	const disabled =    4;  // whether the field is disabled
	const multiple =    8;  // if multiple emails or files are allowed
	const _array   =   16;  // field contains an array of the given type, can not be a meta field
	const meta     =   32;  // whether this is meta data, meta data is part of the URI, making URI a templated URI
	                        // also meta data is never in the data section
	const data     =   64;  // only one field may have this flag, when used on a field the value of the field will
	                        // be put in the data section and all other fields must have the meta flag
	const state    =  128;  // whether the field is a state field, state fields are hidden from the client
	                        // state fields can be used to embed data in the URL without the client knowing about it
	const recon    =  256;  // whether the field is a recon field, whenever the value of a recon field changes the
	                        // client should send a RECON request to the server in which case the resource is
	                        // reconditioned, depending on changing state
	const lookup   =  512;  // whether the field is a lookup field, whenever the value of a lookup field changes the
	                        // client should send a LOOKUP request to the server in which case the resource is
	                        // looked up
	const matrix   = 1024;  // field is a matrix or two dimensional array

	private $flags;         // bitmask of the above constants

	/**
	 * Constructor
	 *
	 * @param int $flags
	 */
	public function __construct(int $flags = 0)
	{
		$this->flags = $flags;
	}

	public function setRequired(): self
	{
		$this->flags |= self::required;
		return $this;
	}

	public function clearRequired(): self
	{
		$this->flags &= ~self::required;
		return $this;
	}

	public function isRequired(): bool
	{
		return (bool)($this->flags & self::required);
	}

	public function setReadonly(): self
	{
		$this->flags |= self::readonly;
		return $this;
	}

	public function clearReadonly(): self
	{
		$this->flags &= ~self::readonly;
		return $this;
	}

	public function isReadonly(): bool
	{
		return (bool)($this->flags & self::readonly);
	}

	public function setDisabled(): self
	{
		$this->flags |= self::disabled;
		return $this;
	}

	public function clearDisabled(): self
	{
		$this->flags &= ~self::disabled;
		return $this;
	}

	public function isDisabled(): bool
	{
		return (bool)($this->flags & self::disabled);
	}

	public function setMultiple(): self
	{
		$this->flags |= self::multiple;
		return $this;
	}

	public function clearMultiple(): self
	{
		$this->flags &= ~self::multiple;
		return $this;
	}

	public function isMultiple(): bool
	{
		return (bool)($this->flags & self::multiple);
	}

	public function setArray(): self
	{
		$this->flags |= self::_array;
		return $this;
	}

	public function clearArray(): self
	{
		$this->flags &= ~self::_array;
		return $this;
	}

	public function isArray(): bool
	{
		return (bool)($this->flags & self::_array);
	}

	public function setMeta(): self
	{
		$this->flags |= self::meta;
		return $this;
	}

	public function clearMeta(): self
	{
		$this->flags &= ~self::meta;
		return $this;
	}

	public function isMeta(): bool
	{
		return (bool)($this->flags & self::meta);
	}

	public function setData(): self
	{
		$this->flags |= self::data;
		return $this;
	}

	public function clearData(): self
	{
		$this->flags &= ~self::data;
		return $this;
	}

	public function isData(): bool
	{
		return (bool)($this->flags & self::data);
	}

	public function setState(): self
	{
		$this->flags |= self::state;
		return $this;
	}

	public function clearState(): self
	{
		$this->flags &= ~self::state;
		return $this;
	}

	public function isState(): bool
	{
		return (bool)($this->flags & self::state);
	}

	public function setRecon(): self
	{
		$this->flags |= self::recon;
		return $this;
	}

	public function clearRecon(): self
	{
		$this->flags &= ~self::recon;
		return $this;
	}

	public function isRecon(): bool
	{
		return (bool)($this->flags & self::recon);
	}

	public function setLookup(): self
	{
		$this->flags |= self::lookup;
		return $this;
	}

	public function clearLookup(): self
	{
		$this->flags &= ~self::lookup;
		return $this;
	}

	public function isLookup(): bool
	{
		return (bool)($this->flags & self::lookup);
	}

	public function setMatrix(): self
	{
		$this->flags |= self::matrix;
		return $this;
	}

	public function clearMatrix(): self
	{
		$this->flags &= ~self::matrix;
		return $this;
	}

	public function isMatrix(): bool
	{
		return (bool)($this->flags & self::matrix);
	}

	public function toInt(): int
	{
		return $this->flags;
	}

	public function meta(stdClass $meta)
	{
		if ($this->isState()) {
			throw new \LogicException("State fields should not be visible to the client.");
		}
		if ($this->isRequired()) $meta->required = true;
		if ($this->isReadonly()) $meta->readonly = true;
		if ($this->isDisabled()) $meta->disabled = true;
		if ($this->isMultiple()) $meta->multiple = true;
		if ($this->isArray   ()) $meta->{"array"} = true;
		if ($this->isMeta    ()) $meta->meta = true;
		if ($this->isData    ()) $meta->data = true;
		if ($this->isRecon   ()) $meta->recon = true;
		if ($this->isLookup  ()) $meta->lookup = true;
		if ($this->isMatrix  ()) $meta->matrix = true;
	}
}

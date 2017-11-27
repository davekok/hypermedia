<?php declare(strict_types=1);

namespace Sturdy\Activity\Response;

use stdClass;
use Sturdy\Activity\Resource;

final class OK implements Response
{
	use ProtocolVersionTrait;
	use DateTrait;
	use NoLocationTrait;

	private $resource;
	private $parts;
	private $part;

	/**
	 * Constructor
	 *
	 * @param Resource $resource  the resource
	 */
	public function __construct(Resource $resource)
	{
		$this->resource = $resource;
		$this->parts = new stdClass;
		$this->parts->main = new stdClass;
		$this->part = $this->parts->main;
	}

	/**
	 * Get the response status code
	 *
	 * @return int  the status code
	 */
	public function getStatusCode(): int
	{
		return 200;
	}

	/**
	 * Get the response status text
	 *
	 * @return string  the status text
	 */
	public function getStatusText(): string
	{
		return "OK";
	}

	/**
	 * Get content type
	 *
	 * @return string  the content type
	 */
	public function getContentType(): string
	{
		return "application/json";
	}

	/**
	 * Get content
	 *
	 * @return string
	 */
	public function getContent(): string
	{
		return json_encode($this->parts, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
	}

	/**
	 * Set section
	 *
	 * @param ?string $section
	 */
	public function section(?string $section): void
	{
		if ($section !== null) {
			$this->part->section = $section;
		}
	}

	/**
	 * Set the data of this response.
	 *
	 * @param array $fields
	 * @param bool  $hasData
	 * @return self
	 */
	public function fields(array $fields, bool $hasData): void
	{
		$this->part->fields = new stdClass;
		foreach ($fields as $name => $field) {
			if ($field->meta??false) {
				$this->part->fields->$name = $field;
			} elseif ($field->data??false) {
				$this->part->fields->$name = $field;
				if ($hasData) {
					$this->part->data = $field->value??null;
				}
				unset($field->value);
			} else {
				$this->part->fields->$name = $field;
				if ($hasData) {
					if (!isset($this->part->data)) {
						$this->part->data = new stdClass;
					}
					$this->part->data->$name = $field->value??null;
				}
				unset($field->value);
			}
		}
	}

	/**
	 * Link to another resource.
	 *
	 * @param  string $name    the name of the link
	 * @param  string $class   the class of the resource
	 * @param  array  $values  the values in case the resource has uri fields
	 * @return bool  link succeeded
	 */
	public function link(string $name, string $class, array $values = [], bool $attach = false): bool
	{
		$link = $this->resource->createLink($class, $values);
		if ($link === null) return false;
		if (!isset($this->part->links)) {
			$this->part->links = new stdClass();
		}
		$this->part->links->$name = $link->toArray();
		return true;
	}

	/**
	 * Attach another resource.
	 *
	 * @param  string $name    the name of the link
	 * @param  string $class   the class of the resource
	 * @param  array  $values  the values in case the resource has uri fields
	 *
	 * Please note that $attach is ignored if link is called from a Resource
	 * that itself is attached by another resource.
	 */
	public function attach(string $name, string $class, array $values = []): void
	{
		if ($this->link($name, $class, $values)) {
			$resource = $this->resource->createAttachedResource($class);
			$previous = $this->part;
			$this->parts->$name = $this->part = new stdClass;
			$resource->call($values);
			$this->part = $previous;
		}
	}

	/**
	 * Get a link to another resource.
	 *
	 * @param  string $class   the class of the resource
	 * @param  array  $values  the values in case the resource has uri fields
	 * @return array  the link
	 */
	public function getLink(string $class, array $values = [], bool $attach = false): array
	{
		$link = $this->resource->createLink($class, $values);
		if ($link === null) return [];
		return $link->toArray();
	}
}

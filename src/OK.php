<?php declare(strict_types=1);

namespace Sturdy\Activity;

final class OK implements Response
{
	private $resource;
	private $parts;
	private $part;

	public function __construct(Resource $resource)
	{
		$this->resource = $resource;
		$this->parts = new stdClass;
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
	 * Get content
	 *
	 * @return string
	 */
	public function getContent(): string
	{
		return json_encode($this->parts, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
	}

	/**
	 * Set the fields of this response.
	 *
	 * @param array $fields
	 * @return self
	 */
	public function setFields(array $fields): void
	{
		$this->part->fields = new stdClass;
		foreach ($fields as $name => $field) {
			if ($field->meta) {
				$this->part->fields->$name = $field;
			} elseif ($field->data) {
				$this->part->fields->$name = $field;
				$this->part->data = $field->value;
				unset($field->value);
			} else {
				if (!isset($this->part->data)) {
					$this->part->data = new stdClass;
				}
				$this->part->fields->$name = $field;
				$this->part->data->$name = $field->value;
			}
		}
	}

	/**
	 * Link to another resource.
	 *
	 * @param  string $name    the name of the link
	 * @param  string $class   the class of the resource
	 * @param  array  $values  the values in case the resource has uri fields
	 * @param  bool   $attach  also attach the resource in the response
	 *
	 * Please note that $attach is ignored if link is called from a Resource
	 * that itself is attached by another resource.
	 */
	public function link(string $name, string $class, array $values = [], bool $attach = false): void
	{
		$link = $this->resource->createLink($class, $values);
		if ($link === null) return;
		$this->part->links->$name = $link->toJson();
		if ($attach && $this->part === $this->parts->main) { // only attach if linking from first resource
			$resource = $this->resource->createAttachedResource($class);
			$previous = $this->part;
			$this->parts->$name = $this->part = new stdClass;
			$resource->call($values);
			$this->part = $previous;
		}
	}

	/**
	 * Convert response using response builder
	 *
	 * @param  ResponseBuilder $rb  the response builder
	 * @return mixed  the response
	 */
	public function convert(ResponseBuilder $rb)
	{
		$responseBuilder->setStatus($this->getStatusCode(), $this->getStatusText());
		$responseBuilder->setContentType('application/json');
		$responseBuilder->setContent($this->getContent());
		return $responseBuilder->getResponse();
	}
}

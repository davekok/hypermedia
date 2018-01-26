<?php declare(strict_types=1);

namespace Sturdy\Activity\Response;

use stdClass;
use Sturdy\Activity\{Resource,Translator};

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
	 * Set hints
	 *
	 * @param ?string $label
	 * @param ?string $icon
	 * @param ?string $section
	 * @param ?string $component
	 * @param ?string $layout
	 * @param string[]|boolean|null $clear
	 */
	public function hints(?string $label, ?string $icon, ?string $section, ?string $component, ?string $layout, $clear): void
	{
		if ($label !== null || $section !== null || $component !== null || $layout !== null) {
			$this->part->hints = new stdClass;
			if ($label !== null) {
				$this->part->hints->label = $label;
			}
			if ($icon !== null) {
				$this->part->hints->icon = $icon;
			}
			if ($section !== null) {
				$this->part->hints->section = $section;
			}
			if ($component !== null) {
				$this->part->hints->component = $component;
			}
			if ($layout !== null) {
				$this->part->hints->layout = $layout;
			}
			if ($clear !== null) {
				$this->part->hints->clear = $clear;
			}
		}
	}

	/**
	 * Set the content of the response
	 * @param stdClass $content  the content
	 */
	public function setContent(stdClass $content): void
	{
		foreach ($content as $key => $value) {
			$this->part->$key = $value;
		}
	}

	/**
	 * Link to another resource.
	 *
	 * @param  string $name    the name of the link
	 * @param  string $label   the label of the link
	 * @param  string $class   the class of the resource
	 * @param  array  $values  the values in case the resource has uri fields
	 * @return bool  link succeeded
	 */
	public function link(string $name, ?string $label, string $class, array $values = [], bool $attach = false): bool
	{
		$link = $this->resource->createLink($class);
		if ($link === null) return false;
		if ($label !== null) $link->setLabel($label, $values);
		if (!isset($this->part->links)) {
			$this->part->links = new stdClass();
		}
		$this->part->links->$name = $link->expand($values);
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
		if ($this->link($name, null, $class, $values)) {
			$resource = $this->resource->createAttachedResource($class);
			$previous = $this->part;
			$this->parts->$name = $this->part = new stdClass;
			$resource->call($values, null);
			$this->part = $previous;
		}
	}

	/**
	 * Request additional resources.
	 *
	 * @param string $linkName
	 * @param string $url
	 */
	public function request(string $linkName, string $url): void
	{
		$this->parts->$linkName = $url;
	}

	/**
	 * Get a link to another resource.
	 *
	 * @param  string $class   the class of the resource
	 * @param  array  $values  the values in case the resource has uri fields
	 * @return object  the link
	 */
	public function getLink(string $class, array ...$values)/*: ?object*/
	{
		$link = $this->resource->createLink($class);
		if (!$link) {
			return null;
		}
		if (count($values)) {
			$values = array_merge(...$values);
		}
		return $link->expand($values, false);
	}
}

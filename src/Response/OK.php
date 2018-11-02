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
	private $stack;
	private $done;

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
		$this->stack = ["main"];
		$this->done = ["main" => false];
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
		$content = json_encode($this->parts, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
		if ($content === false) {
			return json_encode(["error"=>["message"=>"unable to encode response"]]);
		} else {
			return $content;
		}
	}

	/**
	 * Set hints
	 *
	 * @param ?string $label
	 * @param ?string $icon
	 * @param ?string $section
	 * @param ?string $component
	 * @param ?string $layout
	 * @param ?string $variant
	 * @param string[]|boolean|null $clear
	 */
	public function hints(?string $label, ?string $icon, ?string $section, ?string $component, ?string $layout, ?string $variant, $clear): void
	{
		if ($label !== null || $section !== null || $component !== null || $layout !== null || $variant !== null || $clear !== null) {
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
			if ($variant !== null) {
				$this->part->hints->variant = $variant;
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
		$this->done[end($this->stack)] = true;
		foreach ($content as $key => $value) {
			$this->part->$key = $value;
		}
	}

	/**
	 * Whether or not the part is already done.
	 *
	 * @return boolean  is done
	 */
	public function isDone(): bool
	{
		return $this->done[end($this->stack)] ?? false;
	}

	/**
	 * Link to another resource.
	 *
	 * @param  string $name       the name of the link
	 * @param  string $class      the class of the resource
	 * @param  array  $optionals  optional arguments
	 * @return bool  link succeeded
	 *
	 * $optionals = [
	 *   $values => array    used for both link and ICU expansion
	 *   $slot => string     the slot the link is intended for
	 *   $label => string    a ICU expandable message
	 *   $icon => string     a icon identifier
	 *   $selected => bool   whether or not the link is selected
	 *   $target => string   which tab/window to target, see the target attribute of HTML A tag
	 *   $phase => integer   sometimes you wish to differentiate between links, assign a number
	 *                       from 0 to 10 to specify the phase variance of the link
	 * ]
	 */
	public function link(string $name, ?string $class, array $optionals = [], array &$values = null): bool
	{
		$link = $this->resource->createLink($class);
		if ($link === null) return false;
		$link->setName($name);
		if ($values !== null) {
			if (array_key_exists('values', $optionals)) {
				$values = array_merge($optionals['values'], $values);
			}
		} else {
			$values = $optionals['values'] ?? [];
		}
		if (isset($optionals['slot'    ])) $link->setSlot    ($optionals['slot'    ]);
		if (isset($optionals['label'   ])) $link->setLabel   ($optionals['label'   ], $values);
		if (isset($optionals['icon'    ])) $link->setIcon    ($optionals['icon'    ]);
		if (isset($optionals['selected'])) $link->setSelected($optionals['selected']);
		if (isset($optionals['disabled'])) $link->setDisabled($optionals['disabled']);
		if (isset($optionals['target'  ])) $link->setTarget  ($optionals['target'  ]);
		if (isset($optionals['phase'   ])) $link->setPhase   ($optionals['phase'   ]);
		if (!isset($this->part->links)) {
			$this->part->links = [];
		}
		$this->part->links[] = $link->expand($values);
		return true;
	}

	/**
	 * Attach another resource.
	 *
	 * @param  string $name    the name of the link
	 * @param  string $class   the class of the resource
	 * @param  array  $query   the values in case the resource has uri fields
	 *
	 * Please note that $attach is ignored if link is called from a Resource
	 * that itself is attached by another resource.
	 */
	public function attach(string $name, string $class, array $query = []): void
	{
		if ($this->link($name, $class, [], $query)) {
			array_push($this->stack, $name);
			$previous = $this->part;

			$this->parts->$name = $this->part = new stdClass;

			foreach ($query as &$value) {
				if ($value instanceof \JsonSerializable) {
					$value = $value->jsonSerialize();
				} else {
					$value = (string)$value;
				}
			}

			$this->resource
				->createAttachedResource($class, $name === "main")
				->call([], $query, null);

			$this->part = $previous;
			array_pop($this->stack);
		}
	}


	public function attachList(string $name, array $list, string $valueType = "uuid", string $labelType = "string")
	{
		$obj = new stdClass;
		$obj->fields = [["name"=>"list", "type"=>"object", "array"=>true, "data"=>true, "defaultValue"=>[], "fields"=>[
			["name"=>"value", "type"=>"$valueType"],
			["name"=>"label", "type"=>"$labelType"],
		]]];
		$obj->data = $list;
		$this->parts->$name = $obj;
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


	/**
	 * Notify the user with response text
	 * Type can be 'error', 'warning', 'info', 'success'
	 *
	 * @param string $type
	 * @param string $title
	 * @param string $text
	 * @param string|null $icon
	 */
	public function notifyUser(string $type, string $title, string $text, string $icon = null) {
		$this->part->notifications[] = ['type' => $type, 'title' => ucfirst($title), 'text' => ucfirst($text), 'icon' => $icon];
	}

	/**
	 * Log a message to the current part
	 *
	 * @param $data  data to log
	 */
	public function log(...$data): void
	{
		foreach ($data as $item) {
			if (is_scalar($item) || is_array($item) || $item instanceof \stdClass) {
				$this->part->log[] = $item;
			} else if (is_object($item)) {
				if (method_exists($item, "__toString")) {
					$this->part->log[] = $item->__toString();
				} else if (method_exists($item, "toString")) {
					$this->part->log[] = $item->toString();
				} else if (method_exists($item, "jsonSerialize")) {
					$this->part->log[] = $item->jsonSerialize();
				} else {
					$this->part->log[] = get_class($item);
				}
			} else {
				$this->part->log[] = print_r($item, true);
			}
		}
	}
}

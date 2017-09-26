<?php declare(strict_types=1);

namespace Sturdy\Activity;

use stdClass;
use Sturdy\Activity\Meta\{
	CacheItem_Resource,
	Field,
	FieldFlags
};
use Sturdy\Activity\Meta\Type\Type;
use Sturdy\Activity\Response\{
	Accepted,
	BadRequest,
	Created,
	FileNotFound,
	InternalServerError,
	MethodNotAllowed,
	NoContent,
	OK,
	Response,
	UnsupportedMediaType
};

final class Resource
{
	private $cache;
	private $sourceUnit;
	private $tags;
	private $basePath;
	private $branch;
	private $di;

	private $response;

	private $fields;
	private $object;
	private $method;
	private $status;
	private $location;
	private $self;

	public function __construct(Cache $cache, string $sourceUnit, array $tags, string $basePath, JournalBranch $branch, $di)
	{
		$this->cache = $cache;
		$this->sourceUnit = $sourceUnit;
		$this->tags = $tags;
		$this->basePath = $basePath;
		$this->branch = $branch;
		$this->di = $di;
	}

	public function createRootResource(string $verb): self
	{
		if ($verb !== "GET" && $verb !== "POST") {
			throw new MethodNotAllowed("$verb not allowed.");
		}
		$self = new self($this->cache, $this->sourceUnit, $this->tags, $this->basePath, $this->branch, $this->di);
		$resource = $self->cache->getRootResource($self->sourceUnit, $self->tags);
		if ($resource === null) {
			throw new FileNotFound("Root resource not found.");
		}
		$self->initResource($resource, $verb);
		$self->initResponse();
		return $self;
	}

	public function createResource(string $class, string $verb): self
	{
		if ($verb !== "GET" && $verb !== "POST") {
			throw new MethodNotAllowed("$verb not allowed.");
		}
		$self = new self($this->cache, $this->sourceUnit, $this->tags, $this->basePath, $this->branch, $this->di);
		$resource = $self->cache->getResource($self->sourceUnit, $class, $self->tags);
		if ($resource === null) {
			throw new FileNotFound("Resource $class not found.");
		}
		$self->initResource($resource, $verb);
		$self->initResponse();
		return $self;
	}

	public function createAttachedResource(string $class): self
	{
		$self = new self($this->cache, $this->sourceUnit, $this->tags, $this->basePath, $this->branch, $this->di);
		$resource = $self->cache->getResource($self->sourceUnit, $class, $self->tags);
		if ($resource === null) {
			throw new FileNotFound("Resource $class not found.");
		}
		$self->initResource($resource, "GET");
		if ($self->status !== Meta\Verb::OK) {
			throw new InternalServerError("Attached resources must return an OK status code.");
		}
		$self->response = $this->response;
		return $self;
	}

	private function initResource(CacheItem_Resource $resource, string $verb): void
	{
		$class = $resource->getClass();
		$this->fields = $resource->getFields()??[];
		$this->object = new $class;
		[$this->method, $this->status, $this->location, $this->self] = $resource->getVerb($verb);
	}

	private function initResponse(): void
	{
		switch ($this->status) {
			case Meta\Verb::OK:
				$this->response = new OK($this);
				break;

			case Meta\Verb::CREATED:
				$this->response = new Created($this->location);
				break;

			case Meta\Verb::ACCEPTED:
				$this->response = new Accepted();
				break;

			case Meta\Verb::NO_CONTENT:
				$this->response = new NoContent();
				break;

			default:
				throw new InternalServerError("[{$this->class}::{$this->method}] Attached resources must return an OK status code, got {$this->status}.");
		}
	}

	public function call(array $values): Response
	{
		foreach ($this->fields as $name => [$type, $default, $flags, $autocomplete]) {
			// flags check
			$flags = new FieldFlags($flags);
			if ($flags->isRequired() && !isset($values[$name])) {
				throw new BadRequest("$name is required");
			}
			if ($flags->isReadonly() && isset($values[$name])) {
				throw new BadRequest("$name is readonly");
			}
			if ($flags->isDisabled() && isset($values[$name])) {
				throw new BadRequest("$name is disabled");
			}
			// type check
			if (isset($values[$name])) {
				$type = Type::createType($type);
				if ($flags->isArray()) {
					foreach ($values[$name] as $value) {
						if (!$type->filter($value)) {
							throw new BadRequest("$name does not have a valid value: {$value}");
						}
					}
				} elseif ($flags->isMultiple()) {
					foreach (explode(",", $values[$name]) as $value) {
						$value = trim($value);
						if (!$type->filter($value)) {
							throw new BadRequest("$name does not have a valid value: {$value}");
						}
					}
				} else {
					if (!$type->filter($values[$name])) {
						throw new BadRequest("$name does not have a valid value: {$values[$name]}");
					}
				}
			}
			$this->object->$name = $values[$name] ?? null;
		}
		$this->object->{$this->method}($this->response, $this->di);
		$this->branch->addEntry($this->object, $this->method, $this->response->getStatusCode(), $this->response->getStatusText());
		if ($this->self && $this->status === Meta\Verb::OK) {
			$fields = [];
			foreach ($this->fields as $name => [$type, $defaultValue, $flags, $autocomplete]) {
				$field = new stdClass;
				if (isset($this->object->$name)) $field->value = $this->object->$name;
				Type::createType($type)->meta($field);
				if ($defaultValue !== null) $field->defaultValue = $defaultValue;
				$flags = new FieldFlags($flags);
				if ($flags->isRequired()) $field->required = true;
				if ($flags->isReadonly()) $field->readonly = true;
				if ($flags->isDisabled()) $field->disabled = true;
				if ($flags->isMultiple()) $field->multiple = true;
				if ($flags->isArray()) $field->{"array"} = true;
				if ($flags->isMeta()) $field->meta = true;
				if ($flags->isData()) $field->data = true;
				if ($autocomplete) $field->autocomplete = $autocomplete;
				$fields[$name] = $field;
				$this->response->setFields($fields);
			}
		}
		return $this->response;
	}

	/**
	 * Create a link to be used inside the data section.
	 *
	 * @param  string $class           the class of the resource
	 * @param  array  $values          the values in case the resource has meta fields
	 * @param  bool   $mayBeTemplated  whether the like may be a templated link
	 * @return Link                    containing the href property and possibly the templated property
	 */
	public function createLink(string $class, array $values = [], bool $mayBeTemplated = true): ?Link
	{
		$resource = $this->cache->getResource($this->sourceUnit, $class, $this->tags);
		if ($resource === null) {
			return null;
		}
		$fields = $resource->getFields()??[];
		$href = $this->basePath . "/" . strtr($class, "\\", "/");
		$known = "";
		$unknown = "";
		foreach ($fields as $name => $field) {
			if ($field->meta) {
				$flags = new Flags($field->flags);
				if ($flags->isReadonly() || $flags->isDisabled()) continue;
				if (array_key_exists($name, $values)) {
					$known.= "&" . $name . "=" . $values[$name];
				} elseif ($mayBeTemplated) {
					$unknown.= "," . $name;
				} elseif ($flags->isRequired()) {
					throw new InternalServerError("Attempted to create link to $class but required field $name is missing.");
				}
			}
		}
		if ($known) {
			$known[0] = "?";
			$href.= $known;
			if ($unknown) {
				$unknown[0] = "&";
				$href.= "{" . $unknown . "}";
			}
		} elseif ($unknown) {
			$unknown[0] = "?";
			$href.= "{" . $unknown . "}";
		}
		return new Link($href, !empty($unknown));
	}
}

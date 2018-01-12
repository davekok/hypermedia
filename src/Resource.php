<?php declare(strict_types=1);

namespace Sturdy\Activity;

use stdClass;
use Sturdy\Activity\Meta\{
	CacheItem_Resource,
	Field,
	FieldFlags
};
use Sturdy\Activity\Meta\Type\{
	Type,
	ObjectType
};
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
	SeeOther,
	UnsupportedMediaType
};

final class Resource
{
	private $cache;
	private $translator;
	private $sourceUnit;
	private $tags;
	private $basePath;
	private $di;

	private $response;

	private $verb;
	private $conditions;
	private $hints;
	private $fields;
	private $class;
	private $object;
	private $method;
	private $verbflags;

	public function __construct(Cache $cache, Translator $translator, string $sourceUnit, array $tags, string $basePath, string $namespace, $di)
	{
		$this->cache = $cache;
		$this->translator = $translator;
		$this->sourceUnit = $sourceUnit;
		$this->tags = $tags;
		$this->basePath = $basePath;
		$this->namespace = $namespace;
		$this->di = $di;
	}

	/**
	 * Create a link to be used inside the data section.
	 *
	 * @param  string $class  the class of the resource
	 * @return ?Link          containing the href property and possibly the templated property
	 */
	public function createLink(string $class): ?Link
	{
		$resource = $this->cache->getResource($this->sourceUnit, $class, $this->tags);
		return $resource ? new Link($this->translator, $this->basePath, $this->namespace, $resource) : null;
	}

	public function createRootResource(string $verb, array $conditions): self
	{
		if ($verb !== "GET" && $verb !== "POST") {
			throw new MethodNotAllowed("$verb not allowed.");
		}
		$self = new self($this->cache, $this->translator, $this->sourceUnit, array_merge($conditions, $this->tags), $this->basePath, $this->namespace, $this->di);
		$resource = $self->cache->getRootResource($self->sourceUnit, $self->tags);
		if ($resource === null) {
			throw new FileNotFound("Root resource not found.");
		}
		$self->initResource($resource, $verb, $conditions);
		$self->initResponse();
		return $self;
	}

	public function createResource(string $class, string $verb, array $conditions): self
	{
		if ($verb !== "GET" && $verb !== "POST") {
			throw new MethodNotAllowed("$verb not allowed.");
		}
		$self = new self($this->cache, $this->translator, $this->sourceUnit, array_merge($conditions, $this->tags), $this->basePath, $this->namespace, $this->di);
		$resource = $self->cache->getResource($self->sourceUnit, $class, $self->tags);
		if ($resource === null) {
			throw new FileNotFound("Resource $class not found.");
		}
		$self->initResource($resource, $verb, $conditions);
		$self->initResponse();
		return $self;
	}

	public function createAttachedResource(string $class): self
	{
		$self = new self($this->cache, $this->translator, $this->sourceUnit, $this->tags, $this->basePath, $this->namespace, $this->di);
		$resource = $self->cache->getResource($self->sourceUnit, $class, $self->tags);
		if ($resource === null) {
			throw new FileNotFound("Resource $class not found.");
		}
		$self->initResource($resource, "GET", []);
		if ($self->verbflags->getStatus() !== Meta\Verb::OK) {
			throw new InternalServerError("Attached resources must return an OK status code.");
		}
		$self->response = $this->response;
		return $self;
	}

	private function initResource(CacheItem_Resource $resource, string $verb, array $conditions): void
	{
		$this->class = $resource->getClass();
		$this->verb = $verb;
		$this->conditions = $conditions;
		$this->hints = $resource->getHints();
		$this->fields = $resource->getFields() ?? [];
		$this->object = new $this->class;
		[$this->method, $this->verbflags] = $resource->getVerb($verb);
		$this->verbflags = new Meta\VerbFlags($this->verbflags);
	}

	private function initResponse(): void
	{
		switch ($this->verbflags->getStatus()) {
			case Meta\Verb::OK:
				$this->response = new OK($this);
				break;

			case Meta\Verb::NO_CONTENT:
				$this->response = new NoContent();
				break;

			case Meta\Verb::SEE_OTHER:
				$this->response = new SeeOther($this);
				break;

			default:
				throw new InternalServerError("[{$this->class}::{$method}] Unkown status code.");
		}
	}

	public function getObject()/*: object*/
	{
		return $this->object;
	}

	public function getMethod(): string
	{
		return $this->method;
	}

	public function call(array $values, ?array $preserve): Response
	{
		$badRequest = new BadRequest();
		$badRequest->setResource($this->class);
		$this->checkFields($this->fields, $this->object, $values, $badRequest);
		if ($badRequest->hasMessages()) {
			throw $badRequest;
		}

		$this->object->{$this->method}($this->response, $this->di);

		if ($this->verbflags->hasFields() && $this->response instanceof OK) {
			$this->reinit();

			$translatorParameters = get_object_vars($this->object);
			foreach ($translatorParameters as $key => $value) {
				if (!is_scalar($value)) {
					unset($translatorParameters[$key]);
				}
			}
			if (isset($this->hints[0])) {
				$this->hints[0] = ($this->translator)($this->hints[0], $translatorParameters);
			}
			$this->response->hints(...$this->hints);

			$content = new stdClass;
			$state = $this->recurseFields($this->object, $content, $this->fields, $translatorParameters, $preserve);
			$this->response->setContent($content);
			if ($this->verbflags->hasSelfLink()) {
				$this->response->link("self", null, $this->class, $state);
			}
		}

		return $this->response;
	}

	private function checkFields(array $fieldDescriptors, /*object*/ $object, array $values, BadRequest $badRequest, string $prefix = "")
	{
		foreach ($fieldDescriptors as [$name, $type, $defaultValue, $flags, $autocomplete, $label, $icon]) {
			// flags check
			$flags = new FieldFlags($flags);
			if ($flags->isRequired() && !isset($values[$name]) && ($flags->isMeta() || $flags->isState() || $this->verb === "POST")) {
				$badRequest->addMessage("$prefix$name is required");
			}
			if ($flags->isReadonly() && isset($values[$name])) {
				$badRequest->addMessage("$prefix$name is readonly");
			}
			if ($flags->isDisabled() && isset($values[$name])) {
				$badRequest->addMessage("$prefix$name is disabled");
			}
			// type check
			if (isset($values[$name])) {
				$type = Type::createType($type);
				if ($type instanceof ObjectType) {
					if ($flags->isArray()) {
						if (is_array($values[$name])) {
							$object->$name = [];
							$l = count($values[$name]);
							for ($i = 0; $i < $l; ++$i) {
								$object->$name[$i] = $subobject = new stdClass;
								$this->checkFields($type->getFieldDescriptors(), $subobject, $values[$name][$i], $badRequest, "$prefix$name\[$i\].");
							}
						} else {
							$badRequest->addMessage("Expected type of $prefix$name is array, " . gettype($values[$name]) . " found.");
						}
					} else {
						$object->$name = new stdClass;
						$this->checkFields($type->getFieldDescriptors(), $object->$name, $values[$name], $badRequest, "$prefix$name.");
					}
					continue;
				} elseif ($flags->isArray()) {
					if (is_array($values[$name])) {
						foreach ($values[$name] as $value) {
							if (!$type->filter($value)) {
								$badRequest->addMessage("$prefix$name does not have a valid value: {$value}");
							}
						}
					} else {
						$badRequest->addMessage("Expected type of $prefix$name is array, " . gettype($values[$name]) . " found.");
					}
				} elseif ($flags->isMultiple()) {
					foreach (explode(",", $values[$name]) as $value) {
						$value = trim($value);
						if (!$type->filter($value)) {
							$badRequest->addMessage("$prefix$name does not have a valid value: {$value}");
						}
					}
				} else {
					if (!$type->filter($values[$name])) {
						$badRequest->addMessage("$prefix$name does not have a valid value: ".print_r($values[$name],true));
					}
				}
			}
			$object->$name = $values[$name] ?? null;
		}
	}

	/**
	 * Recurse field to configure a response.
	 *
	 * @param  array  $fieldDescriptors      the field descriptors
	 * @param  array  $translatorParameters  translation parameters
	 * @param  array  $preserve              preserve values
	 * @return $state
	 */
	private function recurseFields($source, stdClass $dest, array $fieldDescriptors, array $translatorParameters, ?array $preserve): array
	{
		$state = [];
		foreach ($fieldDescriptors as [$name, $type, $defaultValue, $flags, $autocomplete, $label, $icon]) {
			$flags = new FieldFlags($flags);
			if ($flags->isState()) {
				if (isset($source->$name)) {
					$state[$name] = $source->$name;
				}
			} else {
				$field = new stdClass;
				$field->name = $name;
				if ($label) {
					$field->label = ($this->translator)($label, $translatorParameters);
				}
				if ($icon) {
					$field->icon = $icon;
				}
				if ($defaultValue !== null) {
					$field->defaultValue = $defaultValue;
				}
				if ($autocomplete) {
					$field->autocomplete = $autocomplete;
				}
				$flags->meta($field);
				$type = Type::createType($type);
				$type->meta($field);
				if ($type instanceof ObjectType) {
					$subdest = new stdClass;
					$substate = $this->recurseFields($source->$name ?? ($flags->isArray() ? [] : new stdClass), $subdest, $type->getFieldDescriptors(), $translatorParameters, $preserve[$name] ?? null);
					if (isset($subdest->fields)) {
						$field->fields = $subdest->fields;
					}
					if (isset($subdest->meta)) {
						if (!isset($dest->meta)) $dest->meta = new stdClass;
						$dest->meta->$name = $subdest->meta;
					}
					if (isset($subdest->data)) {
						if ($flags->isData()) {
							$dest->data = $subdest->data;
						} else {
							if (!isset($dest->data)) $dest->data = new stdClass;
							$dest->data->$name = $subdest->data;
						}
					}
					if ($substate) {
						$state[$name] = $substate;
					}
				} elseif (!is_array($source)) {
					if ($flags->isMeta()) {
						if (!isset($dest->meta)) $dest->meta = new stdClass;
						$dest->meta->$name = $preserve[$name] ?? $source->$name ?? null;
					} elseif ($flags->isData() && !isset($dest->data)) {
						$dest->data = $preserve[$name] ?? $source->$name ?? null;
					} else {
						if (!isset($dest->data)) $dest->data = new stdClass;
						$dest->data->$name = $preserve[$name] ?? $source->$name ?? null;
					}
				}
				$dest->fields[] = $field;
			}
		}
		if (is_array($source)) {
			$dest->data = $source;
		}
		return $state;
	}

	/**
	 * Reinit the resource the resource in case recon fields are found.
	 */
	private function reinit(): void
	{
		$conditions = $this->reinitRecurse($this->fields, [], $this->object);
		$resource = $this->cache->getResource($this->sourceUnit, $this->class, array_merge($conditions, $this->tags));
		if ($resource !== null) {
			$this->class = $resource->getClass();
			$this->hints = $resource->getHints();
			$this->fields = $resource->getFields()??[];
			[$this->method, $this->verbflags] = $resource->getVerb($this->verb);
			$this->verbflags = new Meta\VerbFlags($this->verbflags);
		}
	}

	private function reinitRecurse(array $fieldDescriptors, array $conditions, /*object*/ $object, string $prefix = ""): array
	{
		foreach ($fieldDescriptors as [$name, $type, $defaultValue, $flags, $autocomplete, $label, $icon]) {
			$flags = new FieldFlags($flags);
			if ($flags->isRecon() && !(isset($conditions[$name]) && $conditions[$name] === $object->$name)) {
				$conditions[$prefix.$name] = $object->$name;
			}
			$type = Type::createType($type);
			if ($type instanceof ObjectType && isset($object->$name)) {
				if (is_object($object->$name)) {
					$conditions = $this->reinitRecurse($type->getFieldDescriptors(), $conditions, $object->$name, $name."_");
				}
			}
		}
		return $conditions;
	}
}

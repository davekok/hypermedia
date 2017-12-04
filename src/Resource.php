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
	private $hints;
	private $fields;
	private $class;
	private $object;
	private $method;
	private $verbflags;

	public function __construct(Cache $cache, Translator $translator, string $sourceUnit, array $tags, string $basePath, $di)
	{
		$this->cache = $cache;
		$this->translator = $translator;
		$this->sourceUnit = $sourceUnit;
		$this->tags = $tags;
		$this->basePath = $basePath;
		$this->di = $di;
	}

	public function createRootResource(string $verb): self
	{
		if ($verb !== "GET" && $verb !== "POST") {
			throw new MethodNotAllowed("$verb not allowed.");
		}
		$self = new self($this->cache, $this->translator, $this->sourceUnit, $this->tags, $this->basePath, $this->di);
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
		$self = new self($this->cache, $this->translator, $this->sourceUnit, $this->tags, $this->basePath, $this->di);
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
		$self = new self($this->cache, $this->translator, $this->sourceUnit, $this->tags, $this->basePath, $this->di);
		$resource = $self->cache->getResource($self->sourceUnit, $class, $self->tags);
		if ($resource === null) {
			throw new FileNotFound("Resource $class not found.");
		}
		$self->initResource($resource, "GET");
		if ($self->verbflags->getStatus() !== Meta\Verb::OK) {
			throw new InternalServerError("Attached resources must return an OK status code.");
		}
		$self->response = $this->response;
		return $self;
	}

	private function initResource(CacheItem_Resource $resource, string $verb): void
	{
		$this->class = $resource->getClass();
		$this->verb = $verb;
		$this->hints = $resource->getHints();
		$this->fields = $resource->getFields()??[];
		$this->object = new $this->class;
		[$this->method, $this->verbflags] = $resource->getVerb($verb);
		$this->verbflags = new Meta\VerbFlags($this->verbflags);
	}

	private function initResponse(): void
	{
		switch ($this->verbflags->getStatus()) {
			case Meta\Verb::OK:
				$this->response = new OK($this, $this->translator);
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

	public function call(array $values): Response
	{
		$badRequest = new BadRequest();
		$badRequest->setResource($this->class);
		foreach ($this->fields as [$name, $type, $default, $flags, $autocomplete]) {
			// flags check
			$flags = new FieldFlags($flags);
			if ($flags->isRequired() && !isset($values[$name]) && ($flags->isMeta() || $flags->isState() || $this->verb === "POST")) {
				$badRequest->addMessage("$name is required");
			}
			if ($flags->isReadonly() && isset($values[$name])) {
				$badRequest->addMessage("$name is readonly");
			}
			if ($flags->isDisabled() && isset($values[$name])) {
				$badRequest->addMessage("$name is disabled");
			}
			// type check
			if (isset($values[$name])) {
				$type = Type::createType($type);
				if ($flags->isArray()) {
					if (is_array($values[$name])) {
						foreach ($values[$name] as $value) {
							if (!$type->filter($value)) {
								$badRequest->addMessage("$name does not have a valid value: {$value}");
							}
						}
					} else {
						$badRequest->addMessage("Expected type of $name is array, " . gettype($values[$name]) . " found.");
					}
				} elseif ($flags->isMultiple()) {
					foreach (explode(",", $values[$name]) as $value) {
						$value = trim($value);
						if (!$type->filter($value)) {
							$badRequest->addMessage("$name does not have a valid value: {$value}");
						}
					}
				} else {
					if (!$type->filter($values[$name])) {
						$badRequest->addMessage("$name does not have a valid value: {$values[$name]}");
					}
				}
			}
			$this->object->$name = $values[$name] ?? null;
		}

		if ($badRequest->hasMessages()) {
			throw $badRequest;
		}

		$this->object->{$this->method}($this->response, $this->di);

		if ($this->verbflags->hasFields() && $this->response instanceof OK) {
			$parameters = get_object_vars($this->object);
			foreach ($parameters as $key => $parameter) {
				if (!is_scalar($parameter)) {
					unset($parameters[$key]);
				}
			}
			if (isset($this->hints[0])) {
				$this->hints[0] = ($this->translator)($this->hints[0], $parameters);
			}
			$this->response->hints(...$this->hints);
			$fields = [];
			$state = [];
			foreach ($this->fields as [$name, $type, $defaultValue, $flags, $autocomplete, $label]) {
				$flags = new FieldFlags($flags);
				if ($flags->isState()) {
					if (isset($this->object->$name)) {
						$state[$name] = $this->object->$name;
					}
				} else {
					$field = new stdClass;
					$field->name = $name;
					if (isset($label)) {
						$field->label = $label;
					}
					if (isset($this->object->$name)) $field->value = $this->object->$name;
					Type::createType($type)->meta($field);
					if ($defaultValue !== null) $field->defaultValue = $defaultValue;
					$flags->meta($field);
					if ($autocomplete) $field->autocomplete = $autocomplete;
					$fields[] = $field;
					$recursiveTranslate = function($field)use(&$recursiveTranslate, $parameters) {
						if (isset($field->label)) {
							$field->label = ($this->translator)($field->label, $parameters);
						}
						if (isset($field->fields)) {
							foreach ($field->fields as $field) {
								$recursiveTranslate($field);
							}
						}
					};
					$recursiveTranslate($field);
				}
			}
			if ($this->verbflags->hasSelfLink()) {
				$this->response->link("self", null, $this->class, $state);
			}
			if (!empty($fields)) {
				$this->response->fields($fields, $this->verbflags->hasData());
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
		$href = rtrim($this->basePath, "/") . "/" . trim(strtr($class, "\\", "/"), "/");
		$known = "";
		$unknown = "";
		foreach ($fields as [$name, $type, $defaultValue, $flags, $autocomplete]) {
			$flags = new FieldFlags($flags);
			if ($flags->isMeta()) {
				if ($flags->isReadonly() || $flags->isDisabled()) continue;
				if (array_key_exists($name, $values)) {
					$known.= "&" . $name . "=" . urlencode((string)$values[$name]);
				} elseif ($mayBeTemplated) {
					$unknown.= "," . $name;
				} elseif ($flags->isRequired()) {
					throw new InternalServerError("Attempted to create link to $class but required field $name is missing.");
				}
			} elseif ($flags->isState()) {
				if (array_key_exists($name, $values)) {
					$known.= "&" . $name . "=" . urlencode((string)$values[$name]);
				} elseif (!$mayBeTemplated && $flags->isRequired()) {
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

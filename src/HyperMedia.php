<?php declare(strict_types=1);

namespace Sturdy\Activity;

use Throwable;
use Exception;
use Generator;
use stdClass;
use InvalidArgumentException;
use Sturdy\Activity\Meta\FieldFlags;

/**
 * A hyper media middle ware for your resources.
 */
final class HyperMedia
{
	// dependencies/configuration
	private $cache;
	private $sourceUnit;
	private $basePath;
	private $di;

	// state
	private $tags;
	private $response;
	private $responses;
	private $status;
	private $content;

	/**
	 * Constructor
	 *
	 * @param Cache             $cache              the cache provider
	 * @param JournalRepository $journalRepository  the journal repository
	 * @param string            $sourceUnit         the source unit to use
	 * @param string            $basePath           the prefix to remove from the path before processing
	 *                                              and appended for generating links
	 * @param object            $di                 your dependency injection object, should contain all
	 *                                              dependencies for your actions
	 */
	public function __construct(
		Cache $cache,
		JournalRepository $journalRepository,
		string $sourceUnit,
		string $basePath,
		/*object*/ $di)
	{
		$this->cache = $cache;
		$this->journalRepository = $journalRepository;
		$this->sourceUnit = $sourceUnit;
		$this->basePath = $basePath;
		$this->di = $di;
	}

	/**
	 * Create an activity
	 *
	 * @param  string $class  the activity class
	 */
	public function createActivity(string $class): Activity
	{
		$activity = new Activity($this->cache, $this->journalRepository, $this->sourceUnit);
		$activity->load($tags);
		return $activity;
	}

	/**
	 * Process a request
	 *
	 * This function supports varies types for the request and response arguments.
	 * For the request argument you can choose between:
	 * - \Psr\Http\Message\ServerRequestInterface
	 * - \Symfony\Component\HttpFoundation\Request
	 * - stdClass
	 * - array
	 *
	 * For the response argument you can choose between:
	 * - \Psr\Http\Message\ResponseInterface
	 * - \Symfony\Component\HttpFoundation\Response
	 * - stdClass
	 * - array
	 *
	 * This should allow you to integrate this library with most frameworks.
	 *
	 * @param HyperMedia_Request         $request          the request object
	 * @param HyperMedia_ResponseBuilder $responseBuilder  the response builder object
	 * @param array                      $tags             the tags to use
	 * @return mixed  $responseBuilder->getResponse()
	 *
	 * Expected properties for stdClass instance and array:
	 * - method       the HTTP method/verb
	 * - path         the path part of URI, as a string
	 * - query        the query part of URI, as a string
	 * - content      in case of POST, the body of the HTTP message
	 * - contentType  the Content-Type header, must equal application/json
	 *
	 * Properties returned in the response in case of stdClass instance or arry:
	 * - status       the HTTP status code
	 * - location     the Location header, optional
	 * - contentType  the Content-Type header, optional
	 * - content      the content, optional
	 */
	public function process(Request $request, array $tags): Response
	{
		$this->responses = new stdClass;
		$this->responses->main = $this->response = new stdClass;
		$this->tags = $tags;

		try {

			$verb = $request->getVerb();
			$path = substr($request->getPath(), strlen($this->basePath));
			if ($path === "" || $path === "/") { // if root resource
				if ($verb !== "GET") {
					throw new MethodNotAllowed("$verb not allowed.");
				}
				$resource = $this->cache->getRootResource($this->sourceUnit, $this->tags);
				if ($resource === null) {
					throw new FileNotFound("Root resource not found.");
				}
				$values = [];
			} else { // if normal resource
				switch ($verb) {
					case "GET":
					case "POST":
						break;
					default:
						throw new MethodNotAllowed("$verb not allowed.");
				}
				$class = strtr($path, "/", "\\");
				$resource = $this->cache->getResource($this->sourceUnit, $class, $this->tags);
				if ($resource === null) {
					throw new FileNotFound("Resource $class not found.");
				}
				$values = $this->getValues($request);
			}
			unset($request, $class, $path);

			// call resource
			$this->call($resource, $verb, $values);

			return $this->response;
		} catch (Error $e) {
			return $this->response;
		} catch (Throwable $e) {
			return (new InternalServerError("Uncaught exception", 0, $e));
		}
	}

	private function getValues(HyperMedia_Request $request): array
	{
		if ($request->getVerb() === "POST") {
			if ($request->getContentType() === "application/json") {
				$content = json_decode($request->getContent() ?? "", true);
				if (!is_array($content)) {
					throw new BadRequest("The content is not valid JSON.");
				}
			} else {
				throw new UnsupportedMediaType("Expected media type 'application/json'.");
			}
		}
		$query = $request->getQuery();
		if ($query[0] === "?") $query = substr($query, 1);
		if (!empty($query)) {
			$values = [];
			parse_str($query, $values);
			if ($content) {
				$values = array_merge($values, $content);
			}
		} else {
			$values = $content;
		}

		if (isset($values['journalId'])) {
			$this->journalId = (int)$values['journalId'];
			unset($values['journalId']);
		}

		return $values;
	}

	private function build(HyperMedia_ResponseBuilder $responseBuilder, Response $status, $content)
	{
		$responseBuilder->setStatus($status->getStatusCode(), $status->getStatusText());
		if ($status instanceof Created) {
			$responseBuilder->setLocation($status->getLocation());
		}
		if ($content !== null) {
			$responseBuilder->setContentType('application/json');
			$responseBuilder->setContent(json_encode($content, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
		}
		return $responseBuilder->response();
	}

	private function buildError(HyperMedia_ResponseBuilder $responseBuilder, Throwable $e)
	{
		$error = ["message"=>$e->getMessage()];
		$code = $e->getCode();
		if ($code > 0) {
			$error["code"] = $code;
		}
		if ($e instanceof Status) {
			return $this->build($responseBuilder, $e, null, ["error"=>$error]);
		} else {
			return $this->build($responseBuilder, new InternalServerError, null, ["error"=>$error]);
		}
	}

	/**
	 * Call a request
	 *
	 * @param CacheItem_Resource $resource  resource
	 * @param string             $verb      the HTTP verb
	 * @param array              $values    the input fields, from both the URI and the body
	 */
	private function call(CacheItem_Resource $resource, string $verb, array $values): void
	{
		$class = $resource->getClass();
		$fields = $resource->getFields();
		[$method, $status, $location, $self] = $resource->getVerb($verb);
		switch ($status) {
			case Meta\Verb::OK:
				if ($this->responses->main === $this->response) {
					$this->status = new OK;
				}
				$capture = $self;
				break;

			case Meta\Verb::CREATED:
				if ($this->responses->main !== $this->response) {
					throw new InternalServerError("[$class::$method] Method has an invalid status code, $status.");
				}
				$this->status = new Created($location);
				$capture = false;
				break;

			case Meta\Verb::ACCEPTED:
				if ($this->responses->main !== $this->response) {
					throw new InternalServerError("[$class::$method] Method has an invalid status code, $status.");
				}
				$this->status = new Accepted;
				$capture = false;
				break;

			case Meta\Verb::NO_CONTENT:
				if ($this->responses->main !== $this->response) {
					throw new InternalServerError("[$class::$method] Method has an invalid status code, $status.");
				}
				$this->status = new NoContent;
				$capture = false;
				break;

			default:
				throw new InternalServerError("[$class::$method] Attached resources must return an OK status code, got $status.");
		}

		$obj = new $class;
		foreach ($fields as $name => [$type, $default, $flags, $autocomplete, $validation, $link]) {
			// type check
			$type = new FieldType($type);
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
			// validate
			// check options against link
			$obj->$name = $values[$name] ?? null;
		}
		$this->response->links = [];
		$obj->$method($this, $this->di);
		if ($capture) {
			foreach ($fields as $name => [$type, $default, $flags, $autocomplete, $validation, $link]) {
				$flags = new FieldFlags($flags);
				if ($flags->isReadonly()) {
				}
			}

			foreach ($fields as $name => [$type, $default, $flags, $autocomplete, $validation, $link]) {
				$response->$name = $obj->$name;
			}
		}
	}

	/**
	 * Link to another resource.
	 *
	 * @param string $name    the name of the link
	 * @param string $class   the class of the resource
	 * @param array  $values  the values in case the resource has uri fields
	 * @param bool   $attach  also attach the resource in the responce
	 *
	 * Please note that $attach is ignored if link is called from a Resource
	 * that itself is attached by another resource.
	 */
	public function link(string $name, string $class, array $values = [], bool $attach = false): void
	{
		$this->resource->links->$name = $this->createLink($class, $values);
		if ($attach && $this->response === $this->responses->main) { // only attach if linking from first resource
			$resource = $this->cache->getResource($this->sourceUnit, $this->tags, $class);
			if ($resource === null) {
				$this->responses->$name = ["error" => ["message" => "Resource $class not found."]];
			} else {
				$previous = $this->response;
				$this->responses->$name = $this->response = new stdClass;
				$this->call($resource, "GET", $values);
				$this->response = $previous;
			}
		}
	}

	/**
	 * Create a link to be used inside the data section.
	 *
	 * @param  string $class           the class of the resource
	 * @param  array  $values          the values in case the resource has uri fields
	 * @param  bool   $mayBeTemplated  whether the like may be a templated link
	 * @return Link                    containing the href property and possibly the templated property
	 */
	public function createLink(string $class, array $values = [], bool $mayBeTemplated = true): Link
	{
		$href = $this->basePath . "/" . strtr($class, "\\", "/");
		$i = 0;
		foreach ($this->resource->fields as $name => $meta) {
			if ($meta->uri && array_key_exists($name, $fields)) {
				$href.= ($i++ ? "&" : "?") . $name . "=" . $fields[$name];
			}
		}
		$j = 0;
		if ($mayBeTemplated) {
			foreach ($this->resource->fields as $name => $meta) {
				if ($meta->uri && !array_key_exists($name, $fields)) {
					$href.= ($j++ ? "," : ($i ? "{&" : "{?")) . $name;
				}
			}
		}
		if ($j) {
			$href.= "}";
			return new Link($href, true);
		} else {
			return new Link($href);
		}
	}
}

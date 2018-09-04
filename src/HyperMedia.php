<?php declare(strict_types=1);

namespace Sturdy\Activity;

use Throwable, Exception, InvalidArgumentException;
use Sturdy\Activity\Request\Request;
use Sturdy\Activity\Response\{
	Response,
	BadRequest,
	MethodNotAllowed,
	InternalServerError,
	UnsupportedMediaType
};

/**
 * A hyper media middle ware for your resources.
 */
final class HyperMedia
{
	// dependencies/configuration
	private $sharedStateStore;
	private $cache;
	private $translator;
	private $jsonDeserializer;
	private $sourceUnit;
	private $basePath;
	private $di;

	/**
	 * Constructor
	 *
	 * @param Cache             $cache              the cache provider
	 * @param Translator        $translator         the translator
	 * @param JsonDeserializer  $jsonDeserializer   the deserializer
	 * @param string            $sourceUnit         the source unit to use
	 * @param string            $basePath           the prefix to remove from the path before processing
	 *                                              and appended for generating links
	 * @param string            $namespace          namespace to remove from class name
	 *                                              dependencies for your actions
	 */
	public function __construct(
		SharedStateStore $sharedStateStore,
		Cache $cache,
		Translator $translator,
		JsonDeserializer $jsonDeserializer,
		string $sourceUnit,
		string $basePath,
		string $namespace)
	{
		$this->sharedStateStore = $sharedStateStore;
		$this->cache = $cache;
		$this->translator = $translator;
		$this->jsonDeserializer = $jsonDeserializer;
		$this->sourceUnit = $sourceUnit;
		$this->basePath = rtrim($basePath, "/") . "/";
		$this->namespace = !empty($namespace) ? (rtrim($namespace, "\\") . "\\") : '';
	}

	/**
	 * Handle a request
	 *
	 * The $request argument can be either an instance of
	 * - \Psr\Http\Message\ServerRequestInterface
	 * - \Symfony\Component\HttpFoundation\Request
	 * - \Sturdy\Activity\Request\Request
	 * or be
	 * - an array in the structure of $_SERVER
	 * - null, in which case $_SERVER should be used
	 *
	 * The $responseAdaptor argument can be either:
	 * - "psr" returns a \Psr\Http\Message\ResponseInterface
	 * - "symfony" returns a \Symfony\Component\HttpFoundation\Response
	 * - "sturdy" returns a \Sturdy\Activity\Response\Response
	 * - "array" returns ["protocolVersion" => string, "statusCode" => int, "statusText" => string, "headers" => [string => string], "content" => ?string]
	 * - "echo" returns void, echo's response to output instead using header and echo functions
	 * - null, a matching response adaptor is choosen based on your request:
	 *   + psr for \Psr\Http\Message\ServerRequestInterface
	 *   + symfony for \Symfony\Component\HttpFoundation\Request
	 *   + sturdy for \Sturdy\Activity\Request\Request
	 *   + array for array
	 *   + echo for null
	 *
	 * @param array    $tags             the tags to use
	 * @param mixed    $request          the request object
	 * @param ?string  $responseAdaptor  the response adaptor you would like to use
	 * @return mixed   a response
	 */
	public function handle(array $tags, $request, ?string $responseAdaptor = null)
	{
		$request = Http::request($request, $responseAdaptor);
		$verb = $request->getVerb();
		$path = $request->getPath();
		$query = $this->getQuery($request);
		if (isset($query['store'])) {
			$this->sharedStateStore->loadPersistentStore($query['store']);
			unset($query['store']);
		}
		switch ($verb) {
			case "GET":
				$values = [];
				$response = $this->call($verb, $path, $values, $query, $tags);
				break;

			case "POST":
				$values = $this->getBody($request);
				$response = $this->call($verb, $path, $values, $query, $tags);
				break;

			case "RECON":
				$verb = "GET";
				$body = $this->getBody($request);
				$conditions = $body['conditions'];
				$values = $body['data'];
				$response = $this->call($verb, $path, $values, $query, $tags, $conditions, $body['data']);
				break;

			case "LOOKUP":
				$verb = "GET";
				$values = $this->getBody($request);
				$response = $this->call($verb, $path, $values, $query, $tags, [], $body);
				break;

			default:
				$response = new MethodNotAllowed();
				break;
		}
		$this->sharedStateStore->closePersistentStore();
		return Http::response($response);
	}

	/**
	 * Call the resource
	 *
	 * @param  string $verb        the verb to use on the resource
	 * @param  string $path        the path of the resouce
	 * @param  array  $values      the input values
	 * @param  array  $tags        tags
	 * @param  array  $conditions  conditions
	 * @param  array  $preserve    preserve field values
	 * @return Response  the response
	 */
	private function call(string $verb, string $path, array $values, array $query, array $tags, array $conditions = [], array $preserve = null): Response
	{
		try {
			$this->sharedStateStore->fill("query", $query);
			$path = substr($path, strlen($this->basePath));
			if ($path === "" || $path === "/") { // if root resource
				$response = (new Resource($this->sharedStateStore, $this->cache, $this->translator, $this->jsonDeserializer, $this->sourceUnit, $tags, $this->basePath, $this->namespace, "", $query))
					->createRootResource($verb, $conditions)
					->call($values, $query, $preserve);
			} else { // if normal resource
				$class = $this->namespace . strtr(trim(str_replace('-','',ucwords($path,'-/')),"/"),"/","\\");
				$response = (new Resource($this->sharedStateStore, $this->cache, $this->translator, $this->jsonDeserializer, $this->sourceUnit, $tags, $this->basePath, $this->namespace, $class, $query))
					->createResource($class, $verb, $conditions)
					->call($values, $query, $preserve);
			}
		} catch (Response $e) {
			$response = $e;
		} catch (Throwable $e) {
			$response = new InternalServerError("Uncaught exception: $verb {$this->basePath}$path" . ($query ? "?".http_build_query($query) : ""), 0, $e);
		}
		return $response;
	}

	/**
	 * Get the body from request.
	 *
	 * @param  Request $request  the request
	 * @return array  the body
	 */
	private function getBody(Request $request): array
	{
		$contentType = $request->getContentType();
		switch (true) {
			case "application/json" === $contentType:
			case "application/sturdy" === $contentType:
				$values = json_decode($request->getContent() ?? "", true);
				if (!is_array($values)) {
					throw new BadRequest("The content is not valid JSON.");
				}
				return $values;

			case null === $contentType:
				return [];

			default:
				throw new UnsupportedMediaType("Expected media type 'application/json', got '" . $request->getContentType() . "'.");
		}
	}

	/**
	 * Get query parameters from request.
	 *
	 * @param  Request $request  the request
	 * @return array   the query parameters
	 */
	private function getQuery(Request $request): array
	{
		$query = $request->getQuery();
		if ($query !== "") {
			if ($query[0] === "?") $query = substr($query, 1);
			parse_str($query, $query);
			foreach ($query as $key => $value) {
				if ($value === "" || $value === null) {
					unset($query[$key]);
				}
			}
			return $query;
		}
		return [];
	}
}

<?php declare(strict_types=1);

namespace Sturdy\Activity;

use Throwable;
use Exception;
use InvalidArgumentException;
use Sturdy\Activity\Response\{
	BadRequest,
	InternalServerError,
	UnsupportedMediaType
};

/**
 * A hyper media middle ware for your resources.
 */
final class HyperMedia
{
	// dependencies/configuration
	private $cache;
	private $journaling;
	private $translator;
	private $sourceUnit;
	private $basePath;
	private $di;

	/**
	 * Constructor
	 *
	 * @param Cache             $cache              the cache provider
	 * @param JournalRepository $journalRepository  the journal repository
	 * @param Translator        $translator         the translator
	 * @param string            $sourceUnit         the source unit to use
	 * @param string            $basePath           the prefix to remove from the path before processing
	 *                                              and appended for generating links
	 * @param string            $namespace          namespace to remove from class name
	 * @param object            $di                 your dependency injection object, should contain all
	 *                                              dependencies for your actions
	 */
	public function __construct(
		Cache $cache,
		JournalRepository $journalRepository,
		Translator $translator,
		string $sourceUnit,
		string $basePath,
		string $namespace,
		/*object*/ $di)
	{
		$this->cache = $cache;
		$this->journaling = new Journaling($journalRepository);
		$this->translator = $translator;
		$this->sourceUnit = $sourceUnit;
		$this->basePath = rtrim($basePath, "/") . "/";
		$this->namespace = rtrim($namespace, "\\") . "\\";
		$this->di = $di;
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
	 * @return mixed   $response
	 */
	public function handle(array $tags, $request, ?string $responseAdaptor = null)
	{
		$request = $this->adaptRequest($request, $responseAdaptor);
		$verb = $request->getVerb();
		$path = $request->getPath();
		switch ($verb) {
			case "GET":
				$values = $this->getQuery($request);
				$response = $this->call($verb, $path, $values, $tags);
				break;

			case "POST":
				$values = array_merge($this->getBody($request), $this->getQuery($request));
				$response = $this->call($verb, $path, $values, $tags);
				break;

			case "RECON":
				$verb = "GET";
				$body = $this->getBody($request);
				$conditions = $body['conditions'];
				$values = array_merge($this->getQuery($request), $body['data']);
				$response = $this->call($verb, $path, $values, $tags, $conditions, $body['data']);
				break;

			default:
				return new MethodNotAllowed();
		}
		$response->setProtocolVersion($request->getProtocolVersion());
		return $this->adaptResponse($response, $responseAdaptor);
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
	 * @return Response\Response   the response
	 */
	private function call(string $verb, string $path, array $values, array $tags, array $conditions = [], array $preserve = null): Response\Response
	{
		try {
			$path = substr($path, strlen($this->basePath));
			if ($path === "" || $path === "/") { // if root resource
				$resource = (new Resource($this->cache, $this->translator, $this->sourceUnit, $tags, $this->basePath, $this->namespace, $this->di))
					->createRootResource($verb, $conditions);
				$response = $resource->call($values, $preserve);
			} else { // if normal resource
				$class = $this->namespace . strtr(trim(str_replace('-','',ucwords($path,'-/')),"/"),"/","\\");
				$resource = (new Resource($this->cache, $this->translator, $this->sourceUnit, $tags, $this->basePath, $this->namespace, $this->di))
					->createResource($class, $verb, $conditions);
				$response = $resource->call($values, $preserve);
			}
		} catch (Response\Response $e) {
			$response = $e;
		} catch (Throwable $e) {
			$response = new InternalServerError("Uncaught exception", 0, $e);
		}
		return $response;
	}

	/**
	 * Get the body from request.
	 *
	 * @param  Request $request  the request
	 * @return array  the body
	 */
	private function getBody(Request\Request $request): array
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
	 * @return array  the query parameters
	 */
	private function getQuery(Request\Request $request): array
	{
		$query = $request->getQuery();
		if ($query !== "") {
			if ($query[0] === "?") $query = substr($query, 1);
			parse_str($query, $query);
			return $query;
		}
		return [];
	}

	/**
	 * Adapt a request to something sturdy understands.
	 *
	 * @param  mixed  $request           the original request
	 * @param  string &$responseAdaptor  if no response adaptor is set, choose a matching one
	 * @return Request\Request           a sturdy request object
	 */
	private function adaptRequest($request, ?string &$responseAdaptor): Request\Request
	{
		// adaptors
		switch (true) {
			case $request instanceof \Psr\Http\Message\ServerRequestInterface:
				$request = new Request\PsrAdaptor($request);
				if ($responseAdaptor === null) {
					$responseAdaptor = "psr";
				}
				break;

			case $request instanceof \Symfony\Component\HttpFoundation\Request:
				$request = new Request\SymfonyAdaptor($request);
				if ($responseAdaptor === null) {
					$responseAdaptor = "symfony";
				}
				break;

			case $request instanceof Request\Request:
				if ($responseAdaptor === null) {
					$responseAdaptor = "sturdy";
				}
				break;

			case is_array($request):
				$request = new Request\ServerAdaptor($request);
				if ($responseAdaptor === null) {
					$responseAdaptor = "array";
				}
				break;

			case $request === null:
				$request = new Request\ServerAdaptor($_SERVER);
				if ($responseAdaptor === null) {
					$responseAdaptor = "echo";
				}
				break;

			default:
				throw new InvalidArgumentException("\$request argument is of unsupported type");
		}
		return $request;
	}

	/**
	 * Adapt a sponse to something the caller understands
	 *
	 * @param  mixed  $response          the original sturdy response
	 * @param  string $responseAdaptor   the response adaptor to use
	 * @return mixed                     the response
	 */
	private function adaptResponse(Response\Response $response, string $responseAdaptor)
	{
		// adaptors
		switch ($responseAdaptor) {
			case "psr":
				return new Response\PsrAdaptor($response);

			case "symfony":
				return new Response\SymfonyAdaptor($response);

			case "sturdy":
				return $response;

			case "array":
				$headers = [];
				$headers["Date"] = $response->getDate()->format("r");
				if ($location = $response->getLocation()) {
					$headers["Location"] = $location;
				}
				if ($contentType = $response->getContentType()) {
					$headers["Content-Type"] = $contentType;
				}
				return [
					"protocolVersion" => $response->getProtocolVersion(),
					"statusCode" => $response->getStatusCode(),
					"statusText" => $response->getStatusText(),
					"headers" => $headers,
					"content" => $response->getContent(),
				];

			case "echo":
				header("HTTP/".$response->getProtocolVersion()." ".$response->getStatusCode()." ".$response->getStatusText());
				header("Date: ".$response->getDate()->format("r"));
				if ($location = $response->getLocation()) {
					header("Location: ".$location);
				}
				if ($contentType = $response->getContentType()) {
					header("Content-Type: ".$contentType);
				}
				if ($content = $response->getContent()) {
					echo $content;
				}
				return;

			default:
				throw new InvalidArgumentException("Unsupported response adaptor $responseAdaptor.");
		}
	}
}

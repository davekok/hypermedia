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
	private $journalRepository;
	private $sourceUnit;
	private $basePath;
	private $di;

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
		$this->basePath = rtrim($basePath, "/");
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
	 * The $responseAdaptor argument can be eiter:
	 * - "psr" returns a \Psr\Http\Message\ResponseInterface
	 * - "symfony" returns a \Symfony\Component\HttpFoundation\Response
	 * - "sturdy" returns a \Sturdy\Activity\Response\Response
	 * - "array" returns [string $protocolVersion, string $statusCode, string $statusText, string[] $headers, ?string $content]
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
	 * @return mixed  $response
	 */
	public function handle(array $tags, $request, ?string $responseAdaptor = null)
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

		// handle
		try {
			$resource = new Resource($this->cache, $this->sourceUnit, $tags, $this->basePath, $this->di);
			$path = substr($request->getPath(), strlen($this->basePath));
			if ($path === "" || $path === "/") { // if root resource
				return $resource
					->createRootResource($request->getVerb())
					->call($this->getValues($request));
			} else { // if normal resource
				if (preg_match("|^/([0-9]+)/|", $path, $matches)) {
					$path = substr($path, strlen($matches[0]));
					$this->journalId = (int)$matches[1];
					$this->basePath.= "/".$this->journalId."/";
				}
				$response = $resource
					->createResource(strtr(trim($path, "/"), "/", "\\"), $request->getVerb())
					->call($this->getValues($request));
			}
		} catch (Response $e) {
			$response = $e;
		} catch (Throwable $e) {
			$response = (new InternalServerError("Uncaught exception", 0, $e));
		}

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
				return [$response->getProtocolVersion(), $response->getStatusCode(), $response->getStatusText(), $headers, $response->getContent()];

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

	/**
	 * Get values from request.
	 *
	 * @param  Request $request  the request
	 * @return array  the values
	 */
	private function getValues(Request $request): array
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

		return $values;
	}
}

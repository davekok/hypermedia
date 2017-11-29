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
	 * @param object            $di                 your dependency injection object, should contain all
	 *                                              dependencies for your actions
	 */
	public function __construct(
		Cache $cache,
		JournalRepository $journalRepository,
		Translator $translator,
		string $sourceUnit,
		string $basePath,
		/*object*/ $di)
	{
		$this->cache = $cache;
		$this->journaling = new Journaling($journalRepository);
		$this->translator = $translator;
		$this->sourceUnit = $sourceUnit;
		$this->basePath = rtrim($basePath, "/");
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
			$path = substr($request->getPath(), strlen($this->basePath));
			if ($path === "" || $path === "/" || preg_match("|^/([0-9]+)/$|", $path, $matches)) { // if root resource
				if (isset($matches[1])) {
					$this->journaling->resume((int)$matches[1]);
				} else {
					$this->journaling->create($this->sourceUnit, Journal::resource, $tags);
				}
				$this->basePath.= "/".$this->journaling->getId()."/";
				$resource = (new Resource($this->cache, $this->translator, $this->sourceUnit, $tags, $this->basePath, $this->di))
					->createRootResource($request->getVerb());
				$response = $resource->call($this->getValues($request));
			} else { // if normal resource
				if (preg_match("|^/([0-9]+)/|", $path, $matches)) {
					$path = substr($path, strlen($matches[0]));
					$this->journaling->resume((int)$matches[1]);
				} else {
					$this->journaling->create($this->sourceUnit, Journal::resource, $tags);
				}
				$this->basePath.= "/".$this->journaling->getId()."/";
				$class = strtr(trim($path, "/"), "/", "\\");
				$resource = (new Resource($this->cache, $this->translator, $this->sourceUnit, $tags, $this->basePath, $this->di))
					->createResource($class, $request->getVerb());
				$response = $resource->call($this->getValues($request));
			}
		} catch (Response\Response $e) {
			$response = $e;
		} catch (Throwable $e) {
			$response = new InternalServerError("Uncaught exception", 0, $e);
		} finally {
			$response->setProtocolVersion($request->getProtocolVersion());
			if (!$this->journaling->hasJournal()) {
				$this->journaling->create($this->sourceUnit, Journal::resource, $tags);
			}
			if (!isset($resource)) {
				$content = $response->getContent();
				if (isset($content)) {
					$content = json_decode($content);
					$this->journaling->getMainBranch()->addEntry($content, "exception", $response->getStatusCode(), $response->getStatusText());
				} else {
					$this->journaling->getMainBranch()->addEntry($response, "exception", $response->getStatusCode(), $response->getStatusText());
				}
			} else {
				$this->journaling->getMainBranch()->addEntry($resource->getObject(), $resource->getMethod(), $response->getStatusCode(), $response->getStatusText());
			}
			$this->journaling->save();
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
				return [
					"protocolVersion" => $response->getProtocolVersion(),
					"statusCode" => $response->getStatusCode(),
					"statusText" => $response->getStatusText(),
					"headers" => $headers,
					"content" => $response->getContent()
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

	/**
	 * Get values from request.
	 *
	 * @param  Request $request  the request
	 * @return array  the values
	 */
	private function getValues(Request\Request $request): array
	{
		$values = [];
		if ($request->getVerb() === "POST") {
			if ($request->getContentType() === "application/json") {
				$values = json_decode($request->getContent() ?? "", true);
				if (!is_array($values)) {
					throw new BadRequest("The content is not valid JSON.");
				}
			} else {
				throw new UnsupportedMediaType("Expected media type 'application/json', got '" . $request->getContentType() . "'.");
			}
		}
		$query = $request->getQuery();
		if ($query !== "") {
			if ($query[0] === "?") $query = substr($query, 1);
			parse_str($query, $query);
			$values = array_merge($query, $values);
		}
		return $values;
	}
}

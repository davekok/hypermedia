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
		try {
			$resource = new Resource($this->cache, $this->sourceUnit, $tags, $this->basePath, $this->di);
			$path = substr($request->getPath(), strlen($this->basePath));
			if ($path === "" || $path === "/") { // if root resource
				return $resource
					->createRootResource($request->getVerb());
					->call($this->getValues($request));
			} else { // if normal resource
				if (preg_match("|^/([0-9]+)/|", $path, $matches)) {
					$path = substr($path, strlen($matches[0]));
					$this->journalId = (int)$matches[1];
					$this->basePath.= "/".$this->journalId."/";
				}
				return $resource
					->createResource(strtr(trim($path, "/"), "/", "\\"), $request->getVerb())
					->call($this->getValues($request));
			}
		} catch (Response $e) {
			return $e;
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

		return $values;
	}
}

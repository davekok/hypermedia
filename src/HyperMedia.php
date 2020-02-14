<?php declare(strict_types=1);

namespace Sturdy\Activity;

use Throwable, Exception, InvalidArgumentException;
use Sturdy\Activity\Request;
use Sturdy\Activity\Response;

/**
 * A hyper media middle ware for your resources.
 */
class HyperMedia
{
	/**
	 * Create an instance
	 *
	 * @param SharedStateStore  $sharedStateStore   shared state store
	 * @param Cache             $cache              the cache provider
	 * @param Translator        $translator         the translator
	 * @param JsonDeserializer  $jsonDeserializer   the deserializer
	 * @param string            $sourceUnit         the source unit to use
	 * @param string            $basePath           the prefix to remove from the path before processing
	 *                                              and appended for generating links
	 * @param string            $namespace          namespace to remove from class name
	 *                                              dependencies for your actions
	 * @return HyperMedia  a hyper media instance
	 */
	public static function createInstance(
		SharedStateStore $sharedStateStore,
		Cache $cache,
		Translator $translator,
		JsonDeserializer $jsonDeserializer,
		string $sourceUnit,
		string $basePath,
		string $namespace): HyperMedia
	{
		return new class ($sharedStateStore, $cache, $translator, $jsonDeserializer, $sourceUnit, $basePath, $namespace)
		extends HyperMedia {
			public function handle(Request\Request $request): Response\Response
			{
				return parent::realHandle($request);
			}
		};
	}

	/**
	 * Create Psr adaptor
	 *
	 * @param SharedStateStore  $sharedStateStore   shared state store
	 * @param Cache             $cache              the cache provider
	 * @param Translator        $translator         the translator
	 * @param JsonDeserializer  $jsonDeserializer   the deserializer
	 * @param string            $sourceUnit         the source unit to use
	 * @param string            $basePath           the prefix to remove from the path before processing
	 *                                              and appended for generating links
	 * @param string            $namespace          namespace to remove from class name
	 *                                              dependencies for your actions
	 * @return \Psr\Http\Server\RequestHandlerInterface  the adaptation
	 */
	public static function createPsrAdaptor(
		SharedStateStore $sharedStateStore,
		Cache $cache,
		Translator $translator,
		JsonDeserializer $jsonDeserializer,
		string $sourceUnit,
		string $basePath,
		string $namespace): \Psr\Http\Server\RequestHandlerInterface
	{
		return new class ($sharedStateStore, $cache, $translator, $jsonDeserializer, $sourceUnit, $basePath, $namespace)
		extends HyperMedia
		implements \Psr\Http\Server\RequestHandlerInterface {
			public function handle(\Psr\Http\Message\ServerRequestInterface $request): \Psr\Http\Message\ResponseInterface
			{
				return new Response\PsrAdaptor(parent::realHandle(new Request\PsrAdaptor($request)));
			}
		};
	}

	/**
	 * Create symfony adaptor
	 *
	 * @param SharedStateStore  $sharedStateStore   shared state store
	 * @param Cache             $cache              the cache provider
	 * @param Translator        $translator         the translator
	 * @param JsonDeserializer  $jsonDeserializer   the deserializer
	 * @param string            $sourceUnit         the source unit to use
	 * @param string            $basePath           the prefix to remove from the path before processing
	 *                                              and appended for generating links
	 * @param string            $namespace          namespace to remove from class name
	 *                                              dependencies for your actions
	 * @return \Symfony\Component\HttpKernel\HttpKernelInterface  the adaptation
	 */
	public static function createSymfonyAdaptor(
		SharedStateStore $sharedStateStore,
		Cache $cache,
		Translator $translator,
		JsonDeserializer $jsonDeserializer,
		string $sourceUnit,
		string $basePath,
		string $namespace): \Symfony\Component\HttpKernel\HttpKernelInterface
	{
		return new class ($sharedStateStore, $cache, $translator, $jsonDeserializer, $sourceUnit, $basePath, $namespace)
		extends HyperMedia
		implements \Symfony\Component\HttpKernel\HttpKernelInterface {
			public function handle(\Symfony\Component\HttpFoundation\Request $request, $type = self::MASTER_REQUEST, $catch = true): \Symfony\Component\HttpFoundation\Response
			{
				// please note that $type and $catch are ignored
				return new Response\SymfonyAdaptor(parent::realHandle(new Request\SymfonyAdaptor($request)));
			}
		};
	}

	/**
	 * Create laravel adaptor.
	 *
	 * Laravel's http kernel has some additional stuff I don't really know what
	 * it is about. So this implementation may not work properly.
	 *
	 * @param SharedStateStore  $sharedStateStore   shared state store
	 * @param Cache             $cache              the cache provider
	 * @param Translator        $translator         the translator
	 * @param JsonDeserializer  $jsonDeserializer   the deserializer
	 * @param string            $sourceUnit         the source unit to use
	 * @param string            $basePath           the prefix to remove from the path before processing
	 *                                              and appended for generating links
	 * @param string            $namespace          namespace to remove from class name
	 *                                              dependencies for your actions
	 * @param \Illuminate\Contracts\Foundation\Application $app
	 * @param array $bootstrappers
	 * @return \Illuminate\Contracts\Http\Kernel
	 */
	public static function createLaravelAdaptor(
		SharedStateStore $sharedStateStore,
		Cache $cache,
		Translator $translator,
		JsonDeserializer $jsonDeserializer,
		string $sourceUnit,
		string $basePath,
		string $namespace,
		\Illuminate\Contracts\Foundation\Application $app,
		array $bootstrappers = []): \Illuminate\Contracts\Http\Kernel
	{
		return new class ($sharedStateStore, $cache, $translator, $jsonDeserializer, $sourceUnit, $basePath, $namespace, $app, $bootstrappers)
		extends HyperMedia
		implements \Illuminate\Contracts\Http\Kernel {
			private $app;
			private $bootstrappers;

			private function __construct(HyperMedia $hyperMedia, \Illuminate\Contracts\Foundation\Application $app, array $bootstrappers = []) {
				parent::__construct($hyperMedia);
				$this->app = $app;
				$this->bootstrappers = $bootstrappers;
			}

			public function bootstrap()
			{
				if (!$this->app->hasBeenBootstrapped()) {
					$this->app->bootstrapWith($this->bootstrappers);
				}
			}

			public function handle(\Symfony\Component\HttpFoundation\Request $request): Request\SymfonyAdaptor
			{
				return new Response\SymfonyAdaptor(parent::realHandle(new Request\SymfonyAdaptor($request)));
			}

			public function terminate($request, $response)
			{
				$this->app->terminate();
			}

			public function getApplication()
			{
				return $this->app;
			}
		};
	}

	/**
	 * Create an array adaptor to be used with $_SERVER like array.
	 *
	 * @param SharedStateStore  $sharedStateStore   shared state store
	 * @param Cache             $cache              the cache provider
	 * @param Translator        $translator         the translator
	 * @param JsonDeserializer  $jsonDeserializer   the deserializer
	 * @param string            $sourceUnit         the source unit to use
	 * @param string            $basePath           the prefix to remove from the path before processing
	 *                                              and appended for generating links
	 * @param string            $namespace          namespace to remove from class name
	 *                                              dependencies for your actions
	 * @return object  the adaptation
	 */
	public static function createArrayAdaptor(
		SharedStateStore $sharedStateStore,
		Cache $cache,
		Translator $translator,
		JsonDeserializer $jsonDeserializer,
		string $sourceUnit,
		string $basePath,
		string $namespace): HyperMedia
	{
		return new class ($sharedStateStore, $cache, $translator, $jsonDeserializer, $sourceUnit, $basePath, $namespace)
		extends HyperMedia {
			public function handle(array $request = null): Response\ArrayAdaptor
			{
				return new Response\ArrayAdaptor(parent::realHandle(new Request\DefaultAdaptor($request ?? $_SERVER)));
			}
		};
	}

	/**
	 * Create an echo adaptor to be used with $_SERVER like array.
	 *
	 * @param SharedStateStore  $sharedStateStore   shared state store
	 * @param Cache             $cache              the cache provider
	 * @param Translator        $translator         the translator
	 * @param JsonDeserializer  $jsonDeserializer   the deserializer
	 * @param string            $sourceUnit         the source unit to use
	 * @param string            $basePath           the prefix to remove from the path before processing
	 *                                              and appended for generating links
	 * @param string            $namespace          namespace to remove from class name
	 *                                              dependencies for your actions
	 * @return object  the adaptation
	 */
	public static function createEchoAdaptor(
		SharedStateStore $sharedStateStore,
		Cache $cache,
		Translator $translator,
		JsonDeserializer $jsonDeserializer,
		string $sourceUnit,
		string $basePath,
		string $namespace): HyperMedia
	{
		return new class ($sharedStateStore, $cache, $translator, $jsonDeserializer, $sourceUnit, $basePath, $namespace)
		extends HyperMedia {
			public function handle(array $request = null): Response\EchoAdaptor
			{
				return new Response\EchoAdaptor(parent::realHandle(new Request\DefaultAdaptor($request ?? $_SERVER)));
			}
		};
	}

	// dependencies
	private $sharedStateStore;
	private $cache;
	private $translator;
	private $jsonDeserializer;
	// configuration
	private $sourceUnit;
	private $basePath;
	private $namespace;

	/**
	 * Constructor
	 *
	 * @param SharedStateStore  $sharedStateStore   shared state store
	 * @param Cache             $cache              the cache provider
	 * @param Translator        $translator         the translator
	 * @param JsonDeserializer  $jsonDeserializer   the deserializer
	 * @param string            $sourceUnit         the source unit to use
	 * @param string            $basePath           the prefix to remove from the path before processing
	 *                                              and appended for generating links
	 * @param string            $namespace          namespace to remove from class name
	 *                                              dependencies for your actions
	 */
	protected function __construct(
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
		$this->namespace = !empty($namespace) ? (rtrim($namespace, "\\") . "\\") : "";
	}

	/**
	 * Handle a request
	 *
	 * @param Request\Request $request   the request object
	 * @return Response\Response         the response object
	 */
	protected function realHandle(Request\Request $request): Response\Response
	{
		try {
			$verb = $request->getVerb();
			$path = substr($request->getPath(), strlen($this->basePath));
			$query = $this->getQuery($request);
			$this->sharedStateStore->fill("request", [
				"protocolVersion"=>$request->getProtocolVersion(),
				"scheme"=>$request->getScheme(),
				"host"=>$request->getHost(),
				"port"=>$request->getPort(),
				"verb"=>$verb,
				"path"=>$path
			]);
			switch ($verb) {
				case "GET":
					$values = [];
					$response = $this->call($verb, $path, $values, $query);
					break;

				case "POST":
					$values = $this->getBody($request);
					$response = $this->call($verb, $path, $values, $query);
					break;

				case "RECON":
					$body = $this->getBody($request);
					$conditions = $body['conditions'];
					$values = $body['data'];
					$response = $this->call("GET", $path, $values, $query, $conditions, $body['data']);
					break;

				case "LOOKUP":
					$values = $this->getBody($request);
					$response = $this->call("GET", $path, $values, $query, [], []);
					break;

				default:
					$response = new Response\MethodNotAllowed();
					break;
			}
		} catch (Throwable $e) {
			$response = new Response\InternalServerError("Uncaught exception: $verb {$this->basePath}$path" . ($query ? "?".http_build_query($query) : ""), 0, $e);
		}
		$response->setProtocolVersion($request->getProtocolVersion());
		return $response;
	}

	/**
	 * Call the resource
	 *
	 * @param  string $verb        the verb to use on the resource
	 * @param  string $path        the path of the resouce
	 * @param  array  $values      the input values
	 * @param  array  $conditions  conditions
	 * @param  array  $preserve    preserve field values
	 * @return Response  the response
	 */
	private function call(string $verb, string $path, array $values, array $query, array $conditions = [], array $preserve = null): Response\Response
	{
		$this->sharedStateStore->fill("query", $query);
		if ($path === null || $path === "" || $path === "/") { // if root resource
			$response = (new Resource($this->sharedStateStore, $this->cache, $this->translator, $this->jsonDeserializer, $this->sourceUnit, $this->basePath, $this->namespace, "", $query))
				->createRootResource($verb, $conditions)
				->call($values, $query, $preserve);
		} else { // if normal resource
			$class = $this->namespace . strtr(trim(str_replace('-','',ucwords($path,'-/')),"/"),"/","\\");
			$response = (new Resource($this->sharedStateStore, $this->cache, $this->translator, $this->jsonDeserializer, $this->sourceUnit, $this->basePath, $this->namespace, $class, $query))
				->createResource($class, $verb, $conditions)
				->call($values, $query, $preserve);
		}
		return $response;
	}

	/**
	 * Get the body from request.
	 *
	 * @param  Request\Request $request  the request
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
					throw new Response\BadRequest("The content is not valid JSON.");
				}
				return $values;

			case null === $contentType:
				return [];

			default:
				throw new Response\UnsupportedMediaType("Expected media type 'application/json', got '" . $request->getContentType() . "'.");
		}
	}

	/**
	 * Get query parameters from request.
	 *
	 * @param  Request\Request $request  the request
	 * @return array                     the query parameters
	 */
	private function getQuery(Request\Request $request): array
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

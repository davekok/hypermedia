# Sturdy Activity

A component to journal activities as they execute and update activity diagrams when you code.

Sturdy Activity contains two essentials a class to easily create a HyperMedia API and a class
to easily write activities. Sturdy works by using annotations.

## Usage

Write classes to implement the Journal interfaces: Journal, JournalBranch, JournalEntry and JournalRepository.

Scan your source code for annotations using SourceUnitFactory.

```php

$annotationReader = // implementation of \Doctrine\Common\Annotations\Reader
$factory = new \Sturdy\Activity\Meta\SourceUnitFactory($annotationReader);
$unit = $factory->createSourceUnit("app", "/srv/app/src/Activity:/srv/app/src/Resource:...");

$cachepool = // implementation of \Psr\Cache\CacheItemPoolInterface
$cache = new \Sturdy\Activity\Meta\Cache($cachepool);
$cache->updateSourceUnit($unit);

```

## HyperMedia

Example:

```php

use Sturdy\Activity\Meta\{Field,Get,Post};

class Person
{
	/**
	 * @Field("string required")
	 */
	public $name;

	/**
	 * The get verb for this resouce.
	 *
	 * @Get
	 * @param  OK $response    the response
	 * @param     $di          your dependency injection object
	 */
	public function get(\Sturdy\Activity\Response\OK $response, $di): void
	{
		$this->name = "Patrick";
	}

	/**
	 * The post verb for this resouce.
	 *
	 * @Post
	 * @param  OK $response    the response
	 * @param     $di          your dependency injection object
	 */
	public function post(\Sturdy\Activity\Response\OK $response, $di): void
	{
		$name = $this->name; // $this->name is automatically filled from POST body
	}
}

```

```php

class HttpKernel
{
	private $hypermedia;

	public function __construct(\Psr\Cache\CacheItemPoolInterface $cachepool, \Sturdy\Activity\JournalRepository $journalRepository, $di)
	{
		$cache = new \Sturdy\Activity\Meta\Cache($cachepool);
		$this->hypermedia = new \Sturdy\Activity\HyperMedia($cache, $journalRepository, "app", "/", $di);
	}

	public function handle(\Psr\Http\Message\ServerRequestInterface|\Symfony\Component\HttpFoundation\Request $request): \Psr\Http\Message\ResponseInterface|\Symfony\Component\HttpFoundation\Response
	{
		$tags = []; // get features/privileges from your session/environment
		return $this->hypermedia->handle($tags, $request);
	}
}

```

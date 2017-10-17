<?php declare(strict_types=1);

namespace Tests\Sturdy\Activity\Helpers;

use Sturdy\Activity\{
	Cache,
	HyperMedia,
	Journal,
	JournalBranch,
	JournalEntry,
	JournalRepository
};
use Sturdy\Activity\Request\Request;
use Sturdy\Activity\Response;
use Sturdy\Activity\Meta\{
	CacheItem_Resource,
	FieldFlags,
	Verb
};
use PHPUnit\Framework\TestCase;
use Prophecy\{
	Argument, Prophecy\ObjectProphecy, Prophet
};

use Faker;
use Throwable;
use stdClass;

class HyperMediaBase extends TestCase
{
	protected $prophet;
	protected $faker;

	// resource
	protected $sourceUnit;
	protected $basePath;
	protected $class;
	protected $classes;
	protected $attachmentFields;

	protected $method;
	protected $tags;

	// request
	protected $protocolVersion;
	protected $verb;
	protected $root;
	protected $journalId;
	protected $_journalId;
	protected $fields;
	protected $requestContent;

	// response
	protected $statusCode;
	protected $statusText;
	protected $location;
	protected $contentType;
	protected $content;

	public function __construct($name = null, array $data = [], $dataName = '')
	{
		parent::__construct($name, $data, $dataName);
		$this->prophet = new Prophet;
		$this->faker = Faker\Factory::create();
	}

	public function createCache(): Cache
	{
		$resource = (new CacheItem_Resource())
			->setClass($this->class)
			->setTags($this->tags);
		switch ($this->statusCode) {
			default:
			case Verb::OK:
				$resource->setVerb($this->verb, $this->method, Verb::OK);
				break;
			case Verb::NO_CONTENT:
				$resource->setVerb($this->verb, $this->method, Verb::NO_CONTENT);
				break;
			case Verb::ACCEPTED:
				$resource->setVerb($this->verb, $this->method, Verb::ACCEPTED);
				break;
			case Verb::CREATED:
				$resource->setVerb($this->verb, $this->method, Verb::CREATED, $this->location);
				break;
		}

		foreach ($this->fields as $name => $descriptor) {
			$type = $descriptor["type"] . ":,,";
			$flags = 0;
			if ($descriptor["required"] ?? false) $flags |= FieldFlags::required;
			if ($descriptor["meta"] ?? false) $flags |= FieldFlags::meta;
			if ($descriptor["data"] ?? false) $flags |= FieldFlags::data;
			if ($descriptor["array"] ?? false) $flags |= FieldFlags::_array;
			if ($descriptor["multiple"] ?? false) $flags |= FieldFlags::multiple;
			if ($descriptor["readonly"] ?? false) $flags |= FieldFlags::readonly;
			if ($descriptor["disabled"] ?? false) $flags |= FieldFlags::disabled;
			$resource->setField($name, $type, $descriptor["defaultValue"] ?? null, $flags);
		}

		$cache = $this->prophet->prophesize();
		$cache->willImplement(Cache::class);
		$cache->getResource($this->sourceUnit, $this->class, $this->tags)
			->shouldBeCalledTimes(1)
			->willReturn($resource);

		if (isset($this->classes)) {
			foreach($this->classes as $class){
				$resource = (new CacheItem_Resource())->setClass($class)->setTags($this->tags);
				$resource->setVerb('GET', 'foo', Verb::OK);
				$cache->getResource($this->sourceUnit, $class, $this->tags)
					->shouldBeCalledTimes(2)
					->willReturn($resource);

				foreach ($this->attachmentFields[$class]??[] as $name => $descriptor) {
					$type = $descriptor["type"] . ":,,";
					$flags = 0;
					if ($descriptor["required"] ?? false) $flags |= FieldFlags::required;
					if ($descriptor["meta"] ?? false) $flags |= FieldFlags::meta;
					$resource->setField($name, $type, $descriptor["defaultValue"] ?? null, $flags);
				}
			}
		}
		return $cache->reveal();
	}

	public function initResource(string $sourceUnit,string $class,string $method, array $tags = [], string $responseType, string $code = null): void
	{
		// resource
		$this->sourceUnit = $sourceUnit;
		$this->basePath = $this->faker->boolean ? "/" : "/".strtr($this->faker->slug, "-", "/")."/";
		$this->class = $class;
		while(class_exists($this->class))
		{
			$this->class = $this->faker->unique()->word;
		}

		$this->method = $method;
		$this->tags = $tags;
		$responseType = "Sturdy\\Activity\\Response\\" . ucfirst($responseType);

		eval(<<<CLASS
final class $this->class
{
	public function $method($responseType \$response, \$di) {
		$code
	}
}
CLASS
		);
	}

	public function initRequest(string $protocolVersion, string $verb, bool $root = false, array $fields = [])
	{
		$this->protocolVersion = $protocolVersion;
		$this->verb = $verb;
		$this->root = $root;
		$this->journalId = $this->faker->boolean ? null : rand();
		$this->fields = $fields;

		if($verb === "POST") {
			$this->requestContentType = "application/json";
			$this->requestContent = [];
			foreach ($this->fields as $name => &$field) {
				if (!($field["meta"] ?? false) && array_key_exists('value',$field)) {
					$this->requestContent[$name] = $field['value'];
					unset($field['value']);
				}
			}
			$this->requestContent = json_encode($this->requestContent, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		} else if($verb === "GET") {
			$this->requestContentType = null;
			$this->requestContent = null;
		}
	}

	public function initContent(array $fields = [], array $links = null): stdClass
	{
		$content = new stdClass;
		if (count($this->fields)) {
			$content->fields = new stdClass;
			foreach ($fields as $name => $field) {
				$content->fields->$name = new stdClass;
				$content->fields->$name->type = $field["type"];
				if ($field["required"]??false) {
					$content->fields->$name->required = true;
				}
				if ($field["array"]??false) {
					$content->fields->$name->{"array"} = true;
				}
				if ($field["multiple"]??false) {
					$content->fields->$name->multiple = true;
				}
				if ($field["readonly"]??false) {
					$content->fields->$name->readonly = true;
				}
				if ($field["disabled"]??false) {
					$content->fields->$name->disabled = true;
				}
				if ($field["data"]??false) {
					$content->fields->$name->data = true;
					if (isset($field["value"])) {
						$content->data = $field["value"];
					}
				}
				if ($field["meta"]??false) {
					$content->fields->$name->meta = true;
					if (isset($field["value"])) {
						$content->fields->$name->value = $field["value"];
					}
				} else {
					if (isset($field["value"])) {
						if (!isset($content->data)) {
							$content->data = new stdClass;
						}
						$content->data->$name = $field["value"];
					}
				}
			}
		}
		/* TODO: links */
		return $content;
	}


	public function createJournalBranchEntry(): JournalEntry
	{
		$entry = $this->prophet->prophesize();
		$entry->willImplement(JournalEntry::class);
		return $entry->reveal();
	}

	public function createJournalBranch(): JournalBranch
	{
		$branch = $this->prophet->prophesize();
		$branch->willImplement(JournalBranch::class);
		$branch->getLastEntry()->willReturn(null);
		$branch->getJunction()->willReturn(0);
		$self = $this;
		$entries = [];
		$branch->addEntry(Argument::type('object'), $this->method, $this->statusCode, $this->statusText)
			->will(function($args, $branch)use($self,&$entries){
				$entries[] = $entry = $self->createJournalBranch();
				$branch->getLastEntry()->willReturn($entry);
				$branch->getEntries()->willReturn($entries);
				return $this;
			});
		if ($this->statusCode !== 500) {
			$branch->addEntry(Argument::type('object'), Argument::type('string'), 500, "Internal Server Error")
				->shouldNotBeCalled()
				->will(function ($args, $branch) use ($self, &$entries) {
					$entries[] = $entry = $self->createJournalBranch();
					$branch->getLastEntry()->willReturn($entry);
					$branch->getEntries()->willReturn($entries);
					return $this;
				});
		}
		return $branch->reveal();
	}

	public function createNewJournal(): Journal
	{
		$journal = $this->prophet->prophesize();
		$journal->willImplement(Journal::class);
		$journal->getId()->willReturn($this->_journalId);
		$journal->getSourceUnit()->willReturn($this->sourceUnit);
		$journal->getType()->willReturn(Journal::resource);
		$journal->getTags()->willReturn($this->tags);
		$journal->getFirstBranch()->willReturn(null);
		$self = $this;
		$journal->createBranch(0)->will(function($args)use($self,$journal){
			$branch = $self->createJournalBranch();
			$journal->getFirstBranch()->willReturn($branch);
			return $branch;
		});
		return $journal->reveal();
	}

	public function createResumableJournal(): Journal
	{
		$journal = $this->prophet->prophesize();
		$journal->willImplement(Journal::class);
		$journal->getId()->willReturn($this->_journalId);
		$journal->getSourceUnit()->willReturn($this->sourceUnit);
		$journal->getType()->willReturn(Journal::resource);
		$journal->getTags()->willReturn($this->tags);
		$self = $this;
		$journal->getFirstBranch()->will(function($args)use($self,$journal){
			$branch = $self->createJournalBranch();
			$journal->getLastBranch()->willReturn($branch);
			return $branch;
		});
		return $journal->reveal();
	}

	public function createJournalRepository(): JournalRepository
	{
		$journalRepository = $this->prophet->prophesize();
		$journalRepository->willImplement(JournalRepository::class);
		$journalRepository->saveJournal(Argument::type(Journal::class))->shouldBeCalled();
		if ($this->journalId !== null) {
			$journalRepository->findOneJournalById($this->_journalId)
				->shouldBeCalledTimes(1)
				->will([$this,"createResumableJournal"]);
		} else {
			$journalRepository->createJournal($this->sourceUnit, Journal::resource, $this->tags)
				->shouldBeCalledTimes(1)
				->will([$this,"createNewJournal"]);
		}
		return $journalRepository->reveal();
	}

	public function createRequest(bool $predictQuery = true): Request
	{
		$request = $this->prophet->prophesize();
		$request->willImplement(Request::class);
		$request->getProtocolVersion()->shouldBeCalled()->willReturn($this->protocolVersion);
		$request->getVerb()->shouldBeCalled()->willReturn($this->verb<300?$this->verb:200);
		if ($this->journalId !== null) {
			$path = "/$this->journalId/";
		} else {
			$path = "/";
		}
		if (!$this->root) {
			$path.= strtr($this->class,"\\","/");
		}
		$request->getPath()->shouldBeCalled()->willReturn($this->basePath.ltrim($path, "/"));
		if ($predictQuery) {
			$query = "";
			foreach ($this->fields as $name => $descriptor) {
				if (($descriptor["meta"] ?? false) && isset($descriptor["value"])) {
					$query .= "&$name=" . $descriptor["value"];
				}
			}
			if ($query) $query = substr($query, 1);
			$request->getQuery()->shouldBeCalled()->willReturn($query);
		}
		if ($this->requestContentType === null) {
			$request->getContentType()->shouldNotBeCalled()->willReturn($this->requestContentType);
		} else {
			$request->getContentType()->shouldBeCalled()->willReturn($this->requestContentType);
		}
		if ($this->requestContent === null) {
			$request->getContent()->shouldNotBeCalled()->willReturn($this->requestContent);
		} else {
			$request->getContent()->shouldBeCalled()->willReturn($this->requestContent);
		}
		return $request->reveal();
	}

	public function createErrorRequest(): Request
	{
		$request = $this->prophet->prophesize();
		$request->willImplement(Request::class);
		$request->getProtocolVersion()->shouldBeCalled()->willReturn($this->protocolVersion);
		$request->getVerb()->shouldBeCalled()->willReturn($this->verb);
		if ($this->journalId !== null) {
			$path = "/$this->journalId/";
		} else {
			$path = "/";
		}
		if (!$this->root) {
			$path.= strtr($this->class,"\\","/");
		}
		$request->getPath()->shouldBeCalled()->willReturn($this->basePath.ltrim($path, "/"));
		return $request->reveal();
	}

	public function createHyperMedia(): HyperMedia
	{
		return new HyperMedia($this->createCache(), $this->createJournalRepository(), $this->sourceUnit, $this->basePath, new stdClass);
	}

	public function createHyperMediaWithNullCache(): HyperMedia
	{
		$cache = $this->prophet->prophesize();
		$cache->willImplement(Cache::class);
		$cache->getResource($this->sourceUnit, $this->class, $this->tags)
			->shouldBeCalledTimes(1)
			->willReturn(null);

		$cache = $cache->reveal();

		return new HyperMedia($cache, $this->createJournalRepository(), $this->sourceUnit, $this->basePath, new stdClass);
	}
	public function createHyperMediaWithErrorCache(): HyperMedia
	{
		return new HyperMedia($this->prophet->prophesize()->willImplement(Cache::class)->reveal(), $this->createJournalRepository(), $this->sourceUnit, $this->basePath, new stdClass);
	}

	public function handle(HyperMedia $hm, Request $request)
	{
		$response = $hm->handle($this->tags, $request);
		if ($response instanceof Response\Error && $this->statusCode !== $response->getStatusCode()) {
			throw $response; // some error occured
		}
		$this->assertEquals($this->protocolVersion, $response->getProtocolVersion(), "protocol version");
		$this->assertEquals($this->statusCode, $response->getStatusCode(), "status code");
		$this->assertEquals($this->statusText, $response->getStatusText(), "status text");
		if ($this->location !== null) {
			$this->assertEquals($this->location, $response->getLocation(), "location");
		} else {
			$this->assertNull($response->getLocation(), "location");
		}
		if ($this->contentType !== null) {
			$this->assertEquals($this->contentType, $response->getContentType(), "content type");
		} else {
			$this->assertNull($response->getContentType(), "content type");
		}
		if ($this->content !== null) {
			$this->assertJsonStringEqualsJsonString($response->getContent(), $this->content, "content");
		} else {
			$this->assertNull($response->getContent(), "content");
		}
		$this->prophet->checkPredictions();
	}
}

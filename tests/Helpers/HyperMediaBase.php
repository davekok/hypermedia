<?php declare(strict_types=1);

namespace Tests\Sturdy\Activity\Helpers;

use Sturdy\Activity\{
	Cache,
	HyperMedia,
	Journal,
	JournalBranch,
	JournalEntry,
	JournalRepository,
	Translator
};
use Sturdy\Activity\Request\Request;
use Sturdy\Activity\Response;
use Sturdy\Activity\Meta\{
	CacheItem_Resource,
	FieldFlags,
	Verb,
	VerbFlags
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
	protected $label;
	protected $section;
	protected $component;
	protected $layout;
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
	protected $fields = [];
	protected $data = [];
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
			->setHints($this->label, $this->section, $this->component, $this->layout)
			->setTags($this->tags);
		$flags = new VerbFlags();
		$flags->setStatus($this->statusCode);
		$resource->setVerb($this->verb, $this->method, $flags->toInt(), $this->location);

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
			$resource->addField($name, $type, $descriptor["defaultValue"] ?? null, $flags);
		}

		$cache = $this->prophet->prophesize();
		$cache->willImplement(Cache::class);
		$cache->getResource($this->sourceUnit, $this->class, $this->tags)
			->shouldBeCalled()
			->willReturn($resource);

		if (isset($this->classes)) {
			foreach($this->classes as $class){
				$resource = (new CacheItem_Resource())->setClass($class)->setTags($this->tags);
				$flags = new VerbFlags();
				$flags->setStatus(Verb::OK);
				$resource->setVerb('GET', 'foo', $flags->toInt());
				$cache->getResource($this->sourceUnit, $class, $this->tags)
					->shouldBeCalled()
					->willReturn($resource);

				foreach ($this->attachmentFields[$class]??[] as $name => $descriptor) {
					$type = $descriptor["type"] . ":,,";
					$flags = 0;
					if ($descriptor["required"] ?? false) $flags |= FieldFlags::required;
					if ($descriptor["meta"] ?? false) $flags |= FieldFlags::meta;
					$resource->addField($name, $type, $descriptor["defaultValue"] ?? null, $flags);
				}
			}
		}
		return $cache->reveal();
	}

	public function initResource(string $sourceUnit, string $class, string $method, array $tags = [], string $responseType, string $code = null): void
	{
		// resource
		$this->sourceUnit = $sourceUnit;
		$this->basePath = $this->faker->boolean ? "/" : "/".strtr($this->faker->slug, "-", "/")."/";
		$this->class = $class;
		while (class_exists($this->class)) {
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

		if ($verb === "POST") {
			$this->requestContentType = "application/json";
			$this->requestContent = [];
			foreach ($this->fields as $name => &$field) {
				if (array_key_exists($name, $this->data)) {
					$this->requestContent[$name] = $this->data[$name];
				}
			}
			$this->requestContent = json_encode($this->requestContent, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		} else if($verb === "GET") {
			$this->requestContentType = null;
			$this->requestContent = null;
		}
	}

	public function setContent(string $resource, string $class, array $fields = [], $data = null, array $links = []): void
	{
		if (!isset($this->content)) {
			$this->content = new stdClass;
		}
		$this->content->$resource = new stdClass;
		$this->content->$resource->links = new stdClass;
		$haveData = false;
		$meta = "";
		if (count($fields)) {
			$this->content->$resource->fields = [];
			$i = 0;
			foreach ($fields as $name => $field) {
				$this->content->$resource->fields[] = $entry = new stdClass;
				$entry->name = $name;
				if ($field["meta"]??false) {
					if (isset($field["value"])) {
						$entry->value = $field["value"];
					}
					$entry->meta = true;
					if (!(($field["disabled"]??false) || ($field["readonly"]??false))) {
						if ($i++) $meta.= ","; else $meta = "{?";
						$meta.= $name;
					}
				} else {
					$haveData = true;
					// if (isset($field["value"])) {
					// 	if (!isset($this->content->$resource->data)) {
					// 		$this->content->$resource->data = new stdClass;
					// 	}
					// 	$this->content->$resource->data->$name = $field["value"];
					// }
				}
				$entry->type = $field["type"];
				if ($field["required"]??false) {
					$entry->required = true;
				}
				if ($field["array"]??false) {
					$entry->{"array"} = true;
				}
				if ($field["multiple"]??false) {
					$entry->multiple = true;
				}
				if ($field["readonly"]??false) {
					$entry->readonly = true;
				}
				if ($field["disabled"]??false) {
					$entry->disabled = true;
				}
				if ($field["data"]??false) {
					$haveData = true;
					$entry->data = true;
					// if (isset($field["value"])) {
					// 	$this->content->$resource->data = $field["value"];
					// }
				}
			}
			if ($i) $meta.= "}";
		}
		foreach ($links as $name => [$otherclass, $othermeta]) {
			$this->content->$resource->links->$name = new stdClass;
			$this->content->$resource->links->$name->href = $this->basePath . $this->_journalId . '/' . $otherclass . $othermeta;
			if ($othermeta) {
				$this->content->$resource->links->$name->templated = true;
			}
		}
		$this->content->$resource->links->self = new stdClass;
		$this->content->$resource->links->self->href = $this->basePath . $this->_journalId . '/' . $class . $meta;
		if ($meta) {
			$this->content->$resource->links->self->templated = true;
		}
		if ($haveData) {
			$this->content->$resource->data = $data;
		}
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

	public function createTranslator(): Translator
	{
		$translator = $this->prophet->prophesize();
		$translator->willImplement(Translator::class);
		$translator->__invoke(Argument::type('string'), Argument::any())->will(function($args){
			return $args[0];
		});
		return $translator->reveal();
	}

	public function createHyperMedia(): HyperMedia
	{
		return new HyperMedia($this->createCache(), $this->createJournalRepository(), $this->createTranslator(), $this->sourceUnit, $this->basePath, new stdClass);
	}

	public function createHyperMediaWithNullCache(): HyperMedia
	{
		$cache = $this->prophet->prophesize();
		$cache->willImplement(Cache::class);
		$cache->getResource($this->sourceUnit, $this->class, $this->tags)
			->shouldBeCalledTimes(1)
			->willReturn(null);

		$cache = $cache->reveal();

		return new HyperMedia($cache, $this->createJournalRepository(), $this->createTranslator(), $this->sourceUnit, $this->basePath, new stdClass);
	}

	public function createHyperMediaWithErrorCache(): HyperMedia
	{
		return new HyperMedia($this->prophet->prophesize()->willImplement(Cache::class)->reveal(), $this->createJournalRepository(), $this->createTranslator(), $this->sourceUnit, $this->basePath, new stdClass);
	}

	public function handle(HyperMedia $hm, Request $request)
	{
		$response = $hm->handle($this->tags, $request);
		if ($response instanceof Response\Error && $this->statusCode !== $response->getStatusCode()) {
			throw $response; // some error occured
		}
		$this->assertEquals($response->getProtocolVersion(), $this->protocolVersion, "protocol version");
		$this->assertEquals($response->getStatusCode(), $this->statusCode, "status code");
		$this->assertEquals($response->getStatusText(), $this->statusText, "status text");
		if ($this->location !== null) {
			$this->assertEquals($response->getLocation(), $this->location, "location");
		} else {
			$this->assertNull($response->getLocation(), "location");
		}
		if ($this->contentType !== null) {
			$this->assertEquals($response->getContentType(), $this->contentType, "content type");
		} else {
			$this->assertNull($response->getContentType(), "content type");
		}
		if ($this->content !== null) {
			if (is_object($this->content)) {
				$this->content = json_encode($this->content, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
			}
			$this->assertJsonStringEqualsJsonString($response->getContent(), $this->content, "content");
		} else {
			$this->assertNull($response->getContent(), "content");
		}
		$this->prophet->checkPredictions();
	}
}

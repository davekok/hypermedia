<?php declare(strict_types=1);

namespace Tests\Sturdy\Activity;

use Sturdy\Activity\{
	Cache,
	HyperMedia,
	Journal,
	JournalBranch,
	JournalBranchEntry,
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
	Argument,
	Prophet
};
use Faker;
use Throwable;
use stdClass;

class HyperMediaTest extends TestCase
{
	private $prophet;
	private $faker;

	// resource
	private $sourceUnit;
	private $basePath;
	private $class;
	private $method;
	private $tags;

	// request
	private $protocolVersion;
	private $verb;
	private $root;
	private $journalId;
	private $fields;

	// response
	private $statusCode;
	private $statusText;
	private $location;
	private $contentType;
	private $content;

	public function setUp()
	{
		$this->prophet = new Prophet;
		$this->faker = Faker\Factory::create();
	}

	public function testGetResource()
	{
		// resource
		$this->sourceUnit = "TestUnit1";
		$this->basePath = $this->faker->boolean ? "/" : "/".strtr($this->faker->slug, "-", "/")."/";
		$this->class = TestUnit1\Resource1::class;
		$this->method = "foo";
		$this->tags = [];

		// request
		$this->protocolVersion = "1.1";
		$this->verb = "GET";
		$this->root = false;
		$this->journalId = $this->faker->boolean ? null : rand();
		$this->fields = [];
		if ($this->faker->boolean) {
			$this->fields["name"] = ["type"=>"string","value"=>$this->faker->name,"required"=>$this->faker->boolean,"meta"=>$this->verb==="GET"?true:$this->faker->boolean];
		}
		if ($this->faker->boolean) {
			$this->fields["streetName"] = ["type"=>"string","value"=>$this->faker->streetName,"required"=>$this->faker->boolean,"meta"=>$this->verb==="GET"?true:$this->faker->boolean];
		}
		$this->requestContentType = null;
		$this->requestContent = null;

		// response
		$this->statusCode = 200;
		$this->statusText = "OK";
		$this->location = null;
		$this->contentType = "application/json";
		$content = new stdClass;
		$content->main = new stdClass;
		if (count($this->fields)) {
			$content->main->fields = new stdClass;
			foreach ($this->fields as $name => $field) {
				$content->main->fields->$name = new stdClass;
				$content->main->fields->$name->type = $field["type"];
				if ($field["meta"]??false) {
					$content->main->fields->$name->meta = true;
				}
				if ($field["required"]??false) {
					$content->main->fields->$name->required = true;
				}
				if (isset($field["value"])) {
					$content->main->fields->$name->value = $field["value"];
				}
			}
		}
		$this->content = json_encode($content, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);

		$this->handle($this->createHyperMedia(), $this->createRequest());
	}

	public function testPostResource()
	{
		// resource
		$this->sourceUnit = "TestUnit1";
		$this->basePath = $this->faker->boolean ? "/" : "/".strtr($this->faker->slug, "-", "/")."/";
		$this->class = TestUnit1\Resource1::class;
		$this->method = "bar";
		$this->tags = [];

		// request
		$this->protocolVersion = "1.1";
		$this->verb = "POST";
		$this->root = false;
		$this->journalId = $this->faker->boolean ? null : rand();
		$this->fields = [];
		if ($this->faker->boolean) {
			$this->fields["name"] = ["type"=>"string","value"=>$this->faker->name,"required"=>$this->faker->boolean,"meta"=>$this->verb==="GET"?true:$this->faker->boolean];
		}
		if ($this->faker->boolean) {
			$this->fields["streetName"] = ["type"=>"string","value"=>$this->faker->streetName,"required"=>$this->faker->boolean];
		}
		if ($this->faker->boolean) {
			$this->fields["postcode"] = ["type"=>"string","value"=>$this->faker->postcode,"required"=>$this->faker->boolean];
		}
		if ($this->faker->boolean) {
			$this->fields["country"] = ["type"=>"string","value"=>$this->faker->country,"required"=>$this->faker->boolean];
		}
		$this->requestContentType = "application/json";
		$this->requestContent = [];
		foreach ($this->fields as $name => $field) {
			if (!($field["meta"]??false)) {
				$this->requestContent[$name] = $field['value'];
			}
		}
		$this->requestContent = json_encode($this->requestContent, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);

		// response
		$this->statusCode = 204;
		$this->statusText = "No Content";
		$this->location = null;
		$this->contentType = null;
		$this->content = null;

		$this->handle($this->createHyperMedia(), $this->createRequest());
	}

	private function createCache(): Cache
	{
		$resource = (new CacheItem_Resource())
			->setClass($this->class)
			->setTags([]);
		switch ($this->statusCode) {
			case 200:
				$resource->setVerb($this->verb, $this->method, Verb::OK);
				break;
			case 204:
				$resource->setVerb($this->verb, $this->method, Verb::NO_CONTENT);
				break;
		}

		foreach ($this->fields as $name => $descriptor) {
			$type = $descriptor["type"].",,,";
			$flags = 0;
			if ($descriptor["required"]??false) $flags |= FieldFlags::required;
			if ($descriptor["meta"]??false) $flags |= FieldFlags::meta;
			$resource->setField($name, $type, $descriptor["defaultValue"]??null, $flags);
		}

		$cache = $this->prophet->prophesize();
		$cache->willImplement(Cache::class);
		$cache->getResource($this->sourceUnit, $this->class, $this->tags)
			->shouldBeCalledTimes(1)
			->willReturn($resource);
		return $cache->reveal($resource);
	}

	public function createJournalBranchEntry(): JournalBranchEntry
	{
		$entry = $this->prophet->prophesize();
		$entry->willImplement(JournalBranchEntry::class);
		return $entry->reveal();
	}

	public function createJournalBranch(): JournalBranch
	{
		$branch = $this->prophet->prophesize();
		$branch->willImplement(JournalBranch::class);
		$branch->getLastEntry()->willReturn(null);
		$self = $this;
		$entries = [];
		$branch->addEntry(Argument::type($this->class), $this->method, $this->statusCode, $this->statusText)
			->will(function($args, $branch)use($self,&$entries){
				$entries[] = $entry = $self->createJournalBranch();
				$branch->getLastEntry()->willReturn($entry);
				$branch->getEntries()->willReturn($entries);
				return $this;
			});
		return $branch->reveal();
	}

	public function createJournal(): Journal
	{
		$journal = $this->prophet->prophesize();
		$journal->willImplement(Journal::class);
		$journal->getId()->willReturn($this->journalId??rand());
		$journal->getSourceUnit()->willReturn($this->sourceUnit);
		$journal->getType()->willReturn(Journal::resource);
		$journal->getTags()->willReturn($this->tags);
		$journal->getMainBranch()->will([$this,"createJournalBranch"]);
		return $journal->reveal();
	}

	public function createJournalRepository(): JournalRepository
	{
		$journalRepository = $this->prophet->prophesize();
		$journalRepository->willImplement(JournalRepository::class);
		$journalRepository->saveJournal(Argument::type(Journal::class))->shouldBeCalledTimes(1);
		if ($this->journalId !== null) {
			$journalRepository->findOneJournalById($this->journalId)
				->shouldBeCalledTimes(1)
				->will([$this,"createJournal"]);
		} else {
			$journalRepository->createJournal($this->sourceUnit, Journal::resource, $this->tags)
				->shouldBeCalledTimes(1)
				->will([$this,"createJournal"]);
		}
		return $journalRepository->reveal();
	}

	public function createRequest(): Request
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
		$query = "";
		foreach ($this->fields as $name => $descriptor) {
			if ($descriptor["meta"]??false) {
				$query.= "&$name=".$descriptor["value"];
			}
		}
		if ($query) $query = substr($query,1);
		$request->getQuery()->shouldBeCalled()->willReturn($query);
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

	public function createHyperMedia(): HyperMedia
	{
		return new HyperMedia($this->createCache(), $this->createJournalRepository(), $this->sourceUnit, $this->basePath, new stdClass);
	}

	public function handle(HyperMedia $hm, Request $request)
	{
		$response = $hm->handle($this->tags, $request);
		if ($response instanceof Response\Error && $this->statusCode !== $response->getStatusCode()) {
			throw $response; // some error occured
		}
		$this->prophet->checkPredictions();
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
			$this->assertJsonStringEqualsJsonString($this->content, $response->getContent(), "content");
		} else {
			$this->assertNull($response->getContent(), "content");
		}
	}
}

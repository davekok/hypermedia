<?php declare(strict_types=1);

namespace Tests\Sturdy\Activity;

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
use Tests\Sturdy\Activity\Helpers\HyperMediaBase;

class HyperMediaTest extends HyperMediaBase
{
	public function testGetOKResource()
	{
		// resource
		$this->initResource("TestUnit1",$this->faker->unique()->word,"foo",[],"OK");
		
		// request
		$fields = [];
		if ($this->faker->boolean) $fields["name"] = ["type"=>"string","value"=>$this->faker->name,"required"=>$this->faker->boolean,"meta"=>true];
		if ($this->faker->boolean) $this->fields["streetName"] = ["type"=>"string","value"=>$this->faker->streetName,"required"=>$this->faker->boolean,"meta"=>true];
		$this->initRequest("1.1","GET",false, $fields,"OK");
		
		
		$this->requestContentType = null;
		$this->requestContent = null;
		
		// response
		$this->_journalId = $this->journalId??rand();
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
	
	public function testGetOKMetaResource()
	{
		// resource
		$this->initResource("TestUnit1",$this->faker->unique()->word,"foo",[],"OK");
		
		// request
		$fields = [];
		if ($this->faker->boolean) $fields["name"] = ["type"=>"string","value"=>$this->faker->name];
		if ($this->faker->boolean) $this->fields["streetName"] = ["type"=>"string","value"=>$this->faker->streetName,"required"=>$this->faker->boolean,"meta"=>true];
		$this->initRequest("1.1","GET",false, $fields,"OK");
		
		$this->requestContentType = null;
		$this->requestContent = null;
		
		// response
		$this->_journalId = $this->journalId??rand();
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

	public function testPostNoContentResource()
	{
		// resource
		$this->initResource("TestUnit1",$this->faker->unique()->word,"bar",[],"NoContent");

		// request
		$fields = [];
		if ($this->faker->boolean) $fields["name"] = ["type"=>"string","value"=>$this->faker->name,"required"=>$this->faker->boolean,"meta"=>$this->verb==="GET"?true:$this->faker->boolean];
		if ($this->faker->boolean) $fields["streetName"] = ["type"=>"string","value"=>$this->faker->streetName,"required"=>$this->faker->boolean];
		if ($this->faker->boolean) $fields["postcode"] = ["type"=>"string","value"=>$this->faker->postcode,"required"=>$this->faker->boolean];
		if ($this->faker->boolean) $fields["country"] = ["type"=>"string","value"=>$this->faker->country,"required"=>$this->faker->boolean];
		$this->initRequest("1.1","POST",false, $fields,"NoContent");

		// response
		$this->_journalId = $this->journalId??rand();
		$this->statusCode = 204;
		$this->statusText = "No Content";
		$this->location = null;
		$this->contentType = null;
		$this->content = null;

		$this->handle($this->createHyperMedia(), $this->createRequest());
	}

	public function testPostAcceptedResource()
	{
		// resource
		$this->initResource("TestUnit1",$this->faker->unique()->word,"bar",[], "Accepted");

		// request
		$fields = [];
		if ($this->faker->boolean) $fields["name"] = ["type"=>"string","value"=>$this->faker->name,"required"=>$this->faker->boolean,"meta"=>$this->verb==="GET"?true:$this->faker->boolean];
		if ($this->faker->boolean) $fields["streetName"] = ["type"=>"string","value"=>$this->faker->streetName,"required"=>$this->faker->boolean];
		if ($this->faker->boolean) $fields["postcode"] = ["type"=>"string","value"=>$this->faker->postcode,"required"=>$this->faker->boolean];
		if ($this->faker->boolean) $fields["country"] = ["type"=>"string","value"=>$this->faker->country,"required"=>$this->faker->boolean];
		$this->initRequest("1.1","POST",false, $fields,"Accepted");

		// response
		$this->_journalId = $this->journalId??rand();
		$this->statusCode = 202;
		$this->statusText = "Accepted";
		$this->location = null;
		$this->contentType = null;
		$this->content = null;

		$this->handle($this->createHyperMedia(), $this->createRequest());
	}

	public function testPostCreatedResource()
	{
		// resource
		$this->initResource("TestUnit1",$this->faker->unique()->word,"bar", [], "Created");

		// request
		$fields = [];
		if ($this->faker->boolean) $fields["name"] = ["type"=>"string","value"=>$this->faker->name,"required"=>$this->faker->boolean,"meta"=>$this->verb==="GET"?true:$this->faker->boolean];
		if ($this->faker->boolean) $fields["streetName"] = ["type"=>"string","value"=>$this->faker->streetName,"required"=>$this->faker->boolean];
		if ($this->faker->boolean) $fields["postcode"] = ["type"=>"string","value"=>$this->faker->postcode,"required"=>$this->faker->boolean];
		if ($this->faker->boolean) $fields["country"] = ["type"=>"string","value"=>$this->faker->country,"required"=>$this->faker->boolean];
		$this->initRequest("1.1","POST",false, $fields,"Created");

		// response
		$this->_journalId = $this->journalId??rand();
		$this->statusCode = 201;
		$this->statusText = "Created";
		$this->location = $this->faker->url;
		$this->contentType = null;
		$this->content = null;

		$this->handle($this->createHyperMedia(), $this->createRequest());
	}

	public function testGetOKResourceWithAttachment()
	{
		// resource
		$this->sourceUnit = "TestUnit1";
		$this->basePath = $this->faker->boolean ? "/" : "/".strtr($this->faker->slug, "-", "/")."/";
		while (class_exists($this->class = ucfirst($this->faker->unique()->word)));
		
		$this->classes = [];
		while (class_exists($this->classes[0] = ucfirst($this->faker->unique()->word)));
		
		eval(<<<CLASS
final class {$this->class}
{
	public function foo(Sturdy\Activity\Response\OK \$response, \$di)
	{
		\$response->link("aside", "{$this->classes[0]}", [], true);
	}
}
CLASS
		);

		eval(<<<EOD
final class {$this->classes[0]}
{
	public function foo(Sturdy\Activity\Response\OK \$response, \$di)
	{
	}
}
EOD
		);

		$this->method = "foo";
		$this->tags = [];

		// request
		$fields = [];
		if ($this->faker->boolean) $fields["name"] = ["type"=>"string","value"=>$this->faker->name,"meta"=>true];
		if ($this->faker->boolean) $fields["streetName"] = ["type"=>"string","meta"=>true];
		if ($this->faker->boolean) $fields["postcode"] = ["type"=>"string","value"=>$this->faker->postcode,"meta"=>true];
		if ($this->faker->boolean) $fields["country"] = ["type"=>"string","value"=>$this->faker->country,"meta"=>true];
		$this->initRequest("1.1","GET",false, $fields,"OK");

		// response
		$this->_journalId = $this->journalId??rand();
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
		$content->main->links = new stdClass;
		$content->main->links->aside = new stdClass;
		$content->main->links->aside->href = $this->basePath . $this->_journalId . '/' . $this->classes[0];
		$content->aside = new stdClass;

		$this->content = json_encode($content, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);

		$this->handle($this->createHyperMedia(), $this->createRequest());
	}

	public function testGetOKResourceWithAttachmentWithFields()
	{
		// resource
		$this->sourceUnit = "TestUnit1";
		$this->basePath = $this->faker->boolean ? "/" : "/".strtr($this->faker->slug, "-", "/")."/";
		while (class_exists($this->class = ucfirst($this->faker->unique()->word)));
		$this->classes = [];
		while (class_exists($this->classes[0] = ucfirst($this->faker->unique()->word)));

		eval(<<<CLASS
final class {$this->class}
{
	public function foo(Sturdy\Activity\Response\OK \$response, \$di) {
		\$response->link("aside", "{$this->classes[0]}", ["name"=>"Foo"], true);
	}
}
CLASS
		);

		eval(<<<EOD
final class {$this->classes[0]}
{
	public function foo(Sturdy\Activity\Response\OK \$response, \$di): void
	{
	}
}
EOD
		);

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

		foreach ($this->classes as $class) {
			$this->attachmentFields[$class]["name"] = ["type"=>"string","value"=>"Foo","required"=>true,"meta"=>true];
			$this->attachmentFields[$class]["streetName"] = ["type"=>"string","meta"=>true];
		}

		$this->requestContentType = null;
		$this->requestContent = null;

		// response
		$this->_journalId = $this->journalId??rand();
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
		$content->main->links = new stdClass;
		$content->main->links->aside = new stdClass;
		$content->main->links->aside->href = $this->basePath . $this->_journalId . '/' . $this->classes[0] . '?name=Foo{&streetName}';
		$content->main->links->aside->templated = true;
		
		$content->aside = new stdClass;
		$content->aside->fields = new stdClass;
		$content->aside->fields->name = $this->attachmentFields[$this->classes[0]]["name"];
		$content->aside->fields->streetName = $this->attachmentFields[$this->classes[0]]["streetName"];


		$this->content = json_encode($content, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);

		$this->handle($this->createHyperMedia(), $this->createRequest());
	}
}

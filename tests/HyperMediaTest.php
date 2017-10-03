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
		$this->fields = [];
		$this->fields["name"] = ["type"=>"string","value"=>$this->faker->name,"required"=>true,"meta"=>true];
		$this->fields["streetName"] = ["type"=>"string","value"=>$this->faker->streetName,"required"=>true,"meta"=>true];
		$this->initRequest("1.1","GET",false, $this->fields);
		
		
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
	
	public function testGetExceptionResource()
	{
		// resource
		$this->initResource("TestUnit1",$this->faker->unique()->word,"foo",[],"OK","throw new \Exception('bla bla bla');");
		
		// request
		$this->fields = [];
		$this->fields["name"] = ["type"=>"string","value"=>$this->faker->name,"required"=>true,"meta"=>true];
		$this->fields["streetName"] = ["type"=>"string","value"=>$this->faker->streetName,"required"=>true,"meta"=>true];
		$this->initRequest("1.1","GET",false, $this->fields);
		
		$this->requestContentType = null;
		$this->requestContent = null;
		
		// response
		$this->_journalId = $this->journalId??rand();
		$this->statusCode = 500;
		$this->statusText = "Internal Server Error";
		$this->location = null;
		$this->contentType = "application/json";
		
		$hm = $this->createHyperMedia();
		$request = $this->createRequest();
		
		$response = $hm->handle($this->tags, $request);

		$this->prophet->checkPredictions();
		$this->assertEquals($this->protocolVersion, $response->getProtocolVersion(), "protocol version");
		$this->assertEquals($this->statusCode, $response->getStatusCode(), "status code");
		$this->assertEquals($this->statusText, $response->getStatusText(), "status text");
		$this->assertNull($response->getLocation(), "location");
		$this->assertEquals($this->contentType, $response->getContentType(), "content type");
		
		$content = json_decode($response->getContent(),true);
		$this->assertEquals($content["error"]["message"],"Uncaught exception");
		$this->assertEquals($content["error"]["previous"]["message"],"bla bla bla");
	}
	
	public function testGetNotFoundResource()
	{
		// resource
		$resourceName = $this->faker->unique()->word;
		$this->initResource("TestUnit1",$resourceName,"exception",[],"OK");
		
		// request
		$this->fields = [];
		$this->fields["name"] = ["type"=>"string","value"=>$this->faker->name,"required"=>true,"meta"=>true];
		$this->fields["streetName"] = ["type"=>"string","value"=>$this->faker->streetName,"required"=>true,"meta"=>true];
		$this->initRequest("1.1","GET",false, $this->fields);
		
		
		$this->requestContentType = null;
		$this->requestContent = null;
		
		// response
		$this->_journalId = $this->journalId??rand();
		$this->statusCode = 404;
		$this->statusText = "File Not Found";
		$this->location = null;
		$this->contentType = "application/json";
		$content = new stdClass;
		$content->error = new stdClass;
		$content->error->message = "Resource $resourceName not found.";
		$this->content = json_encode($content, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
		
		$this->handle($this->createHyperMediaWithNullCache(), $this->createErrorRequest());
	}
	
	public function testRequestWithNotAllowedMethod()
	{
		// resource
		$this->initResource("TestUnit1",$this->faker->unique()->word,"exception",[],"MethodNotAllowed");
		
		// request
		$fields = [];
		$this->fields["name"] = ["type"=>"string","value"=>$this->faker->name,"required"=>true,"meta"=>true];
		$this->fields["streetName"] = ["type"=>"string","value"=>$this->faker->streetName,"required"=>true,"meta"=>true];
		$this->initRequest("1.1","Test",false, $fields);
		
		
		$this->requestContentType = null;
		$this->requestContent = null;
		
		// response
		$this->_journalId = $this->journalId??rand();
		$this->statusCode = 405;
		$this->statusText = "Method Not Allowed";
		$this->location = null;
		$this->contentType = "application/json";
		$content = new stdClass;
		$content->error = new stdClass;
		$content->error->message = "Test not allowed.";
		
		$this->content = json_encode($content, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
		
		$this->handle($this->createHyperMediaWithErrorCache(), $this->createErrorRequest());
	}
	
	public function testGetOKResourceWithMetaField()
	{
		// resource
		$this->initResource("TestUnit1", $this->faker->unique()->word, "foo", [], "OK", '$this->name = "sdfsedfsd";');
		
		// request
		$this->fields = [];
		$this->fields["name"] = ["type" => "string"];
		$this->fields["streetName"] = ["type" => "string", "value" => $this->faker->streetName, "required" => true, "meta" => true];
		$this->initRequest("1.1", "GET", false, $this->fields);
		
		$this->requestContentType = null;
		$this->requestContent = null;
		
		// response
		$this->_journalId = $this->journalId ?? rand();
		$this->statusCode = 200;
		$this->statusText = "OK";
		$this->location = null;
		$this->contentType = "application/json";
		$content = new stdClass;
		$content->main = new stdClass;
		if (count($this->fields)) {
			$content->main->fields = $this->fields;
		}
		$content->main->data = new stdClass;
		$content->main->data->name = "sdfsedfsd";
		$this->content = json_encode($content, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
		
		$this->handle($this->createHyperMedia(), $this->createRequest());
	}
	
	public function testGetOKResourceWithDataField()
	{
		// resource
		$this->initResource("TestUnit1", $this->faker->unique()->word, "foo", [], "OK", '$this->name = "sdfsedfsd";');
		
		// request
		$this->fields = [];
		$this->fields["name"] = ["type" => "string", "data" => true];
		$this->initRequest("1.1", "GET", false, $this->fields);
		
		$this->requestContentType = null;
		$this->requestContent = null;
		
		// response
		$this->_journalId = $this->journalId ?? rand();
		$this->statusCode = 200;
		$this->statusText = "OK";
		$this->location = null;
		$this->contentType = "application/json";
		$content = new stdClass;
		$content->main = new stdClass;
		if (count($this->fields)) {
			$content->main->fields = $this->fields;
		}
		$content->main->data = "sdfsedfsd";
		$this->content = json_encode($content, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
		
		$this->handle($this->createHyperMedia(), $this->createRequest());
	}
	
	public function testPostOKResourceWithArrayField()
	{
		// resource
		$this->initResource("TestUnit1", $this->faker->unique()->word, "foo", [], "OK");
		
		// request
		$this->fields = [];
		$this->fields["testArray"] = ["type" => "string", "value" => ["val1","val2"], "array" => true];
		$this->initRequest("1.1", "POST", false, $this->fields);
		
		// response
		$this->_journalId = $this->journalId ?? rand();
		$this->statusCode = 200;
		$this->statusText = "OK";
		$this->location = null;
		$this->contentType = "application/json";
		$content = new stdClass;
		$content->main = new stdClass;
		if (count($this->fields)) {
			$content->main->fields = $this->fields;
		}
		$content->main->data = new stdClass;
		$content->main->data->testArray = ["val1","val2"];
		$this->content = json_encode($content, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
		
		$this->handle($this->createHyperMedia(), $this->createRequest());
	}
	
	public function testPostOKResourceWithMultipleField()
	{
		// resource
		$this->initResource("TestUnit1", $this->faker->unique()->word, "foo", [], "OK");
		
		// request
		$this->fields = [];
		$this->fields["testMultiple"] = ["type" => "string", "value" => "val1,val2,val3,val4", "multiple" => true];
		$this->initRequest("1.1", "POST", false, $this->fields);
		
		// response
		$this->_journalId = $this->journalId ?? rand();
		$this->statusCode = 200;
		$this->statusText = "OK";
		$this->location = null;
		$this->contentType = "application/json";
		$content = new stdClass;
		$content->main = new stdClass;
		if (count($this->fields)) {
			$content->main->fields = $this->fields;
		}
		$content->main->data = new stdClass;
		$content->main->data->testMultiple = "val1,val2,val3,val4";
		$this->content = json_encode($content, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
		
		$this->handle($this->createHyperMedia(), $this->createRequest());
	}
	
	public function testPostOKResource()
	{
		// resource
		$this->initResource("TestUnit1",$this->faker->unique()->word,"bar",[],"OK");
		
		// request
		$this->fields = [];
		$this->fields["name"] = ["type"=>"string","value"=>$this->faker->name,"required"=>true,"meta"=>true];
		$this->fields["streetName"] = ["type"=>"string","value"=>$this->faker->streetName,"required"=>true,"meta"=>true];
		$this->fields["postcode"] = ["type"=>"string","value"=>$this->faker->postcode,"required"=>true,"meta"=>true];
		$this->fields["country"] = ["type"=>"string","value"=>$this->faker->country,"required"=>true,"meta"=>true];
		$this->initRequest("1.1","POST",false, $this->fields);
		
		// response
		$this->_journalId = $this->journalId??rand();
		$this->statusCode = 200;
		$this->statusText = "OK";
		$this->location = null;
		$this->contentType = 'application/json';
		$content = new stdClass;
		
		$content->main = new stdClass;
		$content->main->fields = new stdClass;
		if (count($this->fields)) {
			$content->main->fields = $this->fields;
		}
		
		$this->content = json_encode($content, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
		
		$this->handle($this->createHyperMedia(), $this->createRequest());
	}
	
	public function testPostOKWithMetaResource()
	{
		// resource
		$this->initResource("TestUnit1",$this->faker->unique()->word,"bar",[],"OK");
		
		// request
		$this->fields = [];
		$this->fields["name"] = ["type"=>"string","value"=>$this->faker->name];
		$this->fields["streetName"] = ["type"=>"string","value"=>$this->faker->streetName,"required"=>true,"meta"=>true];
		$this->fields["postcode"] = ["type"=>"string","value"=>$this->faker->postcode];
		$this->fields["country"] = ["type"=>"string","value"=>$this->faker->country,"required"=>true,"meta"=>true];
		
		$content = new stdClass;
		$content->main = $this->initContent($this->fields);

		$this->initRequest("1.1","POST",false, $this->fields);
		
		// response
		$this->_journalId = $this->journalId??rand();
		$this->statusCode = 200;
		$this->statusText = "OK";
		$this->location = null;
		$this->contentType = 'application/json';
		$this->content = json_encode($content, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
		
	$this->handle($this->createHyperMedia(), $this->createRequest());
	}
	
	public function testPostOKResource()
	{
		// resource
		$this->initResource("TestUnit1",$this->faker->unique()->word,"bar",[],"OK");
		
		// request
		$this->fields = [];
		$this->fields["name"] = ["type"=>"string","value"=>$this->faker->name,"required"=>true,"meta"=>true];
		$this->fields["streetName"] = ["type"=>"string","value"=>$this->faker->streetName,"required"=>true,"meta"=>true];
		$this->fields["postcode"] = ["type"=>"string","value"=>$this->faker->postcode,"required"=>true,"meta"=>true];
		$this->fields["country"] = ["type"=>"string","value"=>$this->faker->country,"required"=>true,"meta"=>true];
		$this->initRequest("1.1","POST",false, $this->fields);
		
		// response
		$this->_journalId = $this->journalId??rand();
		$this->statusCode = 200;
		$this->statusText = "OK";
		$this->location = null;
		$this->contentType = 'application/json';
		$content = new stdClass;
		
		$content->main = new stdClass;
		$content->main->fields = new stdClass;
		if (count($this->fields)) {
			$content->main->fields = $this->fields;
		}
		
		$this->content = json_encode($content, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
		
		$this->handle($this->createHyperMedia(), $this->createRequest());
	}
	
	public function testPostResourceWithUnsupportedMediaType()
	{
		// resource
		$this->initResource("TestUnit1",$this->faker->unique()->word,"bar",[],"OK");
		
		// request
		$this->fields = [];
		$this->fields["name"] = ["type"=>"string","value"=>$this->faker->name,"required"=>true,"meta"=>true];
		$this->fields["streetName"] = ["type"=>"string","value"=>$this->faker->streetName,"required"=>true,"meta"=>true];
		$this->fields["postcode"] = ["type"=>"string","value"=>$this->faker->postcode,"required"=>true,"meta"=>true];
		$this->fields["country"] = ["type"=>"string","value"=>$this->faker->country,"required"=>true,"meta"=>true];
		$this->initRequest("1.1","POST",false, $this->fields);
		$this->requestContent = null;
		$this->requestContentType = "application/definitelynotjson";
		
		// response
		$this->_journalId = $this->journalId??rand();
		$this->statusCode = 415;
		$this->statusText = "Unsupported Media Type";
		$this->location = null;
		$this->contentType = 'application/json';
		$content = new stdClass;
		
		$content->error = new stdClass;
		$content->error->message = "Expected media type 'application/json', got '" . $this->requestContentType . "'.";
		
		$this->content = json_encode($content, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
		
		$this->handle($this->createHyperMedia(), $this->createRequest(false));
	}
	
	public function testPostResourceWithBadRequest()
	{
		// resource
		$this->initResource("TestUnit1",$this->faker->unique()->word,"bar",[],"OK");
		
		// request
		$this->fields = [];
		$this->fields["name"] = ["type"=>"string","value"=>$this->faker->name];
		$this->fields["streetName"] = ["type"=>"string","value"=>$this->faker->streetName,"required"=>true,"meta"=>true];
		$this->fields["postcode"] = ["type"=>"string","value"=>$this->faker->postcode];
		$this->fields["country"] = ["type"=>"string","value"=>$this->faker->country,"required"=>true,"meta"=>true];
		$this->initRequest("1.1","POST",false, $this->fields);
		$this->requestContent = $this->faker->word;
		
		// response
		$this->_journalId = $this->journalId??rand();
		$this->statusCode = 400;
		$this->statusText = "Bad Request";
		$this->location = null;
		$this->contentType = 'application/json';
		
		$content = new stdClass;
		$content->error = new stdClass;
		$content->error->message = "The content is not valid JSON.";
		
		$this->content = json_encode($content, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
		
		$this->handle($this->createHyperMedia(), $this->createRequest(false));
	}

	public function testPostNoContentResource()
	{
		// resource
		$this->initResource("TestUnit1",$this->faker->unique()->word,"bar",[],"NoContent");

		// request
		$this->fields = [];
		$this->fields["name"] = ["type"=>"string","value"=>$this->faker->name,"required"=>$this->faker->boolean,"meta"=>$this->verb==="GET"?true:$this->faker->boolean];
		$this->fields["streetName"] = ["type"=>"string","value"=>$this->faker->streetName,"required"=>$this->faker->boolean];
		$this->fields["postcode"] = ["type"=>"string","value"=>$this->faker->postcode,"required"=>$this->faker->boolean];
		$this->fields["country"] = ["type"=>"string","value"=>$this->faker->country,"required"=>$this->faker->boolean];
		$this->initRequest("1.1","POST",false, $this->fields);

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
		$this->fields = [];
		$this->fields["name"] = ["type"=>"string","value"=>$this->faker->name,"required"=>$this->faker->boolean,"meta"=>$this->verb==="GET"?true:$this->faker->boolean];
		$this->fields["streetName"] = ["type"=>"string","value"=>$this->faker->streetName,"required"=>$this->faker->boolean];
		$this->fields["postcode"] = ["type"=>"string","value"=>$this->faker->postcode,"required"=>$this->faker->boolean];
		$this->fields["country"] = ["type"=>"string","value"=>$this->faker->country,"required"=>$this->faker->boolean];
		$this->initRequest("1.1","POST",false, $this->fields);

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
		$this->fields = [];
		$this->fields["name"] = ["type"=>"string","value"=>$this->faker->name, "required"=>$this->faker->boolean,"meta"=>$this->verb==="GET"?true:$this->faker->boolean];
		$this->fields["streetName"] = ["type"=>"string", "value"=>$this->faker->streetName, "required"=>$this->faker->boolean];
		$this->fields["postcode"] = ["type"=>"string", "value"=>$this->faker->postcode, "required"=>$this->faker->boolean];
		$this->fields["country"] = ["type"=>"string", "value"=>$this->faker->country, "required"=>$this->faker->boolean];
		$this->initRequest("1.1","POST",false, $this->fields);

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
		$fields["name"] = ["type"=>"string","value"=>$this->faker->name,"meta"=>true];
		$fields["streetName"] = ["type"=>"string","meta"=>true];
		$fields["postcode"] = ["type"=>"string","value"=>$this->faker->postcode,"meta"=>true];
		$fields["country"] = ["type"=>"string","value"=>$this->faker->country,"meta"=>true];
		$this->initRequest("1.1","GET",false, $fields);

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

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
		$this->setContent("main", $this->class, $this->fields);

		$this->handle($this->createHyperMedia(), $this->createRequest());
	}

	public function testGetExceptionResource()
	{
		// resource
		$this->initResource("TestUnit1",$this->faker->unique()->word,"foo",[],"OK","throw new \Exception('bla bla bla');");

		// request
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
		$this->content = new stdClass;
		$this->content->error = new stdClass;
		$this->content->error->message = "Resource $resourceName not found.";

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
		$this->content = new stdClass;
		$this->content->error = new stdClass;
		$this->content->error->message = "Test not allowed.";

		$this->handle($this->createHyperMediaWithErrorCache(), $this->createErrorRequest());
	}

	public function testGetOKResourceWithSection()
	{
		// resource
		$this->initResource("TestUnit1", $this->faker->unique()->word, "foo", [], "OK");

		// request
		$this->section = "main";
		$this->initRequest("1.1", "GET");

		$this->requestContentType = null;
		$this->requestContent = null;

		// response
		$this->_journalId = $this->journalId ?? rand();
		$this->statusCode = 200;
		$this->statusText = "OK";
		$this->location = null;
		$this->contentType = "application/json";
		$this->setContent("main", $this->class);
		$this->content->main->section = "main";

		$this->handle($this->createHyperMedia(), $this->createRequest());
	}

	public function testGetOKResourceWithMetaField()
	{
		// resource
		$this->initResource("TestUnit1", $this->faker->unique()->word, "foo", [], "OK", '$this->name = "sdfsedfsd";');

		// request
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
		$this->setContent("main", $this->class, $this->fields, ["name" => "sdfsedfsd"]);

		$this->handle($this->createHyperMedia(), $this->createRequest());
	}

	public function testGetOKResourceWithDataField()
	{
		// resource
		$this->initResource("TestUnit1", $this->faker->unique()->word, "foo", [], "OK", '$this->name = "sdfsedfsd";');

		// request
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
		$this->setContent("main", $this->class, $this->fields, "sdfsedfsd");

		$this->handle($this->createHyperMedia(), $this->createRequest());
	}

	public function testGetOKResourceWithDataFieldWithNullValue()
	{
		// resource
		$this->initResource("TestUnit1", $this->faker->unique()->word, "foo", [], "OK", '$this->name = null;');

		// request
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
		$this->setContent("main", $this->class, $this->fields, null);

		$this->handle($this->createHyperMedia(), $this->createRequest());
	}

	public function testPostOKResourceWithArrayField()
	{
		// resource
		$this->initResource("TestUnit1", $this->faker->unique()->word, "foo", [], "OK");

		// request
		$this->fields["testArray"] = ["type" => "string", "array" => true];
		$this->data = ["testArray"=>["val1","val2"]];
		$this->initRequest("1.1", "POST", false, $this->fields);

		// response
		$this->_journalId = $this->journalId ?? rand();
		$this->statusCode = 200;
		$this->statusText = "OK";
		$this->location = null;
		$this->contentType = "application/json";
		$this->setContent("main", $this->class, $this->fields, $this->data);

		$this->handle($this->createHyperMedia(), $this->createRequest());
	}

	public function testPostOKResourceWithArrayFieldWithInvalidType()
	{
		// resource
		$this->initResource("TestUnit1", $this->faker->unique()->word, "foo", [], "OK");

		// request
		$this->fields["testArray"] = ["type" => "string", "array" => true];
		$this->data = ["testArray"=>"val1"];
		$this->initRequest("1.1", "POST", false, $this->fields);

		// response
		$this->_journalId = $this->journalId ?? rand();
		$this->statusCode = 400;
		$this->statusText = "Bad Request";
		$this->location = null;
		$this->contentType = "application/json";
		$this->content = new stdClass;
		$this->content->error = new stdClass;
		$this->content->error->message = "Expected type of testArray is array, string found.";

		$this->handle($this->createHyperMedia(), $this->createRequest());
	}

	public function testPostOKResourceWithMultipleField()
	{
		// resource
		$this->initResource("TestUnit1", $this->faker->unique()->word, "foo", [], "OK");

		// request
		$this->fields["testMultiple"] = ["type" => "string", "multiple" => true];
		$this->data = ["testMultiple"=>"val1,val2,val3,val4"];
		$this->initRequest("1.1", "POST", false, $this->fields);

		// response
		$this->_journalId = $this->journalId ?? rand();
		$this->statusCode = 200;
		$this->statusText = "OK";
		$this->location = null;
		$this->contentType = "application/json";
		$this->setContent("main", $this->class, $this->fields, $this->data);

		$this->handle($this->createHyperMedia(), $this->createRequest());
	}

	public function testPostOKResourceWithRequiredField()
	{
		// resource
		$this->initResource("TestUnit1", $this->faker->unique()->word, "foo", [], "OK");

		// request
		$this->fields["testRequired"] = ["type" => "string", "required" => true];
		$this->data = ["testRequired"=>"value"];
		$this->initRequest("1.1", "POST", false, $this->fields);

		// response
		$this->_journalId = $this->journalId ?? rand();
		$this->statusCode = 200;
		$this->statusText = "OK";
		$this->location = null;
		$this->contentType = "application/json";
		$this->setContent("main", $this->class, $this->fields, $this->data);

		$this->handle($this->createHyperMedia(), $this->createRequest());
	}

	public function testPostOKResourceWithRequiredFieldWithNullValue()
	{
		// resource
		$this->initResource("TestUnit1", $this->faker->unique()->word, "foo", [], "OK");

		// request
		$this->fields["testRequired"] = ["type" => "string", "required" => true];
		$this->data = ["testRequired"=>null];
		$this->initRequest("1.1", "POST", false, $this->fields);

		// response
		$this->_journalId = $this->journalId ?? rand();
		$this->statusCode = 400;
		$this->statusText = "Bad Request";
		$this->location = null;
		$this->contentType = "application/json";
		$this->content = new stdClass;
		$this->content->error = new stdClass;
		$this->content->error->message = "testRequired is required";

		$this->handle($this->createHyperMedia(), $this->createRequest());
	}

	public function testPostOKResourceWithRequiredFieldWithNullOrMissingValues()
	{
		// resource
		$this->initResource("TestUnit1", $this->faker->unique()->word, "foo", [], "OK");

		// request
		$this->fields["testRequiredNull"] = ["type" => "string", "required" => true];
		$this->fields["testRequiredMissing"] = ["type" => "string", "required" => true];
		$this->data = ["testRequiredNull"=>null];
		$this->initRequest("1.1", "POST", false, $this->fields);

		// response
		$this->_journalId = $this->journalId ?? rand();
		$this->statusCode = 400;
		$this->statusText = "Bad Request";
		$this->location = null;
		$this->contentType = "application/json";
		$this->content = new stdClass;
		$this->content->error = new stdClass;
		$this->content->error->messages = ["testRequiredNull is required","testRequiredMissing is required"];

		$this->handle($this->createHyperMedia(), $this->createRequest());
	}

	public function testPostOKResourceWithReadOnlyField()
	{
		// resource
		$this->initResource("TestUnit1",$this->faker->unique()->word,"bar",[],"OK");

		// request
		$this->fields["name"] = ["type"=>"string","readonly" => true, "meta"=>true];
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
		$this->setContent("main", $this->class, $this->fields);

		$this->handle($this->createHyperMedia(), $this->createRequest());
	}

	public function testPostOKResourceWithReadOnlyFieldWithValue()
	{
		// resource
		$this->initResource("TestUnit1",$this->faker->unique()->word,"bar",[],"OK");

		// request
		$this->fields["name"] = ["type"=>"string", "readonly" => true];
		$this->fields["streetName"] = ["type"=>"string","value"=>$this->faker->streetName,"required"=>true,"meta"=>true];
		$this->fields["postcode"] = ["type"=>"string","value"=>$this->faker->postcode,"required"=>true,"meta"=>true];
		$this->fields["country"] = ["type"=>"string","value"=>$this->faker->country,"required"=>true,"meta"=>true];
		$this->data = ["name"=>"abc123"];
		$this->initRequest("1.1","POST",false, $this->fields);

		// response
		$this->_journalId = $this->journalId??rand();
		$this->statusCode = 400;
		$this->statusText = "Bad Request";
		$this->location = null;
		$this->contentType = 'application/json';
		$this->content = new stdClass;
		$this->content->error = new stdClass;
		$this->content->error->message = "name is readonly";

		$this->handle($this->createHyperMedia(), $this->createRequest());
	}

	public function testPostOKResourceWithDisabledField()
	{
		// resource
		$this->initResource("TestUnit1",$this->faker->unique()->word,"bar",[],"OK");

		// request
		$this->fields["name"] = ["type"=>"string","disabled"=>true,"meta"=>true];
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
		$this->setContent("main", $this->class, $this->fields);

		$this->handle($this->createHyperMedia(), $this->createRequest());
	}

	public function testPostOKResourceWithDisabledFieldWithValue()
	{
		// resource
		$this->initResource("TestUnit1",$this->faker->unique()->word,"bar",[],"OK");

		// request
		$this->fields["name"] = ["type"=>"string","value"=>$this->faker->name, "disabled"=>true,"meta"=>true];
		$this->fields["streetName"] = ["type"=>"string","value"=>$this->faker->streetName,"required"=>true,"meta"=>true];
		$this->fields["postcode"] = ["type"=>"string","value"=>$this->faker->postcode,"required"=>true,"meta"=>true];
		$this->fields["country"] = ["type"=>"string","value"=>$this->faker->country,"required"=>true,"meta"=>true];
		$this->initRequest("1.1","POST",false, $this->fields);

		// response
		$this->_journalId = $this->journalId??rand();
		$this->statusCode = 400;
		$this->statusText = "Bad Request";
		$this->location = null;
		$this->contentType = 'application/json';
		$this->content = new stdClass;
		$this->content->error = new stdClass;
		$this->content->error->message = "name is disabled";

		$this->handle($this->createHyperMedia(), $this->createRequest());
	}

	public function testPostOKResource()
	{
		// resource
		$this->initResource("TestUnit1",$this->faker->unique()->word,"bar",[],"OK");

		// request
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
		$this->setContent("main", $this->class, $this->fields);

		$this->handle($this->createHyperMedia(), $this->createRequest());
	}

	public function testPostOKWithMetaResource()
	{
		// resource
		$this->initResource("TestUnit1",$this->faker->unique()->word,"bar",[],"OK");

		// request
		$this->fields["name"] = ["type"=>"string"];
		$this->fields["streetName"] = ["type"=>"string","value"=>$this->faker->streetName,"required"=>true,"meta"=>true];
		$this->fields["postcode"] = ["type"=>"string"];
		$this->fields["country"] = ["type"=>"string","value"=>$this->faker->country,"required"=>true,"meta"=>true];
		$this->data = ["name"=>$this->faker->name, "postcode"=>$this->faker->postcode];

		$this->initRequest("1.1","POST",false, $this->fields);

		// response
		$this->_journalId = $this->journalId??rand();
		$this->statusCode = 200;
		$this->statusText = "OK";
		$this->location = null;
		$this->contentType = 'application/json';
		$this->setContent("main", $this->class, $this->fields, $this->data);

		$this->handle($this->createHyperMedia(), $this->createRequest());
	}

	public function testPostResourceWithUnsupportedMediaType()
	{
		// resource
		$this->initResource("TestUnit1",$this->faker->unique()->word,"bar",[],"OK");

		// request
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
		$this->content = new stdClass;
		$this->content->error = new stdClass;
		$this->content->error->message = "Expected media type 'application/json', got '" . $this->requestContentType . "'.";

		$this->handle($this->createHyperMedia(), $this->createRequest(false));
	}

	public function testPostResourceWithBadRequest()
	{
		// resource
		$this->initResource("TestUnit1",$this->faker->unique()->word,"bar",[],"OK");

		// request
		$this->fields["name"] = ["type"=>"string"];
		$this->fields["streetName"] = ["type"=>"string","value"=>$this->faker->streetName,"required"=>true,"meta"=>true];
		$this->fields["postcode"] = ["type"=>"string"];
		$this->fields["country"] = ["type"=>"string","value"=>$this->faker->country,"required"=>true,"meta"=>true];
		$this->data = ["name"=>$this->faker->name,"postcode"=>$this->faker->postcode];
		$this->initRequest("1.1","POST",false, $this->fields);
		$this->requestContent = $this->faker->word;

		// response
		$this->_journalId = $this->journalId??rand();
		$this->statusCode = 400;
		$this->statusText = "Bad Request";
		$this->location = null;
		$this->contentType = 'application/json';
		$this->content = new stdClass;
		$this->content->error = new stdClass;
		$this->content->error->message = "The content is not valid JSON.";

		$this->handle($this->createHyperMedia(), $this->createRequest(false));
	}

	public function testPostNoContentResource()
	{
		// resource
		$this->initResource("TestUnit1",$this->faker->unique()->word,"bar",[],"NoContent");

		// request
		$this->fields["name"] = ["type"=>"string","required"=>$this->faker->boolean];
		$this->fields["streetName"] = ["type"=>"string","required"=>$this->faker->boolean];
		$this->fields["postcode"] = ["type"=>"string","required"=>$this->faker->boolean];
		$this->fields["country"] = ["type"=>"string","required"=>$this->faker->boolean];
		$this->data = ["name"=>$this->faker->name,"streetName"=>$this->faker->streetName,"postcode"=>$this->faker->postcode,"country"=>$this->faker->country];
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
		$this->fields["name"] = ["type"=>"string","required"=>$this->faker->boolean];
		$this->fields["streetName"] = ["type"=>"string","required"=>$this->faker->boolean];
		$this->fields["postcode"] = ["type"=>"string","required"=>$this->faker->boolean];
		$this->fields["country"] = ["type"=>"string","required"=>$this->faker->boolean];
		$this->data = ["name"=>$this->faker->name,"streetName"=>$this->faker->streetName,"postcode"=>$this->faker->postcode,"country"=>$this->faker->country];
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
		$this->fields["name"] = ["type"=>"string", "required"=>$this->faker->boolean];
		$this->fields["streetName"] = ["type"=>"string", "required"=>$this->faker->boolean];
		$this->fields["postcode"] = ["type"=>"string", "required"=>$this->faker->boolean];
		$this->fields["country"] = ["type"=>"string", "required"=>$this->faker->boolean];
		$this->data = ["name"=>$this->faker->name,"streetName"=>$this->faker->streetName,"postcode"=>$this->faker->postcode,"country"=>$this->faker->country];
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
		\$response->attach("aside", "{$this->classes[0]}", []);
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
		$meta = "";
		if (count($this->fields)) {
			$content->main->fields = new stdClass;
			$meta = "{?";
			$i = 0;
			foreach ($this->fields as $name => $field) {
				if ($i++) $meta.= ",";
				$meta.= $name;
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
			$meta.= "}";
		}
		$content->main->links = new stdClass;
		$content->main->links->aside = new stdClass;
		$content->main->links->aside->href = $this->basePath . $this->_journalId . '/' . $this->classes[0];
		$content->main->links->self = new stdClass;
		$content->main->links->self->href = $this->basePath . $this->_journalId . '/' . $this->class . $meta;
		if ($meta) {
			$content->main->links->self->templated = true;
		}
		$content->aside = new stdClass;
		$content->aside->links = new stdClass;
		$content->aside->links->self = new stdClass;
		$content->aside->links->self->href = $this->basePath . $this->_journalId . '/' . $this->classes[0];

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
		\$response->attach("aside", "{$this->classes[0]}", ["name"=>"Foo"]);
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
		$meta = "";
		if (count($this->fields)) {
			$content->main->fields = new stdClass;
			$meta = "{?";
			$i = 0;
			foreach ($this->fields as $name => $field) {
				if ($i++) $meta.= ",";
				$meta.= $name;
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
			$meta.= "}";
		}
		$content->main->links = new stdClass;
		$content->main->links->self = new stdClass;
		$content->main->links->self->href = $this->basePath . $this->_journalId . '/' . $this->class . $meta;
		if ($meta) {
			$content->main->links->self->templated = true;
		}
		$content->main->links->aside = new stdClass;
		$content->main->links->aside->href = $this->basePath . $this->_journalId . '/' . $this->classes[0] . '?name=Foo{&streetName}';
		$content->main->links->aside->templated = true;

		$content->aside = new stdClass;
		$content->aside->fields = new stdClass;
		$content->aside->fields->name = $this->attachmentFields[$this->classes[0]]["name"];
		$content->aside->fields->streetName = $this->attachmentFields[$this->classes[0]]["streetName"];
		$content->aside->links = new stdClass;
		$content->aside->links->self = new stdClass;
		$content->aside->links->self->href = $this->basePath . $this->_journalId . '/' . $this->classes[0] . '{?name,streetName}';
		$content->aside->links->self->templated = true;


		$this->content = json_encode($content, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);

		$this->handle($this->createHyperMedia(), $this->createRequest());
	}
}

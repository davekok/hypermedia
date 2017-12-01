<?php declare(strict_types=1);

namespace Tests\Sturdy\Activity;

use Sturdy\Activity\Meta\{Field,Type,Resource,ResourceCompiler,TagMatcher};
use PHPUnit\Framework\TestCase;
use Faker;
use stdClass;

class FieldTest extends TestCase
{
	private $faker;

	public function __construct($name = null, array $data = [], $dataName = '')
	{
		parent::__construct($name, $data, $dataName);
		$this->faker = Faker\Factory::create();
	}

	public function testStringField()
	{
		$field = new Field;
		$field->setName("name");
		$field->setDescription("name field");
		$field->parse("required string");
		$field->setType(Type\Type::createType($field->getType()->getDescriptor()));
		$this->assertEquals("name", $field->getName());
		$this->assertEquals("name field", $field->getDescription());
		$flags = $field->getFlags();
		$type = $field->getType();
		$this->assertTrue($flags->isRequired());
		$this->assertTrue($type instanceof Type\StringType);
		$meta = new stdClass;
		$flags->meta($meta);
		$type->meta($meta);
		$this->assertEquals("string", $meta->type??null);
		$this->assertTrue($meta->required??null);
	}

	public function testObjectField()
	{
		$field = new Field;
		$field->setName("person");
		$field->setDescription("person field");
		$field->parse("required data object[] (
			*    firstName: required string 'first name of person' #version1,
			*    firstName: string 'first name of person' #version2,
			*    lastName: required string minlength=0 maxlength=40,
			*    emailAddress: email multiple,
			*    telephone: string #version1,
		)");
		$this->assertEquals("person", $field->getName());
		$this->assertEquals("person field", $field->getDescription());
		$fields = $field->getType()->getFields();
		$this->assertEquals(4, count($fields));
		$this->assertEquals(2, count($fields["firstName"]));
		$this->assertEquals(1, count($fields["lastName"]));
		$this->assertEquals(1, count($fields["emailAddress"]));
		$this->assertEquals(1, count($fields["telephone"]));

		$resource = new Resource("foo", "foo");
		$resource->addField($field);
		foreach ($resource->getTaggables() as $taggable) {
			$taggable->setKeyOrder(["version1", "version2"]);
		}
		$matcher = new TagMatcher(["version2"=>true], ["version1", "version2"]);
		$compiler = new ResourceCompiler;
		$compiler->compile($resource, $matcher);

		$field->setType($type = Type\Type::createType($field->getType()->getDescriptor()));
		$flags = $field->getFlags();

		$this->assertTrue($flags->isRequired());
		$this->assertTrue($type instanceof Type\ObjectType);
		$fields = $type->getFieldDescriptors();
		$this->assertFieldExists($fields, "firstName", "isset firstName");
		$this->assertFieldExists($fields, "lastName", "isset lastName");
		$this->assertFieldExists($fields, "emailAddress", "isset emailAddress");
		$this->assertFieldNotExists($fields, "telephone", "isset telephone");
	}

	private function assertFieldExists($fields, $expected, $message): void
	{
		foreach ($fields as [$name, $type, $defaultValue, $flags, $autocomplete, $label]) {
			if ($name == $expected) return;
		}
		$this->assertTrue(false, $message);
	}

	private function assertFieldNotExists($fields, $expected, $message): void
	{
		foreach ($fields as [$name, $type, $defaultValue, $flags, $autocomplete, $label]) {
			if ($name == $expected) return;
		}
		$this->assertTrue(true, $message);
	}
}

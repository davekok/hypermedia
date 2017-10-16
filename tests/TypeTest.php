<?php declare(strict_types=1);

namespace Tests\Sturdy\Activity;

use Sturdy\Activity\Meta\Type;
use PHPUnit\Framework\TestCase;
use Faker;

class TypeTest extends TestCase
{
	private $faker;
	
	public function __construct($name = null, array $data = [], $dataName = '')
	{
		parent::__construct($name, $data, $dataName);
		$this->faker = Faker\Factory::create();
	}
	
	public function testPasswordType()
	{
		$type = new Type\PasswordType;
		$password = "SqZ2nTFCnM$"; $this->assertTrue($type->filter($password));
		$password = "jhsdfkhsfkjhsfkjhskfjhskjfhskjhfskjhfkshf uihkjsdhfoe hfskjdhf uwef ksjndf ksdfkjh"; $this->assertTrue($type->filter($password));
		$password = "isdjfjdi"; $this->assertFalse($type->filter($password));
		$password = "㗐㗑㗒㗓㗔㗕㗖㗗㗘㗙㗚㗛㗜㗝㗞㗟㗠㗡㗢㗣㗤㗥㗦㗧㗨㗩㗪㗫㗬㗭㗮㗯㗰㗱㗲"; $this->assertTrue($type->filter($password));
		$password = "SqZ2n㗓FCnM$"; $this->assertTrue($type->filter($password));
		$password = "㗐㗑㗒㗓㗔㗕8#"; $this->assertTrue($type->filter($password));
	}
	
	public function testBooleanType()
	{
		$type = new Type\BooleanType();
		$boolean = "123"; $this->assertFalse($type->filter($boolean));
		$boolean = 123; $this->assertFalse($type->filter($boolean));
		$boolean = "false"; $this->assertTrue($type->filter($boolean)); $this->assertTrue($boolean === false);
		$boolean = false; $this->assertTrue($type->filter($boolean));
	}
	
	public function testColorType()
	{
		$type = new Type\ColorType();
		$color = "#fff"; $this->assertTrue($type->filter($color));
		$color = "#fFfF"; $this->assertTrue($type->filter($color));
		$color = "#ffffff"; $this->assertTrue($type->filter($color));
		$color = "#ffffffff"; $this->assertTrue($type->filter($color));
		$color = "#zfffff"; $this->assertFalse($type->filter($color));
		$color = $this->faker->boolean; $this->assertFalse($type->filter($color));
		$color = "fff123"; $this->assertFalse($type->filter($color));
		$color = false; $this->assertFalse($type->filter($color));
	}
	
	public function testDateTimeType()
	{
		$type = new Type\DateTimeType();
		$datetime = "1995-09-03T23:59:59Z"; $this->assertTrue($type->filter($datetime));
		$datetime = "1995-09-03 23:59:59"; $this->assertFalse($type->filter($datetime));
		$datetime = "1995-09-03T23:59:"; $this->assertFalse($type->filter($datetime));
		$datetime = true; $this->assertFalse($type->filter($datetime));
	}
	
	// TODO: All Below
	public function testDateType()
	{
		$type = new Type\DateType();
		$datetime = "1995-09-03"; $this->assertTrue($type->filter($datetime));
		$datetime = "1995-13-03"; $this->assertFalse($type->filter($datetime));
		$datetime = 1; $this->assertFalse($type->filter($datetime));
	}
	
	public function testDayType()
	{
		$type = new Type\DayType();
		$day = "31"; $this->assertTrue($type->filter($day));
		$day = "01"; $this->assertTrue($type->filter($day));
		$day = "2"; $this->assertTrue($type->filter($day));
		$day = 2; $this->assertFalse($type->filter($day));
	}
	
	public function testEmailType()
	{
		$type = new Type\EmailType();
		$email = "firstname_lastname@gmail.com"; $this->assertTrue($type->filter($email));
		$email = "firstname.lastname@hotmail.com"; $this->assertTrue($type->filter($email));
		$email = "firstname-lastname@www.weirdsubdomain.com"; $this->assertTrue($type->filter($email));
		$email = "firstname-lastname2hotmail.com"; $this->assertFalse($type->filter($email));
		$email = "firstname"; $this->assertFalse($type->filter($email));
		$email = 2; $this->assertFalse($type->filter($email));
	}
	
	public function testEnumType()
	{
		$type = new Type\EnumType();
		$type->addOption("a");
		$type->addOption("b");
		$type->addOption("c");
		$choice = "b";
		$this->assertTrue($type->filter($choice));
		$choice = "d";
		$this->assertFalse($type->filter($choice));
	}
	
	public function testFloatType()
	{
		$type = new Type\FloatType();
		
	}
	
	public function testHTMLType()
	{
		$type = new Type\HTMLType();
		$html = $this->faker->randomHtml(); $this->assertTrue($type->filter($html));
		
	}
	
	public function testIntegerType()
	{
		$type = new Type\IntegerType();
		
	}
	
	public function testMonthType()
	{
		$type = new Type\MonthType();
		
	}
	
	public function testObjectType()
	{
		$type = new Type\ObjectType();
		
	}
	
	public function testSetType()
	{
		$type = new Type\SetType();
		
	}
	
	public function testStringType()
	{
		$type = new Type\StringType();
		
	}
	
	public function testTimeType()
	{
		$type = new Type\TimeType();
		
	}
	
	public function testURLType()
	{
		$type = new Type\URLType();
		
	}
	
	public function testUUIDType()
	{
		$type = new Type\UUIDType();
		
	}
	
	public function testWeekDayType()
	{
		$type = new Type\WeekDayType();
		
	}
	
	public function testWeekType()
	{
		$type = new Type\WeekType();
		
	}
	
	public function testYearType()
	{
		$type = new Type\YearType();
		
	}
}

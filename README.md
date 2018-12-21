# Sturdy Activity

Sturdy Activity is a project to help developers write great micro services. Currently it
features two essential components. The HyperMedia component helping developers to write
a HyperMedia API and the Activity component which is aimed to help developers write complex
long running backend activities.

Currently the HyperMedia component is in good fashion and very useable, though documentation
is lacking. The Activity component is still work in progress.



## HyperMedia

The HyperMedia class is equiped with several adaptor factory functions. For instance if you
wish to use the Symfony framework you can use the HyperMedia::createSymfonyAdaptor to easily
work with that framework.

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
	 */
	public function get(\Sturdy\Activity\Response\OK $response): void
	{
		$this->name = "Patrick";
	}

	/**
	 * The post verb for this resouce.
	 *
	 * @Post
	 * @param  OK $response    the response
	 */
	public function post(\Sturdy\Activity\Response\OK $response): void
	{
		$name = $this->name; // $this->name is automatically filled from POST body
	}
}

```

```php

HyperMedia::createEchoAdaptor(
	new MySharedStateStore(),
	new MyCache(),
	new MyTranslator(),
	new MyJsonDeserializer(),
	"MySourceUnit",
	"/api/",
	"MyNameSpace"
)->handle($_SERVER)->send()

```

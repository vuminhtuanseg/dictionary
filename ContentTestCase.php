<?php

/**
 * Class ContentTestCase
 */
abstract class ContentTestCase extends Laravel\BrowserKitTesting\TestCase
{
    use \Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

    public $locale = 'en-GB';

    /**
     * The base URL to use while testing the application.
     *
     * @var string
     */
    protected $baseUrl = 'http://localhost';

    /**
     * Creates the application.
     *
     * @return \Illuminate\Foundation\Application
     */
    public function createApplication()
    {
        $app = require __DIR__.'/../bootstrap/app.php';

        $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

        return $app;
    }

    protected function tearDown(): void
    {
        \Mockery::close();

        // Optimize n Speedup
        $refl = new ReflectionObject($this);
        foreach ($refl->getProperties() as $prop) {
            if (!$prop->isStatic() && 0 !== strpos($prop->getDeclaringClass()->getName(), 'PHPUnit_')) {
                $prop->setAccessible(true);
                $prop->setValue($this, null);
            }
        }

        parent::tearDown();
    }

    public function getEntryStub()
    {
        return json_decode(file_get_contents(__DIR__."/_stubs/contentful/entry.json"), true);
    }

    public function getEntryFormattedStub()
    {
        return json_decode(file_get_contents(__DIR__."/_stubs/contentful/entry_formatted.json"), true);
    }

    public function getPageEntryStub()
    {
        return json_decode(file_get_contents(__DIR__."/_stubs/contentful/page_entry.json"), true);
    }

    public function getPostEntryStub()
    {
        return json_decode(file_get_contents(__DIR__."/_stubs/contentful/post_entry.json"), true);
    }

    public function getSimpleEntryStub()
    {
        return json_decode(file_get_contents(__DIR__."/_stubs/contentful/simple_entry.json"), true);
    }

    public function getComplexEntryStub()
    {
        return json_decode(file_get_contents(__DIR__."/_stubs/contentful/complex_entry.json"), true);
    }

    public function getComplexEntryCompletedStub()
    {
        return json_decode(file_get_contents(__DIR__."/_stubs/contentful/complex_entry_completed.json"), true);
    }

    public function getComplexEntryMissingLinksCompletedStub()
    {
        return json_decode(file_get_contents(__DIR__."/_stubs/contentful/complex_entry_missing_links_completed.json"), true);
    }

    public function getComplexEntryLinkStub($linkNumber)
    {
        return json_decode(file_get_contents(__DIR__."/_stubs/contentful/complex_entry_link_{$linkNumber}.json"), true);
    }

    public function getComplexEntryStubWithDepth($depth, $formatted = false)
    {
        return $formatted ?
            json_decode(file_get_contents(__DIR__."/_stubs/contentful/complex_entry_depth_{$depth}_formatted.json"), true) :
            json_decode(file_get_contents(__DIR__."/_stubs/contentful/complex_entry_depth_{$depth}.json"), true);
    }

    public function getComplexEntryLinkStubWithDepth($depth, $link)
    {
        return json_decode(file_get_contents(__DIR__."/_stubs/contentful/complex_entry_depth_{$depth}_link_{$link}.json"), true);
    }

    public function mockEntry($contentType = 'entryContentType', $attributes)
    {
        $fieldClassArray = [];
        $fields = [];
        foreach (Arr::get($attributes, 'fields', []) as $key => $value) {
            $fieldClassArray[$key] = $this->hydrateClass(\Contentful\Delivery\Resource\ContentType\Field::class, [$this->locale => $value, 'id' => $key, 'type' => 'Symbol']);
            $fields[$key] = [$this->locale => $value];
        }

        $sys = $this->hydrateClass(Contentful\Delivery\SystemProperties\Entry::class, $this->getSysProperties($contentType, $attributes, $fieldClassArray));

        return $this->hydrateClass(\Contentful\Delivery\Resource\Entry::class,
            ['sys' => $sys, 'localeCode' => $this->locale, 'localeCodes' => [$this->locale], 'fields' => $fields]);
    }

    public function mockAsset($contentType = 'assertContentType', $attributes)
    {
        $sys = $this->hydrateClass(Contentful\Delivery\SystemProperties\Asset::class, $this->getSysProperties($contentType, $attributes));

        return $this->hydrateClass(\Contentful\Delivery\Resource\Asset::class, compact('sys') + ['localeCode' => $this->locale, 'localeCodes' => [$this->locale]]);
    }

    /**
     * @param  string  $class
     * @param  array  $properties
     * @return object
     * @throws ReflectionException
     */
    protected function hydrateClass(string $class, array $properties): object
    {
        $target = (new \ReflectionClass($class))->newInstanceWithoutConstructor();
        $closure = \Closure::bind(function ($object, array $properties) {
            foreach ($properties as $property => $value) {
                $object->$property = $value;
            }
        }, \null, $class);
        $closure($target, $properties);

        return $target;
    }

    /**
     * @param $contentType
     * @param $attributes
     * @param  array  $fields
     * @return array
     * @throws ReflectionException
     */
    protected function getSysProperties($contentType, $attributes, $fields = []): array
    {
        $environmentSys = $this->hydrateClass(Contentful\Delivery\SystemProperties\Environment::class, ['id' => '1', 'type' => 'environment', 'name' => 'master']);
        $environment = $this->hydrateClass(Contentful\Delivery\Resource\Environment::class, ['sys' => $environmentSys]);
        $spaceSys = $this->hydrateClass(Contentful\Delivery\SystemProperties\Space::class, ['id' => '1', 'type' => 'space', 'name' => 'master']);
        $space = $this->hydrateClass(Contentful\Delivery\Resource\Space::class, ['sys' => $spaceSys]);
        $contentTypeSys = $this->hydrateClass(Contentful\Delivery\SystemProperties\ContentType::class,
            ['id' => $contentType, 'type' => 'contentType', 'name' => 'master', 'environment' => $environment, 'space' => $space]);
        return [
            'id'          => $attributes['id'],
            'type'        => $contentType,
            'createdAt'   => new \DateTimeImmutable('2013-06-27T22:46:12.852Z'),
            'updatedAt'   => new \DateTimeImmutable('2013-06-27T22:46:12.852Z'),
            'revision'    => 1,
            'space'       => $space,
            'locale'      => 'en-GB',
            'environment' => $environment,
            'contentType' => $this->hydrateClass(Contentful\Delivery\Resource\ContentType::class, ['sys' => $contentTypeSys, 'fields' => $fields])
        ];
    }
}

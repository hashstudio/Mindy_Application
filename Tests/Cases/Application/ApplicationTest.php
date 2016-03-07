<?php

namespace Mindy\Application\Tests;

class ApplicationTest extends TestCase
{
    public function testSimple()
    {
        $config = [
            'basePath' => __DIR__ . '/../../app',
            'params' => [
                'foo' => 'bar'
            ],
        ];
        $app = new Application($config);

        // Unique id test
        $this->assertNotNull($app->getId());

        // Base paths test
        $this->assertEquals(realpath(__DIR__ . '/../../app'), $app->getBasePath());
        $this->assertEquals(realpath(__DIR__ . '/../../app/Modules'), $app->getModulePath());
        $this->assertEquals(realpath(__DIR__ . '/../../app/runtime'), $app->getRuntimePath());

        // Params test
        $this->assertEquals(['foo' => 'bar'], $app->getParams()->all());

        // Timezones test
        $this->assertEquals(date_default_timezone_get(), $app->getTimeZone());
        $app->setTimeZone('UTC');
        $this->assertEquals('UTC', $app->getTimeZone());

        // Test Translate component
        $this->assertInstanceOf('\Mindy\Locale\Translate', $app->getTranslate());
    }
}

<?php
/**
 * Copyright 2010-2013 Amazon.com, Inc. or its affiliates. All Rights Reserved.
 *
 * Licensed under the Apache License, Version 2.0 (the "License").
 * You may not use this file except in compliance with the License.
 * A copy of the License is located at
 *
 * http://aws.amazon.com/apache2.0
 *
 * or in the "license" file accompanying this file. This file is distributed
 * on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either
 * express or implied. See the License for the specific language governing
 * permissions and limitations under the License.
 */

namespace Aws\Tests\Common\Client;

use Aws\Common\Client\ClientBuilder;
use Aws\Common\Exception\Parser\JsonQueryExceptionParser;
use Aws\Common\Credentials\Credentials;
use Aws\Common\Enum\ClientOptions as Options;
use Guzzle\Common\Collection;
use Guzzle\Plugin\Backoff\BackoffPlugin;

/**
 * Note: The tests for the build method do not mock anything
 *
 * @covers Aws\Common\Client\ClientBuilder
 */
class ClientBuilderTest extends \Guzzle\Tests\GuzzleTestCase
{
    protected $dynamoDbDescription;
    protected $stsDescription;

    public function setUp()
    {
        $this->dynamoDbDescription = __DIR__ . '/../../../../../src/Aws/DynamoDb/Resources/dynamodb-2011-12-05.php';
        $this->stsDescription = __DIR__ . '/../../../../../src/Aws/Sts/Resources/sts-2011-06-15.php';
    }

    public function testBuild()
    {
        $client = ClientBuilder::factory('Aws\\DynamoDb')
            ->setConfig(array())
            ->setConfigDefaults(array(
                'scheme'   => 'https',
                'region'   => 'us-east-1',
                'service'  => 'dynamodb',
                'service.description' => $this->dynamoDbDescription
            ))
            ->setConfigRequirements(array('scheme'))
            ->setExceptionParser(new JsonQueryExceptionParser())
            ->setIteratorsConfig(array('token_param' => 'foo'))
            ->build();

        $this->assertInstanceOf('Aws\DynamoDb\DynamoDbClient', $client);
    }

    public function testUsesGlobalEndpoint()
    {
        $client = ClientBuilder::factory('Aws\\Sts')
            ->setConfig(array())
            ->setConfigDefaults(array(
                'service' => 'sts',
                'service.description' => $this->stsDescription
            ))
            ->build();

        $this->assertInstanceOf('Aws\Sts\StsClient', $client);
    }

    public function testBuildAlternate()
    {
        $client = ClientBuilder::factory('Aws\\DynamoDb')
            ->setConfigDefaults(array(
                'scheme'  => 'https',
                'region'  => 'us-west-1',
                'service' => 'dynamodb',
                'service.description' => $this->dynamoDbDescription,
                'credentials' => Credentials::factory(array('key' => 'foo', 'secret' => 'bar')),
                'client.backoff' => BackoffPLugin::getExponentialBackoff()
            ))
            ->build();

        $this->assertInstanceOf('Aws\DynamoDb\DynamoDbClient', $client);
    }

    public function getDataForProcessArrayTest()
    {
        return array(
            array(
                array('foo' => 'bar', 'bar' => 'baz'),
                array('foo' => 'bar', 'bar' => 'baz'),
            ),
            array(
                new Collection(array('foo' => 'bar', 'bar' => 'baz')),
                array('foo' => 'bar', 'bar' => 'baz'),
            ),
            array(
                'foo',
                null
            )
        );
    }

    /**
     * @dataProvider getDataForProcessArrayTest
     */
    public function testProcessArrayProcessesCorrectly($input, $expected)
    {
        $builder = ClientBuilder::factory('Aws\\DynamoDb');

        try {
            $builder->setConfig($input);
            $actual = $this->readAttribute($builder, 'config');
        } catch (\InvalidArgumentException $e) {
            $actual = null;
        }

        $this->assertEquals($expected, $actual);
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage No regions found in the
     */
    public function testEnsuresDescriptionsContainRegions()
    {
        ClientBuilder::factory('Aws\\DynamoDb')
            ->setConfig(array('service.description' => array('signatureVersion' => 'v2')))
            ->build();
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage does not specify a valid signatureVersion
     */
    public function testEnsuresSignatureIsProvided()
    {
        ClientBuilder::factory('Aws\\DynamoDb')
            ->setConfig(array(
                'region'  => 'us-west-1',
                'service' => 'dynamodb',
                'scheme'  => 'http',
                'service.description' => array(
                    'regions' => array('us-west-1' => array('hostname' => 'foo', 'http' => true))
                )
            ))
            ->build();
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage A region is required
     */
    public function testEnsuresExceptionIsThrownWhenMissingRequiredRegion()
    {
        ClientBuilder::factory('Aws\\DynamoDb')
            ->setConfig(array(
                'service' => 'dynamodb',
                'service.description' => array(
                    'signatureVersion' => 'v2',
                    'regions' => array('us-east-1' => array())
                )
            ))
            ->build();
    }

    public function testAddsDefaultCredentials()
    {
        $creds = Credentials::factory(array('key' => 'foo', 'secret' => 'bar'));
        $config = array(
            'service' => 'dynamodb',
            'region'  => 'us-east-1',
            'credentials' => $creds,
            'service.description' => array(
                'signatureVersion' => 'v2',
                'regions' => array('us-east-1' => array('https' => true, 'hostname' => 'foo.com'))
            )
        );

        // Ensure that default credentials are set
        $client = ClientBuilder::factory('Aws\\DynamoDb')->setConfig($config)->build();
        $this->assertSame($creds, $client->getCredentials());

        // Ensure that specific credentials can be used
        $config['credentials'] = null;
        $client = ClientBuilder::factory('Aws\\DynamoDb')->setConfig($config)->build();
        $this->assertInstanceOf('Aws\Common\Credentials\RefreshableInstanceProfileCredentials', $client->getCredentials());
    }

    public function testAddsDefaultBackoffPluginIfNeeded()
    {
        $config = array(
            'service' => 'dynamodb',
            'region'  => 'us-east-1',
            'service.description' => array(
                'signatureVersion' => 'v2',
                'regions' => array('us-east-1' => array('https' => true, 'hostname' => 'foo.com'))
            )
        );

        // Ensure that a default plugin is set
        $client = ClientBuilder::factory('Aws\\DynamoDb')->setConfig($config)->build();
        $this->assertInstanceOf('Guzzle\Plugin\Backoff\BackoffPlugin', $client->getConfig(Options::BACKOFF));
        // Ensure that the plugin is set
        $this->assertTrue($this->hasSubscriber($client, $client->getConfig(Options::BACKOFF)));

        // Ensure that a specific plugin can be used
        $plugin = BackoffPlugin::getExponentialBackoff();
        $config[Options::BACKOFF] = $plugin;
        $client = ClientBuilder::factory('Aws\\DynamoDb')->setConfig($config)->build();
        $this->assertSame($plugin, $client->getConfig(Options::BACKOFF));
        // Ensure that the plugin is set
        $this->assertTrue($this->hasSubscriber($client, $plugin));
    }

    public function testUsesBackoffLoggerWithDebug()
    {
        $config = array(
            'service' => 'dynamodb',
            'region'  => 'us-east-1',
            'service.description' => array(
                'signatureVersion' => 'v2',
                'regions' => array('us-east-1' => array('https' => true, 'hostname' => 'foo.com'))
            ),
            Options::BACKOFF_LOGGER => 'debug',
            Options::BACKOFF_LOGGER_TEMPLATE => '[{ts}] {url}'
        );
        $client = ClientBuilder::factory('Aws\\DynamoDb')->setConfig($config)->build();
        $plugin = $client->getConfig(Options::BACKOFF);
        $this->assertInstanceOf('Guzzle\Plugin\Backoff\BackoffPlugin', $plugin);
        $subscribers = $plugin->getEventDispatcher()->getListeners('plugins.backoff.retry');
        $this->assertInstanceOf('Guzzle\Plugin\Backoff\BackoffLogger', $subscribers[0][0]);
        $logger = $subscribers[0][0];
        $formatter = $this->readAttribute($logger, 'formatter');
        $this->assertEquals('[{ts}] {url}', $this->readAttribute($formatter, 'template'));
    }
}

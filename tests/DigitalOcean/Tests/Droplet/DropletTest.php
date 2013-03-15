<?php

/**
 * This file is part of the DigitalOcean library.
 *
 * (c) Antoine Corcy <contact@sbin.dk>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace DigitalOcean\Tests\Droplet;

use DigitalOcean\Tests\TestCase;
use DigitalOcean\Droplet\Droplet;
use DigitalOcean\Droplet\DropletActions;

/**
 * @author Antoine Corcy <contact@sbin.dk>
 */
class DropletTest extends TestCase
{
    protected $dropletId;
    protected $droplet;
    protected $dropletBuildQueryMethod;

    protected function setUp()
    {
        $this->dropletId = 123;

        $this->droplet = new Droplet($this->clientId, $this->apiKey, $this->getMockAdapter($this->never()));
        $this->dropletBuildQueryMethod = new \ReflectionMethod(
            $this->droplet, 'buildQuery'
        );
        $this->dropletBuildQueryMethod->setAccessible(true);
    }

    public function testShowAllActiveUrl()
    {
        $this->assertEquals(
            'https://api.digitalocean.com/droplets/?client_id=foo&api_key=bar',
            $this->dropletBuildQueryMethod->invoke($this->droplet)
        );
    }

    public function testShowAllActive()
    {
        $response = <<<JSON
{"status":"OK","droplets":[{"backups_active":null,"id":123,"image_id":420,"name":"test123","region_id":1,"size_id":33,"status":"active","ip_address":"127.0.0.1"},{"backups_active":1,"id":456,"image_id":420,"name":"test456","region_id":1,"size_id":33,"status":"active","ip_address":"127.0.0.1"}]}
JSON
        ;

        $droplet  = new Droplet($this->clientId, $this->apiKey, $this->getMockAdapterReturns($response));
        $droplets = $droplet->showAllActive();

        $this->assertTrue(is_object($droplets));
        $this->assertEquals('OK', $droplets->status);
        $this->assertCount(2, $droplets->droplets);

        $droplet1 = $droplets->droplets[0];
        $this->assertNull($droplet1->backups_active);
        $this->assertSame(123, $droplet1->id);
        $this->assertSame(420, $droplet1->image_id);
        $this->assertSame('test123', $droplet1->name);
        $this->assertSame(1, $droplet1->region_id);
        $this->assertSame(33, $droplet1->size_id);
        $this->assertSame('active', $droplet1->status);
        $this->assertSame('127.0.0.1', $droplet1->ip_address);

        $droplet1 = $droplets->droplets[1];
        $this->assertSame(1, $droplet1->backups_active);
        $this->assertSame(456, $droplet1->id);
        $this->assertSame(420, $droplet1->image_id);
        $this->assertSame('test456', $droplet1->name);
        $this->assertSame(1, $droplet1->region_id);
        $this->assertSame(33, $droplet1->size_id);
        $this->assertSame('active', $droplet1->status);
        $this->assertSame('127.0.0.1', $droplet1->ip_address);
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Unable to verify credentials. #414LB
     */
    public function testShowAllActiveThrowsInvalidArgumentException()
    {
        $response = <<<JSON
{"status":"ERROR","description":"Unable to verify credentials. #414LB"}
JSON
        ;

        $droplet  = new Droplet($this->clientId, $this->apiKey, $this->getMockAdapterReturns($response));
        $droplet->showAllActive();
    }

    public function testShowAllActiveWithCredentials()
    {
        if (!isset($_SERVER['CLIENT_ID']) || !isset($_SERVER['API_KEY'])) {
            $this->markTestSkipped('You need to configure the CLIENT_ID and API_KEY values in phpunit.xml');
        }

        $droplet  = new Droplet($_SERVER['CLIENT_ID'], $_SERVER['API_KEY'], new \DigitalOcean\HttpAdapter\CurlHttpAdapter());
        $droplets = $droplet->showAllActive();

        $this->assertTrue(is_object($droplets));
        $this->assertEquals('OK', $droplets->status);
        $this->assertCount(count($droplets->droplets), $droplets->droplets);

        $firstDroplet = $droplets->droplets[0];
        $this->assertObjectHasAttribute('id', $firstDroplet);
        $this->assertObjectHasAttribute('name', $firstDroplet);
        $this->assertObjectHasAttribute('image_id', $firstDroplet);
        $this->assertObjectHasAttribute('size_id', $firstDroplet);
        $this->assertObjectHasAttribute('region_id', $firstDroplet);
        $this->assertObjectHasAttribute('backups_active', $firstDroplet);
        $this->assertObjectHasAttribute('ip_address', $firstDroplet);
        $this->assertObjectHasAttribute('status', $firstDroplet);
    }

    public function testShowUrl()
    {
        $this->assertEquals(
            'https://api.digitalocean.com/droplets/123/?client_id=foo&api_key=bar',
            $this->dropletBuildQueryMethod->invoke($this->droplet, $this->dropletId)
        );
    }

    public function testShow()
    {
        $response = <<<JSON
{"status":"OK","droplets":[{"backups_active":1,"id":123,"image_id":420,"name":"test123","region_id":1,"size_id":33,"status":"active","ip_address":"127.0.0.1"}]}
JSON
        ;

        $droplet = new Droplet($this->clientId, $this->apiKey, $this->getMockAdapterReturns($response));
        $droplet = $droplet->show($this->dropletId)->droplets[0];

        $this->assertSame(1, $droplet->backups_active);
        $this->assertSame($this->dropletId, $droplet->id);
        $this->assertSame(420, $droplet->image_id);
        $this->assertSame('test123', $droplet->name);
        $this->assertSame(1, $droplet->region_id);
        $this->assertSame(33, $droplet->size_id);
        $this->assertSame('active', $droplet->status);
        $this->assertSame('127.0.0.1', $droplet->ip_address);
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage No Droplets Found
     */
    public function testShowThrowsRuntimeException()
    {
        $response = <<<JSON
{"status":"ERROR","error_message":"No Droplets Found"}
JSON
        ;

        $droplet = new Droplet($this->clientId, $this->apiKey, $this->getMockAdapterReturns($response));
        $droplet->show($this->dropletId);
    }

    public function testCreateUrl()
    {
        $newDroplet = array(
            'name'        => 'MyNewDroplet',
            'size_id'     => 111,
            'image_id'    => 222,
            'region_id'   => 333,
            'ssh_key_ids' => 'MySshKeyId1,MySshKeyId2',
        );

        $this->assertEquals(
            'https://api.digitalocean.com/droplets/new/?name=MyNewDroplet&size_id=111&image_id=222&region_id=333&ssh_key_ids=MySshKeyId1%2CMySshKeyId2&client_id=foo&api_key=bar',
            $this->dropletBuildQueryMethod->invoke($this->droplet, null, DropletActions::ACTION_NEW, $newDroplet)
        );
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage A new droplet must have a string "name".
     */
    public function testCreateThrowsNameInvalidArgumentException()
    {
        $this->droplet->create(array());
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage A new droplet must have an integer "size_id".
     */
    public function testCreateThrowsSizeIdInvalidArgumentException()
    {
        $this->droplet->create(array(
            'name' => 'MyNewDroplet',
        ));
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage A new droplet must have an integer "image_id".
     */
    public function testCreateThrowsImageIdInvalidArgumentException()
    {
        $this->droplet->create(array(
            'name'    => 'MyNewDroplet',
            'size_id' => 123,
        ));
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage A new droplet must have an integer "region_id".
     */
    public function testCreateThrowsRegionIdInvalidArgumentException()
    {
        $this->droplet->create(array(
            'name'     => 'MyNewDroplet',
            'size_id'  => 123,
            'image_id' => 456,
        ));
    }

    public function testCreate()
    {
        $response = <<<JSON
{"status":"OK","droplet":{"id":100824,"name":"MyNewDroplet","image_id":222,"size_id":111,"region_id":333,"event_id":7499}}
JSON
        ;

        $droplet = new Droplet($this->clientId, $this->apiKey, $this->getMockAdapterReturns($response));
        $createdDroplet = $droplet->create(array(
            'name'        => 'MyNewDroplet',
            'size_id'     => 111,
            'image_id'    => 222,
            'region_id'   => 333,
        ));

        $this->assertTrue(is_object($createdDroplet));
        $this->assertEquals('OK', $createdDroplet->status);

        $createdDroplet = $createdDroplet->droplet;
        $this->assertSame(100824, $createdDroplet->id);
        $this->assertSame('MyNewDroplet', $createdDroplet->name);
        $this->assertSame(111, $createdDroplet->size_id);
        $this->assertSame(222, $createdDroplet->image_id);
        $this->assertSame(333, $createdDroplet->region_id);
        $this->assertSame(7499, $createdDroplet->event_id);
    }

    public function testRebootUrl()
    {
        $this->assertEquals(
            'https://api.digitalocean.com/droplets/123/reboot/?client_id=foo&api_key=bar',
            $this->dropletBuildQueryMethod->invoke(
                $this->droplet, $this->dropletId, DropletActions::ACTION_REBOOT
            )
        );
    }

    public function testReboot()
    {
        $response = <<<JSON
{"status":"OK","event_id":7501}
JSON
        ;

        $droplet = new Droplet($this->clientId, $this->apiKey, $this->getMockAdapterReturns($response));
        $droplet = $droplet->reboot($this->dropletId);

        $this->assertTrue(is_object($droplet));
        $this->assertEquals('OK', $droplet->status);
        $this->assertSame(7501, $droplet->event_id);
    }

    public function testPowerCycleUrl()
    {
        $this->assertEquals(
            'https://api.digitalocean.com/droplets/123/power_cycle/?client_id=foo&api_key=bar',
            $this->dropletBuildQueryMethod->invoke(
                $this->droplet, $this->dropletId, DropletActions::ACTION_POWER_CYCLE
            )
        );
    }

    public function testPowerCycle()
    {
        $response = <<<JSON
{"status":"OK","event_id":7501}
JSON
        ;

        $droplet = new Droplet($this->clientId, $this->apiKey, $this->getMockAdapterReturns($response));
        $droplet = $droplet->powerCycle($this->dropletId);

        $this->assertTrue(is_object($droplet));
        $this->assertEquals('OK', $droplet->status);
        $this->assertSame(7501, $droplet->event_id);
    }

    public function testShutdownUrl()
    {
        $this->assertEquals(
            'https://api.digitalocean.com/droplets/123/shutdown/?client_id=foo&api_key=bar',
            $this->dropletBuildQueryMethod->invoke(
                $this->droplet, $this->dropletId, DropletActions::ACTION_SHUTDOWN
            )
        );
    }

    public function testShutdown()
    {
        $response = <<<JSON
{"status":"OK","event_id":7501}
JSON
        ;

        $droplet = new Droplet($this->clientId, $this->apiKey, $this->getMockAdapterReturns($response));
        $droplet = $droplet->shutdown($this->dropletId);

        $this->assertTrue(is_object($droplet));
        $this->assertEquals('OK', $droplet->status);
        $this->assertSame(7501, $droplet->event_id);
    }

    public function testPowerOnUrl()
    {
        $this->assertEquals(
            'https://api.digitalocean.com/droplets/123/power_on/?client_id=foo&api_key=bar',
            $this->dropletBuildQueryMethod->invoke(
                $this->droplet, $this->dropletId, DropletActions::ACTION_POWER_ON
            )
        );
    }

    public function testPowerOn()
    {
        $response = <<<JSON
{"status":"OK","event_id":7501}
JSON
        ;

        $droplet = new Droplet($this->clientId, $this->apiKey, $this->getMockAdapterReturns($response));
        $droplet = $droplet->powerOn($this->dropletId);

        $this->assertTrue(is_object($droplet));
        $this->assertEquals('OK', $droplet->status);
        $this->assertSame(7501, $droplet->event_id);
    }

    public function testPowerOffUrl()
    {
        $this->assertEquals(
            'https://api.digitalocean.com/droplets/123/power_off/?client_id=foo&api_key=bar',
            $this->dropletBuildQueryMethod->invoke(
                $this->droplet, $this->dropletId, DropletActions::ACTION_POWER_OFF
            )
        );
    }

    public function testPowerOff()
    {
        $response = <<<JSON
{"status":"OK","event_id":7501}
JSON
        ;

        $droplet = new Droplet($this->clientId, $this->apiKey, $this->getMockAdapterReturns($response));
        $droplet = $droplet->powerOff($this->dropletId);

        $this->assertTrue(is_object($droplet));
        $this->assertEquals('OK', $droplet->status);
        $this->assertSame(7501, $droplet->event_id);
    }

    public function testResetRootPasswordUrl()
    {
        $this->assertEquals(
            'https://api.digitalocean.com/droplets/123/password_reset/?client_id=foo&api_key=bar',
            $this->dropletBuildQueryMethod->invoke(
                $this->droplet, $this->dropletId, DropletActions::ACTION_RESET_ROOT_PASSWORD
            )
        );
    }

    public function testResetRootPassword()
    {
        $response = <<<JSON
{"status":"OK","event_id":7501}
JSON
        ;

        $droplet = new Droplet($this->clientId, $this->apiKey, $this->getMockAdapterReturns($response));
        $droplet = $droplet->resetRootPassword($this->dropletId);

        $this->assertTrue(is_object($droplet));
        $this->assertEquals('OK', $droplet->status);
        $this->assertSame(7501, $droplet->event_id);
    }

    public function testResizeUrl()
    {
        $newSize = array(
            'size_id' => 111,
        );

        $this->assertEquals(
            'https://api.digitalocean.com/droplets/123/resize/?size_id=111&client_id=foo&api_key=bar',
            $this->dropletBuildQueryMethod->invoke(
                $this->droplet, $this->dropletId, DropletActions::ACTION_RESIZE, $newSize
            )
        );
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage You need to provide an integer "size_id".
     */
    public function testResizeThrowsSizeIdInvalidArgumentException()
    {
        $this->droplet->resize($this->dropletId, array());
    }

    public function testResize()
    {
        $response = <<<JSON
{"status":"OK","event_id":7501}
JSON
        ;

        $droplet = new Droplet($this->clientId, $this->apiKey, $this->getMockAdapterReturns($response));
        $droplet = $droplet->resize($this->dropletId, array('size_id' => 123));

        $this->assertTrue(is_object($droplet));
        $this->assertEquals('OK', $droplet->status);
        $this->assertSame(7501, $droplet->event_id);
    }

    public function testSnapshotUrlWithoutName()
    {
        $this->assertEquals(
            'https://api.digitalocean.com/droplets/123/snapshot/?client_id=foo&api_key=bar',
            $this->dropletBuildQueryMethod->invoke(
                $this->droplet, $this->dropletId, DropletActions::ACTION_SNAPSHOT
            )
        );
    }

    public function testSnapshotUrlWithName()
    {
        $newSnapshot = array(
            'name' => 'MySnapshotName'
        );

        $this->assertEquals(
            'https://api.digitalocean.com/droplets/123/snapshot/?name=MySnapshotName&client_id=foo&api_key=bar',
            $this->dropletBuildQueryMethod->invoke(
                $this->droplet, $this->dropletId, DropletActions::ACTION_SNAPSHOT, $newSnapshot
            )
        );
    }

    public function testSnapshot()
    {
        $response = <<<JSON
{"status":"OK","event_id":7501}
JSON
        ;

        $droplet = new Droplet($this->clientId, $this->apiKey, $this->getMockAdapterReturns($response));
        $droplet = $droplet->snapshot($this->dropletId);

        $this->assertTrue(is_object($droplet));
        $this->assertEquals('OK', $droplet->status);
        $this->assertSame(7501, $droplet->event_id);
    }

    public function testRestoreUrl()
    {
        $imageToRestore = array(
            'image_id' => 1111,
        );

        $this->assertEquals(
            'https://api.digitalocean.com/droplets/123/restore/?image_id=1111&client_id=foo&api_key=bar',
            $this->dropletBuildQueryMethod->invoke(
                $this->droplet, $this->dropletId, DropletActions::ACTION_RESTORE, $imageToRestore
            )
        );
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage You need to provide the "image_id" to restore.
     */
    public function testRestoreUrlThrowsSizeIdInvalidArgumentException()
    {
        $this->droplet->restore($this->dropletId, array());
    }

    public function testRestore()
    {
        $response = <<<JSON
{"status":"OK","event_id":7501}
JSON
        ;

        $droplet = new Droplet($this->clientId, $this->apiKey, $this->getMockAdapterReturns($response));
        $droplet = $droplet->restore($this->dropletId, array('image_id' => 1111));

        $this->assertTrue(is_object($droplet));
        $this->assertEquals('OK', $droplet->status);
        $this->assertSame(7501, $droplet->event_id);
    }

    public function testRebuildUrl()
    {
        $imageToRebuild = array(
            'image_id' => 1111,
        );

        $this->assertEquals(
            'https://api.digitalocean.com/droplets/123/rebuild/?image_id=1111&client_id=foo&api_key=bar',
            $this->dropletBuildQueryMethod->invoke(
                $this->droplet, $this->dropletId, DropletActions::ACTION_REBUILD, $imageToRebuild
            )
        );
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage You need to provide the "image_id" to rebuild.
     */
    public function testRebuildThrowsSizeIdInvalidArgumentException()
    {
        $this->droplet->rebuild($this->dropletId, array());
    }

    public function testRebuild()
    {
        $response = <<<JSON
{"status":"OK","event_id":7501}
JSON
        ;

        $droplet = new Droplet($this->clientId, $this->apiKey, $this->getMockAdapterReturns($response));
        $droplet = $droplet->rebuild($this->dropletId, array('image_id' => 1111));

        $this->assertTrue(is_object($droplet));
        $this->assertEquals('OK', $droplet->status);
        $this->assertSame(7501, $droplet->event_id);
    }

    public function testEnableAutomaticBackupsUrl()
    {
        $this->assertEquals(
            'https://api.digitalocean.com/droplets/123/enable_backups/?client_id=foo&api_key=bar',
            $this->dropletBuildQueryMethod->invoke(
                $this->droplet, $this->dropletId, DropletActions::ACTION_ENABLE_BACKUPS
            )
        );
    }

    public function testEnableAutomaticBackups()
    {
        $response = <<<JSON
{"status":"OK","event_id":7501}
JSON
        ;

        $droplet = new Droplet($this->clientId, $this->apiKey, $this->getMockAdapterReturns($response));
        $droplet = $droplet->enableAutomaticBackups($this->dropletId);

        $this->assertTrue(is_object($droplet));
        $this->assertEquals('OK', $droplet->status);
        $this->assertSame(7501, $droplet->event_id);
    }

    public function testDisableAutomaticBackupUrl()
    {
        $this->assertEquals(
            'https://api.digitalocean.com/droplets/123/disable_backups/?client_id=foo&api_key=bar',
            $this->dropletBuildQueryMethod->invoke(
                $this->droplet, $this->dropletId, DropletActions::ACTION_DISABLE_BACKUPS
            )
        );
    }

    public function testDisableAutomaticBackup()
    {
        $response = <<<JSON
{"status":"OK","event_id":7501}
JSON
        ;

        $droplet = new Droplet($this->clientId, $this->apiKey, $this->getMockAdapterReturns($response));
        $droplet = $droplet->disableAutomaticBackups($this->dropletId);

        $this->assertTrue(is_object($droplet));
        $this->assertEquals('OK', $droplet->status);
        $this->assertSame(7501, $droplet->event_id);
    }

    public function testDestroyUrl()
    {
        $this->assertEquals(
            'https://api.digitalocean.com/droplets/123/destroy/?client_id=foo&api_key=bar',
            $this->dropletBuildQueryMethod->invoke(
                $this->droplet, $this->dropletId, DropletActions::ACTION_DESTROY
            )
        );
    }

    public function testDestroy()
    {
        $response = <<<JSON
{"status":"OK","event_id":7501}
JSON
        ;

        $droplet = new Droplet($this->clientId, $this->apiKey, $this->getMockAdapterReturns($response));
        $droplet = $droplet->destroy($this->dropletId);

        $this->assertTrue(is_object($droplet));
        $this->assertEquals('OK', $droplet->status);
        $this->assertSame(7501, $droplet->event_id);
    }
}

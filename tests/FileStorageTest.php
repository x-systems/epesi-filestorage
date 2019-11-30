<?php

namespace Epesi\FileStorage\Tests;

use Orchestra\Testbench\TestCase;
use Epesi\FileStorage\Integration\LocalFileStorageAccess;
use Illuminate\Foundation\Testing\Concerns\InteractsWithAuthentication;
use Epesi\Core\System\User\Database\Models\User;
use Epesi\Core\System\Integration\Modules\ModuleJoint;
use Epesi\FileStorage\FileStorageCore;
use Epesi\FileStorage\Integration\RemoteFileStorageAccess;
use Epesi\FileStorage\Database\Models\File;
use Epesi\FileStorage\Database\Models\FileAccessLog;
use Epesi\FileStorage\Database\Models\FileRemoteAccess;
use Epesi\FileStorage\Database\Models\FileContent;

class FileStorageTest extends TestCase
{
	use InteractsWithAuthentication;
	
	public $testFiles = [
			__DIR__ . '/files/test.txt',
			__DIR__ . '/files/test2.txt'
	];
	
	protected function getPackageProviders($app)
	{
		return [

		];
	}
	
	protected function setUp(): void
	{
		parent::setUp();
		
		$this->loadLaravelMigrations();
		
		$this->loadMigrationsFrom(__DIR__ . '../../src/Database/Migrations');
		
		FileStorageCore::boot();
	}
	
    public function testContentStorage()
    {
    	$testFile = reset($this->testFiles);
    	
    	$fileId = FileContent::storeFromFile($testFile);
    	
    	$this->assertNotEmpty($fileId, 'File details not stored in database');
    	
    	$savedFileContent = FileContent::create()->load($fileId);
    	
    	$this->assertEquals(FileContent::hash(file_get_contents($testFile)), $savedFileContent['hash'], 'File hash not stored correctly in database');
    	
    	$this->assertEquals(filesize($testFile), $savedFileContent['size'], 'File size not stored correctly in database');

    	$this->assertEquals(\Illuminate\Support\Facades\File::mimeType($testFile), $savedFileContent['type'], 'File mime type not stored correctly in database');

    	$this->assertFileExists($savedFileContent['path'], 'File not copied to the storage location');
    	
    	$this->assertEquals(file_get_contents($testFile), $savedFileContent['data'], 'File contents not stored or retrieved correctly!');
    }   
    
    public function testFileStorage()
    {
    	$ids = File::storeMany($this->testFiles);

    	$id = reset($ids);
    	
    	$file = File::retrieve($id);

    	$this->assertEquals(basename(reset($this->testFiles)), $file['name'], 'File name not stored correctly!');
    	
    	$this->assertFileExists($file->ref('content')['path'], 'File name not stored correctly!');
    	
    	$id2 = File::store(reset($this->testFiles));
    	
    	$fileNew = File::retrieve($id2);
    	
    	$this->assertNotEquals($file['id'], $fileNew['id'], 'Same files not stored correctly!');

    	$this->assertEquals($file->ref('content')['id'], $fileNew->ref('content')['id'], 'Same files not stored correctly!');
    }    
    
    public function testFileLinks()
    {
    	$newFile = [
	    	'link' => 'test/link',
	    	'backref' => 'test/backref',
	    	'content' => [
	    			'path' => reset($this->testFiles)
	    	]
    	];
    	
    	$id = File::store($newFile);
    	
    	$this->assertIsNumeric($id, 'File not added correctly!');

    	$file = File::retrieve($id, false);

    	$this->assertEquals($newFile['link'], $file['link'], 'File link not stored correctly!');
    	
    	$this->assertEquals($newFile['backref'], $file['backref'], 'File backref not stored correctly!');
    	
    	$this->assertFileExists($file->ref('content')['path'], 'File content not stored correctly!');
    	
    	File::storeMany($id, 'test/backref/changed');
    	
    	$file = File::retrieve($id, false);
    	
    	$this->assertEquals('test/backref/changed', $file['backref'], 'File backref not updated correctly!');
    	
    	$this->assertEquals($id, File::getIdByLink($newFile['link']), 'File cannot locate file by link!');
    	
    	$this->assertTrue(File::exists($id), 'File existence check error!');
    }  
    
    public function testFileLocalAccess()
    {
    	ModuleJoint::register(Mocks\TestFileStorageAccess::class);
    	
    	$this->get('file')->assertStatus(401);

    	$testFile = reset($this->testFiles);

    	$fileId = File::store($testFile);
    	
    	$urls = LocalFileStorageAccess::getActionUrls($fileId, ['hash' => 'test_hash']);
    	
    	foreach ($urls as $url) {
    		$this->get($url)->assertStatus(401);
    	}

    	$this->be($this->mockUser());
    	
    	$fileAccessLog = FileAccessLog::create()->addCondition('file_id', $fileId);
    	
    	$timesAccessed = 0;
    	foreach ($urls as $url) {
    		$response = $this->get($url);

    		$response->assertStatus(200);
    		
    		$response->assertHeader('Content-Length', strlen(file_get_contents($testFile)));
    		
    		$this->assertEquals(++ $timesAccessed, $fileAccessLog->action('count')->getOne(), 'File access log incorrect');
    	}
    }  
    
    public function testFileRemoteAccess()
    {
    	ModuleJoint::register(RemoteFileStorageAccess::class);
    	
    	$testFile = reset($this->testFiles);
    	
    	$fileId = File::store($testFile);
    	
    	$expiry = '2 weeks';
    	
    	$remoteId = FileRemoteAccess::grant($fileId, $expiry);
    	
    	$remote = FileRemoteAccess::create()->load($remoteId);
    	
    	$this->assertEquals((new \DateTime($expiry))->format('Y-m-d H:i:s'), $remote['expires_at']->format('Y-m-d H:i:s'), 'Mismatch in file remote access expiry time');
    	
    	$urls = LocalFileStorageAccess::getActionUrls($fileId);

    	foreach ($urls as $url) {
    		$this->get($url)->assertStatus(401);
    	}
    	
    	$urls = LocalFileStorageAccess::getActionUrls($fileId, ['token' => $remote['token']]);
    	
    	foreach ($urls as $action => $url) {
    		$response = $this->get($url);
    		
    		if ($action != 'download') {
    			$response->assertStatus(401);
    			
    			continue;
    		}
    		
    		$response->assertStatus(200);
    		
    		$response->assertHeader('Content-Length', strlen(file_get_contents($testFile)));
    	}
    	
    	$remote['expires_at'] = date('Y-m-d H:i:s', strtotime('-1 week'));
    	
    	$remote->save();
    	
    	$this->get($urls['download'])->assertStatus(401);
    }  
    
    protected function mockUser()
    {
    	return User::create(['name' => 'Test User', 'email' => 'test@test.test', 'password' => 'test']);
    }
}

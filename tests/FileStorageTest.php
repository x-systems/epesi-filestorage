<?php

namespace Epesi\FileStorage\Tests;

use Orchestra\Testbench\TestCase;
use Epesi\FileStorage\Database\Models\File;
use Epesi\FileStorage\Integration\LocalFileStorageAccess;
use Illuminate\Foundation\Testing\Concerns\InteractsWithAuthentication;
use Epesi\Core\System\User\Database\Models\User;
use Epesi\Core\System\Integration\Modules\ModuleJoint;
use Epesi\FileStorage\FileStorageCore;
use Epesi\FileStorage\Integration\RemoteFileStorageAccess;
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
    	
    	$fileId = FileContent::putFromFile($testFile);
    	
    	$this->assertNotEmpty($fileId, 'File details not stored in database');
    	
    	$savedFileContent = FileContent::find($fileId);
    	
    	$this->assertEquals(FileContent::hash(file_get_contents($testFile)), $savedFileContent->hash, 'File hash not stored correctly in database');
    	
    	$this->assertEquals(filesize($testFile), $savedFileContent->size, 'File size not stored correctly in database');

    	$this->assertEquals(\Illuminate\Support\Facades\File::mimeType($testFile), $savedFileContent->type, 'File mime type not stored correctly in database');

    	$this->assertFileExists($savedFileContent->path, 'File not copied to the storage location');
    	
    	$this->assertEquals(file_get_contents($testFile), $savedFileContent->data, 'File contents not stored or retrieved correctly!');
    }   
    
    public function testFileStorage()
    {
    	$ids = File::putMany($this->testFiles);
    	
    	$id = reset($ids);
    	
    	$file = File::get($id);

    	$this->assertEquals(basename(reset($this->testFiles)), $file->name, 'File name not stored correctly!');
    	
    	$this->assertFileExists($file->content->path, 'File name not stored correctly!');
    	
    	$id2 = File::put(reset($this->testFiles));
    	
    	$fileNew = File::get($id2);
    	
    	$this->assertNotEquals($file->id, $fileNew->id, 'Same files not stored correctly!');

    	$this->assertEquals($file->content->id, $fileNew->content->id, 'Same files not stored correctly!');
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
    	
    	$id = File::put($newFile);
    	
    	$this->assertIsInt($id, 'File not added correctly!');

    	$file = File::get($id, false);

    	$this->assertEquals($newFile['link'], $file->link, 'File link not stored correctly!');
    	
    	$this->assertEquals($newFile['backref'], $file->backref, 'File backref not stored correctly!');
    	
    	$this->assertFileExists($file->content->path, 'File content not stored correctly!');
    	
    	File::putMany($id, 'test/backref/changed');
    	
    	$file = File::get($id, false);
    	
    	$this->assertEquals('test/backref/changed', $file->backref, 'File backref not updated correctly!');
    	
    	$this->assertEquals($id, File::getIdByLink($newFile['link']), 'File cannot locate file by link!');
    	
    	$this->assertTrue(File::fileExists($id), 'File existence check error!');
    }  
    
    public function testFileLocalAccess()
    {
    	ModuleJoint::register(Mocks\TestFileStorageAccess::class);
    	
    	$this->get('file')->assertStatus(401);

    	$testFile = reset($this->testFiles);
    	
    	$fileId = FileContent::putFromFile($testFile);
    	
    	$urls = LocalFileStorageAccess::getActionUrls($fileId, ['hash' => 'test_hash']);//, 'thumbnail' => 0]);
    	
    	foreach ($urls as $url) {
    		$this->get($url)->assertStatus(401);
    	}
    	
    	$this->be($this->mockUser());
    	
    	$timesAccessed = 0;
    	foreach ($urls as $url) {
    		$response = $this->get($url);
    		
    		$response->assertStatus(200);
    		
    		$response->assertHeader('Content-Length', strlen(file_get_contents($testFile)));
    		
    		$this->assertEquals(++ $timesAccessed, FileAccessLog::where('file_id', $fileId)->count(), 'File access log incorrect');
    	}
    }  
    
    public function testFileRemoteAccess()
    {
    	ModuleJoint::register(RemoteFileStorageAccess::class);
    	
    	$testFile = reset($this->testFiles);
    	
    	$fileId = FileContent::putFromFile($testFile);
    	
    	$expiry = '2 weeks';
    	
    	$remote = FileRemoteAccess::grant($fileId, $expiry);
    	
    	$this->assertEquals(strtotime($expiry), strtotime($remote->expires_at), 'Mismatch in file remote access expiry time');
    	
    	$urls = LocalFileStorageAccess::getActionUrls($fileId);
    	
    	foreach ($urls as $url) {
    		$this->get($url)->assertStatus(401);
    	}
    	
    	$urls = LocalFileStorageAccess::getActionUrls($fileId, ['token' => $remote->token]);
    	
    	foreach ($urls as $action => $url) {
    		$response = $this->get($url);
    		
    		if ($action != 'download') {
    			$response->assertStatus(401);
    			
    			continue;
    		}
    		
    		$response->assertStatus(200);
    		
    		$response->assertHeader('Content-Length', strlen(file_get_contents($testFile)));
    	}
    	
    	$remote->expires_at = date('Y-m-d H:i:s', strtotime('-1 week'));
    	
    	$remote->save();
    	
    	$this->get($urls['download'])->assertStatus(401);
    }  
    
    protected function mockUser()
    {
    	return User::create(['name' => 'Test User', 'email' => 'test@test.test', 'password' => 'test']);
    }
}

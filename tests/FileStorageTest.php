<?php

namespace Epesi\FileStorage\Tests;

use Orchestra\Testbench\TestCase;
use Epesi\FileStorage\Database\Models\File;
use Epesi\FileStorage\Database\Models\Meta;
use Epesi\FileStorage\Integration\LocalFileStorageAccess;
use Illuminate\Foundation\Testing\Concerns\InteractsWithAuthentication;
use Epesi\Core\System\User\Database\Models\User;
use Epesi\Core\System\Integration\Modules\ModuleJoint;
use Epesi\FileStorage\FileStorageCore;
use Epesi\FileStorage\Integration\RemoteFileStorageAccess;
use Epesi\FileStorage\Database\Models\Remote;
use Epesi\FileStorage\Database\Models\AccessLog;

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
	
    public function testFileStorage()
    {
    	$testFile = reset($this->testFiles);
    	
    	$fileId = File::putDataFromFile($testFile);
    	
    	$this->assertNotEmpty($fileId, 'File details not stored in database');
    	
    	$savedFile = File::find($fileId);
    	
    	$this->assertEquals(File::hashContent(file_get_contents($testFile)), $savedFile->hash, 'File hash not stored correctly in database');
    	
    	$this->assertEquals(filesize($testFile), $savedFile->size, 'File size not stored correctly in database');

    	$this->assertEquals(\Illuminate\Support\Facades\File::mimeType($testFile), $savedFile->type, 'File mime type not stored correctly in database');

    	$this->assertFileExists($savedFile->path, 'File not copied to the storage location');
    	
    	$this->assertEquals(file_get_contents($testFile), $savedFile->contents, 'File contents not stored or retrieved correctly!');
    }   
    
    public function testMetaAddFiles()
    {
    	$ids = Meta::putMany($this->testFiles);
    	
    	$id = reset($ids);
    	
    	$meta = Meta::get($id);

    	$this->assertEquals(basename(reset($this->testFiles)), $meta->name, 'File name not stored correctly!');
    	
    	$this->assertFileExists($meta->file->path, 'File name not stored correctly!');
    	
    	$id2 = Meta::put(reset($this->testFiles));
    	
    	$metaNew = Meta::get($id2);
    	
    	$this->assertNotEquals($meta->id, $metaNew->id, 'Same files meta not stored correctly!');

    	$this->assertEquals($meta->file->id, $metaNew->file->id, 'Same files not stored correctly!');
    }    
    
    public function testMetaLinks()
    {
    	$newMeta = [
	    	'link' => 'test/link',
	    	'backref' => 'test/backref',
	    	'file' => reset($this->testFiles)
    	];
    	
    	$id = Meta::put($newMeta);
    	
    	$this->assertIsInt($id, 'File not added correctly!');

    	$meta = Meta::get($id, false);

    	$this->assertEquals($newMeta['link'], $meta->link, 'File link not stored correctly!');
    	
    	$this->assertEquals($newMeta['backref'], $meta->backref, 'File backref not stored correctly!');
    	
    	$this->assertFileExists($meta->file->path, 'File name not stored correctly!');
    	
    	Meta::putMany($id, 'test/backref/changed');
    	
    	$meta = Meta::get($id, false);
    	
    	$this->assertEquals('test/backref/changed', $meta->backref, 'File backref not updated correctly!');
    	
    	$this->assertEquals($id, Meta::getIdByLink($newMeta['link']), 'File cannot locate meta by link!');
    	
    	$this->assertTrue(Meta::fileExists($id), 'File existence check error!');
    }  
    
    public function testMetaLocalAccess()
    {
    	ModuleJoint::register(Mocks\TestFileStorageAccess::class);
    	
    	$this->get('file')->assertStatus(401);

    	$testFile = reset($this->testFiles);
    	
    	$fileId = File::putDataFromFile($testFile);
    	
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
    		
    		$this->assertEquals(++ $timesAccessed, AccessLog::where('meta_id', $fileId)->count(), 'File access log incorrect');
    	}
    }  
    
    public function testMetaRemoteAccess()
    {
    	ModuleJoint::register(RemoteFileStorageAccess::class);
    	
    	$testFile = reset($this->testFiles);
    	
    	$fileId = File::putDataFromFile($testFile);
    	
    	$expiry = '2 weeks';
    	
    	$remote = Remote::grant($fileId, $expiry);
    	
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

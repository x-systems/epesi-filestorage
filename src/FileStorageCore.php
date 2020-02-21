<?php

namespace Epesi\FileStorage;

use Epesi\Core\System\Modules\ModuleCore;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class FileStorageCore extends ModuleCore
{
	protected static $alias = 'filestorage';
	
	protected static $view = FileStorageList::class;
	
	protected static $joints = [
			Integration\FileStorageSystemSettings::class,
			Integration\LocalFileStorageAccess::class,
			Integration\RemoteFileStorageAccess::class,
	];
	
	public static function info()
	{
		return [
				__('Author') => 'Georgi Hristov',
				__('Copyright') => 'X Systems Ltd',
				'',
				'Provides unified file storage functionality'
		];
	}
	
	public function install()
	{
	    Models\File::migrate();
	    Models\FileAccessLog::migrate();
	    Models\FileContent::migrate();
	    Models\FileRemoteAccess::migrate();
	    
		$downloadFiles = Permission::create(['name' => 'download files']);
		
		Role::findByName('Admin')->givePermissionTo($downloadFiles);
	}
	
	public function uninstall()
	{
		Permission::findByName('download files')->delete();
	}
	
	public static function boot()
	{
	    Route::group(['namespace' => self::namespace()], function() {
			Route::get('file', 'FileStorageController@get')->middleware('web', FileStorageAccess::class);
		});		
	}
}

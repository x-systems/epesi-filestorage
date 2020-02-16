<?php

namespace Epesi\FileStorage\Models;

use Illuminate\Support\Facades\Auth;
use atk4\data\Model;
use Epesi\Core\Data\HasEpesiConnection;
use Epesi\Core\System\User\Database\Models\atk4\User;

class FileRemoteAccess extends Model
{
    use HasEpesiConnection;
    
    const DEFAULT_PERIOD = '1 week';
    
    public $table = 'filestorage_remote_access';
    
    public function init()
    {
    	parent::init();
    	
    	$this->addFields([
    			'token',
    			'expires_at' => ['type' => 'datetime'],    			
    	]);
    	
    	$this->hasOne('file_id', File::class)->addTitle(['field' => 'file', 'caption' => __('File Name')]);
    	$this->hasOne('created_by', User::class)->addTitle(['field' => 'created_by_user', 'caption' => __('Created By')]);
    	
    	$this->addCalculatedField('href', [[__CLASS__, 'getHrefField']]);
    }

    public static function check($fileId, $token)
    {
    	return (bool) self::create()
    	->addCrits([
    			['file_id', $fileId],
    			['token', $token],
    			['expires_at', '>', date('Y-m-d H:i:s')]
    	])
    	->action('count')->getOne();
    }
    
    public static function grant($file, $expires = self::DEFAULT_PERIOD)
    {
		return self::create()->insert([
				'file_id' => is_numeric($file)? $file: $file->id,
				'token' => md5(uniqid(rand(), true)),
				'created_by' => Auth::id()?: 0,
				'expires_at' => date('Y-m-d H:i:s', strtotime($expires)),
		]);
    }
    
    public static function getHrefField($model)
    {		
    	return url('file') . '?' . http_build_query(['id' => $model['file_id'], 'token' => $model['token']]);
    }
}
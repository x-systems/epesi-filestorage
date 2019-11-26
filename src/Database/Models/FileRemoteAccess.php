<?php

namespace Epesi\FileStorage\Database\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class FileRemoteAccess extends Model {
    const DEFAULT_PERIOD = '1 week';
    
    protected $table = 'filestorage_remote_access';
    protected static $unguarded = true;    

    public static function check($fileId, $token)
    {
    	return (bool) self::where('file_id', $fileId)->where('token', $token)->where('expires_at', '>', date('Y-m-d H:i:s'))->count();
    }
    
    public static function grant($file, $expires = self::DEFAULT_PERIOD)
    {
		return self::create([
				'file_id' => is_numeric($file)? $file: $file->id,
				'token' => md5(uniqid(rand(), true)),
				'created_by' => Auth::id()?: 0,
				'expires_at' => date('Y-m-d H:i:s', strtotime($expires)),
		]);
    }
    
    public function getHrefAttribute()
    {		return url('file') . '?' . http_build_query(['id' => $this->file_id, 'token' => $this->token]);
    }
}
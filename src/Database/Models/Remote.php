<?php

namespace Epesi\FileStorage\Database\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class Remote extends Model {
    const DEFAULT_PERIOD = '1 week';
    
    protected $table = 'filestorage_remote';
    protected static $unguarded = true;    

    public static function access($metaId, $token)
    {
    	return (bool) self::where('meta_id', $metaId)->where('token', $token)->where('expires_at', '>', date('Y-m-d H:i:s'))->count();
    }
    
    public static function grant($metaId, $expires = self::DEFAULT_PERIOD)
    {
		return self::create([
				'meta_id' => $metaId,
				'token' => md5(uniqid(rand(), true)),
				'created_by' => Auth::id()?: 0,
				'expires_at' => date('Y-m-d H:i:s', strtotime($expires)),
		]);
    }
}
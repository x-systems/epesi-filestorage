<?php

namespace Epesi\FileStorage\Database\Models;

use Illuminate\Database\Eloquent\Model;

class FileAccessLog extends Model {
    
	public $timestamps = false;
	
    protected $table = 'filestorage_access_log';
    protected static $unguarded = true;    

}
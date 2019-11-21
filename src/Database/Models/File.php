<?php

namespace Epesi\FileStorage\Database\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class WriteError extends \Exception {}

class File extends Model {
    
	const HASH_METHOD = 'sha512';
	
	public $timestamps = false;
	
    protected $table = 'filestorage_files';
    protected static $unguarded = true;    
    
    protected $appends = ['path'];
    
    /**
     * One file can have many meta datas
     * The actual content is stored only once based on the content hash
     * 
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function meta()
    {
    	return $this->hasMany(Meta::class, 'file_id');
    }
    
    /**
     * Accessor method for retrieving of file content path when using the model
     * Having the $appends property in the File model listing the 'path' makes sure the value is also exported to arrays
     * 
     * @return string
     */
    public function getPathAttribute()
    {
    	return self::storage()->path($this->getStoragePath($this->hash));
    }
    
    /**
     * Accessor method for retrieving of file contents
     * 
     * @return string
     */
    public function getContentsAttribute()
    {
    	return self::storage()->get($this->storage_path);
    }
    
    /**
     * Accessor method for file relative storage path
     * 
     * @return string
     */
    public function getStoragePathAttribute()
    {
    	return $this->getStoragePath($this->hash);
    }
    
    protected static function getStoragePath($hash)
    {
    	return implode(DIRECTORY_SEPARATOR, array_merge(str_split(substr($hash, 0, 5)), [substr($hash, 5)]));
    }
    
    /**
     * Returns the storage where file contents are saved based on config settings
     * 
     * @return \Illuminate\Contracts\Filesystem\Filesystem
     */
    public static function storage()
    {
    	return Storage::disk(config('epesi.filestorage', 'local'));
    }
        
    /**
     * Add file to the filestorage
     *
     * @param string $file File path to save
     *
     * @return int File id in the database
     */
    public static function putDataFromFile($file)
    {
    	return self::putData(file_get_contents($file));
    }
    
    /**
     * Add content to the filestorage
     *
     * @param string $content Content to save
     *
     * @return int File id in the database
     */
    public static function putData($content)
    {
    	$hash = self::hashContent($content);
    	
    	$path = self::getStoragePath($hash);
    	
    	if (! self::storage()->exists($path)) {
    		self::storage()->put($path, $content);
    	}
    	
    	return self::firstOrCreate(compact('hash'), [
    			'size' => self::storage()->size($path),
    			'type' => self::storage()->mimeType($path)
    	])->id;
    }
    
    /**
     * Get the hash of the content using hash method defined as constant to the class
     * 
     * @param string $content
     * @return string
     */
    public static function hashContent($content)
    {
    	return hash(self::HASH_METHOD, $content);
    }
}
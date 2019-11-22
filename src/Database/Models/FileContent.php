<?php

namespace Epesi\FileStorage\Database\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class WriteError extends \Exception {}

class FileContent extends Model {
    
	const HASH_METHOD = 'sha512';
	
	public $timestamps = false;
	
    protected $table = 'filestorage_contents';
    protected static $unguarded = true;    
    
    protected $appends = ['path'];
    
    /**
     * One content can have many files associated with
     * The actual content is stored only once based on the content hash
     * 
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function files()
    {
    	return $this->hasMany(File::class, 'content_id');
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
    public function getDataAttribute()
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
    public static function putFromFile($file)
    {
    	return self::put(file_get_contents($file));
    }
    
    /**
     * Add content to the filestorage
     *
     * @param string $content Content to save
     *
     * @return int File id in the database
     */
    public static function put($content)
    {
    	$hash = self::hash($content);
    	
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
    public static function hash($content)
    {
    	return hash(self::HASH_METHOD, $content);
    }
}
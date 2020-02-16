<?php

namespace Epesi\FileStorage\Models;

use Illuminate\Support\Facades\Storage;
use atk4\data\Model;
use Epesi\Core\Data\HasEpesiConnection;

class WriteError extends \Exception {}

class FileContent extends Model
{
    use HasEpesiConnection;
    
	const HASH_METHOD = 'sha512';

	public $table = 'filestorage_contents';

    protected $appends = ['path'];
    
    function init(){
    	parent::init();
    	
    	$this->addFields([
    			'hash',
    			'size',
    			'type'
    	]);
    	
    	$this->hasMany('files', [File::class, 'their_field' => 'content_id']);
    	$this->addCalculatedField('storage_path', [[__CLASS__, 'getStoragePathField']]);
    	$this->addCalculatedField('path', [[__CLASS__, 'getPathField']]);
    	$this->addCalculatedField('data', [[__CLASS__, 'getDataField']]);    	
    }
    
    /**
     * One content can have many files associated with
     * The actual content is stored only once based on the content hash
     * 
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function files()
    {
    	return $this->ref('files');
    }
    
    /**
     * Accessor method for retrieving of file content path when using the model
     * Having the $appends property in the File model listing the 'path' makes sure the value is also exported to arrays
     * 
     * @return string
     */
    public static function getPathField($model)
    {
    	return self::storage()->path($model->getStoragePath($model->get('hash')));
    }
    
    /**
     * Accessor method for retrieving of file contents
     * 
     * @return string
     */
    public static function getDataField($model)
    {
    	return self::storage()->get($model->get('storage_path'));
    }
    
    /**
     * Accessor method for file relative storage path
     * 
     * @return string
     */
    public static function getStoragePathField($model)
    {
    	return $model->getStoragePath($model->get('hash'));
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
    public static function storeFromFile($file)
    {
    	return self::store(file_get_contents($file));
    }
    
    /**
     * Add content to the filestorage
     *
     * @param string $content Content to save
     *
     * @return int File id in the database
     */
    public static function store($content)
    {
    	$hash = self::hash($content);
    	
    	$path = self::getStoragePath($hash);
    	
    	if (! self::storage()->exists($path)) {
    		self::storage()->put($path, $content);
    	}
    	
    	$content = self::create()->addCondition('hash', $hash);

    	if (! $content->action('count')->getOne()) {  
    		return $content->insert([
    				'size' => self::storage()->size($path),
    				'type' => self::storage()->mimeType($path)
    		]);
    	}
    	
    	return $content->loadAny()->get('id');
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
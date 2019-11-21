<?php

namespace Epesi\FileStorage\Database\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;

class StorageNotFound extends \Exception {}
class LinkNotFound extends \Exception {}
class LinkDuplicate extends \Exception {}
class FileNotFound extends \Exception {}

class Meta extends Model
{
	use SoftDeletes;
	
    protected $table = 'filestorage_meta';
    protected static $unguarded = true;
    
    public function file()
    {
    	return $this->belongsTo(File::class, 'file_id');
    }
    
    /**
     * Retrieve meta info about the file
     *
     * @param int|string $idOrLink Filestorage ID or unique link string
     * @param bool $useCache Use cache or not
     *
     * @return array Metadata about the file.
     *               Keys in array: hash, file, filename, link, backref,
     *               created_at, created_by, deleted, file_id
     * @throws FileNotFound
     * @throws StorageNotFound
     */
    public static function get($idOrLink, $useCache = true)
    {
    	static $cache = [];

    	$id = self::getIdByLink($idOrLink, true, true);

    	if ($useCache && isset($cache[$id])) {
    		return $cache[$id];
    	}

    	if (! $meta = self::with('file')->find($id)) {
    		throw new StorageNotFound('Exception - DB storage object not found: ' . $id);
    	}

    	if (empty($meta->file['hash'])) {
    		throw new FileNotFound('File object does not have corresponding file hash');
    	}

    	return $cache[$id] = $meta;
    }
    
    /**
     * Get Filestorage ID by link
     *
     * @param string $link            Unique link
     * @param bool   $useCache       Use cache or not
     * @param bool   $throwException Throw exception if link is not found
     *
     * @return int Filestorage ID
     * @throws LinkNotFound
     */
    public static function getIdByLink($link, $useCache = true, $throwException = false)
    {
    	static $cache = [];
    	
    	if (is_numeric($link)) return $link;
    	
    	if (!$useCache || !isset($cache[$link])) {
    		$cache[$link] = self::where('link', $link)->value('id');
    		
    		if (!$cache[$link] && $throwException) {
    			throw new LinkNotFound($link);
    		}
    	}
    	
    	return $cache[$link];
    }

    /**
     * Mark file as deleted. Does not remove any content!
     *
     * @param int|string $idOrLink Filestorage ID or unique link
     */
    public static function deleteFile($idOrLink)
    {
   		if ($id = self::getIdByLink($idOrLink, false)) {
   			self::find($id)->delete();
    	}
    }
    
    /**
     * Check if file exists
     *
     * @param int|static $idOrMeta              Filestorage ID or meta object
     * @param bool      $throwException Throw exception on missing file or return false
     *
     * @return bool True if file exists, false otherwise
     * @throws FileNotFound May be thrown if $throwException set to true
     */
    public static function fileExists($idOrMeta, $throwException = false)
    {
    	try {
    		$meta = is_numeric($idOrMeta) ? self::get($idOrMeta) : $idOrMeta;
    		
    		if (! file_exists($meta->file->path)) {
    			throw new FileNotFound('Exception - file not found: ' . $meta->file->path);
    		}
    	} catch (\Exception $exception) {
    		if ($throwException)
    			throw $exception;
    		else
    			return false;
    	}
    	
    	return true;
    }
    
    /**
     * Add multiple files, clone file if file id is provided.
     * May be used to update backref for all files.
     *
     * @param array $files array of existing filestorage ids or array with values for the new file
     * @param string|null $backref Backref for all files
     * @return array Newly created Meta Ids sorted in ascending order
     */
    public static function putMany($files, $backref = null)
    {
    	$ids = [];
    	foreach ((array) $files as $file) {
    		if (! is_numeric($file)) {
    			$ids[] = self::put($file);
    			
    			continue;
    		}
    		
    		$meta = self::get($file, false)->toArray();
    			
    		if ($backref && $meta['backref'] != $backref) {
    			$meta['backref'] = $backref;
    			unset($meta['link']);
    			
    			self::put($meta);
    		}
    			
    		$ids[] = $file;
    	}
    	
    	sort($ids);
    	
    	return $ids;
    }
        
    /**
     * @param string|array     $metaOrFile
     * @param string     $content    Content of the file
     * 
     * @return int Filestorage ID
     * @throws LinkDuplicate
     */
    public static function put($metaOrFile, $content = null) 
    {
    	$meta = $metaOrFile;
    	if (! $content && is_string($metaOrFile)) {
    		$content = file_get_contents($metaOrFile);
    		
    		$meta = [
    				'name' => basename($metaOrFile)
    		];
    	}
    	
    	if (!empty($meta['link']) && self::getIdByLink($meta['link'], false)) {
    		throw new LinkDuplicate($meta['link']);
    	}
    	
    	if ($file = $meta['file']?? null) {
    		$path = is_array($file)? $file['path']: $file;
    		
    		$meta['name'] = $meta['name']?? basename($path);
    		
    		$content = $content?: file_get_contents($path);
    		
    		unset($meta['file']);
    	}
	
    	return self::updateOrCreate(['id' => $meta['id']?? null], array_merge([
    			'created_at' => time(),
    			'created_by' => Auth::id(),
    			'file_id' => File::putData($meta['content']?? $content)
    	], $meta))->id;
    }
    
    public function getThumbnailAttribute()
    {
    	if (! $this->thumbnailPossible()) return false;
    	
    	$image = new \Imagick($this->meta->file->path . '[0]');
    	
    	$image->setImageFormat('jpg');
    	
    	$mime = 'image/jpeg';
    	
    	$name = 'preview.jpeg';
    	
    	$contents = $image . '';
    	
    	return collect(compact('mime', 'name', 'contents'));
    }
    
    protected function thumbnailPossible() {
    	return $this->file->type == 'application/pdf' && class_exists('Imagick');
    }
}
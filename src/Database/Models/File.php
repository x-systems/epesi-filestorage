<?php

namespace Epesi\FileStorage\Database\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;

class LinkNotFound extends \Exception {}
class LinkDuplicate extends \Exception {}
class FileNotFound extends \Exception {}

class File extends Model
{
	use SoftDeletes;
	
    protected $table = 'filestorage_files';
    protected static $unguarded = true;

    public function content()
    {
    	return $this->belongsTo(FileContent::class, 'content_id');
    }
    
    public function links()
    {
    	return $this->hasMany(FileRemoteAccess::class, 'file_id');
    }
    
    
    /**
     * Retrieve the file
     *
     * @param int|string $idOrLink Filestorage ID or unique link string
     * @param bool $useCache Use cache or not
     *
     * @return static
     * 
     * @throws FileNotFound
     */
    public static function get($idOrLink, $useCache = true)
    {
    	static $cache = [];
    	
    	if (is_object($idOrLink)) return $idOrLink;

    	$id = self::getIdByLink($idOrLink, true, true);

    	if ($useCache && isset($cache[$id])) {
    		return $cache[$id];
    	}

    	$file = self::with('content')->findOrFail($id);
    	
    	if (empty($file->content['hash'])) {
    		throw new FileNotFound('File object does not have corresponding content');
    	}

    	return $cache[$id] = $file;
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
    public static function unlink($idOrLink)
    {
   		if ($id = self::getIdByLink($idOrLink, false)) {
   			self::find($id)->delete();
    	}
    }
    
    /**
     * Check if file exists
     *
     * @param int|static $idOrMeta              Filestorage ID or file object
     * @param bool      $throwException Throw exception on missing file or return false
     *
     * @return bool True if file exists, false otherwise
     * @throws FileNotFound May be thrown if $throwException set to true
     */
    public static function exists($idOrMeta, $throwException = false)
    {
    	try {
    		$file = is_numeric($idOrMeta) ? self::get($idOrMeta) : $idOrMeta;
    		
    		if (! file_exists($file->content->path)) {
    			throw new FileNotFound('Exception - file not found: ' . $file->content->path);
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
    	foreach ((array) $files as $filePath) {
    		if (! is_numeric($filePath)) {
    			$ids[] = self::put($filePath);
    			
    			continue;
    		}
    		
    		$file = self::get($filePath, false)->toArray();
    			
    		if ($backref && $file['backref'] != $backref) {
    			$file['backref'] = $backref;
    			unset($file['link']);
    			
    			self::put($file);
    		}
    			
    		$ids[] = $file;
    	}
    	
    	sort($ids);
    	
    	return $ids;
    }
        
    /**
     * @param string|array     $fileOrPath
     * @param string     $content    Content of the file
     * 
     * @return int Filestorage ID
     * @throws LinkDuplicate
     */
    public static function put($fileOrPath, $content = null) 
    {
    	$file = $fileOrPath;
    	if (! $content && is_string($fileOrPath)) {
    		$content = file_get_contents($fileOrPath);
    		
    		$file = [
    				'name' => basename($fileOrPath)
    		];
    	}
    	
    	if (!empty($file['link']) && self::getIdByLink($file['link'], false)) {
    		throw new LinkDuplicate($file['link']);
    	}
    	
    	$content = $file['content']?? $content;
    	
    	if (is_array($content)) {
    		$path = $content['path'];
    			
    		$file['name'] = $file['name']?? basename($path);
    			
    		$content = file_get_contents($path);
    	}

    	unset($file['content']);
    	
    	return self::updateOrCreate(['id' => $file['id']?? null], array_merge([
    			'created_at' => time(),
    			'created_by' => Auth::id(),
    			'content_id' => FileContent::put($content)
    	], $file))->id;
    }
    
    public function getThumbnailAttribute()
    {
    	if (! $this->thumbnailPossible()) return false;
    	
    	$image = new \Imagick($this->content->path . '[0]');
    	
    	$image->setImageFormat('jpg');
    	
    	$mime = 'image/jpeg';
    	
    	$name = 'preview.jpeg';
    	
    	$contents = $image . '';
    	
    	return collect(compact('mime', 'name', 'contents'));
    }
    
    protected function thumbnailPossible() {
    	return $this->content->type == 'application/pdf' && class_exists('Imagick');
    }
}
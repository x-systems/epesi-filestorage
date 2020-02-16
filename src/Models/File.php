<?php

namespace Epesi\FileStorage\Models;

use Illuminate\Support\Facades\Auth;
use atk4\data\Model;
use Epesi\Core\Data\HasEpesiConnection;
use Epesi\Core\System\User\Database\Models\atk4\User;

class LinkNotFound extends \Exception {}
class LinkDuplicate extends \Exception {}
class FileNotFound extends \Exception {}

class File extends Model
{
    use HasEpesiConnection;
    
	public $table = 'filestorage_files';

    function init() {
    	parent::init();
    	
    	$this->addFields([
    	        'created_at' => ['caption' => __('Stored At')],
    			'name' => ['caption' => __('File Name')],
    			'link' => ['caption' => __('Link')],
    			'backref'
    	]);
    	    	
    	$this->hasOne('created_by', [User::class, 'our_field' => 'created_by'])->addTitle(['field' => 'created_by_user', 'caption' => __('Stored By')]);
    	
    	$this->hasOne('content', [FileContent::class, 'our_field' => 'content_id']);
    	
    	$this->hasMany('links', [FileRemoteAccess::class, 'their_field' => 'file_id']);
    	
    	$this->addCalculatedField('thumbnail', [[__CLASS__, 'getThumbnailField']]);
    }

    public function userActiveLinks()
    {
    	return $this->ref('links')->addCrits([
    			['created_by', Auth::id()],
    			['expires_at', '>', date('Y-m-d H:i:s')]
    	]);
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
    public static function retrieve($idOrLink)
    {
    	$id = self::getIdByLink($idOrLink, true, true);

    	$file = self::create()->tryLoad($id);
    	
    	if (! $file->ref('content')['hash']) {
    		throw new FileNotFound('File object does not have corresponding content');
    	}

    	return $file;
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
    	
    	if (is_numeric($link) || is_null($link)) return $link;
    	
    	if (is_object($link)) return $link['id'];
    	
    	if (!$useCache || !isset($cache[$link])) {
    		$file = self::create()->tryLoadBy('link', $link);
    		
    		$cache[$link] = $file? $file->get('id'): null;
    		
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
   			self::create()->delete($id);
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
    		$file = is_numeric($idOrMeta) ? self::retrieve($idOrMeta) : $idOrMeta;
    		
    		if (! file_exists($file->ref('content')['path'])) {
    			throw new FileNotFound('Exception - file not found: ' . $file->ref('content')['path']);
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
    public static function storeMany($files, $backref = null)
    {
    	$ids = [];
    	foreach ((array) $files as $filePath) {
    		if (! is_numeric($filePath)) {
    			$ids[] = self::store($filePath);
    			
    			continue;
    		}
    		
    		$file = self::retrieve($filePath, false);
    			
    		if ($backref && $file['backref'] != $backref) {
    			$file->save(compact('backref'));
    		}
    			
    		$ids[] = $file['id'];
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
    public static function store($fileOrPath, $content = null) 
    {
    	$file = $fileOrPath;
    	
    	if (is_object($file)) {
    		$file->action('update', [
    				'content_id' => FileContent::store($content)
    		]);
    		
    		return $file['id'];
    	}
    	    	
    	if (! $content && is_string($fileOrPath)) {
    		$content = file_get_contents($fileOrPath);
    		
    		$file = [
    				'name' => basename($fileOrPath)
    		];
    	}
    	
    	if (! empty($file['link']) && self::getIdByLink($file['link'], false)) {
    		throw new LinkDuplicate($file['link']);
    	}
    	
    	$content = $file['content']?? $content;
    	
    	if (is_array($content)) {
    		$path = $content['path'];
    			
    		$file['name'] = $file['name']?? basename($path);
    			
    		$content = file_get_contents($path);
    	}

    	unset($file['content']);

   		return self::create()->insert(array_merge([
   				'created_at' => date('Y-m-d H:i:s'),
   				'created_by' => Auth::id(),
   				'content_id' => FileContent::store($content)
   		], $file));
    }
    
    public static function getThumbnailField($model)
    {
    	if (! $model->thumbnailPossible()) return false;
    	
    	$image = new \Imagick($model->ref('content')['path']  . '[0]');
    	
    	$image->setImageFormat('jpg');
    	
    	return collect([
    			'mime' => 'image/jpeg',
    			'name' => 'preview.jpeg',
    			'contents' => $image . ''
    	]);
    }
    
    public function thumbnailPossible() {
    	return $this->ref('content')['type'] == 'application/pdf' && class_exists('Imagick');
    }
}
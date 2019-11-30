<?php 

namespace Epesi\FileStorage\Integration\Joints;

use Epesi\Core\System\Integration\Modules\ModuleJoint;
use Illuminate\Support\Facades\Auth;

abstract class FileStorageAccessJoint extends ModuleJoint
{
	/**
	 * Define the route used when generating urls for file access
	 * 
	 * @var string
	 */
	protected static $route = 'file';
	
	/**
	 * You can override this variable to define allowed actions
	 *
	 * @var array Possible actions to execute
	 */
	protected static $allowedActions = ['download', 'preview', 'inline'];
	
	/**
	 * You can override this variable to allow access for not logged in users
	 *
	 * @var bool
	 */
	protected $forUsersOnly = true;
	
	/**
	 * Checks if access for the specified file is granted
	 * 
	 * @param \Illuminate\Http\Request $request
	 * @return boolean
	 */
	final public function accessGranted($request)
	{
		$action = $request->get('action', 'download');

		return $this->hasUserAccess() && $this->hasActionAccess($action) && $this->hasAccess($request);
	}
	
	/**
	 * Generates URLs for allowed file actions
	 * 
	 * @param array $params
	 * @return string[]
	 */
	final public static function getActionUrls($file, $accessParams = [])
	{
		$id = is_numeric($file)? $file: $file['id'];
		
		$urls = [];
		foreach (static::$allowedActions as $action) {
			$urls[$action] = url(static::$route) . '?' . http_build_query(compact('id', 'action') + $accessParams);
		}
		
		return $urls;
	}
		
	/**
	 * Determine general user access
	 * 
	 * @return boolean
	 */
	final protected function hasUserAccess()
	{
		return $this->forUsersOnly? (bool) Auth::id(): true;
	}
	
	/**
	 * Determine if there is access to the requested action
	 * 
	 * @param string $action
	 * @return boolean
	 */
	final protected function hasActionAccess($action)
	{
		return in_array($action, (array) static::$allowedActions);
	}
	
	/**
	 * Define custom file access
	 * 
	 * @param \Illuminate\Http\Request $request
	 * @return boolean
	 */
	protected function hasAccess($request)
	{
		return Auth::user()->can('download files');
	}
}
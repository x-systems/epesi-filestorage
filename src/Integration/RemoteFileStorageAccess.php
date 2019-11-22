<?php 

namespace Epesi\FileStorage\Integration;

use Epesi\FileStorage\Database\Models\FileRemoteAccess;

class RemoteFileStorageAccess extends Joints\FileStorageAccessJoint
{
	protected static $allowedActions = 'download';

	protected $forUsersOnly = false;
	
	protected function hasAccess($request)
	{
		return FileRemoteAccess::check($request->get('id'), $request->get('token'));
	}
}
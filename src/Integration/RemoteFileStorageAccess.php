<?php 

namespace Epesi\FileStorage\Integration;

use Epesi\FileStorage\Database\Models\Remote;

class RemoteFileStorageAccess extends Joints\FileStorageAccessJoint
{
	protected static $allowedActions = 'download';

	protected $forUsersOnly = false;
	
	protected function hasAccess($request)
	{
		return Remote::access($request->get('id'), $request->get('token'));
	}
}
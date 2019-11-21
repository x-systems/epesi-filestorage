<?php

namespace Epesi\FileStorage;

use Illuminate\Support\Facades\Auth;

class FileStorageAccess
{
    public function handle($request, \Closure $next)
    {
    	foreach (Integration\Joints\FileStorageAccessJoint::collect() as $joint) {
    		if ($joint->accessGranted($request)) {    			
    			$this->logFileAccess($request->get('id'), $request->get('action', 'download'));
    			
    			return $next($request);
    		}
    	}
    	
    	return response('No access to file', 401);
    }
        
    protected function logFileAccess($metaId, $action, $time = null)
    {
    	$ip_address = request()->ip();

    	Database\Models\AccessLog::create([
    			'meta_id' => $metaId,
    			'accessed_at' => date('Y-m-d H:i:s', $time ?: time()),
    			'accessed_by' => Auth::id() ?: 0,
    			'action' => $action,
    			'ip_address' => $ip_address,
    			'host_name' => gethostbyaddr($ip_address)
    	]);
    }
}

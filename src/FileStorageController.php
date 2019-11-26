<?php

namespace Epesi\FileStorage;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;

class FileStorageController extends Controller
{
    public function get(Request $request)
    {
    	try {
    		$file = Database\Models\File::get($request->get('id'));
    	} catch (\Exception $e) {
    		abort(404);
    	}
    	
    	$useThumbnail = false;
    	$disposition = 'attachment';
    	switch ($request->get('action')) {	
    		case 'preview':
    			$useThumbnail = true;
    			// intended fallthrough
    		case 'inline':
    			$disposition = 'inline';
    			break;
    			
    		default:
    			break;
    	}

    	if ($useThumbnail && $request->get('thumbnail', 1) && ($thumbnail = $file->thumbnail)) {
    		$mime = $thumbnail['mime'];
    		$filename = $thumbnail['name'];
    		$contents = $thumbnail['contents'];
    	}
    	else {
    		$mime = $file->content->type;
    		$filename = $file->name;
    		$contents = $file->content->data;
    	}
    	
    	$headers = [
    			'Content-Type' => $mime,
    			'Content-Length' => strlen($contents),
    			'Content-Disposition' => "$disposition; filename=\"$filename\"",
    	];

    	if ($request->get('nocache')) {
    		$headers['Pragma'] = 'no-cache';
    		$headers['Expires'] = '0';
    	}
    	
    	return response($contents, 200, $headers);
    }
}

<?php

namespace Epesi\FileStorage;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;

class FileStorageController extends Controller
{
    public function get(Request $request)
    {
    	try {
    		$meta = Database\Models\Meta::get($request->get('id'));
    	} catch (\Exception $e) {
    		abort(404);
    	}
    	
    	$useThumbnail = false;
    	$disposition = 'attachment';
    	switch ($request->get('action')) {	
    		case 'preview':
    			$useThumbnail = true;
    		case 'inline':
    			$disposition = 'inline';
    			break;
    			
    		default:
    			break;
    	}

    	if ($useThumbnail && $request->get('thumbnail', 1) && ($thumbnail = $meta->thumbnail)) {
    		$mime = $thumbnail['mime'];
    		$filename = $thumbnail['name'];
    		$contents = $thumbnail['contents'];
    	}
    	else {
    		$mime = $meta->file->type;
    		$filename = $meta->name;
    		$contents = $meta->file->contents;
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

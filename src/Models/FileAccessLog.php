<?php

namespace Epesi\FileStorage\Models;

use atk4\data\Model;
use Epesi\Core\Data\HasEpesiConnection;
use Epesi\Core\System\User\Database\Models\atk4\User;

class FileAccessLog extends Model
{
    use HasEpesiConnection;
    
    public $table = 'filestorage_access_log';

    function init() {
    	parent::init();
    	
    	$this->addFields([
    			'file_id',
    			'accessed_at' => ['caption' => __('Accessed At'), 'type' => 'datetime'],
    			'action' => ['caption' => __('Action'), 'type' => 'enum', 'values' => ['download' => __('Download'), 'preview' => __('Preview'), 'inline' => __('Inline')]],
    			'ip_address' => ['caption' => __('IP Address')],
    			'host_name' => ['caption' => __('Host Name')]
    	]);
    	
    	$this->hasOne('accessed_by', [User::class, 'our_field' => 'accessed_by'])->addTitle(['field' => 'accessed_by_user', 'caption' => __('Accessed By')]);
    }
}
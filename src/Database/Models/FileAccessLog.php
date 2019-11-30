<?php

namespace Epesi\FileStorage\Database\Models;

use Epesi\Core\Data\Model;
use Epesi\Core\System\User\Database\Models\atk4\User;

class FileAccessLog extends Model {

    public $table = 'filestorage_access_log';

    function init() {
    	parent::init();
    	
    	$this->addFields([
    			'file_id',
    			['accessed_at', 'caption' => __('Accessed At')],
    			['action', 'caption' => __('Action')],
    			['ip_address', 'caption' => __('IP Address')],
    			['host_name', 'caption' => __('Host Name')]
    	]);
    	
    	$this->hasOne('accessed_by', [User::class, 'our_field' => 'accessed_by'])->addTitle(['field' => 'accessed_by_user', 'caption' => __('Accessed By')]);
    }
}
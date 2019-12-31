<?php

use Illuminate\Database\Migrations\Migration;
use Epesi\FileStorage\Database\Models\File;
use Epesi\FileStorage\Database\Models\FileAccessLog;
use Epesi\FileStorage\Database\Models\FileContent;
use Epesi\FileStorage\Database\Models\FileRemoteAccess;

class CreateFilestorageTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
    	File::migrate();
    	FileAccessLog::migrate();
    	FileContent::migrate();
    	FileRemoteAccess::migrate();
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {

    }
}

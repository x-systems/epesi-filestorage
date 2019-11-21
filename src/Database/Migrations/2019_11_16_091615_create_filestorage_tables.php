<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateFilestorageTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
    	$this->down();
    	
        Schema::create('filestorage_files', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('hash', 128);
            $table->integer('size');
            $table->string('type', 256);
            
            $table->softDeletes();
        });
        
        Schema::create('filestorage_meta', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name', 256);
            $table->string('link', 128)->nullable();
            $table->string('backref', 128)->nullable();
            $table->unsignedBigInteger('file_id');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            
            $table->softDeletes();
            
            $table->foreign('file_id')->references('id')->on('filestorage_files');
            $table->foreign('created_by')->references('id')->on('users');
        });
        
        Schema::create('filestorage_remote', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('meta_id');            
            $table->string('token', 128);
            $table->unsignedBigInteger('created_by');
            $table->timestamp('expires_at');
            $table->timestamps();
            
            $table->foreign('meta_id')->references('id')->on('filestorage_meta');
            $table->foreign('created_by')->references('id')->on('users');
        });
        
        Schema::create('filestorage_access_log', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('meta_id');            
            $table->timestamp('accessed_at');
            $table->unsignedBigInteger('accessed_by');
            $table->string('type', 32);
            $table->string('ip_address', 32);
            $table->string('host_name', 64);
                        
            $table->foreign('meta_id')->references('id')->on('filestorage_meta');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
    	Schema::dropIfExists('filestorage_remote');
    	Schema::dropIfExists('filestorage_access_log');
    	Schema::dropIfExists('filestorage_meta');
        Schema::dropIfExists('filestorage_files');
    }
}

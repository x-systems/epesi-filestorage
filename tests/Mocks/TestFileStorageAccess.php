<?php 

namespace Epesi\FileStorage\Tests\Mocks;

use Epesi\FileStorage\Integration\Joints\FileStorageAccessJoint;

class TestFileStorageAccess extends FileStorageAccessJoint
{
	protected function hasAccess($request)
	{
		return $request->get('hash') == 'test_hash';
	}
}
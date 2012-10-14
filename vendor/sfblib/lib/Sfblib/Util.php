<?php
class Sfblib_Util
{
	static public function guessMimeType($file)
	{
		switch ( strtolower(self::getFileExtension($file)) ) {
			case 'png' : return 'image/png';
			case 'gif' : return 'image/gif';
			case 'jpg' :
			case 'jpeg': return 'image/jpeg';
		}

		$finfo = new finfo(FILEINFO_MIME_TYPE);
		return $finfo->file($href);
	}


	static public function getFileExtension($filename)
	{
		return ltrim(strrchr($filename, '.'), '.');
	}


	static public function initOrIncArrayValue(&$arr, $key, $init_value = 0)
	{
		if ( isset( $arr[$key] ) ) {
			$arr[$key]++;
		} else {
			$arr[$key] = $init_value;
		}
	}
}

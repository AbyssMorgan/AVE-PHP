<?php

declare(strict_types=1);

namespace App\Services;

use AVE;
use GdImage;
use Imagick;
use Exception;
use App\Dictionaries\MediaOrientation;

class MediaFunctions {

	public function __construct(AVE $ave){
		$this->ave = $ave;
	}

	public function getImageFromPath(string $path) : GdImage|bool|null {
		if(!file_exists($path)) return null;
		switch(strtolower(pathinfo($path, PATHINFO_EXTENSION))){
			case 'bmp': return @imagecreatefrombmp($path);
			case 'avif': return @imagecreatefromavif($path);
			case 'gd2': return @imagecreatefromgd2($path);
			case 'gd': return @imagecreatefromgd($path);
			case 'gif': return @imagecreatefromgif($path);
			case 'jpeg':
			case 'jpg': {
				return @imagecreatefromjpeg($path);
			}
			case 'png': return @imagecreatefrompng($path);
			case 'tga': return @imagecreatefromtga($path);
			case 'wbmp': return @imagecreatefromwbmp($path);
			case 'webp': return @imagecreatefromwebp($path);
			case 'xbm': return @imagecreatefromxbm($path);
			case 'xpm': return @imagecreatefromxpm($path);
		}
		return null;
	}

	public function getImageResolution(string $path) : string {
		$image = $this->getImageFromPath($path);
		if(!$image){
			try {
				$image = new Imagick($path);
				$w = $image->getImageWidth();
				$h = $image->getImageHeight();
				$image->clear();
				return $w."x".$h;
			}
			catch(Exception $e){
				return $this->getVideoResolution($path);
			}
		}
		$w = imagesx($image);
		$h = imagesy($image);
		imagedestroy($image);
		return $w."x".$h;
	}

	public function isGifAnimated(string $path) : bool {
		if(!($fh = @fopen($path, 'rb'))) return false;
		$count = 0;
		while(!feof($fh) && $count < 2){
			$chunk = fread($fh, 1024 * 100);
			$count += preg_match_all('#\x00\x21\xF9\x04.{4}\x00(\x2C|\x21)#s', $chunk, $matches);
		}
		fclose($fh);
		return $count > 1;
	}

	public function getVideoFPS(string $path) : float {
		$this->ave->exec("ffprobe", "-v 0 -of csv=p=0 -select_streams v:0 -show_entries stream=r_frame_rate \"$path\" 2>nul", $output);
		eval('$fps = '.trim(preg_replace('/[^0-9.\/]+/', "", $output[0])).';');
		return $fps;
	}

	public function getVideoResolution(string $path) : string {
		$this->ave->exec("ffprobe", "-v error -select_streams v:0 -show_entries stream^=width^,height -of csv^=s^=x:p^=0 \"$path\" 2>nul", $output);
		return rtrim($output[0] ?? '0x0', 'x');
	}

	public function getVideoDuration(string $path) : string {
		$this->ave->exec("ffprobe", "-i \"$path\" -show_entries format=duration -v quiet -of csv=\"p=0\" -sexagesimal 2>nul", $output);
		$file_duration = trim($output[0]);
		$h = $m = $s = 0;
		sscanf($file_duration,"%d:%d:%d", $h, $m, $s);
		return sprintf("%02d:%02d:%02d", $h, $m, $s);
	}

	public function getVideoDurationSeconds(string $path) : int {
		$this->ave->exec("ffprobe", "-i \"$path\" -show_entries format=duration -v quiet -of csv=\"p=0\" -sexagesimal 2>nul", $output);
		$file_duration = trim($output[0]);
		$h = $m = $s = 0;
		sscanf($file_duration,"%d:%d:%d", $h, $m, $s);
		return (intval($h) * 3600) + (intval($m) * 60) + intval($s);
	}

	public function SecToTime(int $s) : string {
		$d = intval(floor($s / 86400));
		$s -= ($d * 86400);
		$h = intval(floor($s / 3600));
		$s -= ($h * 3600);
		$m = intval(floor($s / 60));
		$s -= ($m * 60);
		if($d > 0){
			return sprintf("%d:%02d:%02d:%02d", $d, $h, $m, $s);
		} else if($h > 0){
			return sprintf("%02d:%02d:%02d", $h, $m, $s);
		} else {
			return sprintf("%02d:%02d", $m, $s);
		}
	}

	public function getVideoThumbnail(string $path, string $output, int $w, int $r, int $c) : bool {
		$input_file = $output.DIRECTORY_SEPARATOR.pathinfo($path, PATHINFO_FILENAME)."_s.jpg";
		$output_file = $output.DIRECTORY_SEPARATOR.pathinfo($path, PATHINFO_BASENAME).".webp";
		if(file_exists($output_file)) return true;
		if(!file_exists($input_file)){
			$this->ave->exec("mtn", "-w $w -r $r -c $c -P \"$path\" -O \"$output\" >nul 2>nul", $out);
			if(!file_exists($input_file)) return false;
		}
		$image = new Imagick();
		$image->readImage($input_file);
		$image->writeImage($output_file);
		unlink($input_file);
		return file_exists($output_file);
	}

	public function getMediaOrientation(int $width, int $height) : int {
		if($width > $height){
			return MediaOrientation::MEDIA_ORIENTATION_HORIZONTAL;
		} else if($height > $width){
			return MediaOrientation::MEDIA_ORIENTATION_VERTICAL;
		} else {
			return MediaOrientation::MEDIA_ORIENTATION_SQUARE;
		}
	}

	public function getMediaOrientationName(int $orientation) : string {
		switch($orientation){
			case MediaOrientation::MEDIA_ORIENTATION_HORIZONTAL: return 'Horizontal';
			case MediaOrientation::MEDIA_ORIENTATION_VERTICAL: return 'Vertical';
			case MediaOrientation::MEDIA_ORIENTATION_SQUARE: return 'Square';
		}
		return 'Unknown';
	}

	public function getMediaQuality(int $width, int $height) : string {
		$v = max($width, $height);
		if($v >= 30720){
			return '17280';
		} else if($v >= 15360){
			return '8640';
		} else if($v >= 7680){
			return '4320';
		} else if($v >= 3840){
			return '2160';
		} else if($v >= 2560){
			return '1440';
		} else if($v >= 1920){
			return '1080';
		} else if($v >= 1280){
			return '720';
		} else if($v >= 1024){
			return '540';
		} else if($v >= 850){
			return '480';
		} else if($v >= 640){
			return '360';
		} else if($v >= 320){
			return '240';
		} else {
			return '144';
		}
	}

	public function getImageColorCount(string $path) : int {
		$imagick = new Imagick($path);
		return $imagick->getImageColors();
	}

	public function getImageColorGroup(int $colors) : string {
		if($colors >= 100000){
			return 'Very-High';
		} else if($colors >= 30000){
			return 'High';
		} else if($colors >= 10000){
			return 'Medium';
		} else if($colors >= 1000){
			return 'Low';
		} else {
			return 'Very-Low';
		}
	}

	public function format_episode(int $episode, int $digits, int $max) : string {
		$episode = $episode % $max;
		if($episode < 0) $episode += $max;
		$ep = strval($episode);
		return str_repeat("0", $digits - strlen($ep)).$ep;
	}

}

?>

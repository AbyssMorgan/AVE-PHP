<?php

/* AVE-PHP v2.2.4 */

declare(strict_types=1);

namespace AveCore;

class BinaryFile {

	private mixed $file = null;
	private ?string $path = null;

	function __construct(?string $path = null, ?int $allocate = null){
		$this->path = $path;
		if(!is_null($path)) $this->open($path, $allocate);
	}

	public function create(string $path, ?int $allocate = null) : bool {
		if(file_exists($path)) return false;
		$folder = pathinfo($path, PATHINFO_DIRNAME);
		if(!file_exists($folder) && !@mkdir($folder, 0755, true)) return false;
		$file = @fopen($path, "wb");
		if(!$file) return false;
		if(!is_null($allocate) && $allocate > 0){
			fseek($file, $allocate - 1);
			fwrite($file, "\0");
		} else {
			fwrite($file, "");
		}
		fclose($file);
		return file_exists($path);
	}

	public function open(string $path, ?int $allocate = null) : bool {
		if(!is_null($this->file)) return false;
		if(!file_exists($path) && !$this->create($path, $allocate)) return false;
		$this->file = fopen($path, "r+b");
		if(!$this->file) return false;
		$this->path = $path;
		return true;
	}

	public function close() : bool {
		if(is_null($this->file)) return false;
		fclose($this->file);
		$this->file = null;
		$this->path = null;
		return true;
	}

	public function read(int $offset = 0, ?int $length = null) : string|false {
		if(is_null($this->file)) return false;
		fseek($this->file, $offset);
		if(is_null($length)) $length = filesize($this->path) - $offset;
		if($length <= 0) return "";
		return fread($this->file, $length);
	}

	public function write(string $data, int $offset = 0, ?int $length = null) : int|false {
		if(is_null($this->file)) return false;
		fseek($this->file, $offset);
		return fwrite($this->file, $data, $length);
	}

	public function size() : int|false {
		if(is_null($this->file)) return false;
		return filesize($this->path);
	}

	public function truncate(int $size) : bool {
		if(is_null($this->file)) return false;
		return ftruncate($this->file, $size);
	}

}

?>
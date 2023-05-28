<?php

declare(strict_types=1);

namespace App\Services;

use IntlTimeZone;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;
use FilesystemIterator;
use DirectoryIterator;

class AveCore {

	public int $core_version = 3;

	public IniFile $config;

	public Logs $log_event;
	public Logs $log_error;
	public Logs $log_data;

	public ?string $command;
	public array $arguments;
	public bool $windows;
	public string $logo;
	public string $path;
	public string $tool_name;
	public string $subtool_name;
	public array $folders_state = [];
	public $tool;

	public function __construct(array $arguments){
		date_default_timezone_set(IntlTimeZone::createDefault()->getID());
		unset($arguments[0]);
		$this->command = $arguments[1] ?? null;
		if(isset($arguments[1])) unset($arguments[1]);
		$this->arguments = array_values($arguments);
		$this->windows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
		$this->path = $this->get_file_path(__DIR__."/../..");
		$this->logo = '';
	}

	public function select_action() : bool {
		do {
			$this->clear();
			$this->title("$this->app_name v$this->version > $this->tool_name");
			$this->tool->help();
			$line = $this->get_input(" Action: ");
			if($line == '#') return false;
			$response = $this->tool->action($line);
		}
		while(!$response);
		return true;
	}

	public function setup_folders(array $folders) : void {
		$this->folders_state = [];
		foreach($folders as $folder){
			$this->folders_state[$folder] = file_exists($folder) ? '' : '[NOT EXISTS]';
			$this->write_log("Scan: $folder");
		}
		$this->print_folders_state();
	}

	public function set_folder_done(string $folder) : void {
		$this->folders_state[$folder] = '[DONE]';
		$this->print_folders_state();
	}

	public function print_folders_state() : void {
		$this->clear();
		foreach($this->folders_state as $folder_name => $state){
			$this->echo(" Scan: \"$folder_name\" $state");
		}
	}

	public function set_tool(string $name) : void {
		$this->tool_name = $name;
		$this->subtool_name = '';
		$this->title("$this->app_name v$this->version > $this->tool_name");
		$this->write_log("Set Tool: $this->tool_name");
	}

	public function set_subtool(string $name) : void {
		$this->subtool_name = $name;
		$this->title("$this->app_name v$this->version > $this->tool_name > $this->subtool_name");
		$this->write_log("Set Tool: $this->tool_name > $this->subtool_name");
	}

	public function set_progress(int $progress, int $errors) : void {
		$title = "$this->app_name v$this->version > $this->tool_name";
		if(!empty($this->subtool_name)) $title .= " > $this->subtool_name";
		$this->title("$title > Files: $progress Errors: $errors");
	}

	public function set_progress_ex(string $label, int $progress, int $total) : void {
		$title = "$this->app_name v$this->version > $this->tool_name";
		if(!empty($this->subtool_name)) $title .= " > $this->subtool_name";
		$this->title("$title > $label: $progress / $total");
	}

	public function clear() : void {
		$this->cls();
		if($this->config->get('AVE_SHOW_LOGO', false)){
			echo "$this->logo\r\n";
		} else {
			echo "\r\n";
		}
	}

	public function get_version_number(string $version) : int {
		$ver = explode(".", $version);
		return 10000 * intval($ver[0]) + 100*intval($ver[1]) + intval($ver[2]);
	}

	public function print_help(array $help) : void {
		echo implode("\r\n", $help)."\r\n\r\n";
	}

	public function progress(int|float $count, int|float $total) : void {
		if($total > 0){
			$percent = sprintf("%.02f", ($count / $total) * 100.0);
			echo " Progress: $percent %        \r";
		}
	}

	public function is_valid_label(string $label) : bool {
		return preg_match('/(?=[a-zA-Z0-9_\-]{3,20}$)/i', $label) == 1;
	}

	public function progress_ex(string $label, int|float $count, int|float $total) : void {
		if($total > 0){
			$percent = sprintf("%.02f", ($count / $total) * 100.0);
			echo " $label Progress: $percent %                                        \r";
		}
	}

	public function getHashFromIDX(string $path, array &$keys, bool $progress) : int {
		if(!file_exists($path)) return 0;
		$cnt = 0;
		$size = filesize($path);
		$fp = @fopen($path, "r");
		if($fp){
			while(($line = fgets($fp)) !== false){
				$line = trim($line);
				$keys[pathinfo($line, PATHINFO_FILENAME)] = $line;
				$cnt++;
				if($progress) $this->progress(ftell($fp), $size);
			}
			fclose($fp);
		}
		return $cnt;
	}

	public function formatBytes(int $bytes, int $precision = 2) : string {
		if($bytes <= 0) return '0.00 B';
		$i = floor(log($bytes)/log(1024));
		$sizes = ['B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'];
		return sprintf('%.'.$precision.'f', $bytes/pow(1024, $i)).' '.$sizes[$i];
	}

	public function getFiles(string $path, array|null $extensions = null, array|null $except = null) : array {
		if(!file_exists($path)) return [];
		$data = [];
		$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::KEY_AS_PATHNAME | FilesystemIterator::CURRENT_AS_FILEINFO | FilesystemIterator::SKIP_DOTS));
		foreach($files as $file){
			if($file->isDir() || $file->isLink()) continue;
			if(!is_null($extensions) && !in_array(strtolower($file->getExtension()), $extensions)) continue;
			if(!is_null($except) && in_array(strtolower($file->getExtension()), $except)) continue;
			$fp = $file->getRealPath();
			if(!$fp) continue;
			array_push($data, $fp);
		}
		return $data;
	}

	public function getFolders(string $path) : array {
		if(!file_exists($path)) return [];
		$data = [];
		$files = new DirectoryIterator($path);
		array_push($data, $path);
		foreach($files as $file){
			if($file->isDir() && !$file->isDot()){
				$data = array_merge($data, $this->getFolders($file->getRealPath()));
			}
		}
		return $data;
	}

	public function exit(int $seconds = 10, bool $open_log = false) : void {
		$this->write_log("Exit");
		$this->open_logs($open_log, false);
		$this->timeout($seconds);
	}

	public function init_logs(){
		$timestamp = date("Y-m-d His");
		$this->log_event = new Logs($this->get_file_path($this->config->get('AVE_LOG_FOLDER')."/$timestamp-Event.txt"), true, true);
		$this->log_error = new Logs($this->get_file_path($this->config->get('AVE_LOG_FOLDER')."/$timestamp-Error.txt"), true, true);
		$this->log_data = new Logs($this->get_file_path($this->config->get('AVE_DATA_FOLDER')."/$timestamp.txt"), false, true);
	}

	public function open_logs(bool $open_event = false, bool $init = true) : void {
		$this->log_event->close();
		$this->log_error->close();
		$this->log_data->close();
		if($this->config->get('AVE_OPEN_LOG_EVENT', true) && $open_event && file_exists($this->log_event->getPath())){
			$this->open_file($this->log_event->getPath());
		}
		if(file_exists($this->log_data->getPath())){
			$this->open_file($this->log_data->getPath());
		}
		if(file_exists($this->log_error->getPath())){
			$this->open_file($this->log_error->getPath());
		}
		if($init) $this->init_logs();
	}

	public function timeout(int $seconds) : void {
		$this->title("$this->app_name v$this->version > Exit $seconds seconds");
		if($seconds > 0){
			sleep(1);
			$seconds--;
			$this->timeout($seconds);
		} else {
			exit(0);
		}
	}

	public function write_log(string|array $data) : void {
		if($this->config->get('AVE_LOG_EVENT', true)){
			$this->log_event->write($data);
		}
	}

	public function write_error(string|array $data) : void {
		if($this->config->get('AVE_LOG_ERROR', true)){
			$this->log_error->write($data);
		}
	}

	public function write_data(string|array $data) : void {
		$this->log_data->write($data);
	}

	public function rrmdir(string $dir) : bool {
		if(!file_exists($dir)) return false;
		if(is_dir($dir)){
			$objects = scandir($dir);
			foreach($objects as $object){
				if($object == "." || $object == "..") continue;
				$subdir = $this->get_file_path("$dir/$object");
				if(is_dir($subdir) && !is_link($subdir)){
					$this->rrmdir($subdir);
				} else {
					$this->unlink($subdir);
				}
			}
			$this->rmdir($dir);
		}
		return true;
	}

	public function rmdir(string $path, bool $log = true) : bool {
		if(!file_exists($path) || !is_dir($path)) return false;
		if(rmdir($path)){
			if($log) $this->write_log("DELETE \"$path\"");
			return true;
		} else {
			if($log) $this->write_error("FAILED DELETE \"$path\"");
			return false;
		}
	}

	public function unlink(string $path, bool $log = true) : bool {
		if(!file_exists($path) || is_dir($path)) return false;
		if(unlink($path)){
			if($log) $this->write_log("DELETE \"$path\"");
			return true;
		} else {
			if($log) $this->write_error("FAILED DELETE \"$path\"");
			return false;
		}
	}

	public function mkdir(string $path, bool $log = true) : bool {
		if(file_exists($path) && is_dir($path)) return true;
		if(mkdir($path, 0755, true)){
			if($log) $this->write_log("MKDIR \"$path\"");
			return true;
		} else {
			if($log) $this->write_error("FAILED MKDIR \"$path\"");
			return false;
		}
	}

	public function rename(string $from, string $to, bool $log = true) : bool {
		if($from == $to) return true;
		if(file_exists($to) && pathinfo($from, PATHINFO_DIRNAME) != pathinfo($to, PATHINFO_DIRNAME)){
			if($log) $this->write_error("FAILED RENAME \"$from\" \"$to\" FILE EXIST");
			return false;
		}
		$dir = pathinfo($to, PATHINFO_DIRNAME);
		if(!file_exists($dir)) $this->mkdir($dir);
		if(rename($from, $to)){
			if($log) $this->write_log("RENAME \"$from\" \"$to\"");
			return true;
		} else {
			if($log) $this->write_error("FAILED RENAME \"$from\" \"$to\"");
			return false;
		}
	}

	public function copy(string $from, string $to, bool $log = true) : bool {
		if($from == $to) return true;
		if(file_exists($to) && pathinfo($from, PATHINFO_DIRNAME) != pathinfo($to, PATHINFO_DIRNAME)){
			if($log) $this->write_error("FAILED COPY \"$from\" \"$to\" FILE EXIST");
			return false;
		}
		$dir = pathinfo($to, PATHINFO_DIRNAME);
		if(!file_exists($dir)) $this->mkdir($dir);
		if(copy($from, $to)){
			if($log) $this->write_log("COPY \"$from\" \"$to\"");
			return true;
		} else {
			if($log) $this->write_error("FAILED COPY \"$from\" \"$to\"");
			return false;
		}
	}

	public function delete_files(string $path, array|null $extensions = null, array|null $except = null) : void {
		$files = $this->getFiles($path, $extensions, $except);
		foreach($files as $file){
			$this->unlink($file);
		}
	}

	public function cmd_escape(string $text) : string {
		return str_replace([">", "<"], ["^>", "^<"], $text);
	}

	public function title(string $title) : void {
		if($this->windows){
			system("TITLE ".$this->cmd_escape($title));
		}
	}

	public function cls() : void {
		if($this->windows){
			popen('cls', 'w');
		}
	}

	public function get_input(string $message = '') : string {
		if(!empty($message)) echo $message;
		return trim(readline());
	}

	public function get_input_no_trim(string $message = '') : string {
		if(!empty($message)) echo $message;
		return readline();
	}

	public function pause(?string $message = null) : void {
		if(!is_null($message)) echo $message;
		if($this->windows){
			system("PAUSE > nul");
		}
	}

	public function get_folders(string $string) : array {
		$string = trim($string);
		$folders = [];

		$length = strlen($string);
		$offset = 0;

		while($offset < $length){
			if(substr($string, $offset, 1) == '"'){
				$end = strpos($string, '"', $offset+1);
				array_push($folders, $this->get_file_path(substr($string, $offset+1, $end - $offset-1)));
				$offset = $end + 1;
			} else if(substr($string, $offset, 1) == ' '){
				$offset++;
			} else {
				$end = strpos($string, ' ', $offset);
				if($end !== false){
					array_push($folders, $this->get_file_path(substr($string, $offset, $end - $offset)));
					$offset = $end + 1;
				} else {
					array_push($folders, $this->get_file_path(substr($string, $offset)));
					$offset = $length;
				}
			}
		}

		return array_unique($folders);
	}

	public function echo(string $string = '') : void {
		echo "$string\r\n";
	}

	public function get_variable(string $string) : string {
		exec("echo $string", $var);
		return $var[0] ?? '';
	}

	public function open_file(string $path, string $params = '/MIN') : void {
		if($this->windows && file_exists($path)){
			exec("START $params \"\" \"$path\"");
		}
	}

	public function open_url(string $url) : void {
		if(strpos($url, "https://") !== false || strpos($url, "http://") !== false){
			exec("START \"\" \"$url\"");
		}
	}

	public function get_file_attributes(string $path) : array {
		if($this->windows){
			exec("ATTRIB \"$path\"", $var);
			$attributes = str_replace($path, '', $var[0]);
			return [
				'R' => (strpos($attributes, "R") !== false),
				'A' => (strpos($attributes, "A") !== false),
				'S' => (strpos($attributes, "S") !== false),
				'H' => (strpos($attributes, "H") !== false),
				'I' => (strpos($attributes, "I") !== false),
			];
		} else {
			return [
				'R' => false,
				'A' => false,
				'S' => false,
				'H' => false,
				'I' => false,
			];
		}
	}

	public function set_file_attributes(string $path, bool|null $r = null, bool|null $a = null, bool|null $s = null, bool|null $h = null, bool|null $i = null) : void {
		if($this->windows){
			$attributes = '';
			if(!is_null($r)) $attributes .= ($r ? '+' : '-').'R ';
			if(!is_null($a)) $attributes .= ($a ? '+' : '-').'A ';
			if(!is_null($s)) $attributes .= ($s ? '+' : '-').'S ';
			if(!is_null($h)) $attributes .= ($h ? '+' : '-').'H ';
			if(!is_null($i)) $attributes .= ($i ? '+' : '-').'I ';
			exec("ATTRIB $attributes \"$path\"");
		}
	}

	public function is_valid_device(string $path) : bool {
		if(!$this->windows) return true;
		if(substr($path, 1, 1) == ':'){
			return file_exists(substr($path, 0, 3));
		} else if(substr($path, 0, 2) == "\\\\"){
			$device = substr($path, 2);
			if(strpos($device, "\\") !== false){
				$parts = explode("\\", $device);
				return file_exists("\\\\".$parts[0]."\\".$parts[1]);
			} else {
				return false;
			}
		}
	}

	public function get_file_path(string $path) : string {
		return str_replace(["/", "\\"], DIRECTORY_SEPARATOR, $path);
	}

	public function getComputerName() : string {
		if($this->windows){
			return $this->get_variable("%COMPUTERNAME%");
		} else {
			return shell_exec('hostname');
		}
	}

	public function get_arguments_folders(array $arguments) : string {
		$output = '';
		foreach($arguments as $argument){
			$argument = $this->get_file_path($argument);
			if(substr($argument, 0, 1) == '"'){
				$output .= ' '.$argument;
			} else {
				$output .= ' "'.$argument.'"';
			}
		}
		return $output;
	}

}

?>

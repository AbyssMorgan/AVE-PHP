<?php

declare(strict_types=1);

namespace App\Tools;

use AVE;
use FilesystemIterator;

class DirectoryFunctions {

	private string $name = "DirectoryFunctions";

	private array $params = [];
	private string $action;
	private AVE $ave;

	public function __construct(AVE $ave){
		$this->ave = $ave;
		$this->ave->set_tool($this->name);
	}

	public function help() : void {
		$this->ave->print_help([
			' Actions:',
			' 0 - Delete empty dirs',
			' 1 - Force load icon (desktop.ini)',
			' 2 - Count files in every dirs',
			' 3 - Clone folder structure',
		]);
	}

	public function action(string $action) : bool {
		$this->params = [];
		$this->action = $action;
		switch($this->action){
			case '0': return $this->ToolDeleteEmptyDirs();
			case '1': return $this->ToolForceLoadIcon();
			case '2': return $this->ToolCountFiles();
			case '3': return $this->ToolCloneFolderStructure();
		}
		return false;
	}

	public function ToolDeleteEmptyDirs() : bool {
		$this->ave->clear();
		$this->ave->set_subtool("DeleteEmptyDirs");
		echo " Folders: ";
		$line = $this->ave->get_input();
		if($line == '#') return false;
		$folders = $this->ave->get_folders($line);

		$this->ave->setup_folders($folders);

		$progress = 0;
		$errors = 0;
		$this->ave->set_progress($progress, $errors);

		foreach($folders as $folder){
			if(!file_exists($folder)) continue;
			$files = array_reverse($this->ave->getFolders($folder));
			$items = 0;
			$total = count($files);
			foreach($files as $file){
				$items++;
				if(!file_exists($file)) continue 1;
				$count = iterator_count(new FilesystemIterator($file, FilesystemIterator::SKIP_DOTS));
				if($count == 0){
					if($this->ave->rmdir($file)){
						$progress++;
					} else {
						$errors++;
					}
				}
				$this->ave->progress($items, $total);
				$this->ave->set_progress($progress, $errors);
			}
			unset($files);
			$this->ave->set_folder_done($folder);
		}
		return true;
	}

	public function ToolForceLoadIcon() : bool {
		$this->ave->clear();
		$this->ave->set_subtool("ForceLoadIcon");
		echo " Folders: ";
		$line = $this->ave->get_input();
		if($line == '#') return false;
		$folders = $this->ave->get_folders($line);

		$this->ave->setup_folders($folders);

		$progress = 0;
		$errors = 0;
		$this->ave->set_progress($progress, $errors);

		foreach($folders as $folder){
			if(!file_exists($folder)) continue;
			$files = $this->ave->getFolders($folder);
			$items = 0;
			$total = count($files);
			foreach($files as $file){
				$items++;
				if(!file_exists($file)) continue 1;
				if(!file_exists($file.DIRECTORY_SEPARATOR."desktop.ini")) continue 1;
				$a = $this->ave->get_file_attributes($file);
				$this->ave->set_file_attributes($file, true, $a['A'], $a['S'], $a['H']);
				$progress++;
				$this->ave->progress($items, $total);
				$this->ave->set_progress($progress, $errors);
			}
			unset($files);
			$this->ave->set_folder_done($folder);
		}
		return true;
	}

	public function ToolCountFiles() : bool {
		$this->ave->clear();
		$this->ave->set_subtool("CountFiles");

		echo " Extensions (empty for all): ";
		$line = $this->ave->get_input();
		if($line == '#') return false;

		if($line == '' || $line == '*'){
			$extensions = null;
		} else {
			$extensions = explode(" ", $line);
		}

		echo " Folders: ";
		$line = $this->ave->get_input();
		if($line == '#') return false;
		$folders = $this->ave->get_folders($line);

		$this->ave->setup_folders($folders);

		$progress = 0;
		$errors = 0;
		$this->ave->set_progress($progress, $errors);

		$data = [];

		foreach($folders as $folder){
			if(!file_exists($folder)) continue;
			$files = $this->ave->getFiles($folder, $extensions);
			$this->ave->write_log($files);
			$items = 0;
			$total = count($files);
			foreach($files as $file){
				$items++;
				if(!file_exists($file)) continue 1;
				$progress++;
				$key = pathinfo($file, PATHINFO_DIRNAME);
				if(!isset($data[$key])) $data[$key] = 0;
				$data[$key]++;
				$this->ave->progress($items, $total);
				$this->ave->set_progress($progress, $errors);
			}
			unset($files);
			$this->ave->set_folder_done($folder);
		}

		foreach($data as $path => $count){
			if($this->ave->config->get('AVE_FILE_COUNT_FORMAT') == 'CSV'){
				$this->ave->write_data("$count;\"$path\"");
			} else {
				$this->ave->write_data("\"$count\" \"$path\"");
			}
		}

		unset($data);

		return true;
	}

	public function ToolCloneFolderStructure() : bool {
		$this->ave->clear();
		$this->ave->set_subtool("CloneFolderStructure");

		set_input:
		echo " Input (Folder): ";
		$line = $this->ave->get_input();
		if($line == '#') return false;
		$folders = $this->ave->get_folders($line);
		if(!isset($folders[0])) goto set_input;
		$input = $folders[0];

		if(!file_exists($input) || !is_dir($input)){
			echo " Invalid input folder\r\n";
			goto set_input;
		}

		set_output:
		echo " Output (Folder): ";
		$line = $this->ave->get_input();
		if($line == '#') return false;
		$folders = $this->ave->get_folders($line);
		if(!isset($folders[0])) goto set_output;
		$output = $folders[0];

		if((file_exists($output) && !is_dir($output)) || !$this->ave->mkdir($output)){
			echo " Invalid output folder\r\n";
			goto set_output;
		}

		$progress = 0;
		$errors = 0;
		$this->ave->set_progress($progress, $errors);

		$folders = $this->ave->getFolders($input);
		$items = 0;
		$total = count($folders);
		foreach($folders as $folder){
			$items++;
			$directory = str_replace($input, $output, $folder);
			if(!file_exists($directory)){
				if($this->ave->mkdir($directory)){
					$progress++;
				} else {
					$errors++;
				}
			}
			$this->ave->progress($items, $total);
			$this->ave->set_progress($progress, $errors);
		}
		return true;
	}

}

?>

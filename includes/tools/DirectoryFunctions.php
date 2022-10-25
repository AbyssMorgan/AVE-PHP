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

	public function help(){
		$this->ave->print_help([
			' Actions:',
			' 0 - Delete empty dirs',
			' 1 - Force load icon (desktop.ini)',
		]);
	}

	public function action(string $action){
		$this->params = [];
		$this->action = $action;
		switch($this->action){
			case '0': return $this->tool_deleteemptydirs_action();
			case '1': return $this->tool_forceloadicon_action();
		}
		$this->ave->select_action();
	}

	public function tool_deleteemptydirs_action(){
		$this->ave->clear();
		$this->ave->set_subtool("DeleteEmptyDirs");
		echo " Folders: ";
		$line = $this->ave->get_input();
		if($line == '#') return $this->ave->select_action();
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
		$this->ave->exit();
	}

	public function tool_forceloadicon_action(){
		$this->ave->clear();
		$this->ave->set_subtool("ForceLoadIcon");
		echo " Folders: ";
		$line = $this->ave->get_input();
		if($line == '#') return $this->ave->select_action();
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
				$this->ave->progress($items, $total);
				$this->ave->set_progress($progress, $errors);
			}
			unset($files);
			$this->ave->set_folder_done($folder);
		}
		$this->ave->exit();
	}

}

?>

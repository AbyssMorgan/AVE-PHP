<?php

declare(strict_types=1);

namespace App\Tools;

use AVE;
use App\Services\GuardPattern;
use App\Services\GuardDriver;
use AveCore\IniFile;

class CheckFileIntegrity {

	private string $name = "Check File Integrity";
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
			' 0 - Create pattern',
			' 1 - Generate guard',
			' 2 - Check integrity',
			' 3 - Get files tree',
			' 4 - Update guard: Remove missing files',
			' 5 - Update guard: Add unknown files',
			' 6 - Update guard: Update changed files',
			' 7 - Update guard: Update missing + unknown',
		]);
	}

	public function action(string $action) : bool {
		$this->params = [];
		$this->action = $action;
		switch($this->action){
			case '0': return $this->tool_create_pattern();
			case '1': return $this->tool_guard_generate();
			case '2': return $this->tool_check_integrity();
			case '3': return $this->tool_get_files_tree();
			case '4': return $this->tool_update_remove_missing();
			case '5': return $this->tool_update_add_unknown();
			case '6': return $this->tool_update_changed();
			case '7': return $this->tool_update_missing_and_unknown();
		}
		return false;
	}

	public function tool_create_pattern() : bool {
		$this->ave->clear();
		$this->ave->set_subtool("Create pattern");

		set_input:
		$line = $this->ave->get_input(" Input (Folder): ");
		if($line == '#') return false;
		$folders = $this->ave->get_input_folders($line);
		if(!isset($folders[0])) goto set_input;
		$input = $folders[0];

		if(!file_exists($input) || !is_dir($input)){
			$this->ave->echo(" Invalid input folder");
			goto set_input;
		}

		set_output:
		$line = $this->ave->get_input(" Output (Folder): ");
		if($line == '#') return false;
		$folders = $this->ave->get_input_folders($line);
		if(!isset($folders[0])) goto set_output;
		$output = $folders[0];

		if((file_exists($output) && !is_dir($output)) || !$this->ave->mkdir($output)){
			$this->ave->echo(" Invalid output folder");
			goto set_output;
		}

		set_name:
		$line = $this->ave->get_input(" Name: ");
		if($line == '#') return false;

		$pattern_file = $this->ave->get_input_folders($line);
		if(!isset($pattern_file[0])) goto set_name;
		$pattern_file = preg_replace('/[^A-Za-z0-9_\-]/', '_', $pattern_file[0]).".ave-pat";

		$pattern = new GuardPattern();
		$pattern->set_input($input);

		set_folders:
		$line = $this->ave->get_input(" Folders: ");
		if($line == '#') return false;
		foreach($this->ave->get_input_folders($line) as $folder){
			$pattern->add_folders(str_replace([$input.DIRECTORY_SEPARATOR, $input], "", $folder));
		}

		if(!empty($line)){
			if($this->ave->get_confirm(" More (Y/N): ")) goto set_folders;
		}

		set_files:
		$line = $this->ave->get_input(" Files: ");
		if($line == '#') return false;
		foreach($this->ave->get_input_folders($line) as $file){
			$pattern->add_files(str_replace([$input.DIRECTORY_SEPARATOR, $input], "", $file));
		}

		if(!empty($line)){
			if($this->ave->get_confirm(" More (Y/N): ")) goto set_files;
		}

		$file_name = $this->ave->get_file_path("$output/$pattern_file");

		file_put_contents($file_name, $pattern->get());

		$this->ave->open_logs(false);
		$this->ave->pause(" Operation done, press any key to back to menu");
		return false;
	}

	public function tool_guard_generate() : bool {
		$this->ave->clear();
		$this->ave->set_subtool("Guard generate");

		set_pattern:
		$line = $this->ave->get_input(" Pattern (.ave-pat): ");
		if($line == '#') return false;
		$folders = $this->ave->get_input_folders($line);
		if(!isset($folders[0])) goto set_pattern;
		$pattern_file = $folders[0];

		if(!file_exists($pattern_file)){
			$this->ave->echo(" Pattern file not exists");
			goto set_pattern;
		}

		$pattern = new GuardPattern();
		$pattern->load(file_get_contents($pattern_file));

		$input = $pattern->get_input();
		if(!file_exists($input) || !is_dir($input) || empty($input)){
			$this->ave->echo(" Invalid input folder: \"$input\"");
			goto set_pattern;
		}

		$files = count($pattern->get_files());
		$folders = count($pattern->get_folders());
		$this->ave->echo(" Loaded $folders folders and $files files");

		$guard_file = str_replace(chr(0x5C).chr(0x5C), chr(0x5C), $this->ave->get_file_path("$input/".pathinfo($pattern_file, PATHINFO_FILENAME).".ave-guard"));

		$cwd = getcwd();
		chdir($input);
		$this->ave->echo(" Generate $guard_file");
		$guard = new GuardDriver($guard_file, $pattern->get_folders(), $pattern->get_files());
		$guard->generate();
		chdir($cwd);

		$this->ave->open_logs(false);
		$this->ave->pause(" Operation done, press any key to back to menu");
		return false;
	}

	public function tool_check_integrity() : bool {
		$this->ave->clear();
		$this->ave->set_subtool("Check integrity");

		set_guard:
		$line = $this->ave->get_input(" Guard (.ave-guard): ");
		if($line == '#') return false;
		$folders = $this->ave->get_input_folders($line);
		if(!isset($folders[0])) goto set_guard;
		$guard_file = $folders[0];

		if(!file_exists($guard_file)){
			$this->ave->echo(" Guard file not exists");
			goto set_guard;
		}

		$ini = new IniFile($guard_file, true);
		if(is_null($ini->get('keys'))){
			$this->ave->echo(" File don't contain one of required information (Not a valid guard file ?)");
			goto set_guard;
		}

		$input = pathinfo($guard_file, PATHINFO_DIRNAME);

		$cwd = getcwd();
		chdir($input);
		$this->ave->echo(" Validate files from $guard_file");
		$guard = new GuardDriver($guard_file, $ini->get('folders_to_scan'), $ini->get('files_to_scan'));
		$validation = $guard->validate();
		chdir($cwd);

		$damaged = 0;
		$missing = 0;
		$unknown = 0;

		$errors = [
			'damaged' => [],
			'unknown' => [],
			'missing' => [],
		];

		foreach($validation as $error){
			switch($error['type']){
				case 'damaged': {
					$damaged++;
					array_push($errors['damaged'], $error['file']);
					break;
				}
				case 'unknown': {
					$unknown++;
					array_push($errors['unknown'], $error['file']);
					break;
				}
				case 'missing': {
					$missing++;
					array_push($errors['missing'], $error['file']);
					break;
				}
			}
		}

		$this->ave->write_data("State: $damaged damaged, $missing missing, $unknown unknown");
		$this->ave->write_data(["", "Damaged:"]);
		$this->ave->write_data($errors['damaged'] ?? []);
		$this->ave->write_data(["", "Missing:"]);
		$this->ave->write_data($errors['missing'] ?? []);
		$this->ave->write_data(["", "Unknown:"]);
		$this->ave->write_data($errors['unknown'] ?? []);

		$this->ave->open_logs(false);
		$this->ave->pause(" Operation done, press any key to back to menu");
		return false;
	}

	public function tool_get_files_tree() : bool {
		$this->ave->clear();
		$this->ave->set_subtool("Get files tree");

		set_guard:
		$line = $this->ave->get_input(" Guard (.ave-guard): ");
		if($line == '#') return false;
		$folders = $this->ave->get_input_folders($line);
		if(!isset($folders[0])) goto set_guard;
		$guard_file = $folders[0];

		if(!file_exists($guard_file)){
			$this->ave->echo(" Guard file not exists");
			goto set_guard;
		}

		$ini = new IniFile($guard_file, true);
		if(is_null($ini->get('keys'))){
			$this->ave->echo(" File don't contain one of required information (Not a valid guard file ?)");
			goto set_guard;
		}

		$guard = new GuardDriver($guard_file);
		$tree_file = "$guard_file.txt";

		file_put_contents($tree_file, print_r($guard->get_tree(), true));

		$this->ave->open_file($tree_file);

		$this->ave->open_logs(false);
		$this->ave->pause(" Operation done, press any key to back to menu");
		return false;
	}

	public function tool_update_remove_missing() : bool {
		$this->ave->clear();
		$this->ave->set_subtool("Update remove missing");
		$guard_file = $this->tool_guard_set_file();
		if(is_null($guard_file)) return false;
		$this->tool_guard_update($guard_file, ['damaged' => false, 'unknown' => false, 'missing' => true]);
		$this->ave->open_logs(true);
		$this->ave->pause(" Operation done, press any key to back to menu");
		return false;
	}

	public function tool_update_add_unknown() : bool {
		$this->ave->clear();
		$this->ave->set_subtool("Update add unknown");
		$guard_file = $this->tool_guard_set_file();
		if(is_null($guard_file)) return false;
		$this->tool_guard_update($guard_file, ['damaged' => false, 'unknown' => true, 'missing' => false]);
		$this->ave->open_logs(true);
		$this->ave->pause(" Operation done, press any key to back to menu");
		return false;
	}

	public function tool_update_changed() : bool {
		$this->ave->clear();
		$this->ave->set_subtool("Update changed");
		$guard_file = $this->tool_guard_set_file();
		if(is_null($guard_file)) return false;
		$this->tool_guard_update($guard_file, ['damaged' => true, 'unknown' => false, 'missing' => false]);
		$this->ave->open_logs(true);
		$this->ave->pause(" Operation done, press any key to back to menu");
		return false;
	}

	public function tool_update_missing_and_unknown() : bool {
		$this->ave->clear();
		$this->ave->set_subtool("Update missing and unknown");
		$guard_file = $this->tool_guard_set_file();
		if(is_null($guard_file)) return false;
		$this->tool_guard_update($guard_file, ['damaged' => false, 'unknown' => true, 'missing' => true]);
		$this->ave->open_logs(true);
		$this->ave->pause(" Operation done, press any key to back to menu");
		return false;
	}

	public function tool_guard_set_file() : ?string {
		set_guard:
		$line = $this->ave->get_input(" Guard (.ave-guard): ");
		if($line == '#') return null;
		$folders = $this->ave->get_input_folders($line);
		if(!isset($folders[0])) goto set_guard;
		$guard_file = $folders[0];

		if(!file_exists($guard_file)){
			$this->ave->echo(" Guard file not exists");
			goto set_guard;
		}

		$ini = new IniFile($guard_file, true, true);
		if(is_null($ini->get('keys'))){
			$this->ave->echo(" File don't contain one of required information (Not a valid guard file ?)");
			goto set_guard;
		}

		return $guard_file;
	}

	public function tool_guard_update(string $guard_file, array $params) : void {
		$ini = new IniFile($guard_file, true, true);
		$input = pathinfo($guard_file, PATHINFO_DIRNAME);
		$cwd = getcwd();
		chdir($input);
		$this->ave->echo(" Validate files from $guard_file");
		$guard = new GuardDriver($guard_file, $ini->get('folders_to_scan'), $ini->get('files_to_scan'));
		$validation = $guard->validate($params);

		$guard->load($ini);

		foreach($validation as $error){
			$file = $error['file'];
			switch($error['type']){
				case 'unknown': {
					$this->ave->write_log("ADD FILE \"$file\"");
					$guard->scan_file($file);
					break;
				}
				case 'damaged': {
					$this->ave->write_log("UPDATE FILE \"$file\"");
					$guard->scan_file($file, true);
					break;
				}
				case 'missing': {
					$this->ave->write_log("REMOVE FILE \"$file\"");
					$key = strtoupper(hash('md5', str_replace(["\\", "/"], ":", pathinfo($file, PATHINFO_DIRNAME))));
					if(isset($guard->data[$key][pathinfo($file, PATHINFO_BASENAME)])) unset($guard->data[$key][pathinfo($file, PATHINFO_BASENAME)]);
					if(empty($guard->data[$key])){
						if(isset($guard->data[$key])) unset($guard->data[$key]);
					}
					if(isset($guard->keys[$key])) unset($guard->keys[$key]);
					$key = array_search($file, $guard->file_list);
					if($key !== false){
						if(isset($guard->file_list[$key])) unset($guard->file_list[$key]);
					}
					break;
				}
			}
		}

		$ini->set_all($guard->get(), true);

		chdir($cwd);
	}

}

?>

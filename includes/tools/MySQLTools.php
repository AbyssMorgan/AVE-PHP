<?php

declare(strict_types=1);

namespace App\Tools;

use AVE;
use PDO;
use PDOException;
use App\Services\IniFile;
use App\Services\DataBaseBackup;

class MySQLTools {

	private string $name = "MySQLTools";

	private array $params = [];
	private string $action;
	private string $path;
	private AVE $ave;

	public function __construct(AVE $ave){
		$this->ave = $ave;
		$this->ave->set_tool($this->name);
		$this->path = $this->ave->path.DIRECTORY_SEPARATOR.'config'.DIRECTORY_SEPARATOR.'mysql';
	}

	public function help() : void {
		$this->ave->print_help([
			' Actions:',
			' 0 - Configure connection',
			' 0 - Remove connection',
			' 2 - Open config folder',
			' 3 - Show connections',
			' 4 - Make backup',
		]);
	}

	public function action(string $action) : bool {
		$this->params = [];
		$this->action = $action;
		switch($this->action){
			case '0': return $this->ToolConfigureConnection();
			case '1': return $this->ToolRemoveConnection();
			case '2': return $this->ToolOpenConfigFolder();
			case '3': return $this->ToolShowConnections();
			case '4': return $this->ToolMakeBackup();
		}
		return false;
	}

	public function getConfigPath(string $label) : string {
		return $this->path.DIRECTORY_SEPARATOR."$label.ini";
	}

	public function getConfig(string $label) : IniFile {
		return new IniFile($this->getConfigPath($label), true);
	}

	public function ToolConfigureConnection() : bool {
		$this->ave->clear();
		$this->ave->set_subtool("ConfigureConnection");

		$this->ave->print_help([
			' Allowed characters: A-Z a-z 0-9 _ -',
			' Label length 3 - 20',
		]);

		set_label:
		echo " Label: ";
		$label = $this->ave->get_input();
		if($label == '#') return false;
		if(!preg_match('/(?=[a-zA-Z0-9_\-]{3,20}$)/i', $label)) goto set_label;

		if(file_exists($this->getConfigPath($label))){
			echo " Label \"$label\" already in use.\r\n";
			echo " Overwrite (Y/N): ";
			$line = $this->ave->get_input();
			if(strtoupper($line[0] ?? 'N') == 'N') goto set_label;
		}

		$this->ave->clear();
		$this->ave->print_help([
		 	" Setup label: \"$label\"",
			" Default port is: 3306",
		]);

		set_output:
		echo " Output: ";
		$line = $this->ave->get_input();
		if($line == '#') return false;
		$folders = $this->ave->get_folders($line);
		if(!isset($folders[0])) goto set_output;
		$output = $folders[0];

		if((file_exists($output) && !is_dir($output)) || !$this->ave->mkdir($output)){
			echo " Invalid output folder\r\n";
			goto set_output;
		}

		set_db_connection:
		echo " DB Host: ";
		$db['host'] = $this->ave->get_input();

		echo " DB Port: ";
		$db['port'] = $this->ave->get_input();

		echo " DB User: ";
		$db['user'] = $this->ave->get_input();

		echo " DB Pass: ";
		$db['password'] = $this->ave->get_input_no_trim();

		echo " DB Name: ";
		$db['name'] = $this->ave->get_input();

		$options = [
			PDO::ATTR_EMULATE_PREPARES => true,
			PDO::MYSQL_ATTR_INIT_COMMAND => 'SET SESSION SQL_BIG_SELECTS=1;',
			PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
		];
		try {
			$conn = new PDO("mysql:dbname=".$db['name'].";host=".$db['host'].";port=".$db['port'], $db['user'], $db['password'], $options);
		}
		catch(PDOException $e){
			echo " Failed to connect:\r\n";
			echo " ".$e->getMessage()."\r\n\r\n";
			goto set_db_connection;
		}
		$conn = null;

		$this->ave->clear();
		$this->ave->print_help([
			" Connection test completed successfully.",
			" Set additional config for label: \"$label\"",
		]);

		set_backup_structure:
		echo " Backup structure (Y/N): ";
		$backup['structure'] = strtoupper($this->ave->get_input());
		if(!in_array($backup['structure'][0] ?? '?', ['Y', 'N'])) goto set_backup_structure;

		set_backup_data:
		echo " Backup data (Y/N): ";
		$backup['data'] = strtoupper($this->ave->get_input());
		if(!in_array($backup['data'][0] ?? '?', ['Y', 'N'])) goto set_backup_data;

		set_max_allowed_packet:
		echo " Put SET max_allowed_packet before insert query (Y/N): ";
		$backup['max_allowed_packet'] = strtoupper($this->ave->get_input());
		if(!in_array($backup['max_allowed_packet'][0] ?? '?', ['Y', 'N'])) goto set_max_allowed_packet;

		$ini = $this->getConfig($label);
		$ini->update([
			'DB_HOST' => $db['host'],
			'DB_USER' => $db['user'],
			'DB_PASSWORD' => $db['password'],
			'DB_NAME' => $db['name'],
			'DB_PORT' => intval($db['port']),
			'FOLDER_DATE_FORMAT' => "Y-m-d_His",
			'BACKUP_QUERY_LIMIT' => 50000,
			'BACKUP_INSERT_LIMIT' => 100,
			'SET_MAX_ALLOWED_PACKET' => $backup['max_allowed_packet'] == 'Y',
			'BACKUP_TYPE_STRUCTURE' => $backup['structure'][0] == 'Y',
			'BACKUP_TYPE_DATA' => $backup['data'][0] == 'Y',
			'BACKUP_PATH' => $output,
		], true);

		$this->write_log("Setup connection for \"$label\"");

		$this->ave->clear();
		$this->ave->pause(" Setup connection for \"$label\" done, press enter to back to menu");

		return false;
	}

	public function ToolRemoveConnection() : bool {
		$this->ave->clear();
		$this->ave->set_subtool("RemoveConnection");

		set_label:
		echo " Label: ";
		$label = $this->ave->get_input();
		if($label == '#') return false;
		if(!preg_match('/(?=[a-zA-Z0-9_\-]{3,20}$)/i', $label)) goto set_label;

		$path = $this->getConfigPath($label);
		if(!file_exists($path)){
			echo " Label \"$label\" not exists.\r\n";
			goto set_label;
		}

		$this->unlink($this->getConfigPath($label));

		return false;
	}

	public function ToolOpenConfigFolder() : bool {
		$this->ave->clear();
		$this->ave->set_subtool("OpenConfigFolder");
		$this->ave->open_file($this->path);
		return false;
	}

	public function ToolShowConnections() : bool {
		$this->ave->clear();
		$this->ave->set_subtool("ShowConnections");

		echo " Connections:\r\n";
		$cnt = 0;
		$files = $this->ave->getFiles($this->path, ['ini']);
		foreach($files as $file){
			$ini = new IniFile($file);
			if($ini->isValid() && $ini->isSet('DB_HOST')){
				$label = pathinfo($file, PATHINFO_FILENAME);
				echo " $label".str_repeat(" ",20-strlen($label))," ".$ini->get('DB_HOST').':'.$ini->get('DB_PORT').'@'.$ini->get('DB_USER')."\r\n";
				$cnt++;
			}
		}

		if($cnt == 0){
			echo " No connections found\r\n";
		}

		$this->ave->pause("\r\n Press enter to back to menu");
		return false;
	}

	public function ToolMakeBackup() : bool {
		$this->ave->clear();
		$this->ave->set_subtool("MakeBackup");

		set_label:
		echo " Label: ";
		$label = $this->ave->get_input();
		if($label == '#') return false;
		if(!preg_match('/(?=[a-zA-Z0-9_\-]{3,20}$)/i', $label)) goto set_label;

		if(!file_exists($this->getConfigPath($label))){
			echo " Label \"$label\" not exists.\r\n";
			goto set_label;
		}

		$ini = $this->getConfig($label);

		$this->ave->write_log("Initialize backup for \"$label\"");
		echo " Initialize backup service\r\n";
		$path = $ini->get('BACKUP_PATH').DIRECTORY_SEPARATOR.$label;
		$backup = new DataBaseBackup($path, $ini->get('BACKUP_QUERY_LIMIT'), $ini->get('BACKUP_INSERT_LIMIT'), $ini->get('SET_MAX_ALLOWED_PACKET'), $ini->get('FOLDER_DATE_FORMAT'));

		echo " Connecting to: ".$ini->get('DB_HOST').':'.$ini->get('DB_PORT').'@'.$ini->get('DB_USER')."\r\n";
		if(!$backup->connect($ini->get('DB_HOST'), $ini->get('DB_USER'), $ini->get('DB_PASSWORD'), $ini->get('DB_NAME'), $ini->get('DB_PORT'))) goto set_label;

		echo " Create backup\r\n\r\n";
		$tables = $backup->getTables();
		$progress = 0;
		$total = count($tables);
		$this->ave->set_progress_ex('Tables', $progress, $total);
		foreach($tables as $table){
			$progress++;
			$this->ave->write_log("Create backup for table $table");
			$backup->makeForTable($table, $ini->get('BACKUP_TYPE_STRUCTURE'), $ini->get('BACKUP_TYPE_DATA'));
			echo "\n";
			$this->ave->set_progress_ex('Tables', $progress, $total);
		}
		echo "\n";
		$this->ave->write_log("Finish backup for \"$label\"");

		$this->ave->open_logs(true);
		$this->ave->open_file($path);
		$this->ave->pause(" Backup for \"$label\" done, press enter to back to menu");
		return false;
	}

}

?>

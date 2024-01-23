<?php
error_reporting(-1);
ini_set('display_errors', 1);

define("ROOT_DIR", __DIR__ . '/art-talant.org'); // начальный путь сканирования

/*Конфигурация базы данных*/
define("DB_NAME", 'art-talant');
define("USER", 'art-talant');
define("PASSWORD", '6G1v0Z5w');
define("HOST", 'localhost');

define("EXCLUDED_DIRS", 'components/com_mtree/attachments,tmp/works,downlads,downloads,logs,images/contacts,media/thumb/imagecache,resized/mtree/'); // исключения, список путей к деректориям относительно этого файла, указанных через запятую
define("DEPTH_LEVEL", 0); // (int) глубина сканирования, 0 - без ограничений
define("FILES_EXTENSION", 'php,js,html'); // расширения файлов, через запятую

define("DIRS_TABLE_NAME", 'scanner_dirs'); //название таблицы с директориями
define("FILES_TABLE_NAME", 'scanner_files'); //название таблицы с файлами

define("EMAIL", 'ya@palpalych.ru'); // Email адрес на которой отправлять результат, если не хотите уведомлений на почт, оставьте пустым
/*Конфигурация SMTP*/
define("SMTP_USERNAME", '');  //Смените на имя своего почтового ящика.
define("SMTP_PORT", 25); // Порт работы. Не меняйте, если не уверены.
define("SMTP_HOST", '');  //сервер для отправки почты
define("SMTP_PASSWORD", '');  //Измените пароль
define("SMTP_DEBUG", true);  //Если Вы хотите видеть сообщения ошибок, укажите true вместо false
define("SMTP_CHARSET", 'utf-8');  //кодировка сообщений. (windows-1251 или UTF-8, итд)
define("SMTP_FROM", 'Сканер файлов');


/*дальше ничего не трогать*/

class Scanner
{ 

	protected $_pdo = null;

	protected $_filename;

	protected $_dirs = array();

	protected $_files = array();

	protected $_files_extension = array();

	public $excluded_dirs = array();

	public $new_files = array();

	public $remove_files = array();

	public $files_chang = array();

	protected $_level = 0;

	protected $_parent_id = 0;

	public function __construct()
	{
		$this->_filename = basename(__FILE__);

		try {
			$this->_pdo = new PDO(
					'mysql:dbname=' . DB_NAME . ';host=' . HOST,
					USER,
					PASSWORD,
					array(PDO::ATTR_PERSISTENT => true)
			);
		} catch (PDOException $e) {
			die($e->getMessage());
		}

		$excluded_dirs = explode(',', EXCLUDED_DIRS);
		if (count($excluded_dirs)) {
			foreach ($excluded_dirs as $excluded_dir) {
				if (!empty($excluded_dir))
					$this->excluded_dirs[] = ROOT_DIR . '/' . $excluded_dir;
			}
		}

		$files_extension = explode(',', FILES_EXTENSION);
		if (count($files_extension)) {
			foreach ($files_extension as $file_extension) {
				if (!empty($file_extension))
					$this->_files_extension[] = $file_extension;
			}
		}


		$this->_checkTables();


		$this->_scanDir(ROOT_DIR);

	}

	protected function _checkTables()
	{

		$stmt = $this->_pdo->query("SHOW TABLES LIKE '" . DIRS_TABLE_NAME . "'");

		if ($stmt->rowCount() == 0) {

			$result = $this->_pdo->exec("

                CREATE TABLE `" . DIRS_TABLE_NAME . "` (
                  `id` int(11) NOT NULL AUTO_INCREMENT,
                  `parent_id` int(11) NOT NULL DEFAULT '0',
                  `path` varchar(1000) NOT NULL,
                  PRIMARY KEY (`id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8;

            ");

			if ($result === false) throw new Exception($this->_pdo->errorInfo()[2]);

		}

		$stmt = $this->_pdo->query("SHOW TABLES LIKE '" . FILES_TABLE_NAME . "'");

		if ($stmt->rowCount() == 0) {
			$result = $this->_pdo->exec(
					"

                CREATE TABLE `" . FILES_TABLE_NAME . "` (
                  `id` int(11) NOT NULL AUTO_INCREMENT,
                  `dir_id` int(11) NOT NULL,
                  `name` varchar(255) NOT NULL,
                  `size` int(11) NOT NULL,
                  PRIMARY KEY (`id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8;

            "
			);

			if ($result === false) throw new Exception($this->_pdo->errorInfo()[2]);
		}
	}

	protected function _scanDir($path = __DIR__)
	{

		$dir_id = 0;

		$dir = $this->getDir($path); // get dir data

		$dirs = array();
		$files = array();

		$scanned_directory = array_diff(scandir($path), array('..', '.', $this->_filename));

		foreach ($scanned_directory as $value) {
			if (is_dir($path . '/' . $value)) {
				$dirs[] = $path . '/' . $value;
			} else {
				if (empty($this->_files_extension)) {
					$files[] = $value;
				} else {
					if (!empty($value) && in_array(pathinfo($value, PATHINFO_EXTENSION), $this->_files_extension)) {
						$files[] = $value;
					}
				}
			}
		}

		$count_files = count($files); // now count files

		$count_dirs = count($dirs); // now count children dirs

		$sizes = array();

		if ($count_files) {
			$sizes = $this->getFilesSize($files, $path); // get files size array
		}


		if (!$dir) {
			$result = $this->_pdo->exec('INSERT INTO ' . DIRS_TABLE_NAME . '(parent_id, path) VALUES (' . $this->_parent_id . ', ' . $this->_pdo->quote($path) . ')'); // add new dir info in db
			if ($result === false) throw new Exception($this->_pdo->errorInfo()[2]);
			$dir_id = $this->_pdo->lastInsertId();
			if ($this->_level > 0) {
				$this->new_files[] = $path;
			}
			if ($count_files) {
				$this->_insertFiles($dir_id, $path, $sizes); // add new files in db
			}
		} else {

			$dir_id = $dir['id'];

			$old_dirs = $this->getDirs($dir['id']); // get children dirs
			$odl_dirs_path = $this->arrayValuesByKey($old_dirs, 'path'); // get old children dirs paths
			$new_dirs = array_diff($dirs, $odl_dirs_path); // check new dirs
			$del_dirs = array_diff($odl_dirs_path, $dirs); //check remove dirs

			if (count($new_dirs)) {
				$values = '';
				$i = 0;
				foreach ($new_dirs as $dir) {
					$values .= ($i > 0 ? ', ' : '') . '(' . $dir_id . ', ' . $this->_pdo->quote($dir) . ')';
					$this->new_files[] = $dir;
					$i++;
				}
				$result = $this->_pdo->exec('INSERT INTO ' . DIRS_TABLE_NAME . ' (parent_id, path) VALUES ' . $values);
				if ($result === false) throw new Exception($this->_pdo->errorInfo()[2]);
			}

			if (count($del_dirs)) {
				$wheres = '';
				$i = 0;
				foreach ($del_dirs as $dir) {
					$wheres .= ($i > 0 ? ', ' : '') . $this->_pdo->quote($dir);
					$this->remove_files[] = $dir;
					$i++;
				}
				$result = $this->_pdo->exec('DELETE FROM  ' . DIRS_TABLE_NAME . ' WHERE path IN (' . $wheres . ')');
				if ($result === false) throw new Exception($this->_pdo->errorInfo()[2]);
			}

			$old_files = $this->getFiles($dir_id); // get files in this dir
			$odl_files_path = $this->arrayValuesByKey($old_files, 'name'); //get old files name
			$new_files = array_diff($files, $odl_files_path); // check new files
			$del_files = array_diff($odl_files_path, $files); // check del files
			$rest_files = $odl_files_path;

			if (count($new_files)) {
				$insert_files = array();
				foreach ($new_files as $file_name) {
					$insert_files[$file_name] = $sizes[$file_name];
				}
				$this->_insertFiles($dir_id, $path, $insert_files);
				$rest_files = array_diff($rest_files, $new_files);
			}

			if (count($del_files)) {
				$wheres = '';
				$i = 0;
				foreach ($del_files as $file) {
					$wheres .= ($i > 0 ? ', ' : '') . $this->_pdo->quote($file);
					$this->remove_files[] = $path . '/' . $file;
					$i++;
				}
				$result = $this->_pdo->exec('DELETE FROM  `' . FILES_TABLE_NAME . '` WHERE `dir_id` = ' . $dir_id . ' AND `name` IN (' . $wheres . ')');
				if ($result === false) throw new Exception($this->_pdo->errorInfo()[2]);
				$rest_files = array_diff($rest_files, $del_files);
			}

			foreach ($old_files as $file) {
				if (in_array($file['name'], $rest_files) && $file['size'] != $sizes[$file['name']]) {
					$result = $this->_pdo->exec(
							'UPDATE ' . FILES_TABLE_NAME . ' SET size = ' . $sizes[$file['name']] . ' WHERE id = ' . $file['id']
					);
					if ($result === false) throw new Exception($this->_pdo->errorInfo()[2]);
					$this->files_chang[] = $path . '/' . $file['name'];
				}
			}

		}

		if ($count_dirs) {
			$this->_level++;
			if ($dir_id > 0 && (DEPTH_LEVEL == 0 || $this->_level <= DEPTH_LEVEL)) {
				foreach ($dirs as $dir) {
					if (!in_array($dir, $this->excluded_dirs)) {
						$this->_parent_id = $dir_id;
						$this->_scanDir($dir);
					}
				}
			}
		}

	}


	public function getFilesSize($files, $path)
	{
		$sizes = array();
		foreach ($files as $file) {
			$sizes[$file] = filesize($path . '/' . $file);
		}
		return $sizes;
	}


	protected function _insertFiles($dir_id, $path, $files)
	{
		$values = '';
		$i = 0;
		foreach ($files as $key => $value) {
			$values .= ($i > 0 ? ',' : '') . ' (' . $dir_id . ', ' . $this->_pdo->quote($key) . ', ' . $value . ')';
			$i++;
			if ($this->_level > 0) {
				$this->new_files[] = $path . '/' . $key;
			}
		}
		$result = $this->_pdo->exec(
				'INSERT INTO ' . FILES_TABLE_NAME . ' (dir_id, name, size) VALUES ' . $values
		);
		if ($result === false) throw new Exception($this->_pdo->errorInfo()[2]);
	}

	public function getDir($path)
	{
		if (!isset($this->_dirs[$path])) {
			$stmt = $this->_pdo->query('SELECT * FROM ' . DIRS_TABLE_NAME . ' WHERE path = ' . $this->_pdo->quote($path));
			if ($stmt === false) throw new Exception($this->_pdo->errorInfo()[2]);
			$row = $stmt->fetch(PDO::FETCH_ASSOC);
			if (empty($row)) {
				return false;
			}
			$this->_dirs[$path] = $row;
		}
		return $this->_dirs[$path];
	}


	public function getFiles($dir_id)
	{
		if (!isset($this->_files[$dir_id])) {
			$stmt = $this->_pdo->query('SELECT * FROM `' . FILES_TABLE_NAME . '` WHERE `dir_id` = ' . $dir_id);
			if ($stmt === false) throw new Exception($this->_pdo->errorInfo()[2]);
			$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
			if (empty($rows)) {
				return $rows;
			}
			$this->_files[$dir_id] = $rows;
		}
		return $this->_files[$dir_id];
	}

	public function getDirs($parent_id)
	{
		$stmt = $this->_pdo->query('SELECT * FROM `' . DIRS_TABLE_NAME . '` WHERE `parent_id` = ' . $parent_id);
		if ($stmt === false) throw new Exception($this->_pdo->errorInfo()[2]);
		return $stmt->fetchAll(PDO::FETCH_ASSOC);
	}

	public function arrayValuesByKey($array, $key)
	{
		$new_arr = array();
		foreach ($array as $val) {
			if (!empty($val[$key])) {
				$new_arr[] = $val[$key];
			}
		}
		return $new_arr;
	}

	public static function sendMail($mail_to, $subject, $message, $reply = null, $headers = '')
	{


		$SEND = "Date: " . date("D, d M Y H:i:s") . " UT\r\n";
		$SEND .= 'Subject: =?' . SMTP_CHARSET . '?B?' . base64_encode($subject) . "=?=\r\n";

		if ($headers) $SEND .= $headers . "\r\n\r\n";
		else {
			$SEND .= "Reply-To: " . SMTP_USERNAME . "\r\n";
			$SEND .= "MIME-Version: 1.0\r\n";
			$SEND .= "Content-Type: text/html; charset=\"" . SMTP_CHARSET . "\"\r\n";
			$SEND .= "Content-Transfer-Encoding: 8bit\r\n";
			$SEND .= "From: \"" . SMTP_FROM . "\" <" . SMTP_USERNAME . ">\r\n";
			if (is_array($mail_to)) {
				$SEND .= "To: <" . $mail_to[0] . ">\r\n";
			} else {
				$SEND .= "To: <" . $mail_to . ">\r\n";
			}

			if (isset($reply)) {
				$SEND .= "Reply-To: " . $reply . "\r\n";
			}

			$SEND .= "X-Priority: 3\r\n\r\n";
		}
		$SEND .= $message . "\r\n";

		$new_messages = array();

		$server_parse = function ($socket, $response, $line = __LINE__) {
			while (@substr($server_response, 3, 1) != ' ') {
				if (!($server_response = fgets($socket, 256))) {
					if (SMTP_DEBUG) throw new Exception($server_response);
					return false;
				}
			}
			if (!(substr($server_response, 0, 3) == $response)) {
				if (SMTP_DEBUG) throw new Exception($server_response);
				return false;
			}
			return true;
		};

		if (!$socket = fsockopen(SMTP_HOST, SMTP_PORT, $errno, $errstr, 30)) {
			if (SMTP_DEBUG) {
				throw new Exception('<strong>SMTP_DEBUG:</strong> ' . $errno . '<br>' . $errstr);
			}
		}

		if (!$server_parse($socket, "220")) {
			throw new Exception('<strong>SMTP_DEBUG:</strong> 220');
		}

		fputs($socket, "EHLO " . SMTP_HOST . "\r\n");
		if (!$server_parse($socket, "250")) {
			fclose($socket);
			if (SMTP_DEBUG) throw new Exception('<strong>SMTP_DEBUG:</strong> Не могу отправить EHLO!');
		}

		fputs($socket, "AUTH LOGIN\r\n");
		if (!$server_parse($socket, "334")) {
			fclose($socket);
			if (SMTP_DEBUG) throw new Exception('<strong>SMTP_DEBUG:</strong> Не могу найти ответ на запрос авторизаци.');
		}

		fputs($socket, base64_encode(SMTP_USERNAME) . "\r\n");
		if (!$server_parse($socket, "334")) {
			fclose($socket);
			if (SMTP_DEBUG) throw new Exception('<strong>SMTP_DEBUG:</strong> Логин авторизации не был принят сервером!');
		}

		fputs($socket, base64_encode(SMTP_PASSWORD) . "\r\n");
		if (!$server_parse($socket, "235")) {
			fclose($socket);
			if (SMTP_DEBUG) throw new Exception('<strong>SMTP_DEBUG:</strong> Пароль не был принят сервером как верный! Ошибка авторизации!');
		}

		fputs($socket, "MAIL FROM: <" . SMTP_USERNAME . ">\r\n");
		if (!$server_parse($socket, "250")) {
			fclose($socket);
			if (SMTP_DEBUG) {
				throw new Exception('<strong>SMTP_DEBUG:</strong> Не могу отправить комманду MAIL FROM');
			}
		}

		if (is_array($mail_to)) {

			$succes_count = 0;
			foreach ($mail_to as $mail) {

				fputs($socket, "RCPT TO: <" . $mail . ">\r\n");
				if (!$server_parse($socket, "250")) {
					if (SMTP_DEBUG) throw new Exception('<strong>SMTP_DEBUG:</strong> Не могу отправить комманду RCPT TO: ' . $mail);
				} else {
					$succes_count++;
				}

			}

			if ($succes_count === 0) {
				fclose($socket);
				return false;
			}

		} else {
			fputs($socket, "RCPT TO: <" . $mail_to . ">\r\n");
			if (!$server_parse($socket, "250")) {
				fclose($socket);
				if (SMTP_DEBUG) throw new Exception('<strong>SMTP_DEBUG:</strong> Не могу отправить комманду RCPT TO: ' . $mail_to);
			}

		}

		fputs($socket, "DATA\r\n");
		if (!$server_parse($socket, "354")) {
			fclose($socket);
			if (SMTP_DEBUG) throw new Exception('<strong>SMTP_DEBUG:</strong> Не могу отправить комманду DATA');

		}

		fputs($socket, $SEND . "\r\n.\r\n");
		if (!$server_parse($socket, "250")) {
			fclose($socket);
			if (SMTP_DEBUG) throw new Exception('<strong>SMTP_DEBUG:</strong> Не смог отправить тело письма. Письмо не было отправленно!');

		}

		fputs($socket, "QUIT\r\n");
		fclose($socket);
		return true;

	}

}


try {
	$scanner = new Scanner();
} catch (Exception $e) {
	die('Line ' . $e->getLine() . ': ' . $e->getMessage());
}

$body = '';
$body_html = '';
if (count($scanner->new_files)) {
	$body .= '<br><h3>Новые директории и фалы</h3><br>';
	$body .= '<ul style="list-style: none;">';
	foreach ($scanner->new_files as $file) {
		$body .= '<li style="color: green;">' . $file . '</li><br>';
	}
	$body .= '</ul><br>';
} else {
	$body_html .= '<br><strong>Нет новых файлов или директорий</strong><br>';
}

if (count($scanner->remove_files)) {
	$body .= '<br><h3>Удаленные фалы</h3><br>';
	$body .= '<ul style="list-style: none;">';
	foreach ($scanner->remove_files as $file) {
		$body .= '<li style="color: red;">' . $file . '</li><br>';
	}
	$body .= '</ul><br>';
} else {
	$body_html .= '<br><strong>Нет удаленных файлов или директорий</strong><br>';
}

if (count($scanner->files_chang)) {
	$body .= '<br><h3>Измененные фалы</h3><br>';
	$body .= '<ul style="list-style: none;">';
	foreach ($scanner->files_chang as $file) {
		$body .= '<li style="color: red;"><strong>' . $file . '</strong></li><br>';
	}
	$body .= '</ul><br>';
} else {
	$body_html .= '<br><strong>Нет изменненых файлов</strong><br>';
}

if (strlen(EMAIL) && !empty($body)) {
	if (strlen(SMTP_USERNAME) && strlen(SMTP_HOST) && strlen(SMTP_PASSWORD)) {
		try {
			$result = Scanner::sendMail(EMAIL, 'Изминения на сайте art-talant.org', $body);
		} catch (Exception $e) {
			print_r('Line ' . $e->getLine() . ': ' . $e->getMessage());
		}
	} else {
		$headers = "Content-type: text/html; charset=utf-8 \r\n";
		$reult = mail(EMAIL, 'Изминения на сайте art-talant.org', $body, $headers);
	}
}

?>
<!doctype html>
<html lang="en"> 
<head>
    <meta charset="UTF-8">
    <title>Scanner result</title>
</head>
<body>

<?= $body_html ?>
<?= $body ?>

</body>
</html>
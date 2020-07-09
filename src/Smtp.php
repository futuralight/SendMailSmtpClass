<?php

/**
 * SendMailSmtpClass
 * 
 * Класс для отправки писем через SMTP с авторизацией
 * Может работать через SSL протокол
 * Тестировалось на почтовых серверах yandex.ru, mail.ru и gmail.com, smtp.beget.com
 *
 * v 1.1
 * Добавлено:
 * - Приветствие сервера ehlo в приоритете, если не сервер не ответил, то шлется helo
 * - Работа с кодировками utf-8 и windows-1251
 * - Возможность отправки нескольким получателям
 * - Автоматическое формирование заголовков письма
 * - Возможность вложения файлов в письмо
 * 
 * @author Ipatov Evgeniy <admin@vk-book.ru>
 * @version 1.1
 */

namespace Futuralight\YandexMailSender;


class Smtp
{

	/**
	 * 
	 * @var string $smtp_username - логин
	 * @var string $smtp_password - пароль
	 * @var string $smtp_host - хост
	 * @var array $smtp_from - от кого
	 * @var integer $smtp_port - порт
	 * @var string $smtp_charset - кодировка
	 * @var string $boundary - разделитель содержимого письма(для отправки файлов)
	 * @var bool $addFile - содержит письмо файл или нет
	 * @var string $multipart - заголовки для письма с файлами
	 * @var array $arrayCC - массив получателей копии письма
	 * @var array $arrayBCC - массив получателей скрытой копии письма
	 *
	 */
	public $smtp_username;
	public $smtp_password;
	public $smtp_host;
	public $smtp_from;
	public $smtp_port;
	public $smtp_charset;
	public $boundary;
	public $addFile = false;
	public $multipart;
	public $arrayCC;
	public $arrayBCC;
	public $token;
	public $Subject;
	public $Body;
	private $addresses = [];
	private $from = [];
	private $messageContent;

	public function __construct($smtp_username, $token, $smtp_host = 'ssl://smtp.yandex.com', $smtp_port = 465, $smtp_charset = "utf-8")
	{
		$this->smtp_username = $smtp_username;
		$this->token = $token;
		$this->smtp_host = $smtp_host;
		$this->smtp_port = $smtp_port;
		$this->smtp_charset = $smtp_charset;

		// разделитель файлов
		$this->boundary = "--" . md5(uniqid(time()));
		$this->multipart = "";
	}

	/**
	 * Отправка письма
	 * 
	 * @param string $mailTo - получатель письма
	 * @param string $subject - тема письма
	 * @param string $message - тело письма
	 * @param array $smtp_from - отправитель. Массив с именем и e-mail
	 *
	 * @return bool|string В случаи отправки вернет true, иначе текст ошибки    
	 *
	 */

	public function addAddress($email, $name)
	{
		$this->addresses[] = [
			'address' => $email,
			'name' => $name
		];
	}

	public function setFrom($email, $name)
	{
		$this->from['address'] = $email;
		$this->from['name'] = $name;
	}


	private function getMailAdressesString()
	{
		$mailString = '';
		foreach ($this->addresses as $address) {
			$mailString .= $address['name'] . ' ' . $address['address'] . ', ';
		}
		return $mailString = trim($mailString, ', ');
	}


	function send()
	{
		// подготовка содержимого письма к отправке
		$mailString = $this->getMailAdressesString();
		$this->messageContent = $this->getContentMail($mailString);
		$context = stream_context_create();
		stream_context_set_option($context, 'ssl', 'passphrase', '');
		stream_context_set_option($context, 'ssl', 'allow_self_signed', true);
		stream_context_set_option($context, 'ssl', 'verify_peer', false);
		try {
			if (!$socket = @stream_socket_client($this->smtp_host . ':' . $this->smtp_port, $errorNumber, $errorDescription, 30, STREAM_CLIENT_CONNECT, $context)) {
				throw new \Exception($errorNumber . "." . $errorDescription);
			}
			if (!$this->_parseServer($socket, "220")) {
				throw new \Exception('Connection error');
			}

			$server_name = 'sender.example.com';
			fputs($socket, "EHLO $server_name\r\n");
			if (!$this->_parseServer($socket, "250")) {
				// если сервер не ответил на EHLO, то отправляем HELO
				fputs($socket, "HELO $server_name\r\n");
				if (!$this->_parseServer($socket, "250")) {
					fclose($socket);
					throw new \Exception('Error of command sending: HELO');
				}
			}

			$base64 = $this->encryptAuthString();
			fputs($socket, "AUTH XOAUTH2 {$base64}\r\n");
			if (!$this->_parseServer($socket, "235")) { //535 235
				fclose($socket);
				throw new \Exception('Autorization error');
			}

			// fputs($socket, base64_encode($this->smtp_username) . "\r\n");
			// if (!$this->_parseServer($socket, "334")) {
			// 	fclose($socket);
			// 	throw new Exception('Autorization error');
			// }

			// fputs($socket, base64_encode($this->smtp_password) . "\r\n");
			// if (!$this->_parseServer($socket, "235")) {
			// 	fclose($socket);
			// 	throw new Exception('Autorization error');
			// }

			fputs($socket, "MAIL FROM: <" . $this->smtp_username . ">\r\n");
			if (!$this->_parseServer($socket, "250")) {
				fclose($socket);
				throw new \Exception('Error of command sending: MAIL FROM');
			}


			// $mailTo = str_replace(" ", "", $mailTo);
			// $emails_to_array = explode(',', $mailTo);
			// foreach ($emails_to_array as $email) {
			// 	fputs($socket, "RCPT TO: <{$email}>\r\n");
			// 	if (!$this->_parseServer($socket, "250")) {
			// 		fclose($socket);
			// 		throw new Exception('Error of command sending: RCPT TO');
			// 	}
			// }


			foreach ($this->addresses as $address) {
				fputs($socket, "RCPT TO: <{$address['address']}>\r\n");
				if (!$this->_parseServer($socket, "250")) {
					fclose($socket);
					throw new \Exception('Error of command sending: RCPT TO');
				}
			}




			// если есть кому отправить копию
			if (!empty($this->arrayCC)) {
				foreach ($this->arrayCC as $emailCC) {
					fputs($socket, "RCPT TO: <{$emailCC}>\r\n");
					if (!$this->_parseServer($socket, "250")) {
						fclose($socket);
						throw new \Exception('Error of command sending: RCPT TO');
					}
				}
			}
			// если есть кому отправить скрытую копию
			if (!empty($this->arrayBCC)) {
				foreach ($this->arrayBCC as $emailBCC) {
					fputs($socket, "RCPT TO: <{$emailBCC}>\r\n");
					if (!$this->_parseServer($socket, "250")) {
						fclose($socket);
						throw new \Exception('Error of command sending: RCPT TO');
					}
				}
			}

			fputs($socket, "DATA\r\n");
			if (!$this->_parseServer($socket, "354")) {
				fclose($socket);
				throw new \Exception('Error of command sending: DATA');
			}

			fputs($socket, $this->messageContent . "\r\n.\r\n");
			if (!$this->_parseServer($socket, "250")) {
				fclose($socket);
				throw new \Exception("E-mail didn't sent");
			}

			fputs($socket, "QUIT\r\n");
			fclose($socket);
		} catch (\Exception $e) {
			return  $e->getMessage();
		}
		return true;
	}

	public function encryptAuthString()
	{
		return base64_encode("user={$this->smtp_username}\001auth=Bearer {$this->token}\001\001");
	}

	// добавление файла в письмо
	public function addFile($path)
	{
		if ($path[0] != '/') {
			$path = __DIR__ . '/' . $path;
		}
		$file = @fopen($path, "rb");
		if (!$file) {
			throw new \Exception("File `{$path}` didn't open");
		}
		$data = fread($file,  filesize($path));
		fclose($file);
		$filename = basename($path);
		$multipart  =  "\r\n--{$this->boundary}\r\n";
		$multipart .= "Content-Type: application/octet-stream; name=\"$filename\"\r\n";
		$multipart .= "Content-Transfer-Encoding: base64\r\n";
		$multipart .= "Content-Disposition: attachment; filename=\"$filename\"\r\n";
		$multipart .= "\r\n";
		$multipart .= chunk_split(base64_encode($data));

		$this->multipart .= $multipart;
		$this->addFile = true;
	}

	public function addFileBase64($base64, $filename, $mime)
	{
		$multipart  =  "\r\n--{$this->boundary}\r\n";
		$multipart .= "Content-Type: application/octet-stream; name=\"$filename\"\r\n";
		$multipart .= "Content-Transfer-Encoding: base64\r\n";
		$multipart .= "Content-Disposition: attachment; filename=\"$filename\"\r\n";
		$multipart .= "\r\n";
		$multipart .= chunk_split($base64);

		$this->multipart .= $multipart;
		$this->addFile = true;
	}

	// парсинг ответа сервера
	private function _parseServer($socket, $response)
	{
		$responseServer = $response;
		while (@substr($responseServer, 3, 1) != ' ') {
			if (!($responseServer = fgets($socket, 256))) {
				return false;
			}
		}
		if (!(substr($responseServer, 0, 3) == $response)) {
			return false;
		}
		return true;
	}

	// подготовка содержимого письма
	private function getContentMail($mailTo)
	{
		// если кодировка windows-1251, то перекодируем тему
		if (strtolower($this->smtp_charset) == "windows-1251") {
			$this->Subject = iconv('utf-8', 'windows-1251', $this->Subject);
		}
		$contentMail = "Date: " . date("D, d M Y H:i:s") . " UT\r\n";
		$contentMail .= 'Subject: =?' . $this->smtp_charset . '?B?'  . base64_encode($this->Subject) . "=?=\r\n";

		// заголовок письма
		$headers = "MIME-Version: 1.0\r\n";
		// кодировка письма
		if ($this->addFile) {
			// если есть файлы
			$headers .= "Content-Type: multipart/mixed; boundary=\"{$this->boundary}\"\r\n";
		} else {
			$headers .= "Content-type: text/html; charset={$this->smtp_charset}\r\n";
		}
		if (isset($this->from['name']) && $this->from['address']) {
			$headers .= "From: {$this->from['name']} <{$this->from['address']}>\r\n"; // от кого письмо
		} else {
			$headers .= "From:<{$this->smtp_username}>\r\n"; // от кого письмо
		}
		$headers .= "To: " . $mailTo . "\r\n"; // кому

		// если есть кому отправить копию
		if (!empty($this->arrayCC)) {
			foreach ($this->arrayCC as $emailCC) {
				$headers .= "Cc: " . $emailCC . "\r\n"; // кому копию
			}
		}

		// если есть кому отправить копию
		if (!empty($this->arrayBCC)) {
			foreach ($this->arrayBCC as $emailBCC) {
				$headers .= "Bcc: " . $emailBCC . "\r\n"; // кому копию
			}
		}

		$contentMail .= $headers . "\r\n";

		if ($this->addFile) {
			// если есть файлы
			$multipart  = "--{$this->boundary}\r\n";
			$multipart .= "Content-Type: text/html; charset=utf-8\r\n";
			$multipart .= "Content-Transfer-Encoding: base64\r\n";
			$multipart .= "\r\n";
			$multipart .= chunk_split(base64_encode($this->Body));

			// файлы
			$multipart .= $this->multipart;
			$multipart .= "\r\n--{$this->boundary}--\r\n";

			$contentMail .= $multipart;
		} else {
			$contentMail .= $this->Body . "\r\n";
		}

		// если кодировка windows-1251, то все письмо перекодируем
		if (strtolower($this->smtp_charset) == "windows-1251") {
			$contentMail = iconv('utf-8', 'windows-1251', $contentMail);
		}

		return $contentMail;
	}

	// добавлении получателя копии
	public function toCopy($email)
	{
		$this->arrayCC[] = $email;
	}

	// добавлении получателя скрытой копии
	public function toHideCopy($email)
	{
		$this->arrayBCC[] = $email;
	}

	public function copyToFolder($encoding = false)
	{
		$nameFolder = ($encoding) ? mb_convert_encoding(
			'Отправленные',
			"UTF7-IMAP",
			"UTF8"
		) : "Sent";
		$name = "{$this->smtp_username}";
		$imap = new Imap($this->smtp_username, $this->token);
		$imap->appendMessage($nameFolder, $this->messageContent, $name, $this->getMailAdressesString(), $this->Subject, '', '1.0', 'text/html', false);
	}
}

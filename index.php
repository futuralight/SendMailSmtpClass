<?php
	// пример использования SendMailSmtpClass.php

	require_once "SendMailSmtpClass.php"; // подключаем класс
	  
	// примеры подключения	
	$mailSMTP = new SendMailSmtpClass('m.yurin@ankas.ru', 'AgAEA7qjSAUEAAZdi2-ySeSWtEoJv-1t76nWiOY', 'ssl://smtp.yandex.ru', 465, "UTF-8");
	// $mailSMTP = new SendMailSmtpClass('zhenikipatov@yandex.ru', '***', 'ssl://smtp.yandex.ru', 465, "windows-1251");
	// $mailSMTP = new SendMailSmtpClass('monitor.test@mail.ru', '***', 'ssl://smtp.mail.ru', 465, "UTF-8");
	// $mailSMTP = new SendMailSmtpClass('red@mega-dev.ru', '***', 'ssl://smtp.beget.com', 465, "UTF-8");
	// $mailSMTP = new SendMailSmtpClass('red@mega-dev.ru', '***', 'smtp.beget.com', 2525, "windows-1251");
	// $mailSMTP = new SendMailSmtpClass('red@mega-dev.ru', '***', 'ssl://smtp.beget.com', 465, "utf-8");
	// $mailSMTP = new SendMailSmtpClass('red@mega-dev.ru', '***', 'smtp.beget.com', 2525, "utf-8");
	// $mailSMTP = new SendMailSmtpClass('логин', 'пароль', 'хост', 'порт', 'кодировка письма');
		
	// от кого
	$from = array(
		"Евгений", // Имя отправителя
		"m.yurin@ankas.ru" // почта отправителя
	);
	// кому отправка. Можно указывать несколько получателей через запятую
	$to = 'greedthegangboss@gmail.com';
	
	// добавляем файлы
	$mailSMTP->addFile("test.jpg");
	$mailSMTP->addFile("test2.jpg");
	$mailSMTP->addFile("test3.txt");

	// $mailSMTP->addFileBase64();
	// добавить получателя письма в копию
	// $mailSMTP->toCopy("test-copy@yandex.ru"); 
	// $mailSMTP->toCopy("test-copy@vk-book.ru");
	
	// добавить получателя письма в скрытую копию
	// $mailSMTP->toHideCopy("zhenikipatov@yandex.ru");
	$message = '<body style="margin: 0; padding: 0;">
	<table border="1" cellpadding="0" cellspacing="0" width="100%">
	 <tr>
	  <td>
	   Hello!
	  </td>
	 </tr>
	</table>
   </body>
	';
	// отправляем письмо
	$result =  $mailSMTP->send($to, 'Тема письма с копиями ', $message, $from); 
	// $result =  $mailSMTP->send('Кому письмо', 'Тема письма', 'Текст письма', 'Отправитель письма');
	
	if($result === true){
		echo "Done";
	}else{
		echo "Error: " . $result;
	}
	
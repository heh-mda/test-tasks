<?php

$message = 'Received: from DPS-FM2 (10.74.0.22) by RA-SSA03.esb.ru (103.148.42.223)
with
Microsoft SMTP Server id 12.3.842.3; Tue, 21 Oct 2014 05:22:48 +0400
MIME-Version: 1.0
From: <example@esr.ru>
To: <example@findsport.ru>
Date: Wed, 22 Oct 2014 01:16:19 +0400
Subject: =?utf-8?B?0KHQktCV0KDQmtCQINCh0LvRg9C20LHQsCDQodC/0L7RgNGC?=
=?utf-8?B?0LAg0JfQkCAyMDE0MTAyMSDQn9CeIDIwMTQxMDIx?=
Content-Type: multipart/mixed;
boundary="--boundary_33_04774084-8d36-40f6-8219-9adc84c8dad6"
Message-ID: <7dsa99119f8-0b67-4923-89c5-5ddsfeb9a34d @ RA-SSA03.esb.ru >
X-SA-Do-Not-Run: Yes
X-SA-Exim-Connect-IP: 10.48.4.200

----boundary_33_04774084-8d36-40f6-8219-9adc84c8dad6
Content-Type: text/plain; charset="us-ascii"
Content-Transfer-Encoding: quoted-printable


----boundary_33_04774084-8d36-40f6-8219-9adc84c8dad6
Content-Type: application/octet-stream; name=
"=?utf-8?B?0KHQu9GD0LbQsdCwX9Ch0L/
QvtGA0YLQsDIwMTQxMDIxXzIwMTQxMDIx?=
=?utf-8?B?LnR4dA==?="
Content-Transfer-Encoding: base64
Content-Disposition: attachment

MTg4Nzg1NDU0Njc4ODc1NDU2NAkyMS4xMC4yMDE0CTE3OjAwOjAwCTU0NTc1LTEyNDUJMTIwL
jAwCjQ4OTc1NDY1NDY3ODY0NTY0NjQJMjEuMTAuMjAxNAkxMjoxOTozNgk0NTQ1Ny02ODc3CT
QxNS4zMApUb3RhbDogMiA1MzUuMzA=
——boundary_33_04774084-8d36-40f6-8219-9adc84c8dad6--';

if (parseEmail($message)) {
    echo 'Успех!';
} else {
    echo 'Что-то пошло не так, ой :(';
}

/* Та самая функция. Парсит полученное сообщение и сохраняет данные из вложения в БД. */

function parseEmail(string $email) : bool
{
    $lines = explode(PHP_EOL, $email);

    $headersEnd = array_search('', $lines); //конец "основных" заголовков
    
    /* Проверяем, действительно ли письмо пришло с нужного адреса. Вообще, скорее всего
    здесь лучше было бы использовать регулярное выражение, поскольку вместе с адресом может быть указано и имя,
    но будем считать, что заголовок всегда будет таким и для простоты оставим обычный поиск. 
    Так же здесь есть проверка на то, что отправитель указан именно в заголовках, а не в теле. Не особо нужно
    в данном варианте, но для примера можно оставить, мало ли. */

    $validEmailHeader = array_search('From: <example@esr.ru>', $lines);

    if(!$validEmailHeader || !($validEmailHeader < $headersEnd)) {
        return false;
    }

    /* Ищем заголовок вложения, чтобы уже с него найти пустую строку. */

    $attachmentHeader = array_search('Content-Disposition: attachment', $lines);
    
    if(!$attachmentHeader) {
        return false;
    }
    
    $attachmentHeadersEnd = null; 

    for ($i=$attachmentHeader; $i < count($lines); $i++) { 
        if($lines[$i] == '') {
            $attachmentHeadersEnd = $i;
            break;
        }
    }

    /* За пустой строкой следует тело вложения, его и находим. 
    Boundary - конец вложения, поэтому на нем заканчиваем обход. */

    $attachmentBody = '';
   
    for ($i=$attachmentHeadersEnd; $i < count($lines); $i++) { 
        if($lines[$i]) {
            $attachmentBody .= $lines[$i] . ' ' . PHP_EOL;
        } elseif (strpos($lines[$i], 'boundary')) {
            break;
        }
    }

    if(!$attachmentBody) {
        return false;
    }

    /* Декодируем вложение в понятный формат. */
    $attachmentBody = base64_decode($attachmentBody);

    /* На Windows полученное тело вложения почему-то не разделяется с помощью PHP_EOL, 
    поэтому приходится менять символ новой строки на запятую. На всяких онлайн-интерпретаторах
    все работает и с EOL. */
    $attachmentBody = preg_replace("/((\r?\n)|(\r\n?))/", ',', $attachmentBody);
    
    /* Делаем каждую строку отдельным элементом, избавляясь от общей суммы - она нам не нужна.
    Возможно, тут стоит сделать более сложную проверку, а не просто удалять последний элемент, ведь общей суммы
    может и не быть. Но для данного исходника сгодится и это. */

    $data = explode(',', $attachmentBody);
    array_pop($data);

    /* Здесь мы превращаем каждое значение уже полученной строки в отдельный элемент массива.
    Возникает проблема в том, что время разделяется на два элемента, но потом все это форматируется
    через отдельную функцию, так как время все равно иного формата, чем требует БД. */

    foreach ($data as $key => $value) {
        $value = str_replace('	', ' ', $value);
        $value = explode(' ', $value);

        $data[$key] = $value;
    }

    /* Форматируем полученные данные. */

    $data = formatData($data);

    /* И сохраняем их в базу... */

    store($data);

    return true;
}

/* Соединение с БД. Не лучший вариант, но раз уж мы делаем все в одном скрипте... */

function setupPDO()
{
    $host = '127.0.0.1';
    $db   = 'work';
    $user = 'root';
    $pass = '';
    $charset = 'utf8';

    $dsn = "mysql:host=$host;dbname=$db;charset=$charset";
    $opt = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    
    $pdo = new PDO($dsn, $user, $pass, $opt);

    return $pdo;
}

/* Функция берет индексированный массив данных полученных из парсинга и делает его ассоциативным,
    заодно объединяя дату, форматируя ее в нужном формате. */

function formatData(array $data) : array
{
    $formatted = array();

    foreach($data as $key => $value) {
        $formatted[$key]['id'] = $value[0];
        
        $date = $value[1] . ' ' . $value[2];
        $formatted[$key]['time'] = date("Y-m-d H:i:s", strtotime($date));
        
        $formatted[$key]['fs-id'] = $value[3];
        $formatted[$key]['sum'] = $value[4];
    }

    return $formatted;
}

/* Получаем данные, сохраняем в БД. */

function store(array $data)
{
    $link = setupPDO();
    
    $statement = $link->prepare("INSERT INTO info (date, fs_id, sum)
    VALUES (:date, :fs_id, :sum)");

    foreach($data as $row)
        $statement->execute([
            "date" => $row['time'],
            "fs_id" => $row['fs-id'],
            "sum" => $row['sum'],
        ]);
}
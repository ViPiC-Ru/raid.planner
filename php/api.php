<?php # 0.1.0 api для бота в discord

include_once "../../libs/File-0.1.inc.php";									// 0.1.5 класс для многопоточной работы с файлом
include_once "../../libs/FileStorage-0.5.inc.php";							// 0.5.9 класс для работы с файловым реляционным хранилищем
include_once "../../libs/phpEasy-0.3.inc.php";								// 0.3.7 основная библиотека упрощённого взаимодействия

//////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

$app = array(// основной массив данных
    "val" => array(// переменные и константы
        "baseUrl" => "../base/%name%.db",									// шаблон url для базы данных
        "appToken" => "MY-APP-TOKEN",										// защитный ключ приложения
        "statusUnknown" => "Server unknown status",							// сообщение для неизвестного статуса
        "statusLang" => "en",												// язык для кодов расшифровок
        "format" => "json",													// формат вывода поумолчанию
        "eventTimeAdd" => 72*60*60,											// максимальное время для записи в событие
        "eventTimeDelete" => 12*60*60,										// максимальное время хранения записи события
        "eventTimeStep" => 1*60*60,											// шаг записи на событие (округление времени)
        "eventTimeLimit" => 2,												// лимит записей на время для пользователя
        "eventRaidLimit" => 1,												// лимит записей на время и рейд для пользователя
        "eventViewType" => 2,												// режим просмотра (0 - всё, 1 - гильдия, 2 - канал)
        "discordApiUrl" => "https://discordapp.com/api",					// базовый url для взаимодействия с Discord API
        "discordClientId" => "663665374532993044",							// идентификатор приложения в Discord
        "discordCreatePermission" => 32768,									// разрешения для создание первой записи в событие (прикреплять файлы)
        "discordBotPermission" => 76800,									// минимальные разрешения для работы бота
        "discordBotToken" => "MY-DISCORD-BOT-TOKEN"
    ),
    "base" => array(// базы данных
    ),
    "method" => array(// поддерживаемые методы
        "discord.update" => function($params, $options, $sign, &$status){// обновление информации о событиях
        //@param $params {array} - массив внешних не отфильтрованных значений
        //@param $options {array} - массив внутренних настроек
        //@param $sign {boolean|null} - успешность проверки подписи или null при её отсутствии
        //@param $status {number} - целое число статуса выполнения
        //@return {true|null} - true или пустое значение null при ошибке
            global $app; $result = null;
            
            $now = microtime(true);
            $notifications = array();// текст уведомлений в сообщениях
            $isEventsUpdate = false;// были ли обновлены данные в базе данных
            // получаем очищенные значения параметров
            $token = $app["fun"]["getClearParam"]($params, "token", "string");
            // работаем с данными
            if(!is_null($token) or get_val($options, "nocontrol", false)){// если указаны обязательные поля
                if(!empty($token) or get_val($options, "nocontrol", false)){// если обязательные поля успешно отфильтрованы
                    if($token == $app["val"]["appToken"] or get_val($options, "nocontrol", false)){// если прошли проверку
                        // загружаем необходимые базы занных
                        $events = $app["fun"]["storage"]("events");
                        if(!empty($events) and $events->lock(true) and $events->load(true)){// если удалось подключится к базе данных
                            $raids = $app["fun"]["storage"]("raids");
                            if(!empty($raids)){// если удалось подключится к базе данных
                                $types = $app["fun"]["storage"]("types");
                                if(!empty($types)){// если удалось подключится к базе данных
                                    $roles = $app["fun"]["storage"]("roles");
                                    if(!empty($roles)){// если удалось подключится к базе данных
                                        // очищаем устаревшие записи событий
                                        for($i = $events->length - 1; $i >= 0; $i--){
                                            $event = $events->get($events->key($i));
                                            if(// множественное условие
                                                $event["time"] < $now - $app["val"]["eventTimeDelete"]
                                                or $event["time"] > $now + $app["val"]["eventTimeAdd"]
                                            ){// если нужно удалить эту запись
                                                $events->set($events->key($i));
                                                $isEventsUpdate = true;
                                            };
                                        };
                                        // получаем список гильдий Discord где добавлен бот
                                        $header = array("authorization" => "Bot " . $app["val"]["discordBotToken"]);
                                        $url = $app["val"]["discordApiUrl"] . "/users/@me/guilds";
                                        $data = http("get", $url, null, null, $header, false);
                                        if(200 == $data["status"]){// если запрос выполнен успешно
                                            $data = json_decode($data["body"], true);
                                            if(isset($data) and !empty($data)){// если есть данные
                                                $guilds = $data;// получаем список гильдий
                                                // обрабатываем список гильдий Discord где добавлен бот
                                                for($i = 0, $iLen = count($guilds); $i < $iLen and empty($status); $i++){
                                                    $guild = $guilds[$i];// получаем очередну гильдию
                                                    // получаем список каналов гильдии Discord где добавлен бот
                                                    $url = $app["val"]["discordApiUrl"] . "/guilds/" . $guild["id"] . "/channels";
                                                    $data = http("get", $url, null, null, $header, false);
                                                    if(200 == $data["status"]){// если запрос выполнен успешно
                                                        $data = json_decode($data["body"], true);
                                                        if(isset($data) and !empty($data)){// если есть данные
                                                            $channels = $data;// получаем список каналов
                                                            // обрабатываем список каналов гильдии Discord где добавлен бот
                                                            for($j = 0, $jLen = count($channels); $j < $jLen and empty($status); $j++){
                                                                $channel = $channels[$j];// получаем очередной каналов
                                                                // обрабатываем перезаписи разрешений на канале для бота
                                                                if(0 == $channel["type"]){// если текстовый канал
                                                                    $overwrites = $channel["permission_overwrites"];
                                                                    $isBotAllow = false;// в канале есть нужные разрешения
                                                                    for($k = 0, $kLen = count($overwrites); $k < $kLen and !$isBotAllow; $k++){
                                                                        $overwrite = $overwrites[$k];// получаем очередную перезапись
                                                                        if($app["val"]["discordClientId"] == $overwrite["id"] and "member" == $overwrite["type"]){// если для бота
                                                                            $isBotAllow = ($overwrite["allow"] & $app["val"]["discordBotPermission"]) == $app["val"]["discordBotPermission"];
                                                                        };
                                                                    };
                                                                    // получаем список сообщений на канале
                                                                    if($isBotAllow){// если канал удовлетворяет требованиям
                                                                        if(!isset($notifications[$guild["id"]])) $notifications[$guild["id"]] = array();
                                                                        if(!isset($notifications[$guild["id"]][$channel["id"]])) $notifications[$guild["id"]][$channel["id"]] = array();
                                                                        $url = $app["val"]["discordApiUrl"] . "/channels/" . $channel["id"] . "/messages";
                                                                        $data = http("get", $url, null, null, $header, false);
                                                                        if(200 == $data["status"]){// если запрос выполнен успешно
                                                                            $data = json_decode($data["body"], true);
                                                                            if(isset($data) and !empty($data)){// если есть данные
                                                                                $messages = $data;// получаем список сообщений
                                                                                // обрабатываем список сообщений на канале
                                                                                for($k = count($messages) - 1; $k > - 1 and empty($status); $k--){
                                                                                    $message = $messages[$k];// получаем очередное сообщение
                                                                                    // обрабатываем сообщения с коммандами
                                                                                    if(empty($status) and !$message["pinned"] and $message["author"]["id"] != $app["val"]["discordClientId"]){// если это сообщение с коммандой
                                                                                        $offset = 1* date('Z');// смещение временной зоны
                                                                                        $list = explode(" ", $message["content"]);
                                                                                        $time = isset($list[2], $list[3]) ? strtotime($list[2] . " " . $list[3]) : (isset($list[2]) ? -1 : 0);
                                                                                        if(false === $time) $time = -1;// приводим к единобразному написанию
                                                                                        if($time > 0) $time = floor(($time - $offset) /$app["val"]["eventTimeStep"]) * $app["val"]["eventTimeStep"] + $offset;
                                                                                        $command = array(// переданная комманда
                                                                                            "action" => isset($list[0]) ? mb_strtolower($list[0]) : "",
                                                                                            "role" => isset($list[1]) ? mb_strtolower($list[1]) : "",
                                                                                            "raid" => isset($list[4]) ? mb_strtolower($list[4]) : "",
                                                                                            "option" => isset($list[5]) ? mb_strtolower($list[5]) : "",
                                                                                            "time" => $time
                                                                                        );
                                                                                        // обрабатываем переданную комманду
                                                                                        switch($command["action"]){// поддержмваемые комманды
                                                                                            case "записаться":// добавить запись
                                                                                            case "add":// добавить запись
                                                                                                $isActionAllow = true;// нужно добавить запись
                                                                                                // проверяем что указаны обязательные параметры
                                                                                                $isActionAllow = ($isActionAllow and !empty($command["role"]) and !empty($command["time"]) and !empty($command["raid"]));
                                                                                                // проверяем ограничения по времени записи
                                                                                                $isActionAllow = ($isActionAllow and $command["time"] >= $now and $command["time"] <= $now + $app["val"]["eventTimeAdd"]);
                                                                                                // проверяем корректность указания игровой роли
                                                                                                for($role = null, $l = 0, $lLen = $roles->length; $l < $lLen and $isActionAllow and empty($role); $l++){
                                                                                                    $key = $roles->key($l);// получаем ключевой идентификатор по индексу
                                                                                                    $item = $roles->get($key);// получаем элимент по идентификатору
                                                                                                    if($item["key"] == $command["role"] or $item["synonym"] == $command["role"]) $role = $item;
                                                                                                };
                                                                                                $isActionAllow = ($isActionAllow and !empty($role));
                                                                                                // проверяем корректность указания рейда
                                                                                                for($raid = null, $l = 0, $lLen = $raids->length; $l < $lLen and $isActionAllow and empty($raid); $l++){
                                                                                                    $key = $raids->key($l);// получаем ключевой идентификатор по индексу
                                                                                                    $item = $raids->get($key);// получаем элимент по идентификатору
                                                                                                    if(mb_strtolower($item["key"]) == $command["raid"]) $raid = $item;
                                                                                                };
                                                                                                $isActionAllow = ($isActionAllow and !empty($raid));
                                                                                                // проверяем ограничивающий фильтр в имени канала
                                                                                                $counts = array("channel" => 0, "raid" => 0);// счётчик совпадений ограничений
                                                                                                for($l = 0, $lLen = $types->length; $l < $lLen and $isActionAllow; $l++){
                                                                                                    $key = $types->key($l);// получаем ключевой идентификатор по индексу
                                                                                                    $item = $types->get($key);// получаем элимент по идентификатору
                                                                                                    if(false !== mb_stripos($channel["name"], $item["filter"])){// если есть совподение
                                                                                                        if($item["key"] == $raid["type"]) $counts["raid"]++;
                                                                                                        $counts["channel"]++;// увеличиваем счётчик совпадений
                                                                                                    };
                                                                                                };
                                                                                                $isActionAllow = ($isActionAllow and (empty($counts["channel"]) or !empty($counts["raid"])));
                                                                                                // считаем записи и проверяем лимиты
                                                                                                $counts = array("time" => 0, "raid" => 0, "item" => 0);// счётчик записи
                                                                                                for($l = 0, $lLen = $events->length; $l < $lLen and $isActionAllow; $l++){
                                                                                                    $id = $events->key($l);// получаем ключевой идентификатор по индексу
                                                                                                    $event = $events->get($id);// получаем элимент по идентификатору
                                                                                                    if(// множественное условие
                                                                                                        ($app["val"]["eventViewType"] >= 2 ? $event["channel"] == $channel["id"] : true)
                                                                                                        and ($app["val"]["eventViewType"] >= 1 ? $event["guild"] == $guild["id"] : true)
                                                                                                    ){// если нужно посчитать счётчик
                                                                                                        // в разрезе колличества записей
                                                                                                        if(// множественное условие
                                                                                                            $event["raid"] == $raid["key"]
                                                                                                            and $event["time"] == $command["time"]
                                                                                                        ){// если пользователь и время совпадает
                                                                                                            $counts["item"]++;// увеличиваем счётчик совпадений
                                                                                                        };
                                                                                                        // в разрезе пользователя
                                                                                                        if(// множественное условие
                                                                                                            $event["user"] == $message["author"]["id"]
                                                                                                            and $event["time"] == $command["time"]
                                                                                                        ){// если пользователь и время совпадает
                                                                                                            $counts["time"]++;// увеличиваем счётчик совпадений
                                                                                                            if($event["raid"] == $raid["key"]){// если рейд совпадает
                                                                                                                $counts["raid"]++;// увеличиваем счётчик совпадений
                                                                                                            };
                                                                                                        };
                                                                                                    };
                                                                                                };
                                                                                                $isActionAllow = ($isActionAllow and $counts["raid"] < $app["val"]["eventRaidLimit"]);
                                                                                                $isActionAllow = ($isActionAllow and $counts["time"] < $app["val"]["eventTimeLimit"]);
                                                                                                // проверяем права на создание первой записи
                                                                                                if($isActionAllow and empty($counts["item"])){// если это первая запись
                                                                                                    $permission = $app["val"]["discordCreatePermission"];// по умолчанию
                                                                                                    // проверяем перезапись разрешений для всех
                                                                                                    if(empty($status)){// если нет ошибок
                                                                                                        for($l = 0, $lLen = count($overwrites); $l < $lLen; $l++){
                                                                                                            $overwrite = $overwrites[$l];// получаем очередную перезапись
                                                                                                            if($guild["id"] == $overwrite["id"] and "role" == $overwrite["type"]){// если для всех
                                                                                                                $permission &= ~$overwrite["deny"];
                                                                                                                $permission |= $overwrite["allow"];
                                                                                                                break;
                                                                                                            };
                                                                                                        };
                                                                                                    };
                                                                                                    // проверяем перезапись разрешений для ролей пользователя
                                                                                                    if(empty($status)){// если нет ошибок
                                                                                                        $url = $app["val"]["discordApiUrl"] . "/guilds/" . $guild["id"] . "/members/" . $message["author"]["id"];
                                                                                                        $data = http("get", $url, null, null, $header, true);// делаем запрос с использованием кеша
                                                                                                        if(200 == $data["status"]){// если запрос выполнен успешно
                                                                                                            $data = json_decode($data["body"], true);
                                                                                                            if(isset($data) and !empty($data)){// если есть данные
                                                                                                                $member = $data;// получаем участника гильдии
                                                                                                                $permissions = array("allow" => 0, "deny" => 0);
                                                                                                                for($l = 0, $lLen = count($overwrites); $l < $lLen; $l++){
                                                                                                                    $overwrite = $overwrites[$l];// получаем очередную перезапись
                                                                                                                    for($m = 0, $mLen = count($member["roles"]); $m < $mLen; $m++){
                                                                                                                        $rid = $member["roles"][$m];// получаем очередной идентификатор роли
                                                                                                                        if($rid == $overwrite["id"] and "role" == $overwrite["type"]){// если для этой роли
                                                                                                                            $permissions["allow"] |= $overwrite["allow"];
                                                                                                                            $permissions["deny"] |= $overwrite["deny"];
                                                                                                                            break;
                                                                                                                        };
                                                                                                                    };
                                                                                                                };
                                                                                                                $permission &= ~$permissions["deny"];
                                                                                                                $permission |= $permissions["allow"];
                                                                                                            }else $status = 268;// не удалось получить корректный ответ от удаленного сервера
                                                                                                        }else $status = 268;// не удалось получить корректный ответ от удаленного сервера
                                                                                                    };
                                                                                                    // проверяем перезапись разрешений для пользователя
                                                                                                    if(empty($status)){// если нет ошибок
                                                                                                        for($l = 0, $lLen = count($overwrites); $l < $lLen; $l++){
                                                                                                            $overwrite = $overwrites[$l];// получаем очередную перезапись
                                                                                                            if($message["author"]["id"] == $overwrite["id"] and "member" == $overwrite["type"]){// если для всех
                                                                                                                $permission &= ~$overwrite["deny"];
                                                                                                                $permission |= $overwrite["allow"];
                                                                                                                break;
                                                                                                            };
                                                                                                        };
                                                                                                    };
                                                                                                    // проверяем результирующие разрешение
                                                                                                    if(empty($status)){// если нет ошибок
                                                                                                        $isActionAllow = ($permission & $app["val"]["discordCreatePermission"]) == $app["val"]["discordCreatePermission"];
                                                                                                    };
                                                                                                };
                                                                                                // добавляем запись в событие
                                                                                                if(empty($status) and $isActionAllow){// если нужно добавить запись
                                                                                                    $id = $events->length ? $events->key($events->length - 1) + 1 : 1;
                                                                                                    // формируем структуру записи
                                                                                                    $event = array(// новая запись
                                                                                                        "guild" => $guild["id"],
                                                                                                        "channel" => $channel["id"],
                                                                                                        "user" => $message["author"]["id"],
                                                                                                        "time" => $command["time"],
                                                                                                        "raid" => $raid["key"],
                                                                                                        "role" => $role["key"],
                                                                                                        "leader" => false
                                                                                                    );
                                                                                                    // обрабатываем дополнительные опции
                                                                                                    switch($command["option"]){// поддержмваемые опции
                                                                                                        case "лидер":// лидер
                                                                                                        case "leader":// лидер
                                                                                                            $event["leader"] = true;
                                                                                                            break;
                                                                                                    };
                                                                                                    // добавляем данные в базу данных
                                                                                                    if($events->set($id, null, $event)){// если данные успешно добавлены
                                                                                                        $isEventsUpdate = true;// были обновлены данные в базе данных
                                                                                                    }else $status = 269;// не удалось записать данные в базу данных
                                                                                                };
                                                                                                break;
                                                                                            case "отписаться":// удалить запись
                                                                                            case "remove":// удалить запись
                                                                                                $isActionAllow = true;// нужно удалить запись
                                                                                                // проверяем ограничения по времени записи
                                                                                                $isActionAllow = ($isActionAllow and ($command["time"] >= $now or empty($command["time"])));
                                                                                                // проверяем корректность указания игровой роли
                                                                                                for($role = null, $l = 0, $lLen = $roles->length; $l < $lLen and $isActionAllow and empty($role); $l++){
                                                                                                    $key = $roles->key($l);// получаем ключевой идентификатор по индексу
                                                                                                    $item = $roles->get($key);// получаем элимент по идентификатору
                                                                                                    if($item["key"] == $command["role"] or $item["synonym"] == $command["role"]) $role = $item;
                                                                                                };
                                                                                                $isActionAllow = ($isActionAllow and (empty($command["role"]) or !empty($role)));
                                                                                                // определяем рейд если она указан
                                                                                                for($raid = null, $l = 0, $lLen = $raids->length; $l < $lLen and $isActionAllow and empty($raid); $l++){
                                                                                                    $key = $raids->key($l);// получаем ключевой идентификатор по индексу
                                                                                                    $item = $raids->get($key);// получаем элимент по идентификатору
                                                                                                    if(mb_strtolower($item["key"]) == $command["raid"]) $raid = $item;
                                                                                                };
                                                                                                $isActionAllow = ($isActionAllow and (empty($command["raid"]) or !empty($raid)));
                                                                                                // пробигаемся по записям в событиях и удаляем необходимые
                                                                                                for($l = $events->length - 1; $l > - 1 and $isActionAllow and empty($status); $l--){
                                                                                                    $id = $events->key($l);// получаем ключевой идентификатор по индексу
                                                                                                    $event = $events->get($id);// получаем элимент по идентификатору
                                                                                                    if(// множественное условие
                                                                                                        ($app["val"]["eventViewType"] >= 2 ? $event["channel"] == $channel["id"] : true)
                                                                                                        and ($app["val"]["eventViewType"] >= 1 ? $event["guild"] == $guild["id"] : true)
                                                                                                        and $event["user"] == $message["author"]["id"]
                                                                                                        and (empty($command["time"]) or $event["time"] == $command["time"])
                                                                                                        and (empty($role) or $event["role"] == $role["key"])
                                                                                                        and (empty($raid) or $event["raid"] == $raid["key"])
                                                                                                        and ($event["time"] >= $now)
                                                                                                    ){// если нужно удалить запись из событий
                                                                                                        if($events->set($id)){// если данные успешно удалены
                                                                                                            $isEventsUpdate = true;// были обновлены данные в базе данных
                                                                                                        }else $status = 269;// не удалось записать данные в базу данных
                                                                                                    };
                                                                                                };
                                                                                                break;
                                                                                        };
                                                                                        // удаляем сообщение пользователя
                                                                                        if(empty($status)){// если нет ошибок
                                                                                            $url = $app["val"]["discordApiUrl"] . "/channels/" . $channel["id"] . "/messages/" . $message["id"];
                                                                                            $data = http("delete", $url, null, null, $header, false);
                                                                                            if(204 == $data["status"] or 404 == $data["status"]){// если сообщение удалено
                                                                                            }else $status = 268;// не удалось получить корректный ответ от удаленного сервера
                                                                                        };
                                                                                    };
                                                                                    // удаляем все сообщения нашего бота кроме первого сообщения
                                                                                    if(empty($status) and $message["author"]["id"] == $app["val"]["discordClientId"]){// если это сообщение нашего бота
                                                                                        if(count($notifications[$guild["id"]][$channel["id"]]) or $message["type"]){// если это не первое сообщение нашего бота
                                                                                            $url = $app["val"]["discordApiUrl"] . "/channels/" . $channel["id"] . "/messages/" . $message["id"];
                                                                                            $data = http("delete", $url, null, null, $header, false);
                                                                                            if(204 == $data["status"] or 404 == $data["status"]){// если сообщение удалено
                                                                                            }else $status = 268;// не удалось получить корректный ответ от удаленного сервера
                                                                                        }else $notifications[$guild["id"]][$channel["id"]][$message["id"]] = $message["content"];
                                                                                    };
                                                                                };
                                                                            };
                                                                        }else $status = 268;// не удалось получить корректный ответ от удаленного сервера
                                                                        if(empty($notifications[$guild["id"]][$channel["id"]])) $notifications[$guild["id"]][$channel["id"]][0] = "";
                                                                    };
                                                                };
                                                            };
                                                        };
                                                    }else $status = 268;// не удалось получить корректный ответ от удаленного сервера
                                                };
                                            };
                                        }else $status = 268;// не удалось получить корректный ответ от удаленного сервера
                                        // готовим уведомления для каждого канала в гильдии
                                        if(empty($status)){// если нет ошибок
                                            $header["content-type"] = "application/json;charset=utf-8";
                                            foreach($notifications as $gid => $item){// пробигаемся по гильдиям
                                                foreach($notifications[$gid] as $cid => $item){// пробигаемся по каналам
                                                    foreach($notifications[$gid][$cid] as $mid => $notification){// пробигаемся по сообщениям
                                                        // формируем список записей для отображения
                                                        $leaders = array();// лидеры для групп
                                                        $limits = array();// лимиты для рейдов
                                                        $counts = array();// счётчики для распеределения на группы
                                                        $items = array();// список записей событий
                                                        $index = 0;// индекс элимента в новом массиве
                                                        for($i = 0, $iLen = $events->length; $i < $iLen; $i++){
                                                            $id = $events->key($i);// получаем ключевой идентификатор по индексу
                                                            $item = $events->get($id);// получаем элимент по идентификатору
                                                            if(// множественное условие
                                                                ($app["val"]["eventViewType"] >= 2 ? $item["channel"] == $cid : true)
                                                                and ($app["val"]["eventViewType"] >= 1 ? $item["guild"] == $gid : true)
                                                            ){// если нужно включить запись в уведомление
                                                                $raid = $raids->get($item["raid"]);
                                                                // считаем без учётом группы
                                                                if(!isset($counts[$item["time"]])) $counts[$item["time"]] = array();
                                                                if(!isset($counts[$item["time"]][$item["raid"]])) $counts[$item["time"]][$item["raid"]] = array();
                                                                if(!isset($counts[$item["time"]][$item["raid"]][0])) $counts[$item["time"]][$item["raid"]][0] = array();
                                                                if(!isset($counts[$item["time"]][$item["raid"]][0][""])) $counts[$item["time"]][$item["raid"]][0][""] = 1;
                                                                else $counts[$item["time"]][$item["raid"]][0][""]++;
                                                                if(!isset($counts[$item["time"]][$item["raid"]][0][$item["role"]])) $counts[$item["time"]][$item["raid"]][0][$item["role"]] = 1;
                                                                else $counts[$item["time"]][$item["raid"]][0][$item["role"]]++;
                                                                // вычисляем лимит
                                                                if(!isset($limits[$item["raid"]])){// если нужно вычислить общий лимит
                                                                    $limits[$item["raid"]] = 0;// начальное значение лимита
                                                                    for($limit = 1, $j = 0, $jLen = $roles->length; $j < $jLen and $limit; $j++){
                                                                        $role = $roles->get($roles->key($j));// получаем очередную роль
                                                                        $limit = $raid[$role["key"]];// получаем значение лимита
                                                                        if($limit) $limits[$item["raid"]] += $limit;
                                                                        else $limits[$item["raid"]] = 0;																	
                                                                    };
                                                                };
                                                                // определяем группу
                                                                $limit = $raid[$item["role"]];
                                                                $count = $counts[$item["time"]][$item["raid"]][0][$item["role"]];
                                                                $group = $limit ? ceil($count / $limit) : 1;
                                                                // считаем с учётом группы
                                                                if(!isset($counts[$item["time"]][$item["raid"]][$group])) $counts[$item["time"]][$item["raid"]][$group] = array();
                                                                if(!isset($counts[$item["time"]][$item["raid"]][$group][""])) $counts[$item["time"]][$item["raid"]][$group][""] = 1;
                                                                else $counts[$item["time"]][$item["raid"]][$group][""]++;
                                                                if(!isset($counts[$item["time"]][$item["raid"]][$group][$item["role"]])) $counts[$item["time"]][$item["raid"]][$group][$item["role"]] = 1;
                                                                else $counts[$item["time"]][$item["raid"]][$group][$item["role"]]++;
                                                                // определяем лидера группы
                                                                if(!isset($leaders[$item["time"]])) $leaders[$item["time"]] = array();
                                                                if(!isset($leaders[$item["time"]][$item["raid"]])) $leaders[$item["time"]][$item["raid"]] = array();
                                                                if(!isset($leaders[$item["time"]][$item["raid"]][$group])) $leaders[$item["time"]][$item["raid"]][$group] = $index;
                                                                $leader = $leaders[$item["time"]][$item["raid"]][$group];
                                                                $limit = $limits[$item["raid"]];// получаем значение лимита
                                                                $count = $counts[$item["time"]][$item["raid"]][$group][""];
                                                                if($index != $leader){// если лидер не текущий элимент
                                                                    if(!$items[$leader]["leader"]){// если лидер выбран системой
                                                                        if($item["leader"]) $leader = $leaders[$item["time"]][$item["raid"]][$group] = $index;
                                                                        else if($count == $limit) $items[$leader]["leader"] = true;
                                                                    }else if($item["leader"]) $item["leader"] = false;
                                                                }else if($count == $limit) $item["leader"] = true;
                                                                // расширяем свойства элимента
                                                                $days = array("Воскресенье", "Понедельник", "Вторник", "Среда", "Четверг", "Пятница", "Суббота");
                                                                $item["title"] = date("d.m.Y", $item["time"]);
                                                                $item["day"] = $days[date("w", $item["time"])];
                                                                $item["group"] = $group;
                                                                // сохраняем элимент в массив
                                                                $items[$index] = $item;
                                                                $index++;
                                                            };
                                                        };
                                                        // сортируем список записей для отображения
                                                        usort($items, function($a, $b){// пользовательская сортировка
                                                            if($a["time"] == $b["time"]){// сравниваем время
                                                                if($a["raid"] == $b["raid"]){// сравниваем рейд
                                                                    if($a["group"] == $b["group"]){// сравниваем группы
                                                                        if($a["role"] == $b["role"]){// сравниваем игровые роли
                                                                            if($a["leader"] == $b["leader"]){// сравниваем лидеров
                                                                                if($a["id"] == $b["id"]){// сравниваем идентификаторы
                                                                                    $value = 0;// если два элимента идентичны
                                                                                }else $value = $a["id"] > $b["id"] ? 1 : -1;
                                                                            }else $value = $a["leader"] > $b["leader"] ? 1 : -1;
                                                                        }else $value = $a["role"] < $b["role"] ? 1 : -1;
                                                                    }else $value = $a["group"] > $b["group"] ? 1 : -1;
                                                                }else $value = $a["raid"] > $b["raid"] ? 1 : -1;
                                                            }else $value = $a["time"] > $b["time"] ? 1 : -1;
                                                            return $value;
                                                        });
                                                        // формируем содержимое для уведомления
                                                        $content = "";// начальное значение для формирования контента
                                                        $before = null;// предыдущий элимент
                                                        if(count($items)){// если есть элименты для отображения
                                                            for($i = 0, $iLen = count($items); $i < $iLen; $i++){
                                                                $item = $items[$i];// получаем очередной элимент
                                                                $raid = $raids->get($item["raid"]);
                                                                $role = $roles->get($item["role"]);
                                                                // построчно формируем текст содержимого
                                                                if(empty($before) or $before["title"] != $item["title"]) $content .= (!empty($content) ? "\n\n" : "") . "__**" . $item["title"] . "** - " . $item["day"] . "__";
                                                                if(empty($before) or $before["group"] != $item["group"] or $before["time"] != $item["time"] or $before["raid"] != $item["raid"]){
                                                                    $limit = $limits[$item["raid"]];
                                                                    $count = $counts[$item["time"]][$item["raid"]][$item["group"]][""];
                                                                    $content .= (!empty($content) ? "\n" : "") . "**" . date("H:i", $item["time"]) . "** - **" . $raid["key"] . "** " . $raid["name"] . (!empty($raid["addition"]) ? " **DLC**" : "") . ($limit ? " (" . $count . " из " . $limit . ")" : "");
                                                                };
                                                                if(empty($before) or $before["role"] != $item["role"] or $before["time"] != $item["time"] or $before["raid"] != $item["raid"] or $before["group"] != $item["group"]){
                                                                    $count = $counts[$item["time"]][$item["raid"]][$item["group"]][$item["role"]];
                                                                    $content .= (!empty($content) ? "\n" : "") . $role[1 == $count ? "single" : "multi"] . ": ";
                                                                }else $content .= ", ";
                                                                $content .= "<@!" . $item["user"] . ">";
                                                                if($item["leader"]) $content .= " - лидер";
                                                                // сохраняем ссылку на предыдущий элимент
                                                                $before = $item;
                                                            };
                                                        }else $content = "Ещё никто не записался.";
                                                        // изменяем или публикуем сообщение
                                                        if(empty($mid)){// если нужно опубликовать новое сообщение
                                                            $url = $app["val"]["discordApiUrl"] . "/channels/" . $cid . "/messages";
                                                            $data = array("content" => $content);
                                                            $data = json_encode($data, JSON_UNESCAPED_UNICODE);
                                                            $data = http("post", $url, $data, null, $header, false);
                                                            if(200 == $data["status"]){// если сообщение отправленно
                                                            }else $status = 268;// не удалось получить корректный ответ от удаленного сервера
                                                        }else if($notification != $content){// если нужно изменить старое сообщение
                                                            $url = $app["val"]["discordApiUrl"] . "/channels/" . $cid . "/messages/" . $mid;
                                                            $data = array("content" => $content);
                                                            $data = json_encode($data, JSON_UNESCAPED_UNICODE);
                                                            $data = http("patch", $url, $data, null, $header, false);
                                                            if(200 == $data["status"]){// если сообщение отправленно
                                                            }else $status = 268;// не удалось получить корректный ответ от удаленного сервера
                                                        };
                                                    };
                                                };
                                            };
                                        };
                                    }else $status = 261;// не удалось загрузить одну из многих баз данных
                                }else $status = 261;// не удалось загрузить одну из многих баз данных
                            }else $status = 261;// не удалось загрузить одну из многих баз данных
                        }else $status = 261;// не удалось загрузить одну из многих баз данных
                        // сохраняем базу событий
                        if(!empty($events)){// если база данных получена
                            if(empty($status) and $isEventsUpdate){// если нет ошибок
                                if($events->save(false)){// если данные успешно сохранены
                                    $result = true;// успешное выполнение
                                }else $status = 270;// не удалось сохранить базу данных
                            }else $events->unlock();// разблокируем базу
                        };
                    }else $status = 165;// переданные параметры не верны
                }else $status = 162;// один из обязательных параметров передан в неверном формате
            }else $status = 161;// не передан один из обязательных параметров
            // возвращаем результат
            if(!$result and !empty($status)) $result = false;
            return $result;
        }
    ),
    "format" => array(// поддерживаемые форматы вывода
        "json" => function($data){// в формате json
        //@param $data {array} - массыв выводимых данных
        //@return {boolean} - успешность вывода данных в этом формате
            $error = 0;
            
            // для нормальных браузеров добавляем Content-Type
            if(
                false === strpos($_SERVER["HTTP_USER_AGENT"], "MSIE")
                and false === strpos($_SERVER["HTTP_USER_AGENT"], "Trident")
            ){
                header("Content-Type: application/json; charset=utf-8");
            };
            echo json_encode($data);
            // возвращаем результат
            return !$error;
        },
        "http" => function($data){// в формате http
        //@param $data {array} - массыв выводимых данных
        //@return {boolean} - успешность вывода данных в этом формате
            global $app;
            $error = 0;
            
            // работа с заголовками
            foreach($data as $key => $value){
                if("response" != $key){// если это не результат работы
                    header("X-Api-".ucfirst(strtolower($key)).": ".$value);
                };
            };
            // работаем с содержимым
            if(is_array($data["response"])) echo json_encode($data["response"]);
            else if(is_bool($data["response"])) echo $data["response"] ? "true" : "false";
            else echo $data["response"];
            // возвращаем результат
            return !$error;
        },
        "xml" => function($data){// в формате xml
        //@param $data {array} - массыв выводимых данных
        //@return {boolean} - успешность вывода данных в этом формате
            global $app;
            $error = 0;
            
            function data2node($node, $data){// добавляет данные к узлу
            //@param $node {DOMNode} - узел к которому нужно добавить данные
            //@param $data {mixed} - данные которые необходимо добавить
            //@return {undefined} - нечего не возвращает
                $document = $node->ownerDocument;
                if(is_array($data)){// для массивов
                    foreach($data as $tag => $value){
                        if(is_numeric($tag)){// если не ассоциативный массив
                            $node->setAttribute("list", "true");
                            $tag = "item";
                        };
                        $item = $document->createElement($tag);
                        $node->appendChild($item);
                        data2node($item, $value);
                    };
                }else{// для не массивов
                    switch(true){// пробигаемся по типам данных
                        case is_null($data):
                            $value = "null";
                            break;
                        case is_bool($data):
                            $value = $data ? "true" : "false";
                            break;
                        case is_integer($data):
                        case is_float($data):
                            $value = empty($data) ? "0" : $data;
                            break;
                        default:
                            $value = $data;
                    };
                    $text = new DOMText($value);
                    $node->appendChild($text);
                };
            };
            // готовим и выводим xml через DOM
            $document = new DOMDocument("1.0", "utf-8");
            $node = $document->createElement("api");
            $document->appendChild($node);
            data2node($node, $data);
            header("Content-Type: text/xml; charset=utf-8");
            echo $document->saveXML();
            // возвращаем результат
            return !$error;
        }
    ),
    "fun" => array(// специфические функции
        "storage" => function($name, $filter = null, $update = null, $lock = false){// простые манипуляции с базой данных
        //@param $name {string|object} - имя загружаемой базы данных или объект уже загруженной базы данных
        //@param $filter {array|function|mixed}  - массив атрибутов со значениями для получения выборки, функция принимающая текущий элимент и возвращаюая булевское значения для включения текущего элемента в выборку, значение ключевого аттрибута для включения этого элемента в выборку
        //@param $update {array|function|false}  - массив атрибутов со значениями для обновления выборки, функция принимающая текущий элимент и возвращаюая массив атрибутов со значениями для обновления выборки. Если выборка пуста, а в фильтре передано значение ключевого аттрибута, то создаётся новый элимент. Если передано false то удаляется элимент
        //@param $lock {boolean} - заблокировать хранилище на запись после загрузки
        //@return {null|array|boolean} - null если нечего не делалось из-за отсутствия обязательных параметров или если нет обновляемых даных и выборка представленная массивом элиментов или если в фильтре указано значение ключевого аттрибута. Успешность обновления данных если есть на что обновлять
            global $app;
            $new = false; $items = array();
            // отробатываем первый парамеетр
            if(!empty($name)){// если передана база данных
                if(!is_object($name)){// если передано название базы
                    if(!isset($app["base"][$name])){// если база данных еще не загружалась
                        $storage = new FileStorage(template($app["val"]["baseUrl"], array("name" => $name)));
                        if($storage->load($lock)){// если удалось открыть базу данных
                            $app["base"][$name] = &$storage;
                        }else unset($storage);
                    }else $storage = &$app["base"][$name];
                }else $storage = &$name;
                if(isset($storage)){// если база данных существует
                    // отробатываем второй парамеетр
                    if(!is_null($filter)){// если указан фильтр
                        if(is_array($filter)){// если указаны аттрибуты для фильтрации
                            for($i = 0, $iLen = $storage->length; $i < $iLen; $i++){
                                $item = $storage->get($storage->key($i));
                                $flag = true;
                                foreach($filter as $attr => $value){
                                    if($item[$attr] !== $value){
                                        $flag = false;
                                        break;
                                    };
                                };
                                if($flag) $items[] = $item;
                                unset($item);
                            };
                            $result = &$items;
                        }else if(is_callable($filter) && !is_string($filter)){// если указана функция
                            for($i = 0, $iLen = $storage->length; $i < $iLen; $i++){
                                $item = $storage->get($storage->key($i));
                                $flag = $filter($item);
                                if($flag) $items[] = $item;
                                unset($item);
                            };
                            $result = &$items;
                        }else{// если указано значение ключа
                            $item = $storage->get($filter);
                            if(!is_null($item)) $items[] = $item;
                            else $items[] = array($storage->primary => $filter);
                            $result = &$item;
                        };
                    }else if(!is_null($update)){// если фильтр неуказан а данные для обновления указаны
                        $result = &$storage;
                        for($i = 0, $iLen = $storage->length; $i < $iLen; $i++){
                            $item = $storage->get($storage->key($i));
                            $items[] = $item;
                            unset($item);
                        };
                    }else $result = &$storage;
                    // отробатываем третий парамеетр
                    if(!is_null($update)){// если указаны данные для обновления
                        $flag = null;
                        if(is_array($update)){// если указаны аттрибуты для обновления
                            for($i = 0, $iLen = count($items); $i < $iLen; $i++){
                                $id = $items[$i][$storage->primary];
                                $flag = $storage->set($id, null, $update);
                                if(!$flag) break;
                            };
                        }else if(is_callable($update) && !is_string($update)){// если указана функция
                            for($i = 0, $iLen = count($items); $i < $iLen; $i++){
                                $item = $storage->get($storage->key($i));
                                $flag = $storage->set($storage->key($i), null, $update($item));
                                if(!$flag) break;
                            };
                        }else if(false === $update){// если необходимо удалить элименты
                            for($i = 0, $iLen = count($items); $i < $iLen; $i++){
                                $id = $items[$i][$storage->primary];
                                $flag = $storage->set($id);
                                if(!$flag) break;
                            };
                        };
                        $result = $flag;
                    };
                }else $result = false;
            }else $result = null;
            // возвращаем результат
            return $result;
        },
        "setStatus" => function($id){// устанавливает статус по его идентификатору
        //@param $id {number} - идентификатор устанавливаемого статусного сообщения
        //@return {boolean} - успешность установки указанного статуса
            global $app, $result;
            $error = 0;
            
            if(!empty($id)){// если передан не пустой идентификатор
                $result["msg"] = $app["base"]["statuses"]->get($id, $app["val"]["statusLang"]);
                if(is_null($result["msg"])) $result["msg"] = $app["val"]["statusUnknown"];
                $result["status"] = $id;
            }$error = 1;
            return !$error;
        },
        "getClearParam" => function(&$params, $name, $filter = "string", $list = 0){// фильтреет параметр массива по заданному фильтру
        //@param $params {array} - ассоциативный массив параметров
        //@param $name {string|integer|float} - идентификатор параметра для фильтрации
        //@param $list {integer|true} - сколько УНИКАЛЬНЫХ значений получать из содержимаго (если не равно нулю, то возвращается массив, если true то весь массив)
        //@return {null|false|array|mixed} - null при отсутствии параметра, false при не соответствии фильтру или отфильтрованное значение (или массив отфильтрованных значений)
            $values = $value = null; $delim = ","; $flags = array();
            
            if(isset($params[$name])){// если параметр с таким именем существует
                // готовим список на фильтрацию
                $values = array();
                $value = $params[$name];
                if($list){// если запрашивается список
                    $array = explode($delim, $value);
                    for($i = 0, $iLen = count($array); $i < $iLen; $i++){
                        if(true !== $list and count($values) >= $list) break 1;
                        $value = $array[$i];
                        if(!isset($flags[$value])){// если это уникальное значение
                            $flags[$value] = 1;
                            $values[] = $value;
                        }else $flags[$value]++;
                    };
                }else $values[] = $value;
                // фильтруем каждый элимент списка
                for($i = 0, $iLen = count($values); $i < $iLen; $i++){
                    $value = is_string($values[$i]) ? trim($values[$i]) : $values[$i];
                    switch($filter){// фильтры основанные на регулярных вырожениях
                        case "password": $filter = "(?=^.{8,}$)((?=.*\d)|(?=.*\W+))(?![.\n])(?=.*[A-Z])(?=.*[a-z]).*$"; break; // строчные и прописные латинские буквы, цифры, спецсимволы, минимум 8 символов
                        case "md5": $filter = "^[0-9a-f]{32}$"; $value = mb_strtolower($value); break;
                        case "sha1": $filter = "^[0-9a-f]{40}$"; $value = mb_strtolower($value); break;
                        case "number": $filter = "^\d+$"; break;
                    };
                    switch($filter){// пробигаемся по поддердиваемым фильтрам
                        case "integer": $value = filter_var($value, FILTER_VALIDATE_INT); if(false === $value) (int)$value; break;
                        case "natural": $value = filter_var($value, FILTER_VALIDATE_INT, array("options" => array("min_range" => 0))); if(false === $value) (int)$value; break;
                        case "float": $value = filter_var($value, FILTER_VALIDATE_FLOAT); if(false === $value) (float)$value; break;
                        case "boolean": $value = filter_var($value, FILTER_VALIDATE_BOOLEAN); if(false === $value) $value = 0; break;
                        case "email": $value = filter_var($value, FILTER_VALIDATE_EMAIL); break;
                        case "string": $value = filter_var($value, FILTER_SANITIZE_STRING); if(empty($value)) $value = false; break;
                        case "chars": $value = filter_var($value, FILTER_SANITIZE_FULL_SPECIAL_CHARS); if(empty($value)) $value = false; break;
                        // специализированные фильтры без регулярных вырожений
                        case "file": if(!(isset($value["name"], $value["type"], $value["tmp_name"], $value["error"], $value["size"]) and UPLOAD_ERR_OK == $value["error"] and $value["size"] > 0)) $value = false; break;
                        // фильтры основанные на регулярных вырожениях
                        default: $value = filter_var($value, FILTER_VALIDATE_REGEXP, array("options" => array("regexp" => "/".$filter."/")));
                    };
                    $values[$i] = $value;
                };
            };
            return $list ? $values : $value;
        }
    )
);

// выставляем время по часам сервера
date_default_timezone_set("Europe/Moscow");
// настраиваем крон на полное выполнение скрипта
ini_set("max_input_time", 0);
ini_set("ignore_user_abort", 1);
ini_set("max_execution_time", 0);
// готовим список полученных параметров
$params = array();
foreach($_GET as $key => $value){
    if(!preg_match("//u", $value)){
        $value = iconv("cp1251", "UTF-8", $value);
    };
    $key = str_replace("_", ".", $key);
    $params[$key] = $value;
};
foreach($_POST as $key => $value){
    $key = str_replace("_", ".", $key);
    if(!isset($params[$key])){
        $params[$key] = $value;
    };
};
ksort($params);
// обработываем полученные данные
$statuses = $app["fun"]["storage"]("statuses");
$result = array("response" => null, "status" => 0, "msg" => "");
$status = $result["status"];
if(!empty($statuses)){// подключаемся к базе данных статусов
    $method = $app["fun"]["getClearParam"]($params, "method", "string");
    if(!is_null($method)){// если задан метод в запросе
        if(!empty($method)){// если фильтрация метода прошла успешно
            if(isset($app["method"][$method])){// если метод есть в списке поддерживаемых методов
                $result["response"] = $app["method"][$method]($params, array(), false, $status);
            }else $status = 265;// запрашиваемый метод не поддерживается
        }else $status = 162;// один из обязательных параметров передан в неверном формате
    }else $status = 161;// не передан один из обязательных параметров
}else $status = 261;// не удалось подключиться к базе данных статусов
if(empty($status)) $status = 200;// успешно выполено
$app["fun"]["setStatus"]($status);

// выводим результат работы скрипта
header("Server: Simple API 0.1.0");
$format = $app["fun"]["getClearParam"]($params, "format", "string");
if(empty($format) or !isset($app["format"][$format])){// если задан не поддерживаемый формат
    $format = $app["val"]["format"];// устанавливаем формат поумолчанию
};
header("Cache-Control: no-store");
header("Pragma: no-cache");
$app["format"][$format]($result);
?>
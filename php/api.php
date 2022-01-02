<?php # 0.3.0d api для бота в discord

include_once "../../libs/File-0.1.inc.php";                                 // 0.1.6 класс для многопоточной работы с файлом
include_once "../../libs/FileStorage-0.5.inc.php";                          // 0.5.10 класс для работы с файловым реляционным хранилищем
include_once "../../libs/phpEasy-0.3.inc.php";                              // 0.3.10 основная библиотека упрощённого взаимодействия
include_once "../../libs/vendor/webSocketClient-1.0.inc.php";               // 0.1.0 набор функций для работы с websocket

//////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

$app = array(// основной массив данных
    "val" => array(// переменные и константы
        "baseUrl" => "../base|/%group%|/%name%.db",                         // шаблон url для базы данных
        "cacheUrl" => "../cache|/%group%|/%name%|/%id%.json",               // шаблон url для кеша данных
        "debugUrl" => null,                                                 // шаблон url для включения режима отладки
        "statusUnknown" => "Server unknown status",                         // сообщение для неизвестного статуса
        "statusLang" => "en",                                               // язык для кодов расшифровок
        "format" => "json",                                                 // формат вывода поумолчанию
        "game" => null,                                                     // [изменяется в коде] идентификатор игры
        "useFileCache" => null,                                             // [изменяется в коде] использовать файловый кеш данных
        "lineDelim" => "\n",                                                // разделитель строк используемый в приложении
        "valueDelim" => "|",                                                // разделитель значений на списки для внутреннего использования
        "timeZone" => "Europe/Moscow",                                      // временная зона по умолчанию для работы со временем
        "eventUrl" => "..|/%group%|/%id%|/%name%",                          // шаблон url для генерации ссылки на событие
        "eventTimeAdd" => 15*24*60*60,                                      // максимальное время для записи в событие
        "eventTimeHide" => 6*60*60,                                         // максимальное время отображения записи события
        "eventTimeDelete" => 30*24*60*60,                                   // максимальное время хранения записи события
        "eventTimeClose" => -15*60,                                         // время за которое закрывается событие для изменения
        "eventTimeNotice" => 15*60,                                         // время за которое начинается рассылка уведомлений
        "eventDescriptionLength" => 450,                                    // максимальная длина описания события
        "eventCommentLength" => 50,                                         // максимальная длина комментария пользователя
        "eventNoticeLimit" => 5,                                            // максимальное количество отправляемых уведомлений за один раз
        "eventOperationCycle" => 20,                                        // с какой частотой производить регламентные операции с базой в цикле
        "discordLang" => "ru",                                              // язык по умолчанию для отображения информации в канале Discord
        "discordUrl" => "https://discord.com/",                             // базовый url для взаимодействия с Discord
        "discordWebSocketHost" => "gateway.discord.gg",                     // адрес хоста для взаимодействия с Discord через WebSocket
        "discordWebSocketLoop" => 1000,                                     // лимит итераций цикла общения WebSocket с Discord
        "discordMessageLength" => 2000,                                     // максимальная длина сообщения в Discord
        "discordMessageTime" => 6*60,                                       // максимально допустимое время между сгруппированными сообщениями
        "discordMessageLimit" => 50,                                        // максимальное количество кешируемых сообщений в канале
        "discordCreatePermission" => 32768,                                 // разрешения для создание первой записи в событие (прикреплять файлы)
        "discordUserPermission" => 16384,                                   // разрешения для записи других пользователей (встраивать ссылки)
        "discordMainPermission" => 388160,                                  // минимальные разрешения бота для работы
        "discordInviteUrl" => "https://discord.gg|/%id%",                   // шаблон url для приглашения в гильдию
        "appTimeLimit" => 9*60 + 45,                                        // лимит времи исполнения приложения
        "appToken" => "MY-APP-TOKEN",                                       // защитный ключ приложения
        "discordContentUrl" => "https://cdn.discordapp.com|/%group%|/%name%|/%id%.png"
    ),
    "base" => array(// базы данных
    ),
    "cache" => array(// кеш данных
    ),
    "method" => array(// поддерживаемые методы
        "discord.connect" => function($params, $options, $sign, &$status){// выполняем подключение
        //@param $params {array} - массив внешних не отфильтрованных значений
        //@param $options {array} - массив внутренних настроек
        //@param $sign {boolean|null} - успешность проверки подписи или null при её отсутствии
        //@param $status {integer} - целое число статуса выполнения
        //@return {integer} - битовая маска обновления баз
            global $app; $result = null;
            
            $mask = array(// бит маска баз данных
                "session" => 1 << 0,
                "events" =>  1 << 1,
                "players" => 1 << 2
            );
            $isUpdate = 0;// битовая маска обновления баз
            $isNeedProcessing = false;// требуется ли дальнейшая обработка
            $start = microtime(true);// время начала работы приложения
            $app["fun"]["setDebug"](1, "run");// отладочная информация
            // получаем очищенные значения параметров
            $game = $app["fun"]["getClearParam"]($params, "game", "string");
            $token = $app["fun"]["getClearParam"]($params, "token", "string");
            // проверяем корректность указанных параметров
            if(empty($status)){// если нет ошибок
                if((!is_null($game) and !is_null($token)) or get_val($options, "nocontrol", false)){// если указаны обязательные поля
                    if((!empty($game) and !empty($token)) or get_val($options, "nocontrol", false)){// если обязательные поля успешно отфильтрованы
                        if($token == $app["val"]["appToken"] or get_val($options, "nocontrol", false)){// если прошли проверку
                            $game = $app["val"]["game"] = mb_strtolower($game);// сохраняем информацию об игре
                        }else $status = 303;// переданные параметры не верны
                    }else $status = 302;// один из обязательных параметров передан в неверном формате
                }else $status = 301;// не передан один из обязательных параметров
            };
            // загружаем все необходимые базы данных
            if(empty($status)){// если нет ошибок
                $session = $app["fun"]["getStorage"]($game, "session", true);
                if(!empty($session)){// если удалось получить доступ к базе данных
                }else $status = 304;// не удалось загрузить одну из многих баз данных
            };
            if(empty($status)){// если нет ошибок
                $events = $app["fun"]["getStorage"]($game, "events", true);
                if(!empty($events)){// если удалось получить доступ к базе данных
                }else $status = 304;// не удалось загрузить одну из многих баз данных
            };
            if(empty($status)){// если нет ошибок
                $players = $app["fun"]["getStorage"]($game, "players", true);
                if(!empty($players)){// если удалось получить доступ к базе данных
                }else $status = 304;// не удалось загрузить одну из многих баз данных
            };
            // обрабатываем циклически все уведомления
            if(empty($status)){// если нет ошибок
                $index = 0;// индекс итераций цикла
                $websocket = null;// подключение через веб-сокет
                $heartbeatSendTime = 0;// время отправка последнего серцебиения
                $heartbeatAcceptTime = 0;// время ответа на последнее серцебиение
                $heartbeatInterval = 0;// интервал отправка серцебиения
                $app["val"]["useFileCache"] = true;// включаем использование
                $app["fun"]["setDebug"](1, "begin");// отладочная информация
                do{// выполняем циклическую обработку
                    // получаем данные из подключения
                    if($websocket){// если создано подключение
                        $data = websocket_read($websocket, $error);
                        if($data) $data = json_decode($data, true);
                    }else $data = array("op" => 7);// reconnect
                    $now = microtime(true);// текущее время
                    // обрабатываем код уведомления
                    switch(get_val($data, "op", null)){// поддерживаемые коды
                        case 0:// dispatch
                            $app["fun"]["setDebug"](2, get_val($data, "t", null),
                                isset($data["d"]["guild_id"]) ? $data["d"]["guild_id"] : (isset($data["d"]["author"]["id"]) ? $data["d"]["author"]["id"] : null),
                                isset($data["d"]["channel_id"]) ? $data["d"]["channel_id"] : null,
                                isset($data["d"]["message_id"]) ? $data["d"]["message_id"] : null,
                                isset($data["d"]["user_id"]) ? $data["d"]["user_id"] : null
                            );// отладочная информация
                            // обрабатываем тип уведомления
                            switch(get_val($data, "t", null)){// поддерживаемые типы
                                case "READY":// ready
                                    $isNeedProcessing = false;// требуется ли дальнейшая обработка
                                    // обрабатываем начало подключения
                                    if(isset($data["d"]["session_id"])){// если есть обязательное значение
                                        if($session->set("sid", "value", $data["d"]["session_id"])){// если данные успешно добавлены
                                            $isUpdate |= $mask["session"];// отмечаем изменение базы
                                            if($app["val"]["useFileCache"]){// если используются
                                                $app["fun"]["delFileCache"]($game);// сбрасываем всё
                                            };
                                        }else $status = 309;// не удалось записать данные в базу данных
                                    }else $status = 306;// не удалось получить корректный ответ от удаленного сервера
                                case "RESUMED":// resumed
                                    // обрабатываем возобнавление подключения
                                    if(empty($status)){// если нет ошибок
                                        // обновляем информацию о боте
                                        $data = array(// данные для отправки
                                            "op" => 3,// status update
                                            "d" => array(// data
                                                "since" => null,
                                                "game" => array(
                                                    "name" => $session->get("title", "value"),
                                                    "type" => 0
                                                ),
                                                "status" => "online",
                                                "afk" => false
                                            )
                                        );
                                        $data = json_encode($data, JSON_UNESCAPED_UNICODE);
                                        websocket_write($websocket, $data, true);
                                    };
                                    break;
                                case "TYPING_START":// typing start
                                    $isNeedProcessing = true;// требуется ли дальнейшая обработка
                                    // обрабатываем начало набора сообщения в канале гильдии
                                    if(isset($data["d"]["member"]["user"]["id"], $data["d"]["channel_id"], $data["d"]["guild_id"])){// если есть обязательное значение
                                        // проверяем права доступа
                                        $flag = false;// есть ли необходимые права для выполнения действия
                                        if(!$flag) $config = $app["fun"]["getChannelConfig"]($data["d"]["channel_id"], $data["d"]["guild_id"], null);// конфигурация канала
                                        if(!$flag) $permission = $app["fun"]["getPermission"]("guild", $session->get("bot", "value"), $data["d"]["channel_id"], $data["d"]["guild_id"]);
                                        $flag = ($flag or $config["mode"] and $config["game"] == $game and ($permission & $app["val"]["discordMainPermission"]) == $app["val"]["discordMainPermission"]);
                                        // обновляем участника и пользователя
                                        if($flag){// если бот контролирует канал в котором произошло событие
                                            $member = $app["fun"]["setCache"]("member", $data["d"]["member"], $data["d"]["guild_id"]);
                                            if($member){// если удалось закешировать данные
                                                $user = $app["fun"]["setCache"]("user", $data["d"]["member"]["user"]);
                                                if($user){// если удалось закешировать данные
                                                    $user = $app["fun"]["getCache"]("user", $data["d"]["member"]["user"]["id"]);
                                                }else $app["fun"]["delCache"]("user", $data["d"]["member"]["user"]["id"]);
                                            }else $app["fun"]["delCache"]("member", $data["d"]["member"]["user"]["id"], $data["d"]["guild_id"]);
                                        };
                                        // обновляем информацию о пользователях в ближайщих событиях
                                        if(!$flag){// если бот не контролирует канал в котором произошло событие и не было других обработок
                                            // формируем список событий
                                            $items = array();// список событий
                                            for($i = 0, $iLen = $events->length; $i < $iLen; $i++){
                                                $eid = $events->key($i);// получаем ключевой идентификатор по индексу
                                                $event = $events->get($eid);// получаем элемент по идентификатору
                                                if(// множественное условие
                                                    $event["time"] < $now + $app["val"]["eventTimeNotice"] + $app["val"]["appTimeLimit"]
                                                    and $event["time"] > $now + $app["val"]["eventTimeNotice"]
                                                ){// если нужно добавить в список
                                                    array_push($items, $event);
                                                };
                                            };
                                            // сортируем список событитй
                                            usort($items, function($a, $b){// сортировка
                                                $value = 0;// начальное значение
                                                if(!$value and $a["time"] != $b["time"]) $value = $a["time"] > $b["time"] ? 1 : -1;
                                                if(!$value and $a["raid"] != $b["raid"]) $value = $a["raid"] > $b["raid"] ? 1 : -1;
                                                if(!$value and $a["id"] != $b["id"]) $value = $a["id"] < $b["id"] ? 1 : -1;
                                                // возвращаем результат
                                                return $value;
                                            });
                                            // обновляем информацию о пользователях
                                            for($i = 0, $iLen = count($items); $i < $iLen and !$flag; $i++){
                                                $event = $items[$i];// получаем очередной элимент
                                                for($j = 0, $jLen = $players->length; $j < $jLen and !$flag; $j++){
                                                    $pid = $players->key($j);// получаем ключевой идентификатор по индексу
                                                    $player = $players->get($pid);// получаем элемент по идентификатору
                                                    if(!$player["notice"] and $event["id"] == $player["event"]){// если уведомление ещё не отправлялось
                                                        $user = $app["fun"]["getCache"]("user", $player["user"]);
                                                        $flag = !isset($user["channels"][0]);// проверка получения данных
                                                        if(!$flag){// если удалось получить данные
                                                            $channel = $user["channels"][0];// получаем канал личных сообщений
                                                            if(!isset($channel["messages"])){// если список сообщений ещё не запрашивался
                                                                $channel = $app["fun"]["getCache"]("channel", $channel["id"], null, $user["id"]);
                                                                $flag = true;// прекращаем дальнейшее обновление
                                                            };
                                                        };
                                                    };
                                                };
                                            };
                                        };
                                        // обновляем ближайщий не обновлённый контролируемый канал
                                        if(!$flag){// если бот не контролирует канал в котором произошло событие и не было других обработок
                                            $guild = $app["fun"]["getCache"]("guild", $data["d"]["guild_id"]);
                                            if(isset($guild["channels"])){// если удалось получить данные
                                                $member = $app["fun"]["getCache"]("member", $session->get("bot", "value"), $guild["id"]);
                                                for($i = count($guild["channels"]) - 1; $i > -1 and !$flag; $i--){// пробигаемся по каналам
                                                    $channel = $guild["channels"][$i];// получаем очередной элемент
                                                    if(!isset($channel["messages"])){// если список сообщений ещё не запрашивался
                                                        if(!$flag) $config = $app["fun"]["getChannelConfig"]($channel, $guild, null);// конфигурация канала
                                                        if(!$flag) $permission = $app["fun"]["getPermission"]("guild", $session->get("bot", "value"), $channel, $guild);
                                                        $flag = ($flag or $config["mode"] and $config["game"] == $game and ($permission & $app["val"]["discordMainPermission"]) == $app["val"]["discordMainPermission"]);
                                                        if($flag){// если нужно обновить канал
                                                            // обрабатываем канал
                                                            $isUpdate |= $app["method"]["discord.channel"](
                                                                array(// параметры для метода
                                                                    "channel" => $channel["id"],
                                                                    "guild" => $guild["id"],
                                                                    "game" => $game
                                                                ),
                                                                array(// внутренние опции
                                                                    "nocontrol" => true
                                                                ),
                                                                $sign, $status
                                                            );
                                                        };
                                                    };
                                                };
                                            };
                                        };
                                    };
                                    // обрабатываем начало набора сообщения в личном канале
                                    if(isset($data["d"]["user_id"], $data["d"]["channel_id"]) and !isset($data["d"]["guild_id"])){// если есть обязательное значение
                                        // обновляем пользователя и его канал
                                        $flag = $data["d"]["user_id"] != $session->get("bot", "value");
                                        if($flag){// если информация пришла не от текущего бота
                                            $user = $app["fun"]["getCache"]("user", $data["d"]["user_id"]);
                                            if($user){// если удалось получить информацию о пользователе
                                                $channel = $app["fun"]["getCache"]("channel", $data["d"]["channel_id"], null, $data["d"]["user_id"]);
                                            };
                                        };
                                    };
                                    break;
                                case "GUILD_UPDATE":// guild update
                                    $isNeedProcessing = true;// требуется ли дальнейшая обработка
                                case "GUILD_CREATE":// guild create
                                    // обрабатываем изменение гильдии
                                    if(isset($data["d"]["id"])){// если есть обязательное значение
                                        $guild = $app["fun"]["setCache"]("guild", $data["d"]);
                                        if($guild){// если удалось закешировать данные
                                            if($isNeedProcessing){// если требуется обработка
                                                // обрабатываем гильдию
                                                $isUpdate |= $app["method"]["discord.guild"](
                                                    array(// параметры для метода
                                                        "guild" => $data["d"]["id"],
                                                        "game" => $game
                                                    ),
                                                    array(// внутренние опции
                                                        "nocontrol" => true
                                                    ),
                                                    $sign, $status
                                                );
                                            };
                                        }else $app["fun"]["delCache"]("guild", $data["d"]["id"]);
                                    };
                                    break;
                                case "GUILD_DELETE":// guild delete
                                    $isNeedProcessing = true;// требуется ли дальнейшая обработка
                                    // обрабатываем удаление гильдии
                                    if(isset($data["d"]["id"])){// если есть обязательное значение
                                        $app["fun"]["delCache"]("guild", $data["d"]["id"]);
                                    };
                                    break;
                                case "GUILD_ROLE_UPDATE":// guild role update
                                case "GUILD_ROLE_CREATE":// guild role create
                                    $isNeedProcessing = true;// требуется ли дальнейшая обработка
                                    // обрабатываем изменение роли в гильдии
                                    if(isset($data["d"]["role"]["id"], $data["d"]["guild_id"])){// если есть обязательное значение
                                        $role = $app["fun"]["setCache"]("role", $data["d"]["role"], $data["d"]["guild_id"]);
                                        if($role){// если удалось закешировать данные
                                            $guild = $app["fun"]["getCache"]("guild", $data["d"]["guild_id"]);
                                            if($guild){// если удалось получить данные
                                                // обрабатываем гильдию
                                                $isUpdate |= $app["method"]["discord.guild"](
                                                    array(// параметры для метода
                                                        "guild" => $data["d"]["guild_id"],
                                                        "game" => $game
                                                    ),
                                                    array(// внутренние опции
                                                        "nocontrol" => true
                                                    ),
                                                    $sign, $status
                                                );
                                            };
                                        }else $app["fun"]["delCache"]("role", $data["d"]["role"]["id"], $data["d"]["guild_id"]);
                                    };
                                    break;
                                case "GUILD_ROLE_DELETE":// guild role delete
                                    $isNeedProcessing = true;// требуется ли дальнейшая обработка
                                    // обрабатываем удаление роли в гильдии
                                    if(isset($data["d"]["role_id"], $data["d"]["guild_id"])){// если есть обязательное значение
                                        $app["fun"]["delCache"]("role", $data["d"]["role_id"], $data["d"]["guild_id"]);
                                        $guild = $app["fun"]["getCache"]("guild", $data["d"]["guild_id"]);
                                        if($guild){// если удалось получить данные
                                            // обрабатываем гильдию
                                            $isUpdate |= $app["method"]["discord.guild"](
                                                array(// параметры для метода
                                                    "guild" => $data["d"]["guild_id"],
                                                    "game" => $game
                                                ),
                                                array(// внутренние опции
                                                    "nocontrol" => true
                                                ),
                                                $sign, $status
                                            );
                                        };
                                    };
                                    break;
                                case "GUILD_EMOJIS_UPDATE":// guild emojis update
                                    $isNeedProcessing = true;// требуется ли дальнейшая обработка
                                    // обрабатываем изменение эмодзи гильдии
                                    if(isset($data["d"]["emojis"], $data["d"]["guild_id"])){// если есть обязательное значение
                                        $guild = $app["fun"]["getCache"]("guild", $data["d"]["guild_id"]);
                                        if(isset($guild["emojis"])){// если удалось получить данные
                                            // очищаем список эмодзи гильдии
                                            for($i = count($guild["emojis"]) - 1; $i > -1; $i--){// пробигаемся по эмодзи
                                                $app["fun"]["delCache"]("emoji", $guild["emojis"][$i]["id"], $data["d"]["guild_id"]);
                                            };
                                            // добавляем новые эмодзи гильдии
                                            for($i = 0, $iLen = count($data["d"]["emojis"]); $i < $iLen; $i++){
                                                $emoji = $app["fun"]["setCache"]("emoji", $data["d"]["emojis"][$i], $data["d"]["guild_id"]);
                                                if($emoji){// если удалось закешировать данные
                                                }else $app["fun"]["delCache"]("emoji", $data["d"]["emojis"][$i]["id"], $data["d"]["guild_id"]);
                                            };
                                        };
                                    };
                                    break;
                                case "GUILD_MEMBER_UPDATE":// guild member update
                                case "GUILD_MEMBER_ADD":// guild member add
                                    $isNeedProcessing = true;// требуется ли дальнейшая обработка
                                    // обрабатываем изменение участника
                                    if(isset($data["d"]["user"]["id"], $data["d"]["guild_id"])){// если есть обязательное значение
                                        $member = $app["fun"]["setCache"]("member", $data["d"], $data["d"]["guild_id"]);
                                        if($member){// если удалось закешировать данные
                                            $user = $app["fun"]["setCache"]("user", $data["d"]["user"]);
                                            if($user){// если удалось закешировать данные
                                                $flag = $data["d"]["user"]["id"] == $session->get("bot", "value");
                                                if($flag){// если информация пришла по текущему боту
                                                    // обрабатываем гильдию
                                                    $isUpdate |= $app["method"]["discord.guild"](
                                                        array(// параметры для метода
                                                            "guild" => $data["d"]["guild_id"],
                                                            "game" => $game
                                                        ),
                                                        array(// внутренние опции
                                                            "nocontrol" => true
                                                        ),
                                                        $sign, $status
                                                    );
                                                };
                                            }else $app["fun"]["delCache"]("user", $data["d"]["user"]["id"]);
                                        }else $app["fun"]["delCache"]("member", $data["d"]["user"]["id"], $data["d"]["guild_id"]);
                                    };
                                    break;
                                case "GUILD_MEMBER_REMOVE":// guild member remove
                                    $isNeedProcessing = true;// требуется ли дальнейшая обработка
                                    // обрабатываем удаление участника
                                    if(isset($data["d"]["user"]["id"], $data["d"]["guild_id"])){// если есть обязательное значение
                                        $app["fun"]["delCache"]("member", $data["d"]["user"]["id"], $data["d"]["guild_id"]);
                                    };
                                    break;
                                case "CHANNEL_UPDATE":// channel update
                                    $isNeedProcessing = true;// требуется ли дальнейшая обработка
                                case "CHANNEL_CREATE":// channel create
                                    // обрабатываем изменение канала гильдии
                                    if(isset($data["d"]["id"], $data["d"]["guild_id"])){// если есть обязательное значение
                                        $channel = $app["fun"]["setCache"]("channel", $data["d"], $data["d"]["guild_id"], null);
                                        if($channel){// если удалось закешировать данные
                                            if($isNeedProcessing){// если требуется обработка
                                                // обрабатываем канал
                                                $isUpdate |= $app["method"]["discord.channel"](
                                                    array(// параметры для метода
                                                        "channel" => $data["d"]["id"],
                                                        "guild" => $data["d"]["guild_id"],
                                                        "game" => $game
                                                    ),
                                                    array(// внутренние опции
                                                        "nocontrol" => true
                                                    ),
                                                    $sign, $status
                                                );
                                            };
                                        }else $app["fun"]["delCache"]("channel", $data["d"]["id"], $data["d"]["guild_id"], null);
                                    };
                                    // обрабатываем изменение личного канала
                                    if(isset($data["d"]["id"], $data["d"]["recipients"]) and !isset($data["d"]["guild_id"]) and 1 == count($data["d"]["recipients"])){// если есть обязательное значение
                                        $user = $app["fun"]["setCache"]("user", $data["d"]["recipients"][0]);
                                        if($user){// если удалось закешировать данные
                                            $channel = $app["fun"]["setCache"]("channel", $data["d"], null, $data["d"]["recipients"][0]["id"]);
                                            if($channel){// если удалось закешировать данные
                                            }else $app["fun"]["delCache"]("channel", $data["d"]["id"], null, $data["d"]["recipients"][0]["id"]);
                                        }else $app["fun"]["delCache"]("user", $data["d"]["recipients"][0]["id"]);
                                        $index--;// уменьшаем индекс итераций цикла
                                    };
                                    break;
                                case "CHANNEL_DELETE":// channel delete
                                    $isNeedProcessing = true;// требуется ли дальнейшая обработка
                                    // обрабатываем удаление канала гильдии
                                    if(isset($data["d"]["id"], $data["d"]["guild_id"])){// если есть обязательное значение
                                        $app["fun"]["delCache"]("channel", $data["d"]["id"], $data["d"]["guild_id"], null);
                                    };
                                    break;
                                case "MESSAGE_CREATE":// message create
                                    $isNeedProcessing = true;// требуется ли дальнейшая обработка
                                    // обрабатываем создание сообщения в личном канале
                                    if(isset($data["d"]["id"], $data["d"]["channel_id"], $data["d"]["author"]["id"]) and !isset($data["d"]["guild_id"])){// если есть обязательное значение
                                        $user = $app["fun"]["setCache"]("user", $data["d"]["author"]);
                                        if($user){// если удалось закешировать данные
                                            $flag = $data["d"]["author"]["id"] != $session->get("bot", "value");
                                            if($flag){// если информация пришла не от текущего бота
                                                $message = $app["fun"]["setCache"]("message", $data["d"], $data["d"]["channel_id"], null, $data["d"]["author"]["id"]);
                                                if($message){// если удалось закешировать данные
                                                    // обрабатываем сообщение
                                                    $isUpdate |= $app["method"]["discord.message"](
                                                        array(// параметры для метода
                                                            "message" => $data["d"]["id"],
                                                            "channel" => $data["d"]["channel_id"],
                                                            "user" => $data["d"]["author"]["id"],
                                                            "game" => $game
                                                        ),
                                                        array(// внутренние опции
                                                            "nocontrol" => true
                                                        ),
                                                        $sign, $status
                                                    );
                                                }else $app["fun"]["delCache"]("message", $data["d"]["id"], $data["d"]["channel_id"], null, $data["d"]["author"]["id"]);
                                            };
                                        }else $app["fun"]["delCache"]("user", $data["d"]["author"]["id"]);
                                        $index--;// уменьшаем индекс итераций цикла
                                    };
                                case "MESSAGE_UPDATE":// message update
                                    $isNeedProcessing = true;// требуется ли дальнейшая обработка
                                    // обрабатываем изменение сообщения в канале гильдии
                                    if(isset($data["d"]["id"], $data["d"]["channel_id"], $data["d"]["author"]["id"], $data["d"]["guild_id"])){// если есть обязательное значение
                                        $data["d"]["embed"] = get_val($data["d"]["embeds"], 0, false);// приводим к единому виду
                                        $message = $app["fun"]["setCache"]("message", $data["d"], $data["d"]["channel_id"], $data["d"]["guild_id"], null);
                                        if($message){// если удалось закешировать данные
                                            // проверяем права доступа
                                            $flag = false;// есть ли необходимые права для выполнения действия
                                            if(!$flag) $config = $app["fun"]["getChannelConfig"]($data["d"]["channel_id"], $data["d"]["guild_id"], null);// конфигурация канала
                                            if(!$flag) $permission = $app["fun"]["getPermission"]("guild", $session->get("bot", "value"), $data["d"]["channel_id"], $data["d"]["guild_id"]);
                                            $flag = ($flag or $config["mode"] and $config["game"] == $game and ($permission & $app["val"]["discordMainPermission"]) == $app["val"]["discordMainPermission"]);
                                            if($flag){// если бот контролирует канал в котором произошло событие
                                                $data["d"]["member"]["user"] = $data["d"]["author"];// приводим к единому виду
                                                // обновляем участника и пользователя
                                                $member = $app["fun"]["setCache"]("member", $data["d"]["member"], $data["d"]["guild_id"]);
                                                if($member){// если удалось закешировать данные
                                                    $user = $app["fun"]["setCache"]("user", $data["d"]["member"]["user"]);
                                                    if($user){// если удалось закешировать данные
                                                    }else $app["fun"]["delCache"]("user", $data["d"]["member"]["user"]["id"]);
                                                }else $app["fun"]["delCache"]("member", $data["d"]["member"]["user"]["id"], $data["d"]["guild_id"]);
                                                // обрабатываем сообщение
                                                $isUpdate |= $app["method"]["discord.message"](
                                                    array(// параметры для метода
                                                        "message" => $data["d"]["id"],
                                                        "channel" => $data["d"]["channel_id"],
                                                        "guild" => $data["d"]["guild_id"],
                                                        "game" => $game
                                                    ),
                                                    array(// внутренние опции
                                                        "nocontrol" => true
                                                    ),
                                                    $sign, $status
                                                );
                                            };
                                        }else $app["fun"]["delCache"]("message", $data["d"]["id"], $data["d"]["channel_id"], $data["d"]["guild_id"], null);
                                    };
                                    break;
                                case "MESSAGE_DELETE":// message delete
                                    // поправка на удаление одного сообщения
                                    if(isset($data["d"]["id"])) $data["d"]["ids"] = array($data["d"]["id"]);
                                case "MESSAGE_DELETE_BULK":// message delete bulk
                                    $isNeedProcessing = true;// требуется ли дальнейшая обработка
                                    // обрабатываем удаление сообщений в калале гильдии
                                    if(isset($data["d"]["ids"], $data["d"]["channel_id"], $data["d"]["guild_id"])){// если есть обязательное значение
                                        for($i = count($data["d"]["ids"]) - 1; $i > -1; $i--){// пробигаемся по идентификаторам сообщений
                                            $app["fun"]["delCache"]("message", $data["d"]["ids"][$i], $data["d"]["channel_id"], $data["d"]["guild_id"], null);
                                        };
                                        // проверяем права доступа
                                        $flag = false;// есть ли необходимые права для выполнения действия
                                        if(!$flag) $config = $app["fun"]["getChannelConfig"]($data["d"]["channel_id"], $data["d"]["guild_id"], null);// конфигурация канала
                                        if(!$flag) $permission = $app["fun"]["getPermission"]("guild", $session->get("bot", "value"), $data["d"]["channel_id"], $data["d"]["guild_id"]);
                                        $flag = ($flag or $config["mode"] and $config["game"] == $game and ($permission & $app["val"]["discordMainPermission"]) == $app["val"]["discordMainPermission"]);
                                        if($flag){// если бот контролирует канал в котором произошло событие
                                            // обрабатываем канал
                                            $isUpdate |= $app["method"]["discord.channel"](
                                                array(// параметры для метода
                                                    "channel" => $data["d"]["channel_id"],
                                                    "guild" => $data["d"]["guild_id"],
                                                    "game" => $game
                                                ),
                                                array(// внутренние опции
                                                    "nocontrol" => true
                                                ),
                                                $sign, $status
                                            );
                                        };
                                    };
                                    break;
                                case "MESSAGE_REACTION_ADD":// message reaction add
                                    $isNeedProcessing = true;// требуется ли дальнейшая обработка
                                    // обрабатываем добавление реакции в калале гильдии
                                    if(isset($data["d"]["emoji"]["name"], $data["d"]["member"]["user"]["id"], $data["d"]["user_id"], $data["d"]["message_id"], $data["d"]["channel_id"], $data["d"]["guild_id"])){// если есть обязательное значение
                                        $data["d"]["user"] = $data["d"]["member"]["user"];// приводим к единому виду
                                        $rid = array($data["d"]["user_id"], $data["d"]["emoji"]["name"], $data["d"]["emoji"]["id"]);
                                        $reaction = $app["fun"]["setCache"]("reaction", $data["d"], $data["d"]["message_id"], $data["d"]["channel_id"], $data["d"]["guild_id"], null);
                                        if($reaction){// если удалось закешировать данные
                                            // обновляем участника и пользователя
                                            $member = $app["fun"]["setCache"]("member", $data["d"]["member"], $data["d"]["guild_id"]);
                                            if($member){// если удалось закешировать данные
                                                $user = $app["fun"]["setCache"]("user", $data["d"]["member"]["user"]);
                                                if($user){// если удалось закешировать данные
                                                }else $app["fun"]["delCache"]("user", $data["d"]["member"]["user"]["id"]);
                                            }else $app["fun"]["delCache"]("member", $data["d"]["member"]["user"]["id"], $data["d"]["guild_id"]);
                                            // обрабатываем реакцию
                                            $isUpdate |= $app["method"]["discord.reaction"](
                                                array(// параметры для метода
                                                    "reaction" => $reaction ? implode(":", $rid) : null,
                                                    "message" => $data["d"]["message_id"],
                                                    "channel" => $data["d"]["channel_id"],
                                                    "guild" => $data["d"]["guild_id"],
                                                    "game" => $game
                                                ),
                                                array(// внутренние опции
                                                    "nocontrol" => true
                                                ),
                                                $sign, $status
                                            );
                                        }else $app["fun"]["delCache"]("reaction", $rid, $data["d"]["message_id"], $data["d"]["channel_id"], $data["d"]["guild_id"], null);
                                    };
                                    break;
                                case "MESSAGE_REACTION_REMOVE":// message reaction remove
                                case "MESSAGE_REACTION_REMOVE_ALL":// message reaction remove all
                                case "MESSAGE_REACTION_REMOVE_EMOJI":// message reaction remove emoji
                                    $isNeedProcessing = true;// требуется ли дальнейшая обработка
                                    // обрабатываем удаление реакций в калале гильдии
                                    if(isset($data["d"]["message_id"], $data["d"]["channel_id"], $data["d"]["guild_id"])){// если есть обязательное значение
                                        $message = $app["fun"]["getCache"]("message", $data["d"]["message_id"], $data["d"]["channel_id"], $data["d"]["guild_id"], null);
                                        if($message and isset($message["reactions"])){// если удалось получить данные
                                            for($i = count($message["reactions"]) - 1; $i > -1; $i--){// пробигаемся по реакциям
                                                $reaction = $message["reactions"][$i];// получаем очередной элемент
                                                $rid = array($reaction["user"]["id"], $reaction["emoji"]["name"], $reaction["emoji"]["id"]);
                                                // проверяем соответствие эмодзи в реакции
                                                if(!empty($data["d"]["emoji"]["id"])) $flag = $reaction["emoji"]["id"] == $data["d"]["emoji"]["id"];
                                                else if(isset($data["d"]["emoji"]["name"])) $flag = $reaction["emoji"]["name"] == $data["d"]["emoji"]["name"];
                                                else $flag = true;// если требуется удалить любую реакции с сообщения
                                                // проверяем соответствие пользователя в реакции
                                                if(isset($data["d"]["user_id"]) and $flag) $flag = $reaction["user"]["id"] == $data["d"]["user_id"];
                                                // выполняем удаление данных
                                                if($flag) $app["fun"]["delCache"]("reaction", $rid, $data["d"]["message_id"], $data["d"]["channel_id"], $data["d"]["guild_id"], null);
                                            };
                                            // обрабатываем реакции
                                            $isUpdate |= $app["method"]["discord.reaction"](
                                                array(// параметры для метода
                                                    "reaction" => null,
                                                    "message" => $data["d"]["message_id"],
                                                    "channel" => $data["d"]["channel_id"],
                                                    "guild" => $data["d"]["guild_id"],
                                                    "game" => $game
                                                ),
                                                array(// внутренние опции
                                                    "nocontrol" => true
                                                ),
                                                $sign, $status
                                            );
                                        };
                                    };
                                    break;
                            };
                            break;
                        case 7:// reconnect
                            // инициализируем новое подключение
                            $app["fun"]["setDebug"](1, "reconnect");// отладочная информация
                            if(websocket_check($websocket)) websocket_close($websocket);// закрываем старое подключение
                            $websocket = websocket_open($app["val"]["discordWebSocketHost"], 443, null, $error, 5, true);
                            if($websocket){// если удалось создать подключение к веб-сокету
                                $heartbeatInterval = 0;// отключаем проверку соединения
                            }else $status = 305;// не удалось установить соединение с удалённым сервером
                            break;
                        case 9:// invalid session
                            // пробуем создать новую сессию
                            $data = array(// данные для отправки
                                "op" => 2,// identify
                                "d" => array(// data
                                    // GUILDS | GUILD_EMOJIS_AND_STICKERS | GUILD_MESSAGES | GUILD_MESSAGE_REACTIONS | GUILD_MESSAGE_TYPING | DIRECT_MESSAGES | DIRECT_MESSAGE_REACTIONS | DIRECT_MESSAGE_TYPING
                                    "intents" => 1 << 0 | 1 << 3 | 1 << 9 | 1 << 10 | 1 << 11 | 1 << 12 | 1 << 13 | 1 << 14,
                                    "token" => $session->get("token", "value"),
                                    "properties" => array(
                                        '$browser' => "Bot Gateway Connect"
                                    )
                                )
                            );
                            $data = json_encode($data, JSON_UNESCAPED_UNICODE);
                            websocket_write($websocket, $data, true);
                            break;
                        case 10:// hello
                            // обрабатываем приветствие
                            $app["fun"]["setDebug"](1, "hello");// отладочная информация
                            if(isset($data["d"]["heartbeat_interval"])){// если есть обязательное значение
                                $heartbeatSendTime = $now;
                                $heartbeatAcceptTime = $now;
                                $heartbeatInterval = $data["d"]["heartbeat_interval"] / 1000;
                                // пробуем авторизоваться по сессии
                                $data = array(// данные для отправки
                                    "op" => 6,// resume
                                    "d" => array(// data
                                        "token" => $session->get("token", "value"),
                                        "session_id" => $session->get("sid", "value"),
                                        "seq" => 1 * $session->get("seq", "value")
                                    )
                                );
                                $data = json_encode($data, JSON_UNESCAPED_UNICODE);
                                websocket_write($websocket, $data, true);
                            }else $status = 306;// не удалось получить корректный ответ от удаленного сервера
                            break;
                        case 11:// heartbeat ack
                            // обрабатываем ответ на серцебиение
                            $app["fun"]["setDebug"](1, "heartbeat ack");// отладочная информация
                            $heartbeatAcceptTime = $now;
                            break;
                    };
                    // сохраняем номер уведомления
                    if(empty($status)){// если нет ошибок
                        if(get_val($data, "s", 0)){// если есть номер
                            if($session->set("seq", "value", $data["s"])){// если данные успешно добавлены
                                $isUpdate |= $mask["session"];// отмечаем изменение базы
                            }else $status = 309;// не удалось записать данные в базу данных
                        };
                    };
                    // проверяем и поддерживаем серцебиение
                    if(empty($status)){// если нет ошибок
                        if($heartbeatInterval > 0){// если задан интервал серцебиения
                            if($heartbeatSendTime - $heartbeatAcceptTime > 1.3 * $heartbeatInterval){// если соединение зависло
                                // разрываем соединение
                                $app["fun"]["setDebug"](1, "close", $heartbeatSendTime, $heartbeatAcceptTime, $heartbeatInterval);// отладочная информация
                                websocket_close($websocket);// закрываем старое подключение
                                $websocket = false;// подключение закрыто
                            }else if($now - $heartbeatSendTime > $heartbeatInterval){// если нужно отправить серцебиение
                                // отправляем серцебиение
                                $app["fun"]["setDebug"](1, "heartbeat");// отладочная информация
                                $data = array(// данные для отправки
                                    "op" => 1,// heartbeat
                                    "d" => 1 * $session->get("seq", "value")
                                );
                                $data = json_encode($data, JSON_UNESCAPED_UNICODE);
                                websocket_write($websocket, $data, true);
                                if(!$isNeedProcessing) $heartbeatAcceptTime = $now;
                                $heartbeatSendTime = $now;
                            };
                        };
                    };
                    // выполняем операции с базами данных
                    $value = $app["val"]["eventOperationCycle"];
                    if(!(($index + $value - 3) % $value)){// если пришло время
                        $index++;// увеличиваем индекс итераций цикла
                        // выполняем регламентные операции с базами данных
                        if(empty($status) and !get_val($options, "nocontrol", false)){// если нужно выполнить
                            $items = $app["fun"]["doRoutineStorage"]($now, $status);// получаем списки изменений
                            foreach($items as $name => $list) if(count($list)) $isUpdate |= $mask[$name];
                        };
                        // выполняем обновление изменённых данных после регламентных операций
                        if(empty($status) and !get_val($options, "nocontrol", false)){// если нужно выполнить
                            $count = array();// счётчик событий по каналам гильдии
                            // формируем специализированные данные для обновления
                            foreach($items["events"] as $eid => $event){// пробигаемся по событиям
                                $gid = $event["guild"];// получаем идентификатор гильдии
                                $cid = $event["channel"];// получаем идентификатор канала
                                if(!isset($count[$gid])) $count[$gid] = array();
                                if(!isset($count[$gid][$cid])) $count[$gid][$cid] = 0;
                                $count[$gid][$cid]++;
                            };
                            // выполняем обработку данных для гильдий
                            foreach($count as $gid => $item){// пробигаемся по списку
                                if(!empty($status)) break;// не продолжаем при ошибке
                                // выполняем обработку данных каналов для записи
                                foreach($item as $cid => $value){// пробигаемся по списку
                                    if(!empty($status)) break;// не продолжаем при ошибке
                                    $channel = $app["fun"]["getCache"]("channel", $cid, $gid, null);
                                    if($channel){// если удалось получить данные
                                        // обрабатываем действующий канал
                                        $isUpdate |= $app["method"]["discord.channel"](
                                            array(// параметры для метода
                                                "channel" => $cid,
                                                "guild" => $gid,
                                                "game" => $game
                                            ),
                                            array(// внутренние опции
                                                "nocontrol" => true
                                            ),
                                            $sign, $status
                                        );
                                    }else{// если не удалось получить данные
                                        // обрабатываем удалённый канал
                                        foreach($counts as $eid => $value){// пробигаемся по событиям
                                            // получаем данные о событии
                                            if(!empty($status)) break;// не продолжаем при ошибке
                                            $event = $events->get($eid);// получаем элемент по идентификатору
                                            if($event){// если удалось получить данные о событии
                                                // получаем данные об игроках
                                                for($i = $players->length - 1; $i > -1 and empty($status); $i--){
                                                    $pid = $players->key($i);// получаем ключевой идентификатор по индексу
                                                    $player = $players->get($pid);// получаем элемент по идентификатору
                                                    // выполняем удаление игрока
                                                    if($player["event"] == $eid){// если проверка пройдена
                                                        if($players->set($pid)){// если данные успешно удалены
                                                            $isUpdate |= $mask["players"];// отмечаем изменение базы
                                                        }else $status = 309;// не удалось записать данные в базу данных
                                                    };
                                                };
                                                // выполняем удаление собятия
                                                if(empty($status)){// если нет ошибок
                                                    if($events->set($eid)){// если данные успешно удалены
                                                        $isUpdate |= $mask["events"];// отмечаем изменение базы
                                                    }else $status = 309;// не удалось записать данные в базу данных
                                                };
                                            };
                                        };
                                    };
                                };
                                // выполняем обработку других каналов
                                if(empty($status)){// если нет ошибок
                                    $guild = $app["fun"]["getCache"]("guild", $gid);
                                    if($guild){// если удалось получить данные
                                        // обрабатываем гильдию по остальным каналам
                                        $isUpdate |= $app["method"]["discord.guild"](
                                            array(// параметры для метода
                                                "guild" => $gid,
                                                "game" => $game
                                            ),
                                            array(// внутренние опции
                                                "nocontrol" => true,
                                                "leaderboard" => true,
                                                "schedule" => true
                                            ),
                                            $sign, $status
                                        );
                                    };
                                };
                            };
                        };
                        // выполняем регламентную рассылку уведомлений
                        if(empty($status) and !get_val($options, "nocontrol", false)){// если нужно выполнить
                            $limit = $app["val"]["eventNoticeLimit"];// ограничение на количество уведомлений
                            $items = $app["fun"]["sendEventsNotice"]($now, $limit, $status);// получаем списки изменений
                            foreach($items as $name => $list) if(count($list)) $isUpdate |= $mask[$name];
                        };
                        // переодически сохраняем базу данных событий
                        if(empty($status)){// если нет ошибок
                            if(!get_val($options, "nocontrol", false)){// если это прямой вызов
                                if($isUpdate & $mask["events"]){// если нужно сохранить
                                    if($events->save(true)){// если данные успешно сохранены
                                        $isUpdate &= ~ $mask["events"];// сбрасываем изменение
                                    }else $status = 307;// не удалось сохранить базу данных
                                };
                            };
                        };
                        // переодически сохраняем базу данных играков
                        if(empty($status)){// если нет ошибок
                            if(!get_val($options, "nocontrol", false)){// если это прямой вызов
                                if($isUpdate & $mask["players"]){// если нужно сохранить
                                    if($players->save(true)){// если данные успешно сохранены
                                        $isUpdate &= ~ $mask["players"];// сбрасываем изменение
                                    }else $status = 307;// не удалось сохранить базу данных
                                };
                            };
                        };
                        // переодически сохраняем базу данных сесии
                        if(empty($status)){// если нет ошибок
                            if($isUpdate & $mask["session"]){// если нужно сохранить
                                if($session->save(true)){// если данные успешно сохранены
                                    $isUpdate &= ~ $mask["session"];// сбрасываем изменение
                                }else $status = 307;// не удалось сохранить базу данных
                            };
                        };
                    };
                    // изменяем служебные переменные
                    $index++;// увеличиваем индекс итераций цикла
                }while(// множественное условие
                    empty($status)
                    and $index < $app["val"]["discordWebSocketLoop"]
                    and $now - $start < $app["val"]["appTimeLimit"]
                    and (!$websocket or websocket_check($websocket))
                );
            };
            // сохраняем базу данных событий
            if(isset($events) and !empty($events)){// если база данных загружена
                if(!get_val($options, "nocontrol", false)){// если это прямой вызов
                    if(empty($status) and $isUpdate & $mask["events"]){// если нужно выполнить
                        if($events->save(false)){// если данные успешно сохранены
                        }else $status = 307;// не удалось сохранить базу данных
                    }else $events->unlock();// разблокируем базу
                };
            };
            // сохраняем базу данных играков
            if(isset($players) and !empty($players)){// если база данных загружена
                if(!get_val($options, "nocontrol", false)){// если это прямой вызов
                    if(empty($status) and $isUpdate & $mask["players"]){// если нужно выполнить
                        if($players->save(false)){// если данные успешно сохранены
                        }else $status = 307;// не удалось сохранить базу данных
                    }else $players->unlock();// разблокируем базу
                };
            };
            // сохраняем базу данных сесии
            if(isset($session) and !empty($session)){// если база данных загружена
                if(empty($status) and $isUpdate & $mask["session"]){// если нужно выполнить
                    if($session->save(false)){// если данные успешно сохранены
                    }else $status = 307;// не удалось сохранить базу данных
                }else $session->unlock();// разблокируем базу
            };
            // работаем с файловыми кешами
            if($app["val"]["useFileCache"]){// если используются
                if(empty($status)){// если нет ошибок
                    // сохраняем данные в файловые кеши
                    foreach($app["cache"] as $name => $items){// пробигаемся по группам
                        for($i = count($items) - 1; $i > -1; $i--){// пробигаемся по элементам
                            $item = $items[$i];// получаем очередной элемент из списка элементов
                            if(!$app["fun"]["setFileCache"]($game, $name, $item["id"], $item)){// если не удалось
                                $app["fun"]["delFileCache"]($game, $name, $item["id"]);
                                $status = 311;// не удалось записать данные в файловый кеш
                            };
                        };
                    };
                }else{// если есть ошибки
                    // удаляем данные из файловых кешей
                    foreach($app["cache"] as $name => $items){// пробигаемся по группам
                        for($i = count($items) - 1; $i > -1; $i--){// пробигаемся по элементам
                            $item = $items[$i];// получаем очередной элемент из списка элементов
                            $app["fun"]["delFileCache"]($game, $name, $item["id"]);
                        };
                    };
                };
            };
            // возвращаем результат
            $app["fun"]["setDebug"](1, "exit", $status);// отладочная информация
            $result = $isUpdate;
            return $result;
        },
        "discord.guild" => function($params, $options, $sign, &$status){// обрабатываем гильдию
        //@param $params {array} - массив внешних не отфильтрованных значений
        //@param $options {array} - массив внутренних настроек
        //@param $sign {boolean|null} - успешность проверки подписи или null при её отсутствии
        //@param $status {integer} - целое число статуса выполнения
        //@return {true|false} - были ли изменения базы событий
            global $app; $result = null;

            $mask = array(// бит маска баз данных
                "session" => 1 << 0,
                "events" =>  1 << 1,
                "players" => 1 << 2
            );
            $isUpdate = 0;// битовая маска обновления баз
            $now = microtime(true);// текущее время
            // получаем очищенные значения параметров
            $game = $app["fun"]["getClearParam"]($params, "game", "string");
            $token = $app["fun"]["getClearParam"]($params, "token", "string");
            $guild = $app["fun"]["getClearParam"]($params, "guild", "string");
            $app["fun"]["setDebug"](3, "discord.guild", $guild);// отладочная информация
            // проверяем корректность указанных параметров
            if(empty($status)){// если нет ошибок
                if((!is_null($game) and !is_null($token) and !is_null($guild)) or get_val($options, "nocontrol", false)){// если указаны обязательные поля
                    if((!empty($game) and !empty($token) and !empty($guild)) or get_val($options, "nocontrol", false)){// если обязательные поля успешно отфильтрованы
                        if($token == $app["val"]["appToken"] or get_val($options, "nocontrol", false)){// если прошли проверку
                            $game = $app["val"]["game"] = mb_strtolower($game);// сохраняем информацию об игре
                        }else $status = 303;// переданные параметры не верны
                    }else $status = 302;// один из обязательных параметров передан в неверном формате
                }else $status = 301;// не передан один из обязательных параметров
            };
            // загружаем все необходимые базы данных
            if(empty($status)){// если нет ошибок
                $session = $app["fun"]["getStorage"]($game, "session", true);
                if(!empty($session)){// если удалось получить доступ к базе данных
                }else $status = 304;// не удалось загрузить одну из многих баз данных
            };
            if(empty($status)){// если нет ошибок
                $events = $app["fun"]["getStorage"]($game, "events", true);
                if(!empty($events)){// если удалось получить доступ к базе данных
                }else $status = 304;// не удалось загрузить одну из многих баз данных
            };
            if(empty($status)){// если нет ошибок
                $players = $app["fun"]["getStorage"]($game, "players", true);
                if(!empty($players)){// если удалось получить доступ к базе данных
                }else $status = 304;// не удалось загрузить одну из многих баз данных
            };
            // получаем информацию о гильдии
            if(empty($status)){// если нет ошибок
                $guild = $app["fun"]["getCache"]("guild", $guild);
                if($guild){// если удалось получить данные
                }else $status = 303;// переданные параметры не верны
            };
            // выполняем регламентные операции с базами данных
            if(empty($status) and !get_val($options, "nocontrol", false)){// если нужно выполнить
                $items = $app["fun"]["doRoutineStorage"]($now, $status);// получаем списки изменений
                foreach($items as $name => $list) if(count($list)) $isUpdate |= $mask[$name];
            };
            // выполняем обработку каналов в гильдии
            if(empty($status)){// если нет ошибок
                // вычисляем действующие ограничения
                $isFilterMode = false;// есть ли ограничение по режимам
                foreach(array("schedule", "leaderboard") as $mode){
                    $isFilterMode = ($isFilterMode or get_val($options, $mode, false));
                };
                // формируем список для дальнейшей обработки
                $list = array();// список для дальнейшей обработки
                for($i = count($guild["channels"]) - 1; $i > -1 ; $i--){
                    $channel = $guild["channels"][$i];// получаем очередной элимент
                    array_push($list, $channel);
                };
                // выполняем обработку сформированного списка
                for($i = 0, $iLen = count($list); $i < $iLen and empty($status); $i++){
                    $channel = $list[$i];// получаем очередной элимент из списка
                    $flag = ($channel and !$app["fun"]["getCache"]("channel", $channel["id"], $guild["id"], null));
                    if($flag) continue;// переходим к следующему элименту списка
                    $config = $app["fun"]["getChannelConfig"]($channel, $guild, null);
                    $flag = !get_val($options, $config["mode"], !$isFilterMode);
                    if($flag) continue;// переходим к следующему элименту списка
                    $isUpdate |= $app["method"]["discord.channel"](
                        array(// параметры для метода
                            "channel" => $channel ? $channel["id"] : null,
                            "guild" => $guild["id"],
                            "game" => $game
                        ),
                        array(// внутренние опции
                            "nocontrol" => true
                        ),
                        $sign, $status
                    );
                };
            };
            // сохраняем базу данных событий
            if(isset($events) and !empty($events)){// если база данных загружена
                if(!get_val($options, "nocontrol", false)){// если это прямой вызов
                    if(empty($status) and $isUpdate & $mask["events"]){// если нужно выполнить
                        if($events->save(false)){// если данные успешно сохранены
                        }else $status = 307;// не удалось сохранить базу данных
                    }else $events->unlock();// разблокируем базу
                };
            };
            // сохраняем базу данных игроков
            if(isset($players) and !empty($players)){// если база данных загружена
                if(!get_val($options, "nocontrol", false)){// если это прямой вызов
                    if(empty($status) and $isUpdate & $mask["players"]){// если нужно выполнить
                        if($players->save(false)){// если данные успешно сохранены
                        }else $status = 307;// не удалось сохранить базу данных
                    }else $players->unlock();// разблокируем базу
                };
            };
            // сохраняем базу данных сесии
            if(isset($session) and !empty($session)){// если база данных загружена
                if(!get_val($options, "nocontrol", false)){// если это прямой вызов
                    if(empty($status) and $isUpdate & $mask["session"]){// если нужно выполнить
                        if($session->save(false)){// если данные успешно сохранены
                        }else $status = 307;// не удалось сохранить базу данных
                    }else $session->unlock();// разблокируем базу
                };
            };
            // возвращаем результат
            $result = $isUpdate;
            return $result;
        },
        "discord.channel" => function($params, $options, $sign, &$status){// обрабатываем канал
        //@param $params {array} - массив внешних не отфильтрованных значений
        //@param $options {array} - массив внутренних настроек
        //@param $sign {boolean|null} - успешность проверки подписи или null при её отсутствии
        //@param $status {integer} - целое число статуса выполнения
        //@return {true|false} - были ли изменения базы событий
            global $app; $result = null;

            $mask = array(// бит маска баз данных
                "session" => 1 << 0,
                "events" =>  1 << 1,
                "players" => 1 << 2
            );
            $isUpdate = 0;// битовая маска обновления баз
            $now = microtime(true);// текущее время
            $isNeedProcessing = false;// требуется ли дальнейшая обработка
            // получаем очищенные значения параметров
            $game = $app["fun"]["getClearParam"]($params, "game", "string");
            $token = $app["fun"]["getClearParam"]($params, "token", "string");
            $guild = $app["fun"]["getClearParam"]($params, "guild", "string");
            $channel = $app["fun"]["getClearParam"]($params, "channel", "string");
            $app["fun"]["setDebug"](4, "discord.channel", $guild, $channel);// отладочная информация
            // проверяем корректность указанных параметров
            if(empty($status)){// если нет ошибок
                if((!is_null($game) and !is_null($token) and !is_null($guild) and !is_null($channel)) or get_val($options, "nocontrol", false)){// если указаны обязательные поля
                    if((!empty($game) and !empty($token) and !empty($guild) and !empty($channel)) or get_val($options, "nocontrol", false)){// если обязательные поля успешно отфильтрованы
                        if($token == $app["val"]["appToken"] or get_val($options, "nocontrol", false)){// если прошли проверку
                            $game = $app["val"]["game"] = mb_strtolower($game);// сохраняем информацию об игре
                        }else $status = 303;// переданные параметры не верны
                    }else $status = 302;// один из обязательных параметров передан в неверном формате
                }else $status = 301;// не передан один из обязательных параметров
            };
            // загружаем все необходимые базы данных
            if(empty($status)){// если нет ошибок
                $session = $app["fun"]["getStorage"]($game, "session", true);
                if(!empty($session)){// если удалось получить доступ к базе данных
                }else $status = 304;// не удалось загрузить одну из многих баз данных
            };
            if(empty($status)){// если нет ошибок
                $events = $app["fun"]["getStorage"]($game, "events", true);
                if(!empty($events)){// если удалось получить доступ к базе данных
                }else $status = 304;// не удалось загрузить одну из многих баз данных
            };
            if(empty($status)){// если нет ошибок
                $players = $app["fun"]["getStorage"]($game, "players", true);
                if(!empty($players)){// если удалось получить доступ к базе данных
                }else $status = 304;// не удалось загрузить одну из многих баз данных
            };
            if(empty($status)){// если нет ошибок
                $names = $app["fun"]["getStorage"](null, "names", false);
                if(!empty($names)){// если удалось получить доступ к базе данных
                }else $status = 304;// не удалось загрузить одну из многих баз данных
            };
            // получаем информацию о гильдии
            if(empty($status)){// если нет ошибок
                $guild = $app["fun"]["getCache"]("guild", $guild);
                if($guild){// если удалось получить данные
                }else $status = 303;// переданные параметры не верны
            };
            // получаем информацию о канале
            if(empty($status)){// если нет ошибок
                $channel = $app["fun"]["getCache"]("channel", $channel, $guild["id"], null);
                if($channel){// если удалось получить данные
                }else $status = 303;// переданные параметры не верны
            };
            // получаем конфигурацию канала
            if(empty($status)){// если нет ошибок
                $config = $app["fun"]["getChannelConfig"]($channel, $guild, null);
                if($config){// если удалось получить данные
                    $flag = false;// есть ли необходимые права для выполнения действия
                    if(!$flag) $permission = $app["fun"]["getPermission"]("guild", $session->get("bot", "value"), $channel, $guild);
                    $flag = ($flag or $config["mode"] and $config["game"] == $game and ($permission & $app["val"]["discordMainPermission"]) == $app["val"]["discordMainPermission"]);
                    $isNeedProcessing = ($isNeedProcessing or $flag);
                }else $status = 310;// не корректный внутренний запрос
            };
            // выполняем регламентные операции с базами данных
            if(empty($status) and !get_val($options, "nocontrol", false)){// если нужно выполнить
                $items = $app["fun"]["doRoutineStorage"]($now, $status);// получаем списки изменений
                foreach($items as $name => $list) if(count($list)) $isUpdate |= $mask[$name];
            };
            // выполняем обработку сообщений в канале
            if(empty($status) and $isNeedProcessing){// если нужно выполнить
                $items = null;// данные сообщений для публикации и обновления
                switch($config["mode"]){// поддерживаемые режимы
                    case "schedule":// сводное рассписание
                        // проверяем и исправляем эмодзи гильдии в событиях
                        for($i = 0, $iLen = $events->length; $i < $iLen and empty($status); $i++){
                            $eid = $events->key($i);// получаем ключевой идентификатор по индексу
                            $event = $events->get($eid);// получаем элемент по идентификатору
                            if($event["guild"] == $guild["id"] and !$event["hide"]){// если нужно проверить эмодзи
                                $units = $app["fun"]["fixCustomEmoji"]($eid, true, false, $status);// получаем списки изменений
                                foreach($units as $name => $list) if(count($list)) $isUpdate |= $mask[$name];
                            };
                        };
                        // получаем контент для сообщений
                        if(empty($status)){// если нет ошибок
                            if(is_null($items)) $items = $app["fun"]["getScheduleMessages"]($guild["id"], $config["language"], $config["timezone"], $config["filters"], $status);
                        };
                    case "leaderboard":// таблица лидеров
                        // получаем контент для сообщений
                        if(empty($status)){// если нет ошибок
                            if(is_null($items)) $items = $app["fun"]["getLeaderBoardMessages"]($guild["id"], $config["language"], $config["timezone"], $config["filters"], $status);
                        };
                        // создаём вспомогательные переменные
                        $count = 0;// счётчик уже опубликованных сообщений бота
                        $list = array();// массив сообщений для построения расписания
                        if(!is_null($items) and !count($items)) $items = array(array("content" => $names->get("empty", $config["language"]), "embed" => null));
                        // формируем список контентных сообщений бота и удаляем прочие сообщения
                        for($i = count($channel["messages"]) - 1; $i > -1 and empty($status); $i--){
                            $message = $channel["messages"][$i];// получаем очередной элемент
                            $flag = (!$config["flood"] and !$message["pinned"] and $message["author"]["id"] != $session->get("bot", "value"));
                            if($flag or $message["author"]["id"] == $session->get("bot", "value")){// если сообщение этого бота
                                if($flag or $message["type"]){// если это не контентное сообщение
                                    // удаляем сообщение
                                    $uri = "/channels/" . $channel["id"] . "/messages/" . $message["id"];
                                    $data = $app["fun"]["apiRequest"]("delete", $uri, null, $code);
                                    if(204 == $code or 404 == $code){// если запрос выполнен успешно
                                        $app["fun"]["delCache"]("message", $message["id"], $channel["id"], $guild["id"]);
                                    }else $status = 306;// не удалось получить корректный ответ от удаленного сервера
                                }else{// если это контентное сообщение
                                    // добавляем сообщение в список
                                    array_push($list, $message);// добавляем начало списка
                                    $count++;// увеличиваем счётчик сообщений
                                };
                            }else{// если сообщение не от этого бота
                                // добавляем пустой элемент в список
                                array_push($list, null);// добавляем в начало списка
                            };
                        };
                        // удаляем лишнии сообщения из сформированного списка 
                        $flag = false;// нужно ли удалить сообщение
                        $index = 0;// текущее значение проверенных не пустых сообщений
                        $after = count($items) > $count;// слещующее сообщение в списке
                        $time = $after ? $now : 0;// время более нового сообщения
                        for($i = count($list) - 1; $i > -1 and empty($status); $i--){
                            $message = $list[$i];// получаем очередной элемент
                            $flag = ($flag or empty($message) and !empty($after));
                            if(!empty($message)){// если присутствует сообщение для обработки
                                $flag = ($flag or $time - $message["timestamp"] > $app["val"]["discordMessageTime"]);
                                if($flag or $index >= count($items)){// если нужно удалить сообщение
                                    // удаляем сообщение
                                    $uri = "/channels/" . $channel["id"] . "/messages/" . $message["id"];
                                    $data = $app["fun"]["apiRequest"]("delete", $uri, null, $code);
                                    if(204 == $code or 404 == $code){// если запрос выполнен успешно
                                        $app["fun"]["delCache"]("message", $message["id"], $channel["id"], $guild["id"]);
                                        array_splice($list, $i, 1);
                                        $count--;// уменьшаем счётчик сообщений
                                    }else $status = 306;// не удалось получить корректный ответ от удаленного сервера
                                }else{// если не нужно удалять сообщение
                                    $time = $message["timestamp"];// копируем время
                                    $after = $message;// копируем элемент
                                    $index++;// увеличиваем индекс
                                };
                            }else{// если присутствует пустой элемент
                                $after = $message;// копируем элемент
                                array_splice($list, $i, 1);
                            };
                        };
                        // обрабатываем сообщения
                        $eid = null;// идентификатор события
                        $unit = array("channel" => $channel["id"], "guild" => $guild["id"]);
                        for($i = 0, $iLen = count($items); $i < $iLen and empty($status); $i++){
                            $data = $items[$i];// получаем очередные данные
                            $item = isset($list[$i]) ? $list[$i] : null;
                            if($item) $unit["message"] = $item["id"];
                            // отправляем или изменяем сообщение
                            if(empty($status)){// если нет ошибок
                                if(empty($item)){// если нужно опубликовать новое сообщение
                                    // отправляем новое сообщение
                                    $uri = "/channels/" . $unit["channel"] . "/messages";
                                    $data = $app["fun"]["apiRequest"]("post", $uri, $data, $code);
                                    if(200 == $code){// если запрос выполнен успешно
                                        $data["reactions"] = array();// приводим к единому виду
                                        $data["embed"] = get_val($data["embeds"], 0, false);// приводим к единому виду
                                        if(!$eid or $events->set($eid, "message", $data["id"])){// если данные успешно добавлены
                                            $item = $app["fun"]["setCache"]("message", $data, $unit["channel"], $unit["guild"], null);
                                            $unit["message"] = $data["id"];// фиксируем идентификатор сообщенияя
                                            if($eid) $isUpdate |= $mask["events"];// отмечаем изменение базы
                                        }else $status = 309;// не удалось записать данные в базу данных
                                    }else $status = 306;// не удалось получить корректный ответ от удаленного сервера
                                }else if($item["content"] != $data["content"]){// если нужно изменить старое сообщение
                                    // изменяем старое сообщение
                                    $uri = "/channels/" . $unit["channel"] . "/messages/" . $unit["message"];
                                    $data = $app["fun"]["apiRequest"]("patch", $uri, $data, $code);
                                    if(200 == $code){// если запрос выполнен успешно
                                        $data["embed"] = get_val($data["embeds"], 0, false);// приводим к единому виду
                                        $item = $app["fun"]["setCache"]("message", $data, $unit["channel"], $unit["guild"], null);
                                    }else $status = 306;// не удалось получить корректный ответ от удаленного сервера
                                };
                            };
                            // обрабатываем реакции
                            if(empty($status) and count($item["reactions"])){// если нужно выполнить
                                // удаляем все реакции
                                $uri = "/channels/" . $unit["channel"] . "/messages/" . $unit["message"] . "/reactions";
                                $data = $app["fun"]["apiRequest"]("delete", $uri, null, $code);
                                if(204 == $code or 404 == $code){// если запрос выполнен успешно
                                    for($j = count($item["reactions"]) - 1; $j > -1; $j--){// пробигаемся по реакциям
                                        $reaction = $item["reactions"][$j];// получаем очередной элемент
                                        $rid = array($reaction["user"]["id"], $reaction["emoji"]["name"], $reaction["emoji"]["id"]);
                                        $app["fun"]["delCache"]("reaction", $rid, $unit["message"], $unit["channel"], $unit["guild"], null);
                                    };
                                }else $status = 306;// не удалось получить корректный ответ от удаленного сервера
                            };
                        };
                        break;
                    case "record":// запись в события
                        // выполняем сортировку событий
                        if(empty($status)){// если нет ошибок
                            $items = $app["fun"]["sortEventsMessage"]($guild, $channel, $status);
                            foreach($items as $name => $list) if(count($list)) $isUpdate |= $mask[$name];
                        };
                        // обрабатываем сообщения
                        if(empty($status)){// если нет ошибок
                            // формируем массив связей сообщений и событий
                            $items = array();// вспомогательный ассоциативный массив
                            for($i = 0, $iLen = $events->length; $i < $iLen; $i++){
                                $eid = $events->key($i);// получаем ключевой идентификатор по индексу
                                $event = $events->get($eid);// получаем элемент по идентификатору
                                if(// множественное условие
                                    $event["channel"] == $channel["id"]
                                    and $event["guild"] == $guild["id"]
                                    and !$event["hide"]
                                ){// если нужно посчитать счётчик
                                    $mid = $event["message"];
                                    $items[$mid] = $eid;
                                };
                            };
                            // формируем список для дальнейшей обработки
                            $list = array();// список для дальнейшей обработки
                            for($i = count($channel["messages"]) - 1; $i > -1 ; $i--){
                                $message = $channel["messages"][$i];// получаем очередной элимент
                                $mid = $message["id"];// идентификатор сообщения
                                if(isset($items[$mid])) unset($items[$mid]);
                                array_push($list, $message);
                            };
                            // выполняем обработку сформированного списка
                            $counts = array();// счётчик участников в изменённых событиях
                            foreach($items as $mid => $eid) $counts[$eid] = true;
                            if(!count($list)) array_push($list, null);// пустое значение для разового вызова
                            for($i = 0, $iLen = count($list); $i < $iLen and empty($status); $i++){
                                $message = $list[$i];// получаем очередной элимент из списка
                                $flag = ($message and !$app["fun"]["getCache"]("message", $message["id"], $channel["id"], $guild["id"], null));
                                if($flag) continue;// переходим к следующему элименту списка
                                $isUpdate |= $app["method"]["discord.message"](
                                    array(// параметры для метода
                                        "message" => $message ? $message["id"] : null,
                                        "channel" => $channel["id"],
                                        "guild" => $guild["id"],
                                        "game" => $game
                                    ),
                                    array(// внутренние опции
                                        "nocontrol" => true,
                                        "counts" => !$i ? $counts : array() 
                                    ),
                                    $sign, $status
                                );
                            };
                        };
                        break;
                };
            };
            // сохраняем базу данных событий
            if(isset($events) and !empty($events)){// если база данных загружена
                if(!get_val($options, "nocontrol", false)){// если это прямой вызов
                    if(empty($status) and $isUpdate & $mask["events"]){// если нужно выполнить
                        if($events->save(false)){// если данные успешно сохранены
                        }else $status = 307;// не удалось сохранить базу данных
                    }else $events->unlock();// разблокируем базу
                };
            };
            // сохраняем базу данных игроков
            if(isset($players) and !empty($players)){// если база данных загружена
                if(!get_val($options, "nocontrol", false)){// если это прямой вызов
                    if(empty($status) and $isUpdate & $mask["players"]){// если нужно выполнить
                        if($players->save(false)){// если данные успешно сохранены
                        }else $status = 307;// не удалось сохранить базу данных
                    }else $players->unlock();// разблокируем базу
                };
            };
            // сохраняем базу данных сесии
            if(isset($session) and !empty($session)){// если база данных загружена
                if(!get_val($options, "nocontrol", false)){// если это прямой вызов
                    if(empty($status) and $isUpdate & $mask["session"]){// если нужно выполнить
                        if($session->save(false)){// если данные успешно сохранены
                        }else $status = 307;// не удалось сохранить базу данных
                    }else $session->unlock();// разблокируем базу
                };
            };
            // возвращаем результат
            $result = $isUpdate;
            return $result;
        },
        "discord.message" => function($params, $options, $sign, &$status){// обрабатываем сообщение
        //@param $params {array} - массив внешних не отфильтрованных значений
        //@param $options {array} - массив внутренних настроек
        //@param $sign {boolean|null} - успешность проверки подписи или null при её отсутствии
        //@param $status {integer} - целое число статуса выполнения
        //@return {true|false} - были ли изменения базы событий
            global $app; $result = null;

            $mask = array(// бит маска баз данных
                "session" => 1 << 0,
                "events" =>  1 << 1,
                "players" => 1 << 2
            );
            $filter = array();// события прошедшие фильтр
            $current = null;// подходящее событие
            $isUpdate = 0;// битовая маска обновления баз
            $error = 0;// код ошибки для обратной связи
            $now = microtime(true);// текущее время
            $isEdit = false;// выполняется команда редактирования
            $isSelectNext = false;// требуется ли выбрать ближайщее событие
            $isEveryOneUser = false;// это изменение для всех участников
            $isChangeEvent = false;// это изменение смещает или меняет событие
            $isScheduleChange = false;// изменилось ли сводное расписание
            $isLeaderBoardChange = false;// изменилось ли таблица лидеров
            $isNeedProcessing = false;// требуется ли дальнейшая обработка
            $isNeedDelete = false;// удалить ли элимент переданный на обработку
            $counts = get_val($options, "counts", array());// счётчик участников
            // получаем очищенные значения параметров
            $game = $app["fun"]["getClearParam"]($params, "game", "string");
            $token = $app["fun"]["getClearParam"]($params, "token", "string");
            $guild = $app["fun"]["getClearParam"]($params, "guild", "string");
            $user = $app["fun"]["getClearParam"]($params, "user", "string");
            $channel = $app["fun"]["getClearParam"]($params, "channel", "string");
            $message = $app["fun"]["getClearParam"]($params, "message", "string");
            $app["fun"]["setDebug"](5, "discord.message", $guild ? $guild : $user, $channel, $message, $user ? "user" : null);// отладочная информация
            // проверяем корректность указанных параметров
            if(empty($status)){// если нет ошибок
                if((!is_null($game) and !is_null($token) and (!is_null($guild) xor !is_null($user)) and !is_null($channel) and !is_null($message)) or get_val($options, "nocontrol", false)){// если указаны обязательные поля
                    if((!empty($game) and !empty($token) and (!empty($guild) xor !empty($user)) and !empty($channel) and !empty($message)) or get_val($options, "nocontrol", false)){// если обязательные поля успешно отфильтрованы
                        if($token == $app["val"]["appToken"] or get_val($options, "nocontrol", false)){// если прошли проверку
                            $game = $app["val"]["game"] = mb_strtolower($game);// сохраняем информацию об игре
                        }else $status = 303;// переданные параметры не верны
                    }else $status = 302;// один из обязательных параметров передан в неверном формате
                }else $status = 301;// не передан один из обязательных параметров
            };
            // загружаем все необходимые базы данных
            if(empty($status)){// если нет ошибок
                $session = $app["fun"]["getStorage"]($game, "session", true);
                if(!empty($session)){// если удалось получить доступ к базе данных
                }else $status = 304;// не удалось загрузить одну из многих баз данных
            };
            if(empty($status)){// если нет ошибок
                $events = $app["fun"]["getStorage"]($game, "events", true);
                if(!empty($events)){// если удалось получить доступ к базе данных
                }else $status = 304;// не удалось загрузить одну из многих баз данных
            };
            if(empty($status)){// если нет ошибок
                $players = $app["fun"]["getStorage"]($game, "players", true);
                if(!empty($players)){// если удалось получить доступ к базе данных
                }else $status = 304;// не удалось загрузить одну из многих баз данных
            };
            if(empty($status)){// если нет ошибок
                $raids = $app["fun"]["getStorage"]($game, "raids", false);
                if(!empty($raids)){// если удалось получить доступ к базе данных
                }else $status = 304;// не удалось загрузить одну из многих баз данных
            };
            if(empty($status)){// если нет ошибок
                $chapters = $app["fun"]["getStorage"]($game, "chapters", false);
                if(!empty($chapters)){// если удалось получить доступ к базе данных
                }else $status = 304;// не удалось загрузить одну из многих баз данных
            };
            if(empty($status)){// если нет ошибок
                $types = $app["fun"]["getStorage"]($game, "types", false);
                if(!empty($types)){// если удалось получить доступ к базе данных
                }else $status = 304;// не удалось загрузить одну из многих баз данных
            };
            if(empty($status)){// если нет ошибок
                $roles = $app["fun"]["getStorage"]($game, "roles", false);
                if(!empty($roles)){// если удалось получить доступ к базе данных
                }else $status = 304;// не удалось загрузить одну из многих баз данных
            };
            if(empty($status)){// если нет ошибок
                $names = $app["fun"]["getStorage"](null, "names", false);
                if(!empty($names)){// если удалось получить доступ к базе данных
                }else $status = 304;// не удалось загрузить одну из многих баз данных
            };
            if(empty($status)){// если нет ошибок
                $actions = $app["fun"]["getStorage"](null, "actions", false);
                if(!empty($actions)){// если удалось получить доступ к базе данных
                }else $status = 304;// не удалось загрузить одну из многих баз данных
            };
            if(empty($status)){// если нет ошибок
                $additions = $app["fun"]["getStorage"](null, "additions", false);
                if(!empty($additions)){// если удалось получить доступ к базе данных
                }else $status = 304;// не удалось загрузить одну из многих баз данных
            };
            if(empty($status)){// если нет ошибок
                $dates = $app["fun"]["getStorage"](null, "dates", false);
                if(!empty($dates)){// если удалось получить доступ к базе данных
                }else $status = 304;// не удалось загрузить одну из многих баз данных
            };
            if(empty($status)){// если нет ошибок
                $months = $app["fun"]["getStorage"](null, "months", false);
                if(!empty($months)){// если удалось получить доступ к базе данных
                }else $status = 304;// не удалось загрузить одну из многих баз данных
            };
            // получаем информацию о гильдии
            if(empty($status) and $guild){// если нужно выполнить
                $guild = $app["fun"]["getCache"]("guild", $guild);
                if($guild){// если удалось получить данные
                }else $status = 303;// переданные параметры не верны
            };
            // получаем информацию о пользователе
            if(empty($status) and $user){// если нужно выполнить
                $user = $app["fun"]["getCache"]("user", $user);
                if($user){// если удалось получить данные
                }else $status = 303;// переданные параметры не верны
            };
            // получаем информацию о канале
            if(empty($status)){// если нет ошибок
                if($guild) $channel = $app["fun"]["getCache"]("channel", $channel, $guild["id"], null);
                if($user) $channel = $app["fun"]["getCache"]("channel", $channel, null, $user["id"]);
                if($channel){// если удалось получить данные
                }else $status = 303;// переданные параметры не верны
            };
            // получаем конфигурацию канала
            if(empty($status)){// если нет ошибок
                if($guild) $config = $app["fun"]["getChannelConfig"]($channel, $guild, null);
                if($user) $config = $app["fun"]["getChannelConfig"]($channel, null, $user);
                if($config){// если удалось получить данные
                    $flag = "direct" == $config["mode"];// есть ли необходимые права для выполнения действия
                    if(!$flag) $permission = $app["fun"]["getPermission"]("guild", $session->get("bot", "value"), $channel, $guild);
                    $flag = ($flag or $config["mode"] and $config["game"] == $game and ($permission & $app["val"]["discordMainPermission"]) == $app["val"]["discordMainPermission"]);
                    $isNeedProcessing = ($isNeedProcessing or $message and $flag);
                }else $status = 310;// не корректный внутренний запрос
            };
            // получаем информацию о сообщении
            if(empty($status) and $message){// если нужно выполнить
                if($guild) $message = $app["fun"]["getCache"]("message", $message, $channel["id"], $guild["id"], null);
                if($user) $message = $app["fun"]["getCache"]("message", $message, $channel["id"], null, $user["id"]);
                if($message){// если удалось получить данные
                    $flag = (!$message["pinned"] and $message["author"]["id"] != $session->get("bot", "value"));
                    $isNeedProcessing = ($isNeedProcessing and $flag);
                }else $status = 303;// переданные параметры не верны
            };
            // выполняем регламентные операции с базами данных
            if(empty($status) and !get_val($options, "nocontrol", false)){// если нужно выполнить
                $items = $app["fun"]["doRoutineStorage"]($now, $status);// получаем списки изменений
                foreach($items as $name => $list) if(count($list)) $isUpdate |= $mask[$name];
            };
            // добавляем идентификатор события в счётчик по сообщению
            if(// множественное условие
                empty($status) and $guild and "record" == $config["mode"]
                and $message and $message["author"]["id"] == $session->get("bot", "value")
            ){// если нужно выполнить
                $flag = !$config["history"];// удалить ли элимент переданный на обработку
                for($i = 0, $iLen = $events->length; $i < $iLen; $i++){
                    $eid = $events->key($i);// получаем ключевой идентификатор по индексу
                    $unit = $events->get($eid);// получаем элемент по идентификатору
                    if(// множественное условие
                        $unit["channel"] == $channel["id"]
                        and $unit["guild"] == $guild["id"]
                        and $unit["message"] == $message["id"]
                    ){// если нужно посчитать счётчик
                        if($config["history"] or !$unit["hide"]) $flag = false;
                        if(!$flag and !isset($counts[$eid])) $counts[$eid] = true;
                    };
                };
                if($flag) $isNeedDelete = true;
            };
            // получаем команду из сообщения
            if(empty($status) and $isNeedProcessing){// если нужно выполнить
                if(!$message["type"] and in_array($config["mode"], array("record", "direct"))){// если это контентное сообщение
                    $command = $app["fun"]["getCommand"]($message["content"], $config["language"], $config["timezone"], $message["timestamp"], $status);
                    if(!$command["action"] and !$config["flood"] or $command["action"]) $isNeedDelete = true;
                    if(!$command["action"]) $isNeedProcessing = false;
                }else{// если это не контентное сообщение
                    if($message["type"] or !$config["flood"]) $isNeedDelete = true;
                    $isNeedProcessing = false;
                };
            };
            // пробуем определить событие из команды
            if(empty($status) and $isNeedProcessing and $guild){// если нужно выполнить
                $items = array();// список событий
                $check = array();// ассоциативный массив проверки сообщений
                // формируем вспомогательный объект проверки сообщений
                for($i = count($channel["messages"]) - 1; $i > -1 ; $i--){
                    $item = $channel["messages"][$i];// получаем очередной элимент
                    $mid = $item["id"];// получаем идентификатор
                    $check[$mid] = true;
                };
                // формируем список событий
                for($i = 0, $iLen = $events->length; $i < $iLen; $i++){
                    $eid = $events->key($i);// получаем ключевой идентификатор по индексу
                    $unit = $events->get($eid);// получаем элемент по идентификатору
                    if(// множественное условие
                        $unit["channel"] == $channel["id"]
                        and $unit["guild"] == $guild["id"]
                        and $unit["time"] >= $message["timestamp"] - $app["val"]["eventTimeHide"]
                    ){// если нужно посчитать счётчик
                        $mid = $unit["message"];
                        $flag = ($mid and !isset($check[$mid]));
                        if($flag) $unit["message"] = "";
                        array_push($items, $unit);
                    };
                };
                // сортируем список событий
                usort($items, function($a, $b){// сортировка
                    $value = 0;// начальное значение
                    if(!$value and !$a["message"] and $b["message"]) $value = 1;
                    if(!$value and $a["message"] and !$b["message"]) $value = -1;
                    if(!$value and $a["message"] != $b["message"]) $value = $a["message"] > $b["message"] ? 1 : -1;
                    if(!$value and $a["id"] != $b["id"]) $value = $a["id"] > $b["id"] ? 1 : -1;
                    // возвращаем результат
                    return $value;
                });
                // обрабатываем список событий
                $next = null;// ближайшее событие
                for($i = 0, $iLen = count($items); $i < $iLen; $i++){
                    $unit = $items[$i];// получаем очередной элемент
                    // фильтруем элименты
                    if(// множественное условие
                        (!empty($command["raid"]) ? $command["raid"] == $unit["raid"] : true)
                        and (!empty($command["time"]) ? $app["fun"]["dateFormat"]("H:i", $command["time"], $config["timezone"]) == $app["fun"]["dateFormat"]("H:i", $unit["time"], $config["timezone"]) : true)
                        and (!empty($command["date"]) ? $app["fun"]["dateFormat"]("d.m.Y", $command["date"], $config["timezone"]) == $app["fun"]["dateFormat"]("d.m.Y", $unit["time"], $config["timezone"]) : true)
                    ){// если элимент соответствует фильтру
                        $id = $unit["id"];// получаем идентификатор
                        if(is_null($current)) $current = $unit;
                        else $current = false;
                        $filter[$id] = true;
                    };
                    // определяем ближайшее событие
                    if(// множественное условие
                        $unit["time"] >= $message["timestamp"] + $app["val"]["eventTimeClose"]
                        and (empty($next) or $unit["time"] < $next["time"])
                    ){// если ближайщее событие
                        $next = $unit;
                    };
                };
                // получаем подходящее событие
                if(!empty($command["index"])){// если задан номер записи
                    $i = $command["index"] - 1;// позиция записи в списке
                    if(isset($items[$i])){// если элимент существует
                        $unit = $items[$i];// получаем очередной элемент
                        $current = $unit;// задаём подходящее событие
                    }else $current = false;// сбрасываем подходящее событие
                };
                // определяем дополнительные флаги
                $isSelectNext = (empty($command["index"]) and empty($command["raid"]) and empty($command["date"]) and empty($command["time"]) and count($command["roles"]));
            };
            // обрабатываем команду
            if(empty($status) and $isNeedProcessing){// если нужно выполнить
                switch($command["action"]){// поддержмваемые команды
                    case "*":// изменить запись
                        $isEdit = true;
                        // проверяем поддержку команды
                        if(empty($error)){// если нет проблем
                            if($guild){// если проверка пройдена
                            }else $error = 18;
                        };
                        // корректируем подходящее событие
                        if(empty($error)){// если нет проблем
                            $flag = empty($command["index"]);
                            if($flag) $current = $next;// задаём подходящее событие
                        };
                        // проверяем что определено событие
                        if(empty($error)){// если нет проблем
                            if(!empty($current)){// если проверка пройдена
                            }else $error = $flag ? 12 : 52;
                        };
                    case "+":// добавить запись
                        // проверяем поддержку команды
                        if(empty($error) and !$isEdit){// если нужно выполнить
                            if($guild){// если проверка пройдена
                            }else $error = 18;
                        };
                        // корректируем подходящее событие
                        if(empty($error) and !$isEdit){// если нужно выполнить
                            $flag = $isSelectNext;
                            if($flag) $current = $next;// задаём подходящее событие
                        };
                        // получаем данные из подходящего события
                        if(empty($error) and !$isEdit and $current){// если нужно выполнить
                            if(get_val($filter, $current["id"], false)){// если фильтр пройден
                                $command["raid"] = $current["raid"];
                                $command["time"] = $current["time"];
                            }else $current = false;
                        };
                        // проверяем наложение фильтрующих параметров
                        if(empty($error) and !$isEdit){// если нужно выполнить
                            $flag = !empty($command["index"]);
                            if(false !== $current){// если проверка пройдена
                            }else $error = $flag ? 53 : $error;
                        };
                        // корректируем дату
                        if(empty($error)){// если нет проблем
                            if(empty($command["date"]) and $command["time"] > 0){// если нужно выполнить
                                $time = !empty($current) ? $current["time"] : $command["time"];
                                if($time > 0) $value = $app["fun"]["dateFormat"]("d.m.Y", $time, $config["timezone"]);
                                if($time > 0) $command["date"] = $app["fun"]["dateCreate"]($value, $config["timezone"]);
                            };
                        };
                        // корректируем время
                        if(empty($error)){// если нет проблем
                            if(!empty($command["date"]) and $command["time"] >= 0){// если нужно выполнить
                                $time = (!$command["time"] and !empty($current)) ? $current["time"] : $command["time"];
                                if($time > 0) $value = $app["fun"]["dateFormat"]("d.m.Y", $command["date"], $config["timezone"]);
                                if($time > 0) $value .= " " . $app["fun"]["dateFormat"]("H:i", $time, $config["timezone"]);
                                if($time > 0) $command["time"] = $app["fun"]["dateCreate"]($value, $config["timezone"]);
                            };
                        };
                        // корректируем описание и комментарий
                        if(empty($error) and !$isEdit and !$current){// если нужно выполнить
                            $flag = (mb_strlen($command["comment"]) and !mb_strlen($command["description"]));
                            if($flag) $command["description"] = $command["comment"];
                            if($flag) $command["comment"] = "";// сбрасываем комментарий
                        };
                        // проверяем что указана роль
                        if(empty($error)){// если нет проблем
                            if(count($command["roles"])){// если проверка пройдена
                            }else $error = $isEdit ? $error : 13;
                        };
                        // проверяем ограничения на комментарий
                        if(empty($error)){// если нет проблем
                            $value = $app["val"]["eventCommentLength"];
                            if(mb_strlen($command["comment"]) <= $value){// если проверка пройдена
                            }else $error = 54;
                        };
                        // проверяем ограничения на описание
                        if(empty($error)){// если нет проблем
                            $value = $app["val"]["eventDescriptionLength"];
                            if(mb_strlen($command["description"]) <= $value){// если проверка пройдена
                            }else $error = 57;
                        };
                        // проверяем что указана дата
                        if(empty($error)){// если нет проблем
                            if(!empty($command["date"])){// если проверка пройдена
                            }else $error = $isEdit ? $error : 15;
                        };
                        // проверяем что указано время
                        if(empty($error)){// если нет проблем
                            if(!empty($command["time"])){// если проверка пройдена
                            }else $error = $isEdit ? $error : 16;
                        };
                        // проверяем корректность указания даты
                        if(empty($error)){// если нет проблем
                            if($command["date"] > 0){// если проверка пройдена
                            }else $error = $isEdit ? $error : 55;
                        };
                        // проверяем корректность указания времени
                        if(empty($error)){// если нет проблем
                            $time = null;// сбрасываем значение
                            if($command["time"] > 0){// если проверка пройдена
                                $time = $command["time"];
                            }else if($isEdit and !empty($current["time"])){// если проверка пройдена
                                $time = $current["time"];
                            }else $error = 56;
                        };
                        // проверяем что указан рейд
                        if(empty($error)){// если нет проблем
                            $raid = null;// сбрасываем значение
                            if(!empty($command["raid"])){// если проверка пройдена
                                $raid = $raids->get($command["raid"]);
                            }else if($isEdit and !empty($current["raid"])){// если проверка пройдена
                                $raid = $raids->get($current["raid"]);
                            }else $error = 17;
                        };
                        // проверяем ограничения по времени записи
                        if(empty($error) and $time){// если нужно выполнить
                            $flag = false;// есть ли необходимые права для выполнения действия
                            if(!$flag) $permission = $app["fun"]["getPermission"]("guild", $message["author"]["id"], $channel, $guild);
                            $flag = ($flag or ($permission & $app["val"]["discordUserPermission"]) == $app["val"]["discordUserPermission"]);
                            // выполняем проверки
                            if($time < $message["timestamp"] - $app["val"]["eventTimeHide"]) $error = 22;
                            else if($time > $message["timestamp"] + $app["val"]["eventTimeAdd"]) $error = 23;
                            else if($time < $message["timestamp"] + $app["val"]["eventTimeClose"] and !$flag) $error = 24;
                        };
                        // проверяем возможность использовать ролей в рейде
                        if(empty($error)){// если нет проблем
                            for($i = 0, $iLen = count($command["roles"]); $i < $iLen and !$error; $i++){
                                $key = $command["roles"][$i];// получаем очередное ключевое значение
                                $limit = $raid[$key];// получаем лимит для заданной роли
                                if($limit > -1){// если проверка пройдена
                                }else $error = 59;
                            };
                        };
                        // проверяем ограничивающий фильтр по типу события
                        if(empty($error) and count($config["filters"]["types"])){// если нужно выполнить
                            if(in_array($raid["type"], $config["filters"]["types"])){// если проверка пройдена
                            }else $error = 60;
                        };
                        // проверяем записываемых пользователей
                        if(empty($error) and !$isEdit){// если нужно выполнить
                            for($i = 0, $iLen = count($command["users"]); ($i < $iLen or !$i) and !$error; $i++){
                                $uid = $iLen ? $command["users"][$i] : $message["author"]["id"];
                                $member = $app["fun"]["getCache"]("member", $uid, $guild["id"]);
                                $flag = ($member and !$member["user"]["bot"]);
                                if($flag){// если проверка пройдена
                                }else $error = 61;
                            };
                        };
                        // определяем дополнительные флаги
                        if(empty($error)){// если нет проблем
                            $isChangeEvent = (!empty($command["raid"]) and $current and $current["raid"] != $raid["key"] or !empty($command["time"]) and $current and $current["time"] != $time);
                            $isEveryOneUser = ($isEdit and !count($command["roles"]) and !count($command["users"]) and !mb_strlen($command["comment"]));
                        };
                        // проверяем права на создание записи от имени других пользователей
                        if(empty($error)){// если нет проблем
                            for($i = 0, $iLen = count($command["users"]); ($i < $iLen or !$i) and !$error; $i++){
                                $uid = $iLen ? $command["users"][$i] : $message["author"]["id"];
                                $flag = false;// есть ли необходимые права для выполнения действия
                                $flag = ($flag or $current and $current["leader"] == $message["author"]["id"]);
                                $flag = ($flag or !$isEveryOneUser and $uid == $message["author"]["id"]);// пользователь автор сообщения
                                if(!$flag) $permission = $app["fun"]["getPermission"]("guild", $message["author"]["id"], $channel, $guild);
                                $flag = ($flag or ($permission & $app["val"]["discordUserPermission"]) == $app["val"]["discordUserPermission"]);
                                if($flag){// если проверка пройдена
                                }else $error = $isEdit ? 63 : 62;
                            };
                        };
                        // проверяем права на создание первой записи
                        if(empty($error) and !$isEdit and !$current){// если нужно выполнить
                            $flag = false;// есть ли необходимые права для выполнения действия
                            if(!$flag) $permission = $app["fun"]["getPermission"]("guild", $message["author"]["id"], $channel, $guild);
                            $flag = ($flag or ($permission & $app["val"]["discordCreatePermission"]) == $app["val"]["discordCreatePermission"]);
                            if($flag){// если проверка пройдена
                                $flag = $time < $now + $app["val"]["eventTimeClose"];
                                if($flag) $isLeaderBoardChange = true;
                                $isScheduleChange = true;
                            }else $error = 64;
                        };
                        // проверяем права на перенос события
                        if(empty($error) and $isEdit and $isChangeEvent){// если нужно выполнить
                            $flag = false;// есть ли необходимые права для выполнения действия
                            $flag = ($flag or $current and $current["leader"] == $message["author"]["id"]);
                            if(!$flag) $permission = $app["fun"]["getPermission"]("guild", $message["author"]["id"], $channel, $guild);
                            $flag = ($flag or ($permission & $app["val"]["discordUserPermission"]) == $app["val"]["discordUserPermission"]);
                            if($flag){// если проверка пройдена
                                if($isChangeEvent) $isLeaderBoardChange = true;
                                if($isChangeEvent) $isScheduleChange = true;
                            }else $error = 65;
                        };
                        // проверяем возможность переноса события
                        if(empty($error) and $isEdit and $isChangeEvent){// если нужно выполнить
                            $flag = false;// найдено ли конкурирующее событие
                            for($i = 0, $iLen = $events->length; $i < $iLen and !$flag; $i++){
                                $eid = $events->key($i);// получаем ключевой идентификатор по индексу
                                $unit = $events->get($eid);// получаем элемент по идентификатору
                                if(// множественное условие
                                    $unit["channel"] == $channel["id"]
                                    and $unit["guild"] == $guild["id"]
                                    and $unit["raid"] == $raid["key"]
                                    and $unit["id"] != $current["id"]
                                    and $unit["time"] == $time
                                ){// если есть конкурирующее событие
                                    $flag = true;
                                };
                            };
                            if(!$flag){// если проверка пройдена
                            }else $error = 66;
                        };
                        // проверяем запись участников в событие
                        $items = array();// ассоциативный список участников
                        if(empty($error) and $current){// если нужно выполнить
                            // формируем список идентификаторов пользователей
                            for($i = 0, $iLen = $players->length; $i < $iLen; $i++){
                                $id = $players->key($i);// получаем ключевой идентификатор по индексу
                                $item = $players->get($id);// получаем элемент по идентификатору
                                $uid = $item["user"];// идентификатор пользователя
                                $flag = $item["event"] == $current["id"];
                                if($flag) $items[$uid] = $item;
                            };
                            // выполняем проверку
                            $flag = true;// пройдена ли проверка
                            $list = count($command["users"]) ? $command["users"] : array($message["author"]["id"]);
                            for($i = 0, $iLen = count($list); $i < $iLen and $flag; $i++){
                                $uid = $list[$i];// идентификатор пользователя
                                $flag = isset($items[$uid]) ? $isEdit : (!$isEdit or $isEveryOneUser);
                            };
                            if($flag){// если проверка пройдена
                            }else $error = $isEdit ? 71 : 67;
                        };
                        // переносим параметры команды
                        if(empty($error)){// если нет проблем
                            $event = array();// данные для обновления события
                            $player = array();// данные для обновления записей участников
                            $key = "time"; $value = $command[$key]; if($value and (!$current or $current[$key] != $value)) $event[$key] = $value;
                            $key = "hide"; $value = $command["time"] < $now - $app["val"]["eventTimeHide"]; if($command["time"] and (!$current or $current[$key] != $value)) $event[$key] = $value;
                            $key = "close"; $value = $command["time"] < $now + $app["val"]["eventTimeClose"]; if($command["time"] and (!$current or $current[$key] != $value)) $event[$key] = $value;
                            $key = "raid"; $value = $command[$key]; if(mb_strlen($value) and (!$current or $current[$key] != $value)) $event[$key] = $value;
                            $key = "description"; $value = mb_ucfirst(str_replace($app["val"]["lineDelim"], "<br>", $command[$key])); if(mb_strlen($value) and (!$current or $current[$key] != $value)){ $event[$key] = $value; $isScheduleChange = true; };
                            $key = "comment"; $value = $command[$key]; if(mb_strlen($value)) $player[$key] = $value;
                            $key = "role"; $value = count($command["roles"]); if($value) $player[$key] = "";
                        };
                        // обрабатываем дополнительные опции
                        if(empty($error)){// если нет проблем
                            $permission = $app["fun"]["getPermission"]("guild", $message["author"]["id"], $channel, $guild);
                            for($i = 0, $iLen = count($command["additions"]); $i < $iLen and !$error; $i++){
                                $key = $command["additions"][$i];// получаем очередное значение
                                $leader = isset($event["leader"]) ? $event["leader"] : ($current ? $current["leader"] : "");
                                $value = 24*60*60;// вспомогательное начальное значение
                                $flag = false;// есть ли необходимые права для выполнения действия
                                switch($key){// поддержмваемые опции
                                    case "once":// однократно
                                        $value = 0;// сбрасываем повторение
                                        $flag = ($flag or $leader == $message["author"]["id"]);
                                    case "weekly"://еженедельно
                                        $value = 7 * $value;
                                    case "daily"://ежедневно
                                        $flag = ($flag or ($permission & $app["val"]["discordCreatePermission"]) == $app["val"]["discordCreatePermission"]);
                                        if($flag){// если проверка пройдена
                                            $flag = (!$value or !$current or $leader);
                                            if($flag){// если проверка пройдена
                                                $event["repeat"] = $value;
                                            }else $error = 85;
                                        }else $error = $isEdit ? 25 : 69;
                                        break;
                                    case "leader":// лидер
                                        $value = count($command["users"]) ? end($command["users"]) : $message["author"]["id"];
                                        $flag = ($flag or $leader == $message["author"]["id"] or !$leader);
                                        $flag = ($flag or ($permission & $app["val"]["discordUserPermission"]) == $app["val"]["discordUserPermission"]);
                                        if($flag){// если проверка пройдена
                                            $isScheduleChange = true;
                                            $event["leader"] = $value;
                                        }else $error = 70;
                                        break;
                                    case "member":// участник
                                        $value = "";// сбрасываем на пустое значение
                                        $list = count($command["users"]) ? $command["users"] : array($message["author"]["id"]);
                                        $flag = ($flag or $leader == $message["author"]["id"] or !in_array($leader, $list));
                                        $flag = ($flag or ($permission & $app["val"]["discordUserPermission"]) == $app["val"]["discordUserPermission"]);
                                        if($flag){// если проверка пройдена
                                            $flag = (!$current or in_array($leader, $list));
                                            if($flag) $isScheduleChange = true;
                                            if($flag) $event["leader"] = $value;
                                            if($flag) $event["repeat"] = 0;
                                        }else $error = 70;
                                        break;
                                    case "reject":// исключён
                                        $value = 0;// сбрасываем согласование
                                    case "accept":// принят
                                        $flag = ($flag or $leader == $message["author"]["id"]);
                                        $flag = ($flag or ($permission & $app["val"]["discordUserPermission"]) == $app["val"]["discordUserPermission"]);
                                        if($flag){// если проверка пройдена
                                            $player["accept"] = !!$value;
                                        }else $error = 72;
                                        break;
                                    case "group":// группа
                                        $value = 0;// сбрасываем резерв
                                    case "reserve":// резерв
                                        for($j = 0, $jLen = count($command["users"]); $j < $jLen and !$flag; $j++) if($command["users"][$j] != $message["author"]["id"]) $flag = true;
                                        $flag = (!$flag and !$isEveryOneUser);// проверяем касается ли это изменение всех пользователей или только автора сообщения
                                        $flag = ($flag or $leader == $message["author"]["id"]);
                                        $flag = ($flag or ($permission & $app["val"]["discordUserPermission"]) == $app["val"]["discordUserPermission"]);
                                        if($flag){// если проверка пройдена
                                            $flag = $time < $now + $app["val"]["eventTimeClose"];
                                            if($flag) $isLeaderBoardChange = true;
                                            $isEveryOneUser = false;// сбрасываем общий контекст
                                            $player["reserve"] = !!$value;
                                        }else $error = 72;
                                        break;
                                    case "empty":// пусто
                                        $value = "";// сбрасываем на пустое значение
                                        // сбрасываем комментарий пользователей
                                        $uid = $message["author"]["id"];
                                        $item = isset($items[$uid]) ? $items[$uid] : null;
                                        $flag = ($isEveryOneUser and $item and mb_strlen($item["comment"]));
                                        if($flag) $isEveryOneUser = false;// сбрасываем общий контекст
                                        $flag = ($flag or $leader == $message["author"]["id"]);
                                        $flag = ($flag or ($permission & $app["val"]["discordUserPermission"]) == $app["val"]["discordUserPermission"]);
                                        if($flag and !$isEveryOneUser and !mb_strlen($command["comment"])) $player["comment"] = $value;
                                        // сбрасываем описание события
                                        $flag = false;// есть ли необходимые права для выполнения действия
                                        $flag = ($flag or $leader == $message["author"]["id"]);
                                        $flag = ($flag or ($permission & $app["val"]["discordUserPermission"]) == $app["val"]["discordUserPermission"]);
                                        if($flag and $isEveryOneUser and !mb_strlen($command["description"])) $isScheduleChange = !$event["description"] = $value;
                                        break;
                                    default:// не известная опция
                                        $error = 68;
                                };
                            };
                        };
                        // изменяем данные в базе данных событий
                        if(empty($error) and count($event) and $isEdit){// если нужно выполнить
                            if(empty($status)){// если нет ошибок
                                $eid = $current["id"];// получаем идентификатор события
                                if($events->set($eid, null, $event)){// если данные успешно изменены
                                    $event[$events->primary] = $eid;
                                    if(!isset($counts[$eid])) $counts[$eid] = true;
                                    $isUpdate |= $mask["events"];// отмечаем изменение базы
                                }else $status = 309;// не удалось записать данные в базу данных
                            };
                        };
                        // изменяем данные в базе данных игроков
                        if(empty($error) and count($player) and $isEdit){// если нужно выполнить
                            $index = 0;// индек позиции элемента
                            $list = count($command["users"]) ? $command["users"] : array($message["author"]["id"]);
                            $count = count($command["roles"]);// вспомогательный счётчик ролей в команде для чередования
                            for($i = $players->length - 1; $i > - 1 and empty($status); $i--){
                                $eid = $current["id"];// получаем идентификатор события
                                $pid = $players->key($i);// получаем ключевой идентификатор по индексу
                                $item = $players->get($pid);// получаем элемент по идентификатору
                                if(// множественное условие
                                    ($isEveryOneUser or in_array($item["user"], $list))
                                    and $item["event"] == $current["id"]
                                ){// если нужно изменить запись
                                    if(!$isEveryOneUser) for($index = count($list) - 1; $index > -1; $index--) if($item["user"] == $list[$index]) break;
                                    if($count) $player["role"] = $command["roles"][$index % $count];// выполняем чередование
                                    if($players->set($pid, null, $player)){// если данные успешно изменены
                                        if(!isset($counts[$eid])) $counts[$eid] = true;
                                        $isUpdate |= $mask["players"];// отмечаем изменение базы
                                        $index++;// увиличиваем счётчик
                                    }else $status = 309;// не удалось записать данные в базу данных
                                };
                            };
                            if($index){// если изменены записи
                            }else $error = $isEdit ? 21 : $error;
                        };
                        // добавляем данные в базу данных событий
                        if(empty($error) and !$isEdit and !$current){// если нужно выполнить
                            if(empty($status)){// если нет ошибок
                                $list = count($command["users"]) ? $command["users"] : array($message["author"]["id"]);
                                $event = array_merge(array(// значения по умолчанию
                                    "guild" => $guild["id"],
                                    "channel" => $channel["id"],
                                    "message" => "",// добавляем потом
                                    "time" => $command["time"],
                                    "raid" => $command["raid"],
                                    "leader" => reset($list),// первый
                                    "repeat" => 0,
                                    "close" => false,
                                    "hide" => false,
                                    "description" => ""
                                ), $event);
                                $eid = $events->length ? $events->key($events->length - 1) + 1 : 1;
                                if($events->set($eid, null, $event)){// если данные успешно добавлены
                                    $event[$events->primary] = $eid;
                                    if(!isset($counts[$eid])) $counts[$eid] = true;
                                    $isUpdate |= $mask["events"];// отмечаем изменение базы
                                }else $status = 309;// не удалось записать данные в базу данных
                            };
                        };
                        // добавляем идентификатор события при его отсутствии
                        if(empty($error) and !$isEdit and $current){// если нужно выполнить
                            if(empty($status)){// если нет ошибок
                                $eid = $current["id"];// получаем идентификатор события
                                $event[$events->primary] = $eid;
                            };
                        };
                        // добавляем данные в базу данных игроков
                        if(empty($error) and !$isEdit){// если нужно выполнить
                            $list = count($command["users"]) ? $command["users"] : array($message["author"]["id"]);
                            $count = count($command["roles"]);// вспомогательный счётчик ролей в команде для чередования
                            for($index = 0; $index < count($list) and empty($status); $index++){
                                $player = array_merge(array(// значения по умолчанию
                                    "event" => $event["id"],
                                    "user" => "",
                                    "role" => "",
                                    "accept" => false,
                                    "reserve" => false,
                                    "notice" => $command["time"] < $message["timestamp"] + $app["val"]["eventTimeNotice"],
                                    "comment" => ""
                                ), $player);
                                $player["user"] = $list[$index];// получаем идентификатор пользователя
                                if($count) $player["role"] = $command["roles"][$index % $count];// выполняем чередование
                                $eid = $event["id"];// получаем идентификатор события
                                $pid = $players->length ? $players->key($players->length - 1) + 1 : 1;
                                if($players->set($pid, null, $player)){// если данные успешно добавлены
                                    if(!isset($counts[$eid])) $counts[$eid] = true;
                                    $isUpdate |= $mask["players"];// отмечаем изменение базы
                                }else $status = 309;// не удалось записать данные в базу данных
                            };
                        };
                        break;
                    case "-":// удалить запись
                        // проверяем поддержку команды
                        if(empty($error)){// если нет проблем
                            if($guild){// если проверка пройдена
                            }else $error = 18;
                        };
                        // корректируем подходящую запись
                        if(empty($error)){// если нет проблем
                            $flag = $isSelectNext;
                            if($flag) $current = $next;// задаём подходящую запись
                        };
                        // получаем данные из подходящего события
                        if(empty($error) and $current){// если нужно выполнить
                            if(get_val($filter, $current["id"], false)){// если фильтр пройден
                                $command["raid"] = $current["raid"];
                                $command["time"] = $current["time"];
                            }else $current = false;
                        };
                        // проверяем наложение фильтрующих параметров
                        if(empty($error)){// если нет проблем
                            $flag = (!empty($command["index"]) or empty($command["date"]) and count($command["roles"]));
                            if(false !== $current){// если проверка пройдена
                            }else $error = $flag ? 53 : $error;
                        };
                        // корректируем дату
                        if(empty($error)){// если нет проблем
                            if(empty($command["date"]) and $command["time"] > 0){// если нужно выполнить
                                $time = !empty($current) ? $current["time"] : $command["time"];
                                if($time > 0) $value = $app["fun"]["dateFormat"]("d.m.Y", $time, $config["timezone"]);
                                if($time > 0) $command["date"] = $app["fun"]["dateCreate"]($value, $config["timezone"]);
                            };
                        };
                        // корректируем время
                        if(empty($error)){// если нет проблем
                            if(!empty($command["date"]) and $command["time"] >= 0){// если нужно выполнить
                                $time = (!$command["time"] and !empty($current)) ? $current["time"] : $command["time"];
                                if($time > 0) $value = $app["fun"]["dateFormat"]("d.m.Y", $command["date"], $config["timezone"]);
                                if($time > 0) $value .= " " . $app["fun"]["dateFormat"]("H:i", $time, $config["timezone"]);
                                if($time > 0) $command["time"] = $app["fun"]["dateCreate"]($value, $config["timezone"]);
                            };
                        };
                        // проверяем корректность указания времени
                        if(empty($error)){// если нет проблем
                            $time = null;// сбрасываем значение
                            if(// множественное условие
                                ($command["date"] > 0 or empty($command["date"]))
                                and ($command["time"] > 0 or empty($command["time"]))
                            ){// если проверка пройдена
                                $time = $command["time"];
                            }else $error = 74;
                        };
                        // получаем рейд
                        if(empty($error)){// если нет проблем
                            $raid = null;// сбрасываем значение
                            if(!empty($command["raid"])){// если проверка пройдена
                                $raid = $raids->get($command["raid"]);
                            };
                        };
                        // проверяем ограничения по времени записи
                        if(empty($error)){// если нет проблем
                            $flag = (empty($command["date"]) or empty($command["time"]) or $time >= $message["timestamp"] + $app["val"]["eventTimeClose"]);
                            if(!$flag) $permission = $app["fun"]["getPermission"]("guild", $message["author"]["id"], $channel, $guild);
                            $flag = ($flag or ($permission & $app["val"]["discordUserPermission"]) == $app["val"]["discordUserPermission"]);
                            if($flag){// если проверка пройдена
                            }else $error = 75;
                        };
                        // формируем список участников
                        $items = array();// ассоциативный список участников
                        if(empty($error) and $current){// если нужно выполнить
                            // формируем список идентификаторов пользователей
                            for($i = 0, $iLen = $players->length; $i < $iLen; $i++){
                                $id = $players->key($i);// получаем ключевой идентификатор по индексу
                                $item = $players->get($id);// получаем элемент по идентификатору
                                $uid = $item["user"];// идентификатор пользователя
                                $flag = $item["event"] == $current["id"];
                                if($flag) $items[$uid] = $item;
                            };
                        };
                        // определяем дополнительные флаги
                        if(empty($error)){// если нет проблем
                            $flag = ($current and (!isset($items[$message["author"]["id"]]) or $current["leader"] == $message["author"]["id"]));
                            $isEveryOneUser = ($flag and !count($command["roles"]) and !count($command["users"]) and !mb_strlen($command["comment"]));
                        };
                        // проверяем права на удаление записей других пользователей
                        if(empty($error)){// если нет проблем
                            for($i = 0, $iLen = count($command["users"]); ($i < $iLen or !$i) and !$error; $i++){
                                $uid = $iLen ? $command["users"][$i] : $message["author"]["id"];
                                $flag = false;// есть ли необходимые права для выполнения действия
                                $flag = ($flag or $current and $current["leader"] == $message["author"]["id"]);
                                $flag = ($flag or !$isEveryOneUser and $uid == $message["author"]["id"]);// пользователь автор сообщения
                                if(!$flag) $permission = $app["fun"]["getPermission"]("guild", $message["author"]["id"], $channel, $guild);
                                $flag = ($flag or ($permission & $app["val"]["discordUserPermission"]) == $app["val"]["discordUserPermission"]);
                                if($flag){// если проверка пройдена
                                }else $error = 76;
                            };
                        };
                        // удаляем данные в базе игроков и изменяем данные в базе событий
                        if(empty($error)){// если нет проблем
                            $index = 0;// индек позиции элемента
                            $list = count($command["users"]) ? $command["users"] : array($message["author"]["id"]);
                            for($i = $players->length - 1; $i > - 1 and empty($status); $i--){
                                $pid = $players->key($i);// получаем ключевой идентификатор по индексу
                                $item = $players->get($pid);// получаем элемент по идентификатору
                                $eid = $item["event"];// идентификатор события
                                $unit = $events->get($eid);// получаем информацию о событии
                                if(// множественное условие
                                    $unit["channel"] == $channel["id"]
                                    and $unit["guild"] == $guild["id"] 
                                    and (!$current or $item["event"] == $current["id"])
                                    and !$unit["hide"] and ($isEveryOneUser or in_array($item["user"], $list))
                                    and (!count($command["roles"]) or in_array($item["role"], $command["roles"]))
                                    and (empty($command["raid"]) or $unit["raid"] == $command["raid"])
                                    and (empty($command["date"]) or $app["fun"]["dateFormat"]("d.m.Y", $unit["time"], $config["timezone"]) == $app["fun"]["dateFormat"]("d.m.Y", $command["date"], $config["timezone"]))
                                    and (empty($command["time"]) or $app["fun"]["dateFormat"]("H:i", $unit["time"], $config["timezone"]) == $app["fun"]["dateFormat"]("H:i", $command["time"], $config["timezone"]))
                                ){// если нужно удалить запись
                                    if($players->set($pid)){// если данные успешно изменены
                                        if(!isset($counts[$eid])) $counts[$eid] = 0;
                                        $isUpdate |= $mask["players"];// отмечаем изменение базы
                                        $flag = $unit["time"] < $now + $app["val"]["eventTimeClose"];
                                        if($flag) $isLeaderBoardChange = true;
                                        $index++;// увиличиваем счётчик
                                        // работаем с базой данных событий
                                        $event = array();// сбрасываем значение
                                        $flag = false;// требуется обновить
                                        // проверяем сброс лидера
                                        if($unit["leader"] == $item["user"]){
                                            $isScheduleChange = true;
                                            $event["leader"] = "";
                                            $flag = true;
                                        };
                                        // выполняем обновление события
                                        if($flag){// если требуется обновление
                                            if($events->set($eid, null, $event)){// если данные успешно добавлены
                                                $isUpdate |= $mask["events"];// отмечаем изменение базы
                                            }else $status = 309;// не удалось записать данные в базу данных
                                        };
                                    }else $status = 309;// не удалось записать данные в базу данных
                                };
                            };
                            if($index){// если изменены записи
                            }else $error = 21;
                        };
                        // считаем количество оставшихся игроков
                        if(empty($error)){// если нет проблем
                            for($i = $players->length - 1; $i > - 1 and empty($status); $i--){
                                $pid = $players->key($i);// получаем ключевой идентификатор по индексу
                                $item = $players->get($pid);// получаем элемент по идентификатору
                                $eid = $item["event"];// идентификатор события
                                if(isset($counts[$eid])) $counts[$eid]++;
                            };
                        };
                        break;
                    case "?":// найти запись
                        // проверяем поддержку команды
                        if(empty($error)){// если нет проблем
                            if($user){// если проверка пройдена
                            }else $error = 19;
                        };
                        // формируем список событий
                        if(!$error){// если нет ошибок
                            $index = 0;// индек позиции элемента
                            $items = array();// список событий
                            $count = array();// вспомогательный счётчик
                            for($i = 0, $iLen = $players->length; $i < $iLen; $i++){
                                $pid = $players->key($i);// получаем ключевой идентификатор по индексу
                                $player = $players->get($pid);// получаем элемент по идентификатору
                                $eid = $player["event"];// получаем ключевой идентификатор
                                $event = $events->get($eid);// получаем элемент по идентификатору
                                if(// множественное условие
                                    $event["time"] >= $message["timestamp"] + $app["val"]["eventTimeClose"]
                                    and (empty($command["raid"]) or 0 === mb_strpos($event["raid"], $command["raid"]))
                                    and (!count($command["users"]) or in_array($player["user"] , $command["users"]))
                                    and (!mb_strlen($command["comment"]) or mb_stripos($event["description"], $command["comment"]))
                                ){// если нужно добавить в список
                                    if(!isset($count[$eid])) $count[$eid] = 0;
                                    if(!$count[$eid]) array_push($items, $event);
                                    $count[$eid]++;
                                    $index++;
                                };
                            };
                            if($index){// если найдены записи
                            }else $error = 21;
                        };
                        // сортируем список событитй
                        if(!$error){// если нет ошибок
                            usort($items, function($a, $b){// сортировка
                                $value = 0;// начальное значение
                                if(!$value and $a["time"] != $b["time"]) $value = $a["time"] > $b["time"] ? 1 : -1;
                                if(!$value and $a["raid"] != $b["raid"]) $value = $a["raid"] > $b["raid"] ? 1 : -1;
                                if(!$value and $a["id"] != $b["id"]) $value = $a["id"] < $b["id"] ? 1 : -1;
                                // возвращаем результат
                                return $value;
                            });
                        };
                        // отправляем информацию по событиям
                        if(!$error){// если нет ошибок
                            $index = 0;// индек отправленных уведомлений
                            $limit = $app["val"]["eventNoticeLimit"];// ограничение на количество уведомлений
                            for($i = 0, $iLen = count($items); $i < $iLen and (!$limit or $index < $limit) and empty($status); $i++){
                                $event = $items[$i];// получаем очередной элимент
                                $data = $app["fun"]["getNoticeMessage"]($event["id"], $status);
                                // отправляем личное сообщение
                                if(empty($status)){// если нет ошибок
                                    if($data and $user and !$user["bot"] and isset($user["channels"][0])){// если нужно отправить
                                        $uri = "/channels/" . $user["channels"][0]["id"] . "/messages";
                                        $data = $app["fun"]["apiRequest"]("post", $uri, $data, $code);
                                        if(200 == $code){// если запрос выполнен успешно
                                            $data["embed"] = get_val($data["embeds"], 0, false);// приводим к единому виду
                                            $app["fun"]["setCache"]("message", $data, $user["channels"][0]["id"], null, $user["id"]);
                                            $index++;// увеличиваем индек
                                        }else if(403 == $code){// если у пользователя установлен запрет 
                                            $index++;// увеличиваем индек
                                        }else $status = 306;// не удалось получить корректный ответ от удаленного сервера
                                    };
                                };
                            };
                        };
                        break;
                    default:// не известная команда
                        $error = 11;
                };
            };
            // информируем пользователя об проблеме
            if(// множественное условие
                empty($status) and $error > 0 and $config["language"] and $message
                and $message["timestamp"] > $now + $app["val"]["eventTimeClose"]
            ){// если нужно выполнить
                if(!$user) $user = $app["fun"]["getCache"]("user", $message["author"]["id"]);
                $data = $app["fun"]["getFeedbackMessage"]($error, $config["language"], $status);
                // отправляем личное сообщение
                if(empty($status)){// если нет ошибок
                    if($data and $user and !$user["bot"] and isset($user["channels"][0])){// если нужно отправить
                        $uri = "/channels/" . $user["channels"][0]["id"] . "/messages";
                        $data = $app["fun"]["apiRequest"]("post", $uri, $data, $code);
                        if(200 == $code){// если запрос выполнен успешно
                            $data["embed"] = get_val($data["embeds"], 0, false);// приводим к единому виду
                            $app["fun"]["setCache"]("message", $data, $user["channels"][0]["id"], null, $user["id"]);
                        }else if(403 == $code){// если у пользователя установлен запрет 
                        }else $status = 306;// не удалось получить корректный ответ от удаленного сервера
                    };
                };
            };
            // выполняем сортировку событий
            if(empty($status) and $guild and count($counts)){// если нужно выполнить
                $items = $app["fun"]["sortEventsMessage"]($guild, $channel, $status);
                foreach($items["events"] as $eid => $item) if(!isset($counts[$eid])) $counts[$eid] = true;
                foreach($items as $name => $list) if(count($list)) $isUpdate |= $mask[$name];
            };
            // выполняем удаление пустых событий
            if(empty($status) and $guild){// если нужно выполнить
                foreach($counts as $eid => $count){
                    if(empty($status)){// если нету ошибок
                        if(!$count){// если нет участников события
                            $unit = $events->get($eid);// получаем информацию о событии
                            if($events->set($eid)){// если данные успешно изменены
                                $isUpdate |= $mask["events"];// отмечаем изменение базы
                                $isScheduleChange = true;
                                if($unit["message"]){// если у события есть сообщение
                                    // удаляем сообщение
                                    $uri = "/channels/" . $channel["id"] . "/messages/" . $unit["message"];
                                    $data = $app["fun"]["apiRequest"]("delete", $uri, null, $code);
                                    if(204 == $code or 404 == $code){// если запрос выполнен успешно
                                        $app["fun"]["delCache"]("message", $unit["message"], $channel["id"], $guild["id"], null);
                                    }else $status = 306;// не удалось получить корректный ответ от удаленного сервера
                                };
                            }else $status = 309;// не удалось записать данные в базу данных
                        };
                    }else break;
                };
            };
            // выполняем обновлние сообщений событий
            if(empty($status) and $guild){// если нужно выполнить
                ksort($counts);// сортируем по возрастанию ключей
                foreach($counts as $eid => $count){
                    if(empty($status)){// если нету ошибок
                        if($count){// если есть участники события
                            $unit = $events->get($eid);// получаем информацию о событии
                            $item = $unit["message"] ? $app["fun"]["getCache"]("message", $unit["message"], $unit["channel"], $unit["guild"], null) : null;
                            // проверяем и исправляем эмодзи гильдии в событие
                            if(empty($status)){// если нет ошибок
                                $units = $app["fun"]["fixCustomEmoji"]($eid, true, true, $status);// получаем списки изменений
                                foreach($units as $name => $list) if(count($list)) $isUpdate |= $mask[$name];
                            };
                            // получаем контент для сообщения
                            if(empty($status)){// если нет ошибок
                                $data = $app["fun"]["getRecordMessage"]($eid, $config["language"], $config["timezone"], $status);
                            };
                            // отправляем или изменяем сообщение
                            if(empty($status)){// если нет ошибок
                                if(empty($item)){// если нужно опубликовать новое сообщение
                                    // отправляем новое сообщение
                                    $uri = "/channels/" . $unit["channel"] . "/messages";
                                    $data = $app["fun"]["apiRequest"]("post", $uri, $data, $code);
                                    if(200 == $code){// если запрос выполнен успешно
                                        $data["reactions"] = array();// приводим к единому виду
                                        $data["embed"] = get_val($data["embeds"], 0, false);// приводим к единому виду
                                        if(!$eid or $events->set($eid, "message", $data["id"])){// если данные успешно добавлены
                                            $item = $app["fun"]["setCache"]("message", $data, $unit["channel"], $unit["guild"], null);
                                            $unit["message"] = $data["id"];// фиксируем идентификатор сообщенияя
                                            if($eid) $isUpdate |= $mask["events"];// отмечаем изменение базы
                                        }else $status = 309;// не удалось записать данные в базу данных
                                    }else $status = 306;// не удалось получить корректный ответ от удаленного сервера
                                }else if($item["content"] != $data["content"]){// если нужно изменить старое сообщение
                                    // изменяем старое сообщение
                                    $uri = "/channels/" . $unit["channel"] . "/messages/" . $unit["message"];
                                    $data = $app["fun"]["apiRequest"]("patch", $uri, $data, $code);
                                    if(200 == $code){// если запрос выполнен успешно
                                        $data["embed"] = get_val($data["embeds"], 0, false);// приводим к единому виду
                                        $item = $app["fun"]["setCache"]("message", $data, $unit["channel"], $unit["guild"], null);
                                    }else $status = 306;// не удалось получить корректный ответ от удаленного сервера
                                };
                            };
                            // обрабатываем реакции для сообщения
                            if(empty($status)){// если нет ошибок
                                // формируем список для дальнейшей обработки
                                $list = array();// список для дальнейшей обработки
                                for($i = count($item["reactions"]) - 1; $i > -1 ; $i--){
                                    $reaction = $item["reactions"][$i];// получаем очередной элимент
                                    array_push($list, $reaction);
                                };
                                // выполняем обработку сформированного списка
                                if(!count($list)) array_push($list, null);// пустое значение для разового вызова
                                for($i = 0, $iLen = count($list); $i < $iLen and empty($status); $i++){
                                    $reaction = $list[$i];// получаем очередной элимент из списка
                                    if($reaction) $rid = array($reaction["user"]["id"], $reaction["emoji"]["name"], $reaction["emoji"]["id"]);
                                    $value = $reaction ? implode(":", $rid) : $reaction;// преобразуем реакцию в значение
                                    $flag = ($reaction and !$app["fun"]["getCache"]("reaction", $rid, $unit["message"], $unit["channel"], $unit["guild"], null));
                                    if($flag) continue;// переходим к следующему элименту списка
                                    $isUpdate |= $app["method"]["discord.reaction"](
                                        array(// параметры для метода
                                            "reaction" => $value,
                                            "message" => $item["id"],
                                            "channel" => $channel["id"],
                                            "guild" => $guild["id"],
                                            "game" => $game
                                        ),
                                        array(// внутренние опции
                                            "nocontrol" => true
                                        ),
                                        $sign, $status
                                    );
                                };
                            };
                        };
                    }else break;
                };
            };
            // удаляем сообщение переданное на обработку
            if(empty($status) and $isNeedDelete and $guild){// если нужно выполнить
                // удаляем сообщение
                $uri = "/channels/" . $channel["id"] . "/messages/" . $message["id"];
                $data = $app["fun"]["apiRequest"]("delete", $uri, null, $code);
                if(204 == $code or 404 == $code){// если запрос выполнен успешно
                    $app["fun"]["delCache"]("message", $message["id"], $channel["id"], $guild["id"], null);
                }else $status = 306;// не удалось получить корректный ответ от удаленного сервера
            };
            // выполняем обновление связаных каналов гильдии
            if(empty($status) and ($isScheduleChange or $isLeaderBoardChange)){// если нужно выполнить
                $isUpdate |= $app["method"]["discord.guild"](
                    array(// параметры для метода
                        "guild" => $guild["id"],
                        "game" => $game
                    ),
                    array(// внутренние опции
                        "nocontrol" => true,
                        "schedule" => $isScheduleChange,
                        "leaderboard" => $isLeaderBoardChange
                    ),
                    $sign, $status
                );
            };
            // сохраняем базу данных событий
            if(isset($events) and !empty($events)){// если база данных загружена
                if(!get_val($options, "nocontrol", false)){// если это прямой вызов
                    if(empty($status) and $isUpdate & $mask["events"]){// если нужно выполнить
                        if($events->save(false)){// если данные успешно сохранены
                        }else $status = 307;// не удалось сохранить базу данных
                    }else $events->unlock();// разблокируем базу
                };
            };
            // сохраняем базу данных игроков
            if(isset($players) and !empty($players)){// если база данных загружена
                if(!get_val($options, "nocontrol", false)){// если это прямой вызов
                    if(empty($status) and $isUpdate & $mask["players"]){// если нужно выполнить
                        if($players->save(false)){// если данные успешно сохранены
                        }else $status = 307;// не удалось сохранить базу данных
                    }else $players->unlock();// разблокируем базу
                };
            };
            // сохраняем базу данных сесии
            if(isset($session) and !empty($session)){// если база данных загружена
                if(!get_val($options, "nocontrol", false)){// если это прямой вызов
                    if(empty($status) and $isUpdate & $mask["session"]){// если нужно выполнить
                        if($session->save(false)){// если данные успешно сохранены
                        }else $status = 307;// не удалось сохранить базу данных
                    }else $session->unlock();// разблокируем базу
                };
            };
            // возвращаем результат
            $result = $isUpdate;
            return $result;
        },
        "discord.reaction" => function($params, $options, $sign, &$status){// обрабатываем реарцию
        //@param $params {array} - массив внешних не отфильтрованных значений
        //@param $options {array} - массив внутренних настроек
        //@param $sign {boolean|null} - успешность проверки подписи или null при её отсутствии
        //@param $status {integer} - целое число статуса выполнения
        //@return {true|false} - были ли изменения базы событий
            global $app; $result = null;
            
            $mask = array(// бит маска баз данных
                "session" => 1 << 0,
                "events" =>  1 << 1,
                "players" => 1 << 2
            );
            $current = null;// подходящее событие
            $isUpdate = 0;// битовая маска обновления баз
            $error = 0;// код ошибки для обратной связи
            $now = microtime(true);// текущее время
            $isEdit = false;// выполняется команда редактирования
            $isScheduleChange = false;// изменилось ли сводное расписание
            $isLeaderBoardChange = false;// изменилось ли таблица лидеров
            $isNeedProcessing = false;// требуется ли дальнейшая обработка
            $isNeedDelete = false;// удалить ли элимент переданный на обработку
            $counts = get_val($options, "counts", array());// счётчик участников
            // получаем очищенные значения параметров
            $game = $app["fun"]["getClearParam"]($params, "game", "string");
            $token = $app["fun"]["getClearParam"]($params, "token", "string");
            $guild = $app["fun"]["getClearParam"]($params, "guild", "string");
            $user = $app["fun"]["getClearParam"]($params, "user", "string");
            $channel = $app["fun"]["getClearParam"]($params, "channel", "string");
            $message = $app["fun"]["getClearParam"]($params, "message", "string");
            $reaction = $app["fun"]["getClearParam"]($params, "reaction", "string");
            $app["fun"]["setDebug"](6, "discord.reaction", $guild ? $guild : $user, $channel, $message, $reaction, $user ? "user" : null);// отладочная информация
            // проверяем корректность указанных параметров
            if(empty($status)){// если нет ошибок
                if((!is_null($game) and !is_null($token) and (!is_null($guild) xor !is_null($user)) and !is_null($channel) and !is_null($message) and !is_null($reaction)) or get_val($options, "nocontrol", false)){// если указаны обязательные поля
                    if((!empty($game) and !empty($token) and (!empty($guild) xor !empty($user)) and !empty($channel) and !empty($message) and !empty($reaction)) or get_val($options, "nocontrol", false)){// если обязательные поля успешно отфильтрованы
                        if($token == $app["val"]["appToken"] or get_val($options, "nocontrol", false)){// если прошли проверку
                            $game = $app["val"]["game"] = mb_strtolower($game);// сохраняем информацию об игре
                        }else $status = 303;// переданные параметры не верны
                    }else $status = 302;// один из обязательных параметров передан в неверном формате
                }else $status = 301;// не передан один из обязательных параметров
            };
            // загружаем все необходимые базы данных
            if(empty($status)){// если нет ошибок
                $session = $app["fun"]["getStorage"]($game, "session", true);
                if(!empty($session)){// если удалось получить доступ к базе данных
                }else $status = 304;// не удалось загрузить одну из многих баз данных
            };
            if(empty($status)){// если нет ошибок
                $events = $app["fun"]["getStorage"]($game, "events", true);
                if(!empty($events)){// если удалось получить доступ к базе данных
                }else $status = 304;// не удалось загрузить одну из многих баз данных
            };
            if(empty($status)){// если нет ошибок
                $players = $app["fun"]["getStorage"]($game, "players", true);
                if(!empty($players)){// если удалось получить доступ к базе данных
                }else $status = 304;// не удалось загрузить одну из многих баз данных
            };
            if(empty($status)){// если нет ошибок
                $raids = $app["fun"]["getStorage"]($game, "raids", false);
                if(!empty($raids)){// если удалось получить доступ к базе данных
                }else $status = 304;// не удалось загрузить одну из многих баз данных
            };
            if(empty($status)){// если нет ошибок
                $roles = $app["fun"]["getStorage"]($game, "roles", false);
                if(!empty($roles)){// если удалось получить доступ к базе данных
                }else $status = 304;// не удалось загрузить одну из многих баз данных
            };
            // получаем информацию о гильдии
            if(empty($status) and $guild){// если нужно выполнить
                $guild = $app["fun"]["getCache"]("guild", $guild);
                if($guild){// если удалось получить данные
                }else $status = 303;// переданные параметры не верны
            };
            // получаем информацию о пользователе
            if(empty($status) and $user){// если нужно выполнить
                $user = $app["fun"]["getCache"]("user", $user);
                if($user){// если удалось получить данные
                }else $status = 303;// переданные параметры не верны
            };
            // получаем информацию о канале
            if(empty($status)){// если нет ошибок
                if($guild) $channel = $app["fun"]["getCache"]("channel", $channel, $guild["id"], null);
                if($user) $channel = $app["fun"]["getCache"]("channel", $channel, null, $user["id"]);
                if($channel){// если удалось получить данные
                }else $status = 303;// переданные параметры не верны
            };
            // получаем конфигурацию канала
            if(empty($status)){// если нет ошибок
                if($guild) $config = $app["fun"]["getChannelConfig"]($channel, $guild, null);
                if($user) $config = $app["fun"]["getChannelConfig"]($channel, null, $user);
                if($config){// если удалось получить данные
                    $flag = "direct" == $config["mode"];// есть ли необходимые права для выполнения действия
                    if(!$flag) $permission = $app["fun"]["getPermission"]("guild", $session->get("bot", "value"), $channel, $guild);
                    $flag = ($flag or $config["mode"] and $config["game"] == $game and ($permission & $app["val"]["discordMainPermission"]) == $app["val"]["discordMainPermission"]);
                    $isNeedProcessing = ($isNeedProcessing or $message and $flag);
                }else $status = 310;// не корректный внутренний запрос
            };
            // получаем информацию о сообщении
            if(empty($status)){// если нет ошибок
                if($guild) $message = $app["fun"]["getCache"]("message", $message, $channel["id"], $guild["id"], null);
                if($user) $message = $app["fun"]["getCache"]("message", $message, $channel["id"], null, $user["id"]);
                if($message){// если удалось получить данные
                    $flag = ($reaction and $message["author"]["id"] == $session->get("bot", "value"));
                    $isNeedProcessing = ($isNeedProcessing and $flag);
                }else $status = 303;// переданные параметры не верны
            };
            // получаем информацию о реакции
            if(empty($status) and $reaction){// если нужно выполнить
                if($guild) $reaction = $app["fun"]["getCache"]("reaction", explode(":", $reaction), $message["id"], $channel["id"], $guild["id"], null);
                if($user) $reaction = $app["fun"]["getCache"]("reaction", explode(":", $reaction), $message["id"], $channel["id"], null, $user["id"]);
                if($reaction){// если удалось получить данные
                }else $status = 303;// переданные параметры не верны
            };
            // выполняем регламентные операции с базами данных
            if(empty($status) and !get_val($options, "nocontrol", false)){// если нужно выполнить
                $items = $app["fun"]["doRoutineStorage"]($now, $status);// получаем списки изменений
                foreach($items as $name => $list) if(count($list)) $isUpdate |= $mask[$name];
            };
            // пробуем определить событие из сообщения
            if(// множественное условие
                empty($status) and $message and $guild and "record" == $config["mode"]
                and $message["author"]["id"] == $session->get("bot", "value")
            ){// если нужно выполнить
                for($i = 0, $iLen = $events->length; $i < $iLen; $i++){
                    $eid = $events->key($i);// получаем ключевой идентификатор по индексу
                    $unit = $events->get($eid);// получаем элемент по идентификатору
                    if(// множественное условие
                        $unit["channel"] == $channel["id"]
                        and $unit["guild"] == $guild["id"]
                        and $unit["message"] == $message["id"]
                    ){// если нужно посчитать счётчик
                        $current = $unit;
                    };
                };
            };
            // обрабатываем команду
            if(empty($status) and $isNeedProcessing){// если нужно выполнить
                $isNeedDelete = true;
                // проверяем поддержку команды
                if(empty($error)){// если нет проблем
                    if($guild){// если проверка пройдена
                    }else $error = -1;
                };
                // проверяем что определено событие
                if(empty($error)){// если нет проблем
                    $flag = "record" == $config["mode"];
                    if(!empty($current)){// если проверка пройдена
                        $raid = $raids->get($current["raid"]);
                    }else $error = $flag ? 52 : -1;
                };
                // проверяем указание роли
                if(empty($error)){// если нет проблем
                    $flag = false;// найден подходящий элимент
                    for($i = 0, $iLen = $roles->length; $i < $iLen and !$flag; $i++){
                        $role = $roles->get($roles->key($i));// получаем очередную роль
                        $emoji = $app["fun"]["getEmoji"]($role["icon"]);
                        if(!empty($emoji["id"])) $flag = $reaction["emoji"]["id"] == $emoji["id"];
                        else $flag = $reaction["emoji"]["name"] == $emoji["name"];
                    };
                    if($flag){// если проверка пройдена
                    }else $error = -1;
                };
                // проверяем возможность использовать ролей в рейде
                if(empty($error)){// если нет проблем
                    $key = $role["key"];// получаем очередное ключевое значение
                    $limit = $raid[$key];// получаем лимит для заданной роли
                    if($limit > -1){// если проверка пройдена
                        $flag = $reaction["user"]["id"] == $session->get("bot", "value");
                        if($flag) $isNeedDelete = false;
                    }else $error = -1;
                };
                // проверяем записываемого пользователя
                if(empty($error)){// если нет проблем
                    $flag = !$reaction["user"]["bot"];
                    if($flag){// если проверка пройдена
                    }else $error = -1;
                };
                // проверяем ограничения по времени записи
                if(empty($error)){// если нет проблем
                    $flag = false;// есть ли необходимые права для выполнения действия
                    if(!$flag) $permission = $app["fun"]["getPermission"]("guild", $reaction["user"]["id"], $channel, $guild);
                    $flag = ($flag or ($permission & $app["val"]["discordUserPermission"]) == $app["val"]["discordUserPermission"]);
                    // выполняем проверки
                    if($current["hide"]) $error = 86;
                    else if($current["close"] and !$flag) $error = 24;
                };
                // определяем текущую запись игрока
                if(empty($error)){// если нет проблем
                    $unit = null;// текущущая запись пользователя
                    $eid = $current["id"];// идентификатор события
                    $items = array();// список участников
                    for($i = 0, $iLen = $players->length; $i < $iLen; $i++){
                        $id = $players->key($i);// получаем ключевой идентификатор по индексу
                        $item = $players->get($id);// получаем элемент по идентификатору
                        $flag = $item["event"] == $eid;
                        if($flag){// если игрок из текущего события
                            if(!isset($counts[$eid])) $counts[$eid] = 0;
                            $counts[$eid]++;// увеличиваем счётчик
                            array_push($items, $item);
                            $flag = $item["user"] == $reaction["user"]["id"];
                            if($flag) $unit = $item;
                        };
                    };
                };
                // переносим параметры команды
                if(empty($error)){// если нет проблем
                    $event = array();// данные для обновления события
                    $player = array();// данные для обновления записей участников
                    $flag = ($unit and $app["fun"]["checkRaidPlayers"]($items, $raid, $roles, $unit, false));// находится ли пользователь в основном составе
                    $isEdit = ($unit and $unit["role"] != $role["key"] or $flag);
                    $key = "leader"; $value = ""; if(!$isEdit and $unit and $current[$key] == $unit["user"]){ $event[$key] = $value; $isScheduleChange = true; };
                    $key = "repeat"; $value = 0; if(!$isEdit and $unit and $current["leader"] == $unit["user"] and $current[$key]) $event[$key] = $value;
                    $key = "reserve"; $value = true; if($isEdit and $unit and $unit["role"] == $role["key"] and $flag){ $player[$key] = $value; $isLeaderBoardChange = true; };
                    $key = "role"; $value = $role["key"]; if($isEdit and $unit and $unit[$key] != $role["key"]) $player[$key] = $value;
                    if($current["close"] and !$isEdit and !$unit) $isLeaderBoardChange = true;
                };
                // изменяем данные в базе данных событий
                if(empty($error) and count($event)){// если нужно выполнить
                    if(empty($status)){// если нет ошибок
                        $eid = $current["id"];// получаем идентификатор события
                        if($events->set($eid, null, $event)){// если данные успешно изменены
                            $event[$events->primary] = $eid;
                            if(!isset($counts[$eid])) $counts[$eid] = true;
                            $isUpdate |= $mask["events"];// отмечаем изменение базы
                        }else $status = 309;// не удалось записать данные в базу данных
                    };
                };
                // изменяем данные в базе данных игроков
                if(empty($error) and count($player) and $isEdit){// если нужно выполнить
                    if(empty($status)){// если нет ошибок
                        $eid = $current["id"];// получаем идентификатор события
                        $pid = $unit["id"];// получаем идентификатор события
                        if($players->set($pid, null, $player)){// если данные успешно изменены
                            if(!isset($counts[$eid])) $counts[$eid] = true;
                            $isUpdate |= $mask["players"];// отмечаем изменение базы
                        }else $status = 309;// не удалось записать данные в базу данных
                    };
                };
                // добавляем данные в базу данных игроков
                if(empty($error) and !$isEdit and !$unit){// если нужно выполнить
                    if(empty($status)){// если нет ошибок
                        $eid = $current["id"];// получаем идентификатор события
                        $player = array_merge(array(// значения по умолчанию
                            "event" => $current["id"],
                            "user" => $reaction["user"]["id"],
                            "role" => $role["key"],
                            "accept" => false,
                            "reserve" => false,
                            "notice" => $current["time"] < $now + $app["val"]["eventTimeNotice"],
                            "comment" => ""
                        ), $player);
                        $pid = $players->length ? $players->key($players->length - 1) + 1 : 1;
                        if($players->set($pid, null, $player)){// если данные успешно добавлены
                            if(!isset($counts[$eid])) $counts[$eid] = true;
                            $isUpdate |= $mask["players"];// отмечаем изменение базы
                        }else $status = 309;// не удалось записать данные в базу данных
                    };
                };
                // удаляем данные в базe данных игроков
                if(empty($error) and !$isEdit and $unit){// если нужно выполнить
                    if(empty($status)){// если нет ошибок
                        $eid = $current["id"];// получаем идентификатор события
                        $pid = $unit["id"];// получаем идентификатор события
                        if($players->set($pid)){// если данные успешно удалены
                            if(isset($counts[$eid])) $counts[$eid]--;
                            $isUpdate |= $mask["players"];// отмечаем изменение базы
                            $flag = $current["close"];
                            if($flag) $isLeaderBoardChange = true;
                        }else $status = 309;// не удалось записать данные в базу данных
                    };
                };
            };
            // удаляем реакцию переданное на обработку
            if(empty($status) and $isNeedDelete){// если нужно выполнить
                // удаляем реакцию
                $rid = array("", $reaction["emoji"]["name"], $reaction["emoji"]["id"]);
                $value = urlencode(!empty($rid[2]) ? implode(":", $rid) : $rid[1]);
                $uri = "/channels/" . $channel["id"] . "/messages/" . $message["id"] . "/reactions/" . $value . "/" . $reaction["user"]["id"];
                $data = $app["fun"]["apiRequest"]("delete", $uri, null, $code);
                $rid[0] = $reaction["user"]["id"];// идентификатор пользователя
                if(204 == $code or 404 == $code){// если запрос выполнен успешно
                    if($guild) $app["fun"]["delCache"]("reaction", $rid, $message["id"], $channel["id"], $guild["id"], null);
                    if($user) $app["fun"]["delCache"]("reaction", $rid, $message["id"], $channel["id"], null, $user["id"]);
                }else $status = 306;// не удалось получить корректный ответ от удаленного сервера
            };
            // информируем пользователя об проблеме
            if(// множественное условие
                empty($status) and $error > 0 and $config["language"] and $reaction
            ){// если нужно выполнить
                if(!$user) $user = $app["fun"]["getCache"]("user", $reaction["user"]["id"]);
                $data = $app["fun"]["getFeedbackMessage"]($error, $config["language"], $status);
                // отправляем личное сообщение
                if(empty($status)){// если нет ошибок
                    if($data and $user and !$user["bot"] and isset($user["channels"][0])){// если нужно отправить
                        $uri = "/channels/" . $user["channels"][0]["id"] . "/messages";
                        $data = $app["fun"]["apiRequest"]("post", $uri, $data, $code);
                        if(200 == $code){// если запрос выполнен успешно
                            $data["embed"] = get_val($data["embeds"], 0, false);// приводим к единому виду
                            $app["fun"]["setCache"]("message", $data, $user["channels"][0]["id"], null, $user["id"]);
                        }else if(403 == $code){// если у пользователя установлен запрет 
                        }else $status = 306;// не удалось получить корректный ответ от удаленного сервера
                    };
                };
            };
            // выполняем удаление пустых событий
            if(empty($status) and $guild){// если нужно выполнить
                foreach($counts as $eid => $count){
                    if(empty($status)){// если нету ошибок
                        if(!$count){// если нет участников события
                            $unit = $events->get($eid);// получаем информацию о событии
                            if($events->set($eid)){// если данные успешно изменены
                                $isUpdate |= $mask["events"];// отмечаем изменение базы
                                $isScheduleChange = true;
                                if($unit["message"]){// если у события есть сообщение
                                    // удаляем сообщение
                                    $uri = "/channels/" . $channel["id"] . "/messages/" . $unit["message"];
                                    $data = $app["fun"]["apiRequest"]("delete", $uri, null, $code);
                                    if(204 == $code or 404 == $code){// если запрос выполнен успешно
                                        $app["fun"]["delCache"]("message", $unit["message"], $channel["id"], $guild["id"], null);
                                    }else $status = 306;// не удалось получить корректный ответ от удаленного сервера
                                };
                            }else $status = 309;// не удалось записать данные в базу данных
                        };
                    }else break;
                };
            };
            // выполняем обновлние сообщений событий
            if(empty($status) and $guild){// если нужно выполнить
                ksort($counts);// сортируем по возрастанию ключей
                foreach($counts as $eid => $count){
                    if(empty($status)){// если нету ошибок
                        if($count){// если есть участники события
                            $unit = $events->get($eid);// получаем информацию о событии
                            $item = $unit["message"] ? $app["fun"]["getCache"]("message", $unit["message"], $unit["channel"], $unit["guild"], null) : null;
                            // проверяем и исправляем эмодзи гильдии в событие
                            if(empty($status)){// если нет ошибок
                                $units = $app["fun"]["fixCustomEmoji"]($eid, true, true, $status);// получаем списки изменений
                                foreach($units as $name => $list) if(count($list)) $isUpdate |= $mask[$name];
                            };
                            // получаем контент для сообщения
                            if(empty($status)){// если нет ошибок
                                $data = $app["fun"]["getRecordMessage"]($eid, $config["language"], $config["timezone"], $status);
                            };
                            // отправляем или изменяем сообщение
                            if(empty($status)){// если нет ошибок
                                if(empty($item)){// если нужно опубликовать новое сообщение
                                    // отправляем новое сообщение
                                    $uri = "/channels/" . $unit["channel"] . "/messages";
                                    $data = $app["fun"]["apiRequest"]("post", $uri, $data, $code);
                                    if(200 == $code){// если запрос выполнен успешно
                                        $data["reactions"] = array();// приводим к единому виду
                                        $data["embed"] = get_val($data["embeds"], 0, false);// приводим к единому виду
                                        if(!$eid or $events->set($eid, "message", $data["id"])){// если данные успешно добавлены
                                            $item = $app["fun"]["setCache"]("message", $data, $unit["channel"], $unit["guild"], null);
                                            $unit["message"] = $data["id"];// фиксируем идентификатор сообщенияя
                                            if($eid) $isUpdate |= $mask["events"];// отмечаем изменение базы
                                        }else $status = 309;// не удалось записать данные в базу данных
                                    }else $status = 306;// не удалось получить корректный ответ от удаленного сервера
                                }else if($item["content"] != $data["content"]){// если нужно изменить старое сообщение
                                    // изменяем старое сообщение
                                    $uri = "/channels/" . $unit["channel"] . "/messages/" . $unit["message"];
                                    $data = $app["fun"]["apiRequest"]("patch", $uri, $data, $code);
                                    if(200 == $code){// если запрос выполнен успешно
                                        $data["embed"] = get_val($data["embeds"], 0, false);// приводим к единому виду
                                        $item = $app["fun"]["setCache"]("message", $data, $unit["channel"], $unit["guild"], null);
                                    }else $status = 306;// не удалось получить корректный ответ от удаленного сервера
                                };
                            };
                        };
                    }else break;
                };
            };
            // добавление недостающих реакций
            if(empty($status) and $current and $guild){// если нужно выполнить
                $count = array();// счётчик ролей
                // подготавливаем счётчик ролей
                for($i = 0, $iLen = $roles->length; $i < $iLen; $i++){
                    $key = $roles->key($i);// подготавливаем очередной ключ
                    $count[$key] = 0;// сбрасываем счётчик
                };
                // последовательно счиаем роли по реакциям
                for($i = count($message["reactions"]) - 1; $i > -1 and empty($status); $i--){
                    $item = $message["reactions"][$i];// получаем очередной элемент
                    $flag = $session->get("bot", "value") != $item["user"]["id"];
                    for($j = 0, $jLen = $roles->length; $j < $jLen and !$flag; $j++){
                        $key = $roles->key($j);// подготавливаем очередной ключ
                        $role = $roles->get($key);// получаем очередную роль
                        $emoji = $app["fun"]["getEmoji"]($role["icon"]);
                        if(!empty($emoji["id"])) $flag = $item["emoji"]["id"] == $emoji["id"];
                        else $flag = $item["emoji"]["name"] == $emoji["name"];
                        if($flag) $count[$key]++;// увеличиваем счётчик
                    };
                };
                // добавляем реакции для недостающих ролей
                for($i = 0, $iLen = $roles->length; $i < $iLen and empty($status); $i++){
                    $key = $roles->key($i);// подготавливаем очередной ключ
                    $role = $roles->get($key);// получаем очередную роль
                    $emoji = $app["fun"]["getEmoji"]($role["icon"]);
                    $raid = $raids->get($current["raid"]);
                    $limit = $raid[$role["key"]];
                    $flag = ($limit > -1 and !$count[$key]);
                    // добавляем реакцию
                    if($flag){// если требуется добавить реакцию
                        $item = array("emoji" => $emoji);
                        $item["user"] = array("id" => $session->get("bot", "value"), "bot" => true);
                        $rid = array("", $item["emoji"]["name"], $item["emoji"]["id"]);
                        $value = urlencode(!empty($rid[2]) ? implode(":", $rid) : $rid[1]);
                        $uri = "/channels/" . $channel["id"] . "/messages/" . $message["id"] . "/reactions/" . $value . "/@me";
                        $data = $app["fun"]["apiRequest"]("put", $uri, null, $code);
                        $rid[0] = $item["user"]["id"];// идентификатор пользователя
                        if(204 == $code){// если запрос выполнен успешно
                            $app["fun"]["setCache"]("reaction", $item, $message["id"], $channel["id"], $guild["id"], null);
                        }else $status = 306;// не удалось получить корректный ответ от удаленного сервера
                    };
                };
            };
            // выполняем обновление связаных каналов гильдии
            if(empty($status) and ($isScheduleChange or $isLeaderBoardChange)){// если нужно выполнить
                $isUpdate |= $app["method"]["discord.guild"](
                    array(// параметры для метода
                        "guild" => $guild["id"],
                        "game" => $game
                    ),
                    array(// внутренние опции
                        "nocontrol" => true,
                        "schedule" => $isScheduleChange,
                        "leaderboard" => $isLeaderBoardChange
                    ),
                    $sign, $status
                );
            };
            // сохраняем базу данных событий
            if(isset($events) and !empty($events)){// если база данных загружена
                if(!get_val($options, "nocontrol", false)){// если это прямой вызов
                    if(empty($status) and $isUpdate & $mask["events"]){// если нужно выполнить
                        if($events->save(false)){// если данные успешно сохранены
                        }else $status = 307;// не удалось сохранить базу данных
                    }else $events->unlock();// разблокируем базу
                };
            };
            // сохраняем базу данных игроков
            if(isset($players) and !empty($players)){// если база данных загружена
                if(!get_val($options, "nocontrol", false)){// если это прямой вызов
                    if(empty($status) and $isUpdate & $mask["players"]){// если нужно выполнить
                        if($players->save(false)){// если данные успешно сохранены
                        }else $status = 307;// не удалось сохранить базу данных
                    }else $players->unlock();// разблокируем базу
                };
            };
            // сохраняем базу данных сесии
            if(isset($session) and !empty($session)){// если база данных загружена
                if(!get_val($options, "nocontrol", false)){// если это прямой вызов
                    if(empty($status) and $isUpdate & $mask["session"]){// если нужно выполнить
                        if($session->save(false)){// если данные успешно сохранены
                        }else $status = 307;// не удалось сохранить базу данных
                    }else $session->unlock();// разблокируем базу
                };
            };
            // возвращаем результат
            $result = $isUpdate;
            return $result;
        },
        "event.link" => function($params, $options, $sign, &$status){// обрабатывает ссылку события
        //@param $params {array} - массив внешних не отфильтрованных значений
        //@param $options {array} - массив внутренних настроек
        //@param $sign {boolean|null} - успешность проверки подписи или null при её отсутствии
        //@param $status {integer} - целое число статуса выполнения
        //@return {true|false} - были ли изменения базы событий
            global $app; $result = null;

            $link = null;// возвращаемая ссылка
            $mode = "google";// режим работы по умолчанию
            $now = microtime(true);// текущее время
            $agent = get_val($_SERVER, "HTTP_USER_AGENT", "");
            // получаем очищенные значения параметров
            $game = $app["fun"]["getClearParam"]($params, "game", "string");
            $event = $app["fun"]["getClearParam"]($params, "event", "natural");
            $raid = $app["fun"]["getClearParam"]($params, "raid", "string");
            $app["fun"]["setDebug"](3, "event.link", $event);// отладочная информация
            // проверяем корректность указанных параметров
            if(empty($status)){// если нет ошибок
                if((!is_null($game) and !is_null($event) and !is_null($raid)) or get_val($options, "nocontrol", false)){// если указаны обязательные поля
                    if((!empty($game) and !empty($event) and !empty($raid)) or get_val($options, "nocontrol", false)){// если обязательные поля успешно отфильтрованы
                        $game = $app["val"]["game"] = mb_strtolower($game);// сохраняем информацию об игре
                    }else $status = 302;// один из обязательных параметров передан в неверном формате
                }else $status = 301;// не передан один из обязательных параметров
            };
            // определяем режим работы
            if(empty($status) and $agent){// если нужно выполнить
                // задаём данные для идентификации
                $data = array(// данные для идентификации клиента
                    "discord" => array(// Робот формирования предпросмотра
                        "Mozilla/5.0 (Macintosh; Intel Mac OS X 11.6; rv:92.0) Gecko/20100101 Firefox/92.0",
                        "Mozilla/5.0 (Macintosh; Intel Mac OS X 10.10; rv:38.0) Gecko/20100101 Firefox/38.0",
                        "Discordbot"
                    ),
                    "google" => array(// Пользователь инфраструктуры Google
                        "Chrome"
                    )
                );
                // идентифицируем клиент обращения
                $flag = false;// найдено ли совпадение
                foreach($data as $key => $list){// пробигаемся по ключам
                    for($i = 0, $iLen = count($list); $i < $iLen and !$flag; $i++){
                        $value = $list[$i];// получаем очередное значение
                        $flag = false !== mb_stripos($agent, $value);
                        if($flag) $mode = $key;
                    };
                };
            };
            // выполняем обработку данных
            if(empty($status)){// если нет ошибок
                $app["val"]["useFileCache"] = true;// включаем использование
                switch($mode){// поддерживаемые режимы
                    case "discord":// Discord
                        // загружаем все необходимые базы данных
                        if(empty($status)){// если нет ошибок
                            $session = $app["fun"]["getStorage"]($game, "session", false);
                            if(!empty($session)){// если удалось получить доступ к базе данных
                            }else $status = 304;// не удалось загрузить одну из многих баз данных
                        };
                        if(empty($status)){// если нет ошибок
                            $raids = $app["fun"]["getStorage"]($game, "raids", false);
                            if(!empty($raids)){// если удалось получить доступ к базе данных
                            }else $status = 304;// не удалось загрузить одну из многих баз данных
                        };
                        // получаем информацию о рейде
                        if(empty($status)){// если нет ошибок
                            $value = str_replace(" ", "+", $raid);
                            $raid = $raids->get($value);
                            if($raid){// если удалось получить данные
                            }else $status = 303;// переданные параметры не верны
                        };
                        // формируем ссылку на изображение
                        if(empty($status)){// если нет ошибок
                            $link = template($app["val"]["discordContentUrl"], array("group" => "attachments", "id" => $raid["image"]));
                        };
                        break;
                    case "google":// Google
                        // загружаем все необходимые базы данных
                        if(empty($status)){// если нет ошибок
                            $session = $app["fun"]["getStorage"]($game, "session", false);
                            if(!empty($session)){// если удалось получить доступ к базе данных
                            }else $status = 304;// не удалось загрузить одну из многих баз данных
                        };
                        if(empty($status)){// если нет ошибок
                            $raids = $app["fun"]["getStorage"]($game, "raids", false);
                            if(!empty($raids)){// если удалось получить доступ к базе данных
                            }else $status = 304;// не удалось загрузить одну из многих баз данных
                        };
                        if(empty($status)){// если нет ошибок
                            $events = $app["fun"]["getStorage"]($game, "events", false);
                            if(!empty($events)){// если удалось получить доступ к базе данных
                            }else $status = 304;// не удалось загрузить одну из многих баз данных
                        };
                        // получаем информацию о событии
                        if(empty($status)){// если нет ошибок
                            $event = $events->get($event);
                            if($event){// если удалось получить данные
                            }else $status = 303;// переданные параметры не верны
                        };
                        // получаем информацию о рейде
                        if(empty($status)){// если нет ошибок
                            $raid = $raids->get($event["raid"]);
                            if($raid){// если удалось получить данные
                            }else $status = 303;// переданные параметры не верны
                        };
                        // получаем информацию о гильдии
                        if(empty($status)){// если нет ошибок
                            $guild = $app["fun"]["getCache"]("guild", $event["guild"]);
                            if($guild){// если удалось получить данные
                            }else $status = 303;// переданные параметры не верны
                        };
                        // получаем информацию о канале
                        if(empty($status)){// если нет ошибок
                            $channel = $app["fun"]["getCache"]("channel", $event["channel"], $guild["id"], null);
                            if($channel){// если удалось получить данные
                            }else $status = 303;// переданные параметры не верны
                        };
                        // получаем конфигурацию канала
                        if(empty($status)){// если нет ошибок
                            $config = $app["fun"]["getChannelConfig"]($channel, $guild, null);
                            if($config){// если удалось получить данные
                            }else $status = 310;// не корректный внутренний запрос
                        };
                        // формируем ссылку на календарь
                        if(empty($status)){// если нет ошибок
                            $link = $app["fun"]["getCalendarUrl"]($mode, mb_strtoupper($game) . ": " . $raid["key"] . " - " .  $raid[$config["language"]], $event["time"], 2*60*60, $config["timezone"], $guild["name"], $app["fun"]["strimStrMulti"](mb_ucfirst(trim(str_replace("<br>", $app["val"]["lineDelim"], $event["description"]))), ".?!", 80));
                        };
                        break;
                    default:// не поддерживаемый режим
                        $status = 310;// не корректный внутренний запрос
                };
            };
            // работаем с файловыми кешами
            if($app["val"]["useFileCache"]){// если используются
                if(empty($status)){// если нет ошибок
                    // сохраняем данные в файловые кеши
                    foreach($app["cache"] as $name => $items){// пробигаемся по группам
                        for($i = count($items) - 1; $i > -1; $i--){// пробигаемся по элементам
                            $item = $items[$i];// получаем очередной элемент из списка элементов
                            if(!$app["fun"]["setFileCache"]($game, $name, $item["id"], $item)){// если не удалось
                                $app["fun"]["delFileCache"]($game, $name, $item["id"]);
                                $status = 311;// не удалось записать данные в файловый кеш
                            };
                        };
                    };
                }else{// если есть ошибки
                    // удаляем данные из файловых кешей
                    foreach($app["cache"] as $name => $items){// пробигаемся по группам
                        for($i = count($items) - 1; $i > -1; $i--){// пробигаемся по элементам
                            $item = $items[$i];// получаем очередной элемент из списка элементов
                            $app["fun"]["delFileCache"]($game, $name, $item["id"]);
                        };
                    };
                };
            };
            // возвращаем результат
            $result = $link;
            return $result;
        }
    ),
    "init" => function(){// инициализация приложения
    //@return {undefined} - нечего не возвращает
        global $app;
        $status = 0;

        $response = null;
        // включаем полное выполнение скрипта
        ini_set("max_input_time", 0);
        ini_set("ignore_user_abort", 1);
        ini_set("max_execution_time", 0);
        // задаём временную зону по умолчанию
        $timezone = $app["val"]["timeZone"];
        date_default_timezone_set($timezone);
        // готовим список полученных параметров
        $params = array();
        foreach($_GET as $key => $value){
            if(!preg_match("//u", $value)){
                $value = iconv("cp1251", "utf-8", $value);
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
        // загружаем все необходимые базы данных
        if(empty($status)){// если нет ошибок
            $statuses = $app["fun"]["getStorage"](null, "statuses", false);
            if(!empty($statuses)){// если удалось получить доступ к базе данных
            }else $status = 304;// не удалось загрузить одну из многих баз данных
        };
        // проверяем и выполняем метод
        if(empty($status)){// если нет ошибок
            $method = $app["fun"]["getClearParam"]($params, "method", "string");
            if(!is_null($method)){// если задан метод в запросе
                if(!empty($method)){// если фильтрация метода прошла успешно
                    if(isset($app["method"][$method])){// если метод есть в списке поддерживаемых методов
                        $response = $app["method"][$method]($params, array(), false, $status);
                        if(empty($status)) $status = 200;// успешно выполено
                    }else $status = 308;// запрашиваемый метод не поддерживается
                }else $status = 302;// один из обязательных параметров передан в неверном формате
            }else $status = 301;// не передан один из обязательных параметров
        };
        // определяем формат вывода
        $format = $app["fun"]["getClearParam"]($params, "format", "string");
        if(empty($format) or !isset($app["format"][$format])){// если задан не поддерживаемый формат
            $format = $app["val"]["format"];// устанавливаем формат по умолчанию
        };
        // определяем язык вывода
        $language = $app["fun"]["getClearParam"]($params, "language", "string");
        $item = $statuses ? $statuses->get($statuses->key(0)) : null;
        if(empty($language) or !isset($item[$language])){// если задан не поддерживаемый язык
            $language = $app["val"]["statusLang"];// устанавливаем язык по умолчанию
        };
        // заполняем статусное сообщение
        $message = $statuses ? $statuses->get($status, $language) : "";
        if(!$message) $message = $app["val"]["statusUnknown"];
        // выводим результа
        header("Server: Simple API 0.1.1");
        header("Cache-Control: no-store");
        header("Pragma: no-cache");
        $app["format"][$format](array(
            "response" => $response,
            "status" => $status,
            "message" => $message
        ));
    },
    "format" => array(// поддерживаемые форматы вывода
        "json" => function($data){// в формате json
        //@param $data {array} - массыв выводимых данных
        //@return {boolean} - успешность вывода данных в этом формате
            $error = 0;
            
            // для нормальных браузеров добавляем Content-Type
            if(// множественное условие
                false === strpos($_SERVER["HTTP_USER_AGENT"], "MSIE")
                and false === strpos($_SERVER["HTTP_USER_AGENT"], "Trident")
            ){// если это нормальный браузер
                header("Content-Type: application/json; charset=utf-8");
            };
            echo json_encode($data);
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
        "redirect" => function($data){// в формате перенаправления
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
            if(is_string($data["response"])) header("Location: " . $data["response"]);
            else http_response_code(404);
            // возвращаем результат
            return !$error;
        }
    ),
    "fun" => array(// специфические функции
        "setCache" => function&($type, $data){// добавляет данные в кеш
        //@param $type {string} - тип данных для кеширования
        //@param $data {array} - данные в виде массива 
        //@param ...$id {string} - идентификаторы разных уровней
        //@return {array|null} - ссылка на элемент данныx или null
            global $app;
            $error = 0;
            
            $game = $app["val"]["game"];
            $argLength = func_num_args();
            $argFirst = 2;// первый $id
            $unit = null;// промежуточная ссылка
            $cache = null;// окончательная ссылка
            // загружаем базу данных
            if(!$error){// если нет ошибок
                $session = $app["fun"]["getStorage"]($game, "session", true);
                if(!empty($session)){// если удалось получить доступ к базе данных
                }else $error = 1;
            };
            // выполняем обработку
            if(!$error){// если нет ошибок
                switch($type){// поддерживаемые типы
                    case "guild":// гильдия
                        // проверяем наличее данных
                        if(!$error){// если нет ошибок
                            if(// множественное условие
                                isset($data["id"])
                                and $argLength >= $argFirst
                            ){// если проверка пройдена
                                $gid = $data["id"];
                            }else $error = 3;
                        };
                        // проверяем значение данных
                        if(!$error){// если нет ошибок
                            if(// множественное условие
                                !empty($gid)
                            ){// если проверка пройдена
                            }else $error = 4;
                        };
                        // определяем ссылку на элемента
                        if(!$error){// если нет ошибок
                            $key = "guilds";// задаём ключ
                            $parent = &$app["cache"];
                            if(!is_null($parent)){// если есть родительский элемент
                                if(!isset($parent[$key])) $parent[$key] = array();
                                for($i = 0, $iLen = count($parent[$key]); $i < $iLen; $i++){
                                    if($parent[$key][$i]["id"] == $gid) break;
                                };
                                if(!isset($parent[$key][$i])){// если не найден
                                    if($app["val"]["useFileCache"]){// если используются
                                        $item = $app["fun"]["getFileCache"]($game, $key, $gid);
                                        if($item) $parent[$key][$i] = $item;
                                    };
                                };
                                if(!isset($parent[$key][$i])) $parent[$key][$i] = array();
                                $unit = &$parent[$key][$i];
                            }else $error = 5;
                        };
                        // формируем структуру
                        if(!$error){// если нет ошибок
                            foreach(array("roles", "emojis", "members", "channels") as $key){
                                if(isset($data[$key]) and !isset($unit[$key])){
                                    $unit[$key] = array();
                                };
                            };
                        };
                        // обрабатываем данные
                        if(!$error){// если нет ошибок
                            // идентификатор
                            $key = "id";// задаём ключ
                            if(isset($data[$key])){// если существует
                                $unit[$key] = $data[$key];
                            };
                            // название
                            $key = "name";// задаём ключ
                            if(isset($data[$key])){// если существует
                                $unit[$key] = $data[$key];
                            };
                            // иконка
                            $key = "icon";// задаём ключ
                            if(isset($data[$key])){// если существует
                                $unit[$key] = $data[$key];
                            }else $unit[$key] = "";// по умолчанию
                            // список ролей
                            $key = "roles";// задаём ключ
                            if(isset($data[$key])){// если существует
                                $unit[$key] = array();// сбрасываем список
                                for($i = 0, $iLen = count($data[$key]); $i < $iLen; $i++){
                                    $app["fun"]["setCache"]("role", $data[$key][$i], $gid);
                                };
                            };
                            // список эмодзи
                            $key = "emojis";// задаём ключ
                            if(isset($data[$key])){// если существует
                                $unit[$key] = array();// сбрасываем список
                                for($i = 0, $iLen = count($data[$key]); $i < $iLen; $i++){
                                    $app["fun"]["setCache"]("emoji", $data[$key][$i], $gid);
                                };
                            };
                            // список участников
                            $key = "members";// задаём ключ
                            if(isset($data[$key])){// если существует
                                for($i = 0, $iLen = count($data[$key]); $i < $iLen; $i++){
                                    $app["fun"]["setCache"]("member", $data[$key][$i], $gid);
                                };
                            };
                            // список каналов
                            $key = "channels";// задаём ключ
                            if(isset($data[$key])){// если существует
                                $unit[$key] = array();// сбрасываем список
                                for($i = 0, $iLen = count($data[$key]); $i < $iLen; $i++){
                                    $app["fun"]["setCache"]("channel", $data[$key][$i], $gid, null);
                                };
                            };
                        };
                        // присваеваем ссылку на элемент
                        if(!$error){// если нет ошибок
                            $cache = &$unit;
                        };
                        break;
                    case "user":// пользователь
                        // проверяем наличее данных
                        if(!$error){// если нет ошибок
                            if(// множественное условие
                                isset($data["id"])
                                and $argLength >= $argFirst
                            ){// если проверка пройдена
                                $uid = $data["id"];
                            }else $error = 3;
                        };
                        // проверяем значение данных
                        if(!$error){// если нет ошибок
                            if(// множественное условие
                                !empty($uid)
                            ){// если проверка пройдена
                            }else $error = 4;
                        };
                        // определяем ссылку на элемента
                        if(!$error){// если нет ошибок
                            $key = "users";// задаём ключ
                            $parent = &$app["cache"];
                            if(!is_null($parent)){// если есть родительский элемент
                                if(!isset($parent[$key])) $parent[$key] = array();
                                for($i = 0, $iLen = count($parent[$key]); $i < $iLen; $i++){
                                    if($parent[$key][$i]["id"] == $uid) break;
                                };
                                if(!isset($parent[$key][$i])){// если не найден
                                    if($app["val"]["useFileCache"]){// если используются
                                        $item = $app["fun"]["getFileCache"]($game, $key, $uid);
                                        if($item) $parent[$key][$i] = $item;
                                    };
                                };
                                if(!isset($parent[$key][$i])) $parent[$key][$i] = array();
                                $unit = &$parent[$key][$i];
                            }else $error = 5;
                        };
                        // формируем структуру
                        if(!$error){// если нет ошибок
                            foreach(array("channels") as $key){
                                if(isset($data[$key]) and !isset($unit[$key])){
                                    $unit[$key] = array();
                                };
                            };
                        };
                        // обрабатываем данные
                        if(!$error){// если нет ошибок
                            // идентификатор
                            $key = "id";// задаём ключ
                            if(isset($data[$key])){// если существует
                                $unit[$key] = $data[$key];
                            };
                            // имя
                            $key = "username";// задаём ключ
                            if(isset($data[$key])){// если существует
                                $unit[$key] = $data[$key];
                            };
                            // дискриминатор
                            $key = "discriminator";// задаём ключ
                            if(isset($data[$key])){// если существует
                                $unit[$key] = $data[$key];
                            };
                            // аватарка
                            $key = "avatar";// задаём ключ
                            if(isset($data[$key])){// если существует
                                $unit[$key] = $data[$key];
                            }else $unit[$key] = "";// по умолчанию
                            // признак бота
                            $key = "bot";// задаём ключ
                            if(isset($data[$key])){// если существует
                                $unit[$key] = $data[$key];
                            }else if(!isset($unit[$key])){// если ещё не задано
                                $unit[$key] = false;// по умолчанию
                            };
                            // список каналов
                            $key = "channels";// задаём ключ
                            if(isset($data[$key])){// если существует
                                $unit[$key] = array();// сбрасываем список
                                for($i = 0, $iLen = count($data[$key]); $i < $iLen; $i++){
                                    $app["fun"]["setCache"]("channel", $data[$key][$i], null, $uid);
                                };
                            };
                        };
                        // присваеваем ссылку на элемент
                        if(!$error){// если нет ошибок
                            $cache = &$unit;
                        };
                        break;
                    case "member":// участник
                        // проверяем наличее данных
                        if(!$error){// если нет ошибок
                            if(// множественное условие
                                isset($data["user"]["id"])
                                and $argLength > $argFirst
                            ){// если проверка пройдена
                                $uid = $data["user"]["id"];
                                $gid = func_get_arg($argFirst);
                            }else $error = 3;
                        };
                        // проверяем значение данных
                        if(!$error){// если нет ошибок
                            if(// множественное условие
                                !empty($uid)
                                and !empty($gid)
                            ){// если проверка пройдена
                            }else $error = 4;
                        };
                        // определяем ссылку на элемента
                        if(!$error){// если нет ошибок
                            $key = "members";// задаём ключ
                            $parent = &$app["fun"]["getCache"]("guild", $gid);
                            if(!is_null($parent)){// если есть родительский элемент
                                if(!isset($parent[$key])) $parent[$key] = array();
                                for($i = 0, $iLen = count($parent[$key]); $i < $iLen; $i++){
                                    if($parent[$key][$i]["user"]["id"] == $uid) break;
                                };
                                if(!isset($parent[$key][$i])) $parent[$key][$i] = array();
                                $unit = &$parent[$key][$i];
                            }else $error = 5;
                        };
                        // формируем структуру
                        if(!$error){// если нет ошибок
                            foreach(array("user") as $key){
                                if(isset($data[$key]) and !isset($unit[$key])){
                                    $unit[$key] = array();
                                };
                            };
                        };
                        // обрабатываем данные
                        if(!$error){// если нет ошибок
                            // никнейм
                            $key = "nick";// задаём ключ
                            if(isset($data[$key])){// если существует
                                $unit[$key] = $data[$key];
                            }else $unit[$key] = "";// по умолчанию
                            // идентификатор для пользователя
                            $key = "id";// задаём ключ
                            if(isset($data["user"][$key])){// если существует
                                $unit["user"][$key] = $data["user"][$key];
                            };
                            // имя для пользователя
                            $key = "username";// задаём ключ
                            if(isset($data["user"][$key])){// если существует
                                $unit["user"][$key] = $data["user"][$key];
                            };
                            // дискриминатор для пользователя
                            $key = "discriminator";// задаём ключ
                            if(isset($data["user"][$key])){// если существует
                                $unit["user"][$key] = $data["user"][$key];
                            };
                            // аватарка для пользователя
                            $key = "avatar";// задаём ключ
                            if(isset($data["user"][$key])){// если существует
                                $unit["user"][$key] = $data["user"][$key];
                            }else if(!isset($unit["user"][$key])){// если не задано
                                $unit["user"][$key] = "";// по умолчанию
                            };
                            // признак бота для пользователя
                            $key = "bot";// задаём ключ
                            if(isset($data["user"][$key])){// если существует
                                $unit["user"][$key] = $data["user"][$key];
                            }else if(!isset($unit["user"][$key])){// если не задано
                                $unit["user"][$key] = false;// по умолчанию
                            };
                            // список ролей
                            $key = "roles";// задаём ключ
                            if(isset($data[$key])){// если существует
                                $unit[$key] = $data[$key];
                            };
                        };
                        // присваеваем ссылку на элемент
                        if(!$error){// если нет ошибок
                            $cache = &$unit;
                        };
                        break;
                    case "role":// роль
                        // проверяем наличее данных
                        if(!$error){// если нет ошибок
                            if(// множественное условие
                                isset($data["id"], $data["permissions"], $data["position"])
                                and $argLength > $argFirst
                            ){// если проверка пройдена
                                $rid = $data["id"];
                                $gid = func_get_arg($argFirst);
                            }else $error = 3;
                        };
                        // проверяем значение данных
                        if(!$error){// если нет ошибок
                            if(// множественное условие
                                !empty($rid)
                                and !empty($gid)
                            ){// если проверка пройдена
                            }else $error = 4;
                        };
                        // определяем ссылку на элемента
                        if(!$error){// если нет ошибок
                            $key = "roles";// задаём ключ
                            $parent = &$app["fun"]["getCache"]("guild", $gid);
                            if(!is_null($parent)){// если есть родительский элемент
                                if(!isset($parent[$key])) $parent[$key] = array();
                                for($i = 0, $iLen = count($parent[$key]); $i < $iLen; $i++){
                                    if($parent[$key][$i]["id"] == $rid) break;
                                };
                                if(!isset($parent[$key][$i])) $parent[$key][$i] = array();
                                $unit = &$parent[$key][$i];
                            }else $error = 5;
                        };
                        // обрабатываем данные
                        if(!$error){// если нет ошибок
                            // идентификатор роли
                            $key = "id";// задаём ключ
                            if(isset($data[$key])){// если существует
                                $unit[$key] = $data[$key];
                            };
                            // разрешения роли
                            $key = "permissions";// задаём ключ
                            if(isset($data[$key])){// если существует
                                $unit[$key] = $data[$key];
                            };
                            // позиция роли
                            $key = "position";// задаём ключ
                            if(isset($data[$key])){// если существует
                                $unit[$key] = $data[$key];
                            };
                            // название роли
                            $key = "name";// задаём ключ
                            if(isset($data[$key])){// если существует
                                $unit[$key] = $data[$key];
                            }else if(!isset($unit[$key])){// если не задано
                                $unit[$key] = "";// по умолчанию
                            };
                        };
                        // сортируем элементы
                        if(!$error){// если нет ошибок
                            $key = "roles";// задаём ключ
                            // выполняем сортировку
                            usort($parent[$key], function($a, $b){// сортировка
                                $value = 0;// начальное значение
                                if(!$value and $a["position"] != $b["position"]) $value = $a["id"] > $b["id"] ? 1 : -1;
                                // возвращаем результат
                                return $value;
                            });
                        };
                        // присваеваем ссылку на элемент
                        if(!$error){// если нет ошибок
                            $cache = &$unit;
                        };
                        break;
                    case "emoji":// эмодзи
                        // проверяем наличее данных
                        if(!$error){// если нет ошибок
                            if(// множественное условие
                                isset($data["id"], $data["name"])
                                and $argLength > $argFirst
                            ){// если проверка пройдена
                                $eid = $data["id"];
                                $gid = func_get_arg($argFirst);
                            }else $error = 3;
                        };
                        // проверяем значение данных
                        if(!$error){// если нет ошибок
                            if(// множественное условие
                                !empty($eid)
                                and !empty($gid)
                            ){// если проверка пройдена
                            }else $error = 4;
                        };
                        // определяем ссылку на элемента
                        if(!$error){// если нет ошибок
                            $key = "emojis";// задаём ключ
                            $parent = &$app["fun"]["getCache"]("guild", $gid);
                            if(!is_null($parent)){// если есть родительский элемент
                                if(!isset($parent[$key])) $parent[$key] = array();
                                for($i = 0, $iLen = count($parent[$key]); $i < $iLen; $i++){
                                    if($parent[$key][$i]["id"] == $eid) break;
                                };
                                if(!isset($parent[$key][$i])) $parent[$key][$i] = array();
                                $unit = &$parent[$key][$i];
                            }else $error = 5;
                        };
                        // обрабатываем данные
                        if(!$error){// если нет ошибок
                            // идентификатор эмодзи
                            $key = "id";// задаём ключ
                            if(isset($data[$key])){// если существует
                                $unit[$key] = $data[$key];
                            };
                            // название эмодзи
                            $key = "name";// задаём ключ
                            if(isset($data[$key])){// если существует
                                $unit[$key] = $data[$key];
                            };
                            // доступность эмодзи
                            $key = "available";// задаём ключ
                            if(isset($data[$key])){// если существует
                                $unit[$key] = $data[$key];
                            }else if(!isset($unit[$key])){// если ещё не задано
                                $unit[$key] = false;// по умолчанию
                            };
                        };
                        // присваеваем ссылку на элемент
                        if(!$error){// если нет ошибок
                            $cache = &$unit;
                        };
                        break;
                    case "channel":// канал
                        // проверяем наличее данных
                        if(!$error){// если нет ошибок
                            if(// множественное условие
                                isset($data["id"], $data["type"])
                                and (isset($data["recipients"][0]["id"]) or isset($data["name"]))
                                and $argLength > $argFirst + 1
                            ){// если проверка пройдена
                                $cid = $data["id"];
                                $gid = func_get_arg($argFirst);
                                $uid = func_get_arg($argFirst + 1);
                            }else $error = 3;
                        };
                        // проверяем значение данных
                        if(!$error){// если нет ошибок
                            if(// множественное условие
                                !empty($cid)
                                and (!empty($gid) xor !empty($uid))
                                and (// дополнительное условие
                                    !empty($gid) and 0 == $data["type"]
                                    or !empty($uid) and 1 == $data["type"]
                                    and 1 == count($data["recipients"])
                                    and $uid == $data["recipients"][0]["id"]
                                )
                            ){// если проверка пройдена
                            }else $error = 4;
                        };
                        // определяем ссылку на элемента
                        if(!$error){// если нет ошибок
                            $key = "channels";// задаём ключ
                            switch(true){// по идентификаторам
                                case !empty($gid): $parent = &$app["fun"]["getCache"]("guild", $gid); break;
                                case !empty($uid): $parent = &$app["fun"]["getCache"]("user", $uid); break;
                                default: $parent = null;
                            };
                            if(!is_null($parent)){// если есть родительский элемент
                                if(!isset($parent[$key])) $parent[$key] = array();
                                for($i = 0, $iLen = count($parent[$key]); $i < $iLen; $i++){
                                    if($parent[$key][$i]["id"] == $cid) break;
                                };
                                if(!isset($parent[$key][$i])) $parent[$key][$i] = array();
                                $unit = &$parent[$key][$i];
                            }else $error = 5;
                        };
                        // формируем структуру
                        if(!$error){// если нет ошибок
                            foreach(array("messages") as $key){
                                if(isset($data[$key]) and !isset($unit[$key])){
                                    $unit[$key] = array();
                                };
                            };
                        };
                        // обрабатываем данные
                        if(!$error){// если нет ошибок
                            // идентификатор
                            $key = "id";// задаём ключ
                            if(isset($data[$key])){// если существует
                                $unit[$key] = $data[$key];
                            };
                            // название
                            $key = "name";// задаём ключ
                            if(isset($data[$key])){// если существует
                                $unit[$key] = $data[$key];
                            };
                            // тема
                            $key = "topic";// задаём ключ
                            if(isset($data[$key])){// если существует
                                $unit[$key] = $data[$key];
                            }else $unit[$key] = "";// по умолчанию
                            // тип
                            $key = "type";// задаём ключ
                            if(isset($data[$key])){// если существует
                                $unit[$key] = $data[$key];
                            };
                            // права доступа
                            $key = "permission_overwrites";// задаём ключ
                            if(isset($data[$key])){// если существует
                                $unit[$key] = $data[$key];
                            };
                            // сообщения
                            $key = "messages";// задаём ключ
                            if(isset($data[$key])){// если существует
                                for($i = 0, $iLen = count($data[$key]); $i < $iLen; $i++){
                                    $data[$key][$i]["embed"] = get_val($data[$key][$i]["embeds"], 0, false);
                                    $app["fun"]["setCache"]("message", $data[$key][$i], $cid, $gid, $uid);
                                };
                            };
                        };
                        // присваеваем ссылку на элемент
                        if(!$error){// если нет ошибок
                            $cache = &$unit;
                        };
                        break;
                    case "message":// сообщение
                        // проверяем наличее данных
                        if(!$error){// если нет ошибок
                            if(// множественное условие
                                isset($data["id"], $data["type"], $data["timestamp"], $data["pinned"], $data["author"]["id"])
                                and $argLength > $argFirst + 2
                            ){// если проверка пройдена
                                $mid = $data["id"];
                                $cid = func_get_arg($argFirst);
                                $gid = func_get_arg($argFirst + 1);
                                $uid = func_get_arg($argFirst + 2);
                            }else $error = 3;
                        };
                        // проверяем значение данных
                        if(!$error){// если нет ошибок
                            if(// множественное условие
                                !empty($mid)
                                and !empty($cid)
                                and (!empty($gid) xor !empty($uid))
                                and !empty($data["timestamp"])
                                and !empty($data["author"]["id"])
                            ){// если проверка пройдена
                            }else $error = 4;
                        };
                        // определяем ссылку на элемента
                        if(!$error){// если нет ошибок
                            $key = "messages";// задаём ключ
                            $parent = &$app["fun"]["getCache"]("channel", $cid, $gid, $uid);
                            if(!is_null($parent)){// если есть родительский элемент
                                $flag = (isset($parent[$key]) or 1 == $parent["type"]);// есть ли необходимые права для выполнения действия
                                if(!$flag) $config = $app["fun"]["getChannelConfig"]($parent, $gid, null);// конфигурация канала
                                if(!$flag) $permission = $app["fun"]["getPermission"]("guild", $session->get("bot", "value"), $parent, $gid);
                                $flag = ($flag or $config["mode"] and $config["game"] == $game and ($permission & $app["val"]["discordMainPermission"]) == $app["val"]["discordMainPermission"]);
                                if($flag){// если есть разрешения или учёт уже ведётся
                                    if(!isset($parent[$key])) $parent[$key] = array();
                                    for($i = 0, $iLen = count($parent[$key]); $i < $iLen; $i++){
                                        if($parent[$key][$i]["id"] == $mid) break;
                                    };
                                    if(!isset($parent[$key][$i])) $parent[$key][$i] = array();
                                    $unit = &$parent[$key][$i];
                                }else $error = 6;
                            }else $error = 5;
                        };
                        // формируем структуру
                        if(!$error){// если нет ошибок
                            foreach(array("author") as $key){
                                if(isset($data[$key]) and !isset($unit[$key])){
                                    $unit[$key] = array();
                                };
                            };
                        };
                        // обрабатываем данные
                        if(!$error){// если нет ошибок
                            // идентификатор
                            $key = "id";// задаём ключ
                            if(isset($data[$key])){// если существует
                                $unit[$key] = $data[$key];
                            };
                            // идентификатор для автора
                            $key = "id";// задаём ключ
                            if(isset($data["author"][$key])){// если существует
                                $unit["author"][$key] = $data["author"][$key];
                            };
                            // имя для автора
                            $key = "username";// задаём ключ
                            if(isset($data["author"][$key])){// если существует
                                $unit["author"][$key] = $data["author"][$key];
                            };
                            // дискриминатор для автора
                            $key = "discriminator";// задаём ключ
                            if(isset($data["author"][$key])){// если существует
                                $unit["author"][$key] = $data["author"][$key];
                            };
                            // аватарка для автора
                            $key = "avatar";// задаём ключ
                            if(isset($data["author"][$key])){// если существует
                                $unit["author"][$key] = $data["author"][$key];
                            }else if(!isset($unit["author"][$key])){// если не задано
                                $unit["author"][$key] = "";// по умолчанию
                            };
                            // признак бота для автора
                            $key = "bot";// задаём ключ
                            if(isset($data["author"][$key])){// если существует
                                $unit["author"][$key] = $data["author"][$key];
                            }else if(!isset($unit["author"][$key])){// если не задано
                                $unit["author"][$key] = false;// по умолчанию
                            };
                            // тип
                            $key = "type";// задаём ключ
                            if(isset($data[$key])){// если существует
                                $unit[$key] = $data[$key];
                            };
                            // флаги
                            $key = "flags";// задаём ключ
                            if(isset($data[$key])){// если существует
                                $unit[$key] = $data[$key];
                            }else if(!isset($unit[$key])){// если ещё не задано
                                $unit[$key] = 0;// по умолчанию
                            };
                            // время создания
                            $key = "timestamp";// задаём ключ
                            if(isset($data[$key])){// если существует
                                $value = $data[$key];// получаем значение
                                if(is_string($value)){// если нужно преобразовать в число
                                    if(32 == mb_strlen($value)){// если указаны миллисекунды
                                        $unit[$key] = strtotime(mb_substr($value, 0, 19) . mb_substr($value, 26));
                                        $unit[$key] += (float) "0" . mb_substr($value, 19, 7);
                                    }else $unit[$key] = strtotime($value);
                                }else $unit[$key] = $value;
                            };
                            // содержимое
                            $key = "content";// задаём ключ
                            if(isset($data[$key])){// если существует
                                $unit[$key] = $data[$key];
                            };
                            // встраиваемый контент
                            $key = "embed";// задаём ключ
                            if(isset($data[$key])){// если существует
                                $unit[$key] = null;// сбрасываем значение
                                foreach(array(// набор элиментов первого уровня
                                    "type", "title", "url", "description", "color", "timestamp",
                                    "author", "image", "thumbnail", "fields", "footer"
                                ) as $i){// пробегаемся по первому уровню
                                    if(isset($data[$key][$i])){// если есть свойство первого уровня
                                        if(!isset($unit[$key])) $unit[$key] = array();
                                        if(is_array($data[$key][$i])){// если составное свойство
                                            foreach(array(// набор элиментов второго уровня
                                                "name", "url", "text", "icon_url",
                                                0,  1,  2,  3,  4,  5,  6,  7,  8,  9,
                                                10, 11, 12, 13, 14, 15, 16, 17, 18, 19
                                            ) as $j){// пробегаемся по первому уровню
                                                if(isset($data[$key][$i][$j])){// если есть свойство второго уровня
                                                    if(!isset($unit[$key][$i])) $unit[$key][$i] = array();
                                                    $unit[$key][$i][$j] = $data[$key][$i][$j];
                                                };
                                            };
                                        }else $unit[$key][$i] = $data[$key][$i];
                                    };
                                };
                            }else if(!isset($unit[$key])){// если не задано
                                $unit[$key] = null;
                            };
                            // закрепление
                            $key = "pinned";// задаём ключ
                            if(isset($data[$key])){// если существует
                                $unit[$key] = $data[$key];
                            };
                            // реакции
                            $key = "reactions";// задаём ключ
                            if(isset($data[$key])){// если существует
                                $items = $data[$key];// получаем список элементов
                                for($i = count($items) - 1; $i > -1; $i--){
                                    $item = $items[$i];// получаем очередной элемент
                                    if(1 == $item["count"] and $item["me"]){// если реакция этого бота
                                        $item["user"] = array("id" => $session->get("bot", "value"), "bot" => true);
                                        $items[$i] = $item;// заменяем элемент
                                    }else break;// останавливаем обработку
                                };
                                if(-1 == $i) $unit[$key] = $items;
                            };
                        };
                        // сортируем и удаляем лишние элементы
                        if(!$error){// если нет ошибок
                            $key = "messages";// задаём ключ
                            // выполняем сортировку
                            usort($parent[$key], function($a, $b){// сортировка
                                $value = 0;// начальное значение
                                if(!$value and $a["timestamp"] != $b["timestamp"]) $value = $a["timestamp"] < $b["timestamp"] ? 1 : -1;
                                if(!$value and $a["id"] != $b["id"]) $value = $a["id"] < $b["id"] ? 1 : -1;
                                // возвращаем результат
                                return $value;
                            });
                            // выполняем удаление
                            $limit = $parent["type"] ? $app["val"]["eventNoticeLimit"] + 1 : $app["val"]["discordMessageLimit"];
                            for($i = count($parent[$key]) - 1; $i >= $limit; $i--){// пробигаемся по лишним элиментам
                                if($unit and $unit["id"] == $parent[$key][$i]["id"]) $unit = null;
                                array_splice($parent[$key], $i, 1);
                            };
                        };
                        // присваеваем ссылку на элемент
                        if(!$error){// если нет ошибок
                            $cache = &$unit;
                        };
                        break;
                    case "reaction":// реакция
                        // проверяем наличее данных
                        if(!$error){// если нет ошибок
                            if(// множественное условие
                                isset($data["emoji"]["name"], $data["user"]["id"])
                                and $argLength > $argFirst + 3
                            ){// если проверка пройдена
                                $rid = array(// составной идентификатор
                                    $data["user"]["id"],
                                    $data["emoji"]["name"],
                                    get_val($data["emoji"], "id", null)
                                );
                                $mid = func_get_arg($argFirst);
                                $cid = func_get_arg($argFirst + 1);
                                $gid = func_get_arg($argFirst + 2);
                                $uid = func_get_arg($argFirst + 3);
                            }else $error = 3;
                        };
                        // проверяем значение данных
                        if(!$error){// если нет ошибок
                            if(// множественное условие
                                !empty($rid[0])
                                and !empty($rid[1])
                                and !empty($mid)
                                and !empty($cid)
                                and (!empty($gid) xor !empty($uid))
                            ){// если проверка пройдена
                            }else $error = 4;
                        };
                        // определяем ссылку на элемента
                        if(!$error){// если нет ошибок
                            $key = "reactions";// задаём ключ
                            $parent = &$app["fun"]["getCache"]("message", $mid, $cid, $gid, $uid);
                            if(!is_null($parent)){// если есть родительский элемент
                                $flag = $parent["author"]["id"] == $session->get("bot", "value");
                                if($flag){// если реакция проставлена на сообщение бота
                                    if(!isset($parent[$key])) $parent[$key] = array();
                                    for($i = 0, $iLen = count($parent[$key]); $i < $iLen; $i++){
                                        if(// множественное условие
                                            $parent[$key][$i]["user"]["id"] == $rid[0]
                                            and (// дополнительное условие
                                                !empty($rid[2])
                                                ? $parent[$key][$i]["emoji"]["id"] == $rid[2]
                                                : $parent[$key][$i]["emoji"]["name"] == $rid[1]
                                            )
                                        ) break;
                                    };
                                    if(!isset($parent[$key][$i])) $parent[$key][$i] = array();
                                    $unit = &$parent[$key][$i];
                                }else $error = 6;
                            }else $error = 5;
                        };
                        // формируем структуру
                        if(!$error){// если нет ошибок
                            foreach(array("emoji", "user") as $key){
                                if(isset($data[$key]) and !isset($unit[$key])){
                                    $unit[$key] = array();
                                };
                            };
                        };
                        // обрабатываем данные
                        if(!$error){// если нет ошибок
                            // идентификатор эмодзи
                            $key = "id";// задаём ключ
                            if(isset($data["emoji"][$key])){// если существует
                                $unit["emoji"][$key] = $data["emoji"][$key];
                            }else if(!isset($unit["emoji"][$key])){// если не задано
                                $unit["emoji"][$key] = null;// по умолчанию
                            };
                            // название эмодзи
                            $key = "name";// задаём ключ
                            if(isset($data["emoji"][$key])){// если существует
                                $unit["emoji"][$key] = $data["emoji"][$key];
                            };
                            // идентификатор для пользователя
                            $key = "id";// задаём ключ
                            if(isset($data["user"][$key])){// если существует
                                $unit["user"][$key] = $data["user"][$key];
                            };
                            // признак бота для пользователя
                            $key = "bot";// задаём ключ
                            if(isset($data["user"][$key])){// если существует
                                $unit["user"][$key] = $data["user"][$key];
                            }else if(!isset($unit["user"][$key])){// если не задано
                                $unit["user"][$key] = false;// по умолчанию
                            };
                        };
                        // присваеваем ссылку на элемент
                        if(!$error){// если нет ошибок
                            $cache = &$unit;
                        };
                        break;
                    default:// не известный тип
                        $error = 2;
                };
            };
            // возвращаем результат
            return $cache;
        },
        "getCache" => function&($type){// получает данные из кеша
        //@param $type {string} - тип данных для кеширования
        //@param ...$id {string} - идентификаторы разных уровней
        //@return {array|null} - ссылка на элемент данныx или null
            global $app;
            $error = 0;
            
            $game = $app["val"]["game"];
            $argLength = func_num_args();
            $argFirst = 1;// первый $id
            $unit = null;// промежуточная ссылка
            $cache = null;// окончательная ссылка
            // загружаем базу данных
            if(!$error){// если нет ошибок
                $session = $app["fun"]["getStorage"]($game, "session", true);
                if(!empty($session)){// если удалось получить доступ к базе данных
                }else $error = 1;
            };
            // выполняем обработку
            if(!$error){// если нет ошибок
                switch($type){// поддерживаемые типы
                    case "guild":// гильдия
                        // проверяем наличее параметров
                        if(!$error){// если нет ошибок
                            if(// множественное условие
                                $argLength > $argFirst
                            ){// если проверка пройдена
                                $gid = func_get_arg($argFirst);
                            }else $error = 3;
                        };
                        // проверяем значение данных
                        if(!$error){// если нет ошибок
                            if(// множественное условие
                                !empty($gid)
                            ){// если проверка пройдена
                            }else $error = 4;
                        };
                        // определяем ссылку на элемента
                        if(!$error){// если нет ошибок
                            $key = "guilds";// задаём ключ
                            $parent = &$app["cache"];
                            if(!is_null($parent)){// если есть родительский элемент
                                if(!isset($parent[$key])) $parent[$key] = array();
                                for($i = 0, $iLen = count($parent[$key]); $i < $iLen; $i++){
                                    if($parent[$key][$i]["id"] == $gid) break;
                                };
                                if(!isset($parent[$key][$i])){// если не найден
                                    if($app["val"]["useFileCache"]){// если используются
                                        $item = $app["fun"]["getFileCache"]($game, $key, $gid);
                                        if($item) $parent[$key][$i] = $item;
                                        if($item) $unit = &$parent[$key][$i];
                                    };
                                }else $unit = &$parent[$key][$i];
                            }else $error = 5;
                        };
                        // получаем элемент через api
                        if(!$error and !$unit){// если нужно выполнить
                            $uri = "/guilds/" . $gid;
                            $data = $app["fun"]["apiRequest"]("get", $uri, null, $code);
                            if(200 == $code){// если запрос выполнен успешно
                                $unit = &$app["fun"]["setCache"]($type, $data);
                            }else $error = 6;
                        };
                        // получаем роли через api
                        $key = "roles";// задаём ключ
                        if(!$error and $unit and !isset($unit[$key])){// если нужно выполнить
                            $uri = "/guilds/" . $gid . "/" . $key;
                            $data = $app["fun"]["apiRequest"]("get", $uri, null, $code);
                            if(200 == $code){// если запрос выполнен успешно
                                $unit[$key] = array();// сбрасываем список
                                for($i = 0, $iLen = count($data); $i < $iLen; $i++){
                                    $app["fun"]["setCache"]("role", $data[$i], $gid);
                                };
                            }else $error = 7;
                        };
                        // получаем эмодзи через api
                        $key = "emojis";// задаём ключ
                        if(!$error and $unit and !isset($unit[$key])){// если нужно выполнить
                            $uri = "/guilds/" . $gid . "/" . $key;
                            $data = $app["fun"]["apiRequest"]("get", $uri, null, $code);
                            if(200 == $code){// если запрос выполнен успешно
                                $unit[$key] = array();// сбрасываем список
                                for($i = 0, $iLen = count($data); $i < $iLen; $i++){
                                    $app["fun"]["setCache"]("emoji", $data[$i], $gid);
                                };
                            }else $error = 8;
                        };
                        // получаем каналы через api
                        $key = "channels";// задаём ключ
                        if(!$error and $unit and !isset($unit[$key])){// если нужно выполнить
                            $uri = "/guilds/" . $gid . "/" . $key;
                            $data = $app["fun"]["apiRequest"]("get", $uri, null, $code);
                            if(200 == $code){// если запрос выполнен успешно
                                $unit[$key] = array();// сбрасываем список
                                for($i = 0, $iLen = count($data); $i < $iLen; $i++){
                                    $app["fun"]["setCache"]("channel", $data[$i], $gid, null);
                                };
                            }else $error = 9;
                        };
                        // присваеваем ссылку на элемент
                        if(!$error){// если нет ошибок
                            $cache = &$unit;
                        };
                        break;
                    case "user":// пользователь
                        // проверяем наличее параметров
                        if(!$error){// если нет ошибок
                            if(// множественное условие
                                $argLength > $argFirst
                            ){// если проверка пройдена
                                $uid = func_get_arg($argFirst);
                            }else $error = 3;
                        };
                        // проверяем значение данных
                        if(!$error){// если нет ошибок
                            if(// множественное условие
                                !empty($uid)
                            ){// если проверка пройдена
                            }else $error = 4;
                        };
                        // определяем ссылку на элемента
                        if(!$error){// если нет ошибок
                            $key = "users";// задаём ключ
                            $parent = &$app["cache"];
                            if(!is_null($parent)){// если есть родительский элемент
                                if(!isset($parent[$key])) $parent[$key] = array();
                                for($i = 0, $iLen = count($parent[$key]); $i < $iLen; $i++){
                                    if($parent[$key][$i]["id"] == $uid) break;
                                };
                                if(!isset($parent[$key][$i])){// если не найден
                                    if($app["val"]["useFileCache"]){// если используются
                                        $item = $app["fun"]["getFileCache"]($game, $key, $uid);
                                        if($item) $parent[$key][$i] = $item;
                                        if($item) $unit = &$parent[$key][$i];
                                    };
                                }else $unit = &$parent[$key][$i];
                            }else $error = 5;
                        };
                        // получаем элемент и каналы через api
                        $key = "channels";// задаём ключ
                        if(!$error and !isset($unit[$key])){// если нужно выполнить
                            $uri = "/users/" . $session->get("bot", "value") . "/" . $key;
                            $data = array("recipient_id" => $uid);
                            $data = $app["fun"]["apiRequest"]("post", $uri, $data, $code);
                            if(200 == $code){// если запрос выполнен успешно
                                $unit = &$app["fun"]["setCache"]($type, $data["recipients"][0]);
                                $unit[$key] = array();// сбрасываем список
                                $app["fun"]["setCache"]("channel", $data, null, $uid);
                            }else $error = 8;
                        };
                        // присваеваем ссылку на элемент
                        if(!$error){// если нет ошибок
                            $cache = &$unit;
                        };
                        break;
                    case "member":// участник
                        // проверяем наличее параметров
                        if(!$error){// если нет ошибок
                            if(// множественное условие
                                $argLength > $argFirst + 1
                            ){// если проверка пройдена
                                $uid = func_get_arg($argFirst);
                                $gid = func_get_arg($argFirst + 1);
                            }else $error = 3;
                        };
                        // проверяем значение данных
                        if(!$error){// если нет ошибок
                            if(// множественное условие
                                !empty($uid)
                                and !empty($gid)
                            ){// если проверка пройдена
                            }else $error = 4;
                        };
                        // определяем ссылку на элемента
                        if(!$error){// если нет ошибок
                            $key = "members";// задаём ключ
                            $parent = &$app["fun"]["getCache"]("guild", $gid);
                            if(!is_null($parent)){// если есть родительский элемент
                                if(!isset($parent[$key])) $parent[$key] = array();
                                for($i = 0, $iLen = count($parent[$key]); $i < $iLen; $i++){
                                    if($parent[$key][$i]["user"]["id"] == $uid) break;
                                };
                                if(isset($parent[$key][$i])) $unit = &$parent[$key][$i];
                            }else $error = 5;
                        };
                        // получаем элемент через api
                        if(!$error and !$unit){// если нужно выполнить
                            $uri = "/guilds/" . $gid . "/members/" . $uid;
                            $data = $app["fun"]["apiRequest"]("get", $uri, null, $code);
                            if(200 == $code){// если запрос выполнен успешно
                                $unit = &$app["fun"]["setCache"]($type, $data, $gid);
                            }else $error = 6;
                        };
                        // присваеваем ссылку на элемент
                        if(!$error){// если нет ошибок
                            $cache = &$unit;
                        };
                        break;
                    case "role":// роль
                        // проверяем наличее параметров
                        if(!$error){// если нет ошибок
                            if(// множественное условие
                                $argLength > $argFirst + 1
                            ){// если проверка пройдена
                                $rid = func_get_arg($argFirst);
                                $gid = func_get_arg($argFirst + 1);
                            }else $error = 3;
                        };
                        // проверяем значение данных
                        if(!$error){// если нет ошибок
                            if(// множественное условие
                                !empty($rid)
                                and !empty($gid)
                            ){// если проверка пройдена
                            }else $error = 4;
                        };
                        // определяем ссылку на элемент
                        if(!$error){// если нет ошибок
                            $key = "roles";// задаём ключ
                            $parent = &$app["fun"]["getCache"]("guild", $gid);
                            if(!is_null($parent)){// если есть родительский элемент
                                if(!isset($parent[$key])) $parent[$key] = array();
                                for($i = 0, $iLen = count($parent[$key]); $i < $iLen; $i++){
                                    if($parent[$key][$i]["id"] == $rid) break;
                                };
                                if(isset($parent[$key][$i])) $unit = &$parent[$key][$i];
                            }else $error = 5;
                        };
                        // получаем элемент через api
                        if(!$error and !$unit){// если нужно выполнить
                            $error = 6;// получение не возможно
                        };
                        // присваеваем ссылку на элемент
                        if(!$error){// если нет ошибок
                            $cache = &$unit;
                        };
                        break;
                    case "emoji":// эмодзи
                        // проверяем наличее параметров
                        if(!$error){// если нет ошибок
                            if(// множественное условие
                                $argLength > $argFirst + 1
                            ){// если проверка пройдена
                                $eid = func_get_arg($argFirst);
                                $gid = func_get_arg($argFirst + 1);
                            }else $error = 3;
                        };
                        // проверяем значение данных
                        if(!$error){// если нет ошибок
                            if(// множественное условие
                                !empty($eid)
                                and !empty($gid)
                            ){// если проверка пройдена
                            }else $error = 4;
                        };
                        // определяем ссылку на элемент
                        if(!$error){// если нет ошибок
                            $key = "emojis";// задаём ключ
                            $parent = &$app["fun"]["getCache"]("guild", $gid);
                            if(!is_null($parent)){// если есть родительский элемент
                                if(!isset($parent[$key])) $parent[$key] = array();
                                for($i = 0, $iLen = count($parent[$key]); $i < $iLen; $i++){
                                    if($parent[$key][$i]["id"] == $eid) break;
                                };
                                if(isset($parent[$key][$i])) $unit = &$parent[$key][$i];
                            }else $error = 5;
                        };
                        // получаем элемент через api
                        if(!$error and !$unit){// если нужно выполнить
                            $error = 6;// получение не возможно
                        };
                        // присваеваем ссылку на элемент
                        if(!$error){// если нет ошибок
                            $cache = &$unit;
                        };
                        break;
                    case "channel":// канал
                        // проверяем наличее параметров
                        if(!$error){// если нет ошибок
                            if(// множественное условие
                                $argLength > $argFirst + 2
                            ){// если проверка пройдена
                                $cid = func_get_arg($argFirst);
                                $gid = func_get_arg($argFirst + 1);
                                $uid = func_get_arg($argFirst + 2);
                            }else $error = 3;
                        };
                        // проверяем значение данных
                        if(!$error){// если нет ошибок
                            if(// множественное условие
                                !empty($cid)
                                and (!empty($gid) xor !empty($uid))
                            ){// если проверка пройдена
                            }else $error = 4;
                        };
                        // определяем ссылку на элемента
                        if(!$error){// если нет ошибок
                            $key = "channels";// задаём ключ
                            switch(true){// по идентификаторам
                                case !empty($gid): $parent = &$app["fun"]["getCache"]("guild", $gid); break;
                                case !empty($uid): $parent = &$app["fun"]["getCache"]("user", $uid); break;
                                default: $parent = null;
                            };
                            if(!is_null($parent)){// если есть родительский элемент
                                if(!isset($parent[$key])) $parent[$key] = array();
                                for($i = 0, $iLen = count($parent[$key]); $i < $iLen; $i++){
                                    if($parent[$key][$i]["id"] == $cid) break;
                                };
                                if(isset($parent[$key][$i])) $unit = &$parent[$key][$i];
                            }else $error = 5;
                        };
                        // получаем элемент через api
                        if(!$error and !$unit){// если нужно выполнить
                            $uri = "/channels/" . $cid;
                            $data = $app["fun"]["apiRequest"]("get", $uri, null, $code);
                            if(200 == $code){// если запрос выполнен успешно
                                $unit = &$app["fun"]["setCache"]($type, $data);
                            }else $error = 6;
                        };
                        // получаем сообщения через api
                        $key = "messages";// задаём ключ
                        if(!$error and $unit and !isset($unit[$key])){// если нужно выполнить
                            $flag = 1 == $unit["type"];// есть ли необходимые права для выполнения действия
                            if(!$flag) $config = $app["fun"]["getChannelConfig"]($unit, $gid, null);// конфигурация канала
                            if(!$flag) $permission = $app["fun"]["getPermission"]("guild", $session->get("bot", "value"), $unit, $gid);
                            $flag = ($flag or $config["mode"] and $config["game"] == $game and ($permission & $app["val"]["discordMainPermission"]) == $app["val"]["discordMainPermission"]);
                            if($flag){// если проверка пройдена
                                $limit = $unit["type"] ? $app["val"]["eventNoticeLimit"] + 1 : $app["val"]["discordMessageLimit"];
                                $uri = "/channels/" . $cid  . "/" . $key;
                                $data = array("limit" => $limit);
                                $data = $app["fun"]["apiRequest"]("get", $uri, $data, $code);
                                if(200 == $code){// если запрос выполнен успешно
                                    if(!isset($unit[$key])) $unit[$key] = array();
                                    $key = "reactions";// изменяем ключ
                                    for($i = 0, $iLen = count($data); $i < $iLen; $i++){
                                        if(!isset($data[$i][$key])) $data[$i][$key] = array();
                                        $data[$i]["embed"] = get_val($data[$i]["embeds"], 0, false);
                                        $app["fun"]["setCache"]("message", $data[$i], $cid, $gid, $uid);
                                    };
                                }else $error = 7;
                            };
                        };
                        // присваеваем ссылку на элемент
                        if(!$error){// если нет ошибок
                            $cache = &$unit;
                        };
                        break;
                    case "message":// сообщение
                        // проверяем наличее параметров
                        if(!$error){// если нет ошибок
                            if(// множественное условие
                                $argLength > $argFirst + 3
                            ){// если проверка пройдена
                                $mid = func_get_arg($argFirst);
                                $cid = func_get_arg($argFirst + 1);
                                $gid = func_get_arg($argFirst + 2);
                                $uid = func_get_arg($argFirst + 3);
                            }else $error = 3;
                        };
                        // проверяем значение данных
                        if(!$error){// если нет ошибок
                            if(// множественное условие
                                !empty($mid)
                                and !empty($cid)
                                and (!empty($gid) xor !empty($uid))
                            ){// если проверка пройдена
                            }else $error = 4;
                        };
                        // определяем ссылку на элемента
                        if(!$error){// если нет ошибок
                            $key = "messages";// задаём ключ
                            $parent = &$app["fun"]["getCache"]("channel", $cid, $gid, $uid);
                            if(!is_null($parent)){// если есть родительский элемент
                                $flag = (isset($parent[$key]) or 1 == $parent["type"]);// есть ли необходимые права для выполнения действия
                                if(!$flag) $config = $app["fun"]["getChannelConfig"]($parent, $gid, null);// конфигурация канала
                                if(!$flag) $permission = $app["fun"]["getPermission"]("guild", $session->get("bot", "value"), $parent, $gid);
                                $flag = ($flag or $config["mode"] and $config["game"] == $game and ($permission & $app["val"]["discordMainPermission"]) == $app["val"]["discordMainPermission"]);
                                if($flag){// если есть разрешения или учёт уже ведётся
                                    if(!isset($parent[$key])) $parent[$key] = array();
                                    for($i = 0, $iLen = count($parent[$key]); $i < $iLen; $i++){
                                        if($parent[$key][$i]["id"] == $mid) break;
                                    };
                                    if(isset($parent[$key][$i])) $unit = &$parent[$key][$i];
                                }else $error = 6;
                            }else $error = 5;
                        };
                        // получаем элемент через api
                        if(!$error and !$unit){// если нужно выполнить
                            $uri = "/channels/" . $cid . "/messages/" . $mid;
                            $data = $app["fun"]["apiRequest"]("get", $uri, null, $code);
                            if(200 == $code){// если запрос выполнен успешно
                                $unit = &$app["fun"]["setCache"]($type, $data, $cid, $gid, $uid);
                            }else $error = 6;
                        };
                        // получаем список реакций через api
                        $key = "reactions";// задаём ключ
                        if(!$error and $unit and !isset($unit[$key])){// если нужно выполнить
                            $flag = $unit["author"]["id"] == $session->get("bot", "value");
                            if($flag){// если проверка пройдена
                                $uri = "/channels/" . $cid . "/messages/" . $mid;
                                if(!isset($data)) $data = $app["fun"]["apiRequest"]("get", $uri, null, $code);
                                if(200 == $code){// если запрос выполнен успешно
                                    if(!isset($unit[$key])) $unit[$key] = array();
                                    if(isset($data[$key])){// если есть данные
                                        $items = $data[$key];// получаем список элементов
                                        for($i = 0, $iLen = count($items); $i < $iLen and !$error; $i++){
                                            $item = $items[$i];// получаем очередной элемент
                                            if(1 == $item["count"] and $item["me"]){// если реакция этого бота
                                                $item["user"] = array("id" => $session->get("bot", "value"), "bot" => true);
                                                $app["fun"]["setCache"]("reaction", $item, $mid, $cid, $gid, $uid);
                                            }else{// если реакция участника или другого бота
                                                // получаем пользователей реакции через api
                                                $rid = array("", $item["emoji"]["name"], $item["emoji"]["id"]);
                                                $value = urlencode(!empty($rid[2]) ? implode(":", $rid) : $rid[1]);
                                                $uri = "/channels/" . $cid . "/messages/" . $mid . "/reactions/" . $value;
                                                $data = $app["fun"]["apiRequest"]("get", $uri, null, $code);
                                                if(200 == $code){// если запрос выполнен успешно
                                                    for($j = 0, $jLen = count($data); $j < $jLen; $j++){
                                                        $item["user"] = $data[$j];// приводим к единому виду
                                                        $app["fun"]["setCache"]("reaction", $item, $mid, $cid, $gid, $uid);
                                                    };
                                                }else $error = 7;
                                            };
                                        };
                                    };
                                }else $error = 6;
                            };
                        };
                        // присваеваем ссылку на элемент
                        if(!$error){// если нет ошибок
                            $cache = &$unit;
                        };
                        break;
                    case "reaction":// реакция
                        // проверяем наличее параметров
                        if(!$error){// если нет ошибок
                            if(// множественное условие
                                $argLength > $argFirst + 4
                            ){// если проверка пройдена
                                $rid = func_get_arg($argFirst);
                                $mid = func_get_arg($argFirst + 1);
                                $cid = func_get_arg($argFirst + 2);
                                $gid = func_get_arg($argFirst + 3);
                                $uid = func_get_arg($argFirst + 4);
                            }else $error = 3;
                        };
                        // проверяем значение данных
                        if(!$error){// если нет ошибок
                            if(// множественное условие
                                !empty($rid[0])
                                and !empty($rid[1])
                                and !empty($mid)
                                and !empty($cid)
                                and (!empty($gid) xor !empty($uid))
                            ){// если проверка пройдена
                            }else $error = 4;
                        };
                        // определяем ссылку на элемента
                        if(!$error){// если нет ошибок
                            $key = "reactions";// задаём ключ
                            $parent = &$app["fun"]["getCache"]("message", $mid, $cid, $gid, $uid);
                            if(!is_null($parent)){// если есть родительский элемент
                                if(!isset($parent[$key])) $parent[$key] = array();
                                for($i = 0, $iLen = count($parent[$key]); $i < $iLen; $i++){
                                    if(// множественное условие
                                        $parent[$key][$i]["user"]["id"] == $rid[0]
                                        and (// дополнительное условие
                                            !empty($rid[2])
                                            ? $parent[$key][$i]["emoji"]["id"] == $rid[2]
                                            : $parent[$key][$i]["emoji"]["name"] == $rid[1]
                                        )
                                    ) break;
                                };
                                if(isset($parent[$key][$i])) $unit = &$parent[$key][$i];
                            }else $error = 5;
                        };
                        // получаем элемент через api
                        if(!$error and !$unit){// если нужно выполнить
                            $error = 6;// получение не возможно
                        };
                        // присваеваем ссылку на элемент
                        if(!$error){// если нет ошибок
                            $cache = &$unit;
                        };
                        break;
                    default:// не известный тип
                        $error = 2;
                };
            };
            // возвращаем результат
            return $cache;
        },
        "delCache" => function($type){// удаляет данные из кеша
        //@param $type {string} - тип данных для кеширования
        //@param ...$id {string} - идентификаторы разных уровней
        //@return {array|null} - элемент данныx или null
            global $app;
            $error = 0;
            
            $game = $app["val"]["game"];
            $argLength = func_num_args();
            $argFirst = 1;// первый $id
            $unit = null;// промежуточная ссылка
            $cache = null;// окончательная ссылка
            // загружаем базу данных
            if(!$error){// если нет ошибок
                $session = $app["fun"]["getStorage"]($game, "session", true);
                if(!empty($session)){// если удалось получить доступ к базе данных
                }else $error = 1;
            };
            // выполняем обработку
            if(!$error){// если нет ошибок
                switch($type){// поддерживаемые типы
                    case "guild":// гильдия
                        // проверяем наличее параметров
                        if(!$error){// если нет ошибок
                            if(// множественное условие
                                $argLength > $argFirst
                            ){// если проверка пройдена
                                $gid = func_get_arg($argFirst);
                            }else $error = 3;
                        };
                        // проверяем значение данных
                        if(!$error){// если нет ошибок
                            if(// множественное условие
                                !empty($gid)
                            ){// если проверка пройдена
                            }else $error = 4;
                        };
                        // ищем ссылку на элемента
                        if(!$error){// если нет ошибок
                            $key = "guilds";// задаём ключ
                            $parent = &$app["cache"];
                            if(!is_null($parent)){// если есть родительский элемент
                                if(isset($parent[$key])){// если есть массив элементов у родителя
                                    for($i = 0, $iLen = count($parent[$key]); $i < $iLen; $i++){
                                        if($parent[$key][$i]["id"] == $gid) break;
                                    };
                                    if(isset($parent[$key][$i])) $unit = &$parent[$key][$i];
                                };
                                if($app["val"]["useFileCache"]){// если используются
                                    $app["fun"]["delFileCache"]($game, $key, $gid);
                                };
                            }else $error = 5;
                        };
                        // удаляем ссылку на элемента
                        if(!$error){// если нет ошибок
                            $key = "guilds";// задаём ключ
                            if($unit){// если элемент существует
                                $cache = array_splice($parent[$key], $i, 1)[0];
                            }else $error = 6;
                        };
                        break;
                    case "user":// пользователь
                        // проверяем наличее параметров
                        if(!$error){// если нет ошибок
                            if(// множественное условие
                                $argLength > $argFirst
                            ){// если проверка пройдена
                                $uid = func_get_arg($argFirst);
                            }else $error = 3;
                        };
                        // проверяем значение данных
                        if(!$error){// если нет ошибок
                            if(// множественное условие
                                !empty($uid)
                            ){// если проверка пройдена
                            }else $error = 4;
                        };
                        // ищем ссылку на элемента
                        if(!$error){// если нет ошибок
                            $key = "users";// задаём ключ
                            $parent = &$app["cache"];
                            if(!is_null($parent)){// если есть родительский элемент
                                if(isset($parent[$key])){// если есть массив элементов у родителя
                                    for($i = 0, $iLen = count($parent[$key]); $i < $iLen; $i++){
                                        if($parent[$key][$i]["id"] == $uid) break;
                                    };
                                    if(isset($parent[$key][$i])) $unit = &$parent[$key][$i];
                                };
                                if($app["val"]["useFileCache"]){// если используются
                                    $app["fun"]["delFileCache"]($game, $key, $uid);
                                };
                            }else $error = 5;
                        };
                        // удаляем ссылку на элемента
                        if(!$error){// если нет ошибок
                            $key = "users";// задаём ключ
                            if($unit){// если элемент существует
                                $cache = array_splice($parent[$key], $i, 1)[0];
                            }else $error = 6;
                        };
                        break;
                    case "member":// участник
                        // проверяем наличее параметров
                        if(!$error){// если нет ошибок
                            if(// множественное условие
                                $argLength > $argFirst + 1
                            ){// если проверка пройдена
                                $uid = func_get_arg($argFirst);
                                $gid = func_get_arg($argFirst + 1);
                            }else $error = 3;
                        };
                        // проверяем значение данных
                        if(!$error){// если нет ошибок
                            if(// множественное условие
                                !empty($uid)
                                and !empty($gid)
                            ){// если проверка пройдена
                            }else $error = 4;
                        };
                        // ищем ссылку на элемента
                        if(!$error){// если нет ошибок
                            $key = "members";// задаём ключ
                            $parent = &$app["fun"]["getCache"]("guild", $gid);
                            if(!is_null($parent)){// если есть родительский элемент
                                if(isset($parent[$key])){// если есть массив элементов у родителя
                                    for($i = 0, $iLen = count($parent[$key]); $i < $iLen; $i++){
                                        if($parent[$key][$i]["user"]["id"] == $uid) break;
                                    };
                                    if(isset($parent[$key][$i])) $unit = &$parent[$key][$i];
                                };
                            }else $error = 5;
                        };
                        // удаляем ссылку на элемента
                        if(!$error){// если нет ошибок
                            $key = "members";// задаём ключ
                            if($unit){// если элемент существует
                                $cache = array_splice($parent[$key], $i, 1)[0];
                            }else $error = 6;
                        };
                        break;
                    case "role":// роль
                        // проверяем наличее параметров
                        if(!$error){// если нет ошибок
                            if(// множественное условие
                                $argLength > $argFirst + 1
                            ){// если проверка пройдена
                                $rid = func_get_arg($argFirst);
                                $gid = func_get_arg($argFirst + 1);
                            }else $error = 3;
                        };
                        // проверяем значение данных
                        if(!$error){// если нет ошибок
                            if(// множественное условие
                                !empty($rid)
                                and !empty($gid)
                            ){// если проверка пройдена
                            }else $error = 4;
                        };
                        // ищем ссылку на элемента
                        if(!$error){// если нет ошибок
                            $key = "roles";// задаём ключ
                            $parent = &$app["fun"]["getCache"]("guild", $gid);
                            if(!is_null($parent)){// если есть родительский элемент
                                if(isset($parent[$key])){// если есть массив элементов у родителя
                                    for($i = 0, $iLen = count($parent[$key]); $i < $iLen; $i++){
                                        if($parent[$key][$i]["id"] == $rid) break;
                                    };
                                    if(isset($parent[$key][$i])) $unit = &$parent[$key][$i];
                                };
                            }else $error = 5;
                        };
                        // удаляем ссылку на элемента
                        if(!$error){// если нет ошибок
                            $key = "roles";// задаём ключ
                            if($unit){// если элемент существует
                                $cache = array_splice($parent[$key], $i, 1)[0];
                            }else $error = 6;
                        };
                        break;
                    case "emoji":// эмодзи
                        // проверяем наличее параметров
                        if(!$error){// если нет ошибок
                            if(// множественное условие
                                $argLength > $argFirst + 1
                            ){// если проверка пройдена
                                $eid = func_get_arg($argFirst);
                                $gid = func_get_arg($argFirst + 1);
                            }else $error = 3;
                        };
                        // проверяем значение данных
                        if(!$error){// если нет ошибок
                            if(// множественное условие
                                !empty($eid)
                                and !empty($gid)
                            ){// если проверка пройдена
                            }else $error = 4;
                        };
                        // ищем ссылку на элемента
                        if(!$error){// если нет ошибок
                            $key = "emojis";// задаём ключ
                            $parent = &$app["fun"]["getCache"]("guild", $gid);
                            if(!is_null($parent)){// если есть родительский элемент
                                if(isset($parent[$key])){// если есть массив элементов у родителя
                                    for($i = 0, $iLen = count($parent[$key]); $i < $iLen; $i++){
                                        if($parent[$key][$i]["id"] == $eid) break;
                                    };
                                    if(isset($parent[$key][$i])) $unit = &$parent[$key][$i];
                                };
                            }else $error = 5;
                        };
                        // удаляем ссылку на элемента
                        if(!$error){// если нет ошибок
                            $key = "emojis";// задаём ключ
                            if($unit){// если элемент существует
                                $cache = array_splice($parent[$key], $i, 1)[0];
                            }else $error = 6;
                        };
                        break;
                    case "channel":// канал
                        // проверяем наличее параметров
                        if(!$error){// если нет ошибок
                            if(// множественное условие
                                $argLength > $argFirst + 2
                            ){// если проверка пройдена
                                $cid = func_get_arg($argFirst);
                                $gid = func_get_arg($argFirst + 1);
                                $uid = func_get_arg($argFirst + 2);
                            }else $error = 3;
                        };
                        // проверяем значение данных
                        if(!$error){// если нет ошибок
                            if(// множественное условие
                                !empty($cid)
                                and (!empty($gid) xor !empty($uid))
                            ){// если проверка пройдена
                            }else $error = 4;
                        };
                        // ищем ссылку на элемента
                        if(!$error){// если нет ошибок
                            $key = "channels";// задаём ключ
                            switch(true){// по идентификаторам
                                case !empty($gid): $parent = &$app["fun"]["getCache"]("guild", $gid); break;
                                case !empty($uid): $parent = &$app["fun"]["getCache"]("user", $uid); break;
                                default: $parent = null;
                            };
                            if(!is_null($parent)){// если есть родительский элемент
                                if(isset($parent[$key])){// если есть массив элементов у родителя
                                    for($i = 0, $iLen = count($parent[$key]); $i < $iLen; $i++){
                                        if($parent[$key][$i]["id"] == $cid) break;
                                    };
                                    if(isset($parent[$key][$i])) $unit = &$parent[$key][$i];
                                };
                            }else $error = 5;
                        };
                        // удаляем ссылку на элемента
                        if(!$error){// если нет ошибок
                            $key = "channels";// задаём ключ
                            if($unit){// если элемент существует
                                $cache = array_splice($parent[$key], $i, 1)[0];
                            }else $error = 6;
                        };
                        break;
                    case "message":// сообщение
                        // проверяем наличее параметров
                        if(!$error){// если нет ошибок
                            if(// множественное условие
                                $argLength > $argFirst + 3
                            ){// если проверка пройдена
                                $mid = func_get_arg($argFirst);
                                $cid = func_get_arg($argFirst + 1);
                                $gid = func_get_arg($argFirst + 2);
                                $uid = func_get_arg($argFirst + 3);
                            }else $error = 3;
                        };
                        // проверяем значение данных
                        if(!$error){// если нет ошибок
                            if(// множественное условие
                                !empty($mid)
                                and !empty($cid)
                                and (!empty($gid) xor !empty($uid))
                            ){// если проверка пройдена
                            }else $error = 4;
                        };
                        // ищем ссылку на элемента
                        if(!$error){// если нет ошибок
                            $key = "messages";// задаём ключ
                            $parent = &$app["fun"]["getCache"]("channel", $cid, $gid, $uid);
                            if(!is_null($parent)){// если есть родительский элемент
                                if(isset($parent[$key])){// если есть массив элементов у родителя
                                    for($i = 0, $iLen = count($parent[$key]); $i < $iLen; $i++){
                                        if($parent[$key][$i]["id"] == $mid) break;
                                    };
                                    if(isset($parent[$key][$i])) $unit = &$parent[$key][$i];
                                };
                            }else $error = 5;
                        };
                        // удаляем ссылку на элемента
                        if(!$error){// если нет ошибок
                            $key = "messages";// задаём ключ
                            if($unit){// если элемент существует
                                $cache = array_splice($parent[$key], $i, 1)[0];
                            }else $error = 6;
                        };
                        break;
                    case "reaction":// реакция
                        // проверяем наличее параметров
                        if(!$error){// если нет ошибок
                            if(// множественное условие
                                $argLength > $argFirst + 4
                            ){// если проверка пройдена
                                $rid = func_get_arg($argFirst);
                                $mid = func_get_arg($argFirst + 1);
                                $cid = func_get_arg($argFirst + 2);
                                $gid = func_get_arg($argFirst + 3);
                                $uid = func_get_arg($argFirst + 4);
                            }else $error = 3;
                        };
                        // проверяем значение данных
                        if(!$error){// если нет ошибок
                            if(// множественное условие
                                !empty($rid[0])
                                and !empty($rid[1])
                                and !empty($mid)
                                and !empty($cid)
                                and (!empty($gid) xor !empty($uid))
                            ){// если проверка пройдена
                            }else $error = 4;
                        };
                        // ищем ссылку на элемента
                        if(!$error){// если нет ошибок
                            $key = "reactions";// задаём ключ
                            $parent = &$app["fun"]["getCache"]("message", $mid, $cid, $gid, $uid);
                            if(!is_null($parent)){// если есть родительский элемент
                                if(isset($parent[$key])){// если есть массив элементов у родителя
                                    for($i = 0, $iLen = count($parent[$key]); $i < $iLen; $i++){
                                        if(// множественное условие
                                            $parent[$key][$i]["user"]["id"] == $rid[0]
                                            and (// дополнительное условие
                                                !empty($rid[2])
                                                ? $parent[$key][$i]["emoji"]["id"] == $rid[2]
                                                : $parent[$key][$i]["emoji"]["name"] == $rid[1]
                                            )
                                        ) break;
                                    };
                                    if(isset($parent[$key][$i])) $unit = &$parent[$key][$i];
                                };
                            }else $error = 5;
                        };
                        // удаляем ссылку на элемента
                        if(!$error){// если нет ошибок
                            $key = "reactions";// задаём ключ
                            if($unit){// если элемент существует
                                $cache = array_splice($parent[$key], $i, 1)[0];
                            }else $error = 6;
                        };
                        break;
                    default:// не известный тип
                        $error = 2;
                };
            };
            // возвращаем результат
            return $cache;
        },
        "setFileCache" => function($group, $name, $id, $data){// добавляет данные в файловый кеш
        //@param $group {string} - группа файлового кеша
        //@param $name {string} - название файлового кеша
        //@param $id {string} - идентификатор файлового кеша
        //@param $data {array} - данные для кеширования
        //@return {boolean} - успешность кеширования данных
            global $app;
            $error = 0;

            // проверяем группу
            if(!$error){// если нет ошибок
                if($group){// если проверка пройдена
                }else $error = 1;
            };
            // проверяем название
            if(!$error){// если нет ошибок
                if($name){// если проверка пройдена
                }else $error = 2;
            };
            // проверяем идентификатор
            if(!$error){// если нет ошибок
                if($id){// если проверка пройдена
                }else $error = 3;
            };
            // проверяем данных
            if(!$error){// если нет ошибок
                if($data){// если проверка пройдена
                }else $error = 4;
            };
            // преобразовываем данных в содержимое
            if(!$error){// если нет ошибок
                $content = json_encode($data, JSON_UNESCAPED_UNICODE);
                if($content){// если не пусто
                }else $error = 5;
            };
            // создаём каталог группы
            if(!$error){// если нет ошибок
                $path = template($app["val"]["cacheUrl"], array("group" => $group));
                if(is_dir($path) or @mkdir($path)){// если удалось создать
                }else $error = 6;
            };
            // создаём каталог названия
            if(!$error){// если нет ошибок
                $path = template($app["val"]["cacheUrl"], array("group" => $group, "name" => $name));
                if(is_dir($path) or @mkdir($path)){// если удалось создать
                }else $error = 7;
            };
            // записываем содержимое в файл
            if(!$error){// если нет ошибок
                $path = template($app["val"]["cacheUrl"], array("group" => $group, "name" => $name, "id" => $id));
                $file = new File($path);// используем специализированный класс
                if($file->write($content, false)){// если удалось записать
                }else $error = 8;
            };
            // возвращаем результат
            return !$error;
        },
        "getFileCache" => function($group, $name, $id){// получает данные из файлового кеша
        //@param $group {string} - группа файлового кеша
        //@param $name {string} - название файлового кеша
        //@param $id {string} - идентификатор файлового кеша
        //@return {array|null} - полученные данные
            global $app;
            $error = 0;
            
            $data = null;
            // проверяем группу
            if(!$error){// если нет ошибок
                if($group){// если проверка пройдена
                }else $error = 1;
            };
            // проверяем название
            if(!$error){// если нет ошибок
                if($name){// если проверка пройдена
                }else $error = 2;
            };
            // проверяем идентификатор
            if(!$error){// если нет ошибок
                if($id){// если проверка пройдена
                }else $error = 3;
            };
            // получаем содержимое файла
            if(!$error){// если нет ошибок
                $path = template($app["val"]["cacheUrl"], array("group" => $group, "name" => $name, "id" => $id));
                $file = new File($path);// используем специализированный класс
                $content = $file->read(false);// получаем содержимое
                if($content){// если не пусто
                }else $error = 4;
            };
            // преобразовываем содержимое в данные
            if(!$error){// если нет ошибок
                $data = json_decode($content, true);
                if($data){// если не пусто
                }else $error = 5;
            };
            // возвращаем результат
            return $data;
        },
        "delFileCache" => function($group = null, $name = null, $id = null){// удаляет файловый кеш
        //@param $group {string} - группа файлового кеша
        //@param $name {string} - название файлового кеша
        //@param $id {string} - идентификатор файлового кеша
        //@return {boolean} - успешность удаления
            global $app;
            $error = 0;
            
            // проверяем группу
            if(!$error){// если нет ошибок
                if($group){// если проверка пройдена
                }else $error = 1;
            };
            // проверяем название
            if(!$error){// если нет ошибок
                if($name or !$id){// если проверка пройдена
                }else $error = 2;
            };
            // удаляем каталог группы
            if(!$error and $group and !$name){// если нужно выполнить
                $path = template($app["val"]["cacheUrl"], array("group" => $group));
                if($app["fun"]["delPathNode"]($path)){// если элимент удалён
                }else $error = 3;
            };
            // удаляем каталог названия
            if(!$error and $name and !$id){// если нужно выполнить
                $path = template($app["val"]["cacheUrl"], array("group" => $group, "name" => $name));
                if($app["fun"]["delPathNode"]($path)){// если элимент удалён
                }else $error = 4;
            };
            // удаляем файл по идентификатору
            if(!$error and $id){// если нужно выполнить
                $path = template($app["val"]["cacheUrl"], array("group" => $group, "name" => $name, "id" => $id));
                if($app["fun"]["delPathNode"]($path)){// если элимент удалён
                }else $error = 5;
            };
            // возвращаем результат
            return !$error;
        },
        "delPathNode" => function($path){// рекурсивно удаляет папку или файл
        //@param $path {string} - путь к файлу или папке для удаления
        //@return {boolean} - успешность удаление файла или папки
            global $app;
            $error = 0;
            
            // проверяем что задан путь
            if(!$error){// если нету ошибок
                if($path){// если проверка пройдена
                }else $error = 1;
            };
            // удаляем файл или папку
            if(!$error){// если нету ошибок
                if(file_exists($path)){// если файл или папка существует
                    if(is_dir($path)){// если путь указывает на папку
                        // очищаем папку от дочерних элиментов
                        $list = @scandir($path);// получаем список дочерних элиментов
                        for($j = count($list) - 1; $j > -1 && !$error; $j--){
                            $value = $list[$j];// получаем очередной элимент
                            if("." != $value && ".." != $value){// если это обычные имя
                                if($app["fun"]["delPathNode"](// удаление элимента
                                    $path . DIRECTORY_SEPARATOR . $value
                                )){// если элимент удалён
                                }else $error = 2;
                            };
                        };
                        // удаляем папку
                        if(!$error){// если нету ошибок
                            if(@rmdir($path)){// если удалось удалить папку
                            }else $error = 3;
                        };
                    }else if(@unlink($path)){// если удалось удалить файл
                    }else $error = 4;
                };
            };
            // возвращаем результат
            return !$error;
        },
        "getEmoji" => function($icon){// преобразует строковую иконку в объект эмодзи
        //@param $icon {string} - иконка в строковом формате
        //@return {array} - эмодзи в формате объекта
            
            // выполняем обработку
            $emoji = array("id" => null, "name" => $icon);
            $flag = preg_match("<:(\w+):(\d+)>", $icon, $list);
            if($flag) $emoji = array("id" => $list[2], "name" => $list[1]);
            else $emoji = array("id" => null, "name" => trim($icon));
            // возвращаем результат
            return $emoji;
        },
        "numDeclin" => function ($number, $zero, $one, $two){// склонятор окончаний по числам
        //@params $number {float|string} - целое число для определения склонения
        //@params $zero {mixed} - окончание на ноль единиц
        //@params $one {mixed} - окончание на одину единицу
        //@params $two {mixed} - окончание на две единицы
        //@return {mixed} - одно из переданных окончаний

            // стандартизируем полученные данные
            $number = floor(abs(1 * $number));
            // вычисляем значения первого и второго десятка
            $first = $number % 10;
            $second = ($number % 100 - $first) / 10;
            // определяем необходимое окончание
            if(1 != $second){// если числа не из первого десятка
                if(0 == $first) $result = $zero;
                else if(1 == $first) $result = $one;
                else if(5 > $first) $result = $two;
                else $result = $zero;
            }else $result = $zero;
            // возвращаем результат
            return $result;
        },
        "dateCreate" => function($value, $timezone = null){// преобразует текст в метку времени с учётом временной зоны
        //@param $value {string} - текст времени на английском языке
        //@param $timezone {string} - временная зона
        //@return {float} - метка времени или -1 при ошибке
            
            // выполняем обработку
            $zone = null;// временная зона по умолчанию
            if(!empty($timezone)) $zone = new DateTimeZone($timezone);
            try{// пробуем распарсить время
                $time = new DateTime($value, $zone);
                $timestamp = 1 * $time->format("U");
            }catch(Exception $e){// если возникли ошибки
                $timestamp = -1;
            };
            // возвращаем результат
            return $timestamp;
        },
        "dateFormat" => function($format, $timestamp = null, $timezone = null){// форматирует время с учётом временной зоны
        //@param $format {string} - шаблон формата времени
        //@param $timestamp {float} - временная метка
        //@param $timezone {string} - временная зона
        //@return {string} - отформатированная время
        
            // выполняем обработку
            if(is_null($timestamp)) $timestamp = time();
            $time = new DateTime();// создаём объект для работы со временем
            $time->setTimestamp($timestamp);// задаём время
            if(!empty($timezone)) $zone = new DateTimeZone($timezone);
            if(!empty($timezone)) $time->setTimezone($zone);
            $value = $time->format($format);
            // возвращаем результат
            return $value;
        },
        "doRoutineStorage" => function($timestamp, &$status){// делаем регламентные операции с базами данных
        //@param $timestamp {float} - данные о времени для выполнения регламентных операций
        //@param $status {integer} - целое число статуса выполнения
        //@return {array} - многоуровневый ассоциативный список изменений
            global $app; 

            $list = array(// начальное значение
                "events" => array(),
                "players" => array()
            );
            $game = $app["val"]["game"];
            $app["fun"]["setDebug"](7, "[doRoutineStorage]");// отладочная информация
            $new = array();// массив для связи новых событий
            $items = array();// массив участников событий
            $counts = array();// многоуровневый счётчик событий
            // загружаем все необходимые базы данных
            if(empty($status)){// если нет ошибок
                $events = $app["fun"]["getStorage"]($game, "events", true);
                if(!empty($events)){// если удалось получить доступ к базе данных
                }else $status = 304;// не удалось загрузить одну из многих баз данных
            };
            if(empty($status)){// если нет ошибок
                $players = $app["fun"]["getStorage"]($game, "players", true);
                if(!empty($players)){// если удалось получить доступ к базе данных
                }else $status = 304;// не удалось загрузить одну из многих баз данных
            };
            if(empty($status)){// если нет ошибок
                $roles = $app["fun"]["getStorage"]($game, "roles", false);
                if(!empty($roles)){// если удалось получить доступ к базе данных
                }else $status = 304;// не удалось загрузить одну из многих баз данных
            };
            if(empty($status)){// если нет ошибок
                $raids = $app["fun"]["getStorage"]($game, "raids", false);
                if(!empty($raids)){// если удалось получить доступ к базе данных
                }else $status = 304;// не удалось загрузить одну из многих баз данных
            };
            // первый проход по базе данных событий
            if(empty($status)){// если нет ошибок
                for($i = $events->length - 1; $i > -1; $i--){
                    $eid = $events->key($i);// получаем ключевой идентификатор по индексу
                    $event = $events->get($eid);// получаем элемент по идентификатору
                    // выполняем подсчёт событий
                    if(// множественное условие
                        empty($status) and !$event["hide"]
                    ){// если нужно выполнить
                        // создаём структуру счётчика
                        $count = &$counts;// счётчик элементов
                        foreach(array($event["guild"], $event["channel"], $event["time"], $event["raid"]) as $key){
                            if(!isset($count[$key])) $count[$key] = array();
                            $count = &$count[$key];// получаем ссылку
                        };
                        // выполняем подсчёт
                        $count[$eid] = true;
                    };
                };
            };
            // второй проход по базе данных событий
            if(empty($status)){// если нет ошибок
                for($i = $events->length - 1; $i > -1; $i--){
                    $eid = $events->key($i);// получаем ключевой идентификатор по индексу
                    $event = $events->get($eid);// получаем элемент по идентификатору
                    // закрываем прошедшее событие
                    if(// множественное условие
                        empty($status) and !$event["close"]
                        and $event["time"] < $timestamp + $app["val"]["eventTimeClose"]
                    ){// если нужно выполнить
                        $unit = array(// изменяемые данные
                            "close" => true
                        );
                        if($events->set($eid, null, $unit)){// если данные успешно изменены
                            $list["events"][$eid] = $event;
                        }else $status = 309;// не удалось записать данные в базу данных
                    };
                    // скрываем закончившиеся события
                    if(// множественное условие
                        empty($status) and !$event["hide"]
                        and $event["time"] < $timestamp - $app["val"]["eventTimeHide"]
                    ){// если нужно выполнить
                        $unit = array(// изменяемые данные
                            "hide" => true
                        );
                        if($events->set($eid, null, $unit)){// если данные успешно изменены
                            $list["events"][$eid] = $event;
                        }else $status = 309;// не удалось записать данные в базу данных
                    };
                    // создаём новые повторяющиеся событие
                    if(// множественное условие
                        empty($status) and !$event["hide"] and $event["repeat"] and $event["leader"]
                        and $event["time"] < $timestamp - $app["val"]["eventTimeHide"]
                    ){// если нужно выполнить
                        $value = $timestamp + $app["val"]["eventTimeClose"] - $event["time"];
                        $value = ceil(abs($value) / $event["repeat"]) * $event["repeat"];
                        $value += $event["time"];// смещаем время события
                        // создаём структуру счётчика
                        $count = &$counts;// счётчик элементов
                        foreach(array($event["guild"], $event["channel"], $value, $event["raid"]) as $key){
                            if(!isset($count[$key])) $count[$key] = array();
                            $count = &$count[$key];// получаем ссылку
                        };
                        // добавляем событие
                        if(!count($count)){// если 
                            $unit = array(// изменяемые данные
                                "time" => $value,
                                "close" => false,
                                "hide" => false,
                                "message" => ""
                            );
                            $unit = array_merge($event, $unit);// обединяем с данными из события
                            unset($unit[$events->primary]);// удаляем ключевой идентификатор
                            $id = $events->length ? $events->key($events->length - 1) + 1 : 1;
                            if($events->set($id, null, $unit)){// если данные успешно изменены
                                $unit[$events->primary] = $id;
                                $list["events"][$id] = $unit;
                                $new[$eid] = $id;// добавляем информацию о связе
                            }else $status = 309;// не удалось записать данные в базу данных
                        };
                    };
                };
            };
            // первый проход по базе данных игроков
            if(empty($status)){// если нет ошибок
                for($i = $players->length - 1; $i > -1; $i--){
                    $pid = $players->key($i);// получаем ключевой идентификатор по индексу
                    $player = $players->get($pid);// получаем элемент по идентификатору
                    $eid = $player["event"];// получаем ключевой идентификатор
                    // формируем вспомогательный массив участников событий
                    if(// множественное условие
                        empty($status) and array_key_exists($eid, $list["events"])
                    ){// если нужно выполнить
                        if(!isset($items[$eid])) $items[$eid] = array();
                        array_push($items[$eid], $player);
                    };
                    // создаём нового играка в повторяющиеся событие
                    if(// множественное условие
                        empty($status) and array_key_exists($eid, $new)
                    ){// если нужно выполнить
                        $event = $events->get($eid);// получаем элемент по идентификатору
                        if($event["leader"] == $player["user"] or $player["accept"]){
                            $unit = array(// изменяемые данные
                                "notice" => false,
                                "event" => $new[$eid]
                            );
                            $unit = array_merge($player, $unit);// обединяем с данными из события
                            unset($unit[$players->primary]);// удаляем ключевой идентификатор
                            $id = $players->length ? $players->key($players->length - 1) + 1 : 1;
                            if($players->set($id, null, $unit)){// если данные успешно изменены
                                $unit[$players->primary] = $id;
                                $list["players"][$id] = $unit;
                            }else $status = 309;// не удалось записать данные в базу данных
                        };
                    };
                };
            };
            // третий проход по базе данных событий
            if(empty($status)){// если нет ошибок
                for($i = $events->length - 1; $i > -1; $i--){
                    $eid = $events->key($i);// получаем ключевой идентификатор по индексу
                    $event = $events->get($eid);// получаем элемент по идентификатору
                    // удаляем событие по временным ограничениям
                    if(// множественное условие
                        empty($status)
                        and (// дополнительное условие
                            $event["time"] < $timestamp - $app["val"]["eventTimeDelete"]
                            or $event["time"] > $timestamp + $app["val"]["eventTimeAdd"]
                        )
                    ){// если нужно выполнить
                        if($events->set($eid)){// если данные успешно изменены
                            $list["events"][$eid][$events->primary] = 0;
                        }else $status = 309;// не удалось записать данные в базу данных
                    };
                };
            };
            // второй проход по базе данных игроков
            if(empty($status)){// если нет ошибок
                for($i = $players->length - 1; $i > -1; $i--){
                    $pid = $players->key($i);// получаем ключевой идентификатор по индексу
                    $player = $players->get($pid);// получаем элемент по идентификатору
                    $eid = $player["event"];// получаем ключевой идентификатор
                    // фиксируем резерв в закрытом событии
                    if(// множественное условие
                        empty($status) and array_key_exists($eid, $list["events"])
                        and $list["events"][$eid][$events->primary]
                    ){// если нужно выполнить
                        $event = $events->get($eid);// получаем элемент по идентификатору
                        if($event["close"] and !$event["hide"]){// если событие закрылось
                            $raid = $raids->get($event["raid"]);
                            $value = !$app["fun"]["checkRaidPlayers"]($items[$eid], $raid, $roles, $player, false);
                            if($player["reserve"] != $value){// если необходимо внести изменения
                                $unit = array(// изменяемые данные
                                    "reserve" => $value
                                );
                                if($players->set($pid, null, $unit)){// если данные успешно изменены
                                    $list["players"][$pid] = $player;
                                }else $status = 309;// не удалось записать данные в базу данных
                            };
                        };
                    };
                    // удаляем играка в удалённом событии
                    if(// множественное условие
                        empty($status) and array_key_exists($eid, $list["events"])
                        and !$list["events"][$eid][$events->primary]
                    ){// если нужно выполнить
                        if($players->set($pid)){// если данные успешно изменены
                            $list["players"][$pid][$players->primary] = 0;
                        }else $status = 309;// не удалось записать данные в базу данных
                    };
                };
            };
            // возвращаем результат
            return $list;
        },
        "sendEventsNotice" => function($timestamp, $limit, &$status){// отправляет уведомления о событиях
        //@param $timestamp {float} - данные о времени для поиска не наступивших событий
        //@param $limit {integer} - ограничение на колличество отправляемых уведомлений
        //@param $status {integer} - целое число статуса выполнения
        //@return {array} - многоуровневый ассоциативный список изменений
            global $app; 

            $list = array(// начальное значение
                "events" => array(),
                "players" => array()
            );
            $game = $app["val"]["game"];
            $app["fun"]["setDebug"](7, "[sendEventsNotice]");// отладочная информация
            $counts = array();// многоуровневый счётчик событий
            // загружаем все необходимые базы данных
            if(empty($status)){// если нет ошибок
                $events = $app["fun"]["getStorage"]($game, "events", true);
                if(!empty($events)){// если удалось получить доступ к базе данных
                }else $status = 304;// не удалось загрузить одну из многих баз данных
            };
            if(empty($status)){// если нет ошибок
                $players = $app["fun"]["getStorage"]($game, "players", true);
                if(!empty($players)){// если удалось получить доступ к базе данных
                }else $status = 304;// не удалось загрузить одну из многих баз данных
            };
            // формируем список событий
            if(empty($status)){// если нет ошибок
                $items = array();// список событий
                for($i = 0, $iLen = $events->length; $i < $iLen; $i++){
                    $eid = $events->key($i);// получаем ключевой идентификатор по индексу
                    $event = $events->get($eid);// получаем элемент по идентификатору
                    if(// множественное условие
                        $event["time"] < $timestamp - $app["val"]["eventTimeClose"]
                        and $event["time"] > $timestamp - $app["val"]["eventTimeNotice"]
                    ){// если нужно добавить в список
                        array_push($items, $event);
                    };
                };
            };
            // сортируем список событитй
            if(empty($status)){// если нет ошибок
                usort($items, function($a, $b){// сортировка
                    $value = 0;// начальное значение
                    if(!$value and $a["time"] != $b["time"]) $value = $a["time"] > $b["time"] ? 1 : -1;
                    if(!$value and $a["raid"] != $b["raid"]) $value = $a["raid"] > $b["raid"] ? 1 : -1;
                    if(!$value and $a["id"] != $b["id"]) $value = $a["id"] < $b["id"] ? 1 : -1;
                    // возвращаем результат
                    return $value;
                });
            };
            // отправляем информацию по событиям
            if(empty($status)){// если нет ошибок
                $index = 0;// индек отправленных уведомлений
                for($i = 0, $iLen = count($items); $i < $iLen and (!$limit or $index < $limit) and empty($status); $i++){
                    $event = $items[$i];// получаем очередной элимент
                    for($j = 0, $jLen = $players->length; $j < $jLen and (!$limit or $index < $limit) and empty($status); $j++){
                        $pid = $players->key($j);// получаем ключевой идентификатор по индексу
                        $player = $players->get($pid);// получаем элемент по идентификатору
                        if(!$player["notice"] and $event["id"] == $player["event"]){// если уведомление ещё не отправлялось
                            $user = $app["fun"]["getCache"]("user", $player["user"]);
                            $data = $app["fun"]["getNoticeMessage"]($event["id"], $status);
                            // отправляем личное сообщение
                            if(empty($status)){// если нет ошибок
                                if($data and $user and !$user["bot"] and isset($user["channels"][0])){// если нужно отправить
                                    $uri = "/channels/" . $user["channels"][0]["id"] . "/messages";
                                    $data = $app["fun"]["apiRequest"]("post", $uri, $data, $code);
                                    if(200 == $code){// если запрос выполнен успешно
                                        $data["embed"] = get_val($data["embeds"], 0, false);// приводим к единому виду
                                        $app["fun"]["setCache"]("message", $data, $user["channels"][0]["id"], null, $user["id"]);
                                    }else if(403 == $code){// если у пользователя установлен запрет 
                                    }else $status = 306;// не удалось получить корректный ответ от удаленного сервера
                                };
                            };
                            // вносим изменения в базу данных
                            if(empty($status)){// если нет ошибок
                                if($players->set($pid, "notice", true)){// если данные успешно изменены
                                    $list["players"][$pid] = $player;
                                    $index++;// увеличиваем индек
                                }else $status = 309;// не удалось записать данные в базу данных
                            };
                        };
                    };
                };
            };
            // возвращаем результат
            return $list;
        },
        "fixCustomEmoji" => function($eid, $inDescription, $inComment, &$status){// исправляет упоминание пользовательских эмодзи
        //@param $eid {integer} - идентификатор события для поиска
        //@param $inDescription {boolean} - исправить в описании события
        //@param $inComment {boolean} - исправить в комментарии игрока
        //@param $status {integer} - целое число статуса выполнения
        //@return {array} - многоуровневый ассоциативный список изменений
            global $app;
            $error = 0;
        
            $list = array(// начальное значение
                "events" => array(),
                "players" => array()
            );
            $game = $app["val"]["game"];
            // загружаем все необходимые базы данных
            if(empty($status) and $inDescription){// если нужно выполнить
                $events = $app["fun"]["getStorage"]($game, "events", true);
                if(!empty($events)){// если удалось получить доступ к базе данных
                }else $status = 304;// не удалось загрузить одну из многих баз данных
            };
            if(empty($status) and $inComment){// если нужно выполнить
                $players = $app["fun"]["getStorage"]($game, "players", true);
                if(!empty($players)){// если удалось получить доступ к базе данных
                }else $status = 304;// не удалось загрузить одну из многих баз данных
            };
            // исправляет упоминание кастомных эмодзи в описании событий
            if(empty($status) and $inDescription){// если нужно выполнить
                $event = $events->get($eid);// получаем элемент по идентификатору
                if($event and mb_strlen($event["description"])){// если нужно выполнить
                    $count = 0;// сбрасываем счётчик изменений
                    $value = str_replace("<br>", $app["val"]["lineDelim"], $event["description"]);
                    $value = $app["fun"]["clearDeletedEmoji"]($value, $event, $status, $count);
                    $value = str_replace($app["val"]["lineDelim"], "<br>", $value);
                    // вносим изменения в базу данных
                    if(empty($status) and $count){// если нужно выполнить
                        if($events->set($eid, "description", $value)){// если данные успешно изменены
                            $list["events"][$eid] = $event;
                        }else $status = 309;// не удалось записать данные в базу данных
                    };
                };
            };
            // исправляет упоминание кастомных эмодзи в комментарии игрока
            if(empty($status) and $inComment){// если нужно выполнить
                for($i = 0, $iLen = $players->length; $i < $iLen and empty($status); $i++){
                    $id = $players->key($i);// получаем ключевой идентификатор по индексу
                    $player = $players->get($id);// получаем элемент по идентификатору
                    if($player["event"] == $eid and mb_strlen($player["comment"])){// если нужно выполнить
                        $event = $events->get($eid);// получаем элемент по идентификатору
                        $count = 0;// сбрасываем счётчик изменений
                        $value = $player["comment"];// получаем значение для обработки
                        $value = $app["fun"]["clearDeletedEmoji"]($value, $event, $status, $count);
                        // вносим изменения в базу данных
                        if(empty($status) and $count){// если нужно выполнить
                            if($players->set($id, "comment", $value)){// если данные успешно изменены
                                $list["players"][$id] = $player;
                            }else $status = 309;// не удалось записать данные в базу данных
                        };
                    };
                };
            };
            // возвращаем результат
            return $list;
        },
        "clearDeletedEmoji" => function($imput, &$event, &$status, &$count){// исправляет упоминание кастомных эмодзи
        //@param $imput {string} - проверяемое строковое значение с пользовательскими эмодзи
        //@param $event {object} - событие для получение необходимых данных
        //@param $status {integer} - целое число статуса выполнения
        //@param $count {integer} - счётчик внесённых изменений
        //@return {string} - исправленное строковое значение
            
            $pattern = "/\s*<:(\S+):(\d+)>/";
            $value = preg_replace_callback($pattern, function($matches) use (&$event, &$status, &$count){
                global $app;// доступ к глобальному объекту
                $value = $matches[0];// возвращаемое значение
                // получаем информацию о гильдии
                if(empty($status)){// если нет ошибок
                    $guild = $app["fun"]["getCache"]("guild", $event["guild"]);
                    if($guild){// если удалось получить данные
                    }else $status = 303;// переданные параметры не верны
                };
                // получаем информацию об эмодзи
                if(empty($status)){// если нет ошибок
                    $emoji = $app["fun"]["getCache"]("emoji", $matches[2], $event["guild"]);
                    if(!$emoji or !$emoji["available"]){// если нужно очистить эмодзи
                        $value = "";// сбрасываем возвращаемое значение
                        $count++;// увеличиваем счётчик
                    };
                };
                // возвращаем значение
                return $value;
            }, $imput);
            // возвращаем результат
            return $value;
        },
        "sortEventsMessage" => function($guild, $channel, &$status){// сортирует события с учётом сообщений
        //@param $guild {string} - идентификатор гильдии для фильтрации событий
        //@param $channel {string} - идентификатор канала для фильтрации событий
        //@param $status {integer} - целое число статуса выполнения
        //@return {array} - многоуровневый ассоциативный список изменений
            global $app;
            $error = 0;

            $list = array(// начальное значение
                "events" => array(),
                "players" => array()
            );
            $game = $app["val"]["game"];
            $items = array();// список событий
            $units = array();// список идентификаторов сообщений
            // получаем гильдию
            if(!$error){// если нет ошибок
                if(!is_array($guild) and !empty($guild)){// если нужно получить
                    $guild = $app["fun"]["getCache"]("guild", $guild);
                };
                if(!empty($guild)){// если не пустое значение
                }else $error = 1;
            };
            // получаем канал
            if(!$error){// если нет ошибок
                if(!is_array($channel) and !empty($channel)){// если нужно получить
                    $channel = $app["fun"]["getCache"]("channel", $channel, $guild["id"], null);
                };
                if(!empty($channel)){// если не пустое значение
                }else $error = 2;
            };
            // получаем конфигурацию канала
            if(!$error){// если нет ошибок
                $config = $app["fun"]["getChannelConfig"]($channel, $guild, null);
                if($config){// если удалось получить данные
                }else $error = 3;
            };
            // загружаем все необходимые базы данных
            if(empty($status)){// если нет ошибок
                $session = $app["fun"]["getStorage"]($game, "session", true);
                if(!empty($session)){// если удалось получить доступ к базе данных
                }else $status = 304;// не удалось загрузить одну из многих баз данных
            };
            if(empty($status) and !$error){// если нужно выполнить
                $events = $app["fun"]["getStorage"]($game, "events", true);
                if(!empty($events)){// если удалось получить доступ к базе данных
                }else $status = 304;// не удалось загрузить одну из многих баз данных
            };
            // формируем вспомогательный объект проверки сообщений
            if(empty($status) and !$error){// если нужно выполнить
                $check = array();// вспомогательный объект проверки сообщений
                for($i = count($channel["messages"]) - 1; $i > -1 ; $i--){
                    $message = $channel["messages"][$i];// получаем очередной элимент
                    $mid = $message["id"];// получаем идентификатор сообщения
                    $flag = $message["author"]["id"] == $session->get("bot", "value");
                    if($flag) $check[$mid] = true;
                };
            };
            // формируем список событий и идентификаторов сообщений
            if(empty($status) and !$error){// если нужно выполнить
                for($i = 0, $iLen = $events->length; $i < $iLen; $i++){
                    $eid = $events->key($i);// получаем ключевой идентификатор по индексу
                    $event = $events->get($eid);// получаем элемент по идентификатору
                    if(// множественное условие
                        $event["channel"] == $channel["id"]
                        and $event["guild"] == $guild["id"]
                        and !$event["hide"]
                    ){// если нужно добавить в список
                        $mid = $event["message"];
                        if(!isset($check[$mid])) $mid = "";
                        array_push($items, $event);
                        array_push($units, $mid);
                    };
                };
            };
            // сортируем список событитй
            if(empty($status) and !$error){// если нужно выполнить
                switch($config["sort"]){// поддерживаемые режимы сортировки
                    case "time":// по времени проведения
                        usort($items, function($a, $b){// сортировка
                            $value = 0;// начальное значение
                            if(!$value and $a["time"] != $b["time"]) $value = $a["time"] > $b["time"] ? 1 : -1;
                            if(!$value and $a["raid"] != $b["raid"]) $value = $a["raid"] > $b["raid"] ? 1 : -1;
                            if(!$value and $a["id"] != $b["id"]) $value = $a["id"] < $b["id"] ? 1 : -1;
                            // возвращаем результат
                            return $value;
                        });
                        break;
                    default:// не известный режим
                        $error = 4;
                };
            };
            // изменяем список идентификаторов сообщений
            if(empty($status) and !$error){// если нужно выполнить
                $flag = (!$config["flood"] and !$config["history"]);
                if($flag){// если можно использовать старые сообщения
                    $j = 0;// порядковый номер в списке идентификаторов сообщений
                    $jLen = count($units);// длина списка идентификаторов сообщений
                    $index = $jLen - count($check);// смешение в сообщениях
                    // переносим идентификаторы из списка сообщений
                    for($i = count($channel["messages"]) - 1; $i > -1 ; $i--){
                        $message = $channel["messages"][$i];// получаем очередной элимент
                        $mid = $message["id"];// получаем идентификатор сообщения
                        $flag = $message["author"]["id"] == $session->get("bot", "value");
                        if($flag){// если это сообщение текущего бота
                            $index++;// увеличиваем смещение в сообщениях
                            if($index > 0){// если нужно заменить
                                $units[$j] = $mid;
                                $j++;
                            };
                        };
                    };
                    // дополняем идентификаторы пустыми значениями
                    while($j < $jLen){// пока не достигнут конец списка
                        $mid = "";// сбрасываем значение
                        $units[$j] = $mid;
                        $j++;
                    };
                };
            };
            // сортируем список идентификаторов сообщений
            if(empty($status) and !$error){// если нужно выполнить
                usort($units, function($a, $b){// сортировка
                    $value = 0;// начальное значение
                    if(!$value and !$a and $b) $value = 1;
                    if(!$value and $a and !$b) $value = -1;
                    if(!$value and $a != $b) $value = $a > $b ? 1 : -1;
                    // возвращаем результат
                    return $value;
                });
            };
            // распределяем идентификаторы сообщений по событиям
            if(empty($status)){// если нужно выполнить
                for($i = 0, $iLen = count($items); $i < $iLen and empty($status); $i++){
                    $item = $items[$i];// получаем очередной элемент
                    $mid = $units[$i];// получаем очередной идентификатор
                    $eid = $item["id"];// получаем очередной идентификатор
                    // работаем с базой данных событий
                    $event = array();// сбрасываем значение
                    $flag = false;// требуется обновить
                    // проверяем изменение сообщения
                    if($item["message"] != $mid){
                        $event["message"] = $mid;
                        $flag = true;
                    };
                    // выполняем обновление события
                    if($flag){// если требуется обновление
                        if($events->set($eid, null, $event)){// если данные успешно добавлены
                            $list["events"][$eid] = $item;
                        }else $status = 309;// не удалось записать данные в базу данных
                    };
                };
            };
            // возвращаем результат
            return $list;
        },
        "delEmoji" => function($value){// удаляем эмодзи из строки
        //@param $value {string} - строка для удаления эмодзи
        //@return {string} - строка без эмодзи
            
            // выполняем удаление
            foreach(array(// регулярные выражения для эмодзи
                "/[\x{1F600}-\x{1F64F}]/u", // смайлики
                "/[\x{1F300}-\x{1F5FF}]/u", // различные символы и пиктограммы
                "/[\x{1F680}-\x{1F6FF}]/u", // транспортные и картографические символы
                "/[\x{2600}-\x{26FF}]/u",   // прочие символы
                "/[\x{2700}-\x{27BF}]/u",   // декоративные
                "/[\x{1F1E6}-\x{1F1FF}]/u", // флаги
                "/[\x{1F910}-\x{1F95E}]/u", // другие
                "/[\x{1F980}-\x{1F991}]/u", // другие
                "/[\x{1F9C0}]/u",           // другие
                "/[\x{1F9F9}]/u"            // другие
            ) as $regex){// пробигаемся по регулярным вырожениям
                $value = preg_replace($regex, "", $value);
            };
            // возвращаем результат
            return $value;
        },
        "wrapUrl" => function($value){// оборачивает ссылки экранируя их
        //@param $value {string} - строка для поиска ссылок
        //@return {string} - строка с экранированными ссылками
            
            // выполняем экранирование
            $pattern = "/<*(\b(?:(?:https?):\/\/|www\.)[-a-z0-9+&@#\/%?=~_|!:,.;]*[-a-z0-9+&@#\/%=~_|])>*/i";
            $value = preg_replace($pattern, "<$1>", $value);
            // возвращаем результат
            return $value;
        },
        "getShortValue" => function($value){// получает только символы в верхнем регистре
        //@param $value {string} - значение для получения символов
        //@return {string} - только символы в верхнем регистре
            
            $short = "";// начальное значение
            $value = strval($value);// приводим к строковому типу 
            for($i = 0, $iLen = mb_strlen($value); $i < $iLen; $i++){
                $char = mb_substr($value, $i, 1);// получаем очередной символ
                if(mb_strtoupper($char) == $char) $short .= $char;
            };
            // возвращаем результат
            return $short;
        },
        "getCalendarUrl" => function($provider, $title, $start, $duration, $timezone, $location = null, $description = null){// получает ссылку для создания события в календаре
        //@param $provider {string} - поставщик календаря для создания события
        //@param $title {string} - заголовок события
        //@param $timestamp {float} - временная метка начала события
        //@param $duration {float} - продолжительность события
        //@param $timezone {string} - временная зона для создания события
        //@param $location {string} - место проведения события
        //@param $description {string} - описание события
        //@return {string} - ссылка на события или пустая строка
            global $app;

            $url = "";// начальное значение
            switch($provider){// поддерживаемые провайдеры
                case "google":// Google.Календарь
                    $offset = 1 * $app["fun"]["dateFormat"]("Z", $start, $timezone);
                    $data = array("action" => "TEMPLATE", "text" => $title);
                    if($description) $data["details"] = $description;
                    if($location) $data["location"] = $location;
                    $data["dates"] = implode("/", array(
                        $app["fun"]["dateFormat"]("Ymd\THis\Z", $start - $offset, $timezone),
                        $app["fun"]["dateFormat"]("Ymd\THis\Z", $start - $offset + $duration, $timezone)
                    ));
                    $url = "https://google.com/calendar/event?" . arr2str($data, "&", "=", true);
                    break;
            };
            // возвращаем результат
            return $url;
        },
        "getRaidLimit" => function($raid, $roles){// вычисляет общий лимит рейда
        //@param $raid {array} - рейд для вычисления лимита
        //@param $roles {FileStorage} - база данных ролей
        //@return {integer} - общий лимит рейда

            $limit = 0;// лимит игроков
            // вычисляем лимит игроков в рейде
            for($value = 1, $i = 0, $iLen = $roles->length; $i < $iLen and $value; $i++){
                $key = $roles->key($i);// получаем ключевой идентификатор по индексу
                $value = $raid[$key];// получаем значение лимита
                if($value > 0) $limit += $value;
                if(!$limit) $limit = 0;
            };
            // возвращаем результат
            return $limit;
        },
        "createMessages" => function($embed, $lines = array(), $delim = "", $limit = 0){// формирует массив сообщений из строк
        //@param $embed {array} - встраиваемый контент в последнее сообщение
        //@param $lines {array} - массив строк для формирования сообщений
        //@param $delim {string} - разделитель строк при формировании сообщения
        //@param $limit {integer} - максимальная допустимая длина контента в сообщении
        //@return {array} - массив сообщений или пустой массив
            
            $messages = array();// список сообщений
            $dLen = mb_strlen($delim);// длина разделителя
            // формируем список блоков данных из строк
            $blocks = array();// массив блоков данных
            for($i = 0, $iLen = count($lines); $i < $iLen; $i++){
                $block = "";// сбрасываем значение блока данных
                $bLen = 0;// сбрасываем значение длины блока данных
                $value = 0;// новая расчётная длина блока данных
                for($j = 0, $jLen = count($lines[$i]); $j <= $jLen ; $j++){
                    $line = "";// сбрасываем значение строки данных
                    $lLen = 0;// сбрасываем значение длины строки данных
                    if($j != $jLen){// если очередная строка существует
                        $line = $lines[$i][$j];// получаем строку
                        $lLen = mb_strlen($line);// длина строки
                        $value = $bLen + ($bLen ? $dLen : 0) + $lLen;
                        $flag = ($j and $limit and $value > $limit);
                    }else $flag = true;// перейти к новому блоку
                    if($flag){// если нужно создать блок
                        array_push($blocks, $block);
                        $block = $line;// блок данных
                        $bLen = $lLen;// длина блока
                    }else{// если не нужно создавать блок
                        $block .= ($j ? $delim : "") . $line;
                        $bLen = $value;// длина блока
                    };
                };
            };
            // формируем список сообщений из блоков
            $content = "";// сбрасываем значение контента данных
            $cLen = 0;// сбрасываем значение длины контента данных
            for($i = 0, $iLen = count($blocks); $i <= $iLen; $i++){
                $block = "";// сбрасываем значение блока данных
                $bLen = 0;// сбрасываем значение длины блока данных
                $value = 0;// новая расчётная длина контента данных
                if($i != $iLen){// если очередной блок существует
                    $block = $blocks[$i];// получаем блок
                    $bLen = mb_strlen($block);// длина блокa
                    $value = $cLen + ($cLen ? $dLen : 0) + $bLen;
                    $flag = ($i and $limit and $value > $limit);
                }else $flag = ($iLen or $embed);// перейти к новому сообщению
                if($flag){// если нужно создать сообщение
                    $message = array();// сообщение
                    if($iLen) $message["content"] = $content;
                    $message["embed"] = $i == $iLen ? $embed : null;
                    array_push($messages, $message);
                    $content = $block;// контент данных
                    $cLen = $bLen;// длина контента
                }else{// если не нужно создавать сообщение
                    $content .= ($cLen ? $delim : "") . $block;
                    $cLen = $value;// длина контента
                };
            };
            // возвращаем результат
            return $messages;
        },
        "checkRaidPlayers" => function(&$items, $raid, $roles, $player = null, $accept = null){// считает основной состав, проставляет резерв и сортирует список игроков
        //@param $items {array} - изменяемый список игроков для сортировки и проставления резерва
        //@param $raid {array} - рейд для вычисления лимита
        //@param $roles {FileStorage} - база данных ролей
        //@param $player {array} - игрок для ограничения подсчёта
        //@param $accept {boolean} - считать с учётом согласования
        //@return {integer} - количество игроков в основном составе
            global $app;
            
            $any = "";// идентификатор любой роли
            $length = 0;// количество игроков
            $count = array();// счётчик по ролям
            $limit = $app["fun"]["getRaidLimit"]($raid, $roles);
            $isAccept = false;// включён режим согласования
            // первый раз сортируем список игроков
            usort($items, function($a, $b){// сортировка
                $value = 0;// начальное значение
                if(!$value and $a["reserve"] != $b["reserve"]) $value = $a["reserve"] ? 1 : -1;
                if(!$value and $a["accept"] != $b["accept"]) $value = $b["accept"] ? 1 : -1;
                if(!$value and $a["role"] != $b["role"]) $value = $a["role"] < $b["role"] ? 1 : -1;
                if(!$value and $a["id"] != $b["id"]) $value = $a["id"] > $b["id"] ? 1 : -1;
                // возвращаем результат
                return $value;
            });
            // вычисляем автоматический резерв
            for($i = 0, $iLen = count($items); $i < $iLen; $i++){
                $item = &$items[$i];// получаем очередной элимент
                $key = $item["role"];// идентификатор роли
                if(!isset($count[$any])) $count[$any] = 0;
                if(!isset($count[$key])) $count[$key] = 0;
                $isAccept = ($isAccept or !$i and $item["accept"]);
                if($limit and $count[$any] >= $limit) $item["reserve"] = true;
                if($raid[$key] and !$item["accept"] and $count[$key] >= $raid[$key]) $item["reserve"] = true;
                $flag = (!$isAccept or !$accept or $item["accept"]);// флаг учёта согласования
                if((!$player or $player["id"] == $item["id"]) and $flag and !$item["reserve"]) $length++;
                if(!$item["reserve"]) $count[$any]++;// увеличиваем счётчик любой роли
                if(!$item["reserve"]) $count[$key]++;// увеличиваем счётчик этой роли
            };
            // второй раз сортируем список игроков
            usort($items, function($a, $b){// сортировка
                $value = 0;// начальное значение
                if(!$value and $a["reserve"] != $b["reserve"]) $value = $a["reserve"] ? 1 : -1;
                if(!$value and $a["accept"] != $b["accept"]) $value = $b["accept"] ? 1 : -1;
                if(!$value and $a["role"] != $b["role"]) $value = $a["role"] < $b["role"] ? 1 : -1;
                if(!$value and $a["id"] != $b["id"]) $value = $a["id"] > $b["id"] ? 1 : -1;
                // возвращаем результат
                return $value;
            });
            // возвращаем результат
            return $length;
        },
        "getFeedbackMessage" => function($error, $language, &$status){// формирует сообщение обратной связи
        //@param $error {integer} - код ошибки для сообщения обратной связи
        //@param $language {string} - язык используемый для формирования команды
        //@param $status {integer} - целое число статуса выполнения
        //@return {array} - сообщение или пустой массив при ошибке
            global $app;
            
            $message = array();// сообщение
            // загружаем все необходимые базы данных
            if(empty($status)){// если нет ошибок
                $feedbacks = $app["fun"]["getStorage"](null, "feedbacks", false);
                if(!empty($feedbacks)){// если удалось получить доступ к базе данных
                }else $status = 304;// не удалось загрузить одну из многих баз данных
            };
            if(empty($status)){// если нет ошибок
                $names = $app["fun"]["getStorage"](null, "names", false);
                if(!empty($names)){// если удалось получить доступ к базе данных
                }else $status = 304;// не удалось загрузить одну из многих баз данных
            };
            // формируем встроенный объект
            if(empty($status)){// если нет ошибок
                // получаем описание ошибки
                $feedback = $feedbacks->get($error);// получаем элемент по идентификатору
                if(empty($feedback)) $feedback = $feedbacks->get($error = 10);
                // формируем ссылку на инструкцию
                $url = template($app["val"]["discordInviteUrl"], array(
                    "id" => $names->get("invite", $language)
                ));
                // формируем полное описание
                $value = $feedback[$language];
                $delim = mb_strlen($value) ? mb_substr($value, mb_strlen($value) - 1, 1) : "";
                if(mb_strlen($delim)) $delim = false === mb_strpos(".?!", $delim) ? ". " : " ";
                $value = $app["fun"]["addWhenShort"]($value, $delim . $names->get("notice", $language), 106);
                $value .= " [#" . $names->get("instruction", $language) . "](" . $url . ")";
                // формируем встраиваемый объект
                $embed = array();// задаём не пустое значение
                $embed["type"] = "rich";// формат встраиваемого контента
                $embed["color"] = 16711680;// цвет встраиваемого контента
                $embed["description"] = $value;
            };
            // формируем сообщение
            if(empty($status)){// если нет ошибок
                $list = $app["fun"]["createMessages"]($embed);
                $message = array_merge($message, array_shift($list));
            };
            // возвращаем результат
            return $message;
        },
        "getRecordMessage" => function($eid, $language, $timezone, &$status){// формирует сообщение для записи события
        //@param $eid {integer} - идентификатор события
        //@param $language {string} - язык используемый для формирования команды
        //@param $timezone {string} - временная зона для формирования команды
        //@param $status {integer} - целое число статуса выполнения
        //@return {array} - сообщение или пустой массив при ошибке
            global $app;
            $error = 0;

            $game = $app["val"]["game"];
            $message = array();// сообщение
            // загружаем все необходимые базы данных
            if(empty($status)){// если нет ошибок
                $session = $app["fun"]["getStorage"]($game, "session", true);
                if(!empty($session)){// если удалось получить доступ к базе данных
                }else $status = 304;// не удалось загрузить одну из многих баз данных
            };
            if(empty($status)){// если нет ошибок
                $events = $app["fun"]["getStorage"]($game, "events", true);
                if(!empty($events)){// если удалось получить доступ к базе данных
                }else $status = 304;// не удалось загрузить одну из многих баз данных
            };
            if(empty($status)){// если нет ошибок
                $players = $app["fun"]["getStorage"]($game, "players", true);
                if(!empty($players)){// если удалось получить доступ к базе данных
                }else $status = 304;// не удалось загрузить одну из многих баз данных
            };
            if(empty($status)){// если нет ошибок
                $raids = $app["fun"]["getStorage"]($game, "raids", false);
                if(!empty($raids)){// если удалось получить доступ к базе данных
                }else $status = 304;// не удалось загрузить одну из многих баз данных
            };
            if(empty($status)){// если нет ошибок
                $chapters = $app["fun"]["getStorage"]($game, "chapters", false);
                if(!empty($chapters)){// если удалось получить доступ к базе данных
                }else $status = 304;// не удалось загрузить одну из многих баз данных
            };
            if(empty($status)){// если нет ошибок
                $types = $app["fun"]["getStorage"]($game, "types", false);
                if(!empty($types)){// если удалось получить доступ к базе данных
                }else $status = 304;// не удалось загрузить одну из многих баз данных
            };
            if(empty($status)){// если нет ошибок
                $roles = $app["fun"]["getStorage"]($game, "roles", false);
                if(!empty($roles)){// если удалось получить доступ к базе данных
                }else $status = 304;// не удалось загрузить одну из многих баз данных
            };
            if(empty($status)){// если нет ошибок
                $names = $app["fun"]["getStorage"](null, "names", false);
                if(!empty($names)){// если удалось получить доступ к базе данных
                }else $status = 304;// не удалось загрузить одну из многих баз данных
            };
            if(empty($status)){// если нет ошибок
                $additions = $app["fun"]["getStorage"](null, "additions", false);
                if(!empty($additions)){// если удалось получить доступ к базе данных
                }else $status = 304;// не удалось загрузить одну из многих баз данных
            };
            if(empty($status)){// если нет ошибок
                $dates = $app["fun"]["getStorage"](null, "dates", false);
                if(!empty($dates)){// если удалось получить доступ к базе данных
                }else $status = 304;// не удалось загрузить одну из многих баз данных
            };
            if(empty($status)){// если нет ошибок
                $months = $app["fun"]["getStorage"](null, "months", false);
                if(!empty($months)){// если удалось получить доступ к базе данных
                }else $status = 304;// не удалось загрузить одну из многих баз данных
            };
            // выполняем формирование сообщения
            if(empty($status)){// если нет ошибок
                $blank = "_ _";// не удаляемый пустой символ
                $delim = $app["val"]["lineDelim"];
                $group = -1;// сбрасываем группу строк
                $lines = array();// сгруппированный список строк
                $event = null;// событие
                // проверяем переданные идентификаторы
                if(!$error){// если нет ошибок
                    if($eid){// если проверка пройдена
                        $event = $events->get($eid);
                    }else $error = 1;
                };
                // проверяем успешность получения события
                if(!$error){// если нет ошибок
                    if($event){// если проверка пройдена
                        $raid = $raids->get($event["raid"]);
                        $type = $types->get($raid["type"]);
                        $chapter = $chapters->get($raid["chapter"]);
                    }else $error = 2;
                };
                // получаем информацию о гильдии
                if(!$error){// если нет ошибок
                    $guild = $app["fun"]["getCache"]("guild", $event["guild"]);
                    if($guild){// если удалось получить данные
                    }else $error = 3;
                };
                // получаем информацию о канале
                if(!$error){// если нет ошибок
                    $channel = $app["fun"]["getCache"]("channel", $event["channel"], $event["guild"], null);
                    if($channel){// если удалось получить данные
                    }else $error = 4;
                };
                // определяем номер события
                if(!$error){// если нет ошибок
                    $index = 0;// сбрасываем значение
                    $items = array();// список событий
                    $check = array();// ассоциативный массив проверки сообщений
                    // формируем вспомогательный объект проверки сообщений
                    for($i = count($channel["messages"]) - 1; $i > -1 ; $i--){
                        $item = $channel["messages"][$i];// получаем очередной элимент
                        $mid = $item["id"];// получаем идентификатор
                        $check[$mid] = true;
                    };
                    // формируем список событий
                    for($i = 0, $iLen = $events->length; $i < $iLen; $i++){
                        $id = $events->key($i);// получаем ключевой идентификатор по индексу
                        $item = $events->get($id);// получаем элемент по идентификатору
                        if(// множественное условие
                            $item["channel"] == $event["channel"]
                            and $item["guild"] == $event["guild"]
                            and !$item["hide"]
                        ){// если нужно добавить в список
                            $mid = $item["message"];
                            $flag = ($mid and !isset($check[$mid]));
                            if($flag) $item["message"] = "";
                            array_push($items, $item);
                        };
                    };
                    // сортируем список событий
                    usort($items, function($a, $b){// сортировка
                        $value = 0;// начальное значение
                        if(!$value and !$a["message"] and $b["message"]) $value = 1;
                        if(!$value and $a["message"] and !$b["message"]) $value = -1;
                        if(!$value and $a["message"] != $b["message"]) $value = $a["message"] > $b["message"] ? 1 : -1;
                        if(!$value and $a["id"] != $b["id"]) $value = $a["id"] > $b["id"] ? 1 : -1;
                        // возвращаем результат
                        return $value;
                    });
                    // определяем позицию в списке событий
                    for($i = 0, $iLen = count($items); $i < $iLen and !$index; $i++){
                        $item = $items[$i];// получаем очередной элемент
                        $flag = $item["id"] == $event["id"];
                        if($flag) $index = $i + 1;
                    };
                };
                // формируем список игроков
                if(!$error){// если нет ошибок
                    $items = array();// список игроков
                    for($i = 0, $iLen = $players->length; $i < $iLen; $i++){
                        $id = $players->key($i);// получаем ключевой идентификатор по индексу
                        $player = $players->get($id);// получаем элемент по идентификатору
                        $flag = $player["event"] == $event["id"];
                        if($flag) array_push($items, $player);
                    };
                };
                // сортируем, считаем и изменяем список игроков
                if(!$error){// если нет ошибок
                    $limit = $app["fun"]["getRaidLimit"]($raid, $roles);
                    $count = $app["fun"]["checkRaidPlayers"]($items, $raid, $roles, null, true);
                };
                // формируем шапку
                if(!$error){// если нет ошибок
                    $lines[++$group] = array();// новая группа строк
                    $line = "```";
                    array_push($lines[$group], $line);
                    $line = $names->get("schedule", "icon") . " " . $app["fun"]["dateFormat"]("d", $event["time"], $timezone);
                    $line .= " " . $months->get($app["fun"]["dateFormat"]("n", $event["time"], $timezone), $language);
                    $line .= " " . $additions->get("once", "icon") . " " . $app["fun"]["dateFormat"]("H:i", $event["time"], $timezone);
                    $line .= " " . $additions->get("reject", "icon") . " " . mb_ucfirst(mb_strtolower($dates->get(mb_strtolower($app["fun"]["dateFormat"]("l", $event["time"], $timezone)), $language)));
                    if($index) $line .= " " . $names->get("record", "icon") . " " . str_pad($index, 3, "0", STR_PAD_LEFT);
                    array_push($lines[$group], $line);
                    $line = "```";
                    array_push($lines[$group], $line);
                };
                // формируем заголовок
                if(!$error){// если нет ошибок
                    $line = !$event["close"] ? (($limit and $count < $limit) ? $additions->get("member", "icon") : $type["icon"]) : $additions->get("group", "icon");
                    $line .= " **" . $raid["key"] . "** - " . $raid[$language] . (!empty($chapter[$language]) ? " **DLC " . $chapter[$language] . "**" : "");
                    $line .= " <" . implode(":", array("t", $app["fun"]["dateFormat"]("U", $event["time"], $timezone), "R")) . ">";
                    $line .= ($event["repeat"] and !$event["hide"]) ? "  " . $additions->get("weekly", "icon") : "";
                    array_push($lines[$group], $line);
                    $line = $app["fun"]["href"](template($app["val"]["eventUrl"], array("group" => $game, "id" => $event["id"], "name" => $raid["key"])));
                    array_push($lines[$group], $line);
                };
                // формируем комментарий
                if(!$error and mb_strlen($event["description"])){// если нужно выполнить
                    $line = $app["fun"]["wrapUrl"](str_replace("<br>", $app["val"]["lineDelim"], $event["description"]));
                    array_push($lines[$group], $line);
                };
                // формируем описание состава
                if(!$error){// если нет ошибок
                    $before = null;// предыдущий элимент
                    for($i = 0, $iLen = count($items); $i < $iLen; $i++){
                        $player = $items[$i];// получаем очередной элимент
                        $role = $roles->get($player["role"]);
                        // вспомогательные переменные
                        $title = "";// заголовок группы
                        $description = "";// описание группы
                        // вычисляем заголовок и описание группы
                        if(!$before){// если первый игрок
                            if(!$player["reserve"]){// если не резерв
                                $title = $additions->get("group", $language);
                                if(!$limit) $j = $app["fun"]["numDeclin"]($count , 0, 1, 2);
                                else if("ru" == $language) $j = $app["fun"]["numDeclin"]($limit , 0, 2, 0);
                                else $j = $app["fun"]["numDeclin"]($limit , 0, 1, 2);// для остальных языков
                                $description = $count . ($limit ? " " . $names->get("from", $language) . " " . $limit : "");
                                $description .= " " . explode($app["val"]["valueDelim"], $names->get("player", $language))[$j];
                            }else $title = $additions->get("reserve", $language);
                        }else if($before["reserve"] != $player["reserve"]){
                            $title = $additions->get("reserve", $language);
                        }else if($before["accept"] != $player["accept"]){
                            if(!$player["reserve"]){// если не резерв
                                $title = $names->get("candidate", $language);
                            };
                        };
                        // добавляем заголовок и описание группы
                        if($title){// если есть данные
                            // отделяем заголовок группы
                            $line = $blank;// пустая строка
                            array_push($lines[$group], $line);
                            // выводим заголовок группы
                            $line = "**" . mb_ucfirst(mb_strtolower($title)) . ":**";
                            if($description) $line .= " " . $description;
                            array_push($lines[$group], $line);
                        };
                        // добавляем запись игрока
                        $line = "**`" . str_pad($i + 1, 2, "0", STR_PAD_LEFT) . "`**";
                        $line .= " -" . ($player["accept"] ? $additions->get("accept", "icon") : " ");
                        $line .= mb_strlen($role[$language]) > 2 ? $role[$language] : mb_strtoupper($role[$language]);
                        $line .= " " . ($event["leader"] == $player["user"] ? $additions->get("leader", "icon") : "");
                        $line .= "<@!" . $player["user"] . ">" . (mb_strlen($player["comment"]) ? " " . $app["fun"]["wrapUrl"]($player["comment"]) : "");
                        array_push($lines[$group], $line);
                        // сохраняем состояние
                        $before = $player;
                    };
                    // отделяем картинку от играков
                    if($before){// если первый игрок
                        $line = $blank;// пустая строка
                        array_push($lines[$group], $line);
                    };
                };
                // формируем встроенный объект
                if(!$error){// если нет ошибок
                    $embed = null;
                };
                // формируем сообщение
                if(!$error){// если нет ошибок
                    $list = $app["fun"]["createMessages"]($embed, $lines, $delim, $app["val"]["discordMessageLength"]);
                    $message = array_merge($message, array_shift($list));// обединяем с первым сообщением из списка
                    if(count($list)) $message = array_merge($message, $app["fun"]["getFeedbackMessage"](84, $language, $status));
                };
            };
            // возвращаем результат
            return $message;
        },
        "getNoticeMessage" => function($eid, &$status){// формирует сообщение для уведомления о событии
        //@param $eid {integer} - идентификатор события
        //@param $status {integer} - целое число статуса выполнения
        //@return {array} - сообщение или пустой массив при ошибке
            global $app;
            $error = 0;

            $game = $app["val"]["game"];
            $message = array();// сообщение
            // загружаем все необходимые базы данных
            if(empty($status)){// если нет ошибок
                $session = $app["fun"]["getStorage"]($game, "session", true);
                if(!empty($session)){// если удалось получить доступ к базе данных
                }else $status = 304;// не удалось загрузить одну из многих баз данных
            };
            if(empty($status)){// если нет ошибок
                $events = $app["fun"]["getStorage"]($game, "events", true);
                if(!empty($events)){// если удалось получить доступ к базе данных
                }else $status = 304;// не удалось загрузить одну из многих баз данных
            };
            if(empty($status)){// если нет ошибок
                $raids = $app["fun"]["getStorage"]($game, "raids", false);
                if(!empty($raids)){// если удалось получить доступ к базе данных
                }else $status = 304;// не удалось загрузить одну из многих баз данных
            };
            if(empty($status)){// если нет ошибок
                $chapters = $app["fun"]["getStorage"]($game, "chapters", false);
                if(!empty($chapters)){// если удалось получить доступ к базе данных
                }else $status = 304;// не удалось загрузить одну из многих баз данных
            };
            if(empty($status)){// если нет ошибок
                $types = $app["fun"]["getStorage"]($game, "types", false);
                if(!empty($types)){// если удалось получить доступ к базе данных
                }else $status = 304;// не удалось загрузить одну из многих баз данных
            };
            if(empty($status)){// если нет ошибок
                $names = $app["fun"]["getStorage"](null, "names", false);
                if(!empty($names)){// если удалось получить доступ к базе данных
                }else $status = 304;// не удалось загрузить одну из многих баз данных
            };
            // выполняем формирование сообщения
            if(empty($status)){// если нет ошибок
                $event = null;// событие
                $leader = null;// лидер
                // проверяем переданные идентификаторы
                if(!$error){// если нет ошибок
                    if($eid){// если проверка пройдена
                        $event = $events->get($eid);
                    }else $error = 1;
                };
                // проверяем успешность получения события
                if(!$error){// если нет ошибок
                    if($event){// если проверка пройдена
                        $raid = $raids->get($event["raid"]);
                        $type = $types->get($raid["type"]);
                        $chapter = $chapters->get($raid["chapter"]);
                    }else $error = 1;
                };
                // получаем информацию о гильдии
                if(!$error){// если нет ошибок
                    $guild = $app["fun"]["getCache"]("guild", $event["guild"]);
                    if($guild){// если удалось получить данные
                    }else $error = 2;
                };
                // получаем информацию о канале
                if(!$error){// если нет ошибок
                    $channel = $app["fun"]["getCache"]("channel", $event["channel"], $event["guild"], null);
                    if($channel){// если удалось получить данные
                    }else $error = 3;
                };
                // получаем конфигурацию канала
                if(!$error){// если нет ошибок
                    $config = $app["fun"]["getChannelConfig"]($channel, $guild, null);
                    if($config){// если удалось получить данные
                        $language = $config["language"];
                        $timezone = $config["timezone"];
                    }else $error = 4;
                };
                // получаем информацию о лидере
                if(!$error and !empty($event["leader"])){// если нужно выполнить
                    $leader = $app["fun"]["getCache"]("member", $event["leader"], $guild["id"]);
                };
                // формируем встроенный объект
                if(!$error){// если нет ошибок
                    $leader = $event["leader"] ? $app["fun"]["getCache"]("member", $event["leader"], $guild["id"]) : null;
                    // формируем комментарий к рейду
                    $value = mb_ucfirst(trim(str_replace("<br>", $app["val"]["lineDelim"], $event["description"])));
                    $delim = mb_strlen($value) ? mb_substr($value, mb_strlen($value) - 1, 1) : "";
                    if(false !== mb_strpos($value, $app["val"]["lineDelim"])) $delim = $app["val"]["lineDelim"];
                    else if(mb_strlen($delim)) $delim = false === mb_strpos(".?!", $delim) ? ". " : " ";
                    $value = $app["fun"]["addWhenShort"]($value, $delim . $names->get("notice", $language), 64);
                    // формируем встраиваемый объект
                    $embed = array();// задаём не пустое значение
                    $embed["type"] = "rich";// формат встраиваемого контента
                    $embed["color"] = 176372;// цвет встраиваемого контента
                    $embed["timestamp"] = $app["fun"]["dateFormat"]("c", $event["time"], "UTC");
                    $embed["author"] = array("name" => $guild["name"]);
                    $embed["url"] = $app["fun"]["href"](template($app["val"]["eventUrl"], array("group" => $game, "id" => $event["id"], "name" => $raid["key"])));
                    $embed["description"] = $value . " [#" . trim($app["fun"]["delEmoji"]($channel["name"]), " -_|") . "](" . $app["val"]["discordUrl"] . implode("/", array("channels", $event["guild"], $event["channel"], $event["message"])) . ")";
                    $embed["title"] = $raid["key"] . " - " . $raid[$language] . (!empty($chapter[$language]) ? " DLC " . $chapter[$language] : "");
                    $embed["image"] = array("url" => template($app["val"]["discordContentUrl"], array("group" => "attachments", "id" => $raid["image"])));
                    $embed["footer"] = array("text" => ($leader ? (mb_strlen($leader["nick"]) ? $leader["nick"] : $leader["user"]["username"]) . " " : "") . $names->get("invite", "icon") . " " . $session->get("copyright", "value"));
                    $emoji = $app["fun"]["getEmoji"]($type["logotype"]);
                    if(!empty($emoji["id"])) $embed["thumbnail"] = array("url" => template($app["val"]["discordContentUrl"], array("group" => "emojis", "id" => $emoji["id"])));
                    if(!empty($guild["icon"])) $embed["author"]["icon_url"] = template($app["val"]["discordContentUrl"], array("group" => "icons", "name" => $guild["id"], "id" => $guild["icon"]));
                    if(!empty($leader)) $embed["footer"]["icon_url"] = template($app["val"]["discordContentUrl"], $leader["user"]["avatar"] ? array("group" => "avatars", "name" => $leader["user"]["id"], "id" => $leader["user"]["avatar"]) : array("group" => "embed", "name" => "avatars", "id" => $leader["user"]["discriminator"] % 5));
                };
                // формируем сообщение
                if(!$error){// если нет ошибок
                    $list = $app["fun"]["createMessages"]($embed);
                    $message = array_merge($message, array_shift($list));
                };
            };
            // возвращаем результат
            return $message;
        },
        "getScheduleMessages" => function($gid, $language, $timezone, $filters, &$status){// формирует сообщения для сводного расписания
        //@param $gid {string} - идентификатор гильдии для построения сводного расписания
        //@param $language {string} - язык используемый для формирования команды
        //@param $timezone {string} - временная зона для формирования команды
        //@param $filters {array} - массив с дополнительными фильтрами
        //@param $status {integer} - целое число статуса выполнения
        //@return {array} - массив сообщений или пустой массив при ошибке
            global $app;
            $error = 0;

            $game = $app["val"]["game"];
            $messages = array();// сообщения
            // загружаем все необходимые базы данных
            if(empty($status)){// если нет ошибок
                $events = $app["fun"]["getStorage"]($game, "events", true);
                if(!empty($events)){// если удалось получить доступ к базе данных
                }else $status = 304;// не удалось загрузить одну из многих баз данных
            };
            if(empty($status)){// если нет ошибок
                $raids = $app["fun"]["getStorage"]($game, "raids", false);
                if(!empty($raids)){// если удалось получить доступ к базе данных
                }else $status = 304;// не удалось загрузить одну из многих баз данных
            };
            if(empty($status)){// если нет ошибок
                $chapters = $app["fun"]["getStorage"]($game, "chapters", false);
                if(!empty($chapters)){// если удалось получить доступ к базе данных
                }else $status = 304;// не удалось загрузить одну из многих баз данных
            };
            if(empty($status)){// если нет ошибок
                $types = $app["fun"]["getStorage"]($game, "types", false);
                if(!empty($types)){// если удалось получить доступ к базе данных
                }else $status = 304;// не удалось загрузить одну из многих баз данных
            };
            if(empty($status)){// если нет ошибок
                $names = $app["fun"]["getStorage"](null, "names", false);
                if(!empty($names)){// если удалось получить доступ к базе данных
                }else $status = 304;// не удалось загрузить одну из многих баз данных
            };
            if(empty($status)){// если нет ошибок
                $additions = $app["fun"]["getStorage"](null, "additions", false);
                if(!empty($additions)){// если удалось получить доступ к базе данных
                }else $status = 304;// не удалось загрузить одну из многих баз данных
            };
            if(empty($status)){// если нет ошибок
                $dates = $app["fun"]["getStorage"](null, "dates", false);
                if(!empty($dates)){// если удалось получить доступ к базе данных
                }else $status = 304;// не удалось загрузить одну из многих баз данных
            };
            if(empty($status)){// если нет ошибок
                $months = $app["fun"]["getStorage"](null, "months", false);
                if(!empty($months)){// если удалось получить доступ к базе данных
                }else $status = 304;// не удалось загрузить одну из многих баз данных
            };
            // выполняем формирование сообщения
            if(empty($status)){// если нет ошибок
                $blank = "_ _";// не удаляемый пустой символ
                $delim = $app["val"]["lineDelim"];
                $group = -1;// сбрасываем группу строк
                $lines = array();// сгруппированный список строк
                // проверяем переданный идентификатор
                if(!$error){// если нет ошибок
                    if($gid){// если проверка пройдена
                    }else $error = 1;
                };
                // формируем список событий
                if(!$error){// если нет ошибок
                    $items = array();// список событий
                    for($i = 0, $iLen = $events->length; $i < $iLen; $i++){
                        $id = $events->key($i);// получаем ключевой идентификатор по индексу
                        $event = $events->get($id);// получаем элемент по идентификатору
                        $flag = true;// прошло ли событие проверку
                        // выполняем проверку
                        if($flag){// если пройдена проверка
                            $flag = ($flag and $event["guild"] == $gid);
                            $flag = ($flag and !$event["hide"]);
                        };
                        // выполняем проверку
                        if($flag and count($filters["types"])){// если нужно выполнить
                            $raid = $raids->get($event["raid"]);
                            $flag = ($flag and in_array($raid["type"], $filters["types"]));
                        };
                        // добавляем событие в список
                        if($flag) array_push($items, $event);
                    };
                };
                // сортируем список событий
                if(!$error){// если нет ошибок
                    usort($items, function($a, $b){// сортировка
                        $value = 0;// начальное значение
                        if(!$value and $a["time"] != $b["time"]) $value = $a["time"] > $b["time"] ? 1 : -1;
                        if(!$value and $a["id"] != $b["id"]) $value = $a["id"] > $b["id"] ? 1 : -1;
                        // возвращаем результат
                        return $value;
                    });
                };
                // формируем сводное расписание
                if(!$error){// если нет ошибок
                    $before = null;// предыдущий элимент
                    for($i = 0, $iLen = count($items); $i < $iLen; $i++){
                        $event = $items[$i];// получаем очередной элимент
                        $raid = $raids->get($event["raid"]);
                        $type = $types->get($raid["type"]);
                        $chapter = $chapters->get($raid["chapter"]);
                        // отделяем каждое событие
                        $lines[++$group] = array();// новая группа строк
                        $line = $blank;// пустая строка
                        if($before) array_push($lines[$group], $line);
                        // формируем шапку
                        if(!$before or $app["fun"]["dateFormat"]("d.m.Y", $before["time"], $timezone) != $app["fun"]["dateFormat"]("d.m.Y", $event["time"], $timezone)){// если нужно выполнить
                            $line = "```";
                            array_push($lines[$group], $line);
                            $line = $names->get("schedule", "icon") . " " . $app["fun"]["dateFormat"]("d", $event["time"], $timezone);
                            $line .= " " . $months->get($app["fun"]["dateFormat"]("n", $event["time"], $timezone), $language);
                            $line .= " - " . mb_ucfirst(mb_strtolower($dates->get(mb_strtolower($app["fun"]["dateFormat"]("l", $event["time"], $timezone)), $language)));
                            array_push($lines[$group], $line);
                            $line = "```";
                            array_push($lines[$group], $line);
                        };
                        // формируем заголовок
                        $line = "**" . $app["fun"]["dateFormat"]("H:i", $event["time"], $timezone) . "** —" . $type["logotype"];
                        $line .= "**" . $raid["key"] . "** " . $raid[$language] . (!empty($chapter[$language]) ? " **DLC " . $chapter[$language] . "**" : "");
                        array_push($lines[$group], $line);
                        $line = $event["leader"] ? $additions->get("leader", "icon") . "<@!" . $event["leader"] . ">" : $names->get("begin", $language);
                        $line .= " <" . implode(":", array("t", $app["fun"]["dateFormat"]("U", $event["time"], $timezone), "R")) . "> <#" . $event["channel"] . ">";
                        array_push($lines[$group], $line);
                        $value = mb_ucfirst(trim(str_replace("<br>", " ", $app["fun"]["strimStrMulti"]($event["description"], "."))));
                        $line = $additions->get("reserve", "icon") . " " . $value;
                        if(mb_strlen($value)) array_push($lines[$group], $line);
                        // сохраняем состояние
                        $before = $event;
                    };
                };
                // формируем встроенный объект
                if(!$error){// если нет ошибок
                    $embed = null;// встроенный объект
                };
                // формируем сообщения
                if(!$error){// если нет ошибок
                    $list = $app["fun"]["createMessages"]($embed, $lines, $delim, $app["val"]["discordMessageLength"]);
                    $messages = array_merge($messages, $list);// добавляем полученные сообщения в конец списка
                };
            };
            // возвращаем результат
            return $messages;
        },
        "getLeaderBoardMessages" => function($gid, $language, $timezone, $filters, &$status){// формирует сообщения для сводного расписания
        //@param $gid {string} - идентификатор гильдии для построения сводного расписания
        //@param $language {string} - язык используемый для формирования команды
        //@param $timezone {string} - временная зона для формирования команды
        //@param $filters {array} - массив с дополнительными фильтрами
        //@param $status {integer} - целое число статуса выполнения
        //@return {array} - массив сообщений или пустой массив при ошибке
            global $app;
            $error = 0;

            $game = $app["val"]["game"];
            $messages = array();// сообщения
            // загружаем все необходимые базы данных
            if(empty($status)){// если нет ошибок
                $events = $app["fun"]["getStorage"]($game, "events", true);
                if(!empty($events)){// если удалось получить доступ к базе данных
                }else $status = 304;// не удалось загрузить одну из многих баз данных
            };
            if(empty($status)){// если нет ошибок
                $players = $app["fun"]["getStorage"]($game, "players", true);
                if(!empty($players)){// если удалось получить доступ к базе данных
                }else $status = 304;// не удалось загрузить одну из многих баз данных
            };
            if(empty($status)){// если нет ошибок
                $raids = $app["fun"]["getStorage"]($game, "raids", false);
                if(!empty($raids)){// если удалось получить доступ к базе данных
                }else $status = 304;// не удалось загрузить одну из многих баз данных
            };
            if(empty($status)){// если нет ошибок
                $types = $app["fun"]["getStorage"]($game, "types", false);
                if(!empty($types)){// если удалось получить доступ к базе данных
                }else $status = 304;// не удалось загрузить одну из многих баз данных
            };
            if(empty($status)){// если нет ошибок
                $names = $app["fun"]["getStorage"](null, "names", false);
                if(!empty($names)){// если удалось получить доступ к базе данных
                }else $status = 304;// не удалось загрузить одну из многих баз данных
            };
            if(empty($status)){// если нет ошибок
                $additions = $app["fun"]["getStorage"](null, "additions", false);
                if(!empty($additions)){// если удалось получить доступ к базе данных
                }else $status = 304;// не удалось загрузить одну из многих баз данных
            };
            // выполняем формирование сообщения
            if(empty($status)){// если нет ошибок
                $delim = $app["val"]["lineDelim"];
                $group = -1;// сбрасываем группу строк
                $lines = array();// сгруппированный список строк
                // проверяем переданный идентификатор
                if(!$error){// если нет ошибок
                    if($gid){// если проверка пройдена
                    }else $error = 1;
                };
                // формируем список событий
                if(!$error){// если нет ошибок
                    $list = array();// список идентификаторов событий
                    for($i = 0, $iLen = $events->length; $i < $iLen; $i++){
                        $id = $events->key($i);// получаем ключевой идентификатор по индексу
                        $event = $events->get($id);// получаем элемент по идентификатору
                        $flag = true;// прошло ли событие проверку
                        // выполняем проверку
                        if($flag){// если пройдена проверка
                            $flag = ($flag and $event["guild"] == $gid);
                            $flag = ($flag and $event["close"]);
                        };
                        // выполняем проверку
                        if($flag and count($filters["types"])){// если нужно выполнить
                            $raid = $raids->get($event["raid"]);
                            $flag = ($flag and in_array($raid["type"], $filters["types"]));
                        };
                        // добавляем идентификатор события в список
                        if($flag) array_push($list, $id);
                    };
                };
                // выполняем подсчёт рейтинга
                if(!$error){// если нет ошибок
                    $rate = array();// рейтинг пользователей
                    $count = array();// многомерный счётчик событий
                    for($i = 0, $iLen = $players->length; $i < $iLen; $i++){
                        $id = $players->key($i);// получаем ключевой идентификатор по индексу
                        $player = $players->get($id);// получаем элемент по идентификатору
                        $flag = true;// прошёл ли участник проверку
                        // выполняем проверку
                        if($flag){// если пройдена проверка
                            $flag = ($flag and !$player["reserve"]);
                            $flag = ($flag and in_array($player["event"], $list));
                        };
                        // выполняем проверку
                        if($flag and count($filters["roles"])){// если нужно выполнить
                            $flag = ($flag and in_array($player["role"], $filters["roles"]));
                        };
                        // считаем рейтинг участника
                        if($flag){// если игрок и событие учавствует в подсчёте
                            $event = $events->get($player["event"]);
                            $raid = $raids->get($event["raid"]);
                            $type = $types->get($raid["type"]);
                            // создаём структуру рейтинга и счётчика
                            if(!isset($rate[$player["user"]])) $rate[$player["user"]] = 0;
                            if(!isset($count[$player["user"]])) $count[$player["user"]] = array();
                            if(!isset($count[$player["user"]][$raid["type"]])) $count[$player["user"]][$raid["type"]] = 0;
                            $count[$player["user"]][$raid["type"]]++;
                            $rate[$player["user"]] += $type["rate"];
                        };
                    };
                };
                // формируем таблицу рейтинга
                if(!$error){// если нет ошибок
                    $index = 0;// позиция в рейтинге
                    $limit = 99;// количество участников рейтинга
                    arsort($rate);// упорядочиваем рейтинг
                    foreach($rate as $uid => $value) {
                        $item = $count[$uid];
                        // формируем шапку
                        if(!$index){// если нужно выполнить
                            $lines[++$group] = array();// новая группа строк
                            $line = "```";
                            array_push($lines[$group], $line);
                            $line = $names->get("leaderboard", "icon") . " " . $names->get("leaderboard", $language);
                            array_push($lines[$group], $line);
                            $line = "```";
                            array_push($lines[$group], $line);
                        };
                        // формируем запись участника
                        arsort($item);// упорядочиваем счётчик
                        $list = array();// список фразментов
                        $line = ($index ? "**`" . str_pad($index + 1, 2, "0", STR_PAD_LEFT) . "`**" : $additions->get("leader", "icon"));
                        $line .= " <@!" . $uid . "> ";
                        foreach($item as $key => $value) {
                            $type = $types->get($key);
                            $value = $value . $type["logotype"];
                            array_push($list, $value);
                        };
                        $line .= " " . implode(" ", $list);
                        $line .= "= **" . number_format($rate[$uid], 2, ",", "") . "**";
                        $line .= " " . explode($app["val"]["valueDelim"], $names->get("rate", $language))[$app["fun"]["numDeclin"]($rate[$uid], 0, 1, 2)];
                        array_push($lines[$group], $line);
                        if($index < $limit - 1) $index++;
                        else break;
                    };
                };
                // формируем встроенный объект
                if(!$error){// если нет ошибок
                    $embed = null;// встроенный объект
                };
                // формируем сообщения
                if(!$error){// если нет ошибок
                    $list = $app["fun"]["createMessages"]($embed, $lines, $delim, $app["val"]["discordMessageLength"]);
                    $messages = array_merge($messages, $list);// добавляем полученные сообщения в конец списка
                };
            };
            // возвращаем результат
            return $messages;
        },
        "getCommand" => function($content, $language, $timezone, $timestamp, &$status){// получает команду из контента
        //@param $content {string} - строка с данными для формирования команды
        //@param $language {string|null} - язык используемый для формирования команды
        //@param $timezone {string|null} - временная зона для формирования команды
        //@param $timestamp {float} - временная метка для расчёта времени
        //@param $status {integer} - целое число статуса выполнения
        //@return {array} - полученная команда
            global $app;

            $command = array(// пустая команда
                "action" => "",         // действие
                "roles" => array(),     // роли
                "index" => 0,           // номер
                "date" => 0,            // дата
                "time" => 0,            // дата и время
                "raid" => "",           // рейд
                "users" => array(),     // пользователи
                "additions" => array(), // опции
                "comment" => "",        // комментарий
                "description" => ""     // описание
            );
            $game = $app["val"]["game"];
            // загружаем все необходимые базы данных
            if(empty($status)){// если нет ошибок
                $raids = $app["fun"]["getStorage"]($game, "raids", false);
                if(!empty($raids)){// если удалось получить доступ к базе данных
                }else $status = 304;// не удалось загрузить одну из многих баз данных
            };
            if(empty($status)){// если нет ошибок
                $roles = $app["fun"]["getStorage"]($game, "roles", false);
                if(!empty($roles)){// если удалось получить доступ к базе данных
                }else $status = 304;// не удалось загрузить одну из многих баз данных
            };
            if(empty($status)){// если нет ошибок
                $actions = $app["fun"]["getStorage"](null, "actions", false);
                if(!empty($actions)){// если удалось получить доступ к базе данных
                }else $status = 304;// не удалось загрузить одну из многих баз данных
            };
            if(empty($status)){// если нет ошибок
                $additions = $app["fun"]["getStorage"](null, "additions", false);
                if(!empty($additions)){// если удалось получить доступ к базе данных
                }else $status = 304;// не удалось загрузить одну из многих баз данных
            };
            if(empty($status)){// если нет ошибок
                $dates = $app["fun"]["getStorage"](null, "dates", false);
                if(!empty($dates)){// если удалось получить доступ к базе данных
                }else $status = 304;// не удалось загрузить одну из многих баз данных
            };
            // отделяем первою строку
            if(empty($status)){// если нет ошибок
                $delim = $app["val"]["lineDelim"];
                $content = trim($content);
                $index = mb_strpos($content, $delim);
                if(false !== $index){// если найден разделитель
                    $line = mb_substr($content, 0, $index);
                    $content = mb_substr($content, $index);
                }else{// если не найден разделитель
                    $line = $content;
                    $content = "";
                };
            };
            // формируем команду из строки
            for($index = 0; empty($status); $index++){// циклическая обработка
                // действие - KEY
                $delim = " ";// разделитель параметров
                $key = "action";// текущий параметр команды
                $value = $command[$key];// текущий значение параметра
                if(!$index and !mb_strlen($value)){// если нужно выполнить
                    $value = $app["fun"]["getCommandValue"]($line, $delim, null, $actions);
                    if(mb_strlen($value)){// если найдено ключевое значение
                        $command[$key] = $value;
                        continue;
                    }else break;
                };
                // роли - KEY
                $key = "roles";// текущий параметр команды
                if($index and $language){// если нужно выполнить
                    $value = $app["fun"]["getCommandValue"]($line, $delim, $language, $roles);
                    if(mb_strlen($value)){// если найдено ключевое значение
                        array_push($command[$key], $value);
                        continue;
                    };
                };
                // номер - ID
                $key = "index";// текущий параметр команды
                $value = $command[$key];// текущий значение параметра
                if(!$value){// если нужно выполнить
                    $value = $app["fun"]["getCommandValue"]($line, $delim, null, "/^(0\d{2})$/", $list);
                    if(mb_strlen($value)){// если найдено ключевое значение
                        $command[$key] = 1 * $value;
                        continue;
                    };
                };
                // дата - KEY
                $key = "date";// текущий параметр команды
                $value = $command[$key];// текущий значение параметра
                if(!$value and $language and $timezone){// если нужно выполнить
                    $value = $app["fun"]["getCommandValue"]($line, $delim, $language, $dates);
                    if(mb_strlen($value)){// если найдено ключевое значение
                        $value = $app["fun"]["dateCreate"]($value, $timezone);
                        $command[$key] = $value;
                        continue;
                    };
                };
                // дата - DD.MM.YYYY
                $key = "date";// текущий параметр команды
                $value = $command[$key];// текущий значение параметра
                if(!$value and $timezone){// если нужно выполнить
                    $value = $app["fun"]["getCommandValue"]($line, $delim, null, "/^(\d{1,2})\.(\d{2})\.(\d{4})$/", $list);
                    if(mb_strlen($value)){// если найдено ключевое значение
                        $value = implode(".", array($list[1], $list[2], $list[3]));
                        $value = $app["fun"]["dateCreate"]($value, $timezone);
                        $command[$key] = $value;
                        continue;
                    };
                };
                // дата - DD.MM.YY
                $key = "date";// текущий параметр команды
                $value = $command[$key];// текущий значение параметра
                if(!$value and $timezone){// если нужно выполнить
                    $value = $app["fun"]["getCommandValue"]($line, $delim, null, "/^(\d{1,2})\.(\d{2})\.(\d{2})$/", $list);
                    if(mb_strlen($value)){// если найдено ключевое значение
                        $value = implode(".", array($list[1], $list[2], mb_substr($app["fun"]["dateFormat"]("Y", $timestamp, $timezone), 0, 2) . $list[3]));
                        $value = $app["fun"]["dateCreate"]($value, $timezone);
                        $command[$key] = $value;
                        continue;
                    };
                };
                // дата - DD.MM
                $key = "date";// текущий параметр команды
                $value = $command[$key];// текущий значение параметра
                if(!$value and $timezone){// если нужно выполнить
                    $value = $app["fun"]["getCommandValue"]($line, $delim, null, "/^(\d{1,2})\.(\d{2})$/", $list);
                    if(mb_strlen($value)){// если найдено ключевое значение
                        $t1 = $app["fun"]["dateCreate"](implode(".", array($list[1], $list[2], $app["fun"]["dateFormat"]("Y", $timestamp, $timezone))), $timezone);
                        $t2 = $app["fun"]["dateCreate"](implode(".", array($list[1], $list[2], $app["fun"]["dateFormat"]("Y", $app["fun"]["dateCreate"]("+1 year", $timezone), $timezone))), $timezone);
                        $value = (abs($t1 - $timestamp) < abs($t2 - $timestamp) ? $t1 : $t2);
                        $command[$key] = $value;
                        continue;
                    };
                };
                // дата - MM/DD/YYYY
                $key = "date";// текущий параметр команды
                $value = $command[$key];// текущий значение параметра
                if(!$value and $timezone){// если нужно выполнить
                    $value = $app["fun"]["getCommandValue"]($line, $delim, null, "/^(\d{1,2})\/(\d{2})\/(\d{4})$/", $list);
                    if(mb_strlen($value)){// если найдено ключевое значение
                        $value = implode(".", array($list[2], $list[1], $list[3]));
                        $value = $app["fun"]["dateCreate"]($value, $timezone);
                        $command[$key] = $value;
                        continue;
                    };
                };
                // дата - MM/DD/YY
                $key = "date";// текущий параметр команды
                $value = $command[$key];// текущий значение параметра
                if(!$value and $timezone){// если нужно выполнить
                    $value = $app["fun"]["getCommandValue"]($line, $delim, null, "/^(\d{1,2})\/(\d{2})\/(\d{2})$/", $list);
                    if(mb_strlen($value)){// если найдено ключевое значение
                        $value = implode(".", array($list[2], $list[1], mb_substr($app["fun"]["dateFormat"]("Y", $timestamp, $timezone), 0, 2) . $list[3]));
                        $value = $app["fun"]["dateCreate"]($value, $timezone);
                        $command[$key] = $value;
                        continue;
                    };
                };
                // дата - MM/DD
                $key = "date";// текущий параметр команды
                $value = $command[$key];// текущий значение параметра
                if(!$value and $timezone){// если нужно выполнить
                    $value = $app["fun"]["getCommandValue"]($line, $delim, null, "/^(\d{1,2})\/(\d{2})$/", $list);
                    if(mb_strlen($value)){// если найдено ключевое значение
                        $t1 = $app["fun"]["dateCreate"](implode(".", array($list[2], $list[1], $app["fun"]["dateFormat"]("Y", $timestamp, $timezone))), $timezone);
                        $t2 = $app["fun"]["dateCreate"](implode(".", array($list[2], $list[1], $app["fun"]["dateFormat"]("Y", $app["fun"]["dateCreate"]("+1 year", $timezone), $timezone))), $timezone);
                        $value = (abs($t1 - $timestamp) < abs($t2 - $timestamp) ? $t1 : $t2);
                        $command[$key] = $value;
                        continue;
                    };
                };
                // время - HH:MM
                $key = "time";// текущий параметр команды
                $value = $command[$key];// текущий значение параметра
                if(!$value and $timezone){// если нужно выполнить
                    $value = $app["fun"]["getCommandValue"]($line, $delim, null, "/^(\d{1,2})\:(\d{2})$/", $list);
                    if(mb_strlen($value)){// если найдено ключевое значение
                        $value = implode(":", array($list[1], $list[2]));
                        $value = $app["fun"]["dateCreate"]($value, $timezone);
                        $command[$key] = $value;
                        continue;
                    };
                };
                // рейд - KEY
                $key = "raid";// текущий параметр команды
                $value = $command[$key];// текущий значение параметра
                if(!mb_strlen($value)){// если нужно выполнить
                    $fragment = $line;// копируем состояния строки
                    $value = $app["fun"]["getCommandValue"]($fragment, $delim);
                    if(1 == mb_strlen($value)) $value .= $app["fun"]["getCommandValue"]($fragment, $delim);
                    $value = $app["fun"]["getCommandValue"]($value, $delim, false, $raids);
                    if(mb_strlen($value)){// если найдено ключевое значение
                        $command[$key] = $value;
                        $line = $fragment;
                        continue;
                    };
                };
                // пользователи - ID
                $key = "users";// текущий параметр команды
                if($index){// если нужно выполнить
                    $value = $app["fun"]["getCommandValue"]($line, $delim, null, "/^<@!?(\d+)>/", $list);
                    if(mb_strlen($value)){// если найдено ключевое значение
                        $value = array_pop($list);
                        $flag = !in_array($value, $command[$key]);
                        if($flag) array_push($command[$key], $value);
                        continue;
                    };
                };
                // опция - KEY
                $key = "additions";// текущий параметр команды
                if($index and $language){// если нужно выполнить
                    $value = $app["fun"]["getCommandValue"]($line, $delim, $language, $additions);
                    if(mb_strlen($value)){// если найдено ключевое значение
                        array_push($command[$key], $value);
                        continue;
                    };
                };
                // комментарий - TEXT
                $delim = $app["val"]["lineDelim"];
                $key = "comment";// текущий параметр команды
                $value = $command[$key];// текущий значение параметра
                if(!mb_strlen($value)){// если нужно выполнить
                    $value = $app["fun"]["getCommandValue"]($line, $delim);
                    $value = trim($value);// убираем пробелы с концов
                    if(mb_strlen($value)){// если найдено ключевое значение
                        $command[$key] = $value;
                    };
                };
                // описание - TEXT
                $key = "description";// текущий параметр команды
                if($index){// если нужно выполнить
                    $value = trim($content);// убираем пробелы с концов
                    if(mb_strlen($value)){// если найдено ключевое значение
                        $command[$key] = $value;
                    };
                };
                // завершаем обработку
                break;
            };
            // корректируем время под указанную дату
            if(empty($status) and $command["date"] and $command["time"]){// если нужно выполнить
                $value = $app["fun"]["dateFormat"]("d.m.Y", $command["date"], $timezone);
                $value .= " " . $app["fun"]["dateFormat"]("H:i", $command["time"], $timezone);
                $command["time"] = $app["fun"]["dateCreate"]($value, $timezone);
            };
            // возвращаем результат
            return $command;
        },
        "getCommandValue" => function(&$content, $delim, $language = null, $filter = null, &$list = null){// получаем очередное значение
        //@param $content {string} - страка значений для обработки (сокращается на значение и разделители)
        //@param $delim {string} - разделитель значенией в строке (может состоять из нескольких символов)
        //@param $language {string|null|false} - проверять так же данные в этом ключе (если null, то совподение с началом вода)
        //@param $filter {FileStorage|string} - база данных или регулярное выражение для поиска совподения
        //@param $list {array} - результаты поиска для регулярного выражения
        //@return {string} - очередное ключевое значение или пустая строка
            global $app;
            $result = "";

            $flag = false;// найдено ли совпадение
            // получаем значение из строки по разделителю
            $index = mb_stripos($content, $delim);
            if(false === $index) $fragment = $content;
            else $fragment = mb_substr($content, 0, $index);
            // выполняем проверку полученного значение
            if(!empty($filter)){// если есть ограничивающий фильтр
               if(!is_string($filter)){// если передана база данных
                    // выполняем последовательную проверку с элементами базы данных
                    for($i = 0, $iLen = $filter->length; $i < $iLen and !$flag; $i++){
                        $key = $filter->key($i);// получаем ключевой идентификатор по индексу
                        $item = $filter->get($key);// получаем элемент по идентификатору
                        // проверяем совпадение с ключевым значением
                        if(!$flag and isset($item["key"])){// если совподений ещё не было
                            $value = $item["key"];// получаем значение свойства
                            $length = mb_strlen($value);// получаем длину значения
                            if($length){// если не пустое значение
                                $index = mb_stripos($fragment, $value);// начало совпадения
                                $flag = (is_null($language) or mb_strlen($fragment) == $length);
                                $flag = ($flag and 0 === $index);// найдено ли совпадение
                            };
                        };
                        // проверяем совпадение в языковом ключе по полному значению
                        if(!$flag and isset($item[$language])){// если совподений ещё не было
                            $value = $item[$language];// получаем значение свойства
                            $length = mb_strlen($value);// получаем длину значения
                            if($length){// если не пустое значение
                                $index = mb_stripos($fragment, $value);
                                $flag = mb_strlen($fragment) == $length;
                                $flag = ($flag and 0 === $index);
                            };
                        };
                        // проверяем совпадение в языковом ключе по короткому значению
                        if(!$flag and isset($item[$language])){// если совподений ещё не было
                            $value = $item[$language];// получаем значение свойства
                            $value = $app["fun"]["getShortValue"]($value);
                            $length = mb_strlen($value);// получаем длину значения
                            if($length){// если не пустое значение
                                $index = mb_stripos($fragment, $value);
                                $flag = mb_strlen($fragment) == $length;
                                $flag = ($flag and 0 === $index);
                            };
                        };
                        // сохраняем значение ключа и обрезаем исходную строку
                        if($flag){// если найдено совпадение со свойством элемента
                            $content = mb_substr($content, $length);
                            $result = $key;
                        };
                    };
                }else{// если передано регулярное выражение
                    // выполняем проверку на совподение с шаблоном
                    $flag = preg_match($filter, $fragment, $list);
                    if($flag){// если совподает с шаблоном
                        $value = is_null($language) ? $list[0] : $fragment;
                        $length = mb_strlen($value);// определяем длину
                        $content = mb_substr($content, $length);
                        $result = $value;
                    };
                };
            }else{// если нет ограничивающего фильтра
                // сокращаем строку на значение
                $length = mb_strlen($fragment);
                $content = mb_substr($content, $length);
                $result = $fragment;
            };
            // сокращаем на разделители
            while(0 === mb_stripos($content, $delim)){// пока есть разделитель
                $length = mb_strlen($delim);
                $content = mb_substr($content, $length);
            };
            // возвращаем результат
            return $result;
        },
        "getChannelConfig" => function($channel, $guild, $user){// получаем конфигурацию канала
        //@param $channel {array|string} - канал или его идентификатор
        //@param $guild {array|string} - гильдия или её идентификатор
        //@param $user {array|string} - пользователь или его идентификатор
        //@return {array|null} - конфигурационный массив или null при ошибки
            global $app;
            static $timezones;
            $error = 0;

            $game = $app["val"]["game"];
            $config = null;// конфигурация
            // кэшируем список временных зон
            if(!isset($timezones)){// если список пуст
                $timezones = timezone_identifiers_list();
            };
            // проверяем переданные параметры
            if(!$error){// если нет ошибок
                if($guild xor $user){// если удалось получить доступ к базе данных
                }else $error = 1;
            };
            // получаем гильдию
            if(!$error and $guild){// если нужно выполнить
                if(!is_array($guild) and !empty($guild)){// если нужно получить
                    $guild = $app["fun"]["getCache"]("guild", $guild);
                };
                if(!empty($guild)){// если не пустое значение
                }else $error = 2;
            };
            // получаем пользователя
            if(!$error and $user){// если нужно выполнить
                if(!is_array($user) and !empty($user)){// если нужно получить
                    $user = $app["fun"]["getCache"]("user", $user);
                };
                if(!empty($user)){// если не пустое значение
                }else $error = 3;
            };
            // получаем канал
            if(!$error){// если нет ошибок
                if(!is_array($channel) and !empty($channel)){// если нужно получить
                    if($guild) $channel = $app["fun"]["getCache"]("channel", $channel, $guild["id"], null);
                    if($user) $channel = $app["fun"]["getCache"]("channel", $channel, $user["id"], null);
                };
                if(!empty($channel)){// если не пустое значение
                }else $error = 4;
            };
            // загружаем базу данных
            if(!$error){// если нет ошибок
                $names = $app["fun"]["getStorage"](null, "names", false);
                if(!empty($names)){// если удалось получить доступ к базе данных
                }else $error = 5;
            };
            if(!$error){// если нет ошибок
                $types = $app["fun"]["getStorage"]($game, "types", false);
                if(!empty($types)){// если удалось получить доступ к базе данных
                }else $error = 6;
            };
            if(!$error){// если нет ошибок
                $roles = $app["fun"]["getStorage"]($game, "roles", false);
                if(!empty($roles)){// если удалось получить доступ к базе данных
                }else $error = 7;
            };
            if(!$error){// если нет ошибок
                $months = $app["fun"]["getStorage"](null, "months", false);
                if(!empty($months)){// если удалось получить доступ к базе данных
                }else $error = 8;
            };
            // формируем конфигурацию
            if(!$error){// если нет ошибок
                $config = array(// по умолчанию
                    "language" => $guild ? $app["val"]["discordLang"] : null,
                    "timezone" => $guild ? $app["val"]["timeZone"] : null,
                    "mode" => $user ? "direct" : "",
                    "filters" => array(// фильтры
                        "types" => array(),
                        "roles" => array()
                    ),
                    "history" => !$guild,
                    "flood" => true,
                    "sort" => "",
                    "game" => ""
                );
                // заполняем конфигурацию для канала гильдии
                if($guild){// если канал гильдии
                    // разбиваем тему канала на строки
                    if(mb_strlen($channel["topic"])){// если нужно выполнить
                        $delim = $app["val"]["lineDelim"];// разделитель строк
                        $lines = explode($delim, $channel["topic"]);
                    }else $lines = array();
                    // выполняем первый раз поиск параметров каналов
                    if($config["game"] != $game){// если игра ещё не определена
                        for($i = 0, $iLen = count($lines); $i < $iLen; $i++){// пробигаемся по строчкам
                            $line = trim($lines[$i]);// получаем очередное значение
                            // определяем совместимую игру канала
                            $flag = !$i;// найдено ли совпадение в первой строке
                            $flag = ($flag and 0 === mb_stripos($line, $game));
                            $flag = ($flag and mb_strlen($line) == mb_strlen($game));
                            if($flag) $config["game"] = $game;
                            else if(!$i) break;// останавливаем поиск
                            if($flag) continue;// переходим к следующей строке
                            // определяем режим и язык канала гильдии
                            $flag = false;// найдено ли подходящее значение
                            $id = $months->key(0);// получаем идентификатор по индексу
                            $month = $months->get($id);// получаем элемент по ключу
                            $list = array("record", "schedule", "leaderboard");// поддерживаемые режимы
                            for($j = 0, $jLen = count($list); $j < $jLen and !$flag; $j++){// пробигаемся по списку
                                $mode = $list[$j];// получаем очередной ключ
                                $item = $names->get($mode);// получаем элемент по ключу
                                foreach($month as $key => $value){// пробигаемся по ключам
                                    if($key != $months->primary){// если не первичный ключ
                                        $value = $item[$key];// получаем очередное значение
                                        $flag = 0 === mb_stripos($line, $value);
                                        if($flag){// если найдено совпадение
                                            $config["language"] = $key;
                                            $config["mode"] = $mode;
                                            break;
                                        };
                                    };
                                };
                            };
                            if($flag) continue;// переходим к следующей строке
                            // определяем часовой пояс канала
                            $flag = in_array($line, $timezones);
                            if($flag) $config["timezone"] = $line;
                            if($flag) continue;// переходим к следующей строке
                        };
                    };
                    // выполняем второй раз поиск параметров каналов гильдии
                    if($config["game"] == $game){// если игра уже определена
                        for($i = 0, $iLen = count($lines); $i < $iLen; $i++){// пробигаемся по строчкам
                            $line = trim($lines[$i]);// получаем очередное значение
                            // определяем запрет на флуд
                            $item = $names->get("noflood");// получаем элемент по ключу
                            $value = $item[$config["language"]];// получаем значение
                            $flag = 0 === mb_stripos($line, $value);
                            if($flag) $config["flood"] = false;
                            if($flag) continue;// переходим к следующей строке
                            // определяем сохранение истории
                            $key = "history";// задаём ключевой идентификатор
                            $item = $names->get($key);// получаем элемент по ключу
                            $value = $item[$config["language"]];// получаем значение
                            $flag = 0 === mb_stripos($line, $value);
                            if($flag) $config[$key] = true;
                            if($flag) continue;// переходим к следующей строке
                            // определяем режим сортировки
                            $flag = false;// найдено ли подходящее значение
                            $list = array("time");// поддерживаемые режимы
                            for($j = 0, $jLen = count($list); $j < $jLen and !$flag; $j++){// пробигаемся по списку
                                $mode = $list[$j];// получаем очередной ключ
                                $item = $names->get($mode);// получаем элемент по ключу
                                $value = $item[$config["language"]];// получаем значение
                                $flag = 0 === mb_stripos($line, $value);
                                if($flag) $config["sort"] = $mode;
                            };
                            if($flag) continue;// переходим к следующей строке
                            // определяем ограничение по типам событий
                            for($j = 0, $jLen = $types->length; $j < $jLen; $j++){// пробигаемся по списку
                                $key = $types->key($j);// получаем ключевой идентификатор по индексу
                                $item = $types->get($key);// получаем элемент по ключу
                                $value = $item[$config["language"]];// получаем значение
                                $flag = false !== mb_stripos($line, $value);
                                if($flag) $flag = !in_array($key, $config["filters"]["types"]);
                                if($flag) array_push($config["filters"]["types"], $key);
                            };
                            // определяем ограничение по роли игрока
                            for($j = 0, $jLen = $roles->length; $j < $jLen; $j++){// пробигаемся по списку
                                $key = $roles->key($j);// получаем ключевой идентификатор по индексу
                                $item = $roles->get($key);// получаем элемент по ключу
                                $value = $item[$config["language"]];// получаем значение
                                $flag = false !== mb_stripos($line, $value);
                                if($flag) $flag = !in_array($key, $config["filters"]["roles"]);
                                if($flag) array_push($config["filters"]["roles"], $key);
                            };
                        };
                    };
                };
            };
            // возвращаем результат
            return $config;
        },
        "getPermission" => function($level, $member, $channel, $guild){// получаем разрешения
        //@param $level {string} - уровень проверки разрешений
        //@param $member {array|string} - участник или его идентификатор
        //@param $channel {array|string} - канал или его идентификатор
        //@param $guild {array|string} - гильдия или её идентификатор
        //@return {integer} - значение разрешений
            global $app;
            $error = 0;
            
            $permission = 0;// разрешения
            // получаем гильдию
            if(!$error){// если нет ошибок
                if(!is_array($guild) and !empty($guild)){// если нужно получить
                    $guild = $app["fun"]["getCache"]("guild", $guild);
                };
                if(!empty($guild)){// если не пустое значение
                }else $error = 1;
            };
            // получаем канал
            if(!$error){// если нет ошибок
                if(!is_array($channel) and !empty($channel)){// если нужно получить
                    $channel = $app["fun"]["getCache"]("channel", $channel, $guild["id"], null);
                };
                if(!empty($channel)){// если не пустое значение
                }else $error = 2;
            };
            // получаем участника
            if(!$error){// если нет ошибок
                if(!is_array($member) and !empty($member)){// если нужно получить
                    $member = $app["fun"]["getCache"]("member", $member, $guild["id"]);
                };
                if(!empty($member)){// если не пустое значение
                }else $error = 3;
            };
            // последовательно вычисляем разрешения
            if(!$error and isset($channel["permission_overwrites"])){// если нужно выполнить
                switch($level){// поддерживаемые уровни проверки разрешений
                    case "guild":// начиная с гильдии
                        // проверяем разрешения для всех
                        for($i = 0, $iLen = count($guild["roles"]); $i < $iLen; $i++){
                            $role = $guild["roles"][$i];// получаем очередную роль
                            if($guild["id"] == $role["id"]){// если для всех
                                $permission |= $role["permissions"];
                                break;
                            };
                        };
                        // проверяем разрешения для ролей участника
                        for($i = 0, $iLen = count($guild["roles"]); $i < $iLen; $i++){
                            $role = $guild["roles"][$i];// получаем очередную роль
                            for($j = 0, $jLen = count($member["roles"]); $j < $jLen; $j++){
                                $rid = $member["roles"][$j];// получаем очередной идентификатор роли
                                if($rid == $role["id"]){// если для этой роли
                                    $permission |= $role["permissions"];
                                    break;
                                };
                            };
                        };
                    case "channel":// начиная с канала
                        // проверяем перезапись разрешений для всех
                        for($i = 0, $iLen = count($channel["permission_overwrites"]); $i < $iLen; $i++){
                            $overwrite = $channel["permission_overwrites"][$i];// получаем очередную перезапись
                            if($guild["id"] == $overwrite["id"] and "role" == $overwrite["type"]){// если для всех
                                $permission &= ~$overwrite["deny"];
                                $permission |= $overwrite["allow"];
                                break;
                            };
                        };
                        // проверяем перезапись разрешений для ролей участника
                        $permissions = array("allow" => 0, "deny" => 0);
                        for($i = 0, $iLen = count($channel["permission_overwrites"]); $i < $iLen; $i++){
                            $overwrite = $channel["permission_overwrites"][$i];// получаем очередную перезапись
                            for($j = 0, $jLen = count($member["roles"]); $j < $jLen; $j++){
                                $rid = $member["roles"][$j];// получаем очередной идентификатор роли
                                if($rid == $overwrite["id"] and "role" == $overwrite["type"]){// если для этой роли
                                    $permissions["allow"] |= $overwrite["allow"];
                                    $permissions["deny"] |= $overwrite["deny"];
                                    break;
                                };
                            };
                        };
                        $permission &= ~$permissions["deny"];
                        $permission |= $permissions["allow"];
                    case "member":// начиная с участника
                        // проверяем перезапись разрешений для участника
                        for($i = 0, $iLen = count($channel["permission_overwrites"]); $i < $iLen; $i++){
                            $overwrite = $channel["permission_overwrites"][$i];// получаем очередную перезапись
                            if($member["user"]["id"] == $overwrite["id"] and "member" == $overwrite["type"]){// если для участника
                                $permission &= ~$overwrite["deny"];
                                $permission |= $overwrite["allow"];
                                break;
                            };
                        };
                };
            };
            // возвращаем результат
            return $permission;
        },
        "strimStrMulti" => function($imput, $delim, $length = 0){// обрезает строку по мульти разделителю при привышении длины
        //@param $imput {string} - исходная строка для обрезки
        //@param $delim {string} - строка, где каждый символ разделитель
        //@param $length {integer} - минимальная допустимая длина без обрезки
        //@return {string} - обрезанная строка по первому разделителю или пустая строка
            
            $i = $iLen = mb_strlen($imput);
            for($j = 0, $jLen = mb_strlen($delim); $j < $jLen and $iLen > $length; $j++){
                $value = mb_strpos($imput, mb_substr($delim, $j, 1));
                if(false !== $value) $i = min($value, $i);
            };
            if($iLen > $i) $value = trim(mb_substr($imput, 0, $i));
            else if(!$jLen or $iLen <= $length) $value = $imput;
            else $value = "";
            // возвращаем результат
            return $value;
        },
        "getCustomKey" => function($imput, $delim, $required){// формирует ключ строки с разделителем
        //@param $imput {string} - исходная строка формирования ключа
        //@param $delim {string} - разделитель для исходной строки и ключа
        //@param $required {boolean} - обязательное наличее всех фрагментов
        //@param ...$index {integer} - индексы фрагментов исходной строки для формирования ключа
        //@return {string} - ключ из фрагментов исходной строки и разделителя или пустая строка
        
            // формируем новую последовательность для ключа
            $list = array();// список для формирования ключа
            $fragments = explode($delim, $imput);// разбиваем на фрагменты
            $flag = true;// нужно ли продолжать формирование ключа
            for($i = 3, $iLen = func_num_args(); $i < $iLen and $flag; $i++){
                $index = func_get_arg($i);// получаем очередной ключ
                if(isset($fragments[$index])){// если есть такой фрагмент
                    $value = $fragments[$index];
                    array_push($list, $value);
                }else if($required) $flag = false;
            };
            // формируем ключ из последовательности
            $key = $flag ? implode($delim, $list) : "";
            // возвращаем результат
            return $key;
        },
        "addWhenShort" => function($imput, $add, $length = 0){// добавляет если не хватает длины строки
        //@param $imput {string} - строковое значение к которому нужно добавить
        //@param $add {string} - добовляемое значение при нехватки длины
        //@param $length {integer} - минимальная допустимая длина без добавления
        //@return {string} - исходное или расширенное строковое значение
            
            $value = mb_strlen($imput) < $length ? $imput . $add : $imput;
            // возвращаем результат
            return $value;
        },
        "setDebug" => function($level, $name){// записывает отладочную информацию в файл 
        //@param $level {integer} - уровень отладочной информации
        //@param $name {string} - идентификатор отладочной информации
        //@return {boolean} - успешность выполнения
            global $app;
            static $times = array();
            $error = 0;

            $game = $app["val"]["game"];
            if($app["val"]["debugUrl"]){// если включён режим отладки
                $now = date_create();// текущее время
                $time = isset($times[$level]) ? $times[$level] : $now;
                $items = array();// массив отладочной информации
                // временная метка запуска скрипта
                $item = $_SERVER["REQUEST_TIME"];
                array_push($items, $item);
                // текущая дата и время на момент вызова
                $item = date_format($now, "d.m.y H:i:s.u");
                $item = mb_substr($item, 0, 21);
                array_push($items, $item);
                // время с предыдущего запуска текущего уровня
                $item = date_diff($time, $now);
                if($item->f and $level){// если есть разница
                    $item = $item->format("%R%I:%S.%F");
                    $item = mb_substr($item, 0, 10);
                }else $item = str_pad("", 10, " ", STR_PAD_RIGHT);
                array_push($items, $item);
                // объём использованной оперативной памяти
                $item = memory_get_usage() / 1024 / 1024;
                $item = number_format($item, 3, ".", " ") . " MB";
                $item = str_pad($item, 10, " ", STR_PAD_LEFT);
                array_push($items, $item);
                // уровня вложенности информации
                $item = $level;// уровня вложенности
                array_push($items, $item);
                // идентификатор
                $item = "";// начальное смещение
                for($i = 0, $iLen = $level; $i < $iLen; $i++) $item .= "  ";
                $item = str_pad($item . $name, 31, " ", STR_PAD_RIGHT);
                array_push($items, $item);
                // добавляем оставшиеся значения
                for($i = 2, $iLen = func_num_args(); $i < $iLen; $i++){
                    $item = func_get_arg($i);// получаем очередное значение
                    $item = !is_null($item) ? $item : "";
                    if($item) $item = str_pad($item, 6, " ", STR_PAD_RIGHT);
                    array_push($items, $item);
                };
                // записываем в файл отладочную информацию
                $times[$level] = $now;// сохраняем время
                $line = implode("\t", $items) . $app["val"]["lineDelim"];
                $path = template($app["val"]["debugUrl"], array("group" => $game));
                if(@file_put_contents($path, $line, FILE_APPEND)){// если успешно
                }else $error = 1;
            };
            // возвращаем результат
            return !$error;
        },
        "apiRequest" => function($method, $uri, $data = null, &$code = 0){// http запрос к api
        //@param $method {string} - методов http запроса в нижнем регистре
        //@param $uri {string} - конечная часть url адреса запроса
        //@param $data {array} - строка массив данных для запроса
        //@param $code {integer} - код ответа сервера
        //@return {array|null} - полученные данные или null при ошибки
            global $app;
            static $limits = array();
            $response = null; $error = 0;

            $game = $app["val"]["game"];
            $app["fun"]["setDebug"](7, "🌐 [apiRequest]", $method, $uri);// отладочная информация
            // загружаем базу данных
            if(!$error){// если нет ошибок
                $session = $app["fun"]["getStorage"]($game, "session", true);
                if(!empty($session)){// если удалось получить доступ к базе данных
                }else $error = 1;
            };
            // проверяем переданные данные
            if(!$error){// если нет ошибок
                if(!empty($method) and !empty($uri)){// если не пустые значения
                }else $error = 2;
            };
            // делаем запрос через api
            if(!$error){// если нет ошибок
                $method = mb_strtolower($method);
                $now = microtime(true);// текущее время
                $reset = $now;// время сброса ограничений
                $delim = "/";// разделитель сегментов
                // удаляем информацию об истёкшие ограничениях
                foreach($limits as $key => $limit){// пробигаемся по ограничениям
                    if($now >= $limit["reset"]) unset($limits[$key]);
                };
                // проверяем информацию об ограничениях любых видов запросов
                $key = $app["fun"]["getCustomKey"]($uri, $delim, false, 0, 1, 2, 3);
                if($key){// если удалось получить идентификатор для лимита
                    $limit = get_val($limits, $key, false);// получаем лимит
                    $flag = ($limit and $limit["remaining"] < 1);
                    if($flag) $reset = max($reset, $limit["reset"]);
                };
                // проверяем информацию об ограничениях запросов на удаление
                $key = $app["fun"]["getCustomKey"]($uri, $delim, true, 0, 1, 2);
                if($key){// если удалось получить идентификатор для лимита
                    $limit = get_val($limits, $key, false);// получаем лимит
                    $flag = ($limit and $limit["remaining"] < 1);
                    $flag = ($flag and in_array($method, array("delete")));
                    if($flag) $reset = max($reset, $limit["reset"]);
                };
                // проверяем информацию об ограничениях запросов реакций
                $key = $app["fun"]["getCustomKey"]($uri, $delim, true, 0, 1, 2, 3, 5);
                if($key){// если удалось получить идентификатор для лимита
                    $limit = get_val($limits, $key, false);// получаем лимит
                    $flag = ($limit and $limit["remaining"] < 1);
                    $flag = ($flag and in_array($method, array("put", "delete")));
                    if($flag) $reset = max($reset, $limit["reset"]);
                };
                // дожидаемся сброса ограничений
                $wait = $reset - $now;// время ожидания
                $flag = $wait > 0;// нужно ли подождать перед запросом
                if($flag) usleep($wait * 1000000);// деламем паузу
                if($flag) $app["fun"]["setDebug"](7, "🕓 {sleep}", $wait);// отладочная информация
                // готовим данные и выполняем запрос
                $headers = array();// стандартные заголовки для запроса
                $headers["authorization"] = "Bot " . $session->get("token", "value");
                $headers["x-ratelimit-precision"] = "millisecond";
                $headers["user-agent"] = "DiscordBot";// для обхода блокировки cloudflare
                $flag = (!empty($data) and "get" != $method);
                if($flag) $headers["content-type"] = "application/json;charset=utf-8";
                if($flag) $data = json_encode($data, JSON_UNESCAPED_UNICODE);
                $data = http($method, $app["val"]["discordUrl"] . "api" . $uri, $data, null, $headers, false);
                $now = microtime(true);// текущее время
                $flag = false;// требуется ли учесть эти ограничения
                // добавляем информацию об ограничениях запросов реакций
                $key = $app["fun"]["getCustomKey"]($uri, $delim, true, 0, 1, 2, 3, 5);
                if($key){// если удалось получить идентификатор для лимита
                    $limit = get_val($limits, $key, array());// получаем лимит
                    $value = get_val($limit, "reset", 0);// получаем значение
                    $flag = $value > $now;// требуется ли учесть эти ограничения
                    $limit["reset"] = $flag ? $value : $now + 0.100;// время сброса ограничений
                    $value = get_val($limit, "remaining", -1);// получаем значение
                    $limit["remaining"] = $flag ? $value - 1 : 0;// остаток доступных запросов
                    $flag = in_array($method, array("put", "delete"));// с учётом метода
                    if($flag) $limits[$key] = $limit;// сохраняем значение лимита
                };
                // добавляем информацию об ограничениях любых видов запросов кроме уже проверенных
                $key = $app["fun"]["getCustomKey"]($uri, $delim, false, 0, 1, 2, 3);
                if($key and !$flag){// если удалось получить идентификатор для лимита
                    $limit = get_val($limits, $key, array());// получаем лимит
                    $value = (float)get_val($data["headers"], "x-ratelimit-reset-after", 0);
                    $flag = $value > 0;// требуется ли учесть эти ограничения
                    if($flag) $limit["reset"] = $now + $value;// время сброса ограничений
                    $value = (int)get_val($data["headers"], "x-ratelimit-remaining", -1);
                    $flag = $value > -1;// требуется ли учесть эти ограничения
                    if($flag) $limit["remaining"] = $value;// остаток доступных запросов
                    if($flag) $limits[$key] = $limit;// сохраняем значение лимита
                };
                // добавляем информацию об ограничениях запросов на удаление
                $key = $app["fun"]["getCustomKey"]($uri, $delim, true, 0, 1, 2);
                if($key){// если удалось получить идентификатор для лимита
                    $limit = get_val($limits, $key, array());// получаем лимит
                    $value = get_val($limit, "reset", 0);// получаем значение
                    $flag = $value > $now;// требуется ли учесть эти ограничения
                    $limit["reset"] = $flag ? $value : $now + 5;// время сброса ограничений
                    $value = get_val($limit, "remaining", -1);// получаем значение
                    $limit["remaining"] = $flag ? $value - 1 : 4;// остаток доступных запросов
                    $flag = in_array($method, array("delete"));// с учётом метода
                    if($flag) $limits[$key] = $limit;// сохраняем значение лимита
                };
                // обрабатываем полученный ответ
                $app["fun"]["setDebug"](7, "📄 {response}",
                    ":" . $data["status"] . ":",
                    get_val($data["headers"], "x-ratelimit-bucket", null),
                    get_val($data["headers"], "x-ratelimit-reset-after", null),
                    get_val($data["headers"], "x-ratelimit-remaining", null)
                );// отладочная информация
                $code = $data["status"];// устанавливаем код ответа сервера
                $data = json_decode($data["body"], true);// преобразовываем данные
                if(!empty($data) or is_array($data)) $response = $data;
            };
            // возвращаем результат
            return $response;
        },
        "getStorage" => function($group, $name, $lock = false){// получает базу данных
        //@param $group {string} - группа запрашиваемой базы данных
        //@param $name {string} - имя запрашиваемой базы данных
        //@param $lock {boolean} - установить блокировку при первом подключении
        //@return {null|FileStorage} - ссылка на базу данных
            global $app;
            
            if(!empty($name)){// если передано название
                if(!isset($app["base"][$name])){// если база данных еще не загружалась
                    $data = array("name" => $name);// данные для шаблонизации
                    if($group) $data["group"] = $group;// добавляем группу
                    $path = template($app["val"]["baseUrl"], $data);
                    // поправка на проверку монопольного доступа к файлу базы
                    $flag = false;// пройдена ли проверка на монопольный доступ
                    for($i = 0, $iLen = 120; $i < $iLen and $lock and !$flag; $i++){
                        if($i) usleep(0.5 * 1000000);// ждём некоторое время
                        if(file_exists($path)){// если файл существует
                            $source = @fopen($path, "r");// открываем на чтение
                            if($source){// если удалось открыть
                                $flag = @flock($source, LOCK_EX | LOCK_NB);
                                if($flag) @flock($source, LOCK_UN);
                                @fclose($source);
                            };
                        };
                    };
                    // работаем с объектом базы данных
                    $storage = new FileStorage($path);// создаём объект
                    if(!$lock or $flag and $storage->lock(true)){// если пройдена проверка
                        if($storage->load($lock)){// если удалось открыть базу данных
                            $app["base"][$name] = &$storage;
                        }else $storage = null;
                    }else $storage = null;
                }else $storage = &$app["base"][$name];
            }else $storage = null;
            // возвращаем результат
            return $storage;
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
                // фильтруем каждый элемент списка
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
            // возвращаем результат
            return $list ? $values : $value;
        },
        "strim" => function($input, $before, $after, $include = false, $reverse = false){// возвращает часть строки заключённую между двумя строками
        //@param $input {string} - строка в которой осуществляеться поиск 
        //@param $before {string} -  предшествующий участок строки (может быть пустым)
        //@param $after {string} - завершающий участок строки (может быть пустым) 
        //@param $include {boolean} - включить ограничивающие части в результат
        //@param $reverse {boolean} - выполнить поиск с конца строки
        //@return {string} - часть строки либо пустая строка
            $value = "";

            $input = $input ? (string) $input : $value;
            $before = $before ? (string) $before : $value;
            $after = $after ? (string) $after : $value;
            if (!$reverse) {// прямой поиск вхождения
                $i = $before ? strpos($input, $before) : 0;
                $j = $after && false !== $i ? strpos($input, $after, $i + strlen($before)) : strlen($input);
            } else {// обратный поиск вхождения
                $j = $after ? strrpos($input, $after) : strlen($input);
                $i = $before && false !== $j ? strrpos($input, $before, $j - strlen($input) - 1) : 0;
            };
            if (false !== $i && false !== $j) {// если найдено начало и конец
                $i = $include ? $i : $i + strlen($before);
                $j = $include ? $j + strlen($after) : $j;
                $value = substr($input, $i, $j - $i);
            };
            // возвращаем результат
            return $value;
        },
        "url2obj" => function($url){// превращает url в ассоциативный массив
        //@param $url {string} - полный или не полный url
        //@return {array} - ассоциативный массив с аттрибутами
            global $app;
            $string = $url; $obj = array(); $flag = false;

            // работаем с идентификацией фрагмента
            $key = "fragment"; $value = "#";// задаём ключь и значение тригера
            if(false !== strpos($string, $value)){// если сработал тригер
                $obj[$key] = $app["fun"]["strim"]($string, $value, null, false, false);
                $string = $app["fun"]["strim"]($string, null, $value, false, false);
            };
            // работаем с идентификацией запроса
            $key = "query"; $value = "?";// задаём ключь и значение тригера
            if(false !== strpos($string, $value)){// если сработал тригер
                $obj[$key] = $app["fun"]["strim"]($string, $value, null, false, false);
                $string = $app["fun"]["strim"]($string, null, $value, false, false);
            };
            // работаем с идентификацией пустой схемы
            $key = "scheme"; $value = "//";// задаём ключь и значение тригера
            if(0 === strpos($string, $value)){// если сработал тригер
                $obj[$key] = $app["fun"]["strim"]($string, null, $value, false, false);
                $string = $app["fun"]["strim"]($string, $value, null, false, false);
                $flag = true;// переданный адрес содержит хост
            };
            // работаем с идентификацией пути № 1
            $key = "path"; $value = "/";// задаём ключь и значение тригера
            if(strpos($string, "://") > strpos($string, $value)){// если сработал тригер
                $obj[$key] = $app["fun"]["strim"]($string, $value, null, true, false);
                $string = $app["fun"]["strim"]($string, null, $value, false, false);
            };
            // работаем с идентификацией пути № 2
            $key = "path"; $value = "://";// задаём ключь и значение тригера
            if(0 === strpos($string, $value)){// если сработал тригер
                $obj[$key] = $app["fun"]["strim"]($string, $value, null, true, false);
                $string = $app["fun"]["strim"]($string, null, $value, false, false);
            };
            // работаем с идентификацией не пустой схемы
            $key = "scheme"; $value = "://";// задаём ключь и значение тригера
            if(!$flag and 0 < strpos($string, $value)){// если сработал тригер
                $obj[$key] = $app["fun"]["strim"]($string, null, $value, false, false);
                $string = $app["fun"]["strim"]($string, $value, null, false, false);
                $flag = true;// переданный адрес содержит хост
            };
            // работаем с идентификацией пути № 3
            $key = "path"; $value = "/";// задаём ключь и значение тригера
            if($flag and false !== strpos($string, $value)){// если сработал тригер
                $obj[$key] = $app["fun"]["strim"]($string, $value, null, true, false);
                $string = $app["fun"]["strim"]($string, null, $value, false, false);
            };
            // работаем с идентификацией пути № 4
            $key = "path"; $value = strlen($string);// задаём ключь и значение тригера
            if(!$flag and 0 < $value){// если сработал тригер
                $obj[$key] = $string;
                $string = "";
            };
            // работаем с идентификацией авторизационных данных
            $key = null; $value = "@";// задаём ключь и значение тригера
            if(false !== strpos($string, $value)){// если сработал тригер
                $storage = $app["fun"]["strim"]($string, $value, null, false, false);
                $string = $app["fun"]["strim"]($string, null, $value, false, false);
                // работаем с идентификацией пароля
                $key = "password"; $value = ":";// задаём ключь и значение тригера
                if(false !== strpos($string, $value)){// если сработал тригер
                    $obj[$key] = $app["fun"]["strim"]($string, $value, null, false, false);
                    $string = $app["fun"]["strim"]($string, null, $value, false, false);
                };
                // работаем с идентификацией пользователя
                $key = "user"; $value = true;// задаём ключь и значение тригера
                if($value){// если сработал тригер
                    $obj[$key] = $string;
                    $string = $storage;
                };
            };
            // работаем с идентификацией порта
            $key = "port"; $value = ":";// задаём ключь и значение тригера
            if(false !== strpos($string, $value)){// если сработал тригер
                $obj[$key] = $app["fun"]["strim"]($string, $value, null, false, false);
                $string = $app["fun"]["strim"]($string, null, $value, false, false);
            };
            // работаем с идентификацией домина
            $key = "domain"; $value = true;// задаём ключь и значение тригера
            if($flag and $value){// если сработал тригер
                $obj[$key] = $string;
                $string = "";
            };
            // возвращаем объект
            return $obj;
        },
        "obj2url" => function($obj){// превращает асспциативный массив в url
        //@param $obj {array} - асспциативный массив со специальными аттрибутами
        //@return {string} - строка адреса ресурса
            global $app;
            $url = "";
            
            // работаем с пустой и не пустой схемой
            $value = "";// задаём постое начальное значение
            $key = "user"; if(isset($obj[$key])) $value = "//";
            $key = "password"; if(isset($obj[$key])) $value = "//";
            $key = "domain"; if(isset($obj[$key])) $value = "//";
            $key = "port"; if(isset($obj[$key])) $value = "//";
            $key = "scheme"; if(isset($obj[$key]) and !empty($obj[$key])) $value = "://";
            $url .= ((isset($obj[$key]) and !empty($obj[$key])) ? $obj[$key] : "") . $value;
            // работаем с пользователем
            $key = "user"; $value = "";
            if(isset($obj[$key])) $url .= $value . $obj[$key];
            // работаем с паролем
            $key = "password"; $value = ":";
            if(isset($obj[$key])) $url .= $value . $obj[$key];
            // работаем с доменом
            $value = "";// задаём постое начальное значение
            $key = "user"; if(isset($obj[$key])) $value = "@";
            $key = "password"; if(isset($obj[$key])) $value = "@";
            $key = "domain";
            $url .= $value . ((isset($obj[$key]) and !empty($obj[$key])) ? $obj[$key] : "");
            // работаем с портом
            $key = "port"; $value = ":";
            if(isset($obj[$key])) $url .= $value . $obj[$key];
            // работаем с путём
            $key = "path"; $value = "";
            if(isset($obj[$key])) $url .= $value . $obj[$key];
            // работаем с запросом
            $key = "query"; $value = "?";
            if(isset($obj[$key])) $url .= $value . $obj[$key];
            // работаем с фрагментом
            $key = "fragment"; $value = "#";
            if(isset($obj[$key])) $url .= $value . $obj[$key];
            // возвращаем адрес
            return $url;
        },
        "href" => function($link){// возвращает полный путь строковой ссылки
        //@param link {string} - любой относительный путь
        //@return {string} - полный путь или пустая строка
            global $app;
            $split = "/"; $parent = "..";
            
            $obj = $app["fun"]["url2obj"]($link);
            // получаем объект адреса текущего запроса
            $flag = false;// используется ли https протокол
            if($app["fun"]["getClearParam"]($_SERVER, "HTTPS", "boolean")) $flag = true;
            if("https" == $app["fun"]["getClearParam"]($_SERVER, "HTTP_X_FORWARDED_PROTOCOL", "string")) $flag = true;
            $request = ($flag ? "https" : $app["fun"]["getClearParam"]($_SERVER, "REQUEST_SCHEME", "string")) . "://";
            $value = $app["fun"]["getClearParam"]($_SERVER, "HTTP_HOST", "string"); if(!empty($value)) $request .= $value;
            $value = $app["fun"]["getClearParam"]($_SERVER, "REQUEST_URI", "string"); $request .= !empty($value) ? $value : $split;
            $request = $app["fun"]["url2obj"]($request);
            // вычисляем необходимось добавления абсолютных данных
            $flag = true; foreach(array("scheme", "user", "password", "domain", "port", "path") as $key) if(isset($obj[$key])) $flag = false;
            $key = "query"; if($flag and !isset($obj[$key]) and isset($request[$key])) $obj[$key] = $request[$key]; 
            // вычисляем необходимось добавления абсолютных данных
            $flag = true; foreach(array("scheme", "user", "password", "domain", "port") as $key) if(isset($obj[$key])) $flag = false;
            $key = "scheme"; if($flag and !isset($obj[$key]) and isset($request[$key])) $obj[$key] = $request[$key]; 
            $key = "domain"; if($flag and !isset($obj[$key]) and isset($request[$key])) $obj[$key] = $request[$key]; 
            $key = "path"; if($flag and !isset($obj[$key]) and isset($request[$key])) $obj[$key] = $request[$key]; 
            // корректируем пустую схему в запросе
            $key = "scheme"; if(isset($obj[$key], $request[$key]) and empty($obj[$key])) $obj[$key] = $request[$key]; 
            // преобразовываем относительный значения в пути в абсолютные
            if(isset($obj["path"])){// если задан любой путь для ссылки
                // формируем начальный список папок
                $items = explode($split, $obj["path"]);
                if(0 !== strpos($obj["path"], $split)){// если это относительный путь
                    $forders = explode($split, $request["path"]);
                    array_shift($forders);// удаляем первый пустой элимент
                    array_pop($forders);// удаляем последний элимент с именем файла
                }else{// если это абсолютный путь
                    array_shift($items);// удаляем первый пустой элимент
                    $forders = array();// пустой массив папок
                };
                // дополняем начальный список папками из пути
                for($i = 0, $iLen = count($items); $i < $iLen; $i++){// пробигаемся по папкам пути
                    $item = $items[$i];// получаем очередной элимент по номеру в списке
                    if($parent == $item) array_pop($forders);
                    else array_push($forders, $item);
                };
                // формируем путь
                $obj["path"] = $split . implode($split, $forders);
            };
            // возвращаем  полученный полный адрес
            return $app["fun"]["obj2url"]($obj);
        }
    )
);

// инициализируем приложение
$app["init"]();
?>
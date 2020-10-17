<?php # 0.2.8 api для бота в discord

include_once "../../libs/File-0.1.inc.php";                                 // 0.1.5 класс для многопоточной работы с файлом
include_once "../../libs/FileStorage-0.5.inc.php";                          // 0.5.9 подкласс для работы с файловым реляционным хранилищем
include_once "../../libs/phpEasy-0.3.inc.php";                              // 0.3.9 основная библиотека упрощённого взаимодействия
include_once "../../libs/vendor/webSocketClient-1.0.inc.php";               // 0.1.0 набор функций для работы с websocket

//////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

$app = array(// основной массив данных
    "val" => array(// переменные и константы
        "baseUrl" => "../base|/%name%.db",                                  // шаблон url для базы данных
        "cacheUrl" => "../cache|/%group%|/%name%.json",                     // шаблон url для кеша данных
        "debugUrl" => null,                                                 // шаблон url для включения режима отладки
        "statusUnknown" => "Server unknown status",                         // сообщение для неизвестного статуса
        "statusLang" => "en",                                               // язык для кодов расшифровок
        "format" => "json",                                                 // формат вывода поумолчанию
        "useFileCache" => null,                                             // использовать файловый кеш данных (изменяется в коде)
        "timeZone" => "Europe/Moscow",                                      // временная зона по умолчанию для работы со временем
        "eventTimeAdd" => 8*24*60*60,                                       // максимальное время для записи в событие
        "eventTimeDelete" => 4*60*60,                                       // максимальное время хранения записи события
        "eventTimeClose" => -15*60,                                         // время за которое закрывается событие для изменения
        "eventCommentLength" => 500,                                        // максимальная длина комментария пользователя
        "eventNoteLength" => 50,                                            // максимальная длина заметки пользователя
        "eventOperationCycle" => 5,                                         // с какой частотой производить регламентные операции с базой в цикле
        "discordLang" => "ru",                                              // язык по умолчанию для отображения информации в канале Discord
        "discordApiUrl" => "https://discord.com/api",                       // базовый url для взаимодействия с Discord API
        "discordWebSocketHost" => "gateway.discord.gg",                     // адрес хоста для взаимодействия с Discord через WebSocket
        "discordWebSocketLoop" => 1000,                                     // лимит итераций цикла общения WebSocket с Discord
        "discordMessageLength" => 2000,                                     // максимальная длина сообщения в Discord
        "discordMessageTime" => 6*60,                                       // максимально допустимое время между сгруппированными сообщениями
        "discordCreatePermission" => 32768,                                 // разрешения для создание первой записи в событие (прикреплять файлы)
        "discordUserPermission" => 16384,                                   // разрешения для записи других пользователей (встраивать ссылки)
        "discordMainPermission" => 339008,                                  // минимальные разрешения бота для работы
        "discordListPermission" => 347200,                                  // разрешения бота для ведения сводного расписания
        "discordTalkPermission" => 32768,                                   // разрешения бота для ведения обсуждения в канале (прикреплять файлы)
        "discordBotGame" => "discord.gg/J8smX6R",                           // анонс возле аватарки бота
        "discordAllUser" => "@everyone",                                    // идентификатор всех пользователей
        "appTimeLimit" => 9*60 + 45,                                        // лимит времи исполнения приложения
        "appToken" => "MY-APP-TOKEN",                                       // защитный ключ приложения
        "discordBotId" => "MY-DISCORD-BOT-ID",                              // идентификатор приложения в Discord
        "discordBotToken" => "MY-DISCORD-BOT-TOKEN"
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
        //@param $status {number} - целое число статуса выполнения
        //@return {true|false} - были ли изменения базы событий
            global $app; $result = null;
            
            $isSessionUpdate = false;// были ли обновлены данные в базе данных
            $isEventsUpdate = false;// были ли обновлены данные в базе данных
            $start = microtime(true);// время начала работы приложения
            $app["fun"]["setDebug"](1, "run");// отладочная информация
            // получаем очищенные значения параметров
            $token = $app["fun"]["getClearParam"]($params, "token", "string");
            // проверяем корректность указанных параметров
            if(empty($status)){// если нет ошибок
                if(!is_null($token) or get_val($options, "nocontrol", false)){// если указаны обязательные поля
                    if(!empty($token) or get_val($options, "nocontrol", false)){// если обязательные поля успешно отфильтрованы
                        if($token == $app["val"]["appToken"] or get_val($options, "nocontrol", false)){// если прошли проверку
                        }else $status = 303;// переданные параметры не верны
                    }else $status = 302;// один из обязательных параметров передан в неверном формате
                }else $status = 301;// не передан один из обязательных параметров
            };
            // загружаем все необходимые базы данных
            if(empty($status)){// если нет ошибок
                $session = $app["fun"]["getStorage"]("session", true);
                if(!empty($session)){// если удалось получить доступ к базе данных
                }else $status = 304;// не удалось загрузить одну из многих баз данных
            };
            if(empty($status)){// если нет ошибок
                $events = $app["fun"]["getStorage"]("events", true);
                if(!empty($events)){// если удалось получить доступ к базе данных
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
                                isset($data["d"]["guild_id"]) ? $data["d"]["guild_id"] : null,
                                isset($data["d"]["channel_id"]) ? $data["d"]["channel_id"] : null,
                                isset($data["d"]["message_id"]) ? $data["d"]["message_id"] : null,
                                isset($data["d"]["user_id"]) ? $data["d"]["user_id"] : null
                            );// отладочная информация
                            // обрабатываем тип уведомления
                            switch(get_val($data, "t", null)){// поддерживаемые типы
                                case "READY":// ready
                                    // обрабатываем начало подключения
                                    if(isset($data["d"]["session_id"])){// если есть обязательное значение
                                        if($session->set("sid", "value", $data["d"]["session_id"])){// если данные успешно добавлены
                                            $isSessionUpdate = true;// были обновлены данные в базе данных
                                            if($app["val"]["useFileCache"]){// если используются
                                                $app["fun"]["delFileCache"]();// сбрасываем всё
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
                                                    "name" => $app["val"]["discordBotGame"],
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
                                    // обрабатываем начало набора сообщения
                                    if(isset($data["d"]["member"]["user"]["id"], $data["d"]["channel_id"], $data["d"]["guild_id"])){// если есть обязательное значение
                                        $flag = false;// есть ли необходимые права для выполнения действия
                                        if(!$flag) $permission = $app["fun"]["getPermission"]("member", $app["val"]["discordBotId"], $data["d"]["channel_id"], $data["d"]["guild_id"]);
                                        $flag = ($flag or ($permission & $app["val"]["discordMainPermission"]) == $app["val"]["discordMainPermission"]);
                                        if($flag){// если бот контролирует канал в котором пользователь набирает сообщение
                                            // обновляем участника и пользователя
                                            $member = $app["fun"]["setCache"]("member", $data["d"]["member"], $data["d"]["guild_id"]);
                                            if($member){// если удалось закешировать данные
                                                $user = $app["fun"]["setCache"]("user", $data["d"]["member"]["user"]);
                                                if($user){// если удалось закешировать данные
                                                    $app["fun"]["getCache"]("user", $data["d"]["member"]["user"]["id"]);
                                                }else $app["fun"]["delCache"]("user", $data["d"]["member"]["user"]["id"]);
                                            }else $app["fun"]["delCache"]("member", $data["d"]["member"]["user"]["id"], $data["d"]["guild_id"]);
                                        };
                                        $flag = ($flag or ($permission & $app["val"]["discordListPermission"]) == $app["val"]["discordListPermission"]);
                                        if(!$flag){// если бот не контролирует канал в котором пользователь набирает сообщение
                                            // обновляем ближайщий не обновлённый контролируемый канал
                                            $guild = $app["fun"]["getCache"]("guild", $data["d"]["guild_id"]);
                                            if(isset($guild["channels"])){// если удалось получить данные
                                                $member = $app["fun"]["getCache"]("member", $app["val"]["discordBotId"], $guild["id"]);
                                                for($i = count($guild["channels"]) - 1; $i > -1 and !$flag; $i--){// пробигаемся по каналам
                                                    $channel = $guild["channels"][$i];// получаем очередной элемент
                                                    if(!isset($channel["messages"])){// если список сообщений ещё не запрашивался
                                                        if(!$flag) $permission = $app["fun"]["getPermission"]("member", $member, $channel, $guild);
                                                        $flag = ($flag or ($permission & $app["val"]["discordMainPermission"]) == $app["val"]["discordMainPermission"]);
                                                        $flag = ($flag or ($permission & $app["val"]["discordListPermission"]) == $app["val"]["discordListPermission"]);
                                                        if($flag) $app["fun"]["getCache"]("channel", $channel["id"], $guild["id"], null);
                                                    };
                                                };
                                            };
                                        };
                                    };
                                    break;
                                case "GUILD_CREATE":// guild create
                                case "GUILD_UPDATE":// guild update
                                    // обрабатываем изменение гильдии
                                    if(isset($data["d"]["id"])){// если есть обязательное значение
                                        $guild = $app["fun"]["setCache"]("guild", $data["d"]);
                                        if($guild){// если удалось закешировать данные
                                            // обрабатываем гильдию
                                            $flag = $app["method"]["discord.guild"](
                                                array(// параметры для метода
                                                    "guild" => $data["d"]["id"]
                                                ),
                                                array(// внутренние опции
                                                    "nocontrol" => true
                                                ),
                                                $sign, $status
                                            );
                                            $isEventsUpdate = $isEventsUpdate || $flag;
                                        }else $app["fun"]["delCache"]("guild", $data["d"]["id"]);
                                    };
                                    break;
                                case "GUILD_DELETE":// guild delete
                                    // обрабатываем удаление гильдии
                                    if(isset($data["d"]["id"])){// если есть обязательное значение
                                        $app["fun"]["delCache"]("guild", $data["d"]["id"]);
                                    };
                                    break;
                                case "GUILD_MEMBER_ADD":// guild member add
                                case "GUILD_MEMBER_UPDATE":// guild member update
                                    // обрабатываем изменение участника
                                    if(isset($data["d"]["user"]["id"], $data["d"]["guild_id"])){// если есть обязательное значение
                                        $member = $app["fun"]["setCache"]("member", $data["d"], $data["d"]["guild_id"]);
                                        if($member){// если удалось закешировать данные
                                            $user = $app["fun"]["setCache"]("user", $data["d"]["user"]);
                                            if($user){// если удалось закешировать данные
                                            }else $app["fun"]["delCache"]("user", $data["d"]["user"]["id"]);
                                        }else $app["fun"]["delCache"]("member", $data["d"]["user"]["id"], $data["d"]["guild_id"]);
                                    };
                                    break;
                                case "GUILD_MEMBER_REMOVE":// guild member remove
                                    // обрабатываем удаление участника
                                    if(isset($data["d"]["user"]["id"], $data["d"]["guild_id"])){// если есть обязательное значение
                                        $app["fun"]["delCache"]("member", $data["d"]["user"]["id"], $data["d"]["guild_id"]);
                                    };
                                    break;
                                case "CHANNEL_CREATE":// channel create
                                case "CHANNEL_UPDATE":// channel update
                                    // обрабатываем изменение канала
                                    if(isset($data["d"]["id"], $data["d"]["guild_id"])){// если есть обязательное значение
                                        $channel = $app["fun"]["setCache"]("channel", $data["d"], $data["d"]["guild_id"], null);
                                        if($channel){// если удалось закешировать данные
                                            // обрабатываем канал
                                            $flag = $app["method"]["discord.channel"](
                                                array(// параметры для метода
                                                    "channel" => $data["d"]["id"],
                                                    "guild" => $data["d"]["guild_id"]
                                                ),
                                                array(// внутренние опции
                                                    "nocontrol" => true
                                                ),
                                                $sign, $status
                                            );
                                            $isEventsUpdate = $isEventsUpdate || $flag;
                                            // обновляем сводное расписание в гильдии
                                            $flag = $app["method"]["discord.guild"](
                                                array(// параметры для метода
                                                    "guild" => $data["d"]["guild_id"]
                                                ),
                                                array(// внутренние опции
                                                    "nocontrol" => true,
                                                    "consolidated" => true
                                                ),
                                                $sign, $status
                                            );
                                            $isEventsUpdate = $isEventsUpdate || $flag;
                                        }else $app["fun"]["delCache"]("channel", $data["d"]["id"], $data["d"]["guild_id"]);
                                    };
                                    break;
                                case "CHANNEL_DELETE":// channel delete
                                    // обрабатываем удаление канала
                                    if(isset($data["d"]["id"], $data["d"]["guild_id"])){// если есть обязательное значение
                                        $app["fun"]["delCache"]("channel", $data["d"]["id"], $data["d"]["guild_id"]);
                                    };
                                    break;
                                case "MESSAGE_CREATE":// message create
                                case "MESSAGE_UPDATE":// message update
                                    // обрабатываем изменение сообщения
                                    if(isset($data["d"]["id"], $data["d"]["channel_id"], $data["d"]["guild_id"])){// если есть обязательное значение
                                        $message = $app["fun"]["setCache"]("message", $data["d"], $data["d"]["channel_id"], $data["d"]["guild_id"]);
                                        if($message){// если удалось закешировать данные
                                            // проверяем права доступа
                                            $flag = false;// есть ли необходимые права для выполнения действия
                                            if(!$flag) $permission = $app["fun"]["getPermission"]("member", $app["val"]["discordBotId"], $data["d"]["channel_id"], $data["d"]["guild_id"]);
                                            $flag = ($flag or ($permission & $app["val"]["discordMainPermission"]) == $app["val"]["discordMainPermission"]);
                                            if($flag){// если бот контролирует этот канал
                                                $data["d"]["member"]["user"] = $data["d"]["author"];// приводим к единому виду
                                                // обновляем участника и пользователя
                                                $member = $app["fun"]["setCache"]("member", $data["d"]["member"], $data["d"]["guild_id"]);
                                                if($member){// если удалось закешировать данные
                                                    $user = $app["fun"]["setCache"]("user", $data["d"]["member"]["user"]);
                                                    if($user){// если удалось закешировать данные
                                                    }else $app["fun"]["delCache"]("user", $data["d"]["member"]["user"]["id"]);
                                                }else $app["fun"]["delCache"]("member", $data["d"]["member"]["user"]["id"], $data["d"]["guild_id"]);
                                                // обрабатываем сообщение
                                                $flag = $app["method"]["discord.message"](
                                                    array(// параметры для метода
                                                        "message" => $data["d"]["id"],
                                                        "channel" => $data["d"]["channel_id"],
                                                        "guild" => $data["d"]["guild_id"]
                                                    ),
                                                    array(// внутренние опции
                                                        "nocontrol" => true
                                                    ),
                                                    $sign, $status
                                                );
                                                $isEventsUpdate = $isEventsUpdate || $flag;
                                            };
                                        }else $app["fun"]["delCache"]("message", $data["d"]["id"], $data["d"]["channel_id"], $data["d"]["guild_id"]);
                                    };
                                    break;
                                case "MESSAGE_DELETE":// message delete
                                    // поправка на удаление одного сообщения
                                    if(isset($data["d"]["id"])) $data["d"]["ids"] = array($data["d"]["id"]);
                                case "MESSAGE_DELETE_BULK":// message delete bulk
                                    // обрабатываем удаление сообщений
                                    if(isset($data["d"]["ids"], $data["d"]["channel_id"], $data["d"]["guild_id"])){// если есть обязательное значение
                                        for($i = count($data["d"]["ids"]) - 1; $i > -1; $i--){// пробигаемся по идентификаторам сообщений
                                            $app["fun"]["delCache"]("message", $data["d"]["ids"][$i], $data["d"]["channel_id"], $data["d"]["guild_id"]);
                                        };
                                        // проверяем права доступа
                                        $flag = false;// есть ли необходимые права для выполнения действия
                                        if(!$flag) $permission = $app["fun"]["getPermission"]("member", $app["val"]["discordBotId"], $data["d"]["channel_id"], $data["d"]["guild_id"]);
                                        $flag = ($flag or ($permission & $app["val"]["discordMainPermission"]) == $app["val"]["discordMainPermission"]);
                                        $flag = ($flag or ($permission & $app["val"]["discordListPermission"]) == $app["val"]["discordListPermission"]);
                                        if($flag){// если бот контролирует этот канал
                                            // обрабатываем канал
                                            $flag = $app["method"]["discord.channel"](
                                                array(// параметры для метода
                                                    "channel" => $data["d"]["channel_id"],
                                                    "guild" => $data["d"]["guild_id"]
                                                ),
                                                array(// внутренние опции
                                                    "nocontrol" => true
                                                ),
                                                $sign, $status
                                            );
                                            $isEventsUpdate = $isEventsUpdate || $flag;
                                        };
                                    };
                                    break;
                                case "MESSAGE_REACTION_ADD":// message reaction add
                                    // обрабатываем добавление реакции
                                    if(isset($data["d"]["emoji"]["name"], $data["d"]["member"]["user"]["id"], $data["d"]["user_id"], $data["d"]["message_id"], $data["d"]["channel_id"], $data["d"]["guild_id"])){// если есть обязательное значение
                                        $data["d"]["user"] = $data["d"]["member"]["user"];// приводим к единому виду
                                        $rid = array($data["d"]["user_id"], $data["d"]["emoji"]["name"], $data["d"]["emoji"]["id"]);
                                        $reaction = $app["fun"]["setCache"]("reaction", $data["d"], $data["d"]["message_id"], $data["d"]["channel_id"], $data["d"]["guild_id"]);
                                        if($reaction){// если удалось закешировать данные
                                            // обновляем участника и пользователя
                                            $member = $app["fun"]["setCache"]("member", $data["d"]["member"], $data["d"]["guild_id"]);
                                            if($member){// если удалось закешировать данные
                                                $user = $app["fun"]["setCache"]("user", $data["d"]["member"]["user"]);
                                                if($user){// если удалось закешировать данные
                                                }else $app["fun"]["delCache"]("user", $data["d"]["member"]["user"]["id"]);
                                            }else $app["fun"]["delCache"]("member", $data["d"]["member"]["user"]["id"], $data["d"]["guild_id"]);
                                            // обрабатываем реакцию
                                            $flag = $app["method"]["discord.reaction"](
                                                array(// параметры для метода
                                                    "reaction" => $reaction ? implode(":", $rid) : null,
                                                    "message" => $data["d"]["message_id"],
                                                    "channel" => $data["d"]["channel_id"],
                                                    "guild" => $data["d"]["guild_id"]
                                                ),
                                                array(// внутренние опции
                                                    "nocontrol" => true
                                                ),
                                                $sign, $status
                                            );
                                            $isEventsUpdate = $isEventsUpdate || $flag;
                                        }else $app["fun"]["delCache"]("reaction", $rid, $data["d"]["message_id"], $data["d"]["channel_id"], $data["d"]["guild_id"]);
                                    };
                                    break;
                                case "MESSAGE_REACTION_REMOVE":// message reaction remove
                                case "MESSAGE_REACTION_REMOVE_ALL":// message reaction remove all
                                case "MESSAGE_REACTION_REMOVE_EMOJI":// message reaction remove emoji
                                    // обрабатываем удаление реакций
                                    if(isset($data["d"]["message_id"], $data["d"]["channel_id"], $data["d"]["guild_id"])){// если есть обязательное значение
                                        $message = $app["fun"]["getCache"]("message", $data["d"]["message_id"], $data["d"]["channel_id"], $data["d"]["guild_id"]);
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
                                                if($flag) $app["fun"]["delCache"]("reaction", $rid, $data["d"]["message_id"], $data["d"]["channel_id"], $data["d"]["guild_id"]);
                                            };
                                            // обрабатываем реакции
                                            $flag = $app["method"]["discord.reaction"](
                                                array(// параметры для метода
                                                    "reaction" => null,
                                                    "message" => $data["d"]["message_id"],
                                                    "channel" => $data["d"]["channel_id"],
                                                    "guild" => $data["d"]["guild_id"]
                                                ),
                                                array(// внутренние опции
                                                    "nocontrol" => true
                                                ),
                                                $sign, $status
                                            );
                                            $isEventsUpdate = $isEventsUpdate || $flag;
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
                                    // GUILDS | GUILD_MESSAGES | GUILD_MESSAGE_REACTIONS | GUILD_MESSAGE_TYPING
                                    "intents" => 1 << 0 | 1 << 9 | 1 << 10 | 1 << 11,
                                    "token" => $app["val"]["discordBotToken"],
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
                            if(isset($data["d"]["heartbeat_interval"])){// если есть обязательное значение
                                $heartbeatSendTime = $now;
                                $heartbeatAcceptTime = $now;
                                $heartbeatInterval = $data["d"]["heartbeat_interval"] / 1000;
                                // пробуем авторизоваться по сессии
                                $data = array(// данные для отправки
                                    "op" => 6,// resume
                                    "d" => array(// data
                                        "token" => $app["val"]["discordBotToken"],
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
                            $heartbeatAcceptTime = $now;
                            break;
                    };
                    // сохраняем номер уведомления
                    if(empty($status)){// если нет ошибок
                        if(get_val($data, "s", 0)){// если есть номер
                            if($session->set("seq", "value", $data["s"])){// если данные успешно добавлены
                                $isSessionUpdate = true;// были обновлены данные в базе данных
                            }else $status = 309;// не удалось записать данные в базу данных
                        };
                    };
                    // проверяем и поддерживаем серцебиение
                    if(empty($status)){// если нет ошибок
                        if($heartbeatInterval > 0){// если задан интервал серцебиения
                            if($heartbeatSendTime - $heartbeatAcceptTime > $heartbeatInterval + 5){// если соединение зависло
                                // разрываем соединение
                                $app["fun"]["setDebug"](1, "close");// отладочная информация
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
                                $heartbeatSendTime = $now;
                            };
                        };
                    };
                    // выполняем операции с базами данных
                    if(!(($index - 3) % $app["val"]["eventOperationCycle"])){// если пришло время
                        // выполняем регламентные операции с базой событий
                        if(empty($status)){// если нет ошибок
                            if(!get_val($options, "nocontrol", false)){// если это прямой вызов
                                $items = $app["fun"]["changeEvents"]($now, $status);
                                $flag = count($items) > 0;// были ли изменения
                                $isEventsUpdate = $isEventsUpdate || $flag;
                                $counts = array();// счётчики для каналов
                                // определяем каналы для обновления уведомлений
                                foreach($items as $id => $item){// пробигаемся по значениям
                                    // создаём структуру счётчика
                                    $count = &$counts;// счётчик элементов
                                    foreach(array($item["guild"], $item["channel"]) as $key){
                                        if(!isset($count[$key])) $count[$key] = array();
                                        $count = &$count[$key];// получаем ссылку
                                    };
                                    // выполняем подсчёт элементов
                                    if(!isset($count["item"])) $count["item"] = 0;
                                    $count["item"]++;
                                };
                                // выполняем обновление уведомлений
                                foreach($counts as $gid => $items){// пробигаемся по гильдиям
                                    // получаем данные о гильдии
                                    if(!empty($status)) break;// не продолжаем при ошибке
                                    $guild = $app["fun"]["getCache"]("guild", $gid);
                                    if($guild){// если удалось получить данные
                                        foreach($items as $cid => $count){// пробигаемся по каналам
                                            // получаем данные о канале
                                            if(!empty($status)) break;// не продолжаем при ошибке
                                            $channel = $app["fun"]["getCache"]("channel", $cid, $gid, null);
                                            if($channel){// если удалось получить данные
                                                // обрабатываем канал
                                                $flag = $app["method"]["discord.channel"](
                                                    array(// параметры для метода
                                                        "channel" => $cid,
                                                        "guild" => $gid
                                                    ),
                                                    array(// внутренние опции
                                                        "nocontrol" => true
                                                    ),
                                                    $sign, $status
                                                );
                                                $isEventsUpdate = $isEventsUpdate || $flag;
                                            };
                                        };
                                        // обновляем сводное расписание в гильдии
                                        if(!empty($status)) break;// не продолжаем при ошибке
                                        $flag = $app["method"]["discord.guild"](
                                            array(// параметры для метода
                                                "guild" => $gid
                                            ),
                                            array(// внутренние опции
                                                "nocontrol" => true,
                                                "consolidated" => true
                                            ),
                                            $sign, $status
                                        );
                                        $isEventsUpdate = $isEventsUpdate || $flag;
                                    };
                                };
                            };
                        };
                        // переодически сохраняем базу данных событий
                        if(empty($status)){// если нет ошибок
                            if(!get_val($options, "nocontrol", false)){// если это прямой вызов
                                if($isEventsUpdate){// если нужно сохранить
                                    if($events->save(true)){// если данные успешно сохранены
                                        $isEventsUpdate = false;// были сохранены данные в базе данных
                                    }else $status = 307;// не удалось сохранить базу данных
                                };
                            };
                        };
                        // переодически сохраняем базу данных сесии
                        if(empty($status)){// если нет ошибок
                            if($isSessionUpdate){// если нужно сохранить
                                if($session->save(true)){// если данные успешно сохранены
                                    $isSessionUpdate = false;// были сохранены данные в базе данных
                                }else $status = 307;// не удалось сохранить базу данных
                            };
                        };
                    };
                    // увеличиваем индексы
                    $index++;// индекс итераций цикла
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
                    if(empty($status) and $isEventsUpdate){// если нужно выполнить
                        if($events->save(false)){// если данные успешно сохранены
                        }else $status = 307;// не удалось сохранить базу данных
                    }else $events->unlock();// разблокируем базу
                };
            };
            // сохраняем базу данных сесии
            if(isset($session) and !empty($session)){// если база данных загружена
                if(empty($status) and $isSessionUpdate){// если нужно выполнить
                    if($session->save(false)){// если данные успешно сохранены
                    }else $status = 307;// не удалось сохранить базу данных
                }else $session->unlock();// разблокируем базу
            };
            // работаем с файловыми кешами
            if($app["val"]["useFileCache"]){// если используются
                if(empty($status)){// если нет ошибок
                    // сохраняем данные в файловые кеши
                    foreach($app["cache"] as $group => $items){// пробигаемся по группам
                        for($i = count($items) - 1; $i > -1; $i--){// пробигаемся по элементам
                            $item = $items[$i];// получаем очередной элемент из списка элементов
                            if(!$app["fun"]["setFileCache"]($group, $item["id"], $item)){// если не удалось
                                $app["fun"]["delFileCache"]($group, $item["id"]);
                                $status = 311;// не удалось записать данные в файловый кеш
                            };
                        };
                    };
                }else{// если есть ошибки
                    // удаляем данные из файловых кешей
                    foreach($app["cache"] as $group => $items){// пробигаемся по группам
                        for($i = count($items) - 1; $i > -1; $i--){// пробигаемся по элементам
                            $item = $items[$i];// получаем очередной элемент из списка элементов
                            $app["fun"]["delFileCache"]($group, $item["id"]);
                        };
                    };
                };
            };
            // возвращаем результат
            $app["fun"]["setDebug"](1, "exit", $status);// отладочная информация
            $result = $isEventsUpdate;
            return $result;
        },
        "discord.guild" => function($params, $options, $sign, &$status){// обрабатываем гильдию
        //@param $params {array} - массив внешних не отфильтрованных значений
        //@param $options {array} - массив внутренних настроек
        //@param $sign {boolean|null} - успешность проверки подписи или null при её отсутствии
        //@param $status {number} - целое число статуса выполнения
        //@return {true|false} - были ли изменения базы событий
            global $app; $result = null;

            $now = microtime(true);// текущее время
            $isConsolidated = false;// в данном канале ведётся сводное рассписание
            $isEventsUpdate = false;// были ли обновлены данные в базе данных
            $hasMainPermission = false;// есть основные разрешения
            $hasListPermission = false;// есть разрешения для сводного расписания
            // получаем очищенные значения параметров
            $token = $app["fun"]["getClearParam"]($params, "token", "string");
            $guild = $app["fun"]["getClearParam"]($params, "guild", "string");
            $app["fun"]["setDebug"](3, "discord.guild", $guild);// отладочная информация
            // проверяем корректность указанных параметров
            if(empty($status)){// если нет ошибок
                if((!is_null($token) and !is_null($guild)) or get_val($options, "nocontrol", false)){// если указаны обязательные поля
                    if((!empty($token) and !empty($guild)) or get_val($options, "nocontrol", false)){// если обязательные поля успешно отфильтрованы
                        if($token == $app["val"]["appToken"] or get_val($options, "nocontrol", false)){// если прошли проверку
                        }else $status = 303;// переданные параметры не верны
                    }else $status = 302;// один из обязательных параметров передан в неверном формате
                }else $status = 301;// не передан один из обязательных параметров
            };
            // загружаем все необходимые базы данных
            if(empty($status)){// если нет ошибок
                $events = $app["fun"]["getStorage"]("events", true);
                if(!empty($events)){// если удалось получить доступ к базе данных
                }else $status = 304;// не удалось загрузить одну из многих баз данных
            };
            // получаем информацию о гильдии
            if(empty($status)){// если нет ошибок
                $guild = $app["fun"]["getCache"]("guild", $guild);
                if($guild){// если удалось получить данные
                }else $status = 303;// переданные параметры не верны
            };
            // выполняем регламентные операции с базой событий
            if(empty($status) and !get_val($options, "nocontrol", false)){// если нужно выполнить
                $flag = count($app["fun"]["changeEvents"]($now, $status)) > 0;
                $isEventsUpdate = $isEventsUpdate || $flag;
            };
            // выполняем обработку каналов в гильдии
            if(empty($status)){// если нет ошибок
                for($iLen = count($guild["channels"]), $i = $iLen - 1; $i > -1 and empty($status); $i--){
                    $channel = isset($guild["channels"][$i]) ? $guild["channels"][$i] : false;// получаем очередной элемент
                    // проверяем разрешения
                    if($channel and get_val($options, "consolidated", false)){// если нужно обновить только сводное расписание
                        $permission = $app["fun"]["getPermission"]("member", $app["val"]["discordBotId"], $channel, $guild["id"]);
                        $hasMainPermission = ($permission & $app["val"]["discordMainPermission"]) == $app["val"]["discordMainPermission"];
                        $hasListPermission = ($permission & $app["val"]["discordListPermission"]) == $app["val"]["discordListPermission"];
                        $flag = $isConsolidated = ($hasListPermission and !$hasMainPermission);
                    }else $flag = true;
                    // обрабатываем канал
                    if($channel and $flag){// если очередной элемент существует
                        $flag = $app["method"]["discord.channel"](
                            array(// параметры для метода
                                "channel" => $channel["id"],
                                "guild" => $guild["id"]
                            ),
                            array(// внутренние опции
                                "nocontrol" => true
                            ),
                            $sign, $status
                        );
                        $isEventsUpdate = $isEventsUpdate || $flag;
                    };
                };
            };
            // сохраняем базу данных событий
            if(isset($events) and !empty($events)){// если база данных загружена
                if(!get_val($options, "nocontrol", false)){// если это прямой вызов
                    if(empty($status) and $isEventsUpdate){// если нужно выполнить
                        if($events->save(false)){// если данные успешно сохранены
                        }else $status = 307;// не удалось сохранить базу данных
                    }else $events->unlock();// разблокируем базу
                };
            };
            // возвращаем результат
            $result = $isEventsUpdate;
            return $result;
        },
        "discord.channel" => function($params, $options, $sign, &$status){// обрабатываем канал
        //@param $params {array} - массив внешних не отфильтрованных значений
        //@param $options {array} - массив внутренних настроек
        //@param $sign {boolean|null} - успешность проверки подписи или null при её отсутствии
        //@param $status {number} - целое число статуса выполнения
        //@return {true|false} - были ли изменения базы событий
            global $app; $result = null;

            $now = microtime(true);// текущее время
            $isConsolidated = false;// в данном канале ведётся сводное рассписание
            $isEventsUpdate = false;// были ли обновлены данные в базе данных
            $hasMainPermission = false;// есть основные разрешения
            $hasListPermission = false;// есть разрешения для сводного расписания
            // получаем очищенные значения параметров
            $token = $app["fun"]["getClearParam"]($params, "token", "string");
            $guild = $app["fun"]["getClearParam"]($params, "guild", "string");
            $channel = $app["fun"]["getClearParam"]($params, "channel", "string");
            $app["fun"]["setDebug"](4, "discord.channel", $guild, $channel);// отладочная информация
            // проверяем корректность указанных параметров
            if(empty($status)){// если нет ошибок
                if((!is_null($token) and !is_null($guild) and !is_null($channel)) or get_val($options, "nocontrol", false)){// если указаны обязательные поля
                    if((!empty($token) and !empty($guild) and !empty($channel)) or get_val($options, "nocontrol", false)){// если обязательные поля успешно отфильтрованы
                        if($token == $app["val"]["appToken"] or get_val($options, "nocontrol", false)){// если прошли проверку
                        }else $status = 303;// переданные параметры не верны
                    }else $status = 302;// один из обязательных параметров передан в неверном формате
                }else $status = 301;// не передан один из обязательных параметров
            };
            // загружаем все необходимые базы данных
            if(empty($status)){// если нет ошибок
                $events = $app["fun"]["getStorage"]("events", true);
                if(!empty($events)){// если удалось получить доступ к базе данных
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
                    // проверяем разрешения
                    $permission = $app["fun"]["getPermission"]("member", $app["val"]["discordBotId"], $channel, $guild["id"]);
                    $hasMainPermission = ($permission & $app["val"]["discordMainPermission"]) == $app["val"]["discordMainPermission"];
                    $hasListPermission = ($permission & $app["val"]["discordListPermission"]) == $app["val"]["discordListPermission"];
                    $isConsolidated = ($hasListPermission and !$hasMainPermission);
                }else $status = 303;// переданные параметры не верны
            };
            // выполняем регламентные операции с базой событий
            if(empty($status) and !get_val($options, "nocontrol", false)){// если нужно выполнить
                $flag = count($app["fun"]["changeEvents"]($now, $status)) > 0;
                $isEventsUpdate = $isEventsUpdate || $flag;
            };
            // выполняем обработку сообщений в канале
            if(empty($status) and ($hasMainPermission or $hasListPermission)){// если нужно выполнить
                // формируем список идентификаторов сообщений для обработки
                $list = array();// список идентификаторов сообщений для обработки
                for($i = 0, $iLen = count($channel["messages"]); $i < $iLen; $i++){
                    $message = $channel["messages"][$i];// получаем очередной элемент
                    $flag = (!$message["pinned"] and $message["author"]["id"] != $app["val"]["discordBotId"]);
                    if($flag) array_unshift($list, $message["id"]);// добавляем в список
                };
                // обрабатываем список идентификаторов сообщений
                for($i = 0, $iLen = count($list); (!$i or $i < $iLen) and empty($status); $i++){// пробигаемся по списку идентификаторов сообщений
                    $message = $iLen ? $app["fun"]["getCache"]("message", $list[$i], $channel["id"], $guild["id"]) : null;
                    // обрабатываем сообшение
                    if(!$iLen or $message){// если очередной элемент существует
                        $flag = $app["method"]["discord.message"](
                            array(// параметры для метода
                                "message" => $message ? $message["id"] : null,
                                "channel" => $channel["id"],
                                "guild" => $guild["id"]
                            ),
                            array(// внутренние опции
                                "nocontrol" => true
                            ),
                            $sign, $status
                        );
                        $isEventsUpdate = $isEventsUpdate || $flag;
                    };
                };
            };
            // сохраняем базу данных событий
            if(isset($events) and !empty($events)){// если база данных загружена
                if(!get_val($options, "nocontrol", false)){// если это прямой вызов
                    if(empty($status) and $isEventsUpdate){// если нужно выполнить
                        if($events->save(false)){// если данные успешно сохранены
                        }else $status = 307;// не удалось сохранить базу данных
                    }else $events->unlock();// разблокируем базу
                };
            };
            // возвращаем результат
            $result = $isEventsUpdate;
            return $result;
        },
        "discord.message" => function($params, $options, $sign, &$status){// обрабатываем сообщение
        //@param $params {array} - массив внешних не отфильтрованных значений
        //@param $options {array} - массив внутренних настроек
        //@param $sign {boolean|null} - успешность проверки подписи или null при её отсутствии
        //@param $status {number} - целое число статуса выполнения
        //@return {true|false} - были ли изменения базы событий
            global $app; $result = null;
            
            $error = 0;// код ошибки для обратной связи
            $now = microtime(true);// текущее время
            $flags = array();// список вспомогательных флагов
            $isEdit = false;// эта каманда на изменение записи
            $isExtCommand = false;// передана внешняя команда для обработки
            $isConsolidated = false;// в данном канале ведётся сводное рассписание
            $isConsolidatedChange = false;// изменилось ли сводное расписание
            $isEventsUpdate = false;// были ли обновлены данные в базе данных
            $isNeedProcessing = false;// требуется ли дальнейшая обработка
            $hasMainPermission = false;// есть основные разрешения
            $hasListPermission = false;// есть разрешения для сводного расписания
            $hasTalkPermission = false;// есть разрешения для ведения обсуждения
            $timezone = $app["val"]["timeZone"];// временная зона
            $language = $app["val"]["discordLang"];// локализация
            // получаем очищенные значения параметров
            $token = $app["fun"]["getClearParam"]($params, "token", "string");
            $guild = $app["fun"]["getClearParam"]($params, "guild", "string");
            $channel = $app["fun"]["getClearParam"]($params, "channel", "string");
            $message = $app["fun"]["getClearParam"]($params, "message", "string");
            $app["fun"]["setDebug"](5, "discord.message", $guild, $channel, $message);// отладочная информация
            // проверяем корректность указанных параметров
            if(empty($status)){// если нет ошибок
                if((!is_null($token) and !is_null($guild) and !is_null($channel) and !is_null($message)) or get_val($options, "nocontrol", false)){// если указаны обязательные поля
                    if((!empty($token) and !empty($guild) and !empty($channel) and !empty($message)) or get_val($options, "nocontrol", false)){// если обязательные поля успешно отфильтрованы
                        if($token == $app["val"]["appToken"] or get_val($options, "nocontrol", false)){// если прошли проверку
                        }else $status = 303;// переданные параметры не верны
                    }else $status = 302;// один из обязательных параметров передан в неверном формате
                }else $status = 301;// не передан один из обязательных параметров
            };
            // загружаем все необходимые базы данных
            if(empty($status)){// если нет ошибок
                $events = $app["fun"]["getStorage"]("events", true);
                if(!empty($events)){// если удалось получить доступ к базе данных
                }else $status = 304;// не удалось загрузить одну из многих баз данных
            };
            if(empty($status)){// если нет ошибок
                $raids = $app["fun"]["getStorage"]("raids", false);
                if(!empty($raids)){// если удалось получить доступ к базе данных
                }else $status = 304;// не удалось загрузить одну из многих баз данных
            };
            if(empty($status)){// если нет ошибок
                $chapters = $app["fun"]["getStorage"]("chapters", false);
                if(!empty($chapters)){// если удалось получить доступ к базе данных
                }else $status = 304;// не удалось загрузить одну из многих баз данных
            };
            if(empty($status)){// если нет ошибок
                $types = $app["fun"]["getStorage"]("types", false);
                if(!empty($types)){// если удалось получить доступ к базе данных
                }else $status = 304;// не удалось загрузить одну из многих баз данных
            };
            if(empty($status)){// если нет ошибок
                $roles = $app["fun"]["getStorage"]("roles", false);
                if(!empty($roles)){// если удалось получить доступ к базе данных
                }else $status = 304;// не удалось загрузить одну из многих баз данных
            };
            if(empty($status)){// если нет ошибок
                $names = $app["fun"]["getStorage"]("names", false);
                if(!empty($names)){// если удалось получить доступ к базе данных
                }else $status = 304;// не удалось загрузить одну из многих баз данных
            };
            if(empty($status)){// если нет ошибок
                $actions = $app["fun"]["getStorage"]("actions", false);
                if(!empty($actions)){// если удалось получить доступ к базе данных
                }else $status = 304;// не удалось загрузить одну из многих баз данных
            };
            if(empty($status)){// если нет ошибок
                $additions = $app["fun"]["getStorage"]("additions", false);
                if(!empty($additions)){// если удалось получить доступ к базе данных
                }else $status = 304;// не удалось загрузить одну из многих баз данных
            };
            if(empty($status)){// если нет ошибок
                $dates = $app["fun"]["getStorage"]("dates", false);
                if(!empty($dates)){// если удалось получить доступ к базе данных
                }else $status = 304;// не удалось загрузить одну из многих баз данных
            };
            if(empty($status)){// если нет ошибок
                $months = $app["fun"]["getStorage"]("months", false);
                if(!empty($months)){// если удалось получить доступ к базе данных
                }else $status = 304;// не удалось загрузить одну из многих баз данных
            };
            if(empty($status)){// если нет ошибок
                $feedbacks = $app["fun"]["getStorage"]("feedbacks", false);
                if(!empty($feedbacks)){// если удалось получить доступ к базе данных
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
                    // проверяем разрешения
                    $permission = $app["fun"]["getPermission"]("member", $app["val"]["discordBotId"], $channel, $guild["id"]);
                    $hasMainPermission = ($permission & $app["val"]["discordMainPermission"]) == $app["val"]["discordMainPermission"];
                    $hasListPermission = ($permission & $app["val"]["discordListPermission"]) == $app["val"]["discordListPermission"];
                    $hasTalkPermission = ($permission & $app["val"]["discordTalkPermission"]) == $app["val"]["discordTalkPermission"];
                    $isConsolidated = ($hasListPermission and !$hasMainPermission);
                    // изменяем контекстные переменные
                    $delim = "\n";// разделитель значений в теме канала
                    $list = explode($delim, $channel["topic"]);
                    for($i = 0, $iLen = count($list); $i < $iLen; $i++){// пробигаемся по списку
                        $value = trim($list[$i]);// получаем очередное значение
                        // определяем язык канала
                        $key = $names->key(0);// получаем значение первого ключа
                        $data = $names->get($key);// получаем данные по ключу
                        $key = mb_strtolower($value);// формируем новый ключ
                        $flag = ($key != $names->primary and isset($data[$key]));
                        if($flag) $language = $key;// изменяем переменную
                        // определяем часовой пояс канала
                        $flag = in_array($value, timezone_identifiers_list());
                        if($flag) $timezone = $value;// изменяем переменную
                    };
                }else $status = 303;// переданные параметры не верны
            };
            // получаем информацию о сообщении
            if(empty($status) and !empty($message)){// если нужно выполнить
                $message = $app["fun"]["getCache"]("message", $message, $channel["id"], $guild["id"]);
                if($message){// если удалось получить данные
                    $isNeedProcessing = (!$message["pinned"] and $message["author"]["id"] != $app["val"]["discordBotId"]);
                }else $status = 303;// переданные параметры не верны
            };
            // выполняем регламентные операции с базой событий
            if(empty($status) and !get_val($options, "nocontrol", false)){// если нужно выполнить
                $flag = count($app["fun"]["changeEvents"]($now, $status)) > 0;
                $isEventsUpdate = $isEventsUpdate || $flag;
                $isConsolidatedChange = $flag;
            };
            // получаем данные из внутренних настроек
            if(empty($status)){// если нет ошибок
                $isExtCommand = !empty(get_val($options, "command", false));
                $author = get_val($options, "author", get_val($message, "author", false));
                $timestamp = get_val($message, "timestamp", $now);
            };
            // обрабатываем сообщение
            if(empty($status) and !empty($author) and $hasMainPermission and ($isExtCommand or $isNeedProcessing and !$message["type"])){// если нужно выполнить
                $command = array(// пустая команда
                    "action" => "",     // действие
                    "index" => 0,       // номер
                    "role" => "",       // роль
                    "date" => 0,        // дата
                    "time" => 0,        // дата и время
                    "raid" => "",       // рейд
                    "user" => "",       // пользователь
                    "addition" => "",   // опция
                    "comment" => ""     // комментарий
                );
                // формируем команду из сообщения
                if(!$isExtCommand){// если нужно выполнить
                    $delim = " ";// разделитель параметров в сообщении
                    $content = $message["content"];// получаем содержимое сообщения
                    $content = trim($content);// убираем пробелы с концов сообщения
                    for($index = 0; true; $index++){// циклическая обработка
                        // действие - KEY
                        $key = "action";// текущий параметр команды
                        $value = $command[$key];// текущий значение параметра
                        if(!$index and !mb_strlen($value)){// если нужно выполнить
                            $value = $app["fun"]["getCommandValue"]($content, $delim, null, $actions);
                            if(mb_strlen($value)){// если найдено ключевое значение
                                $command[$key] = $value;
                                continue;
                            };
                        };
                        // роль - KEY
                        $key = "role";// текущий параметр команды
                        $value = $command[$key];// текущий значение параметра
                        if(!mb_strlen($value)){// если нужно выполнить
                            $value = $app["fun"]["getCommandValue"]($content, $delim, $language, $roles);
                            if(mb_strlen($value)){// если найдено ключевое значение
                                $command[$key] = $value;
                                continue;
                            };
                        };
                        // номер - ID
                        $key = "index";// текущий параметр команды
                        $value = $command[$key];// текущий значение параметра
                        if(!$value){// если нужно выполнить
                            $value = $app["fun"]["getCommandValue"]($content, $delim, null, "/^(0\d{2})$/", $list);
                            if(mb_strlen($value)){// если найдено ключевое значение
                                $command[$key] = 1 * $value;
                                continue;
                            };
                        };
                        // дата - KEY
                        $key = "date";// текущий параметр команды
                        $value = $command[$key];// текущий значение параметра
                        if(!$value){// если нужно выполнить
                            $value = $app["fun"]["getCommandValue"]($content, $delim, $language, $dates);
                            if(mb_strlen($value)){// если найдено ключевое значение
                                $value = $app["fun"]["dateCreate"]($value, $timezone);
                                $command[$key] = $value;
                                continue;
                            };
                        };
                        // дата - DD.MM
                        $key = "date";// текущий параметр команды
                        $value = $command[$key];// текущий значение параметра
                        if(!$value){// если нужно выполнить
                            $value = $app["fun"]["getCommandValue"]($content, $delim, null, "/^(\d{1,2}\.)(\d{2})$/", $list);
                            if(mb_strlen($value)){// если найдено ключевое значение
                                $t1 = $app["fun"]["dateCreate"]($value . "." . $app["fun"]["dateFormat"]("Y", $timestamp, $timezone), $timezone);
                                $t2 = $app["fun"]["dateCreate"]($value . "." . $app["fun"]["dateFormat"]("Y", $app["fun"]["dateCreate"]("+1 year", $timezone), $timezone), $timezone);
                                $value = (abs($t1 - $timestamp) < abs($t2 - $timestamp) ? $t1 : $t2);
                                $command[$key] = $value;
                                continue;
                            };
                        };
                        // дата - DD.MM.YY
                        $key = "date";// текущий параметр команды
                        $value = $command[$key];// текущий значение параметра
                        if(!$value){// если нужно выполнить
                            $value = $app["fun"]["getCommandValue"]($content, $delim, null, "/^(\d{1,2}\.)(\d{2}\.)(\d{2})$/", $list);
                            if(mb_strlen($value)){// если найдено ключевое значение
                                $value = $list[1] . $list[2] . mb_substr($app["fun"]["dateFormat"]("Y", $timestamp, $timezone), 0, 2) . $list[3];
                                $value = $app["fun"]["dateCreate"]($value, $timezone);
                                $command[$key] = $value;
                                continue;
                            };
                        };
                        // дата - DD.MM.YYYY
                        $key = "date";// текущий параметр команды
                        $value = $command[$key];// текущий значение параметра
                        if(!$value){// если нужно выполнить
                            $value = $app["fun"]["getCommandValue"]($content, $delim, null, "/^(\d{1,2}\.)(\d{2}\.)(\d{4})$/", $list);
                            if(mb_strlen($value)){// если найдено ключевое значение
                                $value = $app["fun"]["dateCreate"]($value, $timezone);
                                $command[$key] = $value;
                                continue;
                            };
                        };
                        // время - HH:MM
                        $key = "time";// текущий параметр команды
                        $value = $command[$key];// текущий значение параметра
                        if(!$value){// если нужно выполнить
                            $value = $app["fun"]["getCommandValue"]($content, $delim, null, "/^(\d{1,2}\:)(\d{2})$/", $list);
                            if(mb_strlen($value)){// если найдено ключевое значение
                                $value = $app["fun"]["dateCreate"]($value, $timezone);
                                $command[$key] = $value;
                                continue;
                            };
                        };
                        // рейд - KEY
                        $key = "raid";// текущий параметр команды
                        $value = $command[$key];// текущий значение параметра
                        if(!mb_strlen($value)){// если нужно выполнить
                            $text = $content;// копируем состояния строки
                            $value = $app["fun"]["getCommandValue"]($text, $delim);
                            if(1 == mb_strlen($value)) $value .= $app["fun"]["getCommandValue"]($text, $delim);
                            $value = $app["fun"]["getCommandValue"]($value, $delim, false, $raids);
                            if(mb_strlen($value)){// если найдено ключевое значение
                                $command[$key] = $value;
                                $content = $text;
                                continue;
                            };
                        };
                        // пользователь - ID
                        $key = "user";// текущий параметр команды
                        $value = $command[$key];// текущий значение параметра
                        if(!mb_strlen($value)){// если нужно выполнить
                            $value = $app["fun"]["getCommandValue"]($content, $delim, null, "/^<@!?(\d+)>$|^(".$app["val"]["discordAllUser"].")$/", $list);
                            if(mb_strlen($value)){// если найдено ключевое значение
                                $command[$key] = array_pop($list);
                                continue;
                            };
                        };
                        // опция - KEY
                        $key = "addition";// текущий параметр команды
                        $value = $command[$key];// текущий значение параметра
                        if(!mb_strlen($value)){// если нужно выполнить
                            $value = $app["fun"]["getCommandValue"]($content, $delim, $language, $additions);
                            if(mb_strlen($value)){// если найдено ключевое значение
                                $command[$key] = $value;
                                continue;
                            };
                        };
                        // комментарий - TEXT
                        $delim = "\n";// разделитель
                        $key = "comment";// текущий параметр команды
                        $value = $command[$key];// текущий значение параметра
                        if(!mb_strlen($value)){// если нужно выполнить
                            $value = $app["fun"]["getCommandValue"]($content, $delim);
                            if(mb_strlen($value)){// если найдено ключевое значение
                                $command[$key] = $value;
                            };
                        };
                        // завершаем обработку
                        break;
                    };
                }else $command = array_merge($command, $options["command"]);
                // обрабатываем команду в сообщении
                if(empty($error)){// если нет проблем
                    // формируем список записей
                    $items = array();// список записей
                    for($i = 0, $iLen = $events->length; $i < $iLen; $i++){
                        $id = $events->key($i);// получаем ключевой идентификатор по индексу
                        $event = $events->get($id);// получаем элемент по идентификатору
                        if(// множественное условие
                            $event["channel"] == $channel["id"]
                            and $event["guild"] == $guild["id"]
                        ){// если нужно посчитать счётчик
                            $item = $event;// копируем элемент
                            array_push($items, $item);
                        };
                    };
                    // сортируем список записей
                    usort($items, function($a, $b){// сортировка
                        $value = 0;// начальное значение
                        if(!$value and $a["time"] != $b["time"]) $value = $a["time"] > $b["time"] ? 1 : -1;
                        if(!$value and $a["raid"] != $b["raid"]) $value = $a["raid"] > $b["raid"] ? 1 : -1;
                        if(!$value and $a["leader"] != $b["leader"]) $value = $a["leader"] ? 1 : -1;
                        if(!$value and $a["id"] != $b["id"]) $value = $a["id"] < $b["id"] ? 1 : -1;
                        // возвращаем результат
                        return $value;
                    });
                    // удаляем вторичные записи
                    $after = null;// следующий элемент
                    for($i = count($items) - 1; $i > -1; $i--){
                        $item = $items[$i];// получаем очередной элемент
                        if(// множественное условие
                            !empty($after)
                            and $item["time"] == $after["time"]
                            and $item["raid"] == $after["raid"]
                        ){// если нужно удалить запись
                            array_splice($items, $i, 1);
                        }else $after = $item;// копируем элемент
                    };
                    // обрабатываем список записей
                    $next = null;// ближайшая запись
                    $current = null;// подходящая запись
                    for($i = 0, $iLen = count($items); $i < $iLen; $i++){
                        $item = $items[$i];// получаем очередной элемент
                        // фильтруем запись
                        if(// множественное условие
                            (!empty($command["raid"]) ? $command["raid"] == $item["raid"] : true)
                            and (!empty($command["time"]) ? $app["fun"]["dateFormat"]("H:i", $command["time"], $timezone) == $app["fun"]["dateFormat"]("H:i", $item["time"], $timezone) : true)
                            and (!empty($command["date"]) ? $app["fun"]["dateFormat"]("d.m.Y", $command["date"], $timezone) == $app["fun"]["dateFormat"]("d.m.Y", $item["time"], $timezone) : true)
                        ){// если запись соответствует фильтру
                            if(is_null($current)) $current = $item;
                            else $current = false;
                        }else $items[$i]["id"] = 0;
                        // определяем ближайшую запись
                        if(// множественное условие
                            1 == $iLen or empty($next) and $item["time"] >= $timestamp + $app["val"]["eventTimeClose"]
                        ){// если ближайшая запись
                            $next = $item;
                        };
                    };
                    // получаем подходящую запись
                    if(!empty($command["index"])){// если задан номер записи
                        $i = $command["index"] - 1;// позиция записи в списке
                        if(isset($items[$i])){// если запись существует
                            $item = $items[$i];// получаем очередной элемент
                            $current = $item;// задаём подходящую запись
                        }else $current = false;// сбрасываем подходящую запись
                    };
                    // дополняем список вспомогательных флагов
                    if(empty($error)){// если нет проблем
                        $flags["is-select-next"] = (empty($command["index"]) and empty($command["raid"]) and empty($command["date"]) and empty($command["time"]) and !empty($command["role"]));
                    };
                    // обрабатываем команду
                    switch($command["action"]){// поддержмваемые команды
                        case "*":// изменить запись
                            $isEdit = true;
                            // корректируем подходящую запись
                            if(empty($error)){// если нет проблем
                                $flag = empty($command["index"]);
                                if($flag) $current = $next;// задаём подходящую запись
                            };
                            // проверяем что определено событие
                            if(empty($error)){// если нет проблем
                                if(!empty($current)){// если проверка пройдена
                                }else $error = $flag ? 12 : 52;
                            };
                        case "+":// добавить запись
                            // корректируем подходящую запись
                            if(empty($error) and !$isEdit){// если нужно выполнить
                                $flag = $flags["is-select-next"];
                                if($flag) $current = $next;// задаём подходящую запись
                            };
                            // получаем данные из подходящей записи
                            if(empty($error) and !$isEdit){// если нужно выполнить
                                if(!empty($current["id"])){// если запись прошла фильтр
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
                                    if($time > 0) $value = $app["fun"]["dateFormat"]("d.m.Y", $time, $timezone);
                                    if($time > 0) $command["date"] = $app["fun"]["dateCreate"]($value, $timezone);
                                };
                            };
                            // корректируем время
                            if(empty($error)){// если нет проблем
                                if(!empty($command["date"]) and $command["time"] >= 0){// если нужно выполнить
                                    $time = (!$command["time"] and !empty($current)) ? $current["time"] : $command["time"];
                                    if($time > 0) $value = $app["fun"]["dateFormat"]("d.m.Y", $command["date"], $timezone);
                                    if($time > 0) $value .= " " . $app["fun"]["dateFormat"]("H:i", $time, $timezone);
                                    if($time > 0) $command["time"] = $app["fun"]["dateCreate"]($value, $timezone);
                                };
                            };
                            // проверяем что указана роль
                            if(empty($error)){// если нет проблем
                                $role = null;// сбрасываем значение
                                if(!empty($command["role"])){// если проверка пройдена
                                    $role = $roles->get($command["role"]);
                                }else if($isEdit and !empty($current["role"])){// если проверка пройдена
                                    $role = $roles->get($current["role"]);
                                }else $error = 13;
                            };
                            // проверяем ограничения на комментарий
                            if(empty($error)){// если нет проблем
                                $value = $app["val"]["eventCommentLength"];
                                if(mb_strlen($command["comment"]) <= $value){// если проверка пройдена
                                }else $error = 54;
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
                            if(empty($error) and !empty($time)){// если нужно выполнить
                                $flag = false;// есть ли необходимые права для выполнения действия
                                if(!$flag) $permission = $app["fun"]["getPermission"]("channel", $author["id"], $channel["id"], $guild["id"]);
                                $flag = ($flag or ($permission & $app["val"]["discordUserPermission"]) == $app["val"]["discordUserPermission"]);
                                if(// множественное условие
                                    ($flag or $time >= $timestamp + $app["val"]["eventTimeClose"])
                                    and $time > $timestamp - $app["val"]["eventTimeDelete"]
                                    and $time < $timestamp + $app["val"]["eventTimeAdd"]
                                ){// если проверка пройдена
                                }else $error = 58;
                            };
                            // проверяем возможность использовать роль в рейде
                            if(empty($error)){// если нет проблем
                                $limit = $raid[$role["key"]];
                                if($limit > -1){// если проверка пройдена
                                }else $error = 59;
                            };
                            // проверяем ограничивающий фильтр в теме канала
                            if(empty($error)){// если нет проблем
                                $count = array();// счётчик элементов
                                // считаем количество записей
                                for($i = 0, $iLen = $types->length; $i < $iLen; $i++){
                                    $key = $types->key($i);// получаем ключевой идентификатор по индексу
                                    $type = $types->get($key);// получаем элемент по идентификатору
                                    $flag = false;// найдено ли ограничение в теме канала
                                    $flag = ($flag or false !== mb_stripos($channel["topic"], $type["key"]));
                                    $flag = ($flag or false !== mb_stripos($channel["topic"], $type[$language]));
                                    if($flag){// если есть совподение
                                        // выполняем подсчёт элементов
                                        if(!isset($count["raid"])) $count["raid"] = 0;
                                        if(!isset($count["channel"])) $count["channel"] = 0;
                                        if($type["key"] == $raid["type"]) $count["raid"]++;
                                        $count["channel"]++;
                                    };
                                };
                                if(// множественное условие
                                    empty($count["channel"])
                                    or !empty($count["raid"])
                                ){// если проверка пройдена
                                }else $error = 60;
                            };
                            // определяем контекст пользователя
                            if(empty($error)){// если нет проблем
                                if($command["user"] == $app["val"]["discordAllUser"]) $uid = null;
                                else if(!empty($command["user"])) $uid = $command["user"];
                                else $uid = $author["id"];// автор сообщения
                                $member = $uid ? $app["fun"]["getCache"]("member", $uid, $guild["id"]) : null;
                                if($member and !$member["user"]["bot"]){// если проверка пройдена
                                }else $error = $isEdit ? $error : 61;
                            };
                            // вычисляем необходимые счётчики
                            if(empty($error) and !empty($time)){// если нужно выполнить
                                $count = array();// счётчик элементов
                                for($i = 0, $iLen = $events->length; $i < $iLen; $i++){
                                    $id = $events->key($i);// получаем ключевой идентификатор по индексу
                                    $event = $events->get($id);// получаем элемент по идентификатору
                                    // выполняем подсчёт элементов
                                    if(!isset($count["time"])) $count["time"] = 0;
                                    if(!isset($count["raid"])) $count["raid"] = 0;
                                    if(!isset($count["item"])) $count["item"] = 0;
                                    if(!isset($count["edit"])) $count["edit"] = 0;
                                    if(// множественное условие
                                        $event["channel"] == $channel["id"]
                                        and $event["guild"] == $guild["id"]
                                    ){// если нужно посчитать счётчик
                                        // в разрезе редактирования
                                        if(// множественное условие
                                            !empty($current) and $event["time"] == $time
                                            and !($event["time"] == $current["time"] and $event["raid"] == $current["raid"])
                                        ){// если время уже занято другим событием
                                            $count["edit"]++;
                                        };
                                        // в разрезе колличества записей
                                        if(// множественное условие
                                            $event["time"] == $time and $event["raid"] == $raid["key"]
                                        ){// если рейд и время совпадает
                                            $count["item"]++;
                                        };
                                        // в разрезе пользователя
                                        if(// множественное условие
                                            $event["time"] == $time and $member and $event["user"] == $member["user"]["id"]
                                        ){// если пользователь и время совпадает
                                            $count["time"]++;
                                            if($event["raid"] == $raid["key"]){// если рейд совпадает
                                                $count["raid"]++;
                                            };
                                        };
                                    };
                                };
                            };
                            // дополняем список вспомогательных флагов
                            if(empty($error)){// если нет проблем
                                $flags["is-change-event"] = (!empty($command["raid"]) and $current["raid"] != $raid["key"] or !empty($command["time"]) and $current["time"] != $time);
                                $flags["is-author-leader"] = (!empty($current["leader"]) and $author["id"] == $current["user"]);
                                $flags["is-current-comment"] = (!empty($current) and mb_strlen($current["comment"]));
                            };
                            // проверяем права на создание записи от имени других пользователей
                            if(empty($error) and (!$member or $member["user"]["id"] != $author["id"])){// если нужно выполнить
                                $flag = $flags["is-author-leader"];// есть ли необходимые права для выполнения действия
                                if(!$flag) $permission = $app["fun"]["getPermission"]("channel", $author["id"], $channel["id"], $guild["id"]);
                                $flag = ($flag or ($permission & $app["val"]["discordUserPermission"]) == $app["val"]["discordUserPermission"]);
                                if($flag){// если проверка пройдена
                                }else $error = $isEdit ? 63 : 62;
                            };
                            // проверяем права на создание первой записи
                            if(empty($error) and empty($count["item"]) and !$isEdit){// если нужно выполнить
                                $flag = false;// есть ли необходимые права для выполнения действия
                                if(!$flag) $permission = $app["fun"]["getPermission"]("channel", $author["id"], $channel["id"], $guild["id"]);
                                $flag = ($flag or ($permission & $app["val"]["discordCreatePermission"]) == $app["val"]["discordCreatePermission"]);
                                if($flag){// если проверка пройдена
                                    $isConsolidatedChange = true;
                                }else $error = 64;
                            };
                            // проверяем права на перенос события
                            if(empty($error) and $isEdit){// если нужно выполнить
                                $flag = (!$flags["is-change-event"] or $flags["is-author-leader"]);// есть ли необходимые права для выполнения действия
                                if(!$flag) $permission = $app["fun"]["getPermission"]("channel", $author["id"], $channel["id"], $guild["id"]);
                                $flag = ($flag or ($permission & $app["val"]["discordUserPermission"]) == $app["val"]["discordUserPermission"]);
                                if($flag){// если проверка пройдена
                                    $flag = $flags["is-change-event"];
                                    if($flag) $isConsolidatedChange = true;
                                }else $error = 65;
                            };
                            // проверяем возможность переноса события
                            if(empty($error) and !empty($count["edit"]) and $isEdit){// если нужно выполнить
                                $flag = !$flags["is-change-event"];// есть ли возможность переноса события
                                if($flag){// если проверка пройдена
                                }else $error = 66;
                            };
                            // проверяем лимиты для записи
                            if(empty($error) and !$isEdit){// если нужно выполнить
                                $flag = (!$count["raid"] and !$count["time"]);
                                if($flag){// если проверка пройдена
                                }else $error = 67;
                            };
                            // обрабатываем дополнительные опции
                            if(empty($error)){// если нет проблем
                                $unit = array();// данные для события
                                $value = 24*60*60;// база для повторения
                                switch($command["addition"]){// поддержмваемые опции
                                    case "once":// однократно
                                        $value = 0;
                                    case "weekly"://еженедельно
                                        $value = 7 * $value;
                                    case "daily"://ежедневно
                                        $flag = !$value;// есть ли необходимые права для выполнения действия
                                        if(!$flag) $permission = $app["fun"]["getPermission"]("channel", $author["id"], $channel["id"], $guild["id"]);
                                        $flag = ($flag or ($permission & $app["val"]["discordCreatePermission"]) == $app["val"]["discordCreatePermission"]);
                                        if($flag){// если проверка пройдена
                                            $unit["repeat"] = $value;
                                        }else $error = 69;
                                    case "leader":// лидер
                                        $flag = (empty($current["leader"]) or $flags["is-author-leader"]);// есть ли необходимые права для выполнения действия
                                        if(!$flag) $permission = $app["fun"]["getPermission"]("channel", $author["id"], $channel["id"], $guild["id"]);
                                        $flag = ($flag or ($permission & $app["val"]["discordUserPermission"]) == $app["val"]["discordUserPermission"]);
                                        if($flag){// если проверка пройдена
                                            if($member){// если указан пользователь
                                                $unit["leader"] = true;
                                            }else $error = 71;
                                        }else $error = 70;
                                        break;
                                    case "member":// участник
                                        $unit["leader"] = false;
                                        break;
                                    case "reject":// нет
                                        $value = 0;
                                    case "accept":// принят
                                        $flag = $flags["is-author-leader"];// есть ли необходимые права для выполнения действия
                                        if(!$flag) $permission = $app["fun"]["getPermission"]("channel", $author["id"], $channel["id"], $guild["id"]);
                                        $flag = ($flag or ($permission & $app["val"]["discordUserPermission"]) == $app["val"]["discordUserPermission"]);
                                        if($flag){// если проверка пройдена
                                            $unit["accept"] = !!$value;
                                        }else $error = 72;
                                        break;
                                    case "":// опция не указана
                                        break;
                                    default:// не известная опция
                                        $error = 68;
                                };
                            };
                            // дополняем список вспомогательных флагов
                            if(empty($error)){// если нет проблем
                                $flags["is-change-leader"] = (!empty($current["leader"]) and isset($unit["leader"]) and ($unit["leader"] ? $member["user"]["id"] != $current["user"] : (!$member or $member["user"]["id"] == $current["user"])));
                                $flags["is-first-accept"] = (!empty($current["leader"]) and !empty($unit["accept"]) and !$current["accept"] and $member and $member["user"]["id"] != $current["user"]);
                            };
                            // обрабатываем комментарий
                            if(empty($error)){// если нет проблем
                                if("-" == $command["comment"]) $command["comment"] = $unit["comment"] = "";
                                else if(mb_strlen($command["comment"])) $unit["comment"] = $command["comment"];
                                if(!isset($unit["comment"]) and !empty($unit["leader"]) and $flags["is-change-leader"] and $flags["is-current-comment"]) $unit["comment"] = $current["comment"];
                                if(isset($unit["comment"]) and !empty($current["leader"]) and $member and $member["user"]["id"] == $current["user"]) $isConsolidatedChange = true;
                                if(isset($unit["leader"]) and (!$unit["leader"] or empty($current["leader"]) or $current["comment"] != $unit["comment"])) $isConsolidatedChange = true;
                            };
                            // изменяем данные в базе данных
                            if(empty($error)){// если нет проблем
                                $index = 0;// счётчик количества изменённых записей
                                for($i = $events->length - 1; $i > - 1 and empty($status); $i--){
                                    $id = $events->key($i);// получаем ключевой идентификатор по индексу
                                    $event = $events->get($id);// получаем элемент по идентификатору
                                    $item = array();// вспомогательный элемент при переносе рейда
                                    // дополняем список вспомогательных флагов
                                    $flags["is-this-date"] = (empty($current) ? (empty($command["date"]) or $app["fun"]["dateFormat"]("d.m.Y", $event["time"], $timezone) == $app["fun"]["dateFormat"]("d.m.Y", $command["date"], $timezone)) : $event["time"] == $current["time"]);
                                    $flags["is-this-time"] = (empty($current) ? (empty($command["time"]) or $app["fun"]["dateFormat"]("H:i", $event["time"], $timezone) == $app["fun"]["dateFormat"]("H:i", $command["time"], $timezone)) : $event["time"] == $current["time"]);
                                    $flags["is-this-raid"] = (empty($current) ? (empty($command["raid"]) or $event["raid"] == $command["raid"]) : $event["raid"] == $current["raid"]);
                                    $flags["is-this-member"] = (!$member or $event["user"] == $member["user"]["id"]);
                                    $flags["is-change-role"] = (!empty($command["role"]) and $event["role"] != $role["key"]);
                                    if(// множественное условие
                                        $event["channel"] == $channel["id"]
                                        and $event["guild"] == $guild["id"]
                                        and $flags["is-this-date"]
                                        and $flags["is-this-time"]
                                        and $flags["is-this-raid"]
                                        and (
                                            $flags["is-this-member"]
                                            or $flags["is-change-event"]
                                            or $flags["is-first-accept"] and !$flags["is-change-leader"] and $event["leader"]
                                            or $flags["is-change-leader"] and $event["leader"]
                                        )
                                    ){// если нужно изменить запись в событии
                                        if(!empty($command["raid"])) $unit["raid"] = $item["raid"] = $raid["key"];
                                        if(!empty($command["time"])) $unit["time"] = $item["time"] = $time;
                                        if(!empty($command["role"])) $unit["role"] = $role["key"];
                                        if(!isset($unit["accept"]) and $flags["is-change-role"] and !$flags["is-author-leader"]) $unit["accept"] = false;
                                        if(mb_strlen($event["comment"]) and $flags["is-change-leader"] and $event["leader"]) $item["comment"] = "";
                                        if($flags["is-first-accept"] and !$flags["is-change-leader"] and $event["leader"]) $item["accept"] = true;
                                        if($flags["is-change-leader"] and $event["leader"]) $item["leader"] = false;
                                        $flag = $flags["is-this-member"];// нужно ли приминить полные изменения
                                        if($events->set($id, null, $flag ? $unit : $item)){// если данные успешно изменены
                                            $isEventsUpdate = true;// были обновлены данные в базе данных
                                            $index++;// увиличиваем счётчик количества изменённых записей
                                        }else $status = 309;// не удалось записать данные в базу данных
                                    };
                                };
                                if($index){// если изменены записи
                                }else $error = $isEdit ? 21 : $error;
                            };
                            // добавляем данные в базу данных
                            if(empty($error) and !$isEdit){// если нужно выполнить
                                $event = array(// значения по умолчанию
                                    "guild" => $guild["id"],
                                    "channel" => $channel["id"],
                                    "user" => $member["user"]["id"],
                                    "time" => $command["time"],
                                    "comment" => $command["comment"],
                                    "raid" => $raid["key"],
                                    "role" => $role["key"],
                                    "leader" => false,
                                    "accept" => false,
                                    "repeat" => 0
                                );
                                $unit = array_merge($event, $unit);
                                $id = $events->length ? $events->key($events->length - 1) + 1 : 1;
                                if($events->set($id, null, $unit)){// если данные успешно добавлены
                                    $isEventsUpdate = true;// были обновлены данные в базе данных
                                }else $status = 309;// не удалось записать данные в базу данных
                            };
                            break;
                        case "-":// удалить запись
                            // корректируем подходящую запись
                            if(empty($error)){// если нет проблем
                                $flag = $flags["is-select-next"];
                                if($flag) $current = $next;// задаём подходящую запись
                            };
                            // получаем данные из подходящей записи
                            if(empty($error)){// если нет проблем
                                if(!empty($current["id"])){// если запись прошла фильтр
                                    $command["raid"] = $current["raid"];
                                    $command["time"] = $current["time"];
                                }else $current = false;
                            };
                            // проверяем наложение фильтрующих параметров
                            if(empty($error)){// если нет проблем
                                $flag = (!empty($command["index"]) or empty($command["date"]) and !empty($command["role"]));
                                if(false !== $current){// если проверка пройдена
                                }else $error = $flag ? 53 : $error;
                            };
                            // корректируем дату
                            if(empty($error)){// если нет проблем
                                if(empty($command["date"]) and $command["time"] > 0){// если нужно выполнить
                                    $time = !empty($current) ? $current["time"] : $command["time"];
                                    if($time > 0) $value = $app["fun"]["dateFormat"]("d.m.Y", $time, $timezone);
                                    if($time > 0) $command["date"] = $app["fun"]["dateCreate"]($value, $timezone);
                                };
                            };
                            // корректируем время
                            if(empty($error)){// если нет проблем
                                if(!empty($command["date"]) and $command["time"] >= 0){// если нужно выполнить
                                    $time = (!$command["time"] and !empty($current)) ? $current["time"] : $command["time"];
                                    if($time > 0) $value = $app["fun"]["dateFormat"]("d.m.Y", $command["date"], $timezone);
                                    if($time > 0) $value .= " " . $app["fun"]["dateFormat"]("H:i", $time, $timezone);
                                    if($time > 0) $command["time"] = $app["fun"]["dateCreate"]($value, $timezone);
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
                            // получаем игровую роль
                            if(empty($error)){// если нет проблем
                                $role = null;// сбрасываем значение
                                if(!empty($command["role"])){// если проверка пройдена
                                    $role = $roles->get($command["role"]);
                                };
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
                                $flag = (empty($command["date"]) or empty($command["time"]) or $time >= $timestamp + $app["val"]["eventTimeClose"]);
                                if(!$flag) $permission = $app["fun"]["getPermission"]("channel", $author["id"], $channel["id"], $guild["id"]);
                                $flag = ($flag or ($permission & $app["val"]["discordUserPermission"]) == $app["val"]["discordUserPermission"]);
                                if($flag){// если проверка пройдена
                                }else $error = 75;
                            };  
                            // определяем контекст пользователя
                            if(empty($error)){// если нет проблем
                                if($command["user"] == $app["val"]["discordAllUser"]) $uid = null;
                                else if(!empty($command["user"])) $uid = $command["user"];
                                else $uid = $author["id"];// автор сообщения
                                $user = $uid ? $app["fun"]["getCache"]("user", $uid) : null;
                            };
                            // дополняем список вспомогательных флагов
                            if(empty($error)){// если нет проблем
                                $flags["is-author-leader"] = (!empty($current["leader"]) and $author["id"] == $current["user"]);
                            };
                            // проверяем права на удаление записей других пользователей
                            if(empty($error) and (!$user or $user["id"] != $author["id"])){// если нужно выполнить
                                $flag = $flags["is-author-leader"];// есть ли необходимые права для выполнения действия
                                if(!$flag) $permission = $app["fun"]["getPermission"]("channel", $author["id"], $channel["id"], $guild["id"]);
                                $flag = ($flag or ($permission & $app["val"]["discordUserPermission"]) == $app["val"]["discordUserPermission"]);
                                if($flag){// если проверка пройдена
                                }else $error = 76;
                            };
                            // удаляем записи событий
                            if(empty($error)){// если нет проблем
                                $index = 0;// счётчик количества удалённых записей
                                for($i = $events->length - 1; $i > - 1 and empty($status); $i--){
                                    $id = $events->key($i);// получаем ключевой идентификатор по индексу
                                    $event = $events->get($id);// получаем элемент по идентификатору
                                    // дополняем список вспомогательных флагов
                                    $flags["is-this-date"] = (empty($command["date"]) or $app["fun"]["dateFormat"]("d.m.Y", $event["time"], $timezone) == $app["fun"]["dateFormat"]("d.m.Y", $command["date"], $timezone));
                                    $flags["is-this-time"] = (empty($command["time"]) or $app["fun"]["dateFormat"]("H:i", $event["time"], $timezone) == $app["fun"]["dateFormat"]("H:i", $command["time"], $timezone));
                                    $flags["is-this-raid"] = (empty($command["raid"]) or $event["raid"] == $command["raid"]);
                                    $flags["is-this-user"] = (!$user or $event["user"] == $user["id"]);
                                    // проверяем ограничения по времени записи
                                    $flag = ($event["time"] >= $timestamp + $app["val"]["eventTimeClose"]);// есть ли необходимые права для выполнения действия
                                    if(!$flag) $permission = $app["fun"]["getPermission"]("channel", $author["id"], $channel["id"], $guild["id"]);
                                    $flag = ($flag or ($permission & $app["val"]["discordUserPermission"]) == $app["val"]["discordUserPermission"]);
                                    if(// множественное условие
                                        $event["channel"] == $channel["id"]
                                        and $event["guild"] == $guild["id"]
                                        and $flags["is-this-date"]
                                        and $flags["is-this-time"]
                                        and $flags["is-this-raid"]
                                        and $flags["is-this-user"]
                                        and $flag
                                    ){// если нужно удалить запись из событий
                                        if($events->set($id)){// если данные успешно удалены
                                            $isEventsUpdate = true;// были обновлены данные в базе данных
                                            $flag = (!$user or empty($current) or $current["user"] == $user["id"]);
                                            if($flag) $isConsolidatedChange = true;// изменилось ли сводное расписание
                                            $index++;// увиличиваем счётчик количества удалённых записей
                                        }else $status = 309;// не удалось записать данные в базу данных
                                    };
                                };
                                if($index){// если удалены записи
                                }else $error = 21;
                            };
                            break;
                        case "":// команда не указана
                            if($hasTalkPermission){// если обсуждения разрешены
                                $isNeedProcessing = false;// требуется ли дальнейшая обработка                                
                                break;
                            };
                        default:// не известная команда
                            $error = 11;
                    };
                };
                // информируем пользователя
                if(!empty($error) and $timestamp > $now - $app["val"]["eventTimeDelete"]){// если нужно уведомить
                    // готовим контент для личного сообщения
                    if(empty($status)){// если нет ошибок
                        $feedback = $feedbacks->get($error);// получаем элемент по идентификатору
                        if(!empty($feedback)) $content = template($feedback[$language], $command);
                        if(empty($content)) $content = "Ваше сообщение не обработано из-за непредвиденной проблемы.";
                    };
                    // получаем идентификатор личного канала
                    if(empty($status)){// если нет ошибок
                        $user = $app["fun"]["getCache"]("user", $author["id"]);
                        if($user and !$user["bot"] and isset($user["channels"][0])){// если личный канал существует
                            $item = $user["channels"][0];// получаем очередной элемент
                            // отправляем личное сообщение
                            $uri = "/channels/" . $item["id"] . "/messages";
                            $data = array("content" => $content);
                            $data = $app["fun"]["apiRequest"]("post", $uri, $data, $code);
                            if(200 == $code or 403 == $code){// если запрос выполнен успешно
                            }else $status = 306;// не удалось получить корректный ответ от удаленного сервера
                        };
                    };
                };
            };
            // формируем текст для уведомлений
            if(empty($status) and ($hasMainPermission or $hasListPermission)){// если нужно выполнить
                $blank = "_ _";// не удаляемый пустой символ
                $delim = "\n";// разделитель строк
                $lines = array();// список строк
                $blocks = array();// список блоков
                $contents = array();// контент для сообщений
                // формируем список записей
                $index = 0;// индекс элемента в новом массиве
                $items = array();// список записей событий
                for($i = 0, $iLen = $events->length; $i < $iLen; $i++){
                    // обробатываем каждую запись
                    $id = $events->key($i);// получаем ключевой идентификатор по индексу
                    $item = $events->get($id);// получаем элемент по идентификатору
                    if($item["guild"] == $guild["id"]){// если нужно выполнить дополнительные проверки
                        // проверяем разрешения для сводного расписания
                        $flag = !$isConsolidated;// есть ли необходимые права для выполнения действия
                        if(!$flag) $permission = $app["fun"]["getPermission"]("member", $app["val"]["discordBotId"], $item["channel"], $guild["id"]);
                        $flag = ($flag or ($permission & $app["val"]["discordListPermission"]) == $app["val"]["discordListPermission"]);
                        if($isConsolidated ? $flag : $item["channel"] == $channel["id"]){// если нужно включить запись в уведомление
                            // проверяем доступность этой роли в рейде
                            $raid = $raids->get($item["raid"]);
                            $limit = $raid[$item["role"]];
                            if($limit > -1){// если эта роль доступна в рейде
                                // сохраняем элемент в массив
                                $items[$index] = $item;
                                $index++;
                            };
                        };
                    };
                };
                // сортируем список записей для обработки
                usort($items, function($a, $b){// сортировка
                    $value = 0;// начальное значение
                    if(!$value and $a["accept"] != $b["accept"]) $value = $b["accept"] ? 1 : -1;
                    if(!$value and $a["id"] != $b["id"]) $value = $a["id"] > $b["id"] ? 1 : -1;
                    // возвращаем результат
                    return $value;
                });
                // обрабатываем список записей
                $all = "";// идентификатор всех ролей
                $any = 0;// идентификатор любой группы
                $limits = array();// лимиты для рейдов
                $counts = array();// счётчики для групп
                $leaders = array();// лидеры для групп
                $comments = array();// комментарии для групп
                $repeats = array();// повторяемость события
                $length = 0;// колличество рейдов в списке
                for($i = 0, $iLen = count($items); $i < $iLen; $i++){
                    $item = $items[$i];// получаем очередной элемент
                    $unit = $events->get($item["id"]);
                    $raid = $raids->get($item["raid"]);
                    // создаём структуру счётчика
                    $count = &$counts;// счётчик элементов
                    foreach(array($item["channel"], $item["time"], $item["raid"], $any) as $key){
                        if(!isset($count[$key])) $count[$key] = array();
                        $count = &$count[$key];// получаем ссылку
                    };
                    // считаем без учётом группы
                    if(!isset($count[$item["role"]])) $count[$item["role"]] = 0;
                    if(!isset($count[$all])) $count[$all] = 0;
                    if(!$count[$all]) $length++;
                    $count[$item["role"]]++;
                    $count[$all]++;
                    // вычисляем лимиты для счётчика
                    if(!isset($limits[$item["raid"]])){// если нужно вычислить общий лимит
                        $limits[$item["raid"]] = 0;// начальное значение лимита
                        for($limit = 1, $j = 0, $jLen = $roles->length; $j < $jLen and $limit; $j++){
                            $role = $roles->get($roles->key($j));// получаем очередную роль
                            $limit = $raid[$role["key"]];// получаем значение лимита
                            if($limit > 0) $limits[$item["raid"]] += $limit;
                            if(!$limit) $limits[$item["raid"]] = 0;
                        };
                    };
                    // определяем группу
                    $limit = $raid[$item["role"]];
                    $group = $limit ? ceil($count[$item["role"]] / $limit) : 1;
                    // создаём структуру счётчика
                    $count = &$counts;// счётчик элементов
                    foreach(array($item["channel"], $item["time"], $item["raid"], $group) as $key){
                        if(!isset($count[$key])) $count[$key] = array();
                        $count = &$count[$key];// получаем ссылку
                    };
                    // считаем с учётом группы
                    if(!isset($count[$item["role"]])) $count[$item["role"]] = 0;
                    if(!isset($count[$all])) $count[$all] = 0;
                    $count[$item["role"]]++;
                    $count[$all]++;
                    // создаём структуру лидера
                    $leader = &$leaders;// лидер по группам
                    foreach(array($item["channel"], $item["time"], $item["raid"]) as $key){
                        if(!isset($leader[$key])) $leader[$key] = array();
                        $leader = &$leader[$key];// получаем ссылку
                    };
                    // определяем лидера группы
                    if(!isset($leader[$group])) $leader[$group] = $i;
                    $limit = $limits[$item["raid"]];// получаем значение лимита
                    $index = $leader[$group];// порядковый номер лидера
                    if($i != $index){// если лидер не текущий элемент
                        if(!$items[$index]["leader"]){// если лидер выбран системой
                            if($item["leader"]) $leader[$group] = $i;
                            else if($count[$all] == $limit) $items[$index]["leader"] = true;
                        }else if($item["leader"]) $item["leader"] = false;
                    }else if($count[$all] == $limit) $item["leader"] = true;
                    // создаём структуру комментария
                    $comment = &$comments;// комментарий по группам
                    foreach(array($item["channel"], $item["time"], $item["raid"]) as $key){
                        if(!isset($comment[$key])) $comment[$key] = array();
                        $comment = &$comment[$key];// получаем ссылку
                    };
                    // определяем комментарий группы
                    if(!isset($comment[$any])) $comment[$any] = "";
                    if(!isset($comment[$group])) $comment[$group] = "";
                    if($item["leader"] and $unit["leader"]) $comment[$group] = $item["comment"];
                    if(1 == $group) $comment[$any] = $comment[$group];
                    // создаём структуру повторения события
                    $repeat = &$repeats;// повторяемость по группам
                    foreach(array($item["channel"], $item["time"], $item["raid"]) as $key){
                        if(!isset($repeat[$key])) $repeat[$key] = array();
                        $repeat = &$repeat[$key];// получаем ссылку
                    };
                    // определяем повторяемость события
                    if(!isset($repeat[$any])) $repeat[$any] = false;
                    if(!isset($repeat[$group])) $repeat[$group] = false;
                    if($item["repeat"]) $repeat[$any] = true;
                    if($item["repeat"]) $repeat[$group] = true;
                    // расширяем свойства элемента
                    $item["title"] = $app["fun"]["dateFormat"]("d", $item["time"], $timezone) . " " . $months->get($app["fun"]["dateFormat"]("n", $item["time"], $timezone), $language);
                    $item["day"] = mb_ucfirst(mb_strtolower($dates->get(mb_strtolower($app["fun"]["dateFormat"]("l", $item["time"], $timezone)), $language)));
                    $item["group"] = $group;
                    // сохраняем элемент в массив
                    $items[$i] = $item;
                };
                // заменяем номер лидера на идентификатор пользователя
                for($i = 0, $iLen = count($items); $i < $iLen; $i++){
                    $item = $items[$i];// получаем очередной элемент
                    $group = $item["group"];
                    // получаем лидера из структуры
                    $leader = &$leaders;// лидер по группам
                    foreach(array($item["channel"], $item["time"], $item["raid"]) as $key){
                        $leader = &$leader[$key];// получаем ссылку
                    };
                    // выполняем замену
                    $index = $leader[$group];// порядковый номер лидера
                    if($index == $i){// если лидет текущий элимент
                       $leader[$group] = $item["leader"] ? $item["user"] : 0;
                    };
                };
                // сортируем список записей для отображения
                usort($items, function($a, $b){// сортировка
                    $value = 0;// начальное значение
                    if(!$value and $a["time"] != $b["time"]) $value = $a["time"] > $b["time"] ? 1 : -1;
                    if(!$value and $a["channel"] != $b["channel"]) $value = $a["channel"] > $b["channel"] ? 1 : -1;
                    if(!$value and $a["raid"] != $b["raid"]) $value = $a["raid"] > $b["raid"] ? 1 : -1;
                    if(!$value and $a["group"] != $b["group"]) $value = $a["group"] > $b["group"] ? 1 : -1;
                    if(!$value and $a["accept"] != $b["accept"]) $value = $b["accept"] ? 1 : -1;
                    if(!$value and $a["role"] != $b["role"]) $value = $a["role"] < $b["role"] ? 1 : -1;
                    if(!$value and $a["id"] != $b["id"]) $value = $a["id"] > $b["id"] ? 1 : -1;
                    // возвращаем результат
                    return $value;
                });
                // формируем контент для уведомлений
                $before = null;// предыдущий элемент
                $mLen = 0;// длина текущего уведомления
                $bLen = 0;// длина текущего блока
                $line = "";// сбрасываем значение строки
                $position = 0;// позиция пользователя в рейде
                $index = 0;// сбрасываем номер события в списке
                if(count($items)){// если есть элементы для отображения
                    for($i = 0, $iLen = count($items); $i < $iLen; $i++){
                        $item = $items[$i];// получаем очередной элемент
                        $raid = $raids->get($item["raid"]);
                        $role = $roles->get($item["role"]);
                        $type = $types->get($raid["type"]);
                        $chapter = $chapters->get($raid["chapter"]);
                        $limit = $limits[$item["raid"]];
                        $group = $item["group"];
                        // определяем номер события
                        if(// множественное условие
                            empty($before)
                            or $before["time"] != $item["time"]
                            or $before["channel"] != $item["channel"]
                            or $before["raid"] != $item["raid"]
                        ){// если нужно увеличить номер события
                            $index++;
                        };
                        // построчно формируем текст содержимого
                        if(// множественное условие
                            empty($before)
                            or $before["title"] != $item["title"]
                        ){// если нужно добавить информацию о дате
                            // формируем блок и контент    
                            $bLen = 0;// длина текущего блока
                            $flag = count($lines);
                            if($flag){// если сформирован блок
                                if($mLen) $mLen += mb_strlen($delim);
                                $block = implode($delim, $lines);
                                $bLen = mb_strlen($block);
                            };
                            if(// множественное условие
                                count($blocks)
                                and (// дополнительное условие
                                    !$isConsolidated
                                    or $mLen + $bLen > $app["val"]["discordMessageLength"]
                                )
                            ){// если сформирован контент
                                $content = implode($delim, $blocks);
                                array_push($contents, $content);
                                $blocks = array();
                                $mLen = 0;
                            };
                            if($flag){// если сформирован блок
                                array_push($blocks, $block);
                                $mLen += $bLen;
                                $lines = array();
                            };
                            // формируем строки данных
                            if(!empty($before)){
                                $line = $blank;// пустая строка
                                array_push($lines, $line);
                            };
                            $line = "**```";
                            array_push($lines, $line);
                            $line = $additions->get("daily", "icon") . " " . $item["title"] . " - " . $item["day"];
                            array_push($lines, $line);
                            $line = "```**";
                            array_push($lines, $line);
                        };
                        if(// множественное условие
                            empty($before)
                            or $before["group"] != $group
                            or $before["time"] != $item["time"]
                            or $before["channel"] != $item["channel"]
                            or $before["raid"] != $item["raid"]
                        ){// если нужно добавить информацию о времени
                            // получаем счётчик из структуру
                            $count = &$counts;// счётчик элементов
                            foreach(array($item["channel"], $item["time"], $item["raid"], $group) as $key){
                                $count = &$count[$key];// получаем ссылку
                            };
                            // получаем комментарий из структуру
                            $comment = &$comments;// комментарий по группам
                            foreach(array($item["channel"], $item["time"], $item["raid"]) as $key){
                                $comment = &$comment[$key];// получаем ссылку
                            };
                            // получаем повторяемость из структуру
                            $repeat = &$repeats;// повторяемость по группам
                            foreach(array($item["channel"], $item["time"], $item["raid"]) as $key){
                                $repeat = &$repeat[$key];// получаем ссылку
                            };
                            // получаем лидера из структуры
                            $leader = &$leaders;// лидер по группам
                            foreach(array($item["channel"], $item["time"], $item["raid"]) as $key){
                                $leader = &$leader[$key];// получаем ссылку
                            };
                            // формируем блок и контент    
                            $bLen = 0;// длина текущего блока
                            $flag = (count($lines) and $before["title"] == $item["title"]);
                            $flag = ($flag and 1 == $group);
                            if($flag){// если сформирован блок
                                if($mLen) $mLen += mb_strlen($delim);
                                $block = implode($delim, $lines);
                                $bLen = mb_strlen($block);
                            };
                            if(// множественное условие
                                count($blocks)
                                and (// дополнительное условие
                                    !$isConsolidated
                                    or $mLen + $bLen > $app["val"]["discordMessageLength"]
                                )
                            ){// если сформирован контент
                                $content = implode($delim, $blocks);
                                array_push($contents, $content);
                                $blocks = array();
                                $mLen = 0;
                            };
                            if($flag){// если сформирован блок
                                array_push($blocks, $block);
                                $mLen += $bLen;
                                $lines = array();
                            };
                            // формируем строки данных
                            $flag = (1 == $group or $limit and $count[$all] == $limit and !$isConsolidated);
                            if($flag){// если это основная или другая полная группа
                                if(!empty($before) and $before["title"] == $item["title"] and !$isConsolidated){
                                    $line = $blank;// пустая строка
                                    array_push($lines, $line);
                                };
                                $value = mb_ucfirst($app["fun"]["strimStrMulti"]($comment[$any], "."));
                                $line = (!$isConsolidated ? (($limit and $count[$all] < $limit) ? $additions->get("once", "icon") : $type["icon"]) . " " : "");
                                $line .= "**" . $app["fun"]["dateFormat"]("H:i", $item["time"], $timezone) . "**" . ($isConsolidated ? " —" . $type["logotype"] : " - ") . "**" . $raid["key"] . "**";
                                $line .= (($isConsolidated and !empty($leader[$group])) ? " " . $additions->get("leader", "icon") . "<@!" . $leader[$group] . ">"  : "");
                                $line .= (" " . (($isConsolidated and mb_strlen($value)) ? $value : $raid[$language])) . (!empty($chapter[$language]) ? " **DLC " . $chapter[$language] . "**" : "");
                                $line .= (($limit and !$isConsolidated) ? " (" . $count[$all] . " " . $names->get("from", $language) . " " . $limit . ")" : "");
                                $line .= ((1 == $group and $repeat[$any] and !$isConsolidated) ? "  " . $additions->get("weekly", "icon") : "");
                                $line .= ($isConsolidated ? " <#" . $item["channel"] . ">" : "");
                                array_push($lines, $line);
                                $position = 1;
                            }else if((2 == $group or $before["count"] == $limit) and !$isConsolidated){// если это не полная группа
                                $line = "__" . $names->get("reserve", $language) . ":__";
                                array_push($lines, $line);
                            };
                            if($flag and !empty($comment[$any]) and !$isConsolidated){// если есть комментарий
                                $value = mb_ucfirst($comment[$any]);// комментарий к рейду
                                $line = "__" . $names->get("comment", $language) . "__: " . $value;
                                array_push($lines, $line);
                            };
                            // формируем подсказку для записи
                            if($flag and !$isConsolidated){// если это основная или другая полная группа
                                // формируем ассоциативный массив ролей
                                $list = array();// ассоциативный массив идентификаторов ролей
                                for($j = 0, $jLen = $roles->length; $j < $jLen; $j++){
                                    $key = $roles->key($j);// получаем идентификатор роли
                                    $value = isset($count[$key]) ? $raid[$key] - $count[$key] : $raid[$key];
                                    if($raid[$key] > -1) $list[$key] = $value;
                                };
                                // изменем ассоциативный массив ролей
                                $jLen = 0;// сбрасываем значение
                                foreach($list as $key => $value){// пробигаемся по ролям
                                    $value = $roles->get($key, "icon") . " +" . mb_strtolower($roles->get($key, $language));
                                    if($length > 1) $value .= " " . str_pad($index, 3, "0", STR_PAD_LEFT);
                                    $list[$key] = "**" . $value . "**";
                                    $jLen++;// увиличиваем значение
                                };
                                // объединяем ассоциативный массив ролей
                                $line = "";// сбрасываем значение
                                $j = 0;// порядковый номер роли в списке
                                foreach($list as $key => $value){// пробигаемся по ролям
                                    $key = $j ? ($j == $jLen - 1 ? " " . $names->get("or", $language) . " " : ", ") : "";
                                    $line .= $key . $value;
                                    $j++;// увиличиваем значение
                                };
                                // добавляем отдельной строкой
                                $line = $names->get("entry", $language) . " " . $line;
                                array_push($lines, $line);
                            };
                        };
                        // отделяем согласованных
                        if(// множественное условие
                            !empty($before) and !$isConsolidated
                            and (1 == $group or $limit and $count[$all] == $limit)
                            and $before["accept"] and !$item["accept"]
                            and $before["time"] == $item["time"]
                            and $before["channel"] == $item["channel"]
                            and $before["raid"] == $item["raid"]
                            and $before["group"] == $group
                        ){// если нужно отделить согласованных
                            $line = "__" . $names->get("candidate", $language) . ":__";
                            array_push($lines, $line);
                        };
                        // формируем строки данных
                        if(!$isConsolidated){// если это не сводное расписание
                            $flag = (1 == $group or $limit and $count[$all] == $limit);
                            $icon = $item["accept"] ? $additions->get("accept", "icon") : null;
                            $value = $names->get($role["key"], $language);//  получаем альтернативное имя
                            $line = "**`" . str_pad($position, 2, "0", STR_PAD_LEFT) . "`** - " . mb_ucfirst(mb_strtolower(!empty($value) ? $value : $role[$language]));
                            $line .= ": " . (($item["leader"] and $flag) ? $additions->get("leader", "icon") : "") . "<@!" . $item["user"] . ">" . ($icon ? $icon : "");
                            $key = mb_strtolower($additions->get("leader", $language));// идентификатор обозначающий лидера
                            $value = $item["comment"];// комментарий для обработки
                            for($j = -1, $jLen = mb_strlen($key); $j !== false; $j = mb_stripos($value, $key)){
                                if($j > -1) $value = mb_substr($value, 0,  $j) .  mb_substr($value, $j + $jLen);
                            };
                            $value = trim(mb_substr($value, 0, $app["val"]["eventNoteLength"]));
                            if(mb_strtoupper($value) != $value) $value = mb_lcfirst($value);
                            if($item["leader"] and $flag) $line .= (!$icon ? " " : "") . "- " . $key;
                            else if($value) $line .= (!$icon ? " " : "") . "- " . $value;
                            array_push($lines, $line);
                            $position++;
                        };
                        // сохраняем предыдущий элемент
                        $before = $item;// копируем значение
                        $before["count"] = $count[$all];
                    };
                }else{// если нет не одной записи
                    $line = $names->get("empty", $language);
                    array_push($lines, $line);
                };
                // формируем блок и контент    
                $bLen = 0;// длина текущего блока
                $flag = count($lines);
                if($flag){// если сформирован блок
                    if($mLen) $mLen += mb_strlen($delim);
                    $block = implode($delim, $lines);
                    $bLen = mb_strlen($block);
                };
                if(// множественное условие
                    count($blocks)
                    and (// дополнительное условие
                        !$isConsolidated
                        or $mLen + $bLen > $app["val"]["discordMessageLength"]
                    )
                ){// если сформирован контент
                    $content = implode($delim, $blocks);
                    array_push($contents, $content);
                    $blocks = array();
                    $mLen = 0;
                };
                if($flag){// если сформирован блок
                    array_push($blocks, $block);
                    $mLen += $bLen;
                    $lines = array();
                };
                // формируем контент
                if(// множественное условие
                    count($blocks)
                ){// если сформирован контент
                    $content = implode($delim, $blocks);
                    array_push($contents, $content);
                    $blocks = array();
                    $mLen = 0;
                };
            };
            // обрабатываем все сообщения бота
            if(empty($status) and ($hasMainPermission or $hasListPermission)){// если нужно выполнить
                $items = array();// массив сообщений для построения расписания
                $count = 0;// счётчик уже опубликованных сообщений бота
                // формируем список контентных сообщений бота и удаляем прочие сообщения бота
                for($i = count($channel["messages"]) - 1; $i > -1 and empty($status); $i--){
                    $item = $channel["messages"][$i];// получаем очередной элемент
                    $flag = (!$isExtCommand and (!$isNeedProcessing or $message["id"] != $item["id"]));
                    if($item["author"]["id"] == $app["val"]["discordBotId"]){// если это сообщение бота
                        if($item["type"]){// если это не контентное сообщение
                            // удаляем сообщение
                            $uri = "/channels/" . $channel["id"] . "/messages/" . $item["id"];
                            $data = $app["fun"]["apiRequest"]("delete", $uri, null, $code);
                            if(204 == $code or 404 == $code){// если запрос выполнен успешно
                                $app["fun"]["delCache"]("message", $item["id"], $channel["id"], $guild["id"]);
                            }else $status = 306;// не удалось получить корректный ответ от удаленного сервера
                        }else{// если это контентное сообщение
                            // добавляем сообщение в список
                            array_push($items, $item);// добавляем начало списка
                            $count++;// увеличиваем счётчик сообщений
                        };
                    }else if($flag){// если это не текущее сообщение
                        // добавляем пустой элемент в список
                        array_push($items, null);// добавляем в начало списка
                    };
                };
                // удаляем лишнии элементы из сформированного списка 
                $flag = false;// нужно ли удалить сообщение
                $index = 0;// текущее значение проверенных не пустых сообщений
                $after = count($contents) > $count;// слещующее сообщение в списке
                $time = $after ? $now : 0;// время более нового сообщения
                for($i = count($items) - 1; $i > -1 and empty($status); $i--){
                    $item = $items[$i];// получаем очередной элемент
                    $flag = ($flag or empty($item) and !empty($after));
                    if(!empty($item)){// если присутствует сообщение для обработки
                        $flag = ($flag or $time - $item["timestamp"] > $app["val"]["discordMessageTime"]);
                        if($flag or $index >= count($contents)){// если нужно удалить сообщение
                            // удаляем сообщение
                            $uri = "/channels/" . $channel["id"] . "/messages/" . $item["id"];
                            $data = $app["fun"]["apiRequest"]("delete", $uri, null, $code);
                            if(204 == $code or 404 == $code){// если запрос выполнен успешно
                                $app["fun"]["delCache"]("message", $item["id"], $channel["id"], $guild["id"]);
                                array_splice($items, $i, 1);
                                $count--;// уменьшаем счётчик сообщений
                            }else $status = 306;// не удалось получить корректный ответ от удаленного сервера
                        }else{// если не нужно удалять сообщение
                            $time = $item["timestamp"];// копируем время
                            $after = $item;// копируем элемент
                            $index++;// увеличиваем индекс
                        };
                    }else{// если присутствует пустой элемент
                        $after = $item;// копируем элемент
                        array_splice($items, $i, 1);
                    };
                };
            };
            // создаём новые или изменяем имеющиеся сообщения бота
            if(empty($status) and ($hasMainPermission or $hasListPermission)){// если нужно выполнить
                for($i = 0, $iLen = count($contents); $i < $iLen and empty($status); $i++){
                    $content = $contents[$i];// получаем очередной элемент
                    $item = isset($items[$i]) ? $items[$i] : null;
                    if(empty($item)){// если нужно опубликовать новое сообщение
                        // отправляем новое сообщение
                        $uri = "/channels/" . $channel["id"] . "/messages";
                        $data = array("content" => $content);
                        $data = $app["fun"]["apiRequest"]("post", $uri, $data, $code);
                        if(200 == $code){// если запрос выполнен успешно
                            $data["reactions"] = array();// приводим к единому виду
                            $item = $app["fun"]["setCache"]("message", $data, $channel["id"], $guild["id"]);
                        }else $status = 306;// не удалось получить корректный ответ от удаленного сервера
                    }else if($item["content"] != $content){// если нужно изменить старое сообщение
                        // изменяем старое сообщение
                        $uri = "/channels/" . $channel["id"] . "/messages/" . $item["id"];
                        $data = array("content" => $content);
                        $data = $app["fun"]["apiRequest"]("patch", $uri, $data, $code);
                        if(200 == $code){// если запрос выполнен успешно
                            $app["fun"]["setCache"]("message", $data, $channel["id"], $guild["id"]);
                            $item = $app["fun"]["getCache"]("message", $data["id"], $channel["id"], $guild["id"]);
                        }else $status = 306;// не удалось получить корректный ответ от удаленного сервера
                    }else $item = $app["fun"]["getCache"]("message", $item["id"], $channel["id"], $guild["id"]);
                    // обрабатываем реакций для сообщения
                    if(empty($status)){// если нет ошибок
                        // формируем список идентификаторов реакций для обработки
                        $list = array();// список идентификаторов реакций для обработки
                        for($j = 0, $jLen = count($item["reactions"]); $j < $jLen; $j++){
                            $reaction = $item["reactions"][$j];// получаем очередной элемент
                            $rid = array($reaction["user"]["id"], $reaction["emoji"]["name"], $reaction["emoji"]["id"]);
                            array_unshift($list, $rid);// добавляем в список
                        };
                        // обрабатываем список идентификаторов реакций
                        for($j = 0, $jLen = count($list); (!$j or $j < $jLen) and empty($status); $j++){// пробигаемся по списку идентификаторов реакций
                            $reaction = $jLen ? $app["fun"]["getCache"]("reaction", $list[$j], $item["id"], $channel["id"], $guild["id"]) : null;
                            $rid = $reaction ? array($reaction["user"]["id"], $reaction["emoji"]["name"], $reaction["emoji"]["id"]) : null;
                            // обрабатываем реакцию
                            if(!$jLen or $reaction){// если очередной элемент существует
                                $flag = $app["method"]["discord.reaction"](
                                    array(// параметры для метода
                                        "reaction" => $reaction ? implode(":", $rid) : null,
                                        "message" => $item["id"],
                                        "channel" => $channel["id"],
                                        "guild" => $guild["id"]
                                    ),
                                    array(// внутренние опции
                                        "nocontrol" => true
                                    ),
                                    $sign, $status
                                );
                                $isEventsUpdate = $isEventsUpdate || $flag;
                            };
                        };
                    };
                };
            };
            // удаляем сообщение переданное на обработку
            if(empty($status) and $isNeedProcessing and $hasMainPermission){// если нужно выполнить
                // удаляем сообщение
                $uri = "/channels/" . $channel["id"] . "/messages/" . $message["id"];
                $data = $app["fun"]["apiRequest"]("delete", $uri, null, $code);
                if(204 == $code or 404 == $code){// если запрос выполнен успешно
                    $app["fun"]["delCache"]("message", $message["id"], $channel["id"], $guild["id"]);
                }else $status = 306;// не удалось получить корректный ответ от удаленного сервера
            };
            // обновляем сводное расписание в гильдии
            if(empty($status) and $isConsolidatedChange and !$isConsolidated){// если нужно выполнить
                $flag = $app["method"]["discord.guild"](
                    array(// параметры для метода
                        "guild" => $guild["id"]
                    ),
                    array(// внутренние опции
                        "nocontrol" => true,
                        "consolidated" => true
                    ),
                    $sign, $status
                );
                $isEventsUpdate = $isEventsUpdate || $flag;
            };
            // сохраняем базу данных событий
            if(isset($events) and !empty($events)){// если база данных загружена
                if(!get_val($options, "nocontrol", false)){// если это прямой вызов
                    if(empty($status) and $isEventsUpdate){// если нужно выполнить
                        if($events->save(false)){// если данные успешно сохранены
                        }else $status = 307;// не удалось сохранить базу данных
                    }else $events->unlock();// разблокируем базу
                };
            };
            // возвращаем результат
            $result = $isEventsUpdate;
            return $result;
        },
        "discord.reaction" => function($params, $options, $sign, &$status){// обрабатываем реарцию
        //@param $params {array} - массив внешних не отфильтрованных значений
        //@param $options {array} - массив внутренних настроек
        //@param $sign {boolean|null} - успешность проверки подписи или null при её отсутствии
        //@param $status {number} - целое число статуса выполнения
        //@return {true|false} - были ли изменения базы событий
            global $app; $result = null;
            
            $error = 0;// код ошибки для обратной связи
            $now = microtime(true);// текущее время
            $isEventsUpdate = false;// были ли обновлены данные в базе данных
            $hasMainPermission = false;// есть основные разрешения
            // получаем очищенные значения параметров
            $token = $app["fun"]["getClearParam"]($params, "token", "string");
            $guild = $app["fun"]["getClearParam"]($params, "guild", "string");
            $channel = $app["fun"]["getClearParam"]($params, "channel", "string");
            $message = $app["fun"]["getClearParam"]($params, "message", "string");
            $reaction = $app["fun"]["getClearParam"]($params, "reaction", "string");
            $app["fun"]["setDebug"](6, "discord.reaction", $guild, $channel, $message, $reaction);// отладочная информация
            // проверяем корректность указанных параметров
            if(empty($status)){// если нет ошибок
                if((!is_null($token) and !is_null($guild) and !is_null($channel) and !is_null($message) and !is_null($reaction)) or get_val($options, "nocontrol", false)){// если указаны обязательные поля
                    if((!empty($token) and !empty($guild) and !empty($channel) and !empty($message) and !empty($reaction)) or get_val($options, "nocontrol", false)){// если обязательные поля успешно отфильтрованы
                        if($token == $app["val"]["appToken"] or get_val($options, "nocontrol", false)){// если прошли проверку
                        }else $status = 303;// переданные параметры не верны
                    }else $status = 302;// один из обязательных параметров передан в неверном формате
                }else $status = 301;// не передан один из обязательных параметров
            };
            // загружаем все необходимые базы данных
            if(empty($status)){// если нет ошибок
                $events = $app["fun"]["getStorage"]("events", true);
                if(!empty($events)){// если удалось получить доступ к базе данных
                }else $status = 304;// не удалось загрузить одну из многих баз данных
            };
            if(empty($status)){// если нет ошибок
                $raids = $app["fun"]["getStorage"]("raids", false);
                if(!empty($raids)){// если удалось получить доступ к базе данных
                }else $status = 304;// не удалось загрузить одну из многих баз данных
            };
            if(empty($status)){// если нет ошибок
                $roles = $app["fun"]["getStorage"]("roles", false);
                if(!empty($roles)){// если удалось получить доступ к базе данных
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
                    // проверяем разрешения
                    $permission = $app["fun"]["getPermission"]("member", $app["val"]["discordBotId"], $channel, $guild["id"]);
                    $hasMainPermission = ($permission & $app["val"]["discordMainPermission"]) == $app["val"]["discordMainPermission"];
                }else $status = 303;// переданные параметры не верны
            };
            // получаем информацию о сообщении
            if(empty($status)){// если нет ошибок
                $message = $app["fun"]["getCache"]("message", $message, $channel["id"], $guild["id"]);
                if($message){// если удалось получить данные
                }else $status = 303;// переданные параметры не верны
            };
            // получаем информацию о реакции
            if(empty($status) and !empty($reaction)){// если нужно выполнить
                $reaction = $app["fun"]["getCache"]("reaction", explode(":", $reaction), $message["id"], $channel["id"], $guild["id"]);
                if($reaction){// если удалось получить данные
                }else $status = 303;// переданные параметры не верны
            };
            // выполняем регламентные операции с базой событий
            if(empty($status) and !get_val($options, "nocontrol", false)){// если нужно выполнить
                $flag = count($app["fun"]["changeEvents"]($now, $status)) > 0;
                $isEventsUpdate = $isEventsUpdate || $flag;
            };
            // определяем подходящую запись по номеру сообщения
            if(empty($status)){// если нет ошибок
                // формируем список записей
                $items = array();// список записей
                for($i = 0, $iLen = $events->length; $i < $iLen; $i++){
                    $id = $events->key($i);// получаем ключевой идентификатор по индексу
                    $event = $events->get($id);// получаем элемент по идентификатору
                    if(// множественное условие
                        $event["channel"] == $channel["id"]
                        and $event["guild"] == $guild["id"]
                    ){// если нужно посчитать счётчик
                        $item = $event;// копируем элемент
                        array_push($items, $item);
                    };
                };
                // обрабатываем список записей
                for($i = 0, $iLen = count($items); $i < $iLen; $i++){
                    $item = $items[$i];// получаем очередной элемент
                    if($item["user"] == $reaction["user"]["id"]){
                    }else $items[$i]["id"] = 0;
                };
                // сортируем список записей
                usort($items, function($a, $b){// сортировка
                    $value = 0;// начальное значение
                    if(!$value and $a["time"] != $b["time"]) $value = $a["time"] > $b["time"] ? 1 : -1;
                    if(!$value and $a["raid"] != $b["raid"]) $value = $a["raid"] > $b["raid"] ? 1 : -1;
                    if(!$value and $a["id"] != $b["id"]) $value = $a["id"] > $b["id"] ? 1 : -1;
                    // возвращаем результат
                    return $value;
                });
                // удаляем вторичные записи
                $after = null;// следующий элемент
                for($i = count($items) - 1; $i > -1; $i--){
                    $item = $items[$i];// получаем очередной элемент
                    if(// множественное условие
                        !empty($after)
                        and $item["time"] == $after["time"]
                        and $item["raid"] == $after["raid"]
                    ){// если нужно удалить запись
                        array_splice($items, $i, 1);
                    }else $after = $item;// копируем элемент
                };
                // ищем номер текущего сообщения бота в канале
                $flag = false;// найдено ли нужное сообщение
                $index = 0;// порядковый номер события в канале
                for($i = count($channel["messages"]) - 1; $i > -1 and !$flag; $i--){
                    $flag = ($flag or $channel["messages"][$i]["author"]["id"] == $app["val"]["discordBotId"]);
                    if($flag) $index++;// увеличиваем порядковый номер события в канале
                    $flag = ($flag and $channel["messages"][$i]["id"] == $message["id"]);
                };
                // определяем подходящую запись
                $current = null;// подходящая запись
                $i = $index - 1;// позиция записи в списке
                if(isset($items[$i])){// если запись существует
                    $item = $items[$i];// получаем очередной элемент
                    $current = $item;// задаём подходящую запись
                }else $current = false;// сбрасываем подходящую запись
            };
            // выполняем обработку реакции в сообщении
            if(empty($status) and $hasMainPermission and !empty($reaction)){// если нужно выполнить
                $command = array();// сбрасываем значение
                // номер - ID
                if(empty($error)){// если нет проблем
                    $key = "index";// текущий параметр команды
                    $flag = $current;// пройдена ли проверка значения
                    // проверяем текущий параметр
                    if($flag){// если проверка пройдена
                        $command[$key] = $index;
                    }else $error = 21;
                };
                // пользователь - ID
                if(empty($error)){// если нет проблем
                    $key = "user";// текущий параметр команды
                    $flag = !$reaction["user"]["bot"];
                    // проверяем текущий параметр
                    if($flag){// если проверка пройдена
                        $command[$key] = $reaction["user"]["id"];
                    }else $error = 61;
                };
                // роль - KEY
                if(empty($error)){// если нет проблем
                    $key = "role";// текущий параметр команды
                    $flag = false;// пройдена ли проверка значения
                    for($i = 0, $iLen = $roles->length; $i < $iLen and !$flag; $i++){
                        $role = $roles->get($roles->key($i));// получаем очередную роль
                        $emoji = $app["fun"]["getEmoji"]($role["icon"]);
                        if(!empty($emoji["id"])) $flag = $reaction["emoji"]["id"] == $emoji["id"];
                        else $flag = $reaction["emoji"]["name"] == $emoji["name"];
                    };
                    // проверяем текущий параметр
                    if($flag){// если проверка пройдена
                        $command[$key] = $role["key"];
                    }else $error = 13;
                };
                // действие - KEY
                if(empty($error)){// если нет проблем
                    $key = "action";// текущий параметр команды
                    if($current["user"] != $reaction["user"]["id"]) $command[$key] = "+";
                    else if($current["role"] != $role["key"]) $command[$key] = "*";
                    else $command[$key] = "-";
                };
                // проверяем необходимость сохранения реакции
                $flag = false;// нужно ли сохранить реакцию
                if(// множественное условие
                    !empty($current)
                    and $app["val"]["discordBotId"] == $reaction["user"]["id"]
                ){// если это реакция текущего бота
                    // проверяем доступность этой роли в рейде
                    for($i = 0, $iLen = $roles->length; $i < $iLen and !$flag; $i++){
                        $role = $roles->get($roles->key($i));// получаем очередную роль
                        $emoji = $app["fun"]["getEmoji"]($role["icon"]);
                        if(!empty($emoji["id"])) $flag = $reaction["emoji"]["id"] == $emoji["id"];
                        else $flag = $reaction["emoji"]["name"] == $emoji["name"];
                    };
                    if($flag){// если роль найдена
                        $raid = $raids->get($current["raid"]);
                        $limit = $raid[$role["key"]];
                        $flag = $limit > -1;
                    };
                };
                // удаляем реакцию
                if(!$flag){// если требуется удалить реакцию
                    $rid = array("", $reaction["emoji"]["name"], $reaction["emoji"]["id"]);
                    $value = urlencode(!empty($rid[2]) ? implode(":", $rid) : $rid[1]);
                    $uri = "/channels/" . $channel["id"] . "/messages/" . $message["id"] . "/reactions/" . $value . "/" . $reaction["user"]["id"];
                    $data = $app["fun"]["apiRequest"]("delete", $uri, null, $code);
                    $rid[0] = $reaction["user"]["id"];// идентификатор пользователя
                    if(204 == $code or 404 == $code){// если запрос выполнен успешно
                        $app["fun"]["delCache"]("reaction", $rid, $message["id"], $channel["id"], $guild["id"]);
                    }else $status = 306;// не удалось получить корректный ответ от удаленного сервера
                };
                // обробатываем команду
                if(empty($error) and empty($status)){// если нужно выполнить
                    $flag = $app["method"]["discord.message"](
                        array(// параметры для метода
                            "message" => null,
                            "channel" => $channel["id"],
                            "guild" => $guild["id"]
                        ),
                        array(// внутренние опции
                            "author" => $reaction["user"],
                            "command" => $command,
                            "nocontrol" => true
                        ),
                        $sign, $status
                    );
                    $isEventsUpdate = $isEventsUpdate || $flag;
                };
            };
            // выполняем подсчёт и добавление недостающих реакций
            if(empty($status) and $hasMainPermission){// если нужно выполнить
                $count = array();// счётчик ролей
                // подготавливаем счётчик ролей
                for($i = 0, $iLen = $roles->length; $i < $iLen; $i++){
                    $key = $roles->key($i);// подготавливаем очередной ключ
                    $count[$key] = 0;// сбрасываем счётчик
                };
                // последовательно счиаем роли по реакциям
                for($i = count($message["reactions"]) - 1; $i > -1 and empty($status); $i--){
                    $item = $message["reactions"][$i];// получаем очередной элемент
                    $flag = $app["val"]["discordBotId"] != $item["user"]["id"];
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
                if(!empty($current)){// если удалось определить подходящую запись
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
                            $item["user"] = array("id" => $app["val"]["discordBotId"], "bot" => true);
                            $rid = array("", $item["emoji"]["name"], $item["emoji"]["id"]);
                            $value = urlencode(!empty($rid[2]) ? implode(":", $rid) : $rid[1]);
                            $uri = "/channels/" . $channel["id"] . "/messages/" . $message["id"] . "/reactions/" . $value . "/@me";
                            $data = $app["fun"]["apiRequest"]("put", $uri, null, $code);
                            $rid[0] = $item["user"]["id"];// идентификатор пользователя
                            if(204 == $code){// если запрос выполнен успешно
                                $app["fun"]["setCache"]("reaction", $item, $message["id"], $channel["id"], $guild["id"]);
                            }else $status = 306;// не удалось получить корректный ответ от удаленного сервера
                        };
                    };
                };
            };
            // сохраняем базу данных событий
            if(isset($events) and !empty($events)){// если база данных загружена
                if(!get_val($options, "nocontrol", false)){// если это прямой вызов
                    if(empty($status) and $isEventsUpdate){// если нужно выполнить
                        if($events->save(false)){// если данные успешно сохранены
                        }else $status = 307;// не удалось сохранить базу данных
                    }else $events->unlock();// разблокируем базу
                };
            };
            // возвращаем результат
            $result = $isEventsUpdate;
            return $result;
        }
    ),
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
        "setCache" => function&($type, $data){// добавляет данные в кеш
        //@param $type {string} - тип данных для кеширования
        //@param $data {array} - данные в виде массива 
        //@param ...$id {string} - идентификаторы разных уровней
        //@return {array|null} - ссылка на элемент данныx или null
            global $app;
            $error = 0;
            
            $argLength = func_num_args();
            $argFirst = 2;// первый $id
            $unit = null;// промежуточная ссылка
            $cache = null;// окончательная ссылка
            switch($type){// поддерживаемые типы
                case "guild":// гильдия
                    // проверяем наличее данных
                    if(!$error){// если нет ошибок
                        if(// множественное условие
                            isset($data["id"])
                            and $argLength >= $argFirst
                        ){// если проверка пройдена
                            $gid = $data["id"];
                        }else $error = 2;
                    };
                    // проверяем значение данных
                    if(!$error){// если нет ошибок
                        if(// множественное условие
                            !empty($gid)
                        ){// если проверка пройдена
                        }else $error = 3;
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
                            if(!isset($parent[$key][$i])) $parent[$key][$i] = array();
                            $unit = &$parent[$key][$i];
                        }else $error = 4;
                    };
                    // формируем структуру
                    if(!$error){// если нет ошибок
                        foreach(array("members", "channels") as $key){
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
                        }else $error = 2;
                    };
                    // проверяем значение данных
                    if(!$error){// если нет ошибок
                        if(// множественное условие
                            !empty($uid)
                        ){// если проверка пройдена
                        }else $error = 3;
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
                            if(!isset($parent[$key][$i])) $parent[$key][$i] = array();
                            $unit = &$parent[$key][$i];
                        }else $error = 4;
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
                        }else $error = 2;
                    };
                    // проверяем значение данных
                    if(!$error){// если нет ошибок
                        if(// множественное условие
                            !empty($uid)
                            and !empty($gid)
                        ){// если проверка пройдена
                        }else $error = 3;
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
                        }else $error = 4;
                    };
                    // формируем структуру
                    if(!$error){// если нет ошибок
                        foreach(array("user", "roles") as $key){
                            if(isset($data[$key]) and !isset($unit[$key])){
                                $unit[$key] = array();
                            };
                        };
                    };
                    // обрабатываем данные
                    if(!$error){// если нет ошибок
                        // идентификатор пользователя
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
                        }else $error = 2;
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
                        }else $error = 3;
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
                        }else $error = 4;
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
                                $app["fun"]["setCache"]("message", $data[$key][$i], $cid, $gid);
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
                            and $argLength > $argFirst + 1
                        ){// если проверка пройдена
                            $mid = $data["id"];
                            $cid = func_get_arg($argFirst);
                            $gid = func_get_arg($argFirst + 1);
                        }else $error = 2;
                    };
                    // проверяем значение данных
                    if(!$error){// если нет ошибок
                        if(// множественное условие
                            !empty($mid)
                            and !empty($cid)
                            and !empty($gid)
                            and !empty($data["timestamp"])
                            and !empty($data["author"]["id"])
                        ){// если проверка пройдена
                        }else $error = 3;
                    };
                    // определяем ссылку на элемента
                    if(!$error){// если нет ошибок
                        $key = "messages";// задаём ключ
                        $parent = &$app["fun"]["getCache"]("channel", $cid, $gid, null);
                        if(!is_null($parent)){// если есть родительский элемент
                            $flag = false;// есть ли необходимые права для выполнения действия
                            if(!$flag) $permission = $app["fun"]["getPermission"]("member", $app["val"]["discordBotId"], $cid, $gid);
                            $flag = ($flag or ($permission & $app["val"]["discordMainPermission"]) == $app["val"]["discordMainPermission"]);
                            $flag = ($flag or ($permission & $app["val"]["discordListPermission"]) == $app["val"]["discordListPermission"]);
                            if($flag or isset($parent[$key])){// если есть разрешения или учёт уже ведётся
                                if(!isset($parent[$key])) $parent[$key] = array();
                                for($i = 0, $iLen = count($parent[$key]); $i < $iLen; $i++){
                                    if($parent[$key][$i]["id"] == $mid) break;
                                };
                                if(!isset($parent[$key][$i])) $parent[$key][$i] = array();
                                $unit = &$parent[$key][$i];
                            }else $error = 5;
                        }else $error = 4;
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
                        // идентификатор автора
                        $key = "id";// задаём ключ
                        if(isset($data["author"][$key])){// если существует
                            $unit["author"][$key] = $data["author"][$key];
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
                                    $item["user"] = array("id" => $app["val"]["discordBotId"], "bot" => true);
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
                        for($i = count($parent[$key]) - 1; $i >= 50; $i--){
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
                            and $argLength > $argFirst + 2
                        ){// если проверка пройдена
                            $rid = array(// составной идентификатор
                                $data["user"]["id"],
                                $data["emoji"]["name"],
                                get_val($data["emoji"], "id", null)
                            );
                            $mid = func_get_arg($argFirst);
                            $cid = func_get_arg($argFirst + 1);
                            $gid = func_get_arg($argFirst + 2);
                        }else $error = 2;
                    };
                    // проверяем значение данных
                    if(!$error){// если нет ошибок
                        if(// множественное условие
                            !empty($rid[0])
                            and !empty($rid[1])
                            and !empty($mid)
                            and !empty($cid)
                            and !empty($gid)
                        ){// если проверка пройдена
                        }else $error = 3;
                    };
                    // определяем ссылку на элемента
                    if(!$error){// если нет ошибок
                        $key = "reactions";// задаём ключ
                        $parent = &$app["fun"]["getCache"]("message", $mid, $cid, $gid);
                        if(!is_null($parent)){// если есть родительский элемент
                            $flag = $parent["author"]["id"] == $app["val"]["discordBotId"];
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
                            }else $error = 5;
                        }else $error = 4;
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
                        // идентификатор пользователя
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
                    $error = 1;
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
            
            $argLength = func_num_args();
            $argFirst = 1;// первый $id
            $unit = null;// промежуточная ссылка
            $cache = null;// окончательная ссылка
            switch($type){// поддерживаемые типы
                case "guild":// гильдия
                    // проверяем наличее параметров
                    if(!$error){// если нет ошибок
                        if(// множественное условие
                            $argLength > $argFirst
                        ){// если проверка пройдена
                            $gid = func_get_arg($argFirst);
                        }else $error = 2;
                    };
                    // проверяем значение данных
                    if(!$error){// если нет ошибок
                        if(// множественное условие
                            !empty($gid)
                        ){// если проверка пройдена
                        }else $error = 3;
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
                                    $data = $app["fun"]["getFileCache"]($key, $gid);
                                    if($data) $parent[$key][$i] = $data;
                                    if($data) $unit = &$parent[$key][$i];
                                };
                            }else $unit = &$parent[$key][$i];
                        }else $error = 4;
                    };
                    // получаем элемент через api
                    if(!$error and !$unit){// если нужно выполнить
                        $uri = "/guilds/" . $gid;
                        $data = $app["fun"]["apiRequest"]("get", $uri, null, $code);
                        if(200 == $code){// если запрос выполнен успешно
                            $unit = &$app["fun"]["setCache"]($type, $data);
                        }else $error = 5;
                    };
                    // получаем каналы через api
                    $key = "channels";// задаём ключ
                    if(!$error and $unit and !isset($unit[$key])){// если нужно выполнить
                        $uri = "/guilds/" . $gid . "/" . $key;
                        $data = $app["fun"]["apiRequest"]("get", $uri, null, $code);
                        if(200 == $code){// если запрос выполнен успешно
                            if(!isset($unit[$key])) $unit[$key] = array();
                            for($i = 0, $iLen = count($data); $i < $iLen; $i++){
                                $app["fun"]["setCache"]("channel", $data[$i], $gid, null);
                            };
                        }else $error = 7;
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
                        }else $error = 2;
                    };
                    // проверяем значение данных
                    if(!$error){// если нет ошибок
                        if(// множественное условие
                            !empty($uid)
                        ){// если проверка пройдена
                        }else $error = 3;
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
                                    $data = $app["fun"]["getFileCache"]($key, $uid);
                                    if($data) $parent[$key][$i] = $data;
                                    if($data) $unit = &$parent[$key][$i];
                                };
                            }else $unit = &$parent[$key][$i];
                        }else $error = 4;
                    };
                    // получаем элемент через api
                    if(!$error and !$unit){// если нужно выполнить
                        $uri = "/users/" . $uid;
                        $data = $app["fun"]["apiRequest"]("get", $uri, null, $code);
                        if(200 == $code){// если запрос выполнен успешно
                            $unit = &$app["fun"]["setCache"]($type, $data);
                        }else $error = 5;
                    };
                    // получаем каналы через api
                    $key = "channels";// задаём ключ
                    if(!$error and $unit and !isset($unit[$key])){// если нужно выполнить
                        $uri = "/users/" . $app["val"]["discordBotId"] . "/" . $key;
                        $data = array("recipient_id" => $uid);
                        $data = $app["fun"]["apiRequest"]("post", $uri, $data, $code);
                        if(200 == $code){// если запрос выполнен успешно
                            if(!isset($unit[$key])) $unit[$key] = array();
                            $app["fun"]["setCache"]("channel", $data, null, $uid);
                        }else $error = 7;
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
                        }else $error = 2;
                    };
                    // проверяем значение данных
                    if(!$error){// если нет ошибок
                        if(// множественное условие
                            !empty($uid)
                            and !empty($gid)
                        ){// если проверка пройдена
                        }else $error = 3;
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
                        }else $error = 4;
                    };
                    // получаем элемент через api
                    if(!$error and !$unit){// если нужно выполнить
                        $uri = "/guilds/" . $gid . "/members/" . $uid;
                        $data = $app["fun"]["apiRequest"]("get", $uri, null, $code);
                        if(200 == $code){// если запрос выполнен успешно
                            $unit = &$app["fun"]["setCache"]($type, $data, $gid);
                        }else $error = 5;
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
                        }else $error = 2;
                    };
                    // проверяем значение данных
                    if(!$error){// если нет ошибок
                        if(// множественное условие
                            !empty($cid)
                            and (!empty($gid) xor !empty($uid))
                        ){// если проверка пройдена
                        }else $error = 3;
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
                        }else $error = 4;
                    };
                    // получаем элемент через api
                    if(!$error and !$unit){// если нужно выполнить
                        $uri = "/channels/" . $cid;
                        $data = $app["fun"]["apiRequest"]("get", $uri, null, $code);
                        if(200 == $code){// если запрос выполнен успешно
                            $unit = &$app["fun"]["setCache"]($type, $data, $gid, $uid);
                        }else $error = 5;
                    };
                    // получаем сообщения через api
                    $key = "messages";// задаём ключ
                    if(!$error and $unit and !empty($gid) and !isset($unit[$key])){// если нужно выполнить
                        $flag = false;// есть ли необходимые права для выполнения действия
                        if(!$flag) $permission = $app["fun"]["getPermission"]("member", $app["val"]["discordBotId"], $unit, $gid);
                        $flag = ($flag or ($permission & $app["val"]["discordMainPermission"]) == $app["val"]["discordMainPermission"]);
                        $flag = ($flag or ($permission & $app["val"]["discordListPermission"]) == $app["val"]["discordListPermission"]);
                        if($flag){// если проверка пройдена
                            $uri = "/channels/" . $cid  . "/" . $key;
                            $data = $app["fun"]["apiRequest"]("get", $uri, null, $code);
                            if(200 == $code){// если запрос выполнен успешно
                                if(!isset($unit[$key])) $unit[$key] = array();
                                $key = "reactions";// изменяем ключ
                                for($i = 0, $iLen = count($data); $i < $iLen; $i++){
                                    if(!isset($data[$i][$key])) $data[$i][$key] = array();
                                    $app["fun"]["setCache"]("message", $data[$i], $cid, $gid);
                                };
                            }else $error = 6;
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
                            $argLength > $argFirst + 2
                        ){// если проверка пройдена
                            $mid = func_get_arg($argFirst);
                            $cid = func_get_arg($argFirst + 1);
                            $gid = func_get_arg($argFirst + 2);
                        }else $error = 2;
                    };
                    // проверяем значение данных
                    if(!$error){// если нет ошибок
                        if(// множественное условие
                            !empty($mid)
                            and !empty($cid)
                            and !empty($gid)
                        ){// если проверка пройдена
                        }else $error = 3;
                    };
                    // определяем ссылку на элемента
                    if(!$error){// если нет ошибок
                        $key = "messages";// задаём ключ
                        $parent = &$app["fun"]["getCache"]("channel", $cid, $gid, null);
                        if(!is_null($parent)){// если есть родительский элемент
                            $flag = false;// есть ли необходимые права для выполнения действия
                            if(!$flag) $permission = $app["fun"]["getPermission"]("member", $app["val"]["discordBotId"], $cid, $gid);
                            $flag = ($flag or ($permission & $app["val"]["discordMainPermission"]) == $app["val"]["discordMainPermission"]);
                            $flag = ($flag or ($permission & $app["val"]["discordListPermission"]) == $app["val"]["discordListPermission"]);
                            if($flag or isset($parent[$key])){// если есть разрешения или учёт уже ведётся
                                if(!isset($parent[$key])) $parent[$key] = array();
                                for($i = 0, $iLen = count($parent[$key]); $i < $iLen; $i++){
                                    if($parent[$key][$i]["id"] == $mid) break;
                                };
                                if(isset($parent[$key][$i])) $unit = &$parent[$key][$i];
                            }else $error = 5;
                        }else $error = 4;
                    };
                    // получаем элемент через api
                    if(!$error and !$unit){// если нужно выполнить
                        $uri = "/channels/" . $cid . "/messages/" . $mid;
                        $data = $app["fun"]["apiRequest"]("get", $uri, null, $code);
                        if(200 == $code){// если запрос выполнен успешно
                            $unit = &$app["fun"]["setCache"]($type, $data, $cid, $gid);
                        }else $error = 5;
                    };
                    // получаем список реакций через api
                    $key = "reactions";// задаём ключ
                    if(!$error and $unit and !isset($unit[$key])){// если нужно выполнить
                        $flag = $unit["author"]["id"] == $app["val"]["discordBotId"];
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
                                            $item["user"] = array("id" => $app["val"]["discordBotId"], "bot" => true);
                                            $app["fun"]["setCache"]("reaction", $item, $mid, $cid, $gid);
                                        }else{// если реакция участника или другого бота
                                            // получаем пользователей реакции через api
                                            $rid = array("", $item["emoji"]["name"], $item["emoji"]["id"]);
                                            $value = urlencode(!empty($rid[2]) ? implode(":", $rid) : $rid[1]);
                                            $uri = "/channels/" . $cid . "/messages/" . $mid . "/reactions/" . $value;
                                            $data = $app["fun"]["apiRequest"]("get", $uri, null, $code);
                                            if(200 == $code){// если запрос выполнен успешно
                                                for($j = 0, $jLen = count($data); $j < $jLen; $j++){
                                                    $item["user"] = $data[$j];// приводим к единому виду
                                                    $app["fun"]["setCache"]("reaction", $item, $mid, $cid, $gid);
                                                };
                                            }else $error = 6;
                                        };
                                    };
                                };
                            }else $error = 5;
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
                            $argLength > $argFirst + 3
                        ){// если проверка пройдена
                            $rid = func_get_arg($argFirst);
                            $mid = func_get_arg($argFirst + 1);
                            $cid = func_get_arg($argFirst + 2);
                            $gid = func_get_arg($argFirst + 3);
                        }else $error = 2;
                    };
                    // проверяем значение данных
                    if(!$error){// если нет ошибок
                        if(// множественное условие
                            !empty($rid[0])
                            and !empty($rid[1])
                            and !empty($mid)
                            and !empty($cid)
                            and !empty($gid)
                        ){// если проверка пройдена
                        }else $error = 3;
                    };
                    // определяем ссылку на элемента
                    if(!$error){// если нет ошибок
                        $key = "reactions";// задаём ключ
                        $parent = &$app["fun"]["getCache"]("message", $mid, $cid, $gid);
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
                        }else $error = 4;
                    };
                    // получаем элемент через api
                    if(!$error and !$unit){// если нужно выполнить
                        $error = 5;// получение не возможно
                    };
                    // присваеваем ссылку на элемент
                    if(!$error){// если нет ошибок
                        $cache = &$unit;
                    };
                    break;
                default:// не известный тип
                    $error = 1;
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
            
            $argLength = func_num_args();
            $argFirst = 1;// первый $id
            $unit = null;// промежуточная ссылка
            $cache = null;// окончательная ссылка
            switch($type){// поддерживаемые типы
                case "guild":// гильдия
                    // проверяем наличее параметров
                    if(!$error){// если нет ошибок
                        if(// множественное условие
                            $argLength > $argFirst
                        ){// если проверка пройдена
                            $gid = func_get_arg($argFirst);
                        }else $error = 2;
                    };
                    // проверяем значение данных
                    if(!$error){// если нет ошибок
                        if(// множественное условие
                            !empty($gid)
                        ){// если проверка пройдена
                        }else $error = 3;
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
                                $app["fun"]["delFileCache"]($key, $gid);
                            };
                        }else $error = 4;
                    };
                    // удаляем ссылку на элемента
                    if(!$error){// если нет ошибок
                        $key = "guilds";// задаём ключ
                        if($unit){// если элемент существует
                            $cache = array_splice($parent[$key], $i, 1)[0];
                        }else $error = 5;
                    };
                    break;
                case "user":// пользователь
                    // проверяем наличее параметров
                    if(!$error){// если нет ошибок
                        if(// множественное условие
                            $argLength > $argFirst
                        ){// если проверка пройдена
                            $uid = func_get_arg($argFirst);
                        }else $error = 2;
                    };
                    // проверяем значение данных
                    if(!$error){// если нет ошибок
                        if(// множественное условие
                            !empty($uid)
                        ){// если проверка пройдена
                        }else $error = 3;
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
                                $app["fun"]["delFileCache"]($key, $uid);
                            };
                        }else $error = 4;
                    };
                    // удаляем ссылку на элемента
                    if(!$error){// если нет ошибок
                        $key = "users";// задаём ключ
                        if($unit){// если элемент существует
                            $cache = array_splice($parent[$key], $i, 1)[0];
                        }else $error = 5;
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
                        }else $error = 2;
                    };
                    // проверяем значение данных
                    if(!$error){// если нет ошибок
                        if(// множественное условие
                            !empty($uid)
                            and !empty($gid)
                        ){// если проверка пройдена
                        }else $error = 3;
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
                        }else $error = 4;
                    };
                    // удаляем ссылку на элемента
                    if(!$error){// если нет ошибок
                        $key = "members";// задаём ключ
                        if($unit){// если элемент существует
                            $cache = array_splice($parent[$key], $i, 1)[0];
                        }else $error = 5;
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
                        }else $error = 2;
                    };
                    // проверяем значение данных
                    if(!$error){// если нет ошибок
                        if(// множественное условие
                            !empty($cid)
                            and (!empty($gid) xor !empty($uid))
                        ){// если проверка пройдена
                        }else $error = 3;
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
                        }else $error = 4;
                    };
                    // удаляем ссылку на элемента
                    if(!$error){// если нет ошибок
                        $key = "channels";// задаём ключ
                        if($unit){// если элемент существует
                            $cache = array_splice($parent[$key], $i, 1)[0];
                        }else $error = 5;
                    };
                    break;
                case "message":// сообщение
                    // проверяем наличее параметров
                    if(!$error){// если нет ошибок
                        if(// множественное условие
                            $argLength > $argFirst + 2
                        ){// если проверка пройдена
                            $mid = func_get_arg($argFirst);
                            $cid = func_get_arg($argFirst + 1);
                            $gid = func_get_arg($argFirst + 2);
                        }else $error = 2;
                    };
                    // проверяем значение данных
                    if(!$error){// если нет ошибок
                        if(// множественное условие
                            !empty($mid)
                            and !empty($cid)
                            and !empty($gid)
                        ){// если проверка пройдена
                        }else $error = 3;
                    };
                    // ищем ссылку на элемента
                    if(!$error){// если нет ошибок
                        $key = "messages";// задаём ключ
                        $parent = &$app["fun"]["getCache"]("channel", $cid, $gid, null);
                        if(!is_null($parent)){// если есть родительский элемент
                            if(isset($parent[$key])){// если есть массив элементов у родителя
                                for($i = 0, $iLen = count($parent[$key]); $i < $iLen; $i++){
                                    if($parent[$key][$i]["id"] == $mid) break;
                                };
                                if(isset($parent[$key][$i])) $unit = &$parent[$key][$i];
                            };
                        }else $error = 4;
                    };
                    // удаляем ссылку на элемента
                    if(!$error){// если нет ошибок
                        $key = "messages";// задаём ключ
                        if($unit){// если элемент существует
                            $cache = array_splice($parent[$key], $i, 1)[0];
                        }else $error = 5;
                    };
                    break;
                case "reaction":// реакция
                    // проверяем наличее параметров
                    if(!$error){// если нет ошибок
                        if(// множественное условие
                            $argLength > $argFirst + 3
                        ){// если проверка пройдена
                            $rid = func_get_arg($argFirst);
                            $mid = func_get_arg($argFirst + 1);
                            $cid = func_get_arg($argFirst + 2);
                            $gid = func_get_arg($argFirst + 3);
                        }else $error = 2;
                    };
                    // проверяем значение данных
                    if(!$error){// если нет ошибок
                        if(// множественное условие
                            !empty($rid[0])
                            and !empty($rid[1])
                            and !empty($mid)
                            and !empty($cid)
                            and !empty($gid)
                        ){// если проверка пройдена
                        }else $error = 3;
                    };
                    // ищем ссылку на элемента
                    if(!$error){// если нет ошибок
                        $key = "reactions";// задаём ключ
                        $parent = &$app["fun"]["getCache"]("message", $mid, $cid, $gid);
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
                        }else $error = 4;
                    };
                    // удаляем ссылку на элемента
                    if(!$error){// если нет ошибок
                        $key = "reactions";// задаём ключ
                        if($unit){// если элемент существует
                            $cache = array_splice($parent[$key], $i, 1)[0];
                        }else $error = 5;
                    };
                    break;
                default:// не известный тип
                    $error = 1;
            };
            // возвращаем результат
            return $cache;
        },
        "setFileCache" => function($group, $name, $data){// добавляет данные в файловый кеш
        //@param $group {string} - группа файлового кеша
        //@param $name {string} - имя файлового кеша
        //@param $data {array} - данные для кеширования
        //@return {boolean} - успешность кеширования данных
            global $app;
            $error = 0;

            // проверяем группу
            if(!$error){// если нет ошибок
                if(!empty($group)){// если проверка пройдена
                }else $error = 1;
            };
            // проверяем именя
            if(!$error){// если нет ошибок
                if(!empty($name)){// если проверка пройдена
                }else $error = 2;
            };
            // проверяем данных
            if(!$error){// если нет ошибок
                if(!empty($data)){// если проверка пройдена
                }else $error = 3;
            };
            // преобразовываем данных в содержимое
            if(!$error){// если нет ошибок
                $content = json_encode($data, JSON_UNESCAPED_UNICODE);
                if(!empty($content)){// если не пусто
                }else $error = 4;
            };
            // создаём каталог группы
            if(!$error){// если нет ошибок
                $path = template($app["val"]["cacheUrl"], array("group" => $group));
                if(is_dir($path) or @mkdir($path)){// если удалось создать
                }else $error = 5;
            };
            // записываем содержимое в файл
            if(!$error){// если нет ошибок
                $path = template($app["val"]["cacheUrl"], array("group" => $group, "name" => $name));
                $file = new File($path);// используем специализированный класс
                if($file->write($content, false)){// если удалось записать
                }else $error = 6;
            };
            // возвращаем результат
            return !$error;
        },
        "getFileCache" => function($group, $name){// получает данные из файлового кеша
        //@param $group {string} - группа файлового кеша
        //@param $name {string} - имя файлового кеша
        //@return {array|null} - полученные данные
            global $app;
            $error = 0;
            
            $data = null;
            // проверяем группу
            if(!$error){// если нет ошибок
                if(!empty($group)){// если проверка пройдена
                }else $error = 1;
            };
            // проверяем именя
            if(!$error){// если нет ошибок
                if(!empty($name)){// если проверка пройдена
                }else $error = 2;
            };
            // получаем содержимое файла
            if(!$error){// если нет ошибок
                $path = template($app["val"]["cacheUrl"], array("group" => $group, "name" => $name));
                $file = new File($path);// используем специализированный класс
                $content = $file->read(false);// получаем содержимое
                if(!empty($content)){// если не пусто
                }else $error = 3;
            };
            // преобразовываем содержимое в данные
            if(!$error){// если нет ошибок
                $data = json_decode($content, true);
                if(!empty($data)){// если не пусто
                }else $error = 4;
            };
            // возвращаем результат
            return $data;
        },
        "delFileCache" => function($group = null, $name = null){// удаляет файловый кеш
        //@param $group {string} - группа файлового кеша
        //@param $name {string} - имя файлового кеша
        //@return {boolean} - успешность удаления
            global $app;
            $error = 0;
            
            // проверяем группу
            if(!$error){// если нет ошибок
                if(!empty($group) or empty($name)){// если проверка пройдена
                }else $error = 1;
            };
            // удаляем файловый кеш
            if(!$error){// если нет ошибок
                $root = template($app["val"]["cacheUrl"], array());
                // работаем со списком папок
                $folders = !$group ? array_diff(scandir($root), array("..", ".")) : array($group);
                foreach($folders as $folder){// пробигаемся по списку
                    $value = pathinfo($folder, PATHINFO_BASENAME);// полное имя
                    $parent = template($app["val"]["cacheUrl"], array("group" => $folder));
                    $flag = (!$group or mb_strtolower($group) == mb_strtolower($value));
                    if($flag and is_dir($parent)){// если найдено совпадение
                        // работаем со списком файлов
                        $files = !$name ? array_diff(scandir($parent), array("..", ".")) : array($name);
                        $count = count($files);// считаем количество значений в списке
                        foreach($files as $file){// пробигаемся по списку
                            $value = pathinfo($file, PATHINFO_FILENAME);// короткое имя
                            $path = template($app["val"]["cacheUrl"], array("group" => $folder, "name" => $value));
                            $flag = (!$name or mb_strtolower($name) == mb_strtolower($value));
                            if($flag and is_file($path)){// если найдено совпадение
                                // удаляем полученный файл
                                if(!$error){// если нет ошибок
                                    if(@unlink($path)){// если файл удалён
                                        $count--;// уменьшаем счётчик
                                    }else $error = 2;
                                };
                            };
                        };
                        // удаляем полученую папку
                        if(!$error and !$name and !$count){// если нужно выполнить
                            if(@rmdir($parent)){// если папка удалена
                            }else $error = 3;
                        };
                    };
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
        "dateCreate" => function($value, $timezone = null){// преобразует текст в метку времени с учётом временной зоны
        //@param $value {string} - текст времени на английском языке
        //@param $timezone {string} - временная зона
        //@return {number} - метка времени или -1 при ошибке
            
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
        //@param $timestamp {number} - временная метка
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
        "changeEvents" => function($time, &$status){// делаем регламентные операции с событиями
        //@param $time {float} - данные о времени для выполнения регламентных операций
        //@param $status {number} - целое число статуса выполнения
        //@return {array} - список изменённых событий по идентификаторам
            global $app; $list = array();
        
            // загружаем все необходимые базы данных
            if(empty($status)){// если нет ошибок
                $events = $app["fun"]["getStorage"]("events", true);
                if(!empty($events)){// если удалось получить доступ к базе данных
                }else $status = 304;// не удалось загрузить одну из многих баз данных
            };
            // корректируем события по времени
            if(empty($status)){// если нет ошибок
                for($i = $events->length - 1; $i >= 0 and empty($status); $i--){
                    $id = $events->key($i);// получаем ключевой идентификатор по индексу
                    $event = $events->get($id);// получаем элемент по идентификатору
                    $value = $event["time"];// время события
                    // корректируем повторяющиеся события
                    if($event["repeat"]){// если это повторяющиеся событие
                        // корректируем устаревшие повторяющиеся события
                        while($value < $time - $app["val"]["eventTimeDelete"]){
                            $value = $value + $event["repeat"];
                        };
                        // корректируем слишком далёкие повторяющиеся события
                        while($value > $time + $app["val"]["eventTimeAdd"]){
                            $value = $value - $event["repeat"];
                        };
                    };
                    // определяем события выходящие за ограничения по времени
                    if(// множественное условие
                        $value < $time - $app["val"]["eventTimeDelete"]
                        or $value > $time + $app["val"]["eventTimeAdd"]
                    ){// если нужно удалить событие
                        $value = 0;
                    };
                    // вносим изменения в базу данных
                    if($event["time"] != $value){// если есть изменения
                        $flag = $value ? $events->set($id, "time", $value) : $events->set($id);
                        if($flag){// если данные успешно изменены
                            $list[$id] = $event;// добавляем событие в список изменённых
                        }else $status = 309;// не удалось записать данные в базу данных
                    };
                };
            };
            // считаем события для проверки лимитов
            if(empty($status)){// если нет ошибок
                $counts = array();// счётчики записи
                $all = "";// идентификатор всех рейдов
                for($i = 0, $iLen = $events->length; $i < $iLen; $i++){
                    $id = $events->key($i);// получаем ключевой идентификатор по индексу
                    $event = $events->get($id);// получаем элемент по идентификатору
                    // создаём структуру счётчика
                    $count = &$counts;// счётчик элементов
                    foreach(array($event["guild"], $event["channel"], $event["user"], $event["time"]) as $key){
                        if(!isset($count[$key])) $count[$key] = array();
                        $count = &$count[$key];// получаем ссылку
                    };
                    // выполняем подсчёт событий
                    if(!isset($count[$event["raid"]])) $count[$event["raid"]] = 0;
                    if(!isset($count[$all])) $count[$all] = 0;
                    $count[$event["raid"]]++;
                    $count[$all]++;
                };
            };
            // удаляем события по лимитам
            if(empty($status)){// если нет ошибок
                foreach(array(false, true) as $repeat){// пробегаемся по значениям повторяемости
                    for($i = $events->length - 1; $i >= 0 and empty($status); $i--){
                        $id = $events->key($i);// получаем ключевой идентификатор по индексу
                        $event = $events->get($id);// получаем элемент по идентификатору
                        if($repeat == !!$event["repeat"]){// если событие соответствует
                            // получаем счётчик из структуру
                            $count = &$counts;// счётчик элементов
                            foreach(array($event["guild"], $event["channel"], $event["user"], $event["time"]) as $key){
                                $count = &$count[$key];// получаем ссылку
                            };
                            // работаем с лимитами
                            if($count[$event["raid"]] > 1 or $count[$all] > 1){// если есть привышение лимитов
                                if($events->set($id)){// если данные успешно изменены
                                    $list[$id] = $event;// добавляем событие в список изменённых
                                    $count[$event["raid"]]--;
                                    $count[$all]--;
                                }else $status = 309;// не удалось записать данные в базу данных
                            };
                        };
                    };
                };
            };
            // возвращаем результат
            return $list;
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
                        $length = mb_strlen($fragment);
                        $content = mb_substr($content, $length);
                        $result = $fragment;
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
                    case "role":// начиная с ролей
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
        "strimStrMulti" => function($imput, $delim){// обрезает строку по мульти разделителю
        //@param $imput {string} - исходная строка для обрезки
        //@param $delim {string} - строка, где каждый символ разделитель
        //@return {string} - обрезанная строка по первому разделителю или пустая строка
            
            $index = $length = mb_strlen($imput);
            for($i = 0, $iLen = mb_strlen($delim); $i < $iLen; $i++){
                $value = mb_strpos($imput, mb_substr($delim, $i, 1));
                if(false !== $value) $index = min($value, $index);
            };
            
            if($length > $index) $value = trim(mb_substr($imput, 0, $index));
            else if(!$iLen) $value = $imput;
            else $value = "";
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
                    array_push($items, $item);
                };
                // записываем в файл отладочную информацию
                $times[$level] = $now;// сохраняем время
                $line = implode("\t", $items) . "\n";
                $path = template($app["val"]["debugUrl"], array("name" => $name));
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
            $response = null;
            
            // делаем запрос через api
            $app["fun"]["setDebug"](7, "🌐 [apiRequest]", $method, $uri);// отладочная информация
            if(!empty($method) and !empty($uri)){// если не пустые значения
                $method = mb_strtolower($method);
                $now = microtime(true);// текущее время
                $reset = $now;// время сброса ограничений
                $delim = "/";// разделитель сегментов
                // удаляем информацию об истёкшие ограничениях
                foreach($limits as $key => $limit){// пробигаемся по ограничениям
                    if($now >= $limit["reset"]) unset($limits[$key]);
                };
                // проверяем информацию об ограничениях любых видов запросов
                $key = implode($delim, array_slice(explode($delim, $uri), 0, 4));
                $limit = get_val($limits, $key, false);// получаем лимит
                $flag = $limit;// требуется ли учесть эти ограничения
                $flag = ($flag and $limit["remaining"] < 1);
                if($flag) $reset = max($reset, $limit["reset"]);
                // проверяем информацию об ограничениях запросов на удаление
                $key = implode($delim, array_slice(explode($delim, $uri), 0, 3));
                $limit = get_val($limits, $key, false);// получаем лимит
                $flag = $limit;// требуется ли учесть эти ограничения
                $flag = ($flag and $limit["remaining"] < 1);
                $flag = ($flag and "delete" == $method);
                if($flag) $reset = max($reset, $limit["reset"]);
                // дожидаемся сброса ограничений
                $wait = $reset - $now;// время ожидания
                if($wait > 0) usleep($wait * 1000000);
                // готовим данные и выполняем запрос
                $headers = array();// стандартные заголовки для запроса
                $headers["authorization"] = "Bot " . $app["val"]["discordBotToken"];
                $headers["x-ratelimit-precision"] = "millisecond";
                $headers["user-agent"] = "DiscordBot";// для обхода блокировки cloudflare
                $flag = (!empty($data) and "get" != $method);
                if($flag) $headers["content-type"] = "application/json;charset=utf-8";
                if($flag) $data = json_encode($data, JSON_UNESCAPED_UNICODE);
                $data = http($method, $app["val"]["discordApiUrl"] . $uri, $data, null, $headers, false);
                $now = microtime(true);// текущее время
                // добавляем информацию об ограничениях любых видов запросов
                $key = implode($delim, array_slice(explode($delim, $uri), 0, 4));
                $limit = get_val($limits, $key, array());// получаем лимит
                $value = (float)get_val($data["headers"], "x-ratelimit-reset-after", 0);
                $flag = $value > 0;// требуется ли учесть эти ограничения
                if($flag) $limit["reset"] = $now + $value;// время сброса ограничений
                $value = (int)get_val($data["headers"], "x-ratelimit-remaining", -1);
                $flag = $value > -1;// требуется ли учесть эти ограничения
                if($flag) $limit["remaining"] = $value;// остаток доступных запросов
                if($flag) $limits[$key] = $limit;// сохраняем значение лимита
                // добавляем информацию об ограничениях запросов на удаление
                $key = implode($delim, array_slice(explode($delim, $uri), 0, 3));
                $limit = get_val($limits, $key, array());// получаем лимит
                $value = get_val($limit, "reset", 0);// получаем значение
                $flag = $value > $now;// требуется ли учесть эти ограничения
                $limit["reset"] = $flag ? $value : $now + 5;// время сброса ограничений
                $value = get_val($limit, "remaining", -1);// получаем значение
                $limit["remaining"] = $flag ? $value - 1 : 4;// остаток доступных запросов
                $flag = "delete" == $method;// требуется ли учесть эти ограничения
                if($flag) $limits[$key] = $limit;// сохраняем значение лимита
                // обрабатываем полученный ответ
                $code = $data["status"];// устанавливаем код ответа сервера
                $data = json_decode($data["body"], true);// преобразовываем данные
                if(!empty($data) or is_array($data)) $response = $data;
            };
            // возвращаем результат
            return $response;
        },
        "getStorage" => function($name, $lock = false){// получает базу данных
        //@param $name {string} - имя запрашиваемой базы данных
        //@param $lock {boolean} - установить блокировку при первом подключении
        //@return {null|FileStorage} - ссылка на базу данных
            global $app;
            
            if(!empty($name)){// если передано название
                if(!isset($app["base"][$name])){// если база данных еще не загружалась
                    $path = template($app["val"]["baseUrl"], array("name" => $name));
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
        "setStatus" => function($id){// устанавливает статус по его идентификатору
        //@param $id {number} - идентификатор устанавливаемого статусного сообщения
        //@return {boolean} - успешность установки указанного статуса
            global $app, $result;
            $error = 0;
            
            $statuses = $app["fun"]["getStorage"]("statuses");
            if(!empty($statuses)){// если база данных статусов существует
                if(!empty($id)){// если передан не пустой идентификатор
                    $result["msg"] = $statuses->get($id, $app["val"]["statusLang"]);
                }else $error = 2;
            }else $error = 1;
            if(!$result["msg"]) $result["msg"] = $app["val"]["statusUnknown"];
            $result["status"] = $id;
            // возвращаем результат
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
        }
    )
);

// выставляем время временную зону по умолчанию
date_default_timezone_set($app["val"]["timeZone"]);
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
$result = array("response" => null, "status" => 0, "msg" => "");
$statuses = $app["fun"]["getStorage"]("statuses", false);
$status = $result["status"];
if(!empty($statuses)){// если удалось получить доступ к базе данных
    $method = $app["fun"]["getClearParam"]($params, "method", "string");
    if(!is_null($method)){// если задан метод в запросе
        if(!empty($method)){// если фильтрация метода прошла успешно
            if(isset($app["method"][$method])){// если метод есть в списке поддерживаемых методов
                $result["response"] = $app["method"][$method]($params, array(), false, $status);
            }else $status = 308;// запрашиваемый метод не поддерживается
        }else $status = 302;// один из обязательных параметров передан в неверном формате
    }else $status = 301;// не передан один из обязательных параметров
}else $status =304;// не удалось загрузить одну из многих баз данных
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
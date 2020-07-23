<?php # 0.2.6 api для бота в discord

include_once "../../libs/File-0.1.inc.php";                                 // 0.1.5 класс для многопоточной работы с файлом
include_once "../../libs/FileStorage-0.5.inc.php";                          // 0.5.9 подкласс для работы с файловым реляционным хранилищем
include_once "../../libs/phpEasy-0.3.inc.php";                              // 0.3.7 основная библиотека упрощённого взаимодействия
include_once "../../libs/vendor/webSocketClient-1.0.inc.php";               // 0.1.0 набор функций для работы с websocket

//////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

$app = array(// основной массив данных
    "val" => array(// переменные и константы
        "baseUrl" => "../base/%name%.db",                                   // шаблон url для базы данных
        "statusUnknown" => "Server unknown status",                         // сообщение для неизвестного статуса
        "statusLang" => "en",                                               // язык для кодов расшифровок
        "format" => "json",                                                 // формат вывода поумолчанию
        "eventTimeAdd" => 8*24*60*60,                                       // максимальное время для записи в событие
        "eventTimeDelete" => 4*60*60,                                       // максимальное время хранения записи события
        "eventTimeClose" => -15*60,                                         // время за которое закрывается событие для изменения
        "eventTimeStep" => 30*60,                                           // шаг записи на событие (округление времени)
        "eventTimeLimit" => 1,                                              // лимит записей на время для пользователя
        "eventRaidLimit" => 1,                                              // лимит записей на время и рейд для пользователя
        "eventCommentLength" => 500,                                        // максимальная длина комментария пользователя
        "eventNoteLength" => 50,                                            // максимальная длина заметки пользователя
        "eventOperationCycle" => 5,                                         // с какой частотой производить регламентные операции с базой в цикле
        "discordLang" => "ru",                                              // язык отображения информации в Discord
        "discordApiUrl" => "https://discord.com/api",                       // базовый url для взаимодействия с Discord API
        "discordWebSocketHost" => "gateway.discord.gg",                     // адрес хоста для взаимодействия с Discord через WebSocket
        "discordWebSocketLimit" => 1500,                                    // лимит итераций цикла общения WebSocket с Discord
        "discordMessageLength" => 2000,                                     // максимальная длина сообщения в Discord
        "discordMessageTime" => 6*60,                                       // максимально допустимое время между сгруппированными сообщениями
        "discordCreatePermission" => 32768,                                 // разрешения для создание первой записи в событие (прикреплять файлы)
        "discordUserPermission" => 16384,                                   // разрешения для записи других пользователей (встраивать ссылки)
        "discordMainPermission" => 338944,                                  // минимальные разрешения для работы бота
        "discordListPermission" => 347136,                                  // разрешения для ведения сводного расписания ботом
        "discordTalkPermission" => 64,                                      // разрешения для ведения обсуждения в канале
        "discordBotGame" => "рейды от @ViPiC#5562",                         // анонс возле аватарки бота
        "discordAllUser" => "@everyone",                                    // идентификатор всех пользователей
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
                            // обрабатываем тип уведомления
                            switch(get_val($data, "t", null)){// поддерживаемые типы
                                case "READY":// ready
                                    // обрабатываем начало подключения
                                    if(isset($data["d"]["session_id"])){// если есть обязательное значение
                                        if($session->set("sid", "value", $data["d"]["session_id"])){// если данные успешно добавлены
                                            $isSessionUpdate = true;// были обновлены данные в базе данных
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
                                            $member = $app["fun"]["setСache"]("member", $data["d"]["member"], $data["d"]["guild_id"]);
                                            if($member){// если удалось закешировать данные
                                                $user = $app["fun"]["setСache"]("user", $data["d"]["member"]["user"]);
                                                if($user){// если удалось закешировать данные
                                                    $app["fun"]["getСache"]("user", $data["d"]["member"]["user"]["id"]);
                                                }else $app["fun"]["delСache"]("user", $data["d"]["member"]["user"]["id"]);
                                            }else $app["fun"]["delСache"]("member", $data["d"]["member"]["user"]["id"], $data["d"]["guild_id"]);
                                        };
                                    };
                                    break;
                                case "GUILD_CREATE":// guild create
                                case "GUILD_UPDATE":// guild update
                                    // обрабатываем изменение гильдии
                                    if(isset($data["d"]["id"])){// если есть обязательное значение
                                        $guild = $app["fun"]["setСache"]("guild", $data["d"]);
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
                                        }else $app["fun"]["delСache"]("guild", $data["d"]["id"]);
                                    };
                                    break;
                                case "GUILD_DELETE":// guild delete
                                    // обрабатываем удаление гильдии
                                    if(isset($data["d"]["id"])){// если есть обязательное значение
                                        $app["fun"]["delСache"]("guild", $data["d"]["id"]);
                                    };
                                    break;
                                case "GUILD_MEMBER_ADD":// guild member add
                                case "GUILD_MEMBER_UPDATE":// guild member update
                                    // обрабатываем изменение участника
                                    if(isset($data["d"]["user"]["id"], $data["d"]["guild_id"])){// если есть обязательное значение
                                        $member = $app["fun"]["setСache"]("member", $data["d"], $data["d"]["guild_id"]);
                                        if($member){// если удалось закешировать данные
                                        }else $app["fun"]["delСache"]("member", $data["d"]["user"]["id"], $data["d"]["guild_id"]);
                                    };
                                    break;
                                case "GUILD_MEMBER_REMOVE":// guild member remove
                                    // обрабатываем удаление участника
                                    if(isset($data["d"]["user"]["id"], $data["d"]["guild_id"])){// если есть обязательное значение
                                        $app["fun"]["delСache"]("member", $data["d"]["user"]["id"], $data["d"]["guild_id"]);
                                    };
                                    break;
                                case "CHANNEL_CREATE":// channel create
                                case "CHANNEL_UPDATE":// channel update
                                    // обрабатываем изменение канала
                                    if(isset($data["d"]["id"], $data["d"]["guild_id"])){// если есть обязательное значение
                                        $channel = $app["fun"]["setСache"]("channel", $data["d"], $data["d"]["guild_id"], null);
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
                                        }else $app["fun"]["delСache"]("channel", $data["d"]["id"], $data["d"]["guild_id"]);
                                    };
                                    break;
                                case "CHANNEL_DELETE":// channel delete
                                    // обрабатываем удаление канала
                                    if(isset($data["d"]["id"], $data["d"]["guild_id"])){// если есть обязательное значение
                                        $app["fun"]["delСache"]("channel", $data["d"]["id"], $data["d"]["guild_id"]);
                                    };
                                    break;
                                case "MESSAGE_CREATE":// message create
                                case "MESSAGE_UPDATE":// message update
                                    // обрабатываем изменение сообщения
                                    if(isset($data["d"]["id"], $data["d"]["channel_id"], $data["d"]["guild_id"])){// если есть обязательное значение
                                        $message = $app["fun"]["setСache"]("message", $data["d"], $data["d"]["channel_id"], $data["d"]["guild_id"]);
                                        if($message){// если удалось закешировать данные
                                            // проверяем права доступа
                                            $flag = false;// есть ли необходимые права для выполнения действия
                                            if(!$flag) $permission = $app["fun"]["getPermission"]("member", $app["val"]["discordBotId"], $data["d"]["channel_id"], $data["d"]["guild_id"]);
                                            $flag = ($flag or ($permission & $app["val"]["discordMainPermission"]) == $app["val"]["discordMainPermission"]);
                                            if($flag){// если бот контролирует этот канал
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
                                        }else $app["fun"]["delСache"]("message", $data["d"]["id"], $data["d"]["channel_id"], $data["d"]["guild_id"]);
                                    };
                                    break;
                                case "MESSAGE_DELETE":// message delete
                                    // обрабатываем удаление сообщения
                                    if(isset($data["d"]["id"], $data["d"]["channel_id"], $data["d"]["guild_id"])){// если есть обязательное значение
                                        $app["fun"]["delСache"]("message", $data["d"]["id"], $data["d"]["channel_id"], $data["d"]["guild_id"]);
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
                                case "MESSAGE_DELETE_BULK":// message delete bulk
                                    // обрабатываем удаление сообщения
                                    if(isset($data["d"]["ids"], $data["d"]["channel_id"], $data["d"]["guild_id"])){// если есть обязательное значение
                                        for($i = 0, $iLen = count($data["d"]["ids"]); $i < $iLen; $i++){// пробигаемся по идентификаторам сообщений
                                            $app["fun"]["delСache"]("message", $data["d"]["ids"][$i], $data["d"]["channel_id"], $data["d"]["guild_id"]);
                                        };
                                    };
                                    break;
                            };
                            break;
                        case 7:// reconnect
                            // инициализируем новое подключение
                            if(websocket_check($websocket)) websocket_close($websocket);// закрываем старое подключение
                            $websocket = websocket_open($app["val"]["discordWebSocketHost"], 443, null, $error, 10, true);
                            if($websocket){// если удалось создать подключение к веб-сокету
                                $heartbeatInterval = 0;// отключаем проверку соединения
                            }else $status = 305;// не удалось установить соединение с удалённым сервером
                            break;
                        case 9:// invalid session
                            // пробуем создать новую сессию
                            $data = array(// данные для отправки
                                "op" => 2,// identify
                                "d" => array(// data
                                    // GUILDS | GUILD_MESSAGES | GUILD_MESSAGE_TYPING
                                    "intents" => 1 << 0 | 1 << 9 | 1 << 11,
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
                            if($heartbeatSendTime - $heartbeatAcceptTime > $heartbeatInterval + 10){// если соединение зависло
                                // разрываем соединение
                                websocket_close($websocket);// закрываем старое подключение
                                $websocket = false;// подключение закрыто
                            }else if($now - $heartbeatSendTime > $heartbeatInterval){// если нужно отправить серцебиение
                                // отправляем серцебиение
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
                                    $count = &$counts;// счётчик элиментов
                                    foreach(array($item["guild"], $item["channel"]) as $key){
                                        if(!isset($count[$key])) $count[$key] = array();
                                        $count = &$count[$key];// получаем ссылку
                                    };
                                    // выполняем подсчёт элиментов
                                    if(!isset($count["item"])) $count["item"] = 0;
                                    $count["item"]++;
                                };
                                // выполняем обновление уведомлений
                                foreach($counts as $gid => $items){// пробигаемся по гильдиям
                                    // получаем данные о гильдии
                                    if(!empty($status)) break;// не продолжаем при ошибке
                                    $guild = $app["fun"]["getСache"]("guild", $gid);
                                    if($guild){// если удалось получить данные
                                        foreach($items as $cid => $count){// пробигаемся по каналам
                                            // получаем данные о канале
                                            if(!empty($status)) break;// не продолжаем при ошибке
                                            $channel = $app["fun"]["getСache"]("channel", $cid, $gid, null);
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
                    empty($status) and $index < $app["val"]["discordWebSocketLimit"]
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
            // возвращаем результат
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
                $guild = $app["fun"]["getСache"]("guild", $guild);
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
                    $channel = isset($guild["channels"][$i]) ? $guild["channels"][$i] : false;// получаем очередной элимент
                    // проверяем разрешения
                    if($channel and get_val($options, "consolidated", false)){// если нужно обновить только сводное расписание
                        $permission = $app["fun"]["getPermission"]("member", $app["val"]["discordBotId"], $channel, $guild["id"]);
                        $hasMainPermission = ($permission & $app["val"]["discordMainPermission"]) == $app["val"]["discordMainPermission"];
                        $hasListPermission = ($permission & $app["val"]["discordListPermission"]) == $app["val"]["discordListPermission"];
                        $flag = $isConsolidated = ($hasListPermission and !$hasMainPermission);
                    }else $flag = true;
                    // обрабатываем канал
                    if($channel and $flag){// если очередной элимент существует
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
                $guild = $app["fun"]["getСache"]("guild", $guild);
                if($guild){// если удалось получить данные
                }else $status = 303;// переданные параметры не верны
            };
            // получаем информацию о канале
            if(empty($status)){// если нет ошибок
                $channel = $app["fun"]["getСache"]("channel", $channel, $guild["id"], null);
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
                    $message = $channel["messages"][$i];// получаем очередной элимент
                    $flag = (!$message["pinned"] and $message["author"]["id"] != $app["val"]["discordBotId"]);
                    if($flag) array_unshift($list, $message["id"]);// добавляем в список
                };
                // обрабатываем список идентификаторов сообщений
                for($i = 0, $iLen = count($list); !$i or $i < $iLen; $i++){// пробигаемся по списку идентификаторов сообщений
                    $message = $iLen ? $app["fun"]["getСache"]("message", $list[$i], $channel["id"], $guild["id"]) : null;
                    // обрабатываем сообшение
                    if(!$iLen or $message){// если очередной элимент существует
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
            $isEdit = false;// эта каманда на изменение записи
            $isConsolidated = false;// в данном канале ведётся сводное рассписание
            $isEventsUpdate = false;// были ли обновлены данные в базе данных
            $isNeedProcessing = false;// требуется ли дальнейшая обработка
            $hasMainPermission = false;// есть основные разрешения
            $hasListPermission = false;// есть разрешения для сводного расписания
            $hasTalkPermission = false;// есть разрешения для ведения обсуждения
            $language = $app["val"]["discordLang"];// локализация
            // получаем очищенные значения параметров
            $token = $app["fun"]["getClearParam"]($params, "token", "string");
            $guild = $app["fun"]["getClearParam"]($params, "guild", "string");
            $channel = $app["fun"]["getClearParam"]($params, "channel", "string");
            $message = $app["fun"]["getClearParam"]($params, "message", "string");
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
                $guild = $app["fun"]["getСache"]("guild", $guild);
                if($guild){// если удалось получить данные
                }else $status = 303;// переданные параметры не верны
            };
            // получаем информацию о канале
            if(empty($status)){// если нет ошибок
                $channel = $app["fun"]["getСache"]("channel", $channel, $guild["id"], null);
                if($channel){// если удалось получить данные
                    // проверяем разрешения
                    $permission = $app["fun"]["getPermission"]("member", $app["val"]["discordBotId"], $channel, $guild["id"]);
                    $hasMainPermission = ($permission & $app["val"]["discordMainPermission"]) == $app["val"]["discordMainPermission"];
                    $hasListPermission = ($permission & $app["val"]["discordListPermission"]) == $app["val"]["discordListPermission"];
                    $hasTalkPermission = ($permission & $app["val"]["discordTalkPermission"]) == $app["val"]["discordTalkPermission"];
                    $isConsolidated = ($hasListPermission and !$hasMainPermission);
                }else $status = 303;// переданные параметры не верны
            };
            // получаем информацию о сообщении
            if(empty($status) and !empty($message)){// если нужно выполнить
                $message = $app["fun"]["getСache"]("message", $message, $channel["id"], $guild["id"]);
                if($message){// если удалось получить данные
                    $isNeedProcessing = (!$message["pinned"] and $message["author"]["id"] != $app["val"]["discordBotId"]);
                }else $status = 303;// переданные параметры не верны
            };
            // выполняем регламентные операции с базой событий
            if(empty($status) and !get_val($options, "nocontrol", false)){// если нужно выполнить
                $flag = count($app["fun"]["changeEvents"]($now, $status)) > 0;
                $isEventsUpdate = $isEventsUpdate || $flag;
            };
            // обрабатываем сообщение
            if(empty($status) and $isNeedProcessing and $hasMainPermission and !$message["type"]){// если нужно выполнить
                $command = array(// пустая команда
                    "action" => "",     // действие
                    "role" => "",       // роль
                    "date" => 0,        // дата
                    "time" => 0,        // дата и время
                    "raid" => "",       // рейд
                    "user" => "",       // пользователь
                    "addition" => "",   // опция
                    "comment" => ""     // комментарий
                );
                // формируем команду из сообщения
                if(empty($error)){// если нет проблем
                    $delim = " ";// разделитель параметров в сообщении
                    $content = $message["content"];// получаем содержимое сообщения
                    $content = trim($content);// убираем пробелы с концов сообщения
                    $time = "";// вспомогательная переменная для обработки времени
                    for($index = 0; true; $index++){// циклическая обработка
                        // действие - KEY
                        $key = "action";// текущий параметр команды
                        $value = $command[$key];// текущий значение параметра
                        if(!$index and !mb_strlen($value)){// если нужно выполнить
                            $value = $app["fun"]["getCommandValue"]($content, $delim, true, $actions);
                            if(mb_strlen($value)){// если найдено ключевое значение
                                $command[$key] = $value;
                                continue;
                            };
                        };
                        // роль - KEY
                        $key = "role";// текущий параметр команды
                        $value = $command[$key];// текущий значение параметра
                        if(!mb_strlen($value)){// если нужно выполнить
                            $value = $app["fun"]["getCommandValue"]($content, $delim, false, $roles);
                            if(mb_strlen($value)){// если найдено ключевое значение
                                $command[$key] = $value;
                                continue;
                            };
                        };
                        // дата - KEY
                        $key = "date";// текущий параметр команды
                        $value = $command[$key];// текущий значение параметра
                        if(!$value){// если нужно выполнить
                            $value = $app["fun"]["getCommandValue"]($content, $delim, false, $dates);
                            if(mb_strlen($value)){// если найдено ключевое значение
                                $value = strtotime($value, $message["timestamp"]);
                                if(false === $value) $value = -1;// приводим к единобразному написанию
                                $command[$key] = $value;
                                continue;
                            };
                        };
                        // дата - DD.MM
                        $key = "date";// текущий параметр команды
                        $value = $command[$key];// текущий значение параметра
                        if(!$value){// если нужно выполнить
                            $value = $app["fun"]["getCommandValue"]($content, $delim, false, "/^(\d{1,2}\.)(\d{2})$/", $list);
                            if(mb_strlen($value)){// если найдено ключевое значение
                                $t1 = strtotime($value . "." . date("Y", $message["timestamp"]), $message["timestamp"]);
                                $t2 = strtotime($value . "." . date("Y", strtotime("+1 year", $message["timestamp"])), $message["timestamp"]);
                                $value = (abs($t1 - $message["timestamp"]) < abs($t2 - $message["timestamp"]) ? $t1 : $t2);
                                if(false === $value) $value = -1;// приводим к единобразному написанию
                                $command[$key] = $value;
                                continue;
                            };
                        };
                        // дата - DD.MM.YY
                        $key = "date";// текущий параметр команды
                        $value = $command[$key];// текущий значение параметра
                        if(!$value){// если нужно выполнить
                            $value = $app["fun"]["getCommandValue"]($content, $delim, false, "/^(\d{1,2}\.)(\d{2}\.)(\d{2})$/", $list);
                            if(mb_strlen($value)){// если найдено ключевое значение
                                $value = $list[1] . $list[2] . mb_substr(date("Y", $message["timestamp"]), 0, 2) . $list[3];
                                $value = strtotime($value, $message["timestamp"]);
                                if(false === $value) $value = -1;// приводим к единобразному написанию
                                $command[$key] = $value;
                                continue;
                            };
                        };
                        // дата - DD.MM.YYYY
                        $key = "date";// текущий параметр команды
                        $value = $command[$key];// текущий значение параметра
                        if(!$value){// если нужно выполнить
                            $value = $app["fun"]["getCommandValue"]($content, $delim, false, "/^(\d{1,2}\.)(\d{2}\.)(\d{4})$/", $list);
                            if(mb_strlen($value)){// если найдено ключевое значение
                                $value = strtotime($value, $message["timestamp"]);
                                if(false === $value) $value = -1;// приводим к единобразному написанию
                                $command[$key] = $value;
                                continue;
                            };
                        };
                        // время - HH:MM
                        $value = $time;// текущий значение параметра
                        if(!mb_strlen($value)){// если нужно выполнить
                            $value = $app["fun"]["getCommandValue"]($content, $delim, false, "/^(\d{1,2}\:)(\d{2})$/", $list);
                            if(mb_strlen($value)){// если найдено ключевое значение
                                $time = $value;
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
                            $value = $app["fun"]["getCommandValue"]($content, $delim, false, "/^<@!?(\d+)>$|^(".$app["val"]["discordAllUser"].")$/", $list);
                            if(mb_strlen($value)){// если найдено ключевое значение
                                $command[$key] = array_pop($list);
                                continue;
                            };
                        };
                        // опция - KEY
                        $key = "addition";// текущий параметр команды
                        $value = $command[$key];// текущий значение параметра
                        if(!mb_strlen($value)){// если нужно выполнить
                            $value = $app["fun"]["getCommandValue"]($content, $delim, false, $additions);
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
                            $value = $app["fun"]["getCommandValue"]($content, $delim, false);
                            if(mb_strlen($value)){// если найдено ключевое значение
                                $command[$key] = $value;
                            };
                        };
                        // корректируем дату и время
                        $key = "time";// текущий параметр команды
                        if(mb_strlen($time)){// если нужно выполнить
                            $value = date("d.m.Y", $message["timestamp"]);// дата по умолчанию
                            if(!$command["date"]) $command["date"] = strtotime($value, $message["timestamp"]);
                            if($command["date"] > 0) $value = date("d.m.Y", $command["date"]) . " " . $time;
                            else $value = false;// фиксируем ошибку разбора параметра дата в команде
                            if($value) $value = strtotime($value, $message["timestamp"]);
                            if(false === $value) $value = -1;// приводим к единобразному написанию
                            $offset = 1 * date("Z");// определяем текущее смещение временной зоны
                            if($value > 0) $value = ceil(($value - $offset) / $app["val"]["eventTimeStep"]) * $app["val"]["eventTimeStep"] + $offset;
                            $command[$key] = $value;
                        };
                        // завершаем обработку
                        break;
                    };
                };
                // обрабатываем команду в сообщении
                if(empty($error)){// если нет проблем
                    $index = 0;// количество рейдов
                    $unit = null;// ближайщее событие
                    $counts = array();// счётчики элиментов
                    // считаем количество записей
                    for($i = 0, $iLen = $events->length; $i < $iLen; $i++){
                        $id = $events->key($i);// получаем ключевой идентификатор по индексу
                        $event = $events->get($id);// получаем элимент по идентификатору
                        $time = strtotime(date("d.m.Y", $event["time"]));
                        if(// множественное условие
                            $event["channel"] == $channel["id"]
                            and $event["guild"] == $guild["id"]
                            and (!empty($command["raid"]) ? $command["raid"] == $event["raid"] : true)
                            and (!empty($command["time"]) ? $command["time"] == $event["time"] : true)
                            and (!empty($command["date"]) ? $command["date"] == $time : true)
                        ){// если нужно посчитать счётчик
                            // создаём структуру счётчика
                            $count = &$counts;// счётчик элиментов
                            foreach(array($event["time"], $event["raid"]) as $key){
                                if(!isset($count[$key])) $count[$key] = array();
                                $count = &$count[$key];// получаем ссылку
                            };
                            // выполняем подсчёт элиментов
                            if(!isset($count["item"])) $count["item"] = 0;
                            $count["item"]++;
                            // определяем ближайшее событие
                            if(// множественное условие
                                $event["time"] >= $message["timestamp"] + $app["val"]["eventTimeClose"]
                                and (empty($unit) or $unit["time"] > $event["time"])
                            ){// если ближайшее событие
                                $unit = $event;
                            };
                            // сохраняем исходный элимент
                            $item = $event;
                        };
                    };
                    // считаем количество рейдов
                    foreach($counts as $items){
                        foreach($items as $count){
                            $index++;
                        };
                    };
                    // проверяем задан ли не фильтрующий параметр
                    $flag = false;// задан ли не фильтрующий параметр
                    foreach($command as $key => $value){
                        switch($key){// поддерживаемые параметры
                            case "action": case "raid": case "time": case "date": break;
                            default: $flag = ($flag or !empty($value));
                        };
                    };
                    // задаём значения по умолчанию
                    if(1 == $index){// если в канале один рейд
                        $time = strtotime(date("d.m.Y", $item["time"]));
                        if(empty($command["raid"])) $command["raid"] = $item["raid"];
                        if(empty($command["time"])) $command["time"] = $item["time"];
                        if(empty($command["date"])) $command["date"] = $time;
                    }else if(// множественное условие
                        !empty($unit) and $flag
                        and empty($command["raid"])
                        and empty($command["time"])
                        and empty($command["date"])
                    ){// если есть ближайшее событие
                        $item = $unit;// приводим к единобразию
                        $time = strtotime(date("d.m.Y", $item["time"]));
                        $command["raid"] = $item["raid"];
                        $command["time"] = $item["time"];
                        $command["date"] = $time;
                    }else $item = null;
                    // определяем лидера для события
                    $leader = null;// сбрасываем значение
                    if(!empty($item)){// если событие существует
                        for($i = 0, $iLen = $events->length; $i < $iLen; $i++){
                            $id = $events->key($i);// получаем ключевой идентификатор по индексу
                            $event = $events->get($id);// получаем элимент по идентификатору
                            if(// множественное условие
                                $event["channel"] == $item["channel"]
                                and $event["guild"] == $item["guild"]
                                and $event["raid"] == $item["raid"]
                                and $event["time"] == $item["time"]
                                and $event["leader"]
                            ){// если лидер найден
                                $leader = $event["user"];
                            };
                        };
                    };
                    // обрабатываем команду
                    switch($command["action"]){// поддержмваемые команды
                        case "*":// изменить запись
                            $isEdit = true;
                        case "+":// добавить запись
                            // проверяем что указана роль
                            if(empty($error)){// если нет проблем
                                $role = null;// сбрасываем значение
                                if(!empty($command["role"])){// если проверка пройдена
                                    $role = $roles->get($command["role"]);
                                }else $error = $isEdit ? $error : 2;
                            };
                            // проверяем ограничения на комментарий
                            if(empty($error)){// если нет проблем
                                $value = $app["val"]["eventCommentLength"];
                                if(mb_strlen($command["comment"]) <= $value){// если проверка пройдена
                                }else $error = 3;
                            };
                            // проверяем что указана дата
                            if(empty($error)){// если нет проблем
                                if(!empty($command["date"])){// если проверка пройдена
                                }else $error = $isEdit ? 22 : 4;
                            };
                            // проверяем что указано время
                            if(empty($error)){// если нет проблем
                                if(!empty($command["time"])){// если проверка пройдена
                                }else $error = $isEdit ? 22 : 5;
                            };
                            // проверяем корректность указания даты
                            if(empty($error)){// если нет проблем
                                if($command["date"] > 0){// если проверка пройдена
                                }else $error = $isEdit ? 22 : 6;
                            };
                            // проверяем корректность указания времени
                            if(empty($error)){// если нет проблем
                                if($command["time"] > 0){// если проверка пройдена
                                }else $error = $isEdit ? 22 : 7;
                            };
                            // проверяем что указан рейд
                            if(empty($error)){// если нет проблем
                                $raid = null;// сбрасываем значение
                                if(!empty($command["raid"])){// если проверка пройдена
                                    $raid = $raids->get($command["raid"]);
                                }else $error = $isEdit ? 22 : 8;
                            };
                            // проверяем ограничения по времени записи
                            if(empty($error)){// если нет проблем
                                $flag = false;// есть ли необходимые права для выполнения действия
                                if(!$flag) $permission = $app["fun"]["getPermission"]("channel", $message["author"]["id"], $channel["id"], $guild["id"]);
                                $flag = ($flag or ($permission & $app["val"]["discordUserPermission"]) == $app["val"]["discordUserPermission"]);
                                if(// множественное условие
                                    ($flag or $command["time"] >= $message["timestamp"] + $app["val"]["eventTimeClose"])
                                    and $command["time"] > $message["timestamp"] - $app["val"]["eventTimeDelete"]
                                    and $command["time"] < $message["timestamp"] + $app["val"]["eventTimeAdd"]
                                ){// если проверка пройдена
                                }else $error = 9;
                            };
                            // проверяем возможность использовать роль в рейде
                            if(empty($error) and !empty($role)){// если нужно выполнить
                                $limit = $raid[$role["key"]];
                                if($limit > -1){// если проверка пройдена
                                }else $error = 10;
                            };
                            // проверяем ограничивающий фильтр в имени канала
                            if(empty($error)){// если нет проблем
                                $count = array();// счётчик элиментов
                                // считаем количество записей
                                for($i = 0, $iLen = $types->length; $i < $iLen; $i++){
                                    $key = $types->key($i);// получаем ключевой идентификатор по индексу
                                    $item = $types->get($key);// получаем элимент по идентификатору
                                    if(preg_match($item["filter"], $channel["name"], $list)){// если есть совподение
                                        // выполняем подсчёт элиментов
                                        if(!isset($count["raid"])) $count["raid"] = 0;
                                        if(!isset($count["channel"])) $count["channel"] = 0;
                                        if($item["key"] == $raid["type"]) $count["raid"]++;
                                        $count["channel"]++;
                                    };
                                };
                                if(// множественное условие
                                    empty($count["channel"])
                                    or !empty($count["raid"])
                                ){// если проверка пройдена
                                }else $error = 11;
                            };
                            // определяем контекст пользователя
                            if(empty($error)){// если нет проблем
                                if($command["user"] == $app["val"]["discordAllUser"]) $uid = null;
                                else if(!empty($command["user"])) $uid = $command["user"];
                                else $uid = $message["author"]["id"];// автор сообщения
                                $member = $uid ? $app["fun"]["getСache"]("member", $uid, $guild["id"]) : null;
                                if($member and !$member["user"]["bot"]){// если проверка пройдена
                                }else if(!$isEdit) $error = 12;
                            };
                            // считаем записи и проверяем лимиты
                            if(empty($error) and !$isEdit){// если нужно выполнить
                                $count = array();// счётчик элиментов
                                for($i = 0, $iLen = $events->length; $i < $iLen; $i++){
                                    $id = $events->key($i);// получаем ключевой идентификатор по индексу
                                    $event = $events->get($id);// получаем элимент по идентификатору
                                    // выполняем подсчёт элиментов
                                    if(!isset($count["time"])) $count["time"] = 0;
                                    if(!isset($count["raid"])) $count["raid"] = 0;
                                    if(!isset($count["item"])) $count["item"] = 0;
                                    if(// множественное условие
                                        $event["channel"] == $channel["id"]
                                        and $event["guild"] == $guild["id"]
                                    ){// если нужно посчитать счётчик
                                        // в разрезе колличества записей
                                        if(// множественное условие
                                            $event["raid"] == $raid["key"]
                                            and $event["time"] == $command["time"]
                                        ){// если рейд и время совпадает
                                            $count["item"]++;
                                        };
                                        // в разрезе пользователя
                                        if(// множественное условие
                                            $event["user"] == $member["user"]["id"]
                                            and $event["time"] == $command["time"]
                                        ){// если пользователь и время совпадает
                                            $count["time"]++;
                                            if($event["raid"] == $raid["key"]){// если рейд совпадает
                                                $count["raid"]++;
                                            };
                                        };
                                    };
                                };
                                if(// множественное условие
                                    $count["raid"] < $app["val"]["eventRaidLimit"]
                                    and $count["time"] < $app["val"]["eventTimeLimit"]
                                ){// если проверка пройдена
                                }else $error = 13;
                            };
                            // проверяем права на создание записи от других пользователей
                            if(empty($error) and (!$member or $member["user"]["id"] != $message["author"]["id"])){// если нужно выполнить
                                $flag = (!empty($leader) and $message["author"]["id"] == $leader);// есть ли необходимые права для выполнения действия
                                if(!$flag) $permission = $app["fun"]["getPermission"]("channel", $message["author"]["id"], $channel["id"], $guild["id"]);
                                $flag = ($flag or ($permission & $app["val"]["discordUserPermission"]) == $app["val"]["discordUserPermission"]);
                                if($flag){// если проверка пройдена
                                }else $error = 14;
                            };
                            // проверяем права на создание первой записи
                            if(empty($error) and empty($count["item"]) and !$isEdit){// если нужно выполнить
                                $flag = false;// есть ли необходимые права для выполнения действия
                                if(!$flag) $permission = $app["fun"]["getPermission"]("channel", $message["author"]["id"], $channel["id"], $guild["id"]);
                                $flag = ($flag or ($permission & $app["val"]["discordCreatePermission"]) == $app["val"]["discordCreatePermission"]);
                                if($flag){// если проверка пройдена
                                }else $error = 15;
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
                                        if(!$flag) $permission = $app["fun"]["getPermission"]("channel", $message["author"]["id"], $channel["id"], $guild["id"]);
                                        $flag = ($flag or ($permission & $app["val"]["discordCreatePermission"]) == $app["val"]["discordCreatePermission"]);
                                        if($flag){// если проверка пройдена
                                            $unit["repeat"] = $value;
                                        }else $error = 16;
                                    case "leader":// лидер
                                        $unit["leader"] = true;
                                        break;
                                    case "member":// участник
                                        $unit["leader"] = false;
                                        break;
                                    case "reject":// нет
                                        $value = 0;
                                    case "accept":// принят
                                        $flag = (!empty($leader) and $message["author"]["id"] == $leader);// есть ли необходимые права для выполнения действия
                                        if(!$flag) $permission = $app["fun"]["getPermission"]("channel", $message["author"]["id"], $channel["id"], $guild["id"]);
                                        $flag = ($flag or ($permission & $app["val"]["discordUserPermission"]) == $app["val"]["discordUserPermission"]);
                                        if($flag){// если проверка пройдена
                                            $unit["accept"] = !!$value;
                                        }else $error = 17;
                                        break;
                                    case "":// опция не указана
                                        break;
                                    default:// не известная опция
                                        $error = 18;
                                };
                            };
                            // обрабатываем комментарий
                            if(empty($error)){// если нет проблем
                                if("-" == $command["comment"]) $command["comment"] = $unit["comment"] = "";
                                else if(mb_strlen($command["comment"])) $unit["comment"] = $command["comment"];
                            };
                            // изменяем данные в базе данных
                            if(empty($error) and $isEdit){// если нужно выполнить
                                $index = 0;// счётчик количества изменённых записей
                                $flag = false;// есть ли необходимые права для выполнения действия
                                if(!$flag) $permission = $app["fun"]["getPermission"]("channel", $message["author"]["id"], $channel["id"], $guild["id"]);
                                $flag = ($flag or ($permission & $app["val"]["discordUserPermission"]) == $app["val"]["discordUserPermission"]);
                                for($i = $events->length - 1; $i > - 1 and empty($status); $i--){
                                    $id = $events->key($i);// получаем ключевой идентификатор по индексу
                                    $event = $events->get($id);// получаем элимент по идентификатору
                                    $time = strtotime(date("d.m.Y", $event["time"]));
                                    if(// множественное условие
                                        $event["channel"] == $channel["id"]
                                        and $event["guild"] == $guild["id"]
                                        and (empty($member) or $event["user"] == $member["user"]["id"])
                                        and (empty($command["time"]) or $event["time"] == $command["time"])
                                        and (empty($command["date"]) or $time == $command["date"])
                                        and (empty($raid) or $event["raid"] == $raid["key"])
                                        and ($flag or $event["time"] >= $message["timestamp"] + $app["val"]["eventTimeClose"])
                                    ){// если нужно изменить запись в событии
                                        if(!empty($raid)) $unit["raid"] = $raid["key"];
                                        if(!empty($role)) $unit["role"] = $role["key"];
                                        if(!empty($command["time"])) $unit["time"] = $command["time"];
                                        if(// множественное условие
                                            !isset($unit["accept"]) and !empty($role) and $event["role"] != $role["key"]
                                            and (empty($leader) or $message["author"]["id"] != $leader)
                                        ){// если нужно сбросить подтверждение
                                            $unit["accept"] = false;
                                        };
                                        if($events->set($id, null, $unit)){// если данные успешно изменены
                                            $isEventsUpdate = true;// были обновлены данные в базе данных
                                            $index++;// увиличиваем счётчик количества изменённых записей
                                        }else $status = 309;// не удалось записать данные в базу данных
                                    };
                                };
                                if($index){// если изменены записи
                                }else $error = 22;
                            };
                            // добавляем данные в базу данных
                            if(empty($error) and !$isEdit){// если нужно выполнить
                                $index = 0;// счётчик количества добавленных записей
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
                                    $index++;// увиличиваем счётчик количества добавленных записей
                                }else $status = 309;// не удалось записать данные в базу данных
                                if($index){// если добавлены записи
                                }else $error = 22;
                            };
                            break;
                        case "-":// удалить запись
                            // проверяем корректность указания времени
                            if(empty($error)){// если нет проблем
                                if(// множественное условие
                                    ($command["date"] > 0 or empty($command["date"]))
                                    and ($command["time"] > 0 or empty($command["time"]))
                                ){// если проверка пройдена
                                }else $error = 19;
                            };
                            // проверяем ограничения по времени записи
                            if(empty($error)){// если нет проблем
                                $flag = false;// есть ли необходимые права для выполнения действия
                                if(!$flag) $permission = $app["fun"]["getPermission"]("channel", $message["author"]["id"], $channel["id"], $guild["id"]);
                                $flag = ($flag or ($permission & $app["val"]["discordUserPermission"]) == $app["val"]["discordUserPermission"]);
                                if(// множественное условие
                                    ($flag or $command["time"] >= $message["timestamp"] + $app["val"]["eventTimeClose"])
                                    or (empty($command["date"]) or empty($command["time"]))
                                ){// если проверка пройдена
                                }else $error = 20;
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
                            // определяем контекст пользователя
                            if(empty($error)){// если нет проблем
                                if($command["user"] == $app["val"]["discordAllUser"]) $uid = null;
                                else if(!empty($command["user"])) $uid = $command["user"];
                                else $uid = $message["author"]["id"];// автор сообщения
                                $user = $uid ? $app["fun"]["getСache"]("user", $uid) : null;
                            };
                            // проверяем права на удаление записей других пользователей
                            if(empty($error) and (!$user or $user["id"] != $message["author"]["id"])){// если нужно выполнить
                                $flag = (!empty($leader) and $message["author"]["id"] == $leader);// есть ли необходимые права для выполнения действия
                                if(!$flag) $permission = $app["fun"]["getPermission"]("channel", $message["author"]["id"], $channel["id"], $guild["id"]);
                                $flag = ($flag or ($permission & $app["val"]["discordUserPermission"]) == $app["val"]["discordUserPermission"]);
                                if($flag){// если проверка пройдена
                                }else $error = 21;
                            };
                            // удаляем записи событий
                            if(empty($error)){// если нет проблем
                                $index = 0;// счётчик количества удалённых записей
                                $flag = false;// есть ли необходимые права для выполнения действия
                                if(!$flag) $permission = $app["fun"]["getPermission"]("channel", $message["author"]["id"], $channel["id"], $guild["id"]);
                                $flag = ($flag or ($permission & $app["val"]["discordUserPermission"]) == $app["val"]["discordUserPermission"]);
                                for($i = $events->length - 1; $i > - 1 and empty($status); $i--){
                                    $id = $events->key($i);// получаем ключевой идентификатор по индексу
                                    $event = $events->get($id);// получаем элимент по идентификатору
                                    $time = strtotime(date("d.m.Y", $event["time"]));
                                    if(// множественное условие
                                        $event["channel"] == $channel["id"]
                                        and $event["guild"] == $guild["id"]
                                        and (empty($user) or $event["user"] == $user["id"])
                                        and (empty($command["time"]) or $event["time"] == $command["time"])
                                        and (empty($command["date"]) or $time == $command["date"])
                                        and (empty($role) or $event["role"] == $role["key"])
                                        and (empty($raid) or $event["raid"] == $raid["key"])
                                        and ($flag or $event["time"] >= $message["timestamp"] + $app["val"]["eventTimeClose"])
                                    ){// если нужно удалить запись из событий
                                        if($events->set($id)){// если данные успешно удалены
                                            $isEventsUpdate = true;// были обновлены данные в базе данных
                                            $index++;// увиличиваем счётчик количества удалённых записей
                                        }else $status = 309;// не удалось записать данные в базу данных
                                    };
                                };
                                if($index){// если удалены записи
                                }else $error = 22;
                            };
                            break;
                        case "":// команда не указана
                            if($hasTalkPermission){// если обсуждения разрешены
                                $isNeedProcessing = false;
                                break;
                            };
                        default:// не известная команда
                            $error = 1;
                    };
                };
                // информируем пользователя
                if(!empty($error)){// если есть проблема
                    // готовим контент для личного сообщения
                    if(empty($status)){// если нет ошибок
                        $feedback = $feedbacks->get($error);// получаем элимент по идентификатору
                        if(!empty($feedback)) $content = template($feedback[$language], $command);
                        if(empty($content)) $content = "Ваше сообщение не обработано из-за непредвиденной проблемы.";
                    };
                    // получаем идентификатор личного канала
                    if(empty($status)){// если нет ошибок
                        $user = $app["fun"]["getСache"]("user", $message["author"]["id"]);
                        if($user and !$user["bot"] and isset($user["channels"][0])){// если личный канал существует
                            $item = $user["channels"][0];// получаем очередной элимент
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
                $index = 0;// индекс элимента в новом массиве
                $items = array();// список записей событий
                for($i = 0, $iLen = $events->length; $i < $iLen; $i++){
                    // обробатываем каждую запись
                    $id = $events->key($i);// получаем ключевой идентификатор по индексу
                    $item = $events->get($id);// получаем элимент по идентификатору
                    if($item["guild"] == $guild["id"]){// если нужно выполнить дополнительные проверки
                        // проверяем разрешения для сводного расписания
                        $flag = $item["channel"] == $channel["id"];// есть ли необходимые права для выполнения действия
                        if(!$flag) $permission = $app["fun"]["getPermission"]("member", $app["val"]["discordBotId"], $item["channel"], $guild["id"]);
                        $flag = ($flag or ($permission & $app["val"]["discordListPermission"]) == $app["val"]["discordListPermission"]);
                        if($flag and ($isConsolidated or $item["channel"] == $channel["id"])){// если нужно включить запись в уведомление
                            // проверяем доступность этой роли в рейде
                            $raid = $raids->get($item["raid"]);
                            $limit = $raid[$item["role"]];
                            if($limit > -1){// если эта роль доступна в рейде
                                // сохраняем элимент в массив
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
                $tips = array();// подсказки для записи
                $limits = array();// лимиты для рейдов
                $counts = array();// счётчики для групп
                $leaders = array();// лидеры для групп
                $comments = array();// комментарии для групп
                $repeats = array();// повторяемость события
                for($i = 0, $iLen = count($items); $i < $iLen; $i++){
                    $item = $items[$i];// получаем очередной элимент
                    $unit = $events->get($item["id"]);
                    $raid = $raids->get($item["raid"]);
                    // создаём структуру подсказок для записи в события
                    $time = strtotime(date("d.m.Y", $item["time"]));
                    $tip = &$tips;// советы по рейдам
                    foreach(array($item["raid"], $time, $item["time"]) as $key){
                        if(!isset($tip[$key])) $tip[$key] = array();
                        $tip = &$tip[$key];// получаем ссылку
                    };
                    // вычисляем лимиты для подсказок
                    if(!isset($tip[$item["role"]])){// если нужно вычислить лимит для роли
                        for($j = 0, $jLen = $roles->length; $j < $jLen; $j++){
                            $role = $roles->get($roles->key($j));// получаем очередную роль
                            $limit = $raid[$role["key"]];// получаем значение лимита
                            if($limit > -1 and !isset($tip[$role["key"]])) $tip[$role["key"]] = $limit;
                        };
                    };
                    // считаем с учётом роли
                    $tip[$item["role"]]--;
                    // создаём структуру счётчика
                    $count = &$counts;// счётчик элиментов
                    foreach(array($item["time"], $item["raid"], $any) as $key){
                        if(!isset($count[$key])) $count[$key] = array();
                        $count = &$count[$key];// получаем ссылку
                    };
                    // считаем без учётом группы
                    if(!isset($count[$item["role"]])) $count[$item["role"]] = 0;
                    if(!isset($count[$all])) $count[$all] = 0;
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
                    $count = &$counts;// счётчик элиментов
                    foreach(array($item["time"], $item["raid"], $group) as $key){
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
                    foreach(array($item["time"], $item["raid"]) as $key){
                        if(!isset($leader[$key])) $leader[$key] = array();
                        $leader = &$leader[$key];// получаем ссылку
                    };
                    // определяем лидера группы
                    if(!isset($leader[$group])) $leader[$group] = $i;
                    $limit = $limits[$item["raid"]];// получаем значение лимита
                    if($i != $leader[$group]){// если лидер не текущий элимент
                        if(!$items[$leader[$group]]["leader"]){// если лидер выбран системой
                            if($item["leader"]) $leader[$group] = $i;
                            else if($count[$all] == $limit) $items[$leader[$group]]["leader"] = true;
                        }else if($item["leader"]) $item["leader"] = false;
                    }else if($count[$all] == $limit) $item["leader"] = true;
                    // создаём структуру комментария
                    $comment = &$comments;// комментарий по группам
                    foreach(array($item["time"], $item["raid"]) as $key){
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
                    foreach(array($item["time"], $item["raid"]) as $key){
                        if(!isset($repeat[$key])) $repeat[$key] = array();
                        $repeat = &$repeat[$key];// получаем ссылку
                    };
                    // определяем повторяемость события
                    if(!isset($repeat[$any])) $repeat[$any] = false;
                    if(!isset($repeat[$group])) $repeat[$group] = false;
                    if($item["repeat"]) $repeat[$any] = true;
                    if($item["repeat"]) $repeat[$group] = true;
                    // расширяем свойства элимента
                    $item["title"] = date("d", $item["time"]) . " " . $months->get(date("n", $item["time"]), $language);
                    $item["day"] = $dates->get(mb_strtolower(date("l", $item["time"])), $language);
                    $item["group"] = $group;
                    // сохраняем элимент в массив
                    $items[$i] = $item;
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
                $before = null;// предыдущий элимент
                $mLen = 0;// длина текущего уведомления
                $bLen = 0;// длина текущего блока
                $line = "";// сбрасываем значение строки
                $position = 0;// позиция пользователя в рейде
                if(count($items)){// если есть элименты для отображения
                    for($i = 0, $iLen = count($items); $i < $iLen; $i++){
                        $item = $items[$i];// получаем очередной элимент
                        $raid = $raids->get($item["raid"]);
                        $role = $roles->get($item["role"]);
                        $type = $types->get($raid["type"]);
                        $limit = $limits[$item["raid"]];
                        $group = $item["group"];
                        // построчно формируем текст содержимого
                        if(// множественное условие
                            empty($before)
                            or $before["title"] != $item["title"]
                        ){// если нужно добавить информацию о дате
                            // формируем блок и контент    
                            $bLen = 0;// длина текущего блока
                            $flag = count($lines);
                            if($flag){// если есть строка данных
                                $line = $blank;// пустая строка
                                array_push($lines, $line);
                            };
                            if($flag){// если сформирован блок
                                if($mLen) $mLen += mb_strlen($delim);
                                $block = implode($delim, $lines);
                                $bLen = mb_strlen($block);
                            };
                            if(// множественное условие
                                count($blocks)
                                and $mLen + $bLen > $app["val"]["discordMessageLength"]
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
                            $line = "**```" . $item["title"] . " - " . $item["day"] . "```**";
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
                            $count = &$counts;// счётчик элиментов
                            foreach(array($item["time"], $item["raid"], $group) as $key){
                                $count = &$count[$key];// получаем ссылку
                            };
                            // получаем комментарий из структуру
                            $comment = &$comments;// комментарий по группам
                            foreach(array($item["time"], $item["raid"]) as $key){
                                $comment = &$comment[$key];// получаем ссылку
                            };
                            // получаем повторяемость из структуру
                            $repeat = &$repeats;// повторяемость по группам
                            foreach(array($item["time"], $item["raid"]) as $key){
                                $repeat = &$repeat[$key];// получаем ссылку
                            };
                            // формируем блок и контент    
                            $bLen = 0;// длина текущего блока
                            $flag = (count($lines) and $before["title"] == $item["title"]);
                            $flag = ($flag and (1 == $group or $limit and $count[$all] == $limit));
                            if($flag and !$isConsolidated){// если есть строка данных
                                $line = $blank;// пустая строка
                                array_push($lines, $line);
                            };
                            if($flag){// если сформирован блок
                                if($mLen) $mLen += mb_strlen($delim);
                                $block = implode($delim, $lines);
                                $bLen = mb_strlen($block);
                            };
                            if(// множественное условие
                                count($blocks)
                                and $mLen + $bLen > $app["val"]["discordMessageLength"]
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
                                $logotype = ($limit and $count[$all] < $limit) ? $type["processing"] : $type["complete"];
                                $line = ((!empty($logotype) and !$isConsolidated) ? $logotype . " " : "");
                                $line .= "**" . date("H:i", $item["time"]) . "**" . ($isConsolidated ? " —" . $type["icon"] : " - ") . "**" . $raid["key"] . "** " . $raid[$language];
                                $line .= ($isConsolidated ? " (" . mb_strtolower($type[$language]) . ")" : "");
                                $line .= (!empty($raid["chapter"]) ? " **DLC " . $raid["chapter"] . "**" : "");
                                $line .= (($limit and !$isConsolidated) ? " (" . $count[$all] . " из " . $limit . ")" : "");
                                $line .= ((1 == $group and $repeat[$any] and !$isConsolidated) ? "  " . $additions->get("daily", "icon") : "");
                                $line .= ($isConsolidated ? " <#" . $item["channel"] . ">" : "");
                                array_push($lines, $line);
                                $position = 1;
                            }else if((2 == $group or $before["count"] == $limit) and !$isConsolidated){// если это не полная группа
                                $line = "__Резерв:__";
                                array_push($lines, $line);
                            };
                            if($flag and !empty($comment[$any]) and !$isConsolidated){// если есть комментарий
                                $line = "__Комментарий__: " . $comment[$any];
                                array_push($lines, $line);
                            };
                            // формируем подсказку для записи
                            if($flag and !$isConsolidated){// если это основная или другая полная группа
                                $time = strtotime(date("d.m.Y", $item["time"]));
                                // получаем подсказку из структуру
                                $tip = &$tips;// советы по рейдам
                                foreach(array($item["raid"], $time, $item["time"]) as $key){
                                    $tip = &$tip[$key];// получаем ссылку
                                };
                                // сортируем роли в подсказке
                                arsort($tip);// сортируем по убыванию свободных мест
                                // определяем уровень детализации подсказки
                                if(count($tips[$item["raid"]][$time]) > 1) $detail = 3;     // время
                                else if(count($tips[$item["raid"]]) > 1) $detail = 2;       // дата
                                else if(count($tips) > 1) $detail = 1;                      // рейд
                                else $detail = 0;                                           // роль
                                // готовим текст подсказок по уровеню детализации
                                $list = array();// список подсказок для записи
                                foreach($tip as $key => $value){// пробигаемся по ролям
                                    $value = "+" . mb_strtolower($roles->get($key, $language));
                                    if($detail >= 2) $value .= " " . date("d.m.y", $item["time"]);
                                    if($detail >= 3) $value .= " " . date("H:i", $item["time"]);
                                    if($detail >= 1) $value .= " " . $raid["key"];
                                    $value = "**" . $value . "**";
                                    array_push($list, $value);
                                    if($detail >= 1 and mb_strlen($raid["key"]) > 4) break;
                                    if($detail >= 2) break;
                                };
                                // объединяем текст подсказок в одну строку
                                $value = "";// сбрасываем значение
                                for($j = 0, $jLen = count($list); $j < $jLen; $j++){
                                    $key = $j ? ($j == $jLen - 1 ? " или " : ", ") : "";
                                    $value .= $key . $list[$j];
                                };
                                // добавляем отдельной строкой
                                $line = "Записаться " . $value;
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
                            $line = "__Кандидаты:__";
                            array_push($lines, $line);
                        };
                        // формируем строки данных
                        if(!$isConsolidated){// если это не сводное расписание
                            $icon = $item["accept"] ? $additions->get("accept", "icon") : null;
                            $line = "**" . str_pad($position, 2, "0", STR_PAD_LEFT) . "** - " . $role["synonym"] . ": <@!" . $item["user"] . ">" . ($icon ? $icon : "");
                            $key = mb_strtolower($additions->get("leader", $language));// идентификатор обозначающий лидера
                            $value = $item["comment"];// комментарий для обработки
                            for($j = -1, $jLen = mb_strlen($key); $j !== false; $j = mb_stripos($value, $key)){
                                if($j > -1) $value = mb_substr($value, 0,  $j) .  mb_substr($value, $j + $jLen);
                            };
                            $value = mb_strtolower(mb_substr($value, 0, 1)) . mb_substr($value, 1);
                            $value = trim(mb_substr($value, 0, $app["val"]["eventNoteLength"]));
                            $flag = (1 == $group or $limit and $count[$all] == $limit);
                            if($item["leader"] and $flag) $line .= (!$icon ? " " : "") . "- " . $key;
                            else if($value) $line .= (!$icon ? " " : "") . "- " . $value;
                            array_push($lines, $line);
                            $position++;
                        };
                        // сохраняем предыдущий элимент
                        $before = $item;// копируем значение
                        $before["count"] = $count[$all];
                    };
                }else{// если нет не одной записи
                    $line = "Ещё нет событий, но скоро будут.";
                    array_push($lines, $line);
                };
                // формируем блок и контент    
                $bLen = 0;// длина текущего блока
                $flag = count($lines);
                if($flag and count($items)){// если нужно выполнить
                    $line = $blank;// пустая строка
                    array_push($lines, $line);
                };
                if($flag){// если сформирован блок
                    if($mLen) $mLen += mb_strlen($delim);
                    $block = implode($delim, $lines);
                    $bLen = mb_strlen($block);
                };
                if(// множественное условие
                    count($blocks)
                    and $mLen + $bLen > $app["val"]["discordMessageLength"]
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
                    $item = $channel["messages"][$i];// получаем очередной элимент
                    if($item["author"]["id"] == $app["val"]["discordBotId"]){// если это сообщение бота
                        if($item["type"]){// если это не контентное сообщение
                            // удаляем сообщение
                            $uri = "/channels/" . $channel["id"] . "/messages/" . $item["id"];
                            $data = $app["fun"]["apiRequest"]("delete", $uri, null, $code);
                            if(204 == $code or 404 == $code){// если запрос выполнен успешно
                                $app["fun"]["delСache"]("message", $item["id"], $channel["id"], $guild["id"]);
                            }else $status = 306;// не удалось получить корректный ответ от удаленного сервера
                        }else{// если это контентное сообщение
                            // добавляем сообщение в список
                            array_push($items, $item);// добавляем начало списка
                            $count++;// увеличиваем счётчик сообщений
                        };
                    }else if(!$isNeedProcessing or $message["id"] != $item["id"]){// если это не текущее сообщение
                        // добавляем пустой элимент в список
                        array_push($items, null);// добавляем в начало списка
                    };
                };
                // удаляем лишнии элименты из сформированного списка 
                $now = microtime(true);// текущее время
                $flag = false;// нужно ли удалить сообщение
                $index = 0;// текущее значение проверенных не пустых сообщений
                $after = count($contents) > $count;// слещующее сообщение в списке
                $time = $after ? $now : 0;// время более нового сообщения
                for($i = count($items) - 1; $i > -1 and empty($status); $i--){
                    $item = $items[$i];// получаем очередной элимент
                    $flag = ($flag or empty($item) and !empty($after));
                    if(!empty($item)){// если присутствует сообщение для обработки
                        $flag = ($flag or $time - $item["timestamp"] > $app["val"]["discordMessageTime"]);
                        if($flag or $index >= count($contents)){// если нужно удалить сообщение
                            // удаляем сообщение
                            $uri = "/channels/" . $channel["id"] . "/messages/" . $item["id"];
                            $data = $app["fun"]["apiRequest"]("delete", $uri, null, $code);
                            if(204 == $code or 404 == $code){// если запрос выполнен успешно
                                $app["fun"]["delСache"]("message", $item["id"], $channel["id"], $guild["id"]);
                                array_splice($items, $i, 1);
                                $count--;// уменьшаем счётчик сообщений
                            }else $status = 306;// не удалось получить корректный ответ от удаленного сервера
                        }else{// если не нужно удалять сообщение
                            $time = $item["timestamp"];// копируем время
                            $after = $item;// копируем элимент
                            $index++;// увеличиваем индекс
                        };
                    }else{// если присутствует пустой элимент
                        $after = $item;// копируем элимент
                        array_splice($items, $i, 1);
                    };
                };
            };
            // создаём новые или изменяем имеющиеся сообщения бота
            if(empty($status) and ($hasMainPermission or $hasListPermission)){// если нужно выполнить
                for($i = 0, $iLen = count($contents); $i < $iLen and empty($status); $i++){
                    $content = $contents[$i];// получаем очередной элимент
                    $item = isset($items[$i]) ? $items[$i] : null;
                    if(empty($item)){// если нужно опубликовать новое сообщение
                        // отправляем новое сообщение
                        $uri = "/channels/" . $channel["id"] . "/messages";
                        $data = array("content" => $content);
                        $data = $app["fun"]["apiRequest"]("post", $uri, $data, $code);
                        if(200 == $code){// если запрос выполнен успешно
                            $app["fun"]["setСache"]("message", $data, $channel["id"], $guild["id"]);
                        }else $status = 306;// не удалось получить корректный ответ от удаленного сервера
                    }else if($item["content"] != $content){// если нужно изменить старое сообщение
                        // изменяем старое сообщение
                        $uri = "/channels/" . $channel["id"] . "/messages/" . $item["id"];
                        $data = array("content" => $content);
                        $data = $app["fun"]["apiRequest"]("patch", $uri, $data, $code);
                        if(200 == $code){// если запрос выполнен успешно
                            $app["fun"]["setСache"]("message", $data, $channel["id"], $guild["id"]);
                        }else $status = 306;// не удалось получить корректный ответ от удаленного сервера
                    };
                };
            };
            // удаляем сообщение переданное на обработку
            if(empty($status) and $isNeedProcessing and $hasMainPermission){// если нужно выполнить
                // удаляем сообщение
                $uri = "/channels/" . $channel["id"] . "/messages/" . $message["id"];
                $data = $app["fun"]["apiRequest"]("delete", $uri, null, $code);
                if(204 == $code or 404 == $code){// если запрос выполнен успешно
                    $app["fun"]["delСache"]("message", $message["id"], $channel["id"], $guild["id"]);
                }else $status = 306;// не удалось получить корректный ответ от удаленного сервера
            };
            // обновляем сводное расписание в гильдии
            if(empty($status) and $isEventsUpdate and !$isConsolidated){// если нужно выполнить
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
        "setСache" => function&($type, $data){// добавляем данные в кеш
        //@param $type {string} - тип данных для кеширования
        //@param $data {array} - данные в виде массива 
        //@param ...$id {string} - идентификаторы разных уровней
        //@return {array|null} - ссылка на элимент данныx или null
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
                    // определяем ссылку на элимента
                    if(!$error){// если нет ошибок
                        $key = "guilds";// задаём ключ
                        $parent = &$app["cache"];
                        if(!is_null($parent)){// если есть родительский элимент
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
                                $app["fun"]["setСache"]("member", $data[$key][$i], $gid);
                            };
                        };
                        // список каналов
                        $key = "channels";// задаём ключ
                        if(isset($data[$key])){// если существует
                            for($i = 0, $iLen = count($data[$key]); $i < $iLen; $i++){
                                $app["fun"]["setСache"]("channel", $data[$key][$i], $gid, null);
                            };
                        };
                    };
                    // присваеваем ссылку на элимент
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
                    // определяем ссылку на элимента
                    if(!$error){// если нет ошибок
                        $key = "users";// задаём ключ
                        $parent = &$app["cache"];
                        if(!is_null($parent)){// если есть родительский элимент
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
                        }else if(!isset($unit[$key])){// если не задано
                            $unit[$key] = false;// по умолчанию
                        };
                        // список каналов
                        $key = "channels";// задаём ключ
                        if(isset($data[$key])){// если существует
                            for($i = 0, $iLen = count($data[$key]); $i < $iLen; $i++){
                                $app["fun"]["setСache"]("channel", $data[$key][$i], null, $uid);
                            };
                        };
                    };
                    // присваеваем ссылку на элимент
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
                    // определяем ссылку на элимента
                    if(!$error){// если нет ошибок
                        $key = "members";// задаём ключ
                        $parent = &$app["fun"]["getСache"]("guild", $gid);
                        if(!is_null($parent)){// если есть родительский элимент
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
                    // присваеваем ссылку на элимент
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
                            and (0 == $data["type"] or 1 == $data["type"])
                            and (isset($data["recipients"][0]["id"]) ? $uid == $data["recipients"][0]["id"] : 1 != $data["type"])
                            and 1 == count(get_val($data, "recipients", array($uid)))
                        ){// если проверка пройдена
                        }else $error = 3;
                    };
                    // определяем ссылку на элимента
                    if(!$error){// если нет ошибок
                        $key = "channels";// задаём ключ
                        switch(true){// по идентификаторам
                            case !empty($gid): $parent = &$app["fun"]["getСache"]("guild", $gid); break;
                            case !empty($uid): $parent = &$app["fun"]["getСache"]("user", $uid); break;
                            default: $parent = null;
                        };
                        if(!is_null($parent)){// если есть родительский элимент
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
                                $app["fun"]["setСache"]("message", $data[$key][$i], $cid, $gid);
                            };
                        };
                    };
                    // присваеваем ссылку на элимент
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
                    // определяем ссылку на элимента
                    if(!$error){// если нет ошибок
                        $key = "messages";// задаём ключ
                        $parent = &$app["fun"]["getСache"]("channel", $cid, $gid, null);
                        if(!is_null($parent)){// если есть родительский элимент
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
                    };
                    // сортируем элименты
                    if(!$error){// если нет ошибок
                        $key = "messages";// задаём ключ
                        usort($parent[$key], function($a, $b){// сортировка
                            $value = 0;// начальное значение
                            if(!$value and $a["timestamp"] != $b["timestamp"]) $value = $a["timestamp"] < $b["timestamp"] ? 1 : -1;
                            if(!$value and $a["id"] != $b["id"]) $value = $a["id"] < $b["id"] ? 1 : -1;
                            // возвращаем результат
                            return $value;
                        });
                    };
                    // присваеваем ссылку на элимент
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
        "getСache" => function&($type){// получает данные из кеша
        //@param $type {string} - тип данных для кеширования
        //@param ...$id {string} - идентификаторы разных уровней
        //@return {array|null} - ссылка на элимент данныx или null
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
                    // определяем ссылку на элимента
                    if(!$error){// если нет ошибок
                        $key = "guilds";// задаём ключ
                        $parent = &$app["cache"];
                        if(!is_null($parent)){// если есть родительский элимент
                            if(!isset($parent[$key])) $parent[$key] = array();
                            for($i = 0, $iLen = count($parent[$key]); $i < $iLen; $i++){
                                if($parent[$key][$i]["id"] == $gid) break;
                            };
                            if(isset($parent[$key][$i])) $unit = &$parent[$key][$i];
                        }else $error = 4;
                    };
                    // получаем элимент через api
                    if(!$error and !$unit){// если нужно выполнить
                        $uri = "/guilds/" . $gid;
                        $data = $app["fun"]["apiRequest"]("get", $uri, null, $code);
                        if(200 == $code){// если запрос выполнен успешно
                            $unit = &$app["fun"]["setСache"]($type, $data);
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
                                $app["fun"]["setСache"]("channel", $data[$i], $gid, null);
                            };
                        }else $error = 7;
                    };
                    // присваеваем ссылку на элимент
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
                    // определяем ссылку на элимента
                    if(!$error){// если нет ошибок
                        $key = "users";// задаём ключ
                        $parent = &$app["cache"];
                        if(!is_null($parent)){// если есть родительский элимент
                            if(!isset($parent[$key])) $parent[$key] = array();
                            for($i = 0, $iLen = count($parent[$key]); $i < $iLen; $i++){
                                if($parent[$key][$i]["id"] == $uid) break;
                            };
                            if(isset($parent[$key][$i])) $unit = &$parent[$key][$i];
                        }else $error = 4;
                    };
                    // получаем элимент через api
                    if(!$error and !$unit){// если нужно выполнить
                        $uri = "/users/" . $uid;
                        $data = $app["fun"]["apiRequest"]("get", $uri, null, $code);
                        if(200 == $code){// если запрос выполнен успешно
                            $unit = &$app["fun"]["setСache"]($type, $data);
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
                            $app["fun"]["setСache"]("channel", $data, null, $uid);
                        }else $error = 7;
                    };
                    // присваеваем ссылку на элимент
                    if(!$error){// если нет ошибок
                        $cache = &$unit;
                    };
                    break;
                case "member":// участник
                    // проверяем наличее параметров
                    if(!$error){// если нет ошибок
                        if(// множественное условие
                            $argLength > $argFirst
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
                    // определяем ссылку на элимента
                    if(!$error){// если нет ошибок
                        $key = "members";// задаём ключ
                        $parent = &$app["fun"]["getСache"]("guild", $gid);
                        if(!is_null($parent)){// если есть родительский элимент
                            if(!isset($parent[$key])) $parent[$key] = array();
                            for($i = 0, $iLen = count($parent[$key]); $i < $iLen; $i++){
                                if($parent[$key][$i]["user"]["id"] == $uid) break;
                            };
                            if(isset($parent[$key][$i])) $unit = &$parent[$key][$i];
                        }else $error = 4;
                    };
                    // получаем элимент через api
                    if(!$error and !$unit){// если нужно выполнить
                        $uri = "/guilds/" . $gid . "/members/" . $uid;
                        $data = $app["fun"]["apiRequest"]("get", $uri, null, $code);
                        if(200 == $code){// если запрос выполнен успешно
                            $unit = &$app["fun"]["setСache"]($type, $data, $gid);
                        }else $error = 5;
                    };
                    // присваеваем ссылку на элимент
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
                    // определяем ссылку на элимента
                    if(!$error){// если нет ошибок
                        $key = "channels";// задаём ключ
                        switch(true){// по идентификаторам
                            case !empty($gid): $parent = &$app["fun"]["getСache"]("guild", $gid); break;
                            case !empty($uid): $parent = &$app["fun"]["getСache"]("user", $uid); break;
                            default: $parent = null;
                        };
                        if(!is_null($parent)){// если есть родительский элимент
                            if(!isset($parent[$key])) $parent[$key] = array();
                            for($i = 0, $iLen = count($parent[$key]); $i < $iLen; $i++){
                                if($parent[$key][$i]["id"] == $cid) break;
                            };
                            if(isset($parent[$key][$i])) $unit = &$parent[$key][$i];
                        }else $error = 4;
                    };
                    // получаем элимент через api
                    if(!$error and !$unit){// если нужно выполнить
                        $uri = "/channels/" . $cid;
                        $data = $app["fun"]["apiRequest"]("get", $uri, null, $code);
                        if(200 == $code){// если запрос выполнен успешно
                            $unit = &$app["fun"]["setСache"]($type, $data, $gid, $uid);
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
                            $data = array("limit" => 100);// максимально доступное количество
                            $data = $app["fun"]["apiRequest"]("get", $uri, $data, $code);
                            if(200 == $code){// если запрос выполнен успешно
                                if(!isset($unit[$key])) $unit[$key] = array();
                                for($i = 0, $iLen = count($data); $i < $iLen; $i++){
                                    $app["fun"]["setСache"]("message", $data[$i], $cid, $gid);
                                };
                            }else $error = 6;
                        };
                    };
                    // присваеваем ссылку на элимент
                    if(!$error){// если нет ошибок
                        $cache = &$unit;
                    };
                    break;
                case "message":// сообщение
                    // проверяем наличее параметров
                    if(!$error){// если нет ошибок
                        if(// множественное условие
                            $argLength > $argFirst + 1
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
                    // определяем ссылку на элимента
                    if(!$error){// если нет ошибок
                        $key = "messages";// задаём ключ
                        $parent = &$app["fun"]["getСache"]("channel", $cid, $gid, null);
                        if(!is_null($parent)){// если есть родительский элимент
                            if(!isset($parent[$key])) $parent[$key] = array();
                            for($i = 0, $iLen = count($parent[$key]); $i < $iLen; $i++){
                                if($parent[$key][$i]["id"] == $mid) break;
                            };
                            if(isset($parent[$key][$i])) $unit = &$parent[$key][$i];
                        }else $error = 4;
                    };
                    // получаем элимент через api
                    if(!$error and !$unit){// если нужно выполнить
                        $uri = "/channels/" . $cid . "/messages/" . $mid;
                        $data = $app["fun"]["apiRequest"]("get", $uri, null, $code);
                        if(200 == $code){// если запрос выполнен успешно
                            $unit = &$app["fun"]["setСache"]($type, $data, $cid, $gid);
                        }else $error = 5;
                    };
                    // присваеваем ссылку на элимент
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
        "delСache" => function($type){// удаляет данные из кеша
        //@param $type {string} - тип данных для кеширования
        //@param ...$id {string} - идентификаторы разных уровней
        //@return {array|null} - элимент данныx или null
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
                    // ищем ссылку на элимента
                    if(!$error){// если нет ошибок
                        $key = "guilds";// задаём ключ
                        $parent = &$app["cache"];
                        if(!is_null($parent)){// если есть родительский элимент
                            if(isset($parent[$key])){// если есть массив элементов у родителя
                                for($i = 0, $iLen = count($parent[$key]); $i < $iLen; $i++){
                                    if($parent[$key][$i]["id"] == $gid) break;
                                };
                                if(isset($parent[$key][$i])) $unit = &$parent[$key][$i];
                            };
                        }else $error = 4;
                    };
                    // удаляем ссылку на элимента
                    if(!$error){// если нет ошибок
                        $key = "guilds";// задаём ключ
                        if($unit){// если элимент существует
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
                    // ищем ссылку на элимента
                    if(!$error){// если нет ошибок
                        $key = "users";// задаём ключ
                        $parent = &$app["cache"];
                        if(!is_null($parent)){// если есть родительский элимент
                            if(isset($parent[$key])){// если есть массив элементов у родителя
                                for($i = 0, $iLen = count($parent[$key]); $i < $iLen; $i++){
                                    if($parent[$key][$i]["id"] == $uid) break;
                                };
                                if(isset($parent[$key][$i])) $unit = &$parent[$key][$i];
                            };
                        }else $error = 4;
                    };
                    // удаляем ссылку на элимента
                    if(!$error){// если нет ошибок
                        $key = "users";// задаём ключ
                        if($unit){// если элимент существует
                            $cache = array_splice($parent[$key], $i, 1)[0];
                        }else $error = 5;
                    };
                    break;
                case "member":// участник
                    // проверяем наличее параметров
                    if(!$error){// если нет ошибок
                        if(// множественное условие
                            $argLength > $argFirst
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
                    // ищем ссылку на элимента
                    if(!$error){// если нет ошибок
                        $key = "members";// задаём ключ
                        $parent = &$app["fun"]["getСache"]("guild", $gid);
                        if(!is_null($parent)){// если есть родительский элимент
                            if(isset($parent[$key])){// если есть массив элементов у родителя
                                for($i = 0, $iLen = count($parent[$key]); $i < $iLen; $i++){
                                    if($parent[$key][$i]["user"]["id"] == $uid) break;
                                };
                                if(isset($parent[$key][$i])) $unit = &$parent[$key][$i];
                            };
                        }else $error = 4;
                    };
                    // удаляем ссылку на элимента
                    if(!$error){// если нет ошибок
                        $key = "members";// задаём ключ
                        if($unit){// если элимент существует
                            $cache = array_splice($parent[$key], $i, 1)[0];
                        }else $error = 5;
                    };
                    break;
                case "channel":// канал
                    // проверяем наличее параметров
                    if(!$error){// если нет ошибок
                        if(// множественное условие
                            $argLength > $argFirst + 1
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
                            and (!empty($gid) xor!empty($uid))
                        ){// если проверка пройдена
                        }else $error = 3;
                    };
                    // ищем ссылку на элимента
                    if(!$error){// если нет ошибок
                        $key = "channels";// задаём ключ
                        switch(true){// по идентификаторам
                            case !empty($gid): $parent = &$app["fun"]["getСache"]("guild", $gid); break;
                            case !empty($uid): $parent = &$app["fun"]["getСache"]("user", $uid); break;
                            default: $parent = null;
                        };
                        if(!is_null($parent)){// если есть родительский элимент
                            if(isset($parent[$key])){// если есть массив элементов у родителя
                                for($i = 0, $iLen = count($parent[$key]); $i < $iLen; $i++){
                                    if($parent[$key][$i]["id"] == $cid) break;
                                };
                                if(isset($parent[$key][$i])) $unit = &$parent[$key][$i];
                            };
                        }else $error = 4;
                    };
                    // удаляем ссылку на элимента
                    if(!$error){// если нет ошибок
                        $key = "channels";// задаём ключ
                        if($unit){// если элимент существует
                            $cache = array_splice($parent[$key], $i, 1)[0];
                        }else $error = 5;
                    };
                    break;
                case "message":// сообщение
                    // проверяем наличее параметров
                    if(!$error){// если нет ошибок
                        if(// множественное условие
                            $argLength > $argFirst + 1
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
                    // ищем ссылку на элимента
                    if(!$error){// если нет ошибок
                        $key = "messages";// задаём ключ
                        $parent = &$app["fun"]["getСache"]("channel", $cid, $gid, null);
                        if(!is_null($parent)){// если есть родительский элимент
                            if(isset($parent[$key])){// если есть массив элементов у родителя
                                for($i = 0, $iLen = count($parent[$key]); $i < $iLen; $i++){
                                    if($parent[$key][$i]["id"] == $mid) break;
                                };
                                if(isset($parent[$key][$i])) $unit = &$parent[$key][$i];
                            };
                        }else $error = 4;
                    };
                    // удаляем ссылку на элимента
                    if(!$error){// если нет ошибок
                        $key = "messages";// задаём ключ
                        if($unit){// если элимент существует
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
                    $event = $events->get($id);// получаем элимент по идентификатору
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
                    $event = $events->get($id);// получаем элимент по идентификатору
                    // создаём структуру счётчика
                    $count = &$counts;// счётчик элиментов
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
                        $event = $events->get($id);// получаем элимент по идентификатору
                        if($repeat == !!$event["repeat"]){// если событие соответствует
                            // получаем счётчик из структуру
                            $count = &$counts;// счётчик элиментов
                            foreach(array($event["guild"], $event["channel"], $event["user"], $event["time"]) as $key){
                                $count = &$count[$key];// получаем ссылку
                            };
                            // работаем с лимитами
                            if(// множественное условие
                                $count[$event["raid"]] > $app["val"]["eventRaidLimit"]
                                or $count[$all] > $app["val"]["eventTimeLimit"]
                            ){// если есть привышение лимитов
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
        "getCommandValue" => function(&$content, $delim, $begin = false, $filter = null, &$list = null){// получаем очередное значение
        //@param $content {string} - страка значений для обработки (сокращается на значение и разделители)
        //@param $delim {string} - разделитель значенией в строке
        //@param $begin {boolean} - совпадение с началом значения или полное совподение
        //@param $filter {FileStorage|string} - база данных или регулярное выражение для поиска совподения
        //@param $list {array} - результаты поиска для регулярного выражения
        //@return {string} - очередное ключевое значение или пустая строка
            global $app;
            $result = "";

            $flag = false;// найдено совпадение
            $language = $app["val"]["discordLang"];// локализация
            // получаем значение из строки по разделителю
            $index = mb_stripos($content, $delim);
            if(false === $index) $fragment = $content;
            else $fragment = mb_substr($content, 0, $index);
            // выполняем проверку полученного значение
            if(!empty($filter)){// если есть ограничивающий фильтр
               if(!is_string($filter)){// если передана база данных
                    // выполняем последовательную проверку с элиментами базы данных
                    for($i = 0, $iLen = $filter->length; $i < $iLen and !$flag; $i++){
                        $id = $filter->key($i);// получаем ключевой идентификатор по индексу
                        $item = $filter->get($id);// получаем элимент по идентификатору
                        foreach(array("key", $language) as $key){// пробигаемся по свойствам
                            if(isset($item[$key])){// если свойство существует
                                $value = $item[$key];// получаем значение свойства
                                $length = mb_strlen($value);// получаем длину значения
                                $index = mb_stripos($fragment, $value);// позиция начала совпадения
                                $flag = (0 === $index and ($begin or mb_strlen($fragment) == $length));
                                if($flag){// если найдено совпадение со свойством элимента
                                    $content = mb_substr($content, $length);
                                    $result = $id;
                                    break;
                                };
                            };
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
                    $guild = $app["fun"]["getСache"]("guild", $guild);
                };
                if(!empty($guild)){// если не пустое значение
                }else $error = 1;
            };
            // получаем канал
            if(!$error){// если нет ошибок
                if(!is_array($channel) and !empty($channel)){// если нужно получить
                    $channel = $app["fun"]["getСache"]("channel", $channel, $guild["id"], null);
                };
                if(!empty($channel)){// если не пустое значение
                }else $error = 2;
            };
            // получаем участника
            if(!$error){// если нет ошибок
                if(!is_array($member) and !empty($member)){// если нужно получить
                    $member = $app["fun"]["getСache"]("member", $member, $guild["id"]);
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
        "apiRequest" => function($metod, $uri, $data = null, &$code = 0){// http запрос к api
        //@param $metod {string} - методов http запроса в нижнем регистре
        //@param $uri {string} - конечная часть url адреса запроса
        //@param $data {array} - строка массив данных для запроса
        //@param $code {integer} - код ответа сервера
        //@return {array|null} - полученные данные или null при ошибки
            global $app;
            static $time = 0;
            $response = null;
            
            $wait = 0;// время ожидания сброса лимита
            $remain = 1;// текущий остаток запросов        
            // делаем запрос через api
            if(!empty($metod) and !empty($uri)){// если не пустые значения
                // контролируем скорость запросов
                $now = microtime(true);// текущее время
                $value = $time - $now;// время ожидания
                if($value > 0) usleep(1000000 * $value);
                // готовим данные и выполняем запрос
                $headers = array();// стандартные заголовки для запроса
                $headers["authorization"] = "Bot " . $app["val"]["discordBotToken"];
                $headers["x-ratelimit-precision"] = "millisecond";
                if(!empty($data)){// если требуется отправить данные
                    if("get" != mb_strtolower($metod)){// если нужно передать в теле зароса
                        $headers["content-type"] = "application/json;charset=utf-8";
                        $data = json_encode($data, JSON_UNESCAPED_UNICODE);
                    };
                };
                $data = http($metod, $app["val"]["discordApiUrl"] . $uri, $data, null, $headers, false);
                // обрабатываем ограничения скорости запросов
                foreach($data["headers"] as $key => $value) if("x-ratelimit-remaining" == mb_strtolower($key)) $remain = (int)$value;
                foreach($data["headers"] as $key => $value) if("x-ratelimit-reset-after" == mb_strtolower($key)) $wait = (float)$value;
                if(empty($remain) and !empty($wait)) $time = max($time, microtime(true) + $wait);
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
                    for($i = 0, $iLen = 55; $i < $iLen and $lock and !$flag; $i++){
                        if($i) sleep(1);// ждём секунду между итерациями
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
            // возвращаем результат
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
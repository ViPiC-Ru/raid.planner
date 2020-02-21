<?php # 0.2.0 api для бота в discord

include_once "../../libs/File-0.1.inc.php";                                 // 0.1.5 класс для многопоточной работы с файлом
include_once "../../libs/FileStorage-0.5.inc.php";                          // 0.5.9 класс для работы с файловым реляционным хранилищем
include_once "../../libs/phpEasy-0.3.inc.php";                              // 0.3.7 основная библиотека упрощённого взаимодействия
include_once "../../libs/vendor/webSocketClient-1.0.inc.php";               // 0.1.0 набор функций для работы с websocket

//////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

$app = array(// основной массив данных
    "val" => array(// переменные и константы
        "baseUrl" => "../base/%name%.db",                                   // шаблон url для базы данных
        "appToken" => "MY-APP-TOKEN",                                       // защитный ключ приложения
        "statusUnknown" => "Server unknown status",                         // сообщение для неизвестного статуса
        "statusLang" => "en",                                               // язык для кодов расшифровок
        "format" => "json",                                                 // формат вывода поумолчанию
        "eventTimeAdd" => 3*24*60*60,                                       // максимальное время для записи в событие
        "eventTimeDelete" => 12*60*60,                                      // максимальное время хранения записи события
        "eventTimeStep" => 1*60*60,                                         // шаг записи на событие (округление времени)
        "eventTimeLimit" => 2,                                              // лимит записей на время для пользователя
        "eventRaidLimit" => 1,                                              // лимит записей на время и рейд для пользователя
        "discordApiUrl" => "https://discordapp.com/api",                    // базовый url для взаимодействия с Discord API
        "discordWebSocketHost" => "gateway.discord.gg",                     // адрес хоста для взаимодействия с Discord через WebSocket
        "discordWebSocketLimit" => 500,                                     // лимит итераций цикла общения WebSocket с Discord
        "discordClientId" => "663665374532993044",                          // идентификатор приложения в Discord
        "discordCreatePermission" => 32768,                                 // разрешения для создание первой записи в событие (прикреплять файлы)
        "discordBotPermission" => 76800,                                    // минимальные разрешения для работы бота
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
        //@return {true|null} - true или пустое значение null при ошибке
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
                                    break;
                                case "GUILD_CREATE":// guild create
                                case "GUILD_UPDATE":// guild update
                                    // обрабатываем изменение гильдии
                                    if(isset($data["d"]["id"])){// если есть обязательное значение
                                        $guild = $app["fun"]["setСache"]("guild", $data["d"]);
                                        if($guild){// если удалось закешировать данные
                                            $flag = $app["method"]["discord.guild"](
                                                array(// параметры для метода
                                                    "guild" => $data["d"]["id"]
                                                ),
                                                array("nocontrol" => true),
                                                $sign, $status
                                            );
                                            $result = $result || $flag;
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
                                        $channel = $app["fun"]["setСache"]("channel", $data["d"], $data["d"]["guild_id"]);
                                        if($channel){// если удалось закешировать данные
                                            $flag = $app["method"]["discord.channel"](
                                                array(// параметры для метода
                                                    "channel" => $data["d"]["id"],
                                                    "guild" => $data["d"]["guild_id"]
                                                ),
                                                array("nocontrol" => true),
                                                $sign, $status
                                            );
                                            $result = $result || $flag;
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
                                            $flag = $app["method"]["discord.message"](
                                                array(// параметры для метода
                                                    "message" => $data["d"]["id"],
                                                    "channel" => $data["d"]["channel_id"],
                                                    "guild" => $data["d"]["guild_id"]
                                                ),
                                                array("nocontrol" => true),
                                                $sign, $status
                                            );
                                            $result = $result || $flag;
                                        }else $app["fun"]["delСache"]("message", $data["d"]["id"], $data["d"]["channel_id"], $data["d"]["guild_id"]);
                                    };
                                    break;
                                case "MESSAGE_DELETE":// message delete
                                    // обрабатываем удаление сообщения
                                    if(isset($data["d"]["id"], $data["d"]["channel_id"], $data["d"]["guild_id"])){// если есть обязательное значение
                                        $app["fun"]["delСache"]("message", $data["d"]["id"], $data["d"]["channel_id"], $data["d"]["guild_id"]);
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
                                        "seq" => 1*$session->get("seq", "value")
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
                    // выполняем регламентные операции
                    if(empty($status)){// если нет ошибок
                        // проверяем и поддерживаем серцебиение
                        if($heartbeatInterval > 0){// если задан интервал серцебиения
                            if($heartbeatSendTime - $heartbeatAcceptTime > $heartbeatInterval + 10){// если соединение зависло
                                websocket_close($websocket);// закрываем старое подключение
                                $websocket = false;// подключение закрыто
                            }else if($now - $heartbeatSendTime > $heartbeatInterval){// если нужно отправить серцебиение
                                // отправляем серцебиение
                                $data = array(// данные для отправки
                                    "op" => 1,// heartbeat
                                    "d" => 1*$session->get("seq", "value")
                                );
                                $data = json_encode($data, JSON_UNESCAPED_UNICODE);
                                websocket_write($websocket, $data, true);
                                $heartbeatSendTime = $now;
                            };
                        };
                        // переодически сохраняем базу данных сесии
                        if($isSessionUpdate and !(($index - 3) % 15)){// если нужно сохранить
                            if($session->save(true)){// если данные успешно сохранены
                                $isSessionUpdate = false;// были сохранены данные в базе данных
                            }else $status = 307;// не удалось сохранить базу данных
                        };
                    };
                    // увеличиваем индексы
                    $index++;// индекс итераций цикла
                }while(// множественное условие
                    empty($status) and $index < $app["val"]["discordWebSocketLimit"]
                    and (!$websocket or websocket_check($websocket))
                );
            };
            // сохраняем базу данных сесии
            if(isset($session) and !empty($session)){// если база данных загружена
                $lock = get_val($options, "nocontrol", false);
                if(empty($status) and $isSessionUpdate){// если нет ошибок
                    if($session->save($lock)){// если данные успешно сохранены
                    }else $status = 307;// не удалось сохранить базу данных
                }else if(!$lock) $session->unlock();// разблокируем базу
            };
            // сохраняем базу данных событий
            if(isset($events) and !empty($events)){// если база данных загружена
                $lock = get_val($options, "nocontrol", false);
                if(empty($status) and $isEventsUpdate){// если нет ошибок
                    if($events->save($lock)){// если данные успешно сохранены
                    }else $status = 307;// не удалось сохранить базу данных
                }else if(!$lock) $events->unlock();// разблокируем базу
            };
            // возвращаем результат
            if(!$result and !empty($status)) $result = false;
            return $result;
        },
        "discord.guild" => function($params, $options, $sign, &$status){// обрабатываем гильдию
        //@param $params {array} - массив внешних не отфильтрованных значений
        //@param $options {array} - массив внутренних настроек
        //@param $sign {boolean|null} - успешность проверки подписи или null при её отсутствии
        //@param $status {number} - целое число статуса выполнения
        //@return {true|null} - true или пустое значение null при ошибке
            global $app; $result = null;
            
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
            // получаем информацию о гильдии
            if(empty($status)){// если нет ошибок
                $guild = $app["fun"]["getСache"]("guild", $guild);
                if($guild){// если удалось получить данные
                }else $status = 303;// переданные параметры не верны
            };
            // выполняем обработку каналов в гильдии
            if(empty($status)){// если нет ошибок
                for($i = count($guild["channels"]) - 1; $i > -1 and empty($status); $i--){
                    $channel = $guild["channels"][$i];// получаем очередной элимент
                    $flag = $app["method"]["discord.channel"](
                        array(// параметры для метода
                            "channel" => $channel["id"],
                            "guild" => $guild["id"]
                        ),
                        array("nocontrol" => true),
                        $sign, $status
                    );
                    $result = $result || $flag;
                };
            };
            // возвращаем результат
            if(!$result and !empty($status)) $result = false;
            return $result;
        },
        "discord.channel" => function($params, $options, $sign, &$status){// обрабатываем канал
        //@param $params {array} - массив внешних не отфильтрованных значений
        //@param $options {array} - массив внутренних настроек
        //@param $sign {boolean|null} - успешность проверки подписи или null при её отсутствии
        //@param $status {number} - целое число статуса выполнения
        //@return {true|null} - true или пустое значение null при ошибке
            global $app; $result = null;
            
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
            // получаем информацию о гильдии
            if(empty($status)){// если нет ошибок
                $guild = $app["fun"]["getСache"]("guild", $guild);
                if($guild){// если удалось получить данные
                }else $status = 303;// переданные параметры не верны
            };
            // получаем информацию о канале
            if(empty($status)){// если нет ошибок
                $channel = $app["fun"]["getСache"]("channel", $channel, $guild["id"]);
                if($channel){// если удалось получить данные
                }else $status = 303;// переданные параметры не верны
            };
            // выполняем обработку сообщений в канале
            if(empty($status)){// если нет ошибок
                for($i = count($channel["messages"]) - 1; $i > -1 and empty($status); $i--){
                    $message = $channel["messages"][$i];// получаем очередной элимент
                    $flag = $app["method"]["discord.message"](
                        array(// параметры для метода
                            "message" => $message["id"],
                            "channel" => $channel["id"],
                            "guild" => $guild["id"]
                        ),
                        array("nocontrol" => true),
                        $sign, $status
                    );
                    $result = $result || $flag;
                };
            };
            // возвращаем результат
            if(!$result and !empty($status)) $result = false;
            return $result;
        },
        "discord.message" => function($params, $options, $sign, &$status){// обрабатываем сообщение
        //@param $params {array} - массив внешних не отфильтрованных значений
        //@param $options {array} - массив внутренних настроек
        //@param $sign {boolean|null} - успешность проверки подписи или null при её отсутствии
        //@param $status {number} - целое число статуса выполнения
        //@return {true|null} - true или пустое значение null при ошибке
            global $app; $result = null;
            
            $error = 0;// код ошибки для обратной связи
            $now = microtime(true);// текущее время
            $isEventsUpdate = false;// были ли обновлены данные в базе данных
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
                $channel = $app["fun"]["getСache"]("channel", $channel, $guild["id"]);
                if($channel){// если удалось получить данные
                }else $status = 303;// переданные параметры не верны
            };
            // получаем информацию о сообщении
            if(empty($status)){// если нет ошибок
                $message = $app["fun"]["getСache"]("message", $message, $channel["id"], $guild["id"]);
                if($message){// если удалось получить данные
                }else $status = 303;// переданные параметры не верны
            };
            // очищаем устаревшие записи событий
            if(empty($status)){// если нет ошибок
                $now = microtime(true);// текущее время
                for($i = $events->length - 1; $i >= 0 and empty($status); $i--){
                    $event = $events->get($events->key($i));
                    if(// множественное условие
                        $event["time"] < $now - $app["val"]["eventTimeDelete"]
                        or $event["time"] > $now + $app["val"]["eventTimeAdd"]
                    ){// если нужно удалить эту запись
                        if($events->set($events->key($i))){// если данные успешно добавлены
                            $isEventsUpdate = true;// были обновлены данные в базе данных
                        }else $status = 309;// не удалось записать данные в базу данных
                    };
                };
            };
            // обрабатываем сообщение
            if(empty($status)){// если нет ошибок
                if(// множественное условие
                    !$message["pinned"] and !$message["type"]
                    and $message["author"]["id"] != $app["val"]["discordClientId"]
                ){// если это сообщение с командой
                    $now = microtime(true);// текущее время
                    $command = array();// команда заложенная в сообщение
                    // определяем команду в сообщении
                    if(empty($error)){// если нет проблем
                        $delim = " ";// разделитель параметров в сообщении
                        $time = "";// дата и время одной строкой
                        $content = $message["content"];// получаем содержимое сообщения
                        ///////////////////////////////////////////////////////////////////////////////
                        $value ="";// значение действия
                        // обробатываем синоним действия
                        for($i = 0, $iLen = $actions->length; $i < $iLen; $i++){
                            $key = $actions->key($i);// получаем ключевой идентификатор по индексу
                            $item = $actions->get($key);// получаем элимент по идентификатору
                            if(0 === mb_stripos($content, $item["synonym"])){// если найдено совпадение
                                $value = $item["synonym"];// значение параметра
                                $command["action"] = $item["key"];
                                break;
                            };
                        };
                        // обрабатываем параметр
                        if(!mb_strlen($value)){// если параметр ещё не определён
                            $index = mb_stripos($content, $delim);
                            if(false === $index) $value = $content;
                            else $value = mb_substr($content, 0, $index);
                            $command["action"] = mb_strtolower($value);
                        };
                        // готовимся к следующему
                        $content = mb_substr($content, mb_strlen($value));
                        if(0 === mb_stripos($content, $delim)){// если есть разделитель
                            $content = mb_substr($content, mb_strlen($delim));
                        };
                        ///////////////////////////////////////////////////////////////////////////////
                        $value ="";// значение роли
                        // обрабатываем параметр
                        if(!mb_strlen($value)){// если параметр ещё не определён
                            $index = mb_stripos($content, $delim);
                            if(false === $index) $value = $content;
                            else $value = mb_substr($content, 0, $index);
                            $command["role"] = mb_strtolower($value);
                        };
                        // готовимся к следующему
                        $content = mb_substr($content, mb_strlen($value));
                        if(0 === mb_stripos($content, $delim)){// если есть разделитель
                            $content = mb_substr($content, mb_strlen($delim));
                        };
                        ///////////////////////////////////////////////////////////////////////////////
                        $value ="";// значение даты
                        // обрабатываем параметр
                        if(!mb_strlen($value)){// если параметр ещё не определён
                            $index = mb_stripos($content, $delim);
                            if(false === $index) $value = $content;
                            else $value = mb_substr($content, 0, $index);
                            // обробатываем синоним даты
                            for($i = 0, $iLen = $dates->length; $i < $iLen; $i++){
                                $key = $dates->key($i);// получаем ключевой идентификатор по индексу
                                $item = $dates->get($key);// получаем элимент по идентификатору
                                if(// множественное условие
                                    mb_strtolower($value) == mb_strtolower($item["key"])
                                    or mb_strtolower($value) == mb_strtolower($item["synonym"])
                                ){// если найдено совпадение
                                    $time = date("d.m.Y", strtotime($item["key"], $now));
                                    break;
                                };
                            };
                            // обрабатваем короткое написание
                            if(!mb_strlen($time)){// если время ещё не задано
                                if(preg_match("/^\d{1,2}\.\d{2}$/", $value)){
                                    $t1 = strtotime($value . "." . date("Y", $now), $now);
                                    $t2 = strtotime($value . "." . date("Y", strtotime("+1 year", $now)), $now);
                                    $time = date("d.m.Y", abs($t1 - $now) < abs($t2 - $now) ? $t1 : $t2);
                                }else $time = $value;
                            };
                        };
                        // готовимся к следующему
                        $content = mb_substr($content, mb_strlen($value));
                        if(0 === mb_stripos($content, $delim)){// если есть разделитель
                            $content = mb_substr($content, mb_strlen($delim));
                        };
                        ///////////////////////////////////////////////////////////////////////////////
                        $value ="";// значение времени
                        // обрабатываем параметр
                        if(!mb_strlen($value)){// если параметр ещё не определён
                            $index = mb_stripos($content, $delim);
                            if(false === $index) $value = $content;
                            else $value = mb_substr($content, 0, $index);
                            if(!$time or !$value) $time = 0;
                            else $time = strtotime($time . " " . $value);
                            if(false === $time) $time = -1;// приводим к единобразному написанию
                            $offset = 1 * date("Z");// смещение временной зоны
                            if($time > 0) $time = floor(($time - $offset) /$app["val"]["eventTimeStep"]) * $app["val"]["eventTimeStep"] + $offset;
                            $command["time"] = $time;
                        };
                        // готовимся к следующему
                        $content = mb_substr($content, mb_strlen($value));
                        if(0 === mb_stripos($content, $delim)){// если есть разделитель
                            $content = mb_substr($content, mb_strlen($delim));
                        };
                        ///////////////////////////////////////////////////////////////////////////////
                        $value ="";// значение рейда
                        // обрабатываем параметр
                        if(!mb_strlen($value)){// если параметр ещё не определён
                            $index = mb_stripos($content, $delim);
                            if(false === $index) $value = $content;
                            else $value = mb_substr($content, 0, $index);
                            $command["raid"] = mb_strtolower($value);
                        };
                        // готовимся к следующему
                        $content = mb_substr($content, mb_strlen($value));
                        if(0 === mb_stripos($content, $delim)){// если есть разделитель
                            $content = mb_substr($content, mb_strlen($delim));
                        };
                        ///////////////////////////////////////////////////////////////////////////////
                        $value ="";// значение опции
                        // обрабатываем параметр
                        if(!mb_strlen($value)){// если параметр ещё не определён
                            $index = mb_stripos($content, $delim);
                            if(false === $index) $value = $content;
                            else $value = mb_substr($content, 0, $index);
                            $command["addition"] = mb_strtolower($value);
                            // обробатываем синоним опции
                            for($i = 0, $iLen = $additions->length; $i < $iLen; $i++){
                                $key = $additions->key($i);// получаем ключевой идентификатор по индексу
                                $item = $additions->get($key);// получаем элимент по идентификатору
                                if(// множественное условие
                                    mb_strtolower($value) == mb_strtolower($item["key"])
                                    or mb_strtolower($value) == mb_strtolower($item["synonym"])
                                ){// если найдено совпадение
                                    $command["addition"] = $item["key"];
                                    break;
                                };
                            };                            
                        };
                        // готовимся к следующему
                        $content = mb_substr($content, mb_strlen($value));
                        if(0 === mb_stripos($content, $delim)){// если есть разделитель
                            $content = mb_substr($content, mb_strlen($delim));
                        };
                    };
                    // обрабатываем команду в сообщении
                    if(empty($error)){// если нет проблем
                        switch($command["action"]){// поддержмваемые комманды
                            case "add":// добавить запись
                                // проверяем что указана роль
                                if(empty($error)){// если нет проблем
                                    if(!empty($command["role"])){// если проверка пройдена
                                    }else $error = 2;
                                };
                                // проверяем что указано время
                                if(empty($error)){// если нет проблем
                                    if(!empty($command["time"])){// если проверка пройдена
                                    }else $error = 3;
                                };
                                // проверяем что указан рейд
                                if(empty($error)){// если нет проблем
                                    if(!empty($command["raid"])){// если проверка пройдена
                                    }else $error = 4;
                                };
                                // проверяем ограничения по времени записи
                                if(empty($error)){// если нет проблем
                                    if(// множественное условие
                                        $command["time"] >= $now
                                        and $command["time"] <= $now + $app["val"]["eventTimeAdd"]
                                    ){// если проверка пройдена
                                    }else $error = 5;
                                };
                                // проверяем корректность указания игровой роли
                                if(empty($error)){// если нет проблем
                                    for($role = null, $i = 0, $iLen = $roles->length; $i < $iLen and empty($role); $i++){
                                        $key = $roles->key($i);// получаем ключевой идентификатор по индексу
                                        $item = $roles->get($key);// получаем элимент по идентификатору
                                        if($item["key"] == $command["role"] or $item["synonym"] == $command["role"]) $role = $item;
                                    };
                                    if(!empty($role)){// если проверка пройдена
                                    }else $error = 6;
                                };
                                // проверяем корректность указания рейда
                                if(empty($error)){// если нет проблем
                                    for($raid = null, $i = 0, $iLen = $raids->length; $i < $iLen and empty($raid); $i++){
                                        $key = $raids->key($i);// получаем ключевой идентификатор по индексу
                                        $item = $raids->get($key);// получаем элимент по идентификатору
                                        if(mb_strtolower($item["key"]) == $command["raid"]) $raid = $item;
                                    };
                                    if(!empty($raid)){// если проверка пройдена
                                    }else $error = 7;
                                };
                                // проверяем ограничивающий фильтр в имени канала
                                if(empty($error)){// если нет проблем
                                    $counts = array("channel" => 0, "raid" => 0);// счётчик совпадений ограничений
                                    for($i = 0, $iLen = $types->length; $i < $iLen; $i++){
                                        $key = $types->key($i);// получаем ключевой идентификатор по индексу
                                        $item = $types->get($key);// получаем элимент по идентификатору
                                        if(false !== mb_stripos($channel["name"], $item["filter"])){// если есть совподение
                                            if($item["key"] == $raid["type"]) $counts["raid"]++;
                                            $counts["channel"]++;// увеличиваем счётчик совпадений
                                        };
                                    };
                                    if(// множественное условие
                                        empty($counts["channel"])
                                        or !empty($counts["raid"])
                                    ){// если проверка пройдена
                                    }else $error = 8;
                                };
                                // считаем записи и проверяем лимиты
                                if(empty($error)){// если нет проблем
                                    $counts = array("time" => 0, "raid" => 0, "item" => 0);// счётчик записи
                                    for($i = 0, $iLen = $events->length; $i < $iLen; $i++){
                                        $id = $events->key($i);// получаем ключевой идентификатор по индексу
                                        $event = $events->get($id);// получаем элимент по идентификатору
                                        if(// множественное условие
                                            $event["channel"] == $channel["id"]
                                            and $event["guild"] == $guild["id"]
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
                                    if(// множественное условие
                                        $counts["raid"] < $app["val"]["eventRaidLimit"]
                                        and $counts["time"] < $app["val"]["eventTimeLimit"]
                                    ){// если проверка пройдена
                                    }else $error = 9;
                                };
                                // проверяем права на создание первой записи
                                if(empty($error)){// если нет проблем
                                    $permission = $app["fun"]["getPermission"]($message["author"]["id"], $channel["id"], $guild["id"]);
                                    $flag = ($permission & $app["val"]["discordCreatePermission"]) == $app["val"]["discordCreatePermission"];
                                    if($flag){// если проверка пройдена
                                    }else $error = 10;
                                };
                                // формируем элимент события
                                if(empty($error)){// если нет проблем
                                    $id = $events->length ? $events->key($events->length - 1) + 1 : 1;
                                    $event = array(// новая запись
                                        "guild" => $guild["id"],
                                        "channel" => $channel["id"],
                                        "user" => $message["author"]["id"],
                                        "time" => $command["time"],
                                        "raid" => $raid["key"],
                                        "role" => $role["key"],
                                        "leader" => false
                                    );
                                };
                                // обрабатываем дополнительные опции
                                if(empty($error)){// если нет проблем
                                    switch($command["addition"]){// поддержмваемые опции
                                        case "leader":// лидер
                                            $event["leader"] = true;
                                            break;
                                        case "":// опция не указана
                                            break;
                                        default:// не известная опция
                                            $error = 11;
                                    };
                                };
                                // добавляем данные в базу данных
                                if(empty($error)){// если нет проблем
                                    if($events->set($id, null, $event)){// если данные успешно добавлены
                                        $isEventsUpdate = true;// были обновлены данные в базе данных
                                    }else $status = 309;// не удалось записать данные в базу данных
                                };
                                break;
                            case "remove":// удалить запись
                                // проверяем ограничения по времени записи
                                if(empty($error)){// если нет проблем
                                    if(// множественное условие
                                        $command["time"] >= $now
                                        or empty($command["time"])
                                    ){// если проверка пройдена
                                    }else $error = 12;
                                };                                
                                // проверяем корректность указания игровой роли
                                if(empty($error)){// если нет проблем
                                    for($role = null, $i = 0, $iLen = $roles->length; $i < $iLen and empty($role); $i++){
                                        $key = $roles->key($i);// получаем ключевой идентификатор по индексу
                                        $item = $roles->get($key);// получаем элимент по идентификатору
                                        if($item["key"] == $command["role"] or $item["synonym"] == $command["role"]) $role = $item;
                                    };
                                    if(// множественное условие
                                        empty($command["role"])
                                        or !empty($role)
                                    ){// если проверка пройдена
                                    }else $error = 13;
                                };
                                // проверяем корректность указания рейда
                                if(empty($error)){// если нет проблем
                                    for($raid = null, $i = 0, $iLen = $raids->length; $i < $iLen and empty($raid); $i++){
                                        $key = $raids->key($i);// получаем ключевой идентификатор по индексу
                                        $item = $raids->get($key);// получаем элимент по идентификатору
                                        if(mb_strtolower($item["key"]) == $command["raid"]) $raid = $item;
                                    };
                                    if(// множественное условие
                                        empty($command["raid"])
                                        or !empty($raid)
                                    ){// если проверка пройдена
                                    }else $error = 14;
                                };
                                // удаляем записи событий
                                if(empty($error)){// если нет проблем
                                    for($i = $events->length - 1; $i > - 1 and empty($status); $i--){
                                        $id = $events->key($i);// получаем ключевой идентификатор по индексу
                                        $event = $events->get($id);// получаем элимент по идентификатору
                                        if(// множественное условие
                                            $event["channel"] == $channel["id"]
                                            and $event["guild"] == $guild["id"]
                                            and $event["user"] == $message["author"]["id"]
                                            and (empty($command["time"]) or $event["time"] == $command["time"])
                                            and (empty($role) or $event["role"] == $role["key"])
                                            and (empty($raid) or $event["raid"] == $raid["key"])
                                            and ($event["time"] >= $now)
                                        ){// если нужно удалить запись из событий
                                            if($events->set($id)){// если данные успешно удалены
                                                $isEventsUpdate = true;// были обновлены данные в базе данных
                                            }else $status = 309;// не удалось записать данные в базу данных
                                        };
                                    };
                                };
                                break;
                            default:// не известная команда
                                $error = 1;
                        };
                    };
                    // информируем пользователя
                    if(!empty($error)){// если есть проблема
                        // готовим контент для личного сообщения
                        if(empty($status)){// если нет ошибок
                            $feedback = $feedbacks->get($error);// получаем элимент по идентификатору
                            if(!empty($feedback)) $content = template($feedback["content"], $command);
                            if(empty($content)) $content = "Из-за непредвиденной проблемы я не смог записать вас.";
                        };
                        // получаем идентификатор личного канала
                        if(empty($status)){// если нет ошибок
                            $headers = array(// заголовки для запроса
                                "authorization" => "Bot " . $app["val"]["discordBotToken"],
                                "content-type" => "application/json;charset=utf-8"
                            );
                            $url = $app["val"]["discordApiUrl"] . "/users/" . $app["val"]["discordClientId"] . "/channels";
                            $data = array("recipient_id" => $message["author"]["id"]);
                            $data = json_encode($data, JSON_UNESCAPED_UNICODE);
                            $data = http("post", $url, $data, null, $headers, false);
                            if(200 == $data["status"]){// если запрос выполнен успешно
                                $data = json_decode($data["body"], true);
                                if(isset($data["id"]) and !empty($data["id"])){// если есть данные
                                }else $status = 306;// не удалось получить корректный ответ от удаленного сервера
                            }else $status = 306;// не удалось получить корректный ответ от удаленного сервера
                        };
                        // отправляем личное сообщение
                        if(empty($status)){// если нет ошибок
                            $headers = array(// заголовки для запроса
                                "authorization" => "Bot " . $app["val"]["discordBotToken"],
                                "content-type" => "application/json;charset=utf-8"
                            );
                            $url = $app["val"]["discordApiUrl"] . "/channels/" . $data["id"] . "/messages";
                            $data = array("content" => $content);
                            $data = json_encode($data, JSON_UNESCAPED_UNICODE);
                            $data = http("post", $url, $data, null, $headers, false);
                            if(200 == $data["status"] or 403 == $data["status"]){// если была попытка отправить
                            }else $status = 306;// не удалось получить корректный ответ от удаленного сервера
                        };
                    };
                };        
            };
            // удаляем сообщение
            if(empty($status)){// если нет ошибок
                if(// множественное условие
                    !$message["pinned"]
                    and $message["author"]["id"] != $app["val"]["discordClientId"]
                ){// если это сообщение можно удалить
                    $headers = array("authorization" => "Bot " . $app["val"]["discordBotToken"]);
                    $url = $app["val"]["discordApiUrl"] . "/channels/" . $channel["id"] . "/messages/" . $message["id"];
                    $data = http("delete", $url, null, null, $headers, false);
                    if(204 == $data["status"] or 404 == $data["status"]){// если сообщение удалено
                        $app["fun"]["delСache"]("message", $message["id"], $channel["id"], $guild["id"]);
                    }else $status = 306;// не удалось получить корректный ответ от удаленного сервера
                };
            };
            // удаляем все сообщения бота кроме первого
            if(empty($status)){// если нет ошибок
                $notification = null;// сообщение для уведомления
                for($i = count($channel["messages"]) - 1; $i > -1 and empty($status); $i--){
                    $item = $channel["messages"][$i];// получаем очередной элимент
                    if($item["author"]["id"] == $app["val"]["discordClientId"]){// если это сообщение бота
                        if($item["type"] or !empty($notification)){// если это сообщение нужно удалить
                            $headers = array("authorization" => "Bot " . $app["val"]["discordBotToken"]);
                            $url = $app["val"]["discordApiUrl"] . "/channels/" . $channel["id"] . "/messages/" . $item["id"];
                            $data = http("delete", $url, null, null, $headers, false);
                            if(204 == $data["status"] or 404 == $data["status"]){// если сообщение удалено
                                $app["fun"]["delСache"]("message", $item["id"], $channel["id"], $guild["id"]);
                            }else $status = 306;// не удалось получить корректный ответ от удаленного сервера
                        }else $notification = $item;
                    };
                };
            };
            // формируем текст уведомления
            if(empty($status)){// если нет ошибок
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
                        $item["channel"] == $channel["id"]
                        and $item["guild"] == $guild["id"]
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
                        $months = array("", "Января", "Февраля", "Марта", "Апреля", "Мая", "Июня", "Июля", "Августа", "Сентября", "Октября", "Ноября", "Декабря");
                        $item["title"] = date("d", $item["time"]) . " " . $months[date("n", $item["time"])];
                        $item["day"] = $days[date("w", $item["time"])];
                        $item["group"] = $group;
                        // сохраняем элимент в массив
                        $items[$index] = $item;
                        $index++;
                    };
                };
                // сортируем список записей для отображения
                usort($items, function($a, $b){// пользовательская сортировка
                    $value = 0;// начальное значение
                    if(!$value and $a["time"] != $b["time"]) $value = $a["time"] > $b["time"] ? 1 : -1;
                    if(!$value and $a["raid"] != $b["raid"]) $value = $a["raid"] > $b["raid"] ? 1 : -1;
                    if(!$value and $a["group"] != $b["group"]) $value = $a["group"] > $b["group"] ? 1 : -1;
                    if(!$value and $a["role"] != $b["role"]) $value = $a["role"] < $b["role"] ? 1 : -1;
                    if(!$value and $a["leader"] != $b["leader"]) $value = $a["leader"] > $b["leader"] ? 1 : -1;
                    if(!$value and $a["id"] != $b["id"]) $value = $a["id"] > $b["id"] ? 1 : -1;
                    // возвращаем результат
                    return $value;
                });
                // формируем содержимое сообщения
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
                            $content .= (!empty($content) ? "\n" : "") . "**" . date("H:i", $item["time"]) . "** - **" . $raid["key"] . "** " . $raid["name"] . (!empty($raid["chapter"]) ? " **DLC**" : "") . ($limit ? " (" . $count . " из " . $limit . ")" : "");
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
            };
            // отправляем или изменяем уведомление
            if(empty($status)){// если нет ошибок
                if(empty($notification)){// если нужно опубликовать новое сообщение
                    // отправляем новое сообщение
                    $headers = array(// заголовки для запроса
                        "authorization" => "Bot " . $app["val"]["discordBotToken"],
                        "content-type" => "application/json;charset=utf-8"
                    );
                    $url = $app["val"]["discordApiUrl"] . "/channels/" . $channel["id"] . "/messages";
                    $data = array("content" => $content);
                    $data = json_encode($data, JSON_UNESCAPED_UNICODE);
                    $data = http("post", $url, $data, null, $headers, false);
                    if(200 == $data["status"]){// если сообщение отправлено
                        $data = json_decode($data["body"], true);
                        if(isset($data) and !empty($data)){// если есть данные
                            $app["fun"]["setСache"]("message", $data, $channel["id"], $guild["id"]);
                        }else $status = 306;// не удалось получить корректный ответ от удаленного сервера
                    }else $status = 306;// не удалось получить корректный ответ от удаленного сервера
                }else if($notification["content"] != $content){// если нужно изменить старое сообщение
                    // изменяем старое сообщение
                    $headers = array(// заголовки для запроса
                        "authorization" => "Bot " . $app["val"]["discordBotToken"],
                        "content-type" => "application/json;charset=utf-8"
                    );
                    $url = $app["val"]["discordApiUrl"] . "/channels/" . $channel["id"] . "/messages/" . $notification["id"];
                    $data = array("content" => $content);
                    $data = json_encode($data, JSON_UNESCAPED_UNICODE);
                    $data = http("patch", $url, $data, null, $headers, false);
                    if(200 == $data["status"]){// если сообщение изменено
                        $data = json_decode($data["body"], true);
                        if(isset($data) and !empty($data)){// если есть данные
                            $app["fun"]["setСache"]("message", $data, $channel["id"], $guild["id"]);
                        }else $status = 306;// не удалось получить корректный ответ от удаленного сервера
                    }else $status = 306;// не удалось получить корректный ответ от удаленного сервера
                };
            };
            // сохраняем базу данных событий
            if(isset($events) and !empty($events)){// если база данных загружена
                $lock = get_val($options, "nocontrol", false);
                if(empty($status) and $isEventsUpdate){// если нет ошибок
                    if($events->save($lock)){// если данные успешно сохранены
                    }else $status = 307;// не удалось сохранить базу данных
                }else if(!$lock) $events->unlock();// разблокируем базу
            };
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
                            isset(// данные для проверки
                                $data["id"]
                            ) and $argLength >= 2
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
                    // проверяем разрешения
                    if(!$error){// если нет ошибок
                        $flag = true;// без ограничений
                        if($flag){// если проверка пройдена
                        }else $error = 4;
                    };
                    // определяем ссылку на элимента
                    if(!$error){// если нет ошибок
                        $parent = &$app["cache"];
                        if(!is_null($parent)){// если есть родительский элимент
                            if(!isset($parent["guilds"])) $parent["guilds"] = array();
                            for($i = 0, $iLen = count($parent["guilds"]); $i < $iLen; $i++){
                                if($parent["guilds"][$i]["id"] == $gid) break;
                            };
                            if(!isset($parent["guilds"][$i])) $parent["guilds"][$i] = array();
                            $unit = &$parent["guilds"][$i];
                        }else $error = 5;
                    };
                    // обрабатываем данные
                    if(!$error){// если нет ошибок
                        // идентификатор
                        if(isset($data["id"])){// если существует
                            $unit["id"] = $data["id"];
                        };
                        // список участников
                        if(isset($data["members"])){// если существует
                            if(!isset($unit["members"])) $unit["members"] = array();
                            for($i = 0, $iLen = count($data["members"]); $i < $iLen; $i++){
                                $app["fun"]["setСache"]("member", $data["members"][$i], $gid);
                            };
                        };
                        // список каналов
                        if(isset($data["channels"])){// если существует
                            if(!isset($unit["channels"])) $unit["channels"] = array();
                            for($i = 0, $iLen = count($data["channels"]); $i < $iLen; $i++){
                                $app["fun"]["setСache"]("channel", $data["channels"][$i], $gid);
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
                            isset(// данные для проверки
                                $data["user"]["id"]
                            ) and $argLength > $argFirst
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
                    // проверяем разрешения
                    if(!$error){// если нет ошибок
                        $flag = true;// без ограничений
                        if($flag){// если проверка пройдена
                        }else $error = 4;
                    };
                    // определяем ссылку на элимента
                    if(!$error){// если нет ошибок
                        $parent = &$app["fun"]["getСache"]("guild", $gid);
                        if(!is_null($parent)){// если есть родительский элимент
                            if(!isset($parent["members"])) $parent["members"] = array();
                            for($i = 0, $iLen = count($parent["members"]); $i < $iLen; $i++){
                                if($parent["members"][$i]["user"]["id"] == $uid) break;
                            };
                            if(!isset($parent["members"][$i])) $parent["members"][$i] = array();
                            $unit = &$parent["members"][$i];
                        }else $error = 5;
                    };
                    // обрабатываем данные
                    if(!$error){// если нет ошибок
                        // идентификатор пользователя
                        if(isset($data["user"]["id"])){// если существует
                            if(!isset($unit["user"])) $unit["user"] = array();
                            $unit["user"]["id"] = $data["user"]["id"];
                        };
                        // список ролей
                        if(isset($data["roles"])){// если существует
                            if(!isset($unit["roles"])) $unit["roles"] = array();
                            $unit["roles"] = $data["roles"];
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
                            isset(// данные для проверки
                                $data["id"],
                                $data["name"],
                                $data["type"]
                            ) and $argLength > $argFirst
                        ){// если проверка пройдена
                            $cid = $data["id"];
                            $gid = func_get_arg($argFirst);
                        }else $error = 2;
                    };
                    // проверяем значение данных
                    if(!$error){// если нет ошибок
                        if(// множественное условие
                            !empty($cid)
                            and !empty($gid)
                            and 0 == $data["type"]
                        ){// если проверка пройдена
                        }else $error = 3;
                    };
                    // проверяем разрешения
                    if(!$error){// если нет ошибок
                        $permission = $app["fun"]["getPermission"]($app["val"]["discordClientId"], $data, $gid);
                        $flag = ($permission & $app["val"]["discordBotPermission"]) == $app["val"]["discordBotPermission"];
                        if($flag){// если проверка пройдена
                        }else $error = 4;
                    };
                    // определяем ссылку на элимента
                    if(!$error){// если нет ошибок
                        $parent = &$app["fun"]["getСache"]("guild", $gid);
                        if(!is_null($parent)){// если есть родительский элимент
                            if(!isset($parent["channels"])) $parent["channels"] = array();
                            for($i = 0, $iLen = count($parent["channels"]); $i < $iLen; $i++){
                                if($parent["channels"][$i]["id"] == $cid) break;
                            };
                            if(!isset($parent["channels"][$i])) $parent["channels"][$i] = array();
                            $unit = &$parent["channels"][$i];
                        }else $error = 5;
                    };
                    // обрабатываем данные
                    if(!$error){// если нет ошибок
                        // идентификатор
                        if(isset($data["id"])){// если существует
                            $unit["id"] = $data["id"];
                        };
                        // название
                        if(isset($data["name"])){// если существует
                            $unit["name"] = $data["name"];
                        };
                        // тип
                        if(isset($data["type"])){// если существует
                            $unit["type"] = $data["type"];
                        };
                        // права доступа
                        if(isset($data["permission_overwrites"])){// если существует
                            $unit["permission_overwrites"] = $data["permission_overwrites"];
                        };
                        // сообщения
                        if(isset($data["messages"])){// если существует
                            if(!isset($unit["messages"])) $unit["messages"] = array();
                            for($i = 0, $iLen = count($data["messages"]); $i < $iLen; $i++){
                                $app["fun"]["setСache"]("message", $data["messages"][$i], $cid, $gid);
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
                            isset(// данные для проверки
                                $data["id"],
                                $data["type"],
                                $data["pinned"],
                                $data["author"]["id"]
                            ) and $argLength > $argFirst + 1
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
                            and !empty($data["author"]["id"])
                            and (!$data["pinned"] or $data["author"]["id"] == $app["val"]["discordClientId"])
                        ){// если проверка пройдена
                        }else $error = 3;
                    };
                    // проверяем разрешения
                    if(!$error){// если нет ошибок
                        $flag = true;// без ограничений
                        if($flag){// если проверка пройдена
                        }else $error = 4;
                    };
                    // определяем ссылку на элимента
                    if(!$error){// если нет ошибок
                        $parent = &$app["fun"]["getСache"]("channel", $cid, $gid);
                        if(!is_null($parent)){// если есть родительский элимент
                            if(!isset($parent["messages"])) $parent["messages"] = array();
                            for($i = 0, $iLen = count($parent["messages"]); $i < $iLen; $i++){
                                if($parent["messages"][$i]["id"] == $mid) break;
                            };
                            if(!isset($parent["messages"][$i])) $parent["messages"][$i] = array();
                            $unit = &$parent["messages"][$i];
                        }else $error = 5;
                    };
                    // обрабатываем данные
                    if(!$error){// если нет ошибок
                        // идентификатор
                        if(isset($data["id"])){// если существует
                            $unit["id"] = $data["id"];
                        };
                        // идентификатор автора
                        if(isset($data["author"]["id"])){// если существует
                            if(!isset($unit["author"])) $unit["author"] = array();
                            $unit["author"]["id"] = $data["author"]["id"];
                        };
                        // тип
                        if(isset($data["type"])){// если существует
                            $unit["type"] = $data["type"];
                        };
                        // содержимое
                        if(isset($data["content"])){// если существует
                            $unit["content"] = $data["content"];
                        };
                        // закрепление
                        if(isset($data["pinned"])){// если существует
                            $unit["pinned"] = $data["pinned"];
                        };
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
                        $parent = &$app["cache"];
                        if(!is_null($parent)){// если есть родительский элимент
                            if(!isset($parent["guilds"])) $parent["guilds"] = array();
                            for($i = 0, $iLen = count($parent["guilds"]); $i < $iLen; $i++){
                                if($parent["guilds"][$i]["id"] == $gid) break;
                            };
                            if(isset($parent["guilds"][$i])) $unit = &$parent["guilds"][$i];
                        }else $error = 4;
                    };
                    // получаем элимент через api
                    if(!$unit){// если элимент не задан
                        // делаем запрос через api
                        if(!$error){// если нет ошибок
                            $headers = array("authorization" => "Bot " . $app["val"]["discordBotToken"]);
                            $url = $app["val"]["discordApiUrl"] . "/guilds/" . $gid;
                            $data = http("get", $url, null, null, $headers, false);
                            if(200 == $data["status"]){// если запрос выполнен успешно
                                $data = json_decode($data["body"], true);
                            }else $error = 5;
                        };
                        // кешируем полученные данные
                        if(!$error){// если нет ошибок
                            if(isset($data) and !empty($data)){// если есть данные
                                $unit = &$app["fun"]["setСache"]($type, $data);
                            }else $error = 6;
                        };
                    };
                    // получаем участников через api
                    if($unit and !isset($unit["members"])){// если данные не запрашивались
                        // делаем запрос через api
                        if(!$error){// если нет ошибок
                            $headers = array("authorization" => "Bot " . $app["val"]["discordBotToken"]);
                            $url = $app["val"]["discordApiUrl"] . "/guilds/" . $gid . "/members";
                            $data = http("get", $url, null, null, $headers, false);
                            if(200 == $data["status"]){// если запрос выполнен успешно
                                $data = json_decode($data["body"], true);
                            }else $error = 7;
                        };
                        // кешируем полученные данные
                        if(!$error){// если нет ошибок
                            if(isset($data) and !empty($data)){// если есть данные
                                if(!isset($unit["members"])) $unit["members"] = array();
                                for($i = 0, $iLen = count($data); $i < $iLen; $i++){
                                    $app["fun"]["setСache"]("member", $data[$i], $gid);
                                };
                            }else $error = 8;
                        };
                    };
                    // получаем каналы через api
                    if($unit and !isset($unit["channels"])){// если данные не запрашивались
                        // делаем запрос через api
                        if(!$error){// если нет ошибок
                            $headers = array("authorization" => "Bot " . $app["val"]["discordBotToken"]);
                            $url = $app["val"]["discordApiUrl"] . "/guilds/" . $gid . "/channels";
                            $data = http("get", $url, null, null, $headers, false);
                            if(200 == $data["status"]){// если запрос выполнен успешно
                                $data = json_decode($data["body"], true);
                            }else $error = 9;
                        };
                        // кешируем полученные данные
                        if(!$error){// если нет ошибок
                            if(isset($data) and !empty($data)){// если есть данные
                                if(!isset($unit["channels"])) $unit["channels"] = array();
                                for($i = 0, $iLen = count($data); $i < $iLen; $i++){
                                    $app["fun"]["setСache"]("channel", $data[$i], $gid);
                                };
                            }else $error = 10;
                        };
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
                        $parent = &$app["fun"]["getСache"]("guild", $gid);
                        if(!is_null($parent)){// если есть родительский элимент
                            if(!isset($parent["members"])) $parent["members"] = array();
                            for($i = 0, $iLen = count($parent["members"]); $i < $iLen; $i++){
                                if($parent["members"][$i]["user"]["id"] == $uid) break;
                            };
                            if(isset($parent["members"][$i])) $unit = &$parent["members"][$i];
                        }else $error = 4;
                    };
                    // получаем элимент через api
                    if(!$unit){// если элимент не задан
                        // делаем запрос через api
                        if(!$error){// если нет ошибок
                            $headers = array("authorization" => "Bot " . $app["val"]["discordBotToken"]);
                            $url = $app["val"]["discordApiUrl"] . "/guilds/" . $gid . "/members/" . $uid;
                            $data = http("get", $url, null, null, $headers, false);
                            if(200 == $data["status"]){// если запрос выполнен успешно
                                $data = json_decode($data["body"], true);
                            }else $error = 5;
                        };
                        // кешируем полученные данные
                        if(!$error){// если нет ошибок
                            if(isset($data) and !empty($data)){// если есть данные
                                $unit = &$app["fun"]["setСache"]($type, $data, $gid);
                            }else $error = 6;
                        };
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
                            $argLength > $argFirst
                        ){// если проверка пройдена
                            $cid = func_get_arg($argFirst);
                            $gid = func_get_arg($argFirst + 1);
                        }else $error = 2;
                    };
                    // проверяем значение данных
                    if(!$error){// если нет ошибок
                        if(// множественное условие
                            !empty($cid)
                            and !empty($gid)
                        ){// если проверка пройдена
                        }else $error = 3;
                    };
                    // определяем ссылку на элимента
                    if(!$error){// если нет ошибок
                        $parent = &$app["fun"]["getСache"]("guild", $gid);
                        if(!is_null($parent)){// если есть родительский элимент
                            if(!isset($parent["channels"])) $parent["channels"] = array();
                            for($i = 0, $iLen = count($parent["channels"]); $i < $iLen; $i++){
                                if($parent["channels"][$i]["id"] == $cid) break;
                            };
                            if(isset($parent["channels"][$i])) $unit = &$parent["channels"][$i];
                        }else $error = 4;
                    };
                    // получаем элимент через api
                    if(!$unit){// если элимент не задан
                        // делаем запрос через api
                        if(!$error){// если нет ошибок
                            $headers = array("authorization" => "Bot " . $app["val"]["discordBotToken"]);
                            $url = $app["val"]["discordApiUrl"] . "/channels/" . $cid;
                            $data = http("get", $url, null, null, $headers, false);
                            if(200 == $data["status"]){// если запрос выполнен успешно
                                $data = json_decode($data["body"], true);
                            }else $error = 5;
                        };
                        // кешируем полученные данные
                        if(!$error){// если нет ошибок
                            if(isset($data) and !empty($data)){// если есть данные
                                $unit = &$app["fun"]["setСache"]($type, $data, $gid);
                            }else $error = 6;
                        };
                    };
                    // получаем сообщения через api
                    if($unit and !isset($unit["messages"])){// если данные не запрашивались
                        // делаем запрос через api
                        if(!$error){// если нет ошибок
                            $headers = array("authorization" => "Bot " . $app["val"]["discordBotToken"]);
                            $url = $app["val"]["discordApiUrl"] . "/channels/" . $cid . "/messages";
                            $data = http("get", $url, null, null, $headers, false);
                            if(200 == $data["status"]){// если запрос выполнен успешно
                                $data = json_decode($data["body"], true);
                            }else $error = 7;
                        };
                        // кешируем полученные данные
                        if(!$error){// если нет ошибок
                            if(isset($data) and !empty($data)){// если есть данные
                                if(!isset($unit["messages"])) $unit["messages"] = array();
                                for($i = 0, $iLen = count($data); $i < $iLen; $i++){
                                    $app["fun"]["setСache"]("message", $data[$i], $cid, $gid);
                                };
                            }else $error = 8;
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
                        $parent = &$app["fun"]["getСache"]("channel", $cid, $gid);
                        if(!is_null($parent)){// если есть родительский элимент
                            if(!isset($parent["messages"])) $parent["messages"] = array();
                            for($i = 0, $iLen = count($parent["messages"]); $i < $iLen; $i++){
                                if($parent["messages"][$i]["id"] == $mid) break;
                            };
                            if(isset($parent["messages"][$i])) $unit = &$parent["messages"][$i];
                        }else $error = 4;
                    };
                    // получаем элимент через api
                    if(!$unit){// если элимент не задан
                        // делаем запрос через api
                        if(!$error){// если нет ошибок
                            $headers = array("authorization" => "Bot " . $app["val"]["discordBotToken"]);
                            $url = $app["val"]["discordApiUrl"] . "/channels/" . $cid . "/messages/" . $mid;
                            $data = http("get", $url, null, null, $headers, false);
                            if(200 == $data["status"]){// если запрос выполнен успешно
                                $data = json_decode($data["body"], true);
                            }else $error = 5;
                        };
                        // кешируем полученные данные
                        if(!$error){// если нет ошибок
                            if(isset($data) and !empty($data)){// если есть данные
                                $unit = &$app["fun"]["setСache"]($type, $data, $cid, $gid);
                            }else $error = 6;
                        };
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
                        $parent = &$app["cache"];
                        if(!is_null($parent)){// если есть родительский элимент
                            if(!isset($parent["guilds"])) $parent["guilds"] = array();
                            for($i = 0, $iLen = count($parent["guilds"]); $i < $iLen; $i++){
                                if($parent["guilds"][$i]["id"] == $gid) break;
                            };
                            if(isset($parent["guilds"][$i])) $unit = &$parent["guilds"][$i];
                        }else $error = 4;
                    };
                    // удаляем ссылку на элимента
                    if(!$error){// если нет ошибок
                        if($unit){// если элимент существует
                            $cache = array_splice($parent["guilds"], $i, 1)[0];
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
                        $parent = &$app["fun"]["getСache"]("guild", $gid);
                        if(!is_null($parent)){// если есть родительский элимент
                            if(!isset($parent["members"])) $parent["members"] = array();
                            for($i = 0, $iLen = count($parent["members"]); $i < $iLen; $i++){
                                if($parent["members"][$i]["user"]["id"] == $uid) break;
                            };
                            if(isset($parent["members"][$i])) $unit = &$parent["members"][$i];
                        }else $error = 4;
                    };
                    // удаляем ссылку на элимента
                    if(!$error){// если нет ошибок
                        if($unit){// если элимент существует
                            $cache = array_splice($parent["members"], $i, 1)[0];
                        }else $error = 5;
                    };
                    break;
                case "channel":// канал
                    // проверяем наличее параметров
                    if(!$error){// если нет ошибок
                        if(// множественное условие
                            $argLength > $argFirst
                        ){// если проверка пройдена
                            $cid = func_get_arg($argFirst);
                            $gid = func_get_arg($argFirst + 1);
                        }else $error = 2;
                    };
                    // проверяем значение данных
                    if(!$error){// если нет ошибок
                        if(// множественное условие
                            !empty($cid)
                            and !empty($gid)
                        ){// если проверка пройдена
                        }else $error = 3;
                    };
                    // ищем ссылку на элимента
                    if(!$error){// если нет ошибок
                        $parent = &$app["fun"]["getСache"]("guild", $gid);
                        if(!is_null($parent)){// если есть родительский элимент
                            if(!isset($parent["channels"])) $parent["channels"] = array();
                            for($i = 0, $iLen = count($parent["channels"]); $i < $iLen; $i++){
                                if($parent["channels"][$i]["id"] == $cid) break;
                            };
                            if(isset($parent["channels"][$i])) $unit = &$parent["channels"][$i];
                        }else $error = 4;
                    };
                    // удаляем ссылку на элимента
                    if(!$error){// если нет ошибок
                        if($unit){// если элимент существует
                            $cache = array_splice($parent["channels"], $i, 1)[0];
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
                        $parent = &$app["fun"]["getСache"]("channel", $cid, $gid);
                        if(!is_null($parent)){// если есть родительский элимент
                            if(!isset($parent["messages"])) $parent["messages"] = array();
                            for($i = 0, $iLen = count($parent["messages"]); $i < $iLen; $i++){
                                if($parent["messages"][$i]["id"] == $mid) break;
                            };
                            if(isset($parent["messages"][$i])) $unit = &$parent["messages"][$i];
                        }else $error = 4;
                    };
                    // удаляем ссылку на элимента
                    if(!$error){// если нет ошибок
                        if($unit){// если элимент существует
                            $cache = array_splice($parent["messages"], $i, 1)[0];
                        }else $error = 5;
                    };
                    break;
                default:// не известный тип
                    $error = 1;
            };
            // возвращаем результат
            return $cache;
        },
        "getPermission" => function($member, $channel, $guild){// получаем разрешения
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
                    $channel = $app["fun"]["getСache"]("channel", $channel, $guild["id"]);
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
            if(!$error){// если нет ошибок
                // разрешения по умолчанию
                $permission |= $app["val"]["discordCreatePermission"];// разрешено создавать рейды
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
            // возвращаем результат
            return $permission;
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
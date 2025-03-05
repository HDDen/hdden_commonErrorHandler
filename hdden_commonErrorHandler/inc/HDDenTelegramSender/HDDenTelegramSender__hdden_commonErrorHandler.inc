<?php

// отправка в телеграм
class HDDenTelegramSender__hdden_commonErrorHandler{

    private $users = [];
    private $token = '';
    private $telegram_sender_options;
    private $message = '';

    /**
     * $params = [
     *  'token' => '',
     *  'users' => [],
     *  'telegram_sender_options' => [
     *    "disable_web_page_preview" => true,
     *    "parse_mode" => "Markdown"
     *  ],
     * ]
     */
    public function __construct($params = array()){

        // установка токена
        $this->token = !empty($params['token']) ? $params['token'] : '';

        // установка пользователей для отправки
        $this->users = !empty($params['users']) ? $params['users'] : [];
        
        // установка параметров сообщения
        $this->telegram_sender_options = !empty($params['telegram_sender_options']) ? $params['telegram_sender_options'] : ["disable_web_page_preview" => true, "parse_mode" => "Markdown"];
    }

    /**
     * Добавляет строку в сообщение телеграмм
     *
     * @param string $str
     * @return void
     */
    public function appendMessage($str = ''){
        $this->message = $this->message . $str;
    }

    /**
     * Устанавливает сообщение, равное строке
     *
     * @param string $str
     * @return void
     */
    public function setMessage($str = ''){
        $this->message = $str;
    }

    /**
     * Выполняет рассылку сообщения каждому подписчику
     * Если передана пустая строка - отправляем ранее накопленное сообщение
     *
     * @param string $message
     * @return void
     */
    public function send($message = ''){
        if (!$message){
            if ($this->message){
                $message = $this->message;
                $this->message = '';
            } else {
                trigger_error('Telegram::send(): передано пустое сообщение, возвращаемся', E_USER_WARNING);
                return false;
            }
        }

        $message = trim($message);
        if (!$message){
            trigger_error('Telegram::send(): передано пустое сообщение (после $trim()), возвращаемся', E_USER_WARNING);
            return false;
        }

        $result = false;

        $ids = $this->users;

        if (!$ids){
            trigger_error('Telegram::send(): у текущего пользователя нет ни одного id получателя', E_USER_WARNING);
            return $result;
        }

        if (!$this->token){
            trigger_error('Telegram::send(): не установлен токен', E_USER_WARNING);
            return $result;
        }

        // Проверка длины сообщения
        $length = mb_strlen($message);
        $length_limit = 4096;
        $message_parts = array();

        if ($length > $length_limit){

            // маркер разбиения
            $splitBy = PHP_EOL.PHP_EOL;
            // фиксируем оставшийся размер текста
            $remains = $length;
            // вхождение последнего переноса
            $lastRN = false;
            // новая часть стартует с...
            $newPartStartsAt = 0;

            do {
                if ($remains > $length_limit){
                    // взять кусок на $length_limit
                    $temp = mb_substr($message, $newPartStartsAt, $length_limit);
                    // в ней найти вхождение последнего переноса
                    $lastRN = mb_strrpos($temp, $splitBy);

                    if ($lastRN){
                        // добавить в массив фрагмент до переноса
                        $fragment = mb_substr($temp, 0, $lastRN);
                    } else {
                        // добавить в массив весь временный
                        $fragment = $temp;
                    }
                } else {
                    // добавление всего остатка
                    $fragment = mb_substr($message, $newPartStartsAt);
                }
                $message_parts[] = trim($fragment, PHP_EOL);
                // обновляем длину
                // длина текущего фрагмента
                $fragment_length = mb_strlen($fragment);
                // осталось за вычетом конечной позиции и длины текущего
                $remains = mb_strlen($message) - ($newPartStartsAt + $fragment_length);
                // фиксируем начало следующей итерации
                $newPartStartsAt += $fragment_length;
                
            } while ($remains > 0);
        }

        // Отправка всем получателям
        $responses = '';
        foreach ($ids as $abonent){

            if (!empty($message_parts)){
                foreach ($message_parts as $part){
                    $response = json_decode($this->sender($part, $abonent));

                    // уберём ненужный массив из вывода
                    if (isset($response->result) && isset($response->result->entities)){
                        $response->result->entities = '';
                    }
                    $response = print_r($response, true);

                    // добавляем ответ в массив
                    if ($responses){
                        $responses .= ';'.PHP_EOL;
                    }

                    $responses .= $response;
                    // $this->logger->write('Ответ телеги:'.PHP_EOL.$response);
                }
            } else {
                $response = json_decode($this->sender($message, $abonent));
                // уберём ненужный массив из вывода
                if (isset($response->result) && isset($response->result->entities)){
                    $response->result->entities = ''; 
                }
                $response = print_r($response, true);

                // добавляем ответ в массив
                if ($responses){
                    $responses .= ';'.PHP_EOL;
                }

                $responses .= $response;
                // $this->logger->write('Ответ телеги:'.PHP_EOL.$response);
            }
        }

        $result = $responses; // потестировать, а то может всегда true вернём
        return $result;
    }

    /**
     * Просто отправитель
     */
    private function sender($message, $abonent){

        // добавляем объявленные параметры
        if (is_array($this->telegram_sender_options)){
            $options = $this->telegram_sender_options;
        }

        // проброс необходимого
        $options['text'] = $message;
        $options['chat_id'] = $abonent;

        $ch = curl_init();
        curl_setopt_array(
            $ch,
            array(
                CURLOPT_URL => 'https://api.telegram.org/bot' . $this->token . '/sendMessage',
                CURLOPT_POST => TRUE,
                CURLOPT_RETURNTRANSFER => TRUE,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_POSTFIELDS => $options,
            )
        );
        $out = curl_exec($ch);
        curl_close($ch);
        return $out;
    }
}
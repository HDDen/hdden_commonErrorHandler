<?php
/*
Plugin Name: HDDen Common Error Handler
Description: Добавляет на фронтенд функцию hdden_commonErrorHandler({'message': 'Hello PHP_EOLworld', 'reason': ''}), которая передает на бэкенд данные и отправляет их в telegram администратору
Version: 1.0.3
Author: HDDen
*/

define( 'HDDEN_CMNERRHANDLER__PLUGIN_DIR', untrailingslashit( dirname( __FILE__ ) ) );
define( 'HDDEN_CMNERRHANDLER__PLUGIN_URL',
	untrailingslashit( plugins_url( '', plugin_basename(__FILE__) ) )
);
define('HDDEN_CMNERRHANDLER__LOGGERCLASSNAME', 'HDDenLogger__hdden_CMNERRHANDLER');
define('HDDEN_CMNERRHANDLER__TLGCLASSNAME', 'HDDenTelegramSender__hdden_commonErrorHandler');
define('HDDEN_CMNERRHANDLER__PROTECTEDCONFIG', 'HDDenProtectedConfig');
define('HDDEN_CMNERRHANDLER__PLUGINCONFIGLOC', HDDEN_CMNERRHANDLER__PLUGIN_DIR.'/_config.php');

if (function_exists('add_action')){

	add_action('wp_enqueue_scripts', function(){
        wp_enqueue_script('hdden_commonErrorHandler', HDDEN_CMNERRHANDLER__PLUGIN_URL . '/assets/js/hdden_commonErrorHandler.js', [], (filemtime(HDDEN_CMNERRHANDLER__PLUGIN_DIR . '/assets/js/hdden_commonErrorHandler.js')), ['in_footer' => false, 'strategy' => 'async']);
		
		wp_add_inline_script('hdden_commonErrorHandler', 'var hdden_commonErrorHandler_ajaxUrl = '.json_encode([
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
        ]), 'before' );
    }, 0);

	add_action('wp_ajax_hdden_commonErrorHandler', 'hdden_commonErrorHandler');
	add_action('wp_ajax_nopriv_hdden_commonErrorHandler', 'hdden_commonErrorHandler');
}

/**
 * Основная функция, ajax-обработчик
 */
function hdden_commonErrorHandler(){

	// logger
	$logger = false;
	if (defined('HDDEN_CMNERRHANDLER__LOGGERCLASSNAME') && file_exists(HDDEN_CMNERRHANDLER__PLUGIN_DIR.'/inc/HDDenLogger/'.HDDEN_CMNERRHANDLER__LOGGERCLASSNAME.'.inc')){
		include_once(HDDEN_CMNERRHANDLER__PLUGIN_DIR.'/inc/HDDenLogger/'.HDDEN_CMNERRHANDLER__LOGGERCLASSNAME.'.inc');
		if (class_exists(HDDEN_CMNERRHANDLER__LOGGERCLASSNAME)){
			$logger_name = HDDEN_CMNERRHANDLER__LOGGERCLASSNAME;
            $logger = new $logger_name(HDDEN_CMNERRHANDLER__PLUGIN_DIR.'/logs'.'/_log-'.$_SERVER['HTTP_HOST'].'.txt.php');
		}
	}
	$GLOBALS['HDDEN_CMNERRHANDLER__LOGGER'] = $logger;

	// protected config
	if (defined('HDDEN_CMNERRHANDLER__PROTECTEDCONFIG') && file_exists(HDDEN_CMNERRHANDLER__PLUGIN_DIR.'/inc/HDDenProtectedConfig/'.HDDEN_CMNERRHANDLER__PROTECTEDCONFIG.'.php')){
		include_once(HDDEN_CMNERRHANDLER__PLUGIN_DIR.'/inc/HDDenProtectedConfig/'.HDDEN_CMNERRHANDLER__PROTECTEDCONFIG.'.php');
	} else {
		$GLOBALS['HDDEN_CMNERRHANDLER__LOGGER'] ? $GLOBALS['HDDEN_CMNERRHANDLER__LOGGER']->write(
            'hdden_commonErrorHandler():  класс '.HDDEN_CMNERRHANDLER__PROTECTEDCONFIG.' не подключен, дальнейшая работа невозможна'
            ) : false;
		return false;
	}

	// var_export minifier
	if (file_exists(HDDEN_CMNERRHANDLER__PLUGIN_DIR.'/inc/HDDen_minified_varexport/HDDen_minified_varexport.php')){
		include_once(HDDEN_CMNERRHANDLER__PLUGIN_DIR.'/inc/HDDen_minified_varexport/HDDen_minified_varexport.php');
	}

	// load config
	$plugin_config = hdden_commonErrorHandler__loadConfig();

	// telegram
	$telegram = false;
	if (!empty($plugin_config) && !empty($plugin_config['telegram_token']) && !empty($plugin_config['telegram_users'])){
		if (defined('HDDEN_CMNERRHANDLER__TLGCLASSNAME') && file_exists(HDDEN_CMNERRHANDLER__PLUGIN_DIR.'/inc/HDDenTelegramSender/'.HDDEN_CMNERRHANDLER__TLGCLASSNAME.'.inc')){
			include_once(HDDEN_CMNERRHANDLER__PLUGIN_DIR.'/inc/HDDenTelegramSender/'.HDDEN_CMNERRHANDLER__TLGCLASSNAME.'.inc');
			if (class_exists(HDDEN_CMNERRHANDLER__TLGCLASSNAME)){
				$telegram_name = HDDEN_CMNERRHANDLER__TLGCLASSNAME;
				$telegram = new $telegram_name([
					'token' => $plugin_config['telegram_token'],
					'users' => $plugin_config['telegram_users'],
					'telegram_sender_options' => (!empty($plugin_config['telegram_sender_options']) ? $plugin_config['telegram_sender_options'] : []),
				]);
			}
		}
	}
	$GLOBALS['HDDEN_CMNERRHANDLER__TLG'] = $telegram;

	// date time
	$date = date('d/m/Y H:i:s', time());

	// user ip
	$client_ip = (!empty($_SERVER['HTTP_CLIENT_IP']) ? $_SERVER['HTTP_CLIENT_IP'] : (!empty($_SERVER['HTTP_X_FORWARDED_FOR']) ? $_SERVER['HTTP_X_FORWARDED_FOR'] : (!empty($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '')));
    
    // extract query
    $hdden_data = [];
    if (isset($_POST['hdden_data'])){
        $hdden_data = $_POST['hdden_data'];
    }

	$GLOBALS['HDDEN_CMNERRHANDLER__LOGGER'] ? $GLOBALS['HDDEN_CMNERRHANDLER__LOGGER']->write(
		'************************************************************************************'.PHP_EOL.'hdden_commonErrorHandler(): Получено обращение, '.PHP_EOL.var_export($hdden_data, true)
	) : '';

	// достаем полезные данные
	$data = [];
	if (!empty($hdden_data['data'])){
		try {
			$data = json_decode(rawurldecode($hdden_data['data']), true);

			if (!$data){
				$json_error = json_last_error();
				$json_error_message = json_last_error_msg();

				// try again
				$data = json_decode(rawurldecode(str_replace('\\"', '"', $hdden_data['data'])), true);

				if (!$data){
					$json_error = json_last_error();
					$json_error_message = json_last_error_msg();

					$GLOBALS['HDDEN_CMNERRHANDLER__LOGGER'] ? $GLOBALS['HDDEN_CMNERRHANDLER__LOGGER']->write(
						'hdden_commonErrorHandler(): $data после json-обработки пустая, дамп ошибки '.PHP_EOL.var_export($json_error, true).PHP_EOL.$json_error_message
					) : '';
				} else {
					$GLOBALS['HDDEN_CMNERRHANDLER__LOGGER'] ? $GLOBALS['HDDEN_CMNERRHANDLER__LOGGER']->write(
						'hdden_commonErrorHandler(): $data раскодировали только со второй попытки'.PHP_EOL.var_export($json_error, true).PHP_EOL.$json_error_message
					) : '';
				}
			}

			$GLOBALS['HDDEN_CMNERRHANDLER__LOGGER'] ? $GLOBALS['HDDEN_CMNERRHANDLER__LOGGER']->write(
				'hdden_commonErrorHandler(): Данные после обработки, '.PHP_EOL.var_export($data, true)
			) : '';
		} catch (\Throwable $e) {

			$GLOBALS['HDDEN_CMNERRHANDLER__LOGGER'] ? $GLOBALS['HDDEN_CMNERRHANDLER__LOGGER']->write(sprintf('hdden_commonErrorHandler(): Ошибка декодирования входящего json (%d): %s' . PHP_EOL, $e->getCode(), $e->getMessage())) : '';
		}
	}

	// разобрать данные, поместить в массив
	// предполагаем структуру типа
	// $data = [
	// 	"reason" => "какая-то краткая причина",
	// 	"message" => "более развернутое сообщение",
	// ]
	if (!empty($data)){

		// фиксируем причину
		$reason = '';
		if (!empty($data['reason'])) $reason = str_replace('PHP_EOL', PHP_EOL, $data['reason']);

		// фиксируем сообщение
		$message = '';
		if (!empty($data['message'])) $message = str_replace('PHP_EOL', PHP_EOL, $data['message']);

		// проверка заполненности и отправка
		if ($reason || $message){
			
			$GLOBALS['HDDEN_CMNERRHANDLER__LOGGER'] ? $GLOBALS['HDDEN_CMNERRHANDLER__LOGGER']->write(
				'hdden_commonErrorHandler(): Подготавливаемся к отправке'
			) : '';

			// врезка для оповещения в телеграм
			$telegram_message = '';
			if ($reason) $telegram_message .= 'reason: '.$reason.PHP_EOL;
			if ($message) $telegram_message .= 'message: '.$message.PHP_EOL;
			if ($telegram_message) $telegram_message .= PHP_EOL; // чтобы отделить переданное от служебного
			$telegram_message .= '💡'.$_SERVER['HTTP_HOST'].': служебное сообщение, '.$date.PHP_EOL;
			$telegram_message .= 'ip: '.$client_ip.' (<a href="https://ipinfo.io/'.$client_ip.'">ipinfo.io</a>, <a href="https://ipgeolocation.io/ip-location/'.$client_ip.'">ipgeolocation.io</a>, <a href="https://www.whois.com/whois/'.$client_ip.'">whois.com</a>)'.PHP_EOL;
			$telegram_message .= 'User Agent: '.(!empty($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '').PHP_EOL;
			//$telegram_message .= '<pre>'.(function_exists('hdden__minified_varexport') ? hdden__minified_varexport($submission_data, 'print_r') : print_r($submission_data, true)).'</pre>';

			$GLOBALS['HDDEN_CMNERRHANDLER__TLG'] ? $GLOBALS['HDDEN_CMNERRHANDLER__TLG']->send(
				$telegram_message
			) : '';

			unset($telegram_message);
		} else {
			$GLOBALS['HDDEN_CMNERRHANDLER__LOGGER'] ? $GLOBALS['HDDEN_CMNERRHANDLER__LOGGER']->write('hdden_commonErrorHandler(): полезных данных не передано, отправлять нечего') : '';
		}
	}

	$GLOBALS['HDDEN_CMNERRHANDLER__LOGGER'] ? $GLOBALS['HDDEN_CMNERRHANDLER__LOGGER']->write(
		'hdden_commonErrorHandler(): Конец'.PHP_EOL.PHP_EOL
	) : '';
    
    // отдаём ответ
    $out = json_encode([
        'success' => true,
    ]);

    echo $out;

	if (function_exists('wp_die')){
        wp_die();
    }
}

/**
 * Грузит конфиг плагина
 */
function hdden_commonErrorHandler__loadConfig(){
	$result = false;

	// ищем в файловой системе
	if (!file_exists(HDDEN_CMNERRHANDLER__PLUGINCONFIGLOC)){
		$GLOBALS['HDDEN_CMNERRHANDLER__LOGGER'] ? $GLOBALS['HDDEN_CMNERRHANDLER__LOGGER']->write(
		'hdden_commonErrorHandler__loadConfig(): файла '.HDDEN_CMNERRHANDLER__PLUGINCONFIGLOC.' не существует'
		) : false;

		return $result;
	}

	$config_class = HDDEN_CMNERRHANDLER__PROTECTEDCONFIG;
	$config_class = new $config_class(HDDEN_CMNERRHANDLER__PLUGINCONFIGLOC);
	$config = $config_class->read(true, true);

	if (!$config){
		$GLOBALS['HDDEN_CMNERRHANDLER__LOGGER'] ? $GLOBALS['HDDEN_CMNERRHANDLER__LOGGER']->write(
		'hdden_commonErrorHandler__loadConfig(): секретный конфиг не прочитался'
		) : false;

	} else {

		$GLOBALS['HDDEN_CMNERRHANDLER__LOGGER'] ? $GLOBALS['HDDEN_CMNERRHANDLER__LOGGER']->write(
		'hdden_commonErrorHandler__loadConfig(): секретный конфиг загружен'
		) : false;

		$result = $config;
	}

	return $result;
}

<?php
require_once __DIR__ . '/vendor/autoload.php';

use Telegram\Bot\Api;
use Tools\{Config};
use Tools\Prepare\{Keyboards};
use Tools\Do\{MakeKeyboard, Commands, DbQuery};

$chart = new ImageCharts();
$telegram = new Api(Config::TOKEN, true);
$update = $telegram->getWebhookUpdate();

file_put_contents(__DIR__ . '/logs.txt', print_r($update, 1), FILE_APPEND);

$chat_id = $update['message']['from']['id'];
$callback_id = $update['callback_query']['id'];
$callback_chat_id = $update['callback_query']['message']['chat']['id'];
$callback_message_id = $update['callback_query']['message']['message_id'];
$callback_data = $update['callback_query']['data'];
$text = $update['message']['text'];
$first_name = $update['message']['chat']['first_name'];

if (!isset($callback_data)) {
	if (empty(DbQuery::condition($chat_id, 'get'))) {
		DbQuery::condition($chat_id, 'add');
	} elseif (DbQuery::condition($chat_id, 'get')[0]['amount'] == 1) {
		if (is_numeric($text)) {
			$category = DbQuery::get_category($chat_id, 1);
			DbQuery::add_record($chat_id, $first_name, DbQuery::get_type($category), $text, $category);
			$telegram->sendMessage([
				'chat_id' => $chat_id,
				'text' => 'Запись добавлена! Хотите добавить описание?',
				'reply_markup' => MakeKeyboard::get_inline(Keyboards::$description),
			]);
			DbQuery::condition($chat_id, 'set');
		} else {
			$telegram->sendMessage([
				'chat_id' => $chat_id,
				'text' => 'это не цифра попробуй ещё раз',
			]);
		}
		exit;
	} elseif (DbQuery::condition($chat_id, 'get')[0]['description'] == 1) {
		DbQuery::add_description($chat_id, $text);
		$telegram->sendMessage([
			'chat_id' => $chat_id,
			'text' => 'Запись добавлена с описанием!',
			'reply_markup' => MakeKeyboard::get_keyboard(Keyboards::$static_keyboard),
		]);
		DbQuery::condition($chat_id, 'set');
		exit;
	}

	if ($text[0] == '/' or $text == '❓ Помощь') {
		$telegram->sendMessage([
			'chat_id' => $chat_id,
			'text' => Commands::Command($text),
			'parse_mode' => 'HTML',
			'reply_markup' => MakeKeyboard::get_keyboard(Keyboards::$static_keyboard),
		]);
	} elseif ($text == '🖊 Добавить') {
		$telegram->sendMessage([
			'chat_id' => $chat_id,
			'text' => '🖊 Добавить',
			'reply_markup' => MakeKeyboard::get_inline(Keyboards::$choose_input_type),
		]);
	} elseif ($text == '⚙️ Настройки') {
		$telegram->sendMessage([
			'chat_id' => $chat_id,
			'text' => 'Этот пункт пока ещё в разработке 😉',
		]);
	} elseif ($text == '📊 Итоги') {
		$telegram->sendMessage([
			'chat_id' => $chat_id,
			'text' => '📊 Итоги',
			'reply_markup' => MakeKeyboard::get_inline(Keyboards::$results),
		]);
	} elseif ($text == '🔧 Управление') {
		$telegram->sendMessage([
			'chat_id' => $chat_id,
			'text' => '🔧 Управление',
			'reply_markup' => MakeKeyboard::get_inline(Keyboards::$manage_records),
		]);
	} else {
		$telegram->sendMessage([
			'chat_id' => $chat_id,
			'text' => 'Я этого не понимаю :(',
			'reply_markup' => MakeKeyboard::get_keyboard(Keyboards::$static_keyboard),
		]);
	}
}

if (isset($callback_data)) {
	if ($callback_data == '+' or $callback_data == '-') {
		$telegram->editMessageText([
			'chat_id' => $callback_chat_id,
			'message_id' => $callback_message_id,
			'text' => $callback_data == '+' ? '🔍 Выберите категорию дохода' : '🔎 Выберите категорию расхода',
			'reply_markup' => MakeKeyboard::get_inline(Keyboards::categories($callback_data == '+' ? '+' : '-')),
		]);
	} elseif ($callback_data == 'choose_input_type' or $callback_data == 'choose_input_type_back') {
		if ($callback_data == 'choose_input_type_back') {
			DbQuery::condition($callback_chat_id, 'set');
		}
		$telegram->editMessageText([
			'chat_id' => $callback_chat_id,
			'message_id' => $callback_message_id,
			'text' => '🖊 Добавить',
			'reply_markup' => MakeKeyboard::get_inline(Keyboards::$choose_input_type),
		]);
	} elseif (is_numeric($callback_data)) {
		$category = DbQuery::get_category($callback_data);
		$callback_data == 1 or $callback_data == 2 or $callback_data == 3 or $callback_data == 4 ? $word = 'дохода' : $word = 'расхода';

		$telegram->editMessageText([
			'chat_id' => $callback_chat_id,
			'message_id' => $callback_message_id,
			'text' => "Введите мне сумму для $word <b>$category</b>",
			'reply_markup' => MakeKeyboard::get_inline(Keyboards::$back),
			'parse_mode' => 'HTML'
		]);
		DbQuery::condition($callback_chat_id, 'set', NULL, $callback_data, 1);
	} elseif ($callback_data == 'add_description') {
		DbQuery::condition($callback_chat_id, 'set', 1);
		$telegram->editMessageText([
			'chat_id' => $callback_chat_id,
			'message_id' => $callback_message_id,
			'text' => 'Введите мне описание',
			'reply_markup' => MakeKeyboard::get_inline(Keyboards::$back),
		]);
	} elseif ($callback_data == 'no_description') {
		$telegram->deleteMessage(['chat_id' => $callback_chat_id, 'message_id' => $callback_message_id]);
		$telegram->sendMessage([
			'chat_id' => $callback_chat_id,
			'text' => 'Запись добавлена без описания!',
			'reply_markup' => MakeKeyboard::get_keyboard(Keyboards::$static_keyboard),
		]);
	} elseif ($callback_data == 'results') {
		DbQuery::more_resoults($callback_chat_id, 'set');
		$telegram->editMessageText([
			'chat_id' => $callback_chat_id,
			'message_id' => $callback_message_id,
			'text' => '📊 Итоги',
			'reply_markup' => MakeKeyboard::get_inline(Keyboards::$results),
		]);
	} elseif ($callback_data == 'results_today') {
		$telegram->editMessageText([
			'chat_id' => $callback_chat_id,
			'message_id' => $callback_message_id,
			'text' => '⌚ За сегодня',
			'reply_markup' => MakeKeyboard::get_inline(Keyboards::$results_today),
		]);
	} elseif ($callback_data == 'results_month') {
		$telegram->editMessageText([
			'chat_id' => $callback_chat_id,
			'message_id' => $callback_message_id,
			'text' => '🗓️ За месяц',
			'reply_markup' => MakeKeyboard::get_inline(Keyboards::$results_month),
		]);
	} elseif (stristr($callback_data, 'day') or stristr($callback_data, 'month')) {
		$type = NULL;
		$interval = $callback_data;
		if ($callback_data[0] == '+' or $callback_data[0] == '-') {
			$type = $callback_data[0];
			$interval = substr($callback_data, 1);
		}
		DbQuery::more_resoults($callback_chat_id, 'set', $type, $interval);
		$resoult = DbQuery::resoult($callback_chat_id, $type, $interval);
		$telegram->editMessageText([
			'chat_id' => $callback_chat_id,
			'message_id' => $callback_message_id,
			'text' => $resoult,
			'parse_mode' => 'HTML',
			'reply_markup' => MakeKeyboard::get_inline(MakeKeyboard::$more_resoults),
		]);
	} elseif ($callback_data == 'more_resoults') {
		try {
			$more_resoults = DbQuery::more_resoults($callback_chat_id, 'get');

			$get_more_resoults = DbQuery::get_more_resoults($callback_chat_id, $more_resoults[0]['resoult_type'], $more_resoults[0]['resoult_interval']);

			DbQuery::more_resoults($callback_chat_id, 'set');

			$telegram->editMessageText([
				'chat_id' => $callback_chat_id,
				'message_id' => $callback_message_id,
				'text' => $get_more_resoults,
				'parse_mode' => 'HTML',
			]);
		} catch (Exception $x) {
			DbQuery::more_resoults($callback_chat_id, 'set');

			$telegram->editMessageText([
				'chat_id' => $callback_chat_id,
				'message_id' => $callback_message_id,
				'text' => "У тебя ошибка!\n\n" . $x->getMessage() . "\n\n" . $x,
			]);
		}
	} elseif ($callback_data == 'delete_l' or $callback_data == 'delete_m' or $callback_data == 'delete_all') {

		$interval = match ($callback_data) {
			'delete_l' => 'last',
			'delete_m' => 'month',
			'delete_all' => 'all'
		};

		DbQuery::answer_to_delete($callback_chat_id, 'set', $interval);

		$delete_interval = match ($interval) {
			'last' => 'последнюю запись',
			'month' => 'записи за месяц',
			'all' => 'все свои записи'
		};

		$telegram->editMessageText([
			'chat_id' => $callback_chat_id,
			'message_id' => $callback_message_id,
			'text' => "<b>Вы точно хотите удалить $delete_interval?</b>",
			'parse_mode' => 'HTML',
			'reply_markup' => MakeKeyboard::get_inline(MakeKeyboard::$delete_records),
		]);
	} elseif ($callback_data == 'not_delete') {
		DbQuery::answer_to_delete($callback_chat_id, 'set');
		$telegram->editMessageText([
			'chat_id' => $callback_chat_id,
			'message_id' => $callback_message_id,
			'text' => '🔧 Управление',
			'reply_markup' => MakeKeyboard::get_inline(Keyboards::$manage_records),
		]);
	} elseif ($callback_data == 'delete') {

		$interval = DbQuery::answer_to_delete($callback_chat_id, 'get')[0]['delete_interval'];
		DbQuery::delete($callback_chat_id, $interval);
		DbQuery::answer_to_delete($callback_chat_id, 'set');

		$delete_interval = match ($interval) {
			'last' => '🧨 Последняя запись удалена!',
			'month' => '💣 Записи за месяц удалены!',
			'all' => '❌ Все ваши записи удалены!'
		};

		$telegram->editMessageText([
			'chat_id' => $callback_chat_id,
			'message_id' => $callback_message_id,
			'text' => $delete_interval,
			'parse_mode' => 'HTML',
		]);
	}
}


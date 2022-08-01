<?php

namespace Tools\Do;

use Tools\Prepare\DB;

class DbQuery extends DB
{
	public static function get_categories($type)
	{
		$pdo = (new Db)->Db();
		$stmt = $pdo->prepare("SELECT * FROM `categories` WHERE `type` = ?");
		$stmt->execute([$type]);

		$arr = [];

		foreach ($stmt->fetchAll() as $item) {
			$arr[] = ['text' => "{$item['title']}", 'callback_data' => "{$item['id']}"];
		}
		array_splice($arr, $type == '+' ? 4 : 12, 0, [
			['text' => '⬅️ Назад', 'callback_data' => 'choose_input_type']
		]);

		return $arr;
	}

	public static function get_category($id = NULL, $type = NULL)
	{
		$pdo = (new Db)->Db();
		if ($type == NULL) {
			$stmt = $pdo->prepare("SELECT `title` FROM `categories` WHERE `id` = ?");
			$stmt->execute([$id]);
		} else {
			$stmt = $pdo->prepare("SELECT `title` FROM `categories` WHERE `id` = (SELECT `category` FROM `condition` WHERE `chat_id` = ?)");
			$stmt->execute([$id]);
		}

		return $stmt->fetchAll()[0]['title'];
	}

	public static function get_type($category)
	{
		$pdo = (new Db)->Db();
		$stmt = $pdo->prepare("SELECT `type` FROM `categories` WHERE title = ?");
		$stmt->execute([$category]);

		return $stmt->fetchAll()[0]['type'];
	}

	public static function condition($chat_id, $type, $description = NULL, $category = NULL, $amount = NULL)
	{
		$pdo = (new Db)->Db();
		if ($type == 'get') {
			$stmt = $pdo->prepare("SELECT `amount`, `description` FROM `condition` WHERE `chat_id` = ?");
			$stmt->execute([$chat_id]);
		} elseif ($type == 'set') {
			$stmt = $pdo->prepare("UPDATE `condition` SET `category` = ?, `amount` = ?, `description` = ? WHERE `condition`.`chat_id` = ?");
			$stmt->execute([$category, $amount, $description, $chat_id]);
		} elseif ($type == 'add') {
			$stmt = $pdo->prepare("INSERT INTO `condition` (`chat_id`) VALUES (?)");
			$stmt->execute([$chat_id]);
		}

		$res = $stmt->fetchAll();

		return $res;
	}

	public static function add_description($chat_id, $description)
	{
		$pdo = (new Db)->Db();
		$stmt = $pdo->prepare("UPDATE `records` SET `description` = ? WHERE `chat_id` = ? ORDER BY id DESC LIMIT 1");
		$stmt->execute([$description, $chat_id]);
	}

	public static function add_record($chat_id, $first_name, $type, $amount, $category)
	{
		$pdo = (new Db)->Db();
		$stmt = $pdo->prepare("INSERT INTO `records` (`chat_id`, `first_name`, `type`, `amount`, `category`) VALUES (?, ?, ?, ?, ?)");
		$stmt->execute([$chat_id, $first_name, $type, $amount, $category]);
	}

	public static function more_resoults($chat_id, $type, $resoult_type = NULL, $resoult_interval = NULL)
	{
		$pdo = (new Db)->Db();

		if ($type == 'set') {
			$stmt = $pdo->prepare("UPDATE `condition` SET `resoult_type` = ?, `resoult_interval` = ? WHERE `chat_id` = ?");
			$stmt->execute([$resoult_type, $resoult_interval, $chat_id]);
		} elseif ($type == 'get') {
			$stmt = $pdo->prepare("SELECT `resoult_type`, `resoult_interval` FROM `condition` WHERE `chat_id` = ?");
			$stmt->execute([$chat_id]);
		}

		$res = $stmt->fetchAll();

		return $res;
	}

	public static function resoult($chat_id, $type, $interval)
	{
		$pdo = (new Db)->Db();

		$month = [1 => 'январь', 'февраль', 'март', 'апрель', 'май', 'июнь', 'июль', 'август', 'сентябрь', 'октябрь', 'ноябрь', 'декабрь'];

		$type_answer = match ($type) {
			'+' => 'Доходы',
			'-' => 'Расходы',
			default => 'В общем'
		};

		$interval_answer = match ($interval) {
			'day' => 'сегодня',
			'month' => $month[date('n')]
		};

		$answer = "<b>$type_answer за $interval_answer:</b>\n" . PHP_EOL;

		if ($interval == 'day') {
			if ($type == '+' or $type == '-') {
				$stmt = $pdo->prepare("SELECT SUM(`amount`) AS `amount`, `type`, `category` FROM `records` WHERE `chat_id` = ? AND `type` = ? AND DATE(`date&time`) = DATE(?) GROUP BY `category`");
			} elseif ($type == NULL) {
				$stmt = $pdo->prepare("SELECT SUM(`amount`) AS `amount`, `type` FROM `records` WHERE `chat_id` = ? AND DATE(`date&time`) = DATE(?) GROUP BY `type`");
			}
		} elseif ($interval == 'month') {
			if ($type == '+' or $type == '-') {
				$stmt = $pdo->prepare("SELECT SUM(`amount`) AS `amount`, `type`, `category` FROM `records` WHERE `chat_id` = ? AND `type` = ? AND DATE(`date&time`) BETWEEN DATE(?) AND DATE(?) GROUP BY `category`");
			} elseif ($type == NULL) {
				$stmt = $pdo->prepare("SELECT SUM(`amount`) AS `amount`, `type` FROM `records` WHERE `chat_id` = ? AND DATE(`date&time`) BETWEEN DATE(?) AND DATE(?) GROUP BY `type`");
			}
		}

		if ($type != NULL) {
			if ($interval == 'day') {
				$stmt->execute([$chat_id, $type, date('Y-m-d')]);
			} elseif ($interval == 'month') {
				$stmt->execute([$chat_id, $type, date('Y-m-') . '01', date('Y-m-d')]);
			}
		} else {
			if ($interval == 'day') {
				$stmt->execute([$chat_id, date('Y-m-d')]);
			} elseif ($interval == 'month') {
				$stmt->execute([$chat_id, date('Y-m-') . '01', date('Y-m-d')]);
			}
		}

		if ($type == '+' or $type == '-') {
			foreach ($stmt->fetchAll() as $item)
				$answer .= "{$item['category']} - {$item['amount']}\n" . PHP_EOL;
		} elseif ($type == NULL) {
			foreach ($stmt->fetchAll() as $item)
				$item['type'] == '+' ? $plus = $item['amount'] : $minus = $item['amount'];

			$plus = $plus ?? 0;
			$minus = $minus ?? 0;

			$answer .= "Доходов: $plus, расходов: $minus\nИтог за $interval_answer: " . $plus - $minus;
		}

		return $answer;
	}

	public static function get_more_resoults($chat_id, $type, $interval)
	{
		$pdo = (new Db)->Db();

		$month = [1 => 'январь', 'февраль', 'март', 'апрель', 'май', 'июнь', 'июль', 'август', 'сентябрь', 'октябрь', 'ноябрь', 'декабрь'];

		$type_answer = match ($type) {
			'+' => 'Доходы',
			'-' => 'Расходы',
			default => 'В общем'
		};

		$interval_answer = match ($interval) {
			'day' => 'сегодня',
			'month' => $month[date('n')]
		};

		$answer = "Подробнее про \"$type_answer за $interval_answer\"\n\n";

		if ($type == '+' or $type == '-') {
			if ($interval == 'day') {
				$stmt = $pdo->prepare("SELECT `date&time`, `amount`, `category`, `description` FROM `records` WHERE `chat_id` = ? AND `type` = ? AND DATE(`date&time`) = DATE(?)");
				$stmt->execute([$chat_id, $type, date('Y-m-d')]);
			} else {
				$stmt = $pdo->prepare("SELECT `date&time`, `amount`, `category`, `description` FROM `records` WHERE `chat_id` = ? AND `type` = ? AND DATE(`date&time`) BETWEEN DATE(?) AND DATE(?)");
				$stmt->execute([$chat_id, $type, date('Y-m-') . '01', date('Y-m-d')]);
			}

			foreach ($stmt->fetchAll() as $item) {
				$answer .= $interval == 'day' ? substr($item['date&time'], 11, 5) . " = {$item['amount']} - {$item['category']}. Описание: {$item['description']}\n\n" : str_replace('-', '.', substr($item['date&time'], 5, 11)) . " = {$item['amount']} - {$item['category']}. Описание: {$item['description']}\n\n";
			}
		} else {
			if ($interval == 'day') {
				$stmt = $pdo->prepare("SELECT `date&time`, `type`, `amount`, `category`, `description` FROM `records` WHERE `chat_id` = ? AND DATE(`date&time`) = DATE(?)");
				$stmt->execute([$chat_id, date('Y-m-d')]);
			} else {
				$stmt = $pdo->prepare("SELECT `date&time`, `type`, `amount`, `category`, `description` FROM `records` WHERE `chat_id` = ? AND DATE(`date&time`) BETWEEN DATE(?) AND DATE(?)");
				$stmt->execute([$chat_id, date('Y-m-') . '01', date('Y-m-d')]);
			}

			foreach ($stmt->fetchAll() as $item) {
				$answer .= $interval == 'day' ? substr($item['date&time'], 11, 5) . " = {$item['type']}{$item['amount']} - {$item['category']}. Описание: {$item['description']}\n\n" : str_replace('-', '.', substr($item['date&time'], 5, 11)) . " = {$item['type']}{$item['amount']} - {$item['category']}. Описание: {$item['description']}\n\n";
			}
		}

		return $answer;
	}

	public static function answer_to_delete($chat_id, $type, $delete_interval = NULL)
	{
		$pdo = (new Db)->Db();

		if ($type == 'set') {
			$stmt = $pdo->prepare("UPDATE `condition` SET `delete_interval` = ? WHERE `chat_id` = ?");
			$stmt->execute([$delete_interval, $chat_id]);
		} elseif ($type == 'get') {
			$stmt = $pdo->prepare("SELECT `delete_interval` FROM `condition` WHERE `chat_id` = ?");
			$stmt->execute([$chat_id]);
		}

		$res = $stmt->fetchAll();

		return $res;
	}

	public static function delete($chat_id, $delete_interval)
	{
		$pdo = (new Db)->Db();

		if ($delete_interval == 'last') {
			$stmt = $pdo->prepare("DELETE FROM `records` WHERE `chat_id` = ? ORDER BY id DESC LIMIT 1");
			$stmt->execute([$chat_id]);
		} elseif ($delete_interval == 'month') {
			$stmt = $pdo->prepare("DELETE FROM `records` WHERE `chat_id` = ? AND DATE(`date&time`) BETWEEN DATE(?) AND DATE(?)");
			$stmt->execute([$chat_id, date('Y-m-') . '01', date('Y-m-d')]);
		} elseif ($delete_interval == 'all') {
			$stmt = $pdo->prepare("DELETE FROM `records` WHERE `chat_id` = ?");
			$stmt->execute([$chat_id]);
		}
	}
}
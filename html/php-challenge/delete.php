<?php
session_start();
require('dbconnect.php');

if (isset($_SESSION['id'])) {
	$id = $_REQUEST['id'];
	
	// 投稿を検査する
	$messages = $db->prepare('SELECT * FROM posts WHERE id=?');
	$messages->execute(array($id));
	$message = $messages->fetch();

	// リツイートされていたら１減らす
	if (($message['reply_post_id']) >= 1 ) {
		$update = $db->prepare('UPDATE posts SET count_retweeted = count_retweeted - 1 WHERE id = ?');
		$update->execute(array(
			$message['reply_post_id']
		));
	}

	if ($message['member_id'] == $_SESSION['id']) {
		// 削除する
		$del = $db->prepare('DELETE FROM posts WHERE id=?');
		$del->execute(array($id));
	}
}

header('Location: index.php'); exit();
?>

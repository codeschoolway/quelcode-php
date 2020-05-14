<?php
session_start();
require('dbconnect.php');

if (isset($_SESSION['id']) && $_SESSION['time'] + 3600 > time()) {
	// ログインしている
	$_SESSION['time'] = time();

	$members = $db->prepare('SELECT * FROM members WHERE id=?');
	$members->execute(array($_SESSION['id']));
	$member = $members->fetch();
} else {
	// ログインしていない
	header('Location: login.php'); exit();
}

// 投稿を記録する
if (!empty($_POST)) {
	if ($_POST['message'] != '') {
		$message = $db->prepare('INSERT INTO posts SET member_id=?, message=?, reply_post_id=?, created=NOW()');
		$message->execute(array(
			$member['id'],
			$_POST['message'],
			$_POST['reply_post_id']
		));

		$getId = $db->prepare('SELECT LAST_INSERT_ID()');
		$getId->execute(array());
		$theId = $getId->fetch();
		
		$postTwData = $db->prepare('SELECT * FROM posts WHERE id = ?');
		$postTwData->execute(array(
			$theId['LAST_INSERT_ID()']
		));
		$theTwData = $postTwData->fetch();

		$likeTwData = $db->prepare('INSERT INTO tweets SET member_id=?, post_id=?');
		$likeTwData->execute(array(
			$theTwData['member_id'],
			$theTwData['id']
		));
		// /**** 各投稿に対応したテーブルtweetsの列を作る

		// **** 各投稿に対応したテーブルlikesの列を作る
		$postData = $db->prepare('SELECT * FROM posts WHERE id = ?');
		$postData->execute(array(
			$theId['LAST_INSERT_ID()']
		));
		$theData = $postData->fetch();

		$likeData = $db->prepare('INSERT INTO likes SET member_id=?, post_id=?');
		$likeData->execute(array(
			$theData['member_id'],
			$theData['id']
		));
		// /**** 各投稿に対応したテーブルlikesの列を作る
		
		header('Location: index.php'); exit();
	}
}

// 投稿を取得する
$page = $_REQUEST['page'];
if ($page == '') {
	$page = 1;
}
$page = max($page, 1);

// 最終ページを取得する
$counts = $db->query('SELECT COUNT(*) AS cnt FROM posts');
$cnt = $counts->fetch();
$maxPage = ceil($cnt['cnt'] / 5);
$page = min($page, $maxPage);

$start = ($page - 1) * 5;
$start = max(0, $start);

$posts = $db->prepare('SELECT m.id, m.name, m.picture, p.* FROM members m, posts p WHERE m.id=p.member_id ORDER BY p.created DESC LIMIT ?, 5');
$posts->bindParam(1, $start, PDO::PARAM_INT);
$posts->execute();

// 返信の場合
// $_REQUEST['res']にはid（投稿済みメッセージのid）が格納されている。URLに表示される
if (isset($_REQUEST['res'])) {
	$response = $db->prepare('SELECT m.name, m.picture, p.* FROM members m, posts p WHERE m.id=p.member_id AND p.id=? ORDER BY p.created DESC');
	$response->execute(array($_REQUEST['res']));

	$table = $response->fetch();
	$message = '@' . $table['name'] . ' ' . $table['message'];
}

// **** リツイートボタン ***********
if (isset($_REQUEST['retweeted'])) {
	updateTables($_REQUEST['retweeted'], 'tweets', 'is_tweet', 'count_retweeted');
}
// /**** リツイートボタン ***********


// **** いいねボタン ******
if (isset($_REQUEST['like'])) {
	updateTables($_REQUEST['like'], 'likes', 'is_like', 'count_like');
}
// /**** いいねボタン ******


// *** いいねとリツイートの更新 *******
function updateTables($request, $table, $isStatus, $countStatus) {
	require('dbconnect.php');
	
	$checkLikes = $db->prepare('SELECT * FROM ' . $table . ' WHERE member_id = ' . $_SESSION['id'] . ' and post_id = ?');
	$checkLikes->execute(array(
		$request
	));
	$allLikes = $checkLikes->fetch();

	$add = 1;
	$add = ($allLikes[''.$isStatus.''] === '0' ? $add : (- ($add)));
	$currentLike = ($allLikes[''.$isStatus.''] === '0' ? true : false);

	$update = $db->prepare('UPDATE posts SET ' . $countStatus . ' = ' . $countStatus . ' + ? WHERE id = ?');
	$update->execute(array(
		$add,
		$request
	));

	$updateLikes = $db->prepare('UPDATE ' . $table . ' SET ' . $isStatus . ' = ? WHERE post_id = ?');
	$updateLikes->execute(array(
		$currentLike,
		$request
	));

	if ($table === 'tweets') {
		updateCountRetweet($request);
	}

	header('Location: index.php'); exit();
}
// /*** いいねとリツイートの更新 *******


// ****** いいねの状態を確認 ******
function checkIsLikeStatus($postId) {
	require('dbconnect.php');

	$status = $db->prepare('SELECT * FROM likes WHERE member_id = ? and post_id = ?');
	$status->execute(array(
		$_SESSION['id'],
		$postId
	));
	
	$isLike = $status->fetch();
	return $isLike;
}
// /****** いいねの状態を確認 ******


function updateCountRetweet($id) {
	require('dbconnect.php');

	$checkLikes = $db->prepare('SELECT reply_post_id FROM posts WHERE id = ?');
	$checkLikes->execute(array(
		$id
	));
	$allLikes = $checkLikes->fetch();

	// postsのreply_post_id
	$replyPostId = $allLikes['reply_post_id'];
	// reply_post_idが0より大きいということはリツイートされている
	if ($replyPostId > '0') {
		$checkLikes = $db->prepare('SELECT * FROM tweets WHERE member_id = ' . $_SESSION['id'] . ' and post_id = ?');
		$checkLikes->execute(array(
			$_REQUEST['retweeted']
		));
		$allLikes = $checkLikes->fetch();

		$add = 1;
		$add = ($allLikes['is_tweet'] === '0' ? (- ($add)) : $add);
		$currentLike = ($allLikes['is_tweet'] === '0' ? false : true);

		$update = $db->prepare('UPDATE posts SET count_retweeted  = count_retweeted + ? WHERE id = ?');
		$update->execute(array(
			$add,
			$replyPostId
		));
	
		// 1か０のいずれかのみ挿入
		$updateLikes = $db->prepare('UPDATE tweets SET is_tweet = ? WHERE member_id = ' . $_SESSION['id'] . ' and post_id = ?');
		$updateLikes->execute(array(
			$currentLike,
			$replyPostId
		));
	}
}

// htmlspecialcharsのショートカット
function h($value) {
	return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

// 本文内のURLにリンクを設定します
function makeLink($value) {
	return mb_ereg_replace("(https?)(://[[:alnum:]\+\$\;\?\.%,!#~*/:@&=_-]+)", '<a href="\1\2">\1\2</a>' , $value);
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<meta http-equiv="X-UA-Compatible" content="ie=edge">
    <link href="https://use.fontawesome.com/releases/v5.6.1/css/all.css" rel="stylesheet">
	<title>ひとこと掲示板</title>
	<link rel="stylesheet" href="style.css" />
</head>

<body>
	<div id="wrap">
	<div id="head">
		<h1>ひとこと掲示板</h1>
	</div>
	<div id="content">
		<div style="text-align: right"><a href="logout.php">ログアウト</a></div>
			<form action="" method="post">
			<dl>
				<dt><?php echo h($member['name']); ?>さん、メッセージをどうぞ</dt>
				<dd>
				<textarea name="message" cols="50" rows="5"><?php echo h($message); ?></textarea>
				<input type="hidden" name="reply_post_id" value="<?php echo h($_REQUEST['res']); ?>" />
				</dd>
			</dl>
			<div>
				<p><input type="submit" value="投稿する" /></p>
			</div>
		</form>
			<?php
			foreach ($posts as $post): ?>
				<div class="msg">
					<img src="member_picture/<?php echo h($post['picture']); ?>" width="48" height="48" alt="<?php echo h($post['name']); ?>" />
					<p><?php echo makeLink(h($post['message'])); ?><span class="name">（<?php echo h($post['name']); ?>）</span>
					[<a href="index.php?res=<?php echo h($post['id']); ?>">Re</a>]

					<!-- ****** リツイートボタン ****** -->
					<?php if (($_SESSION['id'] == $post['member_id'])): ?>
						<?php if (($post['count_retweeted'] > 0) ): ?>
						<a href="index.php?retweeted=<?php echo h($post['id']); ?>"style="color: #16BF63;"><i class="fas fa-retweet"></i></a>
						<?php else: ?>
						<!-- リツイート可能 -->
						<a href="index.php?retweeted=<?php echo h($post['id']); ?>" style="color: #828181"><i class="fas fa-retweet"></i></a>
						<?php endif; ?>

						<?php if ($post['count_retweeted'] > 0): ?>
						<?php echo $post['count_retweeted']; ?>
						<?php endif;?>
					<?php endif;?>
					<!-- / ****** リツイートボタン ****** -->

					<!-- ****** いいねボタン ****** -->
					<?php if (($_SESSION['id'] == $post['member_id'])): ?>
						<?php $likeStatus = checkIsLikeStatus($post['id']); ?>
						<?php if (($likeStatus['is_like'] !== '0')  && ($post['count_like'] > 0) ): ?>
						<a href="index.php?like=<?php echo h($post['id']); ?>" style="color: red"><i class="fas fa-heart"></i></a>
						<?php else: ?>
						<a href="index.php?like=<?php echo h($post['id']); ?>" style="color: #828181"><i class="fas fa-heart"></i></a>
						<? endif; ?>

						<?php if ($post['count_like'] > 0): ?>
						<?php echo $post['count_like']; ?>
						<?php endif;?>
					<?php endif;?>
					<!-- / ****** いいねボタン ****** -->
					</p>

					<p class="day"><a href="view.php?id=<?php echo h($post['id']); ?>"><?php echo h($post['created']); ?></a>
					<?php if ($post['reply_post_id'] > 0):?>
						<a href="view.php?id=<?php echo h($post['reply_post_id']); ?>">
						返信元のメッセージ</a>
					<?php endif; ?>
					<?php if ($_SESSION['id'] == $post['member_id']): ?>
						[<a href="delete.php?id=<?php echo h($post['id']); ?>" style="color: #F33;">削除</a>]
					<?php endif; ?>
					</p>
				</div>
			<?php endforeach; ?>

			<ul class="paging">
			<?php if ($page > 1) { ?>
				<li><a href="index.php?page=<?php print($page - 1); ?>">前のページへ</a></li>
			<?php } else { ?>
				<li>前のページへ</li>
			<?php } ?>
			<?php if ($page < $maxPage) { ?>
				<li><a href="index.php?page=<?php print($page + 1); ?>">次のページへ</a></li>
			<?php } else { ?>
				<li>次のページへ</li>
			<?php } ?>
			</ul>
		</div>
	</div>
</body>
</html>

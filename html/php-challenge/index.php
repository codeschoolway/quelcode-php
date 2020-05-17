<?php
session_start();
require_once('dbconnect.php');

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


// *** いいねとリツイートのオン/オフの切り替え・更新 *******
function updateTables($request, $table, $isStatus, $countStatus) {
	global $db;
	
	$checkLikes = $db->prepare('SELECT * FROM ' . $table . ' WHERE member_id = ' . $_SESSION['id'] . ' and post_id = ?');
	$checkLikes->execute(array(
		$request
	));
	$allLikes = $checkLikes->fetch();

	if ($allLikes === false) {
		// $allLikesがfalseならログイン中のユーザーのmember_idを使って該当テーブルに新規で列を作る
		$likeData = $db->prepare('INSERT INTO ' . $table . ' SET member_id=?, post_id=?');
		$likeData->execute(array(
			$_SESSION['id'],
			$request
		));
	}
	
	$add = 1;
	$add = ($allLikes[''.$isStatus.''] === '0' ? $add : (- ($add)));
	$currentLike = ($allLikes[''.$isStatus.''] === '0' ? true : false);

	// いいねとリツイートのカウントを増減
	$update = $db->prepare('UPDATE posts SET ' . $countStatus . ' = ' . $countStatus . ' + ? WHERE id = ?');
	$update->execute(array(
		$add,
		$request
	));

	$updateLikes = $db->prepare('UPDATE ' . $table . ' SET ' . $isStatus . ' = ? WHERE member_id=? and post_id = ?');
	$updateLikes->execute(array(
		$currentLike,
		$_SESSION['id'],
		$request
	));

	// リツイートボタンが押されたらそのリツイートの表示位置を一番上にする（postsテーブルのcreatedをリツイートされた日時に置き換える）
	if ($table === 'tweets') {
		// $addが１ならリツイートがオンされた
		if ($add === 1) {
			// tweetsテーブルにリツイートされた時間を登録する
			$dateCreated = date("Y-m-d H:i:s");
			//$dateCreated = 'NOW()';
			$eitherDate = 'retweeted';

			updateDates($eitherDate, $dateCreated, $request);

			// tweetsテーブルにpostsのcreatedを登録する
			$getCreatedPosts = $db->prepare('SELECT created FROM posts WHERE id = ?');
			$getCreatedPosts->execute(array(
				$request
			));
			//$ret = $getCreated->rowCount();
			$createdPosts = $getCreatedPosts->fetch();
			//var_dump($created['created']);
			$dateCreatedPosts = $createdPosts['created'];
			$eitherDate = 'origin';

			updateDates($eitherDate, $dateCreatedPosts, $request);

			// postsのcreatedにリツイートされた日時を登録
			updateCreatedPosts($dateCreated, $request);

		// $addが１ではないならリツイートが取り消された
		} else {

			// 取り消すリツイートの投稿者のmemberIdを取得
			$getMemberId = $db->prepare('SELECT member_id FROM posts WHERE id = ?');
			$getMemberId->execute(array(
				$request
			));

			$aryMemberId = $getMemberId->fetch();
			$memberId = $aryMemberId['member_id'];

			$getOrigin = $db->prepare('SELECT * FROM tweets WHERE member_id = ? AND post_id = ?');
			$getOrigin->execute(array(
				$memberId,
				$request
			));
			$theOrigin = $getOrigin->fetch();
			$originCreated = $theOrigin['origin_created'];

			updateCreatedPosts($originCreated, $request);
		}

		updateCountRetweet($request);
	}

	header('Location: index.php'); exit();
}

function updateCreatedPosts($dateCreated, $request) {
	global $db;
	$updateCreated = $db->prepare('UPDATE posts SET created=:created WHERE id=:id');
	$updateCreated->bindParam(':created', $dateCreated);
	$updateCreated->bindParam(':id', $request);
	$updateCreated->execute();
}

// /*** いいねとリツイートの更新 *******
function updateDates($eitherDate, $dateCreated, $request) {
	global $db;
	$memberId = (int)$_SESSION['id'];
	// ******** tweetsテーブルのmember_id($_SESSION['id'])とpost_id($request)を使ってリツイートされた日時をtweetsテーブルのretweeted_createdに挿入する
	// ******** リツイートされたツイートが最初に作られた日時をtweetsテーブルのorigin_createdに挿入する
	$updateCreated = $db->prepare('UPDATE tweets SET ' . $eitherDate . '_created=:created WHERE member_id=:memberId and post_id=:id');
	$updateCreated->bindParam(':created', $dateCreated);
	$updateCreated->bindParam(':memberId', $memberId);
	$updateCreated->bindParam(':id', $request);
	$updateCreated->execute();

}

function updateCountRetweet($id) {
	global $db;

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

// ****** いいねの状態を確認 ******
function checkIsLikeStatus($postId) {
	global $db;

	$status = $db->prepare('SELECT * FROM likes WHERE member_id = ? and post_id = ?');
	$status->execute(array(
		$_SESSION['id'],
		$postId
	));
	
	$isLike = $status->fetch();
	return $isLike;
}
// /****** いいねの状態を確認 ******

function checkIsRetweeted($postId) {
	global $db;

	$checkTweeted = $db->prepare('SELECT is_tweet from tweets WHERE member_id = ? and post_id = ?');
	$checkTweeted->execute(array(
		$_SESSION['id'],
		$postId
	));
	$isTweeted = $checkTweeted->fetch();
	if (!($isTweeted['is_tweet'])) {
		return false;

	}
	return true;
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

						<?php if(checkIsRetweeted($post['id'])): ?>	
							<a href="index.php?retweeted=<?php echo h($post['id']); ?>"style="color: #16BF63;"><i class="fas fa-retweet"></i></a>
						<?php else: ?>
						<!-- リツイート可能 -->
							<a href="index.php?retweeted=<?php echo h($post['id']); ?>" style="color: #828181"><i class="fas fa-retweet"></i></a>
						<?php endif; ?>

						<?php if ($post['count_retweeted'] > 0): ?>
							<?php echo $post['count_retweeted']; ?>
						<?php endif;?>
					<!-- / ****** リツイートボタン ****** -->

					<!-- ****** いいねボタン ****** -->
						<?php $likeStatus = checkIsLikeStatus($post['id']); ?>
						<?php if (($likeStatus['is_like'] !== '0')  && ($post['count_like'] > 0) ): ?>
							<a href="index.php?like=<?php echo h($post['id']); ?>" style="color: red"><i class="fas fa-heart"></i></a>
						<?php else: ?>
							<a href="index.php?like=<?php echo h($post['id']); ?>" style="color: #828181"><i class="fas fa-heart"></i></a>
						<? endif; ?>

						<?php if ($post['count_like'] > 0): ?>
							<?php echo $post['count_like']; ?>
						<?php endif;?>
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

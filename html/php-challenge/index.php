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

		header('Location: index.php'); exit();
	}
}

// ****** RTボタン **********************
// RT作成
if (isset($_REQUEST['retweet_id'])) {
	$getAllPosts = $db->prepare('SELECT * FROM posts WHERE id=?');
	$getAllPosts->execute(array(
		$_REQUEST['retweet_id']
	));
	$allPosts = $getAllPosts->fetch();

	$rtMessage = "RT " . $allPosts['message']; 
	$likeData = $db->prepare('INSERT INTO posts SET message=?, member_id=?, retweet_origin_id=?, created=NOW()');
	$likeData->execute(array(
		$rtMessage,
		$_SESSION['id'],
		$_REQUEST['retweet_id']
	));

	header('Location: index.php'); exit();
}

// RT削除
if (isset($_REQUEST['delete_rt_origin_id'])) {
	$delete = $db->prepare(('DELETE FROM posts WHERE member_id =? AND retweet_origin_id=?'));
	$delete->execute(array(
		$_SESSION['id'],
		$_REQUEST['delete_rt_origin_id']
	));

	header('Location: index.php'); exit();
}

function checkIfRtOrigin($id) {
	global $db;

	$getRow = $db->prepare('SELECT * FROM posts WHERE member_id=? and retweet_origin_id=?');
	$getRow->execute(array(
		$_SESSION['id'],
		$id
	));
	$row = $getRow->fetch();
	if($row !== false) {
		return true;
	} else {
		return false;
	}
}
// ****** RTボタン ここまで *************


// ****** likeボタン **************
if (isset($_REQUEST['like_id'])) {
	
	$likeData = $db->prepare('INSERT INTO likes SET member_id=?, post_id=?');
	$likeData->execute(array(
		$_SESSION['id'],
		$_REQUEST['like_id']
	));

	header('Location: index.php'); exit();
}

if (isset($_REQUEST['delete_post_id'])) {
	$delete = $db->prepare(('DELETE FROM likes WHERE member_id =? AND post_id=?'));
	$delete->execute(array(
		$_SESSION['id'],
		$_REQUEST['delete_post_id']
	));

	header('Location: index.php'); exit();

}

function getCountRt($id) {
	global $db;

	$getCounts = $db->prepare('SELECT COUNT(*) AS cnt FROM posts WHERE retweet_origin_id =?');
	$getCounts->execute(array(
		$id
	));
	$count = $getCounts->fetch();

	return $count['cnt'];
}
// ***** likeボタン ここまで ********************

function checkIfPostId($postId) {
	global $db;

	$getRow = $db->prepare('SELECT * FROM likes WHERE member_id=? and post_id=?');
	$getRow->execute(array(
		$_SESSION['id'],
		$postId
	));
	$row = $getRow->fetch();
	if($row !== false) {
		return true;
	} else {
		return false;
	}
}

function checkIfRt($id) {
	global $db;
	$getCounts = $db->prepare('SELECT retweet_origin_id FROM posts WHERE id=?');
	$getCounts->execute(array(
		$id
	));
	$count = $getCounts->fetch();
	if($count['retweet_origin_id'] > '0') {
		return true;
	} else {
		return false;
	}
}

function getCountLike($postId) {
	global $db;
	$getCounts = $db->prepare('SELECT COUNT(*) AS cnt FROM likes WHERE post_id=?');
	$getCounts->execute(array(
		$postId
	));
	$theId = $getCounts->fetch();
	return $theId['cnt'];
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

$posts = $db->prepare('SELECT m.name, m.picture, p.* FROM members m, posts p WHERE m.id=p.member_id ORDER BY p.created DESC LIMIT ?, 5');
$posts->bindParam(1, $start, PDO::PARAM_INT);
$posts->execute();

// 返信の場合
if (isset($_REQUEST['res'])) {
	$response = $db->prepare('SELECT m.name, m.picture, p.* FROM members m, posts p WHERE m.id=p.member_id AND p.id=? ORDER BY p.created DESC');
	$response->execute(array($_REQUEST['res']));

	$table = $response->fetch();
	$message = '@' . $table['name'] . ' ' . $table['message'];
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
        <p>
          <input type="submit" value="投稿する" />
        </p>
      </div>
    </form>

<?php foreach ($posts as $post): ?>
    <div class="msg">
    <img src="member_picture/<?php echo h($post['picture']); ?>" width="48" height="48" alt="<?php echo h($post['name']); ?>" />
	<p><?php echo makeLink(h($post['message'])); ?><span class="name">（<?php echo h($post['name']); ?>）</span>

	<?php echo $post['id']; ?>

	[<a href="index.php?res=<?php echo h($post['id']); ?>">Re</a>]

	<?php 
	if($post['retweet_origin_id'] > '0') {
		$rt_origin_id = $post['retweet_origin_id'];
	} else {
		$rt_origin_id = $post['id'];
	}
	?>

	<!-- **** Retweetボタン **** -->
	<?php if((checkIfRtOrigin($rt_origin_id))) : ?>	
		<a href="index.php?delete_rt_origin_id=<?php echo h($rt_origin_id); ?>" style="color: #16BF63;"><i class="fas fa-retweet"></i></a>
	<?php else: ?>	
		<a href="index.php?retweet_id=<?php echo h($rt_origin_id); ?>" style="color: #828181;"><i class="fas fa-retweet"></i></a>
	<?php endif; ?>
	<?php echo h(getCountRt($rt_origin_id)); ?>
	<!-- **** Rtweetボタン ここまで **** -->
	
	<!-- **** Likeボタン **** -->
	<?php if(checkIfPostId($rt_origin_id) ) : ?>
		<a href="index.php?delete_post_id=<?php echo h($rt_origin_id); ?>" style="color: red"><i class="fas fa-heart"></i></a>
	<?php else: ?>
		<a href="index.php?like_id=<?php echo h($rt_origin_id); ?>" style="color: #828181;"><i class="fas fa-heart"></i></a>
	<?php endif; ?>
	<?php echo h(getCountLike($rt_origin_id)); ?>
	<!-- **** Likeボタン ここまで **** -->
	</p>

    <p class="day"><a href="view.php?id=<?php echo h($post['id']); ?>"><?php echo h($post['created']); ?></a>
		<?php
if ($post['reply_post_id'] > 0):
?>
<a href="view.php?id=<?php echo
h($post['reply_post_id']); ?>">
返信元のメッセージ</a>
<?php
endif;
?>
<?php
if ($_SESSION['id'] == $post['member_id']):
?>
[<a href="delete.php?id=<?php echo h($post['id']); ?>"
style="color: #F33;">削除</a>]
<?php
endif;
?>
    </p>
    </div>
<?php
endforeach;
?>

<ul class="paging">
<?php
if ($page > 1) {
?>
<li><a href="index.php?page=<?php print($page - 1); ?>">前のページへ</a></li>
<?php
} else {
?>
<li>前のページへ</li>
<?php
}
?>
<?php
if ($page < $maxPage) {
?>
<li><a href="index.php?page=<?php print($page + 1); ?>">次のページへ</a></li>
<?php
} else {
?>
<li>次のページへ</li>
<?php
}
?>
</ul>
  </div>
</div>
</body>
</html>
<?php
session_start();

// 1. データベース接続 (ファイルがなければ自動生成されます)
try {
    $db = new PDO('sqlite:quiz.db');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // テーブルがなければ作成 (今回は「日本語の意味」から「英単語」を当てる形式)
    $db->exec("CREATE TABLE IF NOT EXISTS words (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        question TEXT NOT NULL,
        answer TEXT NOT NULL
    )");

    // サンプルデータが空なら投入
    $count = $db->query("SELECT COUNT(*) FROM words")->fetchColumn();
    if ($count == 0) {
        $stmt = $db->prepare("INSERT INTO words (question, answer) VALUES (?, ?)");
        $samples = [
            ["科学", "science"],
            ["コンピュータ", "computer"],
            ["素晴らしい、素晴らしい", "excellent"],
            ["環境", "environment"],
            ["可能性", "possibility"]
        ];
        foreach ($samples as $sample) {
            $stmt->execute($sample);
        }
    }
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

// 2. ゲームのリセット・初期化処理
if (isset($_GET['reset']) || !isset($_SESSION['quiz_queue'])) {
    $_SESSION['score'] = 0;
    $_SESSION['current_step'] = 0;
    
    // DBからランダムに5問取得してセッションに格納
    $stmt = $db->query("SELECT question, answer FROM words ORDER BY RANDOM() LIMIT 5");
    $_SESSION['quiz_queue'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$quiz_queue = $_SESSION['quiz_queue'];
$total_questions = count($quiz_queue);
$current_step = $_SESSION['current_step'];
$message = "";
$show_next = false;

// 3. 回答送信時の処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user_answer'])) {
    $correct_answer = $quiz_queue[$current_step]['answer'];
    // 前後の空白を取り除き、小文字に統一
    $user_input = trim($_POST['user_answer']);

    if (strtolower($user_input) === strtolower($correct_answer)) {
        $message = "⭕ 正解！ その調子です。";
        $_SESSION['score']++;
    } else {
        $message = "❌ 不正解... 正解は 「" . htmlspecialchars($correct_answer, ENT_QUOTES, 'UTF-8') . "」 でした。";
    }
    $show_next = true;
    $_SESSION['current_step']++;
}

// 4. 次の問題へ進む処理
if (isset($_POST['next'])) {
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>記述式・英単語クイズ</title>
    <style>
        body { font-family: sans-serif; margin: 40px; background: #f4f6f9; text-align: center; }
        .quiz-container { background: #fff; padding: 30px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); display: inline-block; max-width: 450px; width: 100%; box-sizing: border-box; }
        .question-box { font-size: 16px; color: #666; margin-bottom: 10px; }
        .japanese-word { font-size: 32px; font-weight: bold; color: #2c3e50; margin-bottom: 25px; }
        .input-text { width: 100%; padding: 12px; font-size: 18px; border: 2px solid #ddd; border-radius: 6px; box-sizing: border-box; text-align: center; margin-bottom: 15px; }
        .input-text:focus { border-color: #3498db; outline: none; }
        .btn { display: block; width: 100%; padding: 12px; background: #3498db; color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 16px; font-weight: bold; }
        .btn:hover { background: #2980b9; }
        .btn-next { background: #2ecc71; }
        .btn-next:hover { background: #27ae60; }
        .message-box { font-size: 18px; font-weight: bold; margin: 20px 0; padding: 10px; border-radius: 6px; background: #fdf2e9; color: #e67e22; }
    </style>
</head>
<body>

<div class="quiz-container">
    <h2>英単語記述クイズ</h2>

    <?php if ($current_step < $total_questions): ?>
        <?php 
        $current_quiz = $quiz_queue[$current_step]; 
        ?>
        <div class="question-box">問題 <?php echo ($current_step + 1); ?> / <?php echo $total_questions; ?></div>
        <p>次の日本語を意味する英単語を入力してください：</p>
        <div class="japanese-word"><?php echo htmlspecialchars($current_quiz['question'], ENT_QUOTES, 'UTF-8'); ?></div>

        <?php if (!$show_next): ?>
            <!-- 入力フォーム -->
            <form method="POST" autocomplete="off">
                <input type="text" name="user_answer" class="input-text" placeholder="ここに英語を入力" autofocus required>
                <button type="submit" class="btn">回答を送信</button>
            </form>
        <?php else: ?>
            <!-- 結果と次への遷移 -->
            <div class="message-box"><?php echo $message; ?></div>
            <form method="POST">
                <button type="submit" name="next" class="btn btn-next">次の問題へ</button>
            </form>
        <?php endif; ?>

    <?php else: ?>
        <!-- 終了画面 -->
        <h3>全問終了！</h3>
        <p style="font-size: 18px;">あなたのスコア: <strong><?php echo $_SESSION['score']; ?></strong> / <?php echo $total_questions; ?></p>
        <hr style="border: 0; border-top: 1px solid #eee; margin: 20px 0;">
        <a href="?reset=1" class="btn" style="text-decoration: none; display: block;">もう一度挑戦する</a>
    <?php endif; ?>
</div>

</body>
</html>

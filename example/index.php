<?php
require_once ('../scms.php');

function articleUrl($articleKey)
{
    return 'http://' . $_SERVER['HTTP_HOST'] . '?article=' . $articleKey;
}

function tagUrl($tagKey) 
{
    return 'http://' . $_SERVER['HTTP_HOST'] . '?tag=' . $tagKey;
}



$scms = new SCMS(
    dirname(__FILE__) . '/articles/', 
    dirname(__FILE__) . '/cache/', 
    'def12cd0ff9bbb9e1d59bc005f1e52ad',
    'articleUrl',
    'tagUrl'
);
$article = null;
$article_list = null;
$tag_list = null;

$title = 'Example';
$description = 'Example description';

if (isset($_GET['article'])) {
    $key = $_GET['article'];
    if ($key) {
        if (! $scms->articleExists($key)) {
            header("HTTP/1.1 301 Moved Permanently");
            header("Location: " . articleUrl(''));
            exit();
        }
        $article = $scms->getArticle($key);
        $title = $article->getTitle() . ' - ' . $title; 
        $description = $article->getDescription();
    } else {
        $article_list = $scms->getAllArticles();
        $title = 'List of all articles - ' . $title;
        $description = $title;
    }    
} else if (isset($_GET['tag'])) {
    $key = $_GET['tag'];
    if ($key) {
        if (! $scms->tagExists($key)) {
            header("HTTP/1.1 301 Moved Permanently");
            header("Location: " . tagUrl(''));
            exit();
        }
        $tag = $scms->getTag($key);
        $article_list = $scms->getArticlesForTag($tag);
        $title = 'Articles for tag ' . $tag->getTitle();
        $description = $title;
    } else {
        $tag_list = $scms->getAllTags();
        $title = 'List of all tags';
        $description = $title;
    }
}
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="cz" lang="cz">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<meta name="description" content="<?php echo $description; ?>" />
<title><?php echo $title; ?></title>
</head>
<body>
<?php 

if (isset($article)) {
    echo $article->getContent();
    $tags = $article->getTags();
    
    if ($tags) {
        echo '<h2>Tags</h2>';
        echo '<ul>';
        foreach ($tags as $tag) {
            echo '<li><a href="'. tagUrl($tag->getKey()) . '">' . $tag->getTitle() . '</a></li>';
        }
        echo '</ul>';
    }
    
    $related = $article->getRelated();
    if ($related) {
        echo '<h2>Related Articles</h2>';
        echo '<ul>';
        foreach ($related as $rel) {
            echo '<li><a href="' . articleUrl($rel->getKey()) . '">' . $rel->getTitle() . '</a></li>';
        }
        echo '</ul>';
    }
} else if (isset($article_list)) {
    echo '<ul>';
    foreach ($article_list as $article) {
        echo '<li><a href="' . articleUrl($article->getKey()) . '">' . $article->getTitle() . '</a></li>';
    }
    echo '</ul>';
} else if (isset($tag_list)) {
    asort($tag_list);
    echo '<ul>';
    foreach ($tag_list as $tag) {
        echo '<li><a href="' . tagUrl($tag->getKey()) . '">' . $tag->getTitle() . '</a> (' . count($tag->getArticleKeys()) . ')</li>';
    }
    echo '</ul>';
}
?>
</body>
</html>
